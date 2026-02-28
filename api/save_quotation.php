<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    // ไม่รับค่า doc_no จากฟอร์มแล้ว เพราะเราจะรันจาก ID อัตโนมัติ
    $doc_date = mysqli_real_escape_string($conn, $_POST['doc_date']);
    $valid_until = (int) $_POST['valid_until'];
    $payment_term = mysqli_real_escape_string($conn, $_POST['payment_term']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    $subtotal = 0;
    if (isset($_POST['item_qty'])) {
        foreach ($_POST['item_qty'] as $key => $qty) {
            $price = floatval($_POST['item_price'][$key]);
            $subtotal += (floatval($qty) * $price);
        }
    }
    $vat = $subtotal * 0.07;
    $grand_total = $subtotal + $vat;

    mysqli_begin_transaction($conn);

    try {
        // 1. บันทึกข้อมูลหลักก่อน (ใส่ค่าว่างใน doc_no ไว้ก่อน)
        $sql_quotation = "INSERT INTO quotations (
                            doc_no, doc_date, supplier_id, customer_id, 
                            valid_until, payment_term, notes, subtotal, 
                            vat, grand_total, created_by
                          ) VALUES (
                            '', '$doc_date', '$supplier_id', '$customer_id', 
                            '$valid_until', '$payment_term', '$notes', '$subtotal', 
                            '$vat', '$grand_total', '$user_id'
                          )";

        if (!mysqli_query($conn, $sql_quotation))
            throw new Exception(mysqli_error($conn));

        // 2. ดึง ID ที่เพิ่ง INSERT สำเร็จ
        $quotation_id = mysqli_insert_id($conn);

        // 3. สร้างเลขที่เอกสารจาก ID (เช่น QTN-00054)
        $new_doc_no = "QTN-" . str_pad($quotation_id, 6, '0', STR_PAD_LEFT);

        // 4. อัปเดตเลขที่เอกสารกลับเข้าไปที่ ID นั้น
        $update_doc_no = "UPDATE quotations SET doc_no = '$new_doc_no' WHERE id = $quotation_id";
        if (!mysqli_query($conn, $update_doc_no))
            throw new Exception(mysqli_error($conn));

        // บันทึกรายการสินค้า (เหมือนเดิม)
        if (isset($_POST['item_desc'])) {
            foreach ($_POST['item_desc'] as $key => $desc) {
                if (trim($desc) == "")
                    continue;

                $item_desc = mysqli_real_escape_string($conn, $desc);
                $item_qty = floatval($_POST['item_qty'][$key]);
                $item_price = floatval($_POST['item_price'][$key]);
                $item_total = $item_qty * $item_price;

                $sql_item = "INSERT INTO quotation_items (quotation_id, item_desc, item_qty, item_price, item_total) 
                             VALUES ('$quotation_id', '$item_desc', '$item_qty', '$item_price', '$item_total')";
                if (!mysqli_query($conn, $sql_item))
                    throw new Exception(mysqli_error($conn));
            }
        }

        mysqli_commit($conn);
        $_SESSION['flash_msg'] = 'add_success';
        header("Location: ../doc_list.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['flash_msg'] = 'error';
        // พิมพ์ Error ออกมาดูถ้าบันทึกไม่สำเร็จ (สำหรับตอน Test)
        // die($e->getMessage()); 
        header("Location: ../doc_list.php");
        exit();
    }
}
?>