<?php
require_once '../config.php';
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Method not allowed");

$customer_id = (int) ($_POST['customer_id'] ?? 0);
$supplier_id = (int)($_POST['supplier_id'] ?? 0);
$doc_date    = $_POST['doc_date'] ?? date('Y-m-d');
$due_date    = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
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
        doc_no = ?, doc_date = ?, due_date = ?, supplier_id = ?, customer_id = ?, 
        expense_cat_id = ?, budget_type_id = ?, objective_id = ?, budget_amount = ?, 
        expectation = ?, practice_method = ?, budget_details = ?, is_internal = ?, reference_no = ?, 
        payment_term = ?, requested_by = ?, contact_tel = ?, notes = ?, subtotal = ?, 
        vat = ?, grand_total = ?, vat_percent = ?, wht_percent = ?, wht_amount = ?, 
        total_after_wht = ?, created_by = ?, attachment_1 = ?, attachment_2 = ?, status = ?";
        
    $stmt = $conn->prepare($sql);
    // Bind 29 ตัว (ตัด budget_limit_type ออกไปก่อนเพื่อกัน error)
    $stmt->bind_param("sssiiiiidsssissssddddddisssss", 
        $temp_no, $doc_date, $due_date, $supplier_id, $customer_id, 
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
    
    $conn->query("UPDATE pr SET doc_no = 'PR-" . ((date('y') + 43) . date('m')) . str_pad($pr_id, 4, '0', STR_PAD_LEFT) . "' WHERE id = $pr_id");

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
    $_SESSION['flash_msg'] = 'add_success';
    header("Location: ../request_buy_history.php");
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "Error: " . $e->getMessage();
}
$conn->close();
?>