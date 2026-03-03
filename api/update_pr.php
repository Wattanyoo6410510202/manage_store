<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pr_id = mysqli_real_escape_string($conn, $_POST['pr_id']);
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    $reference_no = mysqli_real_escape_string($conn, $_POST['reference_no']);
    $payment_term = mysqli_real_escape_string($conn, $_POST['payment_term']);
    $requested_by = mysqli_real_escape_string($conn, $_POST['requested_by']);
    $contact_tel = mysqli_real_escape_string($conn, $_POST['contact_tel']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    // ตรวจสอบสถานะภายใน
    $is_internal = ($customer_id === 0) ? 1 : 0;

    mysqli_begin_transaction($conn);

    try {
        // --- ส่วนที่เพิ่ม: คำนวณยอดรวมใหม่ก่อนอัปเดตตารางหลัก ---
        $item_descs = $_POST['item_desc'] ?? [];
        $item_qtys = $_POST['item_qty'] ?? [];
        $item_prices = $_POST['item_price'] ?? [];
        
        $new_subtotal = 0;
        foreach ($item_descs as $key => $desc) {
            if (trim($desc) === '') continue;
            $qty = floatval($item_qtys[$key]);
            $price = floatval($item_prices[$key]);
            $new_subtotal += ($qty * $price);
        }
        $new_vat = $new_subtotal * 0.07;
        $new_grand_total = $new_subtotal + $new_vat;

        // 1. อัปเดตข้อมูลหลัก (เพิ่ม subtotal, vat, grand_total และ is_internal)
        $sql_update_pr = "UPDATE pr SET 
            supplier_id = '$supplier_id',
            customer_id = '$customer_id',
            is_internal = '$is_internal',
            due_date = '$due_date',
            reference_no = '$reference_no',
            payment_term = '$payment_term',
            requested_by = '$requested_by',
            contact_tel = '$contact_tel',
            notes = '$notes',
            subtotal = '$new_subtotal',
            vat = '$new_vat',
            grand_total = '$new_grand_total',
            updated_at = NOW()
            WHERE id = '$pr_id'";

        if (!mysqli_query($conn, $sql_update_pr)) {
            throw new Exception("ไม่สามารถอัปเดตข้อมูลหลักได้: " . mysqli_error($conn));
        }

        // 2. ลบรายการสินค้าเดิม
        $sql_delete_items = "DELETE FROM pr_items WHERE pr_id = '$pr_id'";
        mysqli_query($conn, $sql_delete_items);

        // 3. บันทึกรายการสินค้าใหม่
        $item_units = $_POST['item_unit'] ?? [];
        foreach ($item_descs as $key => $desc) {
            if (trim($desc) === '') continue;

            $d = mysqli_real_escape_string($conn, $desc);
            $q = floatval($item_qtys[$key]);
            $u = mysqli_real_escape_string($conn, $item_units[$key]);
            $p = floatval($item_prices[$key]);
            $total = $q * $p;

            $sql_item = "INSERT INTO pr_items (pr_id, item_desc, item_qty, item_unit, item_price, item_total) 
                         VALUES ('$pr_id', '$d', '$q', '$u', '$p', '$total')";

            if (!mysqli_query($conn, $sql_item)) {
                throw new Exception("ไม่สามารถบันทึกรายการสินค้าได้: " . mysqli_error($conn));
            }
        }

        mysqli_commit($conn);
        $_SESSION['flash_msg'] = 'update_success';
        header("Location: ../pr_list.php");

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>alert('เกิดข้อผิดพลาด: " . $e->getMessage() . "'); window.history.back();</script>";
    }
}