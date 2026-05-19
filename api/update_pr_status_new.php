<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function send_json($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

function shutdown_handler() {
    $last_error = error_get_last();
    if ($last_error !== null && in_array($last_error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean();
        send_json('error', 'PHP Critical Error: ' . $last_error['message']);
    }
}
register_shutdown_function('shutdown_handler');

if (!isset($_SESSION['user_id'])) {
    send_json('error', 'Unauthorized access');
}

$pr_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$user_role = $_SESSION['role'] ?? '';

if ($pr_id <= 0) {
    send_json('error', 'Invalid PR ID');
}

if ($action !== 'approved') {
    send_json('error', 'Invalid action');
}

// Fetch PR data with requester role
$stmt = $conn->prepare("
    SELECT pr.*, u.role as requester_role 
    FROM pr 
    LEFT JOIN users u ON pr.created_by = u.id 
    WHERE pr.id = ?
");
$stmt->bind_param("i", $pr_id);
$stmt->execute();
$result = $stmt->get_result();
$pr = $result->fetch_assoc();

if (!$pr) {
    send_json('error', 'PR not found');
}

if ($pr['deleted_at'] !== null) {
    send_json('error', 'ใบขอซื้อนี้ถูกลบไปแล้ว');
}

if ($pr['status'] === 'approved') {
    send_json('error', 'ใบขอซื้อนี้ได้รับอนุมัติครบถ้วนแล้ว');
}

// Check if user already approved in any level
if (
    $pr['approved_by_0'] == $user_id ||
    $pr['approved_by'] == $user_id || 
    $pr['approved_by_1'] == $user_id || 
    $pr['approved_by_2'] == $user_id || 
    $pr['approved_by_3'] == $user_id
) {
    send_json('error', 'คุณได้ทำการอนุมัติรายการนี้ไปแล้ว');
}

$update_col = "";
$time_col = "";

// --- Dynamic Level 0 (Supervisor) Mapping ---
$requester_role = $pr['requester_role'] ?? '';
$is_gm = (strpos($user_role, 'gm') === 0);

// ถ้าเป็น GM ของแผนกไหน ให้สิทธิ์อนุมัติหัวหน้าของแผนกนั้น
// เช่น ถ้า requester เป็น 'staff_shotel' และ user เป็น 'gmshotel' ให้ผ่าน
$target_head_role = 'gm' . str_replace('staff_', '', $requester_role);

// กรณีพิเศษ: ถ้า requester เป็น 'hok' หรือ 'acc'
if ($requester_role == 'hok') $target_head_role = 'gmhok';
if ($requester_role == 'acc') $target_head_role = 'gmacc';

// Check for Level 0 Approval
if (empty($pr['approved_by_0'])) {
    // อนุมัติได้ถ้าเป็น GM ที่ถูกต้อง หรือ Admin/GMHOK
    if (($is_gm && $user_role === $target_head_role) || in_array($user_role, ['admin', 'gmhok'])) {
        $update_col = "approved_by_0";
        $time_col = "approved_at_0";
    }
}

// Role-based approval mapping for other levels
if (!$update_col) {
    if ($user_role === 'procure') {
        if (empty($pr['approved_by'])) { $update_col = "approved_by"; $time_col = "approved_at"; }
    } elseif ($user_role === 'gmacc') { // Changed from 'acc' to 'gmacc' per step.md
        if (empty($pr['approved_by_1'])) { $update_col = "approved_by_1"; $time_col = "approved_at_1"; }
    } elseif ($user_role === 'mgr') {
        if (empty($pr['approved_by_2'])) { $update_col = "approved_by_2"; $time_col = "approved_at_2"; }
    } elseif ($user_role === 'mgr2') {
        if (empty($pr['approved_by_3'])) { $update_col = "approved_by_3"; $time_col = ""; }
    } elseif (in_array($user_role, ['admin', 'gmhok'])) {
        // Admin/GMHOK can act as backup for other levels
        if (empty($pr['approved_by_0']) && $target_head_role === 'gmhok') { $update_col = "approved_by_0"; $time_col = "approved_at_0"; }
        elseif (empty($pr['approved_by'])) { $update_col = "approved_by"; $time_col = "approved_at"; }
        elseif (empty($pr['approved_by_1'])) { $update_col = "approved_by_1"; $time_col = "approved_at_1"; }
        elseif (empty($pr['approved_by_2'])) { $update_col = "approved_by_2"; $time_col = "approved_at_2"; }
        elseif (empty($pr['approved_by_3'])) { $update_col = "approved_by_3"; $time_col = ""; }
    }
}

if (!$update_col) {
    send_json('error', 'คุณไม่มีสิทธิ์อนุมัติในขั้นตอนนี้ หรือมีการอนุมัติในส่วนของคุณไปแล้ว');
}

// Update the approval column and its timestamp (if exists)
$sql_update = "UPDATE pr SET $update_col = ?, updated_at = NOW() ";
if ($time_col) {
    $sql_update .= ", $time_col = NOW() ";
}
$sql_update .= " WHERE id = ?";

$update_stmt = $conn->prepare($sql_update);
$update_stmt->bind_param("ii", $user_id, $pr_id);

if ($update_stmt->execute()) {
    // Re-fetch to check overall status
    $stmt->execute();
    $pr = $stmt->get_result()->fetch_assoc();
    
    $approver_count = 0;
    if (!empty($pr['approved_by_0'])) $approver_count++;
    if (!empty($pr['approved_by'])) $approver_count++;
    if (!empty($pr['approved_by_1'])) $approver_count++;
    if (!empty($pr['approved_by_2'])) $approver_count++;
    if (!empty($pr['approved_by_3'])) $approver_count++;
    
    $limit_type = strtolower(trim($pr['budget_limit_type'] ?? 'low'));
    $is_fully = false;
    
    // Updated is_fully logic to require level 0 (approved_by_0)
    $has_level0 = !empty($pr['approved_by_0']);
    
    if ($has_level0) {
        if ($limit_type === 'low' && $approver_count >= 3) $is_fully = true; // Level 0 + 2 more
        elseif ($limit_type === 'mid' && $approver_count >= 4) $is_fully = true; // Level 0 + 3 more
        elseif ($limit_type === 'high') {
            // High: ต้องครบ (Level 0 + Procure + GMACC + Mgr2)
            if (!empty($pr['approved_by']) && !empty($pr['approved_by_1']) && !empty($pr['approved_by_3'])) {
                $is_fully = true;
            }
        }
        elseif ($approver_count >= 5) $is_fully = true;
    }

    if ($is_fully) {
        $finish_stmt = $conn->prepare("UPDATE pr SET status = 'approved' WHERE id = ?");
        $finish_stmt->bind_param("i", $pr_id);
        $finish_stmt->execute();

        // --- AUTO CREATE PO ---
        try {
            // 1. ดึงข้อมูล PR ทั้งหมดอีกครั้ง
            $pr_query = $conn->prepare("SELECT * FROM pr WHERE id = ?");
            $pr_query->bind_param("i", $pr_id);
            $pr_query->execute();
            $pr_full = $pr_query->get_result()->fetch_assoc();

            // 2. บันทึกหัว PO (ใช้ pr.doc_no เป็น reference_no ของ PO)
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
                $pr_full['doc_no'], // ใช้เลขที่ PR เป็น reference_no
                $pr_full['due_date'], 
                $pr_full['payment_term'], 
                $pr_full['requested_by'], 
                $pr_full['notes'], 
                $pr_full['subtotal'], 
                $pr_full['vat_percent'], 
                $pr_full['vat'], // vat_amount ใน po คือ vat ใน pr
                $pr_full['wht_percent'], 
                $pr_full['wht_amount'], 
                $pr_full['grand_total'], 
                $user_id,
                $pr_full['attachment_1'],
                $pr_full['attachment_2']
            );

            if ($stmt_po->execute()) {
                $new_po_id = $conn->insert_id;
                
                // 3. เจนเลขที่ PO (PO-6903XXXX)
                $date_prefix = (date('y') + 43) . date('m');
                $new_doc_no = "PO-" . $date_prefix . str_pad($new_po_id, 4, '0', STR_PAD_LEFT);
                $conn->query("UPDATE po SET doc_no = '$new_doc_no' WHERE id = $new_po_id");

                // 4. คัดลอกรายการสินค้าจาก pr_items ไป po_items
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
            }
        } catch (Exception $e) {
            // กรณีสร้าง PO พลาด อาจจะ log ไว้ แต่ PR ยังถือว่าอนุมัติสำเร็จ
        }
    }

    send_json('success', 'อนุมัติเรียบร้อยแล้ว', [
        'full_approved' => $is_fully, 
        'column' => $update_col,
        'approved_date' => date('d/m/y'),
        'approver_count' => $approver_count
    ]);
} else {
    send_json('error', 'Database update failed: ' . $conn->error);
}

$conn->close();
?>