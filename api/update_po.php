<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่า (ป้องกัน SQL Injection)
    $po_id = mysqli_real_escape_string($conn, $_POST['po_id']);
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $reference_no = mysqli_real_escape_string($conn, $_POST['reference_no']);
    $payment_term = mysqli_real_escape_string($conn, $_POST['payment_term']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $po_date = mysqli_real_escape_string($conn, $_POST['po_date']);

    $item_descs = $_POST['item_desc'] ?? [];
    $item_qtys = $_POST['item_qty'] ?? [];
    $item_units = $_POST['item_unit'] ?? [];
    $item_prices = $_POST['item_price'] ?? [];

    mysqli_begin_transaction($conn);

    try {
        // 2. คำนวณยอด
        $calc_subtotal = 0;
        foreach ($item_qtys as $index => $qty) {
            $price = $item_prices[$index] ?? 0;
            $calc_subtotal += ($qty * $price);
        }
        $vat = $calc_subtotal * 0.07;
        $grand_total = $calc_subtotal + $vat;

        // 3. UPDATE ตาราง po (แก้ชื่อคอลัมน์ให้ตรง DB)
        $sql_update_po = "UPDATE po SET 
                            supplier_id = '$supplier_id',
                            customer_id = '$customer_id',
                            reference_no = '$reference_no',
                            payment_term = '$payment_term',
                            notes = '$notes',
                            subtotal = '$calc_subtotal',  -- แก้จาก total_amount เป็น subtotal
                            vat_amount = '$vat',
                            grand_total = '$grand_total',
                            created_at = '$po_date' 
                          WHERE id = '$po_id'";

        if (!mysqli_query($conn, $sql_update_po)) {
            throw new Exception("Error Update PO: " . mysqli_error($conn));
        }

        // 4. จัดการ po_items
        // 5. จัดการรายการสินค้า (ลบของเก่าทิ้ง แล้วเพิ่มของใหม่)
        $sql_delete_items = "DELETE FROM po_items WHERE po_id = '$po_id'";
        mysqli_query($conn, $sql_delete_items);

        foreach ($item_descs as $index => $desc) {
            if (empty(trim($desc)))
                continue;

            $desc = mysqli_real_escape_string($conn, $desc);
            $qty = mysqli_real_escape_string($conn, $item_qtys[$index]);
            $unit = mysqli_real_escape_string($conn, $item_units[$index]);
            $price = mysqli_real_escape_string($conn, $item_prices[$index]);

            // คำนวณยอดรวมต่อบรรทัด
            $line_total = $qty * $price;

            // แก้ไขชื่อคอลัมน์จาก item_total เป็น total_price ให้ตรงกับ DB ของคุณ
            $sql_item = "INSERT INTO po_items (po_id, item_desc, item_qty, item_unit, item_price, total_price) 
                 VALUES ('$po_id', '$desc', '$qty', '$unit', '$price', '$line_total')";

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
        // แสดง Error ออกมาดูแบบชัดๆ
        die("Database Error: " . $e->getMessage());
    }
}