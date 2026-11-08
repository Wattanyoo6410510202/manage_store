<?php
/**
 * แก้ไขเฉพาะใบเทียบราคาโดย admin (หน้า edit_pr_quotes.php / api/update_pr_quotes.php)
 *
 * รายการสินค้า จำนวน ส่วนลด และ VAT อ่านจาก DB เสมอ ไม่รับจากฟอร์ม
 * ราคาและร้านของ PR จะเปลี่ยนตามร้านที่เลือก เพื่อให้ตรงกับใบเทียบราคา
 */
require_once __DIR__ . '/pr_quotes_lib.php';
require_once __DIR__ . '/budget_projects_lib.php';

/** คืนเหตุผลที่แก้ไม่ได้ หรือ null ถ้าแก้ได้ */
function pr_quotes_admin_block_reason(string $role, ?array $pr): ?string
{
    if (strtolower(trim($role)) !== 'admin') {
        return 'เฉพาะผู้ดูแลระบบ (admin) เท่านั้นที่แก้ไขใบเทียบราคาได้';
    }
    if ($pr === null || !empty($pr['deleted_at'])) {
        return 'ไม่พบใบขอซื้อ';
    }
    return null;
}

/**
 * ปรับราคาและยอดของ PO ที่ออกจาก PR นี้ให้ตรงกับร้านที่เลือก — แก้ทีละแถวเพื่อคง po_items.id ที่การรับของอ้างอิงอยู่
 *
 * @param array $pr_items แถว pr_items เรียงตาม id (key ตรงกับ $prices และ $lines)
 * @return ?string เลขที่ PO ที่ปรับ หรือ null ถ้าไม่มี PO
 * @throws DomainException เมื่อระบุ PO ไม่ได้ หรือ PO ถูกแก้จนไม่ตรงกับ PR
 */
function pr_quotes_admin_sync_po(mysqli $conn, array $pr, array $pr_items, array $prices, array $lines, array $totals, float $wht_amount): ?string
{
    $doc_no = (string)$pr['doc_no'];
    $stmt = $conn->prepare("SELECT id, doc_no, vat_percent, wht_percent FROM po WHERE reference_no = ? AND deleted_at IS NULL FOR UPDATE");
    $stmt->bind_param('s', $doc_no);
    $stmt->execute();
    $pos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if ($pos === []) {
        return null;
    }
    if (count($pos) > 1) {
        throw new DomainException('ใบขอซื้อนี้มีใบสั่งซื้อมากกว่า 1 ใบ ระบบปรับราคาให้อัตโนมัติไม่ได้ กรุณาแก้ไขที่ใบสั่งซื้อ');
    }

    $po = $pos[0];
    $po_id = (int)$po['id'];
    $stmt = $conn->prepare("SELECT id, item_desc, item_qty, item_discount FROM po_items WHERE po_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $po_id);
    $stmt->execute();
    $po_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $matches = count($po_items) === count($pr_items)
        && abs((float)$po['vat_percent'] - (float)$pr['vat_percent']) < 0.001
        && abs((float)$po['wht_percent'] - (float)$pr['wht_percent']) < 0.001;
    foreach ($matches ? $pr_items : [] as $key => $item) {
        $matches = $matches
            && (string)$po_items[$key]['item_desc'] === (string)$item['item_desc']
            && abs((float)$po_items[$key]['item_qty'] - (float)$item['item_qty']) < 0.001
            && abs((float)$po_items[$key]['item_discount'] - (float)$item['item_discount']) < 0.001;
    }
    if (!$matches) {
        throw new DomainException('ใบสั่งซื้อ ' . $po['doc_no'] . ' ถูกแก้ไขจนไม่ตรงกับใบขอซื้อ ระบบปรับราคาให้อัตโนมัติไม่ได้ กรุณาแก้ไขที่ใบสั่งซื้อ');
    }

    $stmt = $conn->prepare("UPDATE po_items SET item_price = ?, total_price = ? WHERE id = ?");
    foreach ($po_items as $key => $po_item) {
        $po_item_id = (int)$po_item['id'];
        $stmt->bind_param('ddi', $prices[$key], $lines[$key], $po_item_id);
        $stmt->execute();
    }
    $stmt = $conn->prepare("UPDATE po SET subtotal = ?, vat_amount = ?, wht_amount = ?, grand_total = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ddddi', $totals['subtotal'], $totals['vat'], $wht_amount, $totals['grand_total'], $po_id);
    $stmt->execute();

    return (string)$po['doc_no'];
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
 * @return array{obsolete_files: array, old_total: float, new_total: float, store_changed: bool, po_doc_no: ?string}
 * @throws DomainException เมื่อแก้ไม่ได้หรือข้อมูลไม่ครบ
 */
function pr_quotes_admin_apply(mysqli $conn, string $role, int $pr_id, array $post): array
{
    $stmt = $conn->prepare("SELECT * FROM pr WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $pr_id);
    $stmt->execute();
    $pr = $stmt->get_result()->fetch_assoc() ?: null;

    $block = pr_quotes_admin_block_reason($role, $pr);
    if ($block !== null) {
        throw new DomainException($block);
    }

    $stmt = $conn->prepare("SELECT id, item_desc, item_qty, item_price, item_discount FROM pr_items WHERE pr_id = ? ORDER BY id ASC");
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

    $wht_amount = round($totals['subtotal'] * ($wht_percent / 100), 2);

    // ปรับ PO ก่อนแก้ PR เพื่อให้กรณี PO ไม่ตรงกันหยุดก่อนมีการเขียนข้อมูล
    $prices_changed = false;
    foreach ($items as $key => $item) {
        $prices_changed = $prices_changed || abs((float)$item['item_price'] - (float)$selected['prices'][$key]) > 0.005;
    }
    $po_doc_no = $prices_changed
        ? pr_quotes_admin_sync_po($conn, $pr, $items, $selected['prices'], $totals['lines'], $totals, $wht_amount)
        : null;

    $stmt = $conn->prepare("UPDATE pr_items SET item_price = ?, item_total = ? WHERE id = ?");
    foreach ($items as $key => $item) {
        $item_id = (int)$item['id'];
        $stmt->bind_param('ddi', $selected['prices'][$key], $totals['lines'][$key], $item_id);
        $stmt->execute();
    }

    // PR ที่อนุมัติครบแล้วคงประเภทวงเงินตามที่อนุมัติไว้
    $limit_type = $total_changed && ($pr['status'] ?? '') !== 'approved'
        ? pr_quotes_admin_limit_type($new_total)
        : (string)$pr['budget_limit_type'];
    $stmt = $conn->prepare("UPDATE pr SET store_id = ?, subtotal = ?, vat = ?, wht_amount = ?, grand_total = ?, budget_limit_type = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('iddddsi', $selected['store_id'], $totals['subtotal'], $totals['vat'], $wht_amount, $new_total, $limit_type, $pr_id);
    $stmt->execute();

    return [
        'obsolete_files' => pr_quotes_save($conn, $pr_id, $parsed),
        'old_total' => $old_total,
        'new_total' => $new_total,
        'store_changed' => (int)($pr['store_id'] ?? 0) !== (int)$selected['store_id'],
        'po_doc_no' => $po_doc_no,
    ];
}
