<?php
/**
 * บันทึกการอนุมัติ 1 ขั้นของใบขอซื้อ + ตรวจว่าอนุมัติครบหรือยัง (ครบ = สร้าง PO อัตโนมัติ)
 * ใช้ร่วมกันระหว่าง api/update_pr_status_new.php (อนุมัติในระบบ) และลิงก์เซ็น (pr_sign_lib.php)
 * ผู้เรียกต้องตรวจสิทธิ์ / สถานะ PR / ขั้นยังว่างอยู่ ก่อนเรียก
 */
require_once __DIR__ . '/pr_timeline_lib.php';

const PR_APPROVAL_STAGE_COLS = ['approved_by_0', 'approved_by', 'approved_by_1', 'approved_by_2', 'approved_by_3'];

/** @return array{full_approved: bool, approver_count: int} */
function pr_approval_apply_stage(mysqli $conn, int $pr_id, string $update_col, int $user_id, ?string $log_note = null): array
{
    if (!in_array($update_col, PR_APPROVAL_STAGE_COLS, true)) {
        throw new InvalidArgumentException('Invalid approval stage');
    }
    $time_col = PR_STAGE_TIME_COLS[$update_col];

    $fetch_pr = function () use ($conn, $pr_id): array {
        $stmt = $conn->prepare("SELECT pr.*, u.role as requester_role, u.sup_id as requester_sup_id FROM pr LEFT JOIN users u ON pr.created_by = u.id WHERE pr.id = ?");
        $stmt->bind_param("i", $pr_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: [];
    };
    $pr = $fetch_pr();

    $sql_update = "UPDATE pr SET $update_col = ?, updated_at = NOW(), $time_col = NOW() ";
    if ($update_col === 'approved_by_0' && (int)($pr['allow_resubmit'] ?? 0) === 3) {
        $sql_update .= ", reject_reason = NULL, rejected_by = NULL, rejected_at = NULL, allow_resubmit = 0 ";
    }
    $sql_update .= " WHERE id = ?";
    $update_stmt = $conn->prepare($sql_update);
    $update_stmt->bind_param("ii", $user_id, $pr_id);
    $update_stmt->execute();

    pr_log_activity($conn, $pr_id, 'approved', $user_id, $log_note, $update_col);
    $pr = $fetch_pr();

    $approver_count = 0;
    foreach (PR_APPROVAL_STAGE_COLS as $col) {
        if (!empty($pr[$col])) $approver_count++;
    }

    $limit_type = strtolower(trim($pr['budget_limit_type'] ?? 'low'));
    if (in_array($limit_type, ['low', 'mid', 'high'], true)) {
        // low: หัวหน้า + จัดซื้อ / mid: + MGR / high: + MGR2
        $is_fully = true;
        foreach (pr_required_stages($limit_type) as $col) {
            if (empty($pr[$col])) $is_fully = false;
        }
    } else {
        $is_fully = $approver_count >= 4;
    }

    // --- LINE NOTIFY (ยกเว้นขั้นตอน Procure) ---
    if ($update_col !== 'approved_by') {
        try {
            require_once __DIR__ . '/api/notify_helper.php';
            $level_labels = [
                'approved_by_0' => 'หัวหน้าแผนก (Level 0)',
                'approved_by_1' => 'GM ฝ่ายบัญชี (GMACC)',
                'approved_by_2' => 'ผู้จัดการ (MGR)',
                'approved_by_3' => 'ผู้จัดการอาวุโส (MGR2)',
            ];
            $level_label = $level_labels[$update_col] ?? 'ผู้อนุมัติ';

            if ($pr['created_by']) {
                $msg = "✅ ใบขอซื้อได้รับการอนุมัติ\n";
                $msg .= "เลขที่: " . $pr['doc_no'] . "\n";
                $msg .= "ขั้นตอน: $level_label\n";
                $msg .= "ยอดสุทธิ: " . number_format($pr['grand_total'], 2) . " บาท\n";
                $msg .= "เปิดดู: " . getPRUrl($pr_id);
                notifyUserLine($pr['created_by'], $msg);
            }

            // แจ้งผู้อนุมัติคนถัดไป (ถ้ายังอนุมัติไม่ครบ)
            if (!$is_fully) {
                $next_roles = [];
                if (empty($pr['approved_by'])) {
                    $next_roles = ['procure'];
                } elseif (empty($pr['approved_by_1'])) {
                    $next_roles = ['gmacc'];
                } elseif (empty($pr['approved_by_2'])) {
                    $next_roles = ['mgr'];
                } elseif (empty($pr['approved_by_3'])) {
                    $next_roles = ['mgr2'];
                }

                if (!empty($next_roles)) {
                    $msg_next = "📋 ใบขอซื้อรอการอนุมัติจากคุณ\n";
                    $msg_next .= "เลขที่: " . $pr['doc_no'] . "\n";
                    $msg_next .= "ผ่านขั้นตอน: $level_label แล้ว\n";
                    $msg_next .= "ยอดสุทธิ: " . number_format($pr['grand_total'], 2) . " บาท\n";
                    $msg_next .= "เปิดดู: " . getPRUrl($pr_id) . "\n";
                    $msg_next .= "⚠️ กรุณาอนุมัติในขั้นตอนถัดไป";
                    notifyRoleGroupLine($next_roles, $msg_next, $pr['supplier_id']);
                }
            }

            // แจ้งจัดซื้อ (Procure) ทุกครั้งที่หัวหน้าอนุมัติ โดยไม่ filter supplier
            if ($update_col === 'approved_by_0') {
                $msg_procure = "📋 ใบขอซื้อผ่านหัวหน้าแผนกแล้ว กรุณาดำเนินการจัดซื้อ\n";
                $msg_procure .= "เลขที่: " . $pr['doc_no'] . "\n";
                $msg_procure .= "ยอดสุทธิ: " . number_format($pr['grand_total'], 2) . " บาท\n";
                $msg_procure .= "เปิดดู: " . getPRUrl($pr_id);
                notifyRoleGroupLine(['procure'], $msg_procure);
            }
        } catch (Exception $e) {}
    }

    if ($is_fully) {
        pr_approval_finish($conn, $pr_id, $pr, $user_id);
    }

    return ['full_approved' => $is_fully, 'approver_count' => $approver_count];
}

/** อนุมัติครบ: เปลี่ยนสถานะ + สร้าง PO อัตโนมัติ + แจ้งเตือน */
function pr_approval_finish(mysqli $conn, int $pr_id, array $pr, int $user_id): void
{
    $finish_stmt = $conn->prepare("UPDATE pr SET status = 'approved' WHERE id = ?");
    $finish_stmt->bind_param("i", $pr_id);
    $finish_stmt->execute();
    pr_log_activity($conn, $pr_id, 'fully_approved', $user_id);

    // --- AUTO CREATE PO ---
    try {
        $pr_query = $conn->prepare("SELECT * FROM pr WHERE id = ?");
        $pr_query->bind_param("i", $pr_id);
        $pr_query->execute();
        $pr_full = $pr_query->get_result()->fetch_assoc();

        // บันทึกหัว PO (ใช้ pr.doc_no เป็น reference_no ของ PO)
        $sql_po = "INSERT INTO po (
            doc_no, customer_id, supplier_id, reference_no, express_ref_code,
            due_date, payment_term, requested_by,
            notes, subtotal, vat_percent, vat_amount,
            wht_percent, wht_amount, grand_total,
            status, created_by, created_at, attachment_1, attachment_2
        ) VALUES (
            '', ?, ?, ?, '',
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            'pending', ?, NOW(), ?, ?
        )";

        $stmt_po = $conn->prepare($sql_po);
        $stmt_po->bind_param("iisssssddddddiss",
            $pr_full['customer_id'],
            $pr_full['supplier_id'],
            $pr_full['doc_no'],
            $pr_full['due_date'],
            $pr_full['payment_term'],
            $pr_full['requested_by'],
            $pr_full['notes'],
            $pr_full['subtotal'],
            $pr_full['vat_percent'],
            $pr_full['vat'],
            $pr_full['wht_percent'],
            $pr_full['wht_amount'],
            $pr_full['grand_total'],
            $user_id,
            $pr_full['attachment_1'],
            $pr_full['attachment_2']
        );

        if ($stmt_po->execute()) {
            $new_po_id = $conn->insert_id;

            // เจนเลขที่ PO (PO-6903XXXX)
            $date_prefix = (date('y') + 43) . date('m');
            $new_doc_no = "PO-" . $date_prefix . str_pad($new_po_id, 4, '0', STR_PAD_LEFT);
            $conn->query("UPDATE po SET doc_no = '$new_doc_no' WHERE id = $new_po_id");
            pr_log_activity($conn, $pr_id, 'po_created', $user_id, 'เลขที่ ' . $new_doc_no);

            // คัดลอกรายการสินค้าจาก pr_items ไป po_items
            $items_query = $conn->prepare("SELECT * FROM pr_items WHERE pr_id = ?");
            $items_query->bind_param("i", $pr_id);
            $items_query->execute();
            $items_res = $items_query->get_result();

            $stmt_po_item = $conn->prepare("INSERT INTO po_items (po_id, item_desc, item_qty, item_unit, item_price, item_discount, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            while ($item = $items_res->fetch_assoc()) {
                $stmt_po_item->bind_param("isdsddd",
                    $new_po_id,
                    $item['item_desc'],
                    $item['item_qty'],
                    $item['item_unit'],
                    $item['item_price'],
                    $item['item_discount'],
                    $item['item_total']
                );
                $stmt_po_item->execute();
            }

            // --- LINE NOTIFICATION (Fully Approved) ---
            try {
                require_once __DIR__ . '/api/line_notify.php';
                $sup_res = mysqli_query($conn, "SELECT company_name, line_token FROM suppliers WHERE id = " . (int)$pr_full['supplier_id']);
                $sup_data = mysqli_fetch_assoc($sup_res);
                if (!empty($sup_data['line_token'])) {
                    $msg = "✅ ใบขอซื้ออนุมัติสมบูรณ์\n";
                    $msg .= "เลขที่ PR: " . $pr_full['doc_no'] . "\n";
                    $msg .= "สร้างเลขที่ PO: " . $new_doc_no . "\n";
                    $msg .= "บริษัท: " . $sup_data['company_name'] . "\n";
                    $msg .= "ยอดสุทธิ: " . number_format($pr_full['grand_total'], 2) . " บาท\n";
                    sendLineNotify($msg, $sup_data['line_token']);
                }
            } catch (Exception $e) {}
        }
    } catch (Exception $e) {
        // กรณีสร้าง PO พลาด PR ยังถือว่าอนุมัติสำเร็จ
    }

    // --- LINE NOTIFY แจ้งผู้สร้าง PR ว่าอนุมัติครบถ้วน + สร้าง PO ---
    try {
        require_once __DIR__ . '/api/notify_helper.php';
        if ($pr['created_by']) {
            $msg = "🎉 ใบขอซื้ออนุมัติครบถ้วน!\n";
            $msg .= "เลขที่ PR: " . $pr['doc_no'] . "\n";
            $msg .= "สร้าง PO อัตโนมัติแล้ว\n";
            $msg .= "ยอดสุทธิ: " . number_format($pr['grand_total'], 2) . " บาท\n";
            $msg .= "เปิดดู: " . getPRUrl($pr_id);
            notifyUserLine($pr['created_by'], $msg);
        }
    } catch (Exception $e) {}
}
