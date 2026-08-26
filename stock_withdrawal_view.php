<?php

require_once __DIR__ . '/stock_common.php';

$withdrawalId = (int)($_GET['id'] ?? $_POST['withdrawal_id'] ?? 0);
if ($withdrawalId <= 0) {
    http_response_code(404);
    die('ไม่พบใบเบิก');
}

function load_stock_withdrawal(mysqli $conn, int $withdrawalId): ?array
{
    $stmt = $conn->prepare(
        'SELECT w.*, s.company_name, u.name requester_name
         FROM stock_withdrawals w INNER JOIN suppliers s ON s.id = w.sup_id INNER JOIN users u ON u.id = w.requester_id
         WHERE w.id = ?'
    );
    $stmt->bind_param('i', $withdrawalId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

$withdrawal = load_stock_withdrawal($conn, $withdrawalId);
if (!$withdrawal || !stock_can_view_withdrawal(
    (string)$stock_actor['role'],
    (int)$stock_actor['id'],
    (int)$stock_actor['sup_id'],
    (int)$withdrawal['requester_id'],
    (int)$withdrawal['sup_id']
)) {
    http_response_code(403);
    die('คุณไม่มีสิทธิ์ดูใบเบิกนี้');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        stock_verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        $conn->begin_transaction();
        stock_lock_withdrawal($conn, $withdrawalId);
        if ($action === 'issue') {
            stock_require_manager($stock_actor);
            $itemId = (int)$_POST['item_id'];
            $quantity = (float)$_POST['quantity'];
            $itemStmt = $conn->prepare(
                'SELECT wi.*, p.sup_id, COALESCE(b.quantity, 0) available
                 FROM stock_withdrawal_items wi INNER JOIN stock_products p ON p.id = wi.product_id
                 INNER JOIN stock_balances b ON b.product_id = p.id
                 WHERE wi.id = ? AND wi.withdrawal_id = ? FOR UPDATE'
            );
            $itemStmt->bind_param('ii', $itemId, $withdrawalId);
            $itemStmt->execute();
            $item = $itemStmt->get_result()->fetch_assoc();
            $itemStmt->close();
            if (!$item || (int)$item['sup_id'] !== (int)$withdrawal['sup_id']) {
                throw new DomainException('ไม่พบรายการสินค้าในใบเบิก');
            }
            $remaining = (float)$item['requested_quantity'] - (float)$item['issued_quantity'] - (float)$item['cancelled_quantity'];
            $issueQuantity = stock_issue_quantity($remaining, (float)$item['available'], $quantity);
            $issueStmt = $conn->prepare('INSERT INTO stock_withdrawal_issues (withdrawal_item_id, issued_quantity, issued_by) VALUES (?, ?, ?)');
            $issueStmt->bind_param('idi', $itemId, $issueQuantity, $stock_actor['id']);
            $issueStmt->execute();
            $issueId = (int)$conn->insert_id;
            $issueStmt->close();
            $updateItem = $conn->prepare('UPDATE stock_withdrawal_items SET issued_quantity = issued_quantity + ? WHERE id = ?');
            $updateItem->bind_param('di', $issueQuantity, $itemId);
            $updateItem->execute();
            $updateItem->close();
            stock_apply_movement($conn, (int)$withdrawal['sup_id'], (int)$item['product_id'], 'out_withdrawal', -$issueQuantity, 'withdrawal_issue', $issueId, (int)$stock_actor['id'], 'จ่ายตามใบเบิก ' . $withdrawal['doc_no']);
            stock_refresh_withdrawal_status($conn, $withdrawalId);
            stock_flash('success', 'บันทึกจ่ายสินค้าแล้ว รอผู้ขอยืนยันจำนวนที่ได้รับจริง');
        } elseif ($action === 'confirm') {
            if ((int)$stock_actor['id'] !== (int)$withdrawal['requester_id']) {
                throw new DomainException('เฉพาะผู้ขอเบิกเท่านั้นที่ยืนยันรับสินค้าได้');
            }
            $issueId = (int)$_POST['issue_id'];
            $receivedQuantity = (float)$_POST['received_quantity'];
            $receiverNote = trim((string)($_POST['receiver_note'] ?? ''));
            $issueStmt = $conn->prepare(
                "SELECT i.*, wi.withdrawal_id FROM stock_withdrawal_issues i
                 INNER JOIN stock_withdrawal_items wi ON wi.id = i.withdrawal_item_id
                 WHERE i.id = ? AND wi.withdrawal_id = ? AND i.status = 'waiting_confirmation' FOR UPDATE"
            );
            $issueStmt->bind_param('ii', $issueId, $withdrawalId);
            $issueStmt->execute();
            $issue = $issueStmt->get_result()->fetch_assoc();
            $issueStmt->close();
            if (!$issue || $receivedQuantity < 0 || $receivedQuantity > (float)$issue['issued_quantity'] + 0.00001) {
                throw new DomainException('จำนวนที่ยืนยันรับไม่ถูกต้อง');
            }
            if ($receivedQuantity < (float)$issue['issued_quantity'] - 0.00001 && $receiverNote === '') {
                throw new DomainException('กรุณาระบุเหตุผลเมื่อได้รับไม่ครบตามที่ผู้ดูแลจ่าย');
            }
            $issueStatus = abs($receivedQuantity - (float)$issue['issued_quantity']) < 0.00001 ? 'confirmed' : 'discrepancy';
            $confirmStmt = $conn->prepare(
                'UPDATE stock_withdrawal_issues SET received_quantity = ?, received_by = ?, received_at = NOW(), receiver_note = NULLIF(?, \'\'), status = ? WHERE id = ?'
            );
            $confirmStmt->bind_param('dissi', $receivedQuantity, $stock_actor['id'], $receiverNote, $issueStatus, $issueId);
            $confirmStmt->execute();
            $confirmStmt->close();
            $itemUpdate = $conn->prepare('UPDATE stock_withdrawal_items SET received_quantity = received_quantity + ? WHERE id = ?');
            $itemUpdate->bind_param('di', $receivedQuantity, $issue['withdrawal_item_id']);
            $itemUpdate->execute();
            $itemUpdate->close();
            stock_refresh_withdrawal_status($conn, $withdrawalId);
            stock_flash('success', $issueStatus === 'confirmed' ? 'ยืนยันรับสินค้าแล้ว' : 'บันทึกผลต่างแล้ว ผู้ดูแล Stock จะตรวจสอบต่อ');
        } elseif ($action === 'resolve_discrepancy') {
            stock_require_manager($stock_actor);
            $issueId = (int)$_POST['issue_id'];
            $resolutionType = (string)($_POST['resolution_type'] ?? '');
            $resolutionNote = trim((string)($_POST['resolution_note'] ?? ''));
            if (!in_array($resolutionType, ['returned_to_stock', 'accepted_loss'], true) || $resolutionNote === '') {
                throw new DomainException('กรุณาเลือกวิธีจัดการและระบุเหตุผลของผลต่าง');
            }
            $issueStmt = $conn->prepare(
                "SELECT issue_row.*, wi.withdrawal_id, wi.product_id, p.sup_id
                 FROM stock_withdrawal_issues issue_row
                 INNER JOIN stock_withdrawal_items wi ON wi.id = issue_row.withdrawal_item_id
                 INNER JOIN stock_products p ON p.id = wi.product_id
                 WHERE issue_row.id = ? AND wi.withdrawal_id = ? AND issue_row.status = 'discrepancy' FOR UPDATE"
            );
            $issueStmt->bind_param('ii', $issueId, $withdrawalId);
            $issueStmt->execute();
            $issue = $issueStmt->get_result()->fetch_assoc();
            $issueStmt->close();
            if (!$issue) {
                throw new DomainException('ไม่พบผลต่างที่รอดำเนินการ');
            }
            $missingQuantity = stock_issue_discrepancy_quantity((float)$issue['issued_quantity'], (float)$issue['received_quantity']);
            $actionType = $resolutionType === 'returned_to_stock' ? 'discrepancy_return' : 'discrepancy_loss';
            if ($resolutionType === 'returned_to_stock') {
                stock_apply_movement(
                    $conn,
                    (int)$issue['sup_id'],
                    (int)$issue['product_id'],
                    'return',
                    $missingQuantity,
                    'withdrawal_issue',
                    $issueId,
                    (int)$stock_actor['id'],
                    'คืนส่วนต่างจากใบเบิก ' . $withdrawal['doc_no']
                );
                $itemUpdate = $conn->prepare('UPDATE stock_withdrawal_items SET issued_quantity = issued_quantity - ? WHERE id = ?');
                $itemUpdate->bind_param('di', $missingQuantity, $issue['withdrawal_item_id']);
                $itemUpdate->execute();
                $itemUpdate->close();
            }
            $resolveStmt = $conn->prepare(
                "UPDATE stock_withdrawal_issues
                 SET status = 'resolved', resolution_type = ?, resolution_quantity = ?, resolved_by = ?, resolved_at = NOW(), resolution_note = ?
                 WHERE id = ?"
            );
            $resolveStmt->bind_param('sdisi', $resolutionType, $missingQuantity, $stock_actor['id'], $resolutionNote, $issueId);
            $resolveStmt->execute();
            $resolveStmt->close();
            $actionStmt = $conn->prepare(
                'INSERT INTO stock_withdrawal_actions (withdrawal_id, withdrawal_item_id, issue_id, action_type, quantity, actor_id, reason)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $actionStmt->bind_param('iiisdis', $withdrawalId, $issue['withdrawal_item_id'], $issueId, $actionType, $missingQuantity, $stock_actor['id'], $resolutionNote);
            $actionStmt->execute();
            $actionStmt->close();
            stock_refresh_withdrawal_status($conn, $withdrawalId);
            stock_flash('success', $resolutionType === 'returned_to_stock'
                ? 'คืนส่วนต่างเข้า Stock แล้ว และยอดที่ยังต้องการกลับไปค้างในใบเดิม'
                : 'บันทึกส่วนต่างเป็นสินค้าสูญหายแล้ว');
        } elseif ($action === 'cancel_remaining') {
            if ((int)$stock_actor['id'] !== (int)$withdrawal['requester_id'] && $stock_actor['role'] !== 'admin') {
                throw new DomainException('เฉพาะผู้ขอเบิกเท่านั้นที่ยกเลิกยอดค้างได้');
            }
            $itemId = (int)$_POST['item_id'];
            $cancelReason = trim((string)($_POST['cancel_reason'] ?? ''));
            if ($cancelReason === '') {
                throw new DomainException('กรุณาระบุเหตุผลที่ยกเลิกยอดค้าง');
            }
            $itemStmt = $conn->prepare('SELECT * FROM stock_withdrawal_items WHERE id = ? AND withdrawal_id = ? FOR UPDATE');
            $itemStmt->bind_param('ii', $itemId, $withdrawalId);
            $itemStmt->execute();
            $item = $itemStmt->get_result()->fetch_assoc();
            $itemStmt->close();
            if (!$item) {
                throw new DomainException('ไม่พบรายการที่ต้องการยกเลิก');
            }
            $remaining = (float)$item['requested_quantity'] - (float)$item['issued_quantity'] - (float)$item['cancelled_quantity'];
            if ($remaining <= 0) {
                throw new DomainException('รายการนี้ไม่มียอดค้างให้ยกเลิก');
            }
            $cancelStmt = $conn->prepare('UPDATE stock_withdrawal_items SET cancelled_quantity = cancelled_quantity + ? WHERE id = ?');
            $cancelStmt->bind_param('di', $remaining, $itemId);
            $cancelStmt->execute();
            $cancelStmt->close();
            $actionStmt = $conn->prepare(
                "INSERT INTO stock_withdrawal_actions (withdrawal_id, withdrawal_item_id, action_type, quantity, actor_id, reason)
                 VALUES (?, ?, 'cancel_remaining', ?, ?, ?)"
            );
            $actionStmt->bind_param('iidis', $withdrawalId, $itemId, $remaining, $stock_actor['id'], $cancelReason);
            $actionStmt->execute();
            $actionStmt->close();
            stock_refresh_withdrawal_status($conn, $withdrawalId);
            stock_flash('success', 'ยกเลิกจำนวนที่ยังค้างในรายการนี้แล้ว');
        } else {
            throw new DomainException('คำสั่งไม่ถูกต้อง');
        }
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        stock_flash('error', stock_error_message($exception));
    }
    stock_redirect('stock_withdrawal_view.php?id=' . $withdrawalId);
}

$withdrawal = load_stock_withdrawal($conn, $withdrawalId);
$itemStmt = $conn->prepare(
    'SELECT wi.*, p.sku, p.name, p.unit, COALESCE(b.quantity, 0) available
     FROM stock_withdrawal_items wi INNER JOIN stock_products p ON p.id = wi.product_id
     LEFT JOIN stock_balances b ON b.product_id = p.id
     WHERE wi.withdrawal_id = ? ORDER BY wi.id'
);
$itemStmt->bind_param('i', $withdrawalId);
$itemStmt->execute();
$items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();
$issueStmt = $conn->prepare(
    'SELECT i.*, wi.product_id, p.name product_name, p.unit, issuer.name issuer_name, receiver.name receiver_name
     FROM stock_withdrawal_issues i INNER JOIN stock_withdrawal_items wi ON wi.id = i.withdrawal_item_id
     INNER JOIN stock_products p ON p.id = wi.product_id
     LEFT JOIN users issuer ON issuer.id = i.issued_by LEFT JOIN users receiver ON receiver.id = i.received_by
     WHERE wi.withdrawal_id = ? ORDER BY i.id DESC'
);
$issueStmt->bind_param('i', $withdrawalId);
$issueStmt->execute();
$issues = $issueStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$issueStmt->close();
$actionStmt = $conn->prepare(
    'SELECT actions.*, actor.name actor_name, p.name product_name, p.unit
     FROM stock_withdrawal_actions actions
     INNER JOIN stock_withdrawal_items wi ON wi.id = actions.withdrawal_item_id
     INNER JOIN stock_products p ON p.id = wi.product_id
     LEFT JOIN users actor ON actor.id = actions.actor_id
     WHERE actions.withdrawal_id = ? ORDER BY actions.id DESC'
);
$actionStmt->bind_param('i', $withdrawalId);
$actionStmt->execute();
$actions = $actionStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$actionStmt->close();
$flash = stock_take_flash();
include __DIR__ . '/header.php';
?>

<div class="max-w-6xl mx-auto p-4 md:p-8 space-y-6" data-stock-ui="procurement">
    <div><a href="<?= stock_can_manage($stock_actor['role']) ? 'stock_withdrawals.php' : 'stock_my_withdrawals.php' ?>" class="text-sm font-bold text-indigo-600 transition-colors hover:text-indigo-800 hover:underline"><i class="fas fa-arrow-left mr-1"></i><?= stock_can_manage($stock_actor['role']) ? 'กลับรายการใบเบิก' : 'กลับสถานะใบเบิกของฉัน' ?></a><div class="mt-3 flex flex-wrap items-center gap-3"><h1 class="text-2xl font-black text-slate-800 tracking-tight"><?= stock_e($withdrawal['doc_no']) ?></h1><span data-stock-withdrawal-status class="rounded-full px-3 py-1 text-xs font-semibold <?= stock_withdrawal_status_badge_class((string)$withdrawal['status']) ?>"><?= stock_e(stock_status_label($withdrawal['status'])) ?></span></div><p class="mt-2 text-sm text-slate-500"><?= stock_e($withdrawal['requester_name']) ?> · <?= stock_e($withdrawal['company_name']) ?> · <?= date('d/m/Y H:i', strtotime($withdrawal['created_at'])) ?></p></div>
    <?php stock_render_flash($flash); ?>
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><div class="text-xs font-bold uppercase tracking-wider text-slate-500">วัตถุประสงค์</div><div class="mt-2 text-slate-900"><?= nl2br(stock_e($withdrawal['purpose'])) ?></div></div>

    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-[850px] w-full text-sm"><thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="text-left px-4 py-3">สินค้า</th><th class="text-right px-4 py-3">ขอเบิก</th><th class="text-right px-4 py-3">จ่ายแล้ว</th><th class="text-right px-4 py-3">รับจริง</th><th class="text-right px-4 py-3">คงเหลือ Stock</th><th class="text-left px-4 py-3 w-64">ดำเนินการ</th></tr></thead><tbody class="divide-y divide-slate-100">
    <?php foreach ($items as $item): $remaining = max(0, (float)$item['requested_quantity'] - (float)$item['issued_quantity'] - (float)$item['cancelled_quantity']); $maxIssue = min($remaining, (float)$item['available']); ?>
        <tr class="align-top"><td class="px-4 py-3 font-semibold text-slate-900"><?= stock_e($item['name']) ?><?php if ((float)$item['cancelled_quantity'] > 0): ?><div class="text-xs text-slate-500">ยกเลิก <?= number_format((float)$item['cancelled_quantity'], 2) ?></div><?php endif; ?></td><td class="px-4 py-3 text-right"><?= number_format((float)$item['requested_quantity'], 2) ?> <?= stock_e($item['unit']) ?></td><td class="px-4 py-3 text-right"><?= number_format((float)$item['issued_quantity'], 2) ?></td><td class="px-4 py-3 text-right"><?= number_format((float)$item['received_quantity'], 2) ?></td><td class="px-4 py-3 text-right font-semibold"><?= number_format((float)$item['available'], 2) ?></td><td class="px-4 py-3">
        <?php if (stock_can_manage($stock_actor['role']) && $remaining > 0): ?><form method="post" class="flex gap-2"><input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>"><input type="hidden" name="action" value="issue"><input type="hidden" name="withdrawal_id" value="<?= $withdrawalId ?>"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><input type="number" name="quantity" required min="0.01" max="<?= $maxIssue ?>" step="0.01" value="<?= $maxIssue ?>" class="w-28 rounded-xl border border-slate-200 px-2 py-1.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" <?= $maxIssue <= 0 ? 'disabled' : '' ?>><button class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-indigo-700 disabled:bg-slate-300" <?= $maxIssue <= 0 ? 'disabled' : '' ?>>จ่ายสินค้า</button></form><?php if ($maxIssue <= 0): ?><div class="text-xs text-amber-700">Stock ยังไม่พอ ยอด <?= number_format($remaining, 2) ?> ค้างในใบเดิม</div><?php endif; ?>
        <?php elseif ((int)$stock_actor['id'] === (int)$withdrawal['requester_id'] && $remaining > 0): ?><form method="post" class="space-y-2" onsubmit="return confirm('ยืนยันยกเลิกจำนวนที่ยังไม่ได้จ่าย?')"><input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>"><input type="hidden" name="action" value="cancel_remaining"><input type="hidden" name="withdrawal_id" value="<?= $withdrawalId ?>"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><input name="cancel_reason" required maxlength="500" placeholder="เหตุผลที่ยกเลิก" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs"><button class="text-xs font-semibold text-rose-700 hover:underline">ยกเลิกยอดค้าง <?= number_format($remaining, 2) ?></button></form><?php else: ?><span class="text-xs text-slate-400">ไม่มีรายการค้าง</span><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div></div>

    <h2 class="text-lg font-bold text-slate-900 mb-3">ประวัติการจ่ายและรับจริง</h2>
    <div class="space-y-3"><?php foreach ($issues as $issue): ?><div class="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm md:flex-row md:items-center"><div class="flex-1"><div class="font-semibold text-slate-900"><?= stock_e($issue['product_name']) ?> · จ่าย <?= number_format((float)$issue['issued_quantity'], 2) ?> <?= stock_e($issue['unit']) ?></div><div class="text-xs text-slate-500 mt-1">โดย <?= stock_e($issue['issuer_name'] ?: '-') ?> · <?= date('d/m/Y H:i', strtotime($issue['issued_at'])) ?></div><?php if ($issue['status'] === 'discrepancy'): ?><div class="text-xs text-rose-700 mt-2">รับจริง <?= number_format((float)$issue['received_quantity'], 2) ?> · <?= stock_e($issue['receiver_note']) ?></div><?php endif; ?></div>
        <?php if ($issue['status'] === 'waiting_confirmation' && (int)$stock_actor['id'] === (int)$withdrawal['requester_id']): ?><form method="post" class="w-full md:w-auto flex flex-col sm:flex-row gap-2"><input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>"><input type="hidden" name="action" value="confirm"><input type="hidden" name="withdrawal_id" value="<?= $withdrawalId ?>"><input type="hidden" name="issue_id" value="<?= (int)$issue['id'] ?>"><input type="number" name="received_quantity" required min="0" max="<?= (float)$issue['issued_quantity'] ?>" step="0.01" value="<?= (float)$issue['issued_quantity'] ?>" class="w-32 rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" title="จำนวนที่ได้รับจริง"><input name="receiver_note" maxlength="500" placeholder="เหตุผลถ้ารับไม่ครบ" class="rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"><button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-emerald-700">ยืนยันรับจริง</button></form><?php elseif ($issue['status'] === 'discrepancy' && stock_can_manage($stock_actor['role'])): ?><form method="post" class="w-full md:w-[28rem] grid grid-cols-1 sm:grid-cols-2 gap-2"><input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>"><input type="hidden" name="action" value="resolve_discrepancy"><input type="hidden" name="withdrawal_id" value="<?= $withdrawalId ?>"><input type="hidden" name="issue_id" value="<?= (int)$issue['id'] ?>"><select name="resolution_type" required class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"><option value="returned_to_stock">พบสินค้าและคืนเข้า Stock</option><option value="accepted_loss">ยืนยันเป็นสินค้าสูญหาย</option></select><input name="resolution_note" required maxlength="500" placeholder="ผลการตรวจสอบ" class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"><button class="sm:col-span-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-indigo-700">ปิดผลต่าง</button></form><?php else: ?><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= in_array($issue['status'], ['confirmed', 'resolved'], true) ? 'bg-emerald-100 text-emerald-800' : ($issue['status'] === 'discrepancy' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') ?>"><?= $issue['status'] === 'confirmed' ? 'ยืนยันแล้ว' : ($issue['status'] === 'resolved' ? 'ปิดผลต่างแล้ว' : ($issue['status'] === 'discrepancy' ? 'พบผลต่าง' : 'รอยืนยัน')) ?></span><?php endif; ?></div><?php endforeach; ?><?php if (!$issues): ?><div class="rounded-2xl border border-slate-100 bg-white p-8 text-center text-slate-500 shadow-sm">ยังไม่มีการจ่ายสินค้า</div><?php endif; ?></div>

    <?php if ($actions): ?><h2 class="text-lg font-bold text-slate-900">ประวัติการแก้ไขยอดค้างและผลต่าง</h2><div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"><?php foreach ($actions as $action): ?><div class="p-5 flex flex-col md:flex-row md:items-center gap-2"><div class="flex-1"><div class="font-semibold text-slate-900"><?= stock_e($action['product_name']) ?> · <?= $action['action_type'] === 'cancel_remaining' ? 'ยกเลิกยอดค้าง' : ($action['action_type'] === 'discrepancy_return' ? 'คืนผลต่างเข้า Stock' : 'บันทึกสินค้าสูญหาย') ?> <?= number_format((float)$action['quantity'], 2) ?> <?= stock_e($action['unit']) ?></div><div class="text-xs text-slate-500 mt-1"><?= stock_e($action['reason']) ?></div></div><div class="text-xs text-slate-500"><?= stock_e($action['actor_name'] ?: '-') ?> · <?= date('d/m/Y H:i', strtotime($action['created_at'])) ?></div></div><?php endforeach; ?></div><?php endif; ?>
</div>
<?php include __DIR__ . '/footer.php'; ?>
