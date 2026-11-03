<?php
/**
 * API ส่งรายละเอียดเอกสาร 1 ใบ (ใบขอซื้อ PR / ใบสั่งซื้อ PO) ออกไปให้เว็บอื่น (อ่านอย่างเดียว) — ยืนยันตัวตนดู external_api_auth.php
 *
 * GET api/document_data.php?type=pr&id=123
 * GET api/document_data.php?type=po&id=45
 */
declare(strict_types=1);

require_once __DIR__ . '/external_api_auth.php';
ext_api_guard();

$type = (string)($_GET['type'] ?? '');
$id = (int)($_GET['id'] ?? 0);
if (!in_array($type, ['pr', 'po'], true) || $id <= 0) {
    ext_api_out(400, ['ok' => false, 'error' => 'ระบุ type (pr|po) และ id']);
}

function doc_one(mysqli $conn, string $sql, string $types, array $params): ?array
{
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function doc_all(mysqli $conn, string $sql, string $types, array $params): array
{
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

function doc_float(array $row, array $keys): array
{
    foreach ($keys as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null) {
            $row[$k] = (float)$row[$k];
        }
    }
    return $row;
}

try {
    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/supplier_display.php';
    $conn->set_charset('utf8mb4');

    if ($type === 'pr') {
        require_once dirname(__DIR__) . '/pr_timeline_lib.php';
        $h = doc_one($conn,
            "SELECT p.id, p.doc_no, p.doc_date, p.due_date, p.created_at, p.priority, p.status, p.received_status, p.received_at,
                    p.requested_by, p.contact_tel, p.notes, p.expectation, p.payment_term, p.payment_method,
                    p.subtotal, p.vat, p.vat_percent, p.wht_percent, p.wht_amount, p.grand_total, p.total_after_wht,
                    p.reject_reason, p.rejected_at, p.quote_selection_reason, p.budget_limit_type, p.is_internal,
                    s.company_name company, st.store_name store, c.customer_name vendor, ec.name category, bt.name budget,
                    u.name creator, u_rej.name rejected_by
             FROM pr p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN stores st ON st.id = p.store_id
             LEFT JOIN customers c ON c.id = p.customer_id
             LEFT JOIN expense_categories ec ON ec.id = p.expense_cat_id
             LEFT JOIN budget_types bt ON bt.id = p.budget_type_id
             LEFT JOIN users u ON u.id = p.created_by
             LEFT JOIN users u_rej ON u_rej.id = p.rejected_by
             WHERE p.id = ? AND p.deleted_at IS NULL", 'i', [$id]);
        if (!$h) {
            ext_api_out(404, ['ok' => false, 'error' => 'ไม่พบใบขอซื้อ']);
        }
        $h['company'] = supplier_display_name((string)$h['company']);
        $h = doc_float($h, ['subtotal', 'vat', 'vat_percent', 'wht_percent', 'wht_amount', 'grand_total', 'total_after_wht']);

        $items = array_map(fn ($r) => doc_float($r, ['item_qty', 'item_price', 'item_discount', 'item_total']), doc_all($conn,
            'SELECT item_desc, item_qty, item_unit, item_price, item_discount, item_total FROM pr_items WHERE pr_id = ? ORDER BY id', 'i', [$id]));
        $quotes = array_map(fn ($r) => doc_float($r, ['subtotal', 'vat', 'grand_total']) + ['is_selected' => (bool)$r['is_selected']], doc_all($conn,
            'SELECT q.quote_no, q.delivery_days, q.payment_term, q.service_note, q.subtotal, q.vat, q.grand_total, q.is_selected, s.store_name store
             FROM pr_quotes q LEFT JOIN stores s ON s.id = q.store_id WHERE q.pr_id = ? ORDER BY q.sort_order, q.id', 'i', [$id]));
        $pos = array_map(fn ($r) => doc_float($r, ['grand_total']) + ['id' => (int)$r['id']], doc_all($conn,
            'SELECT id, doc_no, status, grand_total, created_at FROM po WHERE reference_no = ? AND deleted_at IS NULL ORDER BY id', 's', [$h['doc_no']]));
        $tl = pr_timeline_build($conn, $id) ?? ['events' => [], 'upcoming' => []];
        $events = array_map(fn ($e) => ['title' => $e['title'], 'color' => $e['color'], 'by' => $e['actor_name'], 'at' => $e['at'], 'note' => $e['note']], $tl['events']);
        $upcoming = array_map(fn ($u) => ['title' => $u['title'], 'state' => $u['state']], $tl['upcoming']);

        ext_api_out(200, ['ok' => true, 'data' => [
            'type' => 'pr', 'header' => $h, 'items' => $items, 'quotes' => $quotes, 'pos' => $pos,
            'timeline' => $events, 'upcoming' => $upcoming,
        ]]);
    }

    $h = doc_one($conn,
        "SELECT o.id, o.doc_no, o.reference_no, o.express_ref_code, o.created_at, o.approved_at, o.due_date, o.status,
                o.payment_term, o.requested_by, o.contact_tel, o.notes,
                o.subtotal, o.vat_amount vat, o.vat_percent, o.wht_percent, o.wht_amount, o.grand_total, o.total_after_wht,
                s.company_name company, c.customer_name vendor, u.name creator, ua.name approved_by
         FROM po o
         LEFT JOIN suppliers s ON s.id = o.supplier_id
         LEFT JOIN customers c ON c.id = o.customer_id
         LEFT JOIN users u ON u.id = o.created_by
         LEFT JOIN users ua ON ua.id = o.approved_by
         WHERE o.id = ? AND o.deleted_at IS NULL", 'i', [$id]);
    if (!$h) {
        ext_api_out(404, ['ok' => false, 'error' => 'ไม่พบใบสั่งซื้อ']);
    }
    $h['company'] = supplier_display_name((string)$h['company']);
    $h = doc_float($h, ['subtotal', 'vat', 'vat_percent', 'wht_percent', 'wht_amount', 'grand_total', 'total_after_wht']);
    $items = array_map(fn ($r) => doc_float($r, ['item_qty', 'item_price', 'item_discount', 'item_total']), doc_all($conn,
        'SELECT item_desc, item_qty, item_unit, item_price, item_discount, total_price item_total FROM po_items WHERE po_id = ? ORDER BY id', 'i', [$id]));
    $pr = $h['reference_no'] ? doc_one($conn, 'SELECT id, doc_no, status FROM pr WHERE doc_no = ? AND deleted_at IS NULL', 's', [$h['reference_no']]) : null;

    ext_api_out(200, ['ok' => true, 'data' => [
        'type' => 'po', 'header' => $h, 'items' => $items, 'pr' => $pr ? ['id' => (int)$pr['id'], 'doc_no' => $pr['doc_no'], 'status' => $pr['status']] : null,
    ]]);
} catch (Throwable $e) {
    error_log('[document-api] ' . get_class($e) . ': ' . $e->getMessage());
    ext_api_out(500, ['ok' => false, 'error' => 'ดึงข้อมูลไม่สำเร็จ']);
}
