<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pr_id = mysqli_real_escape_string($conn, $_POST['pr_id']);
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    $reference_no = mysqli_real_escape_string($conn, $_POST['reference_no']);
    $payment_term = mysqli_real_escape_string($conn, $_POST['payment_term']);
    $requested_by = mysqli_real_escape_string($conn, $_POST['requested_by']);
    $contact_tel = mysqli_real_escape_string($conn, $_POST['contact_tel']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    // เริ่ม Transaction เพื่อป้องกันข้อมูลบันทึกไม่ครบ
    mysqli_begin_transaction($conn);

    try {
        // 1. อัปเดตข้อมูลหลักในตาราง pr
        $sql_update_pr = "UPDATE pr SET 
            supplier_id = '$supplier_id',
            due_date = '$due_date',
            reference_no = '$reference_no',
            payment_term = '$payment_term',
            requested_by = '$requested_by',
            contact_tel = '$contact_tel',
            notes = '$notes',
            updated_at = NOW()
            WHERE id = '$pr_id'";

        if (!mysqli_query($conn, $sql_update_pr)) {
            throw new Exception("ไม่สามารถอัปเดตข้อมูลหลักได้: " . mysqli_error($conn));
        }

        // 2. ลบรายการสินค้าเดิมออกก่อน (เพื่อเตรียม Insert ชุดใหม่ที่แก้ไขแล้ว)
        $sql_delete_items = "DELETE FROM pr_items WHERE pr_id = '$pr_id'";
        mysqli_query($conn, $sql_delete_items);

        // 3. วนลูปบันทึกรายการสินค้าใหม่
        $item_descs = $_POST['item_desc'] ?? [];
        $item_qtys = $_POST['item_qty'] ?? [];
        $item_units = $_POST['item_unit'] ?? [];
        $item_prices = $_POST['item_price'] ?? [];

        foreach ($item_descs as $key => $desc) {
            if (trim($desc) === '')
                continue; // ข้ามรายการที่ว่าง

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

        // ถ้าทุกอย่างเรียบร้อยให้ Commit
        mysqli_commit($conn);
        $_SESSION['flash_msg'] = 'update_success';
        header("Location: ../pr_list.php");

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาดให้ Rollback ข้อมูลกลับทั้งหมด
        mysqli_rollback($conn);
        echo "<script>alert('เกิดข้อผิดพลาด: " . $e->getMessage() . "'); window.history.back();</script>";
    }
} else {
    header("Location: ../pr_list.php");
}
?>