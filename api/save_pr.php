<?php
require_once '../config.php';
date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- ฟังก์ชันเขียน Log เพื่อการ Debug ---
function write_log($message) {
    $log_file = 'debug.log';
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    write_log("Error: Invalid Method");
    die("Method not allowed");
}

// 1. รับค่าจากฟอร์ม
$customer_id  = (int)($_POST['customer_id'] ?? 0);
$supplier_id  = (int)($_POST['supplier_id'] ?? 0);
$doc_date     = $_POST['doc_date'] ?? date('Y-m-d');
$due_date     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$reference_no = $_POST['reference_no'] ?? '';
$payment_term = $_POST['payment_term'] ?? '';
$requested_by = $_POST['requested_by'] ?? '';
$contact_tel  = $_POST['contact_tel'] ?? '';
$notes        = $_POST['notes'] ?? '';
$vat_percent  = floatval($_POST['vat_percent'] ?? 7);
$is_internal  = ($customer_id === 0) ? 1 : 0; 
$user_id      = $_SESSION['user_id'] ?? 0;

$items_desc     = $_POST['item_desc'] ?? [];
$items_qty      = $_POST['item_qty'] ?? [];
$items_unit     = $_POST['item_unit'] ?? [];
$items_price    = $_POST['item_price'] ?? [];
$items_discount = $_POST['item_discount'] ?? [];

mysqli_begin_transaction($conn);

try {
    write_log("Starting PR Save Process...");

    // 2. คำนวณยอด
    $total_subtotal = 0;
    foreach ($items_desc as $key => $val) {
        if (trim($val) == "") continue;
        $qty      = floatval($items_qty[$key]);
        $price    = floatval($items_price[$key]);
        $discount = floatval($items_discount[$key] ?? 0);
        $total_subtotal += ($qty * $price) - $discount;
    }
    $total_vat = $total_subtotal * ($vat_percent / 100);
    $grand_total = $total_subtotal + $total_vat;

    // 3. บันทึกตารางหลัก
    // มีเครื่องหมาย ? ทั้งหมด 16 จุด (ตัวสุดท้ายคือ status เป็นค่าคงที่ 'pending')
    $sql_main = "INSERT INTO pr (
                    doc_no, doc_date, due_date, supplier_id, customer_id, is_internal, 
                    reference_no, payment_term, requested_by, contact_tel, notes, 
                    subtotal, vat, grand_total, vat_percent, created_by, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

    $stmt = $conn->prepare($sql_main);
    if (!$stmt) {
        throw new Exception("Prepare Statement Failed (Main): " . $conn->error);
    }

    $temp_no = "TEMP-" . time();
    // bind_param ต้องมี 16 ตัว (ตรงตามจำนวน ? ในวงเล็บ VALUES)
    $stmt->bind_param(
        "sssiissssssddddi", 
        $temp_no, $doc_date, $due_date, $supplier_id, $customer_id, $is_internal,
        $reference_no, $payment_term, $requested_by, $contact_tel, $notes,
        $total_subtotal, $total_vat, $grand_total, $vat_percent, $user_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Execute Failed (Main): " . $stmt->error);
    }
    
    $pr_id = $conn->insert_id;
    write_log("Inserted PR ID: $pr_id");

    // 4. สร้างเลขที่เอกสารใหม่ (PR-260305XXXXXX)
    $date_prefix = date('ymd'); 
    $new_doc_no = "PR-" . $date_prefix . str_pad($pr_id, 6, '0', STR_PAD_LEFT);
    $conn->query("UPDATE pr SET doc_no = '$new_doc_no' WHERE id = $pr_id");
    write_log("Generated Doc No: $new_doc_no");

    // 5. บันทึกตารางลูก
    $stmt_item = $conn->prepare("INSERT INTO pr_items (pr_id, item_desc, item_qty, item_unit, item_price, item_discount, item_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($items_desc as $key => $desc) {
        if (trim($desc) == "") continue;
        $qty      = floatval($items_qty[$key]);
        $unit     = $items_unit[$key];
        $price    = floatval($items_price[$key]);
        $discount = floatval($items_discount[$key] ?? 0);
        $total    = ($qty * $price) - $discount;

        $stmt_item->bind_param("isssddd", $pr_id, $desc, $qty, $unit, $price, $discount, $total);
        if (!$stmt_item->execute()) {
            throw new Exception("Execute Failed (Item): " . $stmt_item->error);
        }
    }

    mysqli_commit($conn);
    write_log("PR Saved Successfully!");
    
    $_SESSION['flash_msg'] = 'add_success';
    header("Location: ../pr_list.php");

} catch (Exception $e) {
    mysqli_rollback($conn);
    write_log("FATAL ERROR: " . $e->getMessage());
    
    // แสดง Error ออกหน้าจอด้วยเพื่อความชัดเจน
    echo "<h3>เกิดข้อผิดพลาดในการบันทึก</h3>";
    echo "สาเหตุ: " . $e->getMessage();
    echo "<br><br><a href='javascript:history.back()'>กลับไปแก้ไข</a>";
    exit;
}

$conn->close();
?>