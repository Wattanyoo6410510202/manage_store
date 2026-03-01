<?php
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// 1. รับค่าจากฟอร์ม
$customer_id = $_POST['customer_id'] ?? 0;
$supplier_id = $_POST['supplier_id'] ?? 0;
$doc_date = $_POST['doc_date'] ?? date('Y-m-d');
$due_date = $_POST['due_date'] ?? null;
$reference_no = $_POST['reference_no'] ?? '';
$payment_term = $_POST['payment_term'] ?? '';
$requested_by = $_POST['requested_by'] ?? '';
$contact_tel = $_POST['contact_tel'] ?? '';
$notes = $_POST['notes'] ?? '';

// รายการสินค้า (Arrays)
$items_desc = $_POST['item_desc'] ?? [];
$items_qty = $_POST['item_qty'] ?? [];
$items_unit = $_POST['item_unit'] ?? [];
$items_price = $_POST['item_price'] ?? [];

// เริ่มต้น Transaction
mysqli_begin_transaction($conn);

try {
    // 2. สร้างเลขที่เอกสารอัตโนมัติ (รูปแบบ PR-000001)
    $prefix = "PR";

    // นับจำนวนเอกสารทั้งหมดที่มี Prefix เป็น PR-
    $sql_count = "SELECT COUNT(id) as total FROM pr WHERE doc_no LIKE '$prefix-%'";
    $res_count = mysqli_query($conn, $sql_count);
    $row_count = mysqli_fetch_assoc($res_count);

    // บวกค่าเพิ่ม 1 และเติมเลข 0 ข้างหน้าให้ครบ 6 หลัก
    $next_id = str_pad($row_count['total'] + 1, 6, '0', STR_PAD_LEFT);

    // รวมร่างเป็น PR-000001
    $doc_no = $prefix . "-" . $next_id;

    // 3. คำนวณหาค่ารวม (Server-side calculation)
    $subtotal = 0;
    foreach ($items_desc as $key => $val) {
        $qty = floatval($items_qty[$key]);
        $price = floatval($items_price[$key]);
        $subtotal += ($qty * $price);
    }
    $vat = $subtotal * 0.07;
    $grand_total = $subtotal + $vat;

    // 4. บันทึกลงตาราง pr (หัวเอกสาร)
    $stmt = $conn->prepare("INSERT INTO pr (doc_no, doc_date, due_date, supplier_id, customer_id, reference_no, payment_term, requested_by, contact_tel, notes, subtotal, vat, grand_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "sssiisssssddd",
        $doc_no,
        $doc_date,
        $due_date,
        $supplier_id,
        $customer_id,
        $reference_no,
        $payment_term,
        $requested_by,
        $contact_tel,
        $notes,
        $subtotal,
        $vat,
        $grand_total
    );

    if (!$stmt->execute())
        throw new Exception("Error saving PR header");
    $pr_id = $conn->insert_id;

    // 5. บันทึกลงตาราง pr_items (รายการสินค้า)
    $stmt_item = $conn->prepare("INSERT INTO pr_items (pr_id, item_desc, item_qty, item_unit, item_price, item_total) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($items_desc as $key => $desc) {
        if (trim($desc) == "")
            continue; // ข้ามรายการที่ว่าง

        $qty = floatval($items_qty[$key]);
        $unit = $items_unit[$key];
        $price = floatval($items_price[$key]);
        $total = $qty * $price;

        $stmt_item->bind_param("isssdd", $pr_id, $desc, $qty, $unit, $price, $total);
        if (!$stmt_item->execute())
            throw new Exception("Error saving PR item: " . $desc);
    }

    // ถ้าทุกอย่างผ่าน Commit เลย!
    mysqli_commit($conn);
    $_SESSION['flash_msg'] = 'add_success';
    header("Location: ../pr_list.php");

} catch (Exception $e) {
    // ถ้าพลาดแม้แต่นิดเดียว Rollback ทั้งหมด
    mysqli_rollback($conn);
    $_SESSION['flash_msg'] = 'error';
    header("Location: ../pr_list.php");
}

$conn->close();
?>