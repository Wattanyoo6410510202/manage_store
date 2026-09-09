<?php

require_once __DIR__ . '/stock_common.php';

if (!in_array($stock_actor['role'], ['procure', 'gmhr', 'admin'], true)) {
    http_response_code(403);
    die('คุณไม่มีสิทธิ์เข้าถึงการกระทบยอดพัสดุ');
}

$companies = stock_companies($conn);
$selectedSupId = stock_can_manage($stock_actor['role'])
    ? (int)($_GET['sup_id'] ?? $_POST['sup_id'] ?? 0)
    : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        stock_verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        $conn->begin_transaction();
        if ($action === 'create') {
            stock_require_manager($stock_actor);
            $supId = (int)$_POST['sup_id'];
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($supId <= 0) {
                throw new DomainException('กรุณาเลือกบริษัทที่ตรวจนับ');
            }
            $productStmt = $conn->prepare(
                'SELECT p.id, p.name, COALESCE(b.quantity, 0) system_quantity
                 FROM stock_products p INNER JOIN stock_balances b ON b.product_id = p.id
                 WHERE p.sup_id = ? AND p.is_active = 1 ORDER BY p.id FOR UPDATE'
            );
            $productStmt->bind_param('i', $supId);
            $productStmt->execute();
            $products = $productStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $productStmt->close();
            if (!$products) {
                throw new DomainException('บริษัทนี้ยังไม่มีพัสดุให้กระทบยอด');
            }
            $counts = (array)($_POST['physical_quantity'] ?? []);
            $hasDifference = false;
            foreach ($products as $product) {
                if (!array_key_exists($product['id'], $counts) || !is_numeric($counts[$product['id']]) || (float)$counts[$product['id']] < 0) {
                    throw new DomainException('กรุณากรอกยอดนับจริงของพัสดุทุกรายการ');
                }
                if (stock_reconciliation_requires_approval((float)$product['system_quantity'], (float)$counts[$product['id']])) {
                    $hasDifference = true;
                }
            }
            if ($hasDifference && $reason === '') {
                throw new DomainException('กรุณาระบุสาเหตุของผลต่างก่อนส่ง GMHR');
            }
            $status = $hasDifference ? 'pending_approval' : 'matched';
            $docNo = stock_generate_doc_no($conn, 'RC', 'stock_reconciliations');
            $headerStmt = $conn->prepare('INSERT INTO stock_reconciliations (doc_no, sup_id, status, reason, counted_by) VALUES (?, ?, ?, NULLIF(?, \'\'), ?)');
            $headerStmt->bind_param('sissi', $docNo, $supId, $status, $reason, $stock_actor['id']);
            $headerStmt->execute();
            $reconciliationId = (int)$conn->insert_id;
            $headerStmt->close();
            $itemStmt = $conn->prepare('INSERT INTO stock_reconciliation_items (reconciliation_id, product_id, system_quantity, physical_quantity, difference_quantity) VALUES (?, ?, ?, ?, ?)');
            foreach ($products as $product) {
                $systemQuantity = (float)$product['system_quantity'];
                $physicalQuantity = (float)$counts[$product['id']];
                $difference = stock_reconciliation_difference($systemQuantity, $physicalQuantity);
                $itemStmt->bind_param('iiddd', $reconciliationId, $product['id'], $systemQuantity, $physicalQuantity, $difference);
                $itemStmt->execute();
            }
            $itemStmt->close();
            $conn->commit();
            stock_flash('success', $hasDifference ? "ส่งผลต่าง {$docNo} ให้ GMHR อนุมัติแล้ว" : "กระทบยอด {$docNo} แล้ว ยอดตรงกันทั้งหมด");
            stock_redirect('stock_reconciliation.php?sup_id=' . $supId);
        }

        if (in_array($action, ['approve', 'reject'], true)) {
            if (!stock_can_approve_reconciliation($stock_actor['role'])) {
                throw new DomainException('เฉพาะ GMHR เท่านั้นที่อนุมัติผลต่างได้');
            }
            $reconciliationId = (int)$_POST['reconciliation_id'];
            $reviewNote = trim((string)($_POST['review_note'] ?? ''));
            if ($action === 'reject' && $reviewNote === '') {
                throw new DomainException('กรุณาระบุเหตุผลที่ไม่อนุมัติ');
            }
            $headerStmt = $conn->prepare("SELECT * FROM stock_reconciliations WHERE id = ? AND status = 'pending_approval' FOR UPDATE");
            $headerStmt->bind_param('i', $reconciliationId);
            $headerStmt->execute();
            $reconciliation = $headerStmt->get_result()->fetch_assoc();
            $headerStmt->close();
            if (!$reconciliation) {
                throw new DomainException('รายการนี้ถูกดำเนินการแล้วหรือไม่พบข้อมูล');
            }
            if ($action === 'approve') {
                $itemsStmt = $conn->prepare('SELECT * FROM stock_reconciliation_items WHERE reconciliation_id = ? ORDER BY id FOR UPDATE');
                $itemsStmt->bind_param('i', $reconciliationId);
                $itemsStmt->execute();
                $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $itemsStmt->close();
                foreach ($items as $item) {
                    $difference = (float)$item['difference_quantity'];
                    stock_apply_reconciliation_adjustment(
                        $conn,
                        (int)$reconciliation['sup_id'],
                        (int)$item['product_id'],
                        (float)$item['system_quantity'],
                        $difference,
                        (int)$item['id'],
                        (int)$stock_actor['id'],
                        'GMHR อนุมัติผลต่าง ' . $reconciliation['doc_no']
                    );
                }
            }
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $updateStmt = $conn->prepare('UPDATE stock_reconciliations SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = NULLIF(?, \'\') WHERE id = ?');
            $updateStmt->bind_param('sisi', $newStatus, $stock_actor['id'], $reviewNote, $reconciliationId);
            $updateStmt->execute();
            $updateStmt->close();
            $conn->commit();
            stock_flash('success', $action === 'approve' ? 'อนุมัติและปรับยอด Stock แล้ว' : 'ไม่อนุมัติผลต่าง โดยยอด Stock ไม่เปลี่ยน');
            stock_redirect('stock_reconciliation.php');
        }
        throw new DomainException('คำสั่งไม่ถูกต้อง');
    } catch (Throwable $exception) {
        $conn->rollback();
        stock_flash('error', stock_error_message($exception));
        stock_redirect('stock_reconciliation.php?sup_id=' . (int)($_POST['sup_id'] ?? 0));
    }
}

$countProducts = [];
if (stock_can_manage($stock_actor['role']) && $selectedSupId > 0) {
    $productStmt = $conn->prepare(
        'SELECT p.id, p.sku, p.name, p.unit, p.category, COALESCE(b.quantity, 0) quantity
         FROM stock_products p INNER JOIN stock_balances b ON b.product_id = p.id
         WHERE p.sup_id = ? AND p.is_active = 1 ORDER BY p.name'
    );
    $productStmt->bind_param('i', $selectedSupId);
    $productStmt->execute();
    $countProducts = $productStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $productStmt->close();
}

$filterSql = '';
if (stock_can_manage($stock_actor['role']) && $selectedSupId > 0) {
    $filterSql = ' WHERE r.sup_id = ' . $selectedSupId;
}
$reconciliations = $conn->query(
    "SELECT r.*, s.company_name, counter.name counter_name, reviewer.name reviewer_name,
            COUNT(ri.id) item_count, COALESCE(SUM(ABS(ri.difference_quantity)), 0) difference_total
     FROM stock_reconciliations r INNER JOIN suppliers s ON s.id = r.sup_id
     LEFT JOIN users counter ON counter.id = r.counted_by LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
     LEFT JOIN stock_reconciliation_items ri ON ri.reconciliation_id = r.id
     {$filterSql} GROUP BY r.id
     ORDER BY (r.status = 'pending_approval') DESC, r.id DESC LIMIT 150"
)->fetch_all(MYSQLI_ASSOC);

$reconciliationItems = [];
if ($reconciliations) {
    $ids = implode(',', array_map(fn ($row) => (int)$row['id'], $reconciliations));
    $rows = $conn->query(
        "SELECT ri.*, p.sku, p.name, p.unit FROM stock_reconciliation_items ri
         INNER JOIN stock_products p ON p.id = ri.product_id WHERE ri.reconciliation_id IN ({$ids}) ORDER BY ri.id"
    )->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $row) {
        $reconciliationItems[(int)$row['reconciliation_id']][] = $row;
    }
}
$flash = stock_take_flash();
include __DIR__ . '/header.php';
?>

<div class="max-w-7xl mx-auto p-4 md:p-8 space-y-6" data-stock-ui="procurement">
    <div><h1 class="text-2xl font-black text-slate-800 tracking-tight">กระทบยอดพัสดุ</h1><p class="mt-1 text-sm text-slate-500"><?= $stock_actor['role'] === 'gmhr' ? 'ตรวจสอบและอนุมัติรายงานผลต่างจากทุกบริษัท' : 'เปรียบเทียบยอดในระบบกับยอดที่นับได้จริง' ?></p></div>
    <?php stock_render_flash($flash); ?>

    <?php if (stock_can_manage($stock_actor['role'])): ?>
    <form method="get" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><label class="block max-w-xl text-sm font-semibold text-slate-700">บริษัทที่ต้องการตรวจนับ<select name="sup_id" onchange="this.form.submit()" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm outline-none transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"><option value="">เลือกบริษัท</option><?php foreach ($companies as $company): ?><option value="<?= (int)$company['id'] ?>" <?= $selectedSupId === (int)$company['id'] ? 'selected' : '' ?>><?= stock_e(supplier_display_name($company['company_name'])) ?></option><?php endforeach; ?></select></label></form>
    <?php if ($selectedSupId > 0): ?><details class="group rounded-2xl border border-slate-100 bg-white shadow-sm"><summary class="flex cursor-pointer list-none justify-between p-5 font-bold text-slate-800"><span><i class="fas fa-clipboard-check mr-2 text-indigo-600"></i>เริ่มตรวจนับรอบใหม่</span><i class="fas fa-chevron-down text-xs text-slate-400 transition-transform group-open:rotate-180"></i></summary><form method="post" class="border-t border-slate-100"><input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>"><input type="hidden" name="action" value="create"><input type="hidden" name="sup_id" value="<?= $selectedSupId ?>"><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="text-left px-4 py-3">พัสดุ</th><th class="text-right px-4 py-3">ยอดระบบ</th><th class="text-left px-4 py-3 w-52">ยอดนับจริง</th></tr></thead><tbody class="divide-y divide-slate-100"><?php foreach ($countProducts as $product): ?><tr class="transition-colors hover:bg-slate-50"><td class="px-4 py-3 font-semibold text-slate-900"><?= stock_e($product['name']) ?><div class="text-xs text-slate-500"><?= stock_e($product['unit']) ?></div></td><td class="px-4 py-3 text-right"><?= number_format((float)$product['quantity'], 2) ?></td><td class="px-4 py-3"><input type="number" name="physical_quantity[<?= (int)$product['id'] ?>]" min="0" step="0.01" value="<?= (float)$product['quantity'] ?>" required class="w-full rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"></td></tr><?php endforeach; ?><?php if (!$countProducts): ?><tr><td colspan="3" class="px-4 py-10 text-center text-slate-500">ยังไม่มีพัสดุให้ตรวจนับ</td></tr><?php endif; ?></tbody></table></div><div class="flex flex-col gap-4 border-t border-slate-100 p-5 md:flex-row md:items-end"><label class="flex-1 text-sm font-semibold text-slate-700">สาเหตุของผลต่าง<textarea name="reason" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="จำเป็นเมื่อยอดนับจริงไม่ตรงกับระบบ"></textarea></label><button <?= !$countProducts ? 'disabled' : '' ?> class="rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-md shadow-indigo-200 transition-all hover:bg-indigo-700 active:scale-95 disabled:bg-slate-300 disabled:shadow-none">บันทึกผลตรวจนับ</button></div></form></details><?php endif; ?>
    <?php endif; ?>

    <div class="space-y-4"><?php foreach ($reconciliations as $row): ?><details class="group rounded-2xl border border-slate-100 bg-white shadow-sm" <?= $row['status'] === 'pending_approval' ? 'open' : '' ?>><summary class="flex cursor-pointer list-none flex-col gap-3 p-5 md:flex-row md:items-center"><div class="flex-1"><div class="font-bold text-slate-900"><?= stock_e($row['doc_no']) ?> · <?= stock_e(supplier_display_name($row['company_name'])) ?></div><div class="text-xs text-slate-500 mt-1">ผู้ตรวจ <?= stock_e($row['counter_name'] ?: '-') ?> · <?= date('d/m/Y H:i', strtotime($row['counted_at'])) ?> · <?= (int)$row['item_count'] ?> รายการ</div></div><div class="flex items-center gap-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $row['status'] === 'pending_approval' ? 'bg-amber-100 text-amber-800' : ($row['status'] === 'approved' || $row['status'] === 'matched' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800') ?>"><?= stock_e(stock_status_label($row['status'])) ?></span><i class="fas fa-chevron-down text-xs text-slate-400 transition-transform group-open:rotate-180"></i></div></summary><div class="border-t border-slate-100 p-5"><div class="text-sm text-slate-700 mb-3"><strong>สาเหตุ:</strong> <?= stock_e($row['reason'] ?: 'ยอดตรงกันทั้งหมด') ?></div><div class="overflow-x-auto rounded-xl border border-slate-100"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="text-left px-3 py-2">พัสดุ</th><th class="text-right px-3 py-2">ยอดระบบ</th><th class="text-right px-3 py-2">นับจริง</th><th class="text-right px-3 py-2">ผลต่าง</th></tr></thead><tbody class="divide-y divide-slate-100"><?php foreach ($reconciliationItems[(int)$row['id']] ?? [] as $item): ?><tr><td class="px-3 py-2 font-semibold"><?= stock_e($item['name']) ?></td><td class="px-3 py-2 text-right"><?= number_format((float)$item['system_quantity'], 2) ?></td><td class="px-3 py-2 text-right"><?= number_format((float)$item['physical_quantity'], 2) ?></td><td class="px-3 py-2 text-right font-bold <?= (float)$item['difference_quantity'] < 0 ? 'text-rose-700' : ((float)$item['difference_quantity'] > 0 ? 'text-amber-700' : 'text-emerald-700') ?>"><?= (float)$item['difference_quantity'] > 0 ? '+' : '' ?><?= number_format((float)$item['difference_quantity'], 2) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php if ($row['status'] === 'pending_approval' && stock_can_approve_reconciliation($stock_actor['role'])): ?><form method="post" class="mt-4 flex flex-col md:flex-row gap-3"><input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>"><input type="hidden" name="reconciliation_id" value="<?= (int)$row['id'] ?>"><input name="review_note" maxlength="1000" placeholder="หมายเหตุการพิจารณา (บังคับเมื่อไม่อนุมัติ)" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"><button name="action" value="reject" class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700 transition-colors hover:bg-rose-50">ไม่อนุมัติ</button><button name="action" value="approve" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-bold text-white transition-colors hover:bg-emerald-700">อนุมัติและปรับยอด</button></form><?php elseif ($row['reviewed_by']): ?><div class="mt-3 text-xs text-slate-500">พิจารณาโดย <?= stock_e($row['reviewer_name']) ?><?= $row['review_note'] ? ' · ' . stock_e($row['review_note']) : '' ?></div><?php endif; ?>
        </div></details><?php endforeach; ?><?php if (!$reconciliations): ?><div class="rounded-2xl border border-slate-100 bg-white p-12 text-center text-slate-500 shadow-sm">ยังไม่มีประวัติการกระทบยอด</div><?php endif; ?></div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
