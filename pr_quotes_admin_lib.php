<?php
/**
 * แก้ไขเฉพาะใบเทียบราคาโดย admin (หน้า edit_pr_quotes.php / api/update_pr_quotes.php)
 *
 * รายการสินค้า จำนวน ส่วนลด และ VAT อ่านจาก DB เสมอ ไม่รับจากฟอร์ม
 * ราคาและร้านของ PR จะเปลี่ยนตามร้านที่เลือก เพื่อให้ตรงกับใบเทียบราคา
 */
require_once __DIR__ . '/pr_quotes_lib.php';
require_once __DIR__ . '/budget_projects_lib.php';

/** คืนเหตุผลที่แก้ไม่ได้ หรือ null ถ้าแก้ได้ — PO คัดลอกราคาไปจาก PR แล้วจึงห้ามแก้หลังออก PO */
function pr_quotes_admin_block_reason(string $role, ?array $pr, bool $has_po): ?string
{
    if (strtolower(trim($role)) !== 'admin') {
        return 'เฉพาะผู้ดูแลระบบ (admin) เท่านั้นที่แก้ไขใบเทียบราคาได้';
    }
    if ($pr === null || !empty($pr['deleted_at'])) {
        return 'ไม่พบใบขอซื้อ';
    }
    if (($pr['status'] ?? '') === 'approved' || $has_po) {
        return 'ใบขอซื้อนี้อนุมัติครบหรือออกใบสั่งซื้อแล้ว จึงแก้ไขใบเทียบราคาไม่ได้';
    }
    return null;
}

function pr_quotes_admin_has_po(mysqli $conn, string $doc_no): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM po WHERE reference_no = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param('s', $doc_no);
    $stmt->execute();
    return $stmt->get_result()->fetch_row() !== null;
}

/** ประเภทวงเงินตามยอดสุทธิ (เกณฑ์เดียวกับ calculateTotal ในฟอร์ม PR) */
function pr_quotes_admin_limit_type(float $grand_total): string
{
    if ($grand_total <= 10000) {
        return 'low';
    }
    return $grand_total <= 99999 ? 'mid' : 'high';
}

function pr_quotes_admin_csrf_token(): string
{
    if (empty($_SESSION['pr_quotes_csrf'])) {
        $_SESSION['pr_quotes_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pr_quotes_csrf'];
}

function pr_quotes_admin_verify_csrf(array $post): bool
{
    return isset($_SESSION['pr_quotes_csrf'], $post['csrf'])
        && hash_equals($_SESSION['pr_quotes_csrf'], (string)$post['csrf']);
}

/**
 * บันทึกใบเทียบราคาและปรับราคา/ร้าน/ยอดของ PR ตามร้านที่เลือก — เรียกภายใน transaction
 *
 * @return array{obsolete_files: array, old_total: float, new_total: float, store_changed: bool}
 * @throws DomainException เมื่อแก้ไม่ได้หรือข้อมูลไม่ครบ
 */
function pr_quotes_admin_apply(mysqli $conn, string $role, int $pr_id, array $post): array
{
    $stmt = $conn->prepare("SELECT * FROM pr WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $pr_id);
    $stmt->execute();
    $pr = $stmt->get_result()->fetch_assoc() ?: null;

    $block = pr_quotes_admin_block_reason($role, $pr, $pr !== null && pr_quotes_admin_has_po($conn, (string)$pr['doc_no']));
    if ($block !== null) {
        throw new DomainException($block);
    }

    $stmt = $conn->prepare("SELECT id, item_qty, item_discount FROM pr_items WHERE pr_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $pr_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if ($items === []) {
        throw new DomainException('ใบขอซื้อนี้ไม่มีรายการสินค้า');
    }

    $post['quote_enabled'] = '1'; // หน้านี้เพิ่ม/แก้ได้เท่านั้น ไม่ลบใบเทียบราคาออกจาก PR
    $vat_percent = (float)$pr['vat_percent'];
    $wht_percent = (float)$pr['wht_percent'];
    $parsed = pr_quotes_parse(
        $post,
        array_keys($items),
        array_map('floatval', array_column($items, 'item_qty')),
        array_map('floatval', array_column($items, 'item_discount')),
        $vat_percent,
        $wht_percent
    );

    $selected = $parsed['quotes'][$parsed['selected']];
    $totals = $selected['totals'];
    $old_total = (float)$pr['grand_total'];
    $new_total = (float)$totals['grand_total'];
    $total_changed = abs($new_total - $old_total) > 0.005;

    if ($total_changed && (int)($pr['installment_period'] ?? 0) > 0) {
        throw new DomainException('ใบขอซื้อนี้มีกำหนดผ่อนชำระ ยอดสุทธิจะเปลี่ยนจาก ' . number_format($old_total, 2) . ' เป็น ' . number_format($new_total, 2) . ' บาท กรุณาแก้ไขงวดในหน้าแก้ไขใบขอซื้อ');
    }
    if ($new_total > $old_total + 0.005) {
        $budget_error = budget_pr_check($conn, (int)$pr['budget_type_id'], (int)($pr['budget_project_id'] ?? 0), $new_total, $pr_id);
        if ($budget_error !== null) {
            throw new DomainException($budget_error);
        }
    }

    $stmt = $conn->prepare("UPDATE pr_items SET item_price = ?, item_total = ? WHERE id = ?");
    foreach ($items as $key => $item) {
        $item_id = (int)$item['id'];
        $stmt->bind_param('ddi', $selected['prices'][$key], $totals['lines'][$key], $item_id);
        $stmt->execute();
    }

    $wht_amount = round($totals['subtotal'] * ($wht_percent / 100), 2);
    $limit_type = $total_changed ? pr_quotes_admin_limit_type($new_total) : (string)$pr['budget_limit_type'];
    $stmt = $conn->prepare("UPDATE pr SET store_id = ?, subtotal = ?, vat = ?, wht_amount = ?, grand_total = ?, budget_limit_type = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('iddddsi', $selected['store_id'], $totals['subtotal'], $totals['vat'], $wht_amount, $new_total, $limit_type, $pr_id);
    $stmt->execute();

    return [
        'obsolete_files' => pr_quotes_save($conn, $pr_id, $parsed),
        'old_total' => $old_total,
        'new_total' => $new_total,
        'store_changed' => (int)($pr['store_id'] ?? 0) !== (int)$selected['store_id'],
    ];
}
