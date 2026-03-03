<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quotation_id = mysqli_real_escape_string($conn, $_POST['quotation_id']);
    $supplier_id  = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $doc_date     = mysqli_real_escape_string($conn, $_POST['doc_date']);
    $valid_until  = mysqli_real_escape_string($conn, $_POST['valid_until']);
    $payment_term = mysqli_real_escape_string($conn, $_POST['payment_term']);
    $notes        = mysqli_real_escape_string($conn, $_POST['notes']);

    // เริ่ม Transaction เพื่อความปลอดภัยของข้อมูล
    mysqli_begin_transaction($conn);

    try {
        // 1. คำนวณยอดรวมใหม่จาก Array ที่ส่งมา
        $subtotal = 0;
        if (isset($_POST['item_desc'])) {
            foreach ($_POST['item_desc'] as $key => $desc) {
                $qty = floatval($_POST['item_qty'][$key]);
                $price = floatval($_POST['item_price'][$key]);
                $subtotal += ($qty * $price);
            }
        }
        $vat = $subtotal * 0.07;
        $grand_total = $subtotal + $vat;

        // 2. อัปเดตข้อมูลหลักในตาราง quotations
        $sql_update = "UPDATE quotations SET 
                        supplier_id = '$supplier_id',
                        doc_date = '$doc_date',
                        valid_until = '$valid_until',
                        payment_term = '$payment_term',
                        subtotal = '$subtotal',
                        vat = '$vat',
                        grand_total = '$grand_total',
                        notes = '$notes'
                       WHERE id = '$quotation_id' AND deleted_at IS NULL";
        
        if (!mysqli_query($conn, $sql_update)) {
            throw new Exception("ไม่สามารถอัปเดตข้อมูลใบเสนอราคาได้");
        }

        // 3. ลบรายการสินค้าเดิมทิ้งทั้งหมด เพื่อเตรียมลงใหม่
        mysqli_query($conn, "DELETE FROM quotation_items WHERE quotation_id = '$quotation_id'");

        // 4. เพิ่มรายการสินค้าใหม่เข้าไป
        if (isset($_POST['item_desc'])) {
            foreach ($_POST['item_desc'] as $key => $desc) {
                if (empty($desc)) continue; // ข้ามแถวที่ว่าง

                $item_desc  = mysqli_real_escape_string($conn, $desc);
                $item_qty   = floatval($_POST['item_qty'][$key]);
                $item_price = floatval($_POST['item_price'][$key]);
                $item_total = $item_qty * $item_price;

                $sql_item = "INSERT INTO quotation_items (quotation_id, item_desc, item_qty, item_price, item_total) 
                             VALUES ('$quotation_id', '$item_desc', '$item_qty', '$item_price', '$item_total')";
                
                if (!mysqli_query($conn, $sql_item)) {
                    throw new Exception("ไม่สามารถบันทึกรายการสินค้าได้ที่แถว " . ($key + 1));
                }
            }
        }

        // ถ้าทุกอย่างถูกต้อง ให้ยืนยันการบันทึก
        mysqli_commit($conn);
        $_SESSION['flash_msg'] = 'update_success';
        header("Location: ../doc_list.php");

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาด ให้ยกเลิกสิ่งที่ทำมาทั้งหมด (Rollback)
        mysqli_rollback($conn);
        $_SESSION['flash_msg'] = 'update_failed';
        header("Location: ../doc_list.php");
    }
}