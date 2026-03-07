<?php
require_once '../config.php';
date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) { session_start(); }



header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Method not allowed");
}

// 1. รับค่าจากฟอร์ม
$customer_id   = (int)($_POST['customer_id'] ?? 0);
$supplier_id   = (int)($_POST['supplier_id'] ?? 0);
$doc_date      = $_POST['doc_date'] ?? date('Y-m-d');
$due_date      = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$reference_no  = $_POST['reference_no'] ?? '';
$payment_term  = $_POST['payment_term'] ?? '';
$requested_by  = $_POST['requested_by'] ?? '';
$contact_tel   = $_POST['contact_tel'] ?? '';
$notes         = $_POST['notes'] ?? '';
$vat_percent   = floatval($_POST['vat_percent'] ?? 7);
$wht_percent   = floatval($_POST['wht_percent'] ?? 0); // เพิ่มรับค่า หัก ณ ที่จ่าย
$is_internal   = ($customer_id === 0) ? 1 : 0; 
$user_id       = $_SESSION['user_id'] ?? 0;

$items_desc     = $_POST['item_desc'] ?? [];
$items_qty      = $_POST['item_qty'] ?? [];
$items_unit     = $_POST['item_unit'] ?? [];
$items_price    = $_POST['item_price'] ?? [];
$items_discount = $_POST['item_discount'] ?? [];

mysqli_begin_transaction($conn);

try {

    // 2. คำนวณยอด
    $total_subtotal = 0;
    foreach ($items_desc as $key => $val) {
        if (trim($val) == "") continue;
        $qty      = floatval($items_qty[$key]);
        $price    = floatval($items_price[$key]);
        $discount = floatval($items_discount[$key] ?? 0);
        $total_subtotal += ($qty * $price) - $discount;
    }
    
    // คำนวณภาษีและยอดหัก
    $total_vat = $total_subtotal * ($vat_percent / 100);
    $total_wht = $total_subtotal * ($wht_percent / 100); // หัก ณ ที่จ่าย (ฐานจากยอดก่อน VAT)
    $grand_total = ($total_subtotal + $total_vat) - $total_wht;

    // 3. บันทึกตารางหลัก (เพิ่มคอลัมน์ wht_amount และ wht_percent)
    $sql_main = "INSERT INTO pr (
                    doc_no, doc_date, due_date, supplier_id, customer_id, is_internal, 
                    reference_no, payment_term, requested_by, contact_tel, notes, 
                    subtotal, vat, wht_amount, grand_total, vat_percent, wht_percent, created_by, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

    $stmt = $conn->prepare($sql_main);
    if (!$stmt) {
        throw new Exception("Prepare Statement Failed (Main): " . $conn->error);
    }

    $temp_no = "TEMP-" . time();
    
    // bind_param: sssiissssssddddddi (18 ตัวแปร)
    // s = string, i = integer, d = double (decimal)
    $stmt->bind_param(
        "sssiissssssddddddi", 
        $temp_no, $doc_date, $due_date, $supplier_id, $customer_id, $is_internal,
        $reference_no, $payment_term, $requested_by, $contact_tel, $notes,
        $total_subtotal, $total_vat, $total_wht, $grand_total, $vat_percent, $wht_percent, $user_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Execute Failed (Main): " . $stmt->error);
    }
    
    $pr_id = $conn->insert_id;
    ("Inserted PR ID: $pr_id");

    // 4. สร้างเลขที่เอกสารใหม่ (PR-260305XXXXXX)
    $date_prefix = date('ymd'); 
    $new_doc_no = "PR-" . $date_prefix . str_pad($pr_id, 6, '0', STR_PAD_LEFT);
    $conn->query("UPDATE pr SET doc_no = '$new_doc_no' WHERE id = $pr_id");
    ("Generated Doc No: $new_doc_no");

    // 5. บันทึกตารางลูก
    $stmt_item = $conn->prepare("INSERT INTO pr_items (pr_id, item_desc, item_qty, item_unit, item_price, item_discount, item_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($items_desc as $key => $desc) {
        if (trim($desc) == "") continue;
        $qty      = floatval($items_qty[$key]);
        $unit     = $items_unit[$key];
        $price    = floatval($items_price[$key]);
        $discount = floatval($items_discount[$key] ?? 0);
        $total    = ($qty * $price) - $discount;

        // i = pr_id, s = desc, d = qty, s = unit, d = price, d = discount, d = total
        $stmt_item->bind_param("isdsddd", $pr_id, $desc, $qty, $unit, $price, $discount, $total);
        if (!$stmt_item->execute()) {
            throw new Exception("Execute Failed (Item): " . $stmt_item->error);
        }
    }

    mysqli_commit($conn);
    ("PR Saved Successfully!");
    
    $_SESSION['flash_msg'] = 'add_success';
    header("Location: ../pr_list.php");

} catch (Exception $e) {
    mysqli_rollback($conn);
    ("FATAL ERROR: " . $e->getMessage());
    
    echo "<h3>เกิดข้อผิดพลาดในการบันทึก</h3>";
    echo "สาเหตุ: " . $e->getMessage();
    echo "<br><br><a href='javascript:history.back()'>กลับไปแก้ไข</a>";
    exit;
}

$conn->close();
?>