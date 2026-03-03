<?php
require_once '../config.php';
// เริ่ม session เพื่อเอา user_id คนที่ login
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// 1. รับค่าจากฟอร์ม
$customer_id  = (int)($_POST['customer_id'] ?? 0);
$supplier_id  = (int)($_POST['supplier_id'] ?? 0);
$doc_date     = $_POST['doc_date'] ?? date('Y-m-d');
$due_date     = $_POST['due_date'] ?? null;
$reference_no = $_POST['reference_no'] ?? '';
$payment_term = $_POST['payment_term'] ?? '';
$requested_by = $_POST['requested_by'] ?? '';
$contact_tel  = $_POST['contact_tel'] ?? '';
$notes        = $_POST['notes'] ?? '';

// --- จุดสำคัญ: ตรวจสอบว่าเป็นภายในหรือไม่ ---
$is_internal = ($customer_id === 0) ? 1 : 0; 
$user_id     = $_SESSION['user_id'] ?? 0; // ID พนักงานที่ Login

// รายการสินค้า
$items_desc  = $_POST['item_desc'] ?? [];
$items_qty   = $_POST['item_qty'] ?? [];
$items_unit  = $_POST['item_unit'] ?? [];
$items_price = $_POST['item_price'] ?? [];

mysqli_begin_transaction($conn);

try {
    // 2. สร้างเลขที่เอกสาร (เหมือนเดิม)
    $prefix = "PR";
    $sql_count = "SELECT COUNT(id) as total FROM pr WHERE doc_no LIKE '$prefix-%'";
    $res_count = mysqli_query($conn, $sql_count);
    $row_count = mysqli_fetch_assoc($res_count);
    $next_id = str_pad($row_count['total'] + 1, 6, '0', STR_PAD_LEFT);
    $doc_no = $prefix . "-" . $next_id;

    // 3. คำนวณราคารวม
    $subtotal = 0;
    foreach ($items_desc as $key => $val) {
        $qty = floatval($items_qty[$key]);
        $price = floatval($items_price[$key]);
        $subtotal += ($qty * $price);
    }
    $vat = $subtotal * 0.07;
    $grand_total = $subtotal + $vat;

    // 4. บันทึกลงตาราง pr (เพิ่ม is_internal และ created_by)
    // SQL เพิ่ม: is_internal, created_by
    $stmt = $conn->prepare("INSERT INTO pr (doc_no, doc_date, due_date, supplier_id, customer_id, is_internal, reference_no, payment_term, requested_by, contact_tel, notes, subtotal, vat, grand_total, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // bind_param: เพิ่ม i (is_internal) และ i (created_by) 
    // รูปแบบ: sssii i sssss ddd i
    $stmt->bind_param(
        "sssiisssssdddii",
        $doc_no,
        $doc_date,
        $due_date,
        $supplier_id,
        $customer_id,
        $is_internal,  // เพิ่มค่านี้
        $reference_no,
        $payment_term,
        $requested_by,
        $contact_tel,
        $notes,
        $subtotal,
        $vat,
        $grand_total,
        $user_id      // เพิ่มค่านี้
    );

    if (!$stmt->execute())
        throw new Exception("Error saving PR header: " . $stmt->error);
    
    $pr_id = $conn->insert_id;

    // 5. บันทึกลงตาราง pr_items (เหมือนเดิม)
    $stmt_item = $conn->prepare("INSERT INTO pr_items (pr_id, item_desc, item_qty, item_unit, item_price, item_total) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($items_desc as $key => $desc) {
        if (trim($desc) == "") continue;

        $qty   = floatval($items_qty[$key]);
        $unit  = $items_unit[$key];
        $price = floatval($items_price[$key]);
        $total = $qty * $price;

        $stmt_item->bind_param("isssdd", $pr_id, $desc, $qty, $unit, $price, $total);
        if (!$stmt_item->execute())
            throw new Exception("Error saving PR item: " . $desc);
    }

    mysqli_commit($conn);
    $_SESSION['flash_msg'] = 'add_success';
    header("Location: ../pr_list.php");

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['flash_msg'] = 'error';
    // จารสามารถเปิดบรรทัดล่างนี้เพื่อดู error ตอน debug ได้ครับ
    // die($e->getMessage()); 
    header("Location: ../pr_list.php");
}

$conn->close();
?>