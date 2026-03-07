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
    
    // รับค่าภาษีต่างๆ
    $vat_percent  = floatval($_POST['vat_percent'] ?? 7);
    $wht_percent  = floatval($_POST['wht_percent'] ?? 0); 

    // รายการสินค้า (Array)
    $item_descs     = $_POST['item_desc'] ?? [];
    $item_qtys      = $_POST['item_qty'] ?? [];
    $item_units     = $_POST['item_unit'] ?? [];
    $item_prices    = $_POST['item_price'] ?? [];
    $item_discounts = $_POST['item_discount'] ?? [];

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
            
            $new_subtotal += ($qty * $price) - $discount;
        }
        
        // 3. คำนวณยอดทางภาษี (Logic แบบ PO)
        $new_vat        = $new_subtotal * ($vat_percent / 100);
        $new_wht_amount = $new_subtotal * ($wht_percent / 100);
        
        // สูตร: (สินค้า + VAT) - หัก ณ ที่จ่าย = ยอดสุทธิที่จะไปลง grand_total
        $net_grand_total = ($new_subtotal + $new_vat) - $new_wht_amount;

        // 4. อัปเดตข้อมูลหลัก (โครงสร้างแบบ PO)
        $sql_main = "UPDATE pr SET 
                        supplier_id = ?, customer_id = ?, is_internal = ?, 
                        due_date = ?, reference_no = ?, payment_term = ?, 
                        requested_by = ?, contact_tel = ?, notes = ?, 
                        subtotal = ?, vat = ?, 
                        vat_percent = ?, wht_percent = ?, wht_amount = ?, 
                        grand_total = ?, updated_at = NOW() 
                    WHERE id = ?";
        
        $stmt = $conn->prepare($sql_main);
        
        // i=3, s=6, d=5, i=1 (รวม 15+1 = 16 ตัวแปร)
        // ลำดับ: i,i,i, s,s,s,s,s,s, d,d, d,d,d, d, i
        $stmt->bind_param(
            "iiissssssddddddi",
            $supplier_id, $customer_id, $is_internal,
            $due_date, $reference_no, $payment_term,
            $requested_by, $contact_tel, $notes,
            $new_subtotal, $new_vat, 
            $vat_percent, $wht_percent, $new_wht_amount,
            $net_grand_total, // ยอดสุทธิมาลงที่นี่แทน total_after_wht
            $pr_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Error Update Header: " . $stmt->error);
        }

        // 5. ลบรายการสินค้าเดิมทิ้งก่อน (Clean & Re-insert)
        $sql_delete = "DELETE FROM pr_items WHERE pr_id = ?";
        $stmt_del = $conn->prepare($sql_delete);
        $stmt_del->bind_param("i", $pr_id);
        $stmt_del->execute();

        // 6. บันทึกรายการสินค้าใหม่
        $sql_item = "INSERT INTO pr_items (pr_id, item_desc, item_qty, item_unit, item_price, item_discount, item_total) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_item = $conn->prepare($sql_item);

        foreach ($item_descs as $key => $desc) {
            if (trim($desc) === '') continue;

            $q     = floatval($item_qtys[$key]);
            $u     = $item_units[$key];
            $p     = floatval($item_prices[$key]);
            $disc  = floatval($item_discounts[$key] ?? 0);
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