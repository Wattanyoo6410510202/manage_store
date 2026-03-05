<?php
require_once '../config.php';

// 1. ตั้งค่าโซนเวลาให้เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 2. รับค่าจากฟอร์ม
    $customer_id = (int) ($_POST['customer_id'] ?? 0);
    $supplier_id = (int) ($_POST['supplier_id'] ?? 0);
    $reference_no = mysqli_real_escape_string($conn, $_POST['reference_no'] ?? '');
    $due_date = !empty($_POST['due_date']) ? mysqli_real_escape_string($conn, $_POST['due_date']) : null;
    $payment_term = mysqli_real_escape_string($conn, $_POST['payment_term'] ?? '');
    $requested_by = mysqli_real_escape_string($conn, $_POST['requested_by'] ?? '');
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    $created_by = $_SESSION['user_id'] ?? 0;

    // รับค่า % ภาษี และ % หัก ณ ที่จ่าย
    $vat_percent = floatval($_POST['vat_percent'] ?? 0);
    $wht_percent = floatval($_POST['wht_percent'] ?? 0); // เพิ่มรับค่า WHT

    mysqli_begin_transaction($conn);

    try {
        // 3. คำนวณยอดรวมก่อนบันทึก
        $item_descs = $_POST['item_desc'] ?? [];
        $item_qtys = $_POST['item_qty'] ?? [];
        $item_units = $_POST['item_unit'] ?? [];
        $item_prices = $_POST['item_price'] ?? [];
        $item_discounts = $_POST['item_discount'] ?? [];

        $subtotal = 0;
        foreach ($item_descs as $key => $desc) {
            if (trim($desc) === '') continue;
            $qty = floatval($item_qtys[$key] ?? 0);
            $prc = floatval($item_prices[$key] ?? 0);
            $disc = floatval($item_discounts[$key] ?? 0);
            $subtotal += ($qty * $prc) - $disc;
        }

        // คำนวณ VAT และ WHT
        $vat_amount = $subtotal * ($vat_percent / 100);
        $wht_amount = $subtotal * ($wht_percent / 100); // หัก ณ ที่จ่าย คำนวณจากยอดก่อน VAT
        
        // ยอดสุทธิ = (ยอดสินค้า + VAT) - หัก ณ ที่จ่าย
        $grand_total = ($subtotal + $vat_amount) - $wht_amount;

        // 4. บันทึกหัวเอกสาร (เพิ่มฟิลด์ wht_percent, wht_amount)
        $sql_po = "INSERT INTO po (
            doc_no, customer_id, supplier_id, reference_no, 
            due_date, payment_term, requested_by, 
            notes, subtotal, vat_percent, vat_amount, 
            wht_percent, wht_amount, grand_total, 
            status, created_by, created_at
        ) VALUES (
            '', '$customer_id', '$supplier_id', '$reference_no', 
            " . ($due_date ? "'$due_date'" : "NULL") . ", '$payment_term', '$requested_by', 
            '$notes', '$subtotal', '$vat_percent', '$vat_amount', 
            '$wht_percent', '$wht_amount', '$grand_total', 
            'pending', '$created_by', NOW()
        )";

        if (!mysqli_query($conn, $sql_po)) {
            throw new Exception("Insert หัว PO พัง: " . mysqli_error($conn));
        }

        // 5. ดึง ID ล่าสุดมาเจนเลขที่เอกสาร
        $po_id = mysqli_insert_id($conn);

        // ปี พ.ศ. 2 หลัก (2026 + 43 = 69) และ เดือน 2 หลัก
        $date_prefix = (date('y') + 43) . date('m');

        // รูปแบบ PO-69030001 (ใช้ 4 หลักตาม str_pad ด้านล่าง)
        $doc_no = "PO-" . $date_prefix . str_pad($po_id, 4, '0', STR_PAD_LEFT);

        // 6. UPDATE เลขที่เอกสารกลับเข้าไป
        $sql_update_no = "UPDATE po SET doc_no = '$doc_no' WHERE id = $po_id";
        if (!mysqli_query($conn, $sql_update_no)) {
            throw new Exception("Update เลขที่เอกสารพัง: " . mysqli_error($conn));
        }

        // 7. บันทึกลงตาราง po_items
        foreach ($item_descs as $key => $desc) {
            if (trim($desc) === '') continue;

            $d = mysqli_real_escape_string($conn, $desc);
            $q = floatval($item_qtys[$key] ?? 0);
            $u = mysqli_real_escape_string($conn, $item_units[$key] ?? '');
            $p = floatval($item_prices[$key] ?? 0);
            $disc_item = floatval($item_discounts[$key] ?? 0);
            $total = ($q * $p) - $disc_item;

            $sql_item = "INSERT INTO po_items (
                po_id, item_desc, item_qty, item_unit, item_price, item_discount, total_price
            ) VALUES (
                '$po_id', '$d', '$q', '$u', '$p', '$disc_item', '$total'
            )";

            if (!mysqli_query($conn, $sql_item)) {
                throw new Exception("บันทึกรายการสินค้าพัง: " . mysqli_error($conn));
            }
        }

        mysqli_commit($conn);
        $_SESSION['flash_msg'] = 'add_success';
        header("Location: ../po_list.php");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>alert('เกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
}
?>