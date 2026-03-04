<?php
// เปิด Error เพื่อ Debug (จารปิดได้ถ้าขึ้น Production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่าพื้นฐาน
    $pr_id        = (int)$_POST['pr_id'];
    $supplier_id  = (int)$_POST['supplier_id'];
    $customer_id  = (int)($_POST['customer_id'] ?? 0);
    $due_date     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $reference_no = $_POST['reference_no'] ?? '';
    $payment_term = $_POST['payment_term'] ?? '';
    $requested_by = $_POST['requested_by'] ?? '';
    $contact_tel  = $_POST['contact_tel'] ?? '';
    $notes        = $_POST['notes'] ?? '';
    $vat_percent  = floatval($_POST['vat_percent'] ?? 7); // รับค่า VAT จากฟอร์ม

    // รายการสินค้า (Array)
    $item_descs     = $_POST['item_desc'] ?? [];
    $item_qtys      = $_POST['item_qty'] ?? [];
    $item_units     = $_POST['item_unit'] ?? [];
    $item_prices    = $_POST['item_price'] ?? [];
    $item_discounts = $_POST['item_discount'] ?? []; // เพิ่มส่วนลด

    $is_internal = ($customer_id === 0) ? 1 : 0;

    mysqli_begin_transaction($conn);

    try {
        // 2. คำนวณราคารวมใหม่ทั้งหมด
        $new_subtotal = 0;
        foreach ($item_descs as $key => $desc) {
            if (trim($desc) === '') continue;
            
            $qty      = floatval($item_qtys[$key]);
            $price    = floatval($item_prices[$key]);
            $discount = floatval($item_discounts[$key] ?? 0);
            
            // สูตร: (จำนวน * ราคา) - ส่วนลด
            $new_subtotal += ($qty * $price) - $discount;
        }
        
        $new_vat = $new_subtotal * ($vat_percent / 100);
        $new_grand_total = $new_subtotal + $new_vat;

        // 3. อัปเดตข้อมูลหลัก (ตาราง purchase_requests)
        $sql_main = "UPDATE pr SET 
                        supplier_id = ?, customer_id = ?, is_internal = ?, 
                        due_date = ?, reference_no = ?, payment_term = ?, 
                        requested_by = ?, contact_tel = ?, notes = ?, 
                        subtotal = ?, vat = ?, grand_total = ?, 
                        vat_percent = ?, updated_at = NOW() 
                    WHERE id = ?";
        
        $stmt = $conn->prepare($sql_main);
        $stmt->bind_param(
            "iiissssssddddi",
            $supplier_id, $customer_id, $is_internal,
            $due_date, $reference_no, $payment_term,
            $requested_by, $contact_tel, $notes,
            $new_subtotal, $new_vat, $new_grand_total,
            $vat_percent, $pr_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Error Update Header: " . $stmt->error);
        }

        // 4. ลบรายการสินค้าเดิมทิ้งก่อน เพื่อบันทึกใหม่ (Clean & Re-insert)
        $sql_delete = "DELETE FROM pr_items WHERE pr_id = ?";
        $stmt_del = $conn->prepare($sql_delete);
        $stmt_del->bind_param("i", $pr_id);
        $stmt_del->execute();

        // 5. บันทึกรายการสินค้าใหม่
        $sql_item = "INSERT INTO pr_items (pr_id, item_desc, item_qty, item_unit, item_price, item_discount, item_total) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_item = $conn->prepare($sql_item);

        foreach ($item_descs as $key => $desc) {
            if (trim($desc) === '') continue;

            $q    = floatval($item_qtys[$key]);
            $u    = $item_units[$key];
            $p    = floatval($item_prices[$key]);
            $disc = floatval($item_discounts[$key] ?? 0);
            $total = ($q * $p) - $disc;

            $stmt_item->bind_param("isssddd", $pr_id, $desc, $q, $u, $p, $disc, $total);
            
            if (!$stmt_item->execute()) {
                throw new Exception("Error Insert Item: " . $desc);
            }
        }

        mysqli_commit($conn);
        $_SESSION['flash_msg'] = 'update_success';
        header("Location: ../pr_list.php");

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.history.back();</script>";
    }
}
$conn->close();
?>