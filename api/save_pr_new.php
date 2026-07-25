<?php
require_once '../config.php';
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Method not allowed");

$customer_id = (int) ($_POST['customer_id'] ?? 0);
$supplier_id = (int)($_POST['supplier_id'] ?? 0);

// ถ้า user มี sup_id ให้บังคับใช้ sup_id ของตัวเอง (ป้องกันเลือก supplier ผิด)
$user_sup_id = (int)($_SESSION['sup_id'] ?? 0);
if ($user_sup_id > 0 && $supplier_id !== $user_sup_id) {
    $supplier_id = $user_sup_id;
}
$store_id    = (int)($_POST['store_id'] ?? 0);
$doc_date    = $_POST['doc_date'] ?? date('Y-m-d');
$due_date    = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$priority    = $_POST['priority'] ?? 'ปานกลาง';
$reference_no = $_POST['reference_no'] ?? '';
$payment_term = $_POST['payment_term'] ?? '';
$requested_by = $_POST['requested_by'] ?? '';
$contact_tel  = $_POST['contact_tel'] ?? '';
$notes        = $_POST['notes'] ?? '';
$vat_percent  = floatval($_POST['vat_percent'] ?? 7);
$wht_percent  = floatval($_POST['wht_percent'] ?? 0);
$is_internal  = ($customer_id === 0) ? 1 : 0;
$user_id      = $_SESSION['user_id'] ?? 0;

$expense_cat_id    = (int)($_POST['expense_cat_id'] ?? 0);
$budget_type_id    = (int)($_POST['budget_type_id'] ?? 0);
$objective_id      = (int)($_POST['objective_id'] ?? 0);
$budget_limit_type = $_POST['budget_limit_type'] ?? '';
$budget_amount     = floatval($_POST['budget_amount'] ?? 0);
$budget_details    = $_POST['budget_details'] ?? '';
$expectation       = $_POST['expectation'] ?? '';
$practice_method   = $_POST['practice_method'] ?? '';

// จัดการไฟล์
function uploadPRFile($file_key) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/pr/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
        $new_name = 'PR_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $upload_dir . $new_name)) return $new_name;
    }
    return null;
}
$attachment_1 = uploadPRFile('attachment_1');
$attachment_2 = uploadPRFile('attachment_2');

// ถ้า store_id = 0 ให้เก็บเป็น NULL
if ($store_id === 0) $store_id = null;

$items_desc = $_POST['item_desc'] ?? [];
$items_qty = $_POST['item_qty'] ?? [];
$items_price = $_POST['item_price'] ?? [];
$items_discount = $_POST['item_discount'] ?? [];
$items_unit = $_POST['item_unit'] ?? [];

mysqli_begin_transaction($conn);
try {
    $total_subtotal = 0;
    foreach ($items_desc as $key => $val) {
        if (trim($val) == "") continue;
        $total_subtotal += (floatval($items_qty[$key]) * floatval($items_price[$key])) - floatval($items_discount[$key] ?? 0);
    }
    $total_vat = $total_subtotal * ($vat_percent / 100);
    $total_wht = $total_subtotal * ($wht_percent / 100);
    $grand_total = ($total_subtotal + $total_vat) - $total_wht;
    $total_after_wht = $grand_total; // เพิ่มการคำนวณค่านี้
    $status = 'pending';
    $temp_no = "TEMP-" . time();
    $notes_to_save = trim($notes);

    // SQL สำหรับ Insert (ใส่ค่าว่างสำหรับ budget_limit_type ไปก่อนเพื่อกัน error)
    $sql = "INSERT INTO pr SET 
        doc_no = ?, doc_date = ?, due_date = ?, priority = ?, supplier_id = ?, store_id = ?, customer_id = ?, 
        expense_cat_id = ?, budget_type_id = ?, objective_id = ?, budget_amount = ?, 
        expectation = ?, practice_method = ?, budget_details = ?, is_internal = ?, reference_no = ?, 
        payment_term = ?, requested_by = ?, contact_tel = ?, notes = ?, subtotal = ?, 
        vat = ?, grand_total = ?, vat_percent = ?, wht_percent = ?, wht_amount = ?, 
        total_after_wht = ?, created_by = ?, attachment_1 = ?, attachment_2 = ?, status = ?";
        
    $stmt = $conn->prepare($sql);
    // Bind 31 ตัว (เพิ่ม store_id)
    $stmt->bind_param("ssssiiiiiidsssissssddddddisssss", 
        $temp_no, $doc_date, $due_date, $priority, $supplier_id, $store_id, $customer_id, 
        $expense_cat_id, $budget_type_id, $objective_id, $budget_amount, 
        $expectation, $practice_method, $budget_details, $is_internal, $reference_no, 
        $payment_term, $requested_by, $contact_tel, $notes_to_save, $total_subtotal, 
        $total_vat, $grand_total, $vat_percent, $wht_percent, $total_wht, 
        $total_after_wht, $user_id, $attachment_1, $attachment_2, $status
    );
    $stmt->execute();
    
    $pr_id = $conn->insert_id;
    
    // บังคับ Update ทับตรงๆ เพื่อให้มั่นใจ 100% ว่าค่าเข้า
    $safe_limit = $conn->real_escape_string($budget_limit_type);
    $conn->query("UPDATE pr SET budget_limit_type = '$safe_limit' WHERE id = $pr_id");
    
    $new_doc_no = 'PR-' . ((date('y') + 43) . date('m')) . str_pad($pr_id, 4, '0', STR_PAD_LEFT);
    $conn->query("UPDATE pr SET doc_no = '$new_doc_no' WHERE id = $pr_id");

    $stmt_item = $conn->prepare("INSERT INTO pr_items (pr_id, item_desc, item_qty, item_unit, item_price, item_discount, item_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($items_desc as $key => $desc) {
        if (trim($desc) == "") continue;
        $q = floatval($items_qty[$key]);
        $u = $items_unit[$key];
        $p = floatval($items_price[$key]);
        $d = floatval($items_discount[$key] ?? 0);
        $t = ($q * $p) - $d;
        $stmt_item->bind_param("isdsddd", $pr_id, $desc, $q, $u, $p, $d, $t);
        $stmt_item->execute();
    }
    mysqli_commit($conn);
    
    // --- LINE NOTIFICATION ---
    try {
        require_once 'line_notify.php';
        $sup_res = mysqli_query($conn, "SELECT company_name, line_token FROM suppliers WHERE id = $supplier_id");
        $sup_data = mysqli_fetch_assoc($sup_res);
        
        if (!empty($sup_data['line_token'])) {
            $msg = "🔔 มีใบขอซื้อใหม่ (PR)\n";
            $msg .= "เลขที่: " . $new_doc_no . "\n";
            $msg .= "บริษัท: " . $sup_data['company_name'] . "\n";
            $msg .= "ยอดสุทธิ: " . number_format($grand_total, 2) . " บาท\n";
            $msg .= "ผู้ขอซื้อ: " . ($_SESSION['user_name'] ?? 'ไม่ระบุ') . "\n";
            $msg .= "วันที่ต้องการ: " . ($due_date ? date('d/m/Y', strtotime($due_date)) : '-') . "\n";
            $msg .= "ลิ้งค์: " . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . str_replace('api/save_pr_new.php', 'view_pr_new.php?id='.$pr_id, $_SERVER['PHP_SELF']);
            
            sendLineNotify($msg, $sup_data['line_token']);
        }
    } catch (Exception $e) { /* Ignore line error */ }

    // --- LINE NOTIFY หัวหน้าแผนก (GM) เมื่อมี PR ใหม่รออนุมัติ ---
    try {
        require_once 'notify_helper.php';

        $msg = "📋 ใบขอซื้อใหม่รอการอนุมัติ\n";
        $msg .= "เลขที่: " . $new_doc_no . "\n";
        $msg .= "บริษัท: " . ($sup_data['company_name'] ?? '-') . "\n";
        $msg .= "ยอดสุทธิ: " . number_format($grand_total, 2) . " บาท\n";
        $msg .= "ผู้ขอซื้อ: " . ($_SESSION['user_name'] ?? 'ไม่ระบุ') . "\n";
        $msg .= "เปิดดู: " . getPRUrl($pr_id) . "\n";
        $msg .= "⚠️ กรุณาอนุมัติใบขอซื้อนี้";

        // แจ้ง GM ทุกคนที่ sup_id ตรงกับ supplier_id ของ PR
        if (!empty($supplier_id)) {
            notifyGMsBySupId($msg, $supplier_id);
        }

        // แจ้ง Admin/GMHOK ด้วย (เผื่อกรณีหัวหน้าไม่อยู่)
        $msg_admin = $msg . "\n\n(แจ้ง Admin: มี PR ใหม่รอการดำเนินการ)";
        notifyRoleGroupLine(['admin', 'gmhok'], $msg_admin, $supplier_id);
    } catch (Exception $e) { /* Ignore line error */ }

    $_SESSION['flash_msg'] = 'add_success';
    header("Location: ../request_buy_history.php");
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "Error: " . $e->getMessage();
}
$conn->close();
?>