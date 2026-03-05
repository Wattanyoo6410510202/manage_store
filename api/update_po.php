<?php
session_start();
require_once '../config.php';
date_default_timezone_set('Asia/Bangkok');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าและทำความสะอาดข้อมูลพื้นฐาน
    $po_id        = mysqli_real_escape_string($conn, $_POST['po_id']);
    $supplier_id  = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $customer_id  = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $reference_no = mysqli_real_escape_string($conn, $_POST['reference_no']);
    $payment_term = mysqli_real_escape_string($conn, $_POST['payment_term']);
    $notes        = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // รับค่า VAT และ WHT Percent จากฟอร์ม
    $vat_percent  = isset($_POST['vat_percent']) ? floatval($_POST['vat_percent']) : 0;
    $wht_percent  = isset($_POST['wht_percent']) ? floatval($_POST['wht_percent']) : 0; // เพิ่มใหม่

    $item_descs     = $_POST['item_desc'] ?? [];
    $item_qtys      = $_POST['item_qty'] ?? [];
    $item_units     = $_POST['item_unit'] ?? [];
    $item_prices    = $_POST['item_price'] ?? [];
    $item_discounts = $_POST['item_discount'] ?? [];

    mysqli_begin_transaction($conn);

    try {
        // 2. คำนวณยอดรวมใหม่ทั้งหมด (ฝั่ง Server เพื่อความแม่นยำ)
        $calc_subtotal = 0;
        foreach ($item_qtys as $index => $qty) {
            if (empty(trim($item_descs[$index]))) continue; // ข้ามรายการว่าง
            $price    = floatval($item_prices[$index] ?? 0);
            $discount = floatval($item_discounts[$index] ?? 0);
            $qty_val  = floatval($qty);
            $calc_subtotal += (($qty_val * $price) - $discount);
        }
        
        // คำนวณภาษี
        $vat_amount  = $calc_subtotal * ($vat_percent / 100);
        $wht_amount  = $calc_subtotal * ($wht_percent / 100); // หัก ณ ที่จ่าย คำนวณจาก Subtotal
        
        // สูตร: (รวมสินค้า + VAT) - หัก ณ ที่จ่าย
        $grand_total = ($calc_subtotal + $vat_amount) - $wht_amount;

        // 3. UPDATE ตาราง po (เพิ่มคอลัมน์ wht เข้าไปด้วย)
        $sql_update_po = "UPDATE po SET 
                            supplier_id  = '$supplier_id',
                            customer_id  = '$customer_id',
                            reference_no = '$reference_no',
                            payment_term = '$payment_term',
                            notes        = '$notes',
                            subtotal     = '$calc_subtotal',
                            vat_percent  = '$vat_percent', 
                            vat_amount   = '$vat_amount',
                            wht_percent  = '$wht_percent', 
                            wht_amount   = '$wht_amount',
                            grand_total  = '$grand_total',
                            updated_at   = NOW() 
                          WHERE id = '$po_id'";

        if (!mysqli_query($conn, $sql_update_po)) {
            throw new Exception("Error Update PO: " . mysqli_error($conn));
        }

        // 4. ลบรายการสินค้าเดิมทิ้งก่อน
        $sql_delete_items = "DELETE FROM po_items WHERE po_id = '$po_id'";
        mysqli_query($conn, $sql_delete_items);

        // 5. Insert รายการสินค้าใหม่เข้าไป
        foreach ($item_descs as $index => $desc) {
            if (empty(trim($desc))) continue;

            $d    = mysqli_real_escape_string($conn, $desc);
            $q    = floatval($item_qtys[$index]);
            $u    = mysqli_real_escape_string($conn, $item_units[$index]);
            $p    = floatval($item_prices[$index]);
            $disc = floatval($item_discounts[$index] ?? 0);
            
            $line_total = ($q * $p) - $disc;

            $sql_item = "INSERT INTO po_items (po_id, item_desc, item_qty, item_unit, item_price, item_discount, total_price) 
                         VALUES ('$po_id', '$d', '$q', '$u', '$p', '$disc', '$line_total')";

            if (!mysqli_query($conn, $sql_item)) {
                throw new Exception("ไม่สามารถเพิ่มรายการสินค้าได้: " . mysqli_error($conn));
            }
        }

        mysqli_commit($conn);
        $_SESSION['flash_msg'] = 'update_success';
        header("Location: ../po_list.php");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        die("Database Error: " . $e->getMessage());
    }
}
?>