<?php
/**
 * API ส่งข้อมูลหน้ารออนุมัติ (ใบขอซื้อ PR ภายใน) ออกไปให้เว็บอื่น (อ่านอย่างเดียว) — ยืนยันตัวตนดู external_api_auth.php
 * ข้อมูลเหมือนหน้า pending_approval.php ในมุมมองของ admin (เห็นทุกใบ ไม่จำกัดทีม)
 *
 * GET api/pending_approval_data.php
 *   sup_id          : id บริษัท/หน่วยงาน (0 หรือไม่ใส่ = ทั้งหมด)
 *   date_from       : YYYY-MM-DD   date_to : YYYY-MM-DD (นับตามวันที่สร้าง รวมวันสุดท้าย)
 *   purchase_status : pending (รอเบิก) | approved (เบิกแล้ว)
 *   sup_status      : pending (หัวหน้ารออนุมัติ) | approved | rejected
 */
declare(strict_types=1);

require_once __DIR__ . '/external_api_auth.php';
ext_api_guard();

function pa_date_param(string $name): ?string
{
    $v = (string)($_GET[$name] ?? '');
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v ? $v : null;
}

function pa_step($id, ?string $name, ?string $at): array
{
    return [
        'approved' => !empty($id),
        'by' => !empty($id) ? $name : null,
        'at' => !empty($id) ? $at : null,
    ];
}

try {
    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/supplier_display.php';
    $conn->set_charset('utf8mb4');

    $where = 'p.deleted_at IS NULL AND p.is_internal = 1';
    $types = '';
    $params = [];
    $supId = max(0, (int)($_GET['sup_id'] ?? 0));
    if ($supId > 0) {
        $where .= ' AND p.supplier_id = ?';
        $types .= 'i';
        $params[] = $supId;
    }
    if ($from = pa_date_param('date_from')) {
        $where .= ' AND p.created_at >= ?';
        $types .= 's';
        $params[] = $from;
    }
    if ($to = pa_date_param('date_to')) {
        $where .= ' AND p.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
        $types .= 's';
        $params[] = $to;
    }

    $stmt = $conn->prepare(
        "SELECT p.id, p.doc_no, p.doc_date, p.due_date, p.created_at, p.priority,
                COALESCE(p.status, '') status, COALESCE(p.received_status, 'pending') received_status,
                p.subtotal, p.vat, p.grand_total, p.notes, p.requested_by,
                p.supplier_id, s.company_name supplier_name, st.store_name,
                ec.name category, bt.name budget,
                u_creator.name creator_name, u_creator.role creator_role,
                p.approved_by_0, u_app0.name approver_0_name, p.approved_at_0,
                p.approved_by, u2.name approver_name, p.approved_at,
                p.approved_by_1, u_app1.name approver_1_name, p.approved_at_1,
                p.approved_by_2, u_app2.name approver_2_name, p.approved_at_2,
                p.approved_by_3, u_app3.name approver_3_name, p.approved_at_3,
                p.reject_reason, u_rej.name rejected_by_name, p.rejected_at,
                p.attachment_1, p.attachment_2, p.quote_selection_reason,
                (SELECT item_desc FROM pr_items WHERE pr_id = p.id ORDER BY id ASC LIMIT 1) first_item_desc,
                (SELECT COUNT(*) FROM pr_items WHERE pr_id = p.id) item_count,
                (SELECT COUNT(*) FROM pr_quotes q WHERE q.pr_id = p.id) quote_count,
                (SELECT CONCAT(o.id, '|', o.doc_no, '|', COALESCE(o.status, '')) FROM po o
                 WHERE o.reference_no = p.doc_no AND o.deleted_at IS NULL ORDER BY o.id LIMIT 1) po_ref
         FROM pr p
         LEFT JOIN suppliers s ON p.supplier_id = s.id
         LEFT JOIN stores st ON p.store_id = st.id
         LEFT JOIN expense_categories ec ON ec.id = p.expense_cat_id
         LEFT JOIN budget_types bt ON bt.id = p.budget_type_id
         LEFT JOIN users u_creator ON p.created_by = u_creator.id
         LEFT JOIN users u_app0 ON p.approved_by_0 = u_app0.id
         LEFT JOIN users u2 ON p.approved_by = u2.id
         LEFT JOIN users u_app1 ON p.approved_by_1 = u_app1.id
         LEFT JOIN users u_app2 ON p.approved_by_2 = u_app2.id
         LEFT JOIN users u_app3 ON p.approved_by_3 = u_app3.id
         LEFT JOIN users u_rej ON p.rejected_by = u_rej.id
         WHERE {$where}
         ORDER BY p.created_at DESC"
    );
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $wantPurchase = in_array($_GET['purchase_status'] ?? '', ['pending', 'approved'], true) ? $_GET['purchase_status'] : '';
    $wantSup = in_array($_GET['sup_status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['sup_status'] : '';

    $list = [];
    $counts = [
        'total' => 0, 'total_value' => 0.0,
        'purchase' => ['pending' => 0, 'approved' => 0],
        'supervisor' => ['pending' => 0, 'approved' => 0, 'rejected' => 0],
    ];
    foreach ($rows as $r) {
        // ตรงกับหน้า pending_approval.php: ถูกปฏิเสธ (ขั้นไหนก็ได้) > อนุมัติแล้ว > รออนุมัติ
        $supStatus = trim((string)($r['reject_reason'] ?? '')) !== ''
            ? 'rejected'
            : (!empty($r['approved_by_0']) ? 'approved' : 'pending');
        $purchase = $r['status'] === 'approved' ? 'approved' : 'pending';
        if (($wantPurchase !== '' && $purchase !== $wantPurchase) || ($wantSup !== '' && $supStatus !== $wantSup)) {
            continue;
        }

        $counts['total']++;
        $counts['total_value'] += (float)$r['grand_total'];
        $counts['purchase'][$purchase]++;
        $counts['supervisor'][$supStatus]++;

        $list[] = [
            'id' => (int)$r['id'],
            'doc_no' => $r['doc_no'],
            'doc_date' => $r['doc_date'],
            'due_date' => $r['due_date'],
            'created_at' => $r['created_at'],
            'priority' => $r['priority'] ?: 'ปานกลาง',
            'company_id' => $r['supplier_id'] !== null ? (int)$r['supplier_id'] : null,
            'company' => supplier_display_name((string)($r['supplier_name'] ?: '-')),
            'store' => $r['store_name'],
            'category' => $r['category'],
            'budget' => $r['budget'],
            'first_item' => $r['first_item_desc'],
            'item_count' => (int)$r['item_count'],
            'subtotal' => (float)$r['subtotal'],
            'vat' => (float)$r['vat'],
            'grand_total' => (float)$r['grand_total'],
            'notes' => $r['notes'],
            'requested_by' => $r['requested_by'],
            'creator' => $r['creator_name'],
            'creator_role' => $r['creator_role'],
            'status' => $r['status'],
            'purchase_status' => $purchase,
            'purchase_status_label' => $purchase === 'approved' ? 'เบิกแล้ว' : 'รอเบิก',
            'received_status' => $r['received_status'],
            'supervisor_status' => $supStatus,
            'supervisor_status_label' => ['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ปฏิเสธ'][$supStatus],
            'approvals' => [
                'supervisor' => pa_step($r['approved_by_0'], $r['approver_0_name'], $r['approved_at_0']), // หัวหน้า
                'procure' => pa_step($r['approved_by'], $r['approver_name'], $r['approved_at']),            // จัดซื้อ
                'gmacc' => pa_step($r['approved_by_1'], $r['approver_1_name'], $r['approved_at_1']),        // GM บัญชี
                'sup' => pa_step($r['approved_by_2'], $r['approver_2_name'], $r['approved_at_2']),          // SUP (mgr)
                'ceo' => pa_step($r['approved_by_3'], $r['approver_3_name'], $r['approved_at_3']),          // CEO (mgr2)
            ],
            'rejection' => $supStatus === 'rejected' ? [
                'reason' => $r['reject_reason'],
                'by' => $r['rejected_by_name'],
                'at' => $r['rejected_at'],
            ] : null,
            'quote_count' => (int)$r['quote_count'],
            // ใบสั่งซื้อ (PO) ใบแรกที่ออกจากใบขอซื้อนี้ — null = ยังไม่ออก PO
            'po' => $r['po_ref'] ? (static function (string $v): array {
                [$id, $no, $st] = explode('|', $v, 3);
                return ['id' => (int)$id, 'doc_no' => $no, 'status' => $st];
            })($r['po_ref']) : null,
            'not_cheapest_reason' => trim((string)$r['quote_selection_reason']) !== '' ? $r['quote_selection_reason'] : null,
            'attachments' => array_values(array_map(
                fn ($f) => 'uploads/pr/' . $f,
                array_filter([$r['attachment_1'], $r['attachment_2']])
            )),
        ];
    }
    $counts['total_value'] = round($counts['total_value'], 2);

    $companies = $conn->query('SELECT id, company_name FROM suppliers ORDER BY company_name')->fetch_all(MYSQLI_ASSOC);

    ext_api_out(200, ['ok' => true, 'data' => [
        'filters' => [
            'sup_id' => $supId, 'date_from' => $from, 'date_to' => $to,
            'purchase_status' => $wantPurchase ?: null, 'sup_status' => $wantSup ?: null,
        ],
        'summary' => $counts,
        'prs' => $list,
        'companies' => array_map(fn ($c) => [
            'id' => (int)$c['id'],
            'name' => supplier_display_name((string)$c['company_name']),
        ], $companies),
        'generatedAt' => date('d/m/Y H:i'),
    ]]);
} catch (Throwable $e) {
    error_log('[pending-approval-api] ' . get_class($e) . ': ' . $e->getMessage());
    ext_api_out(500, ['ok' => false, 'error' => 'ดึงข้อมูลไม่สำเร็จ']);
}
