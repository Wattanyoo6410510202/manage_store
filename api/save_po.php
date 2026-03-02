<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าจากฟอร์ม
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $reference_no = mysqli_real_escape_string($conn, $_POST['reference_no']);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    $payment_term = mysqli_real_escape_string($conn, $_POST['payment_term']);
    $requested_by = mysqli_real_escape_string($conn, $_POST['requested_by']);
    $contact_tel = mysqli_real_escape_string($conn, $_POST['contact_tel']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $vat_percent = floatval($_POST['vat_type']);

    // 2. สร้างเลขที่เอกสาร PO อัตโนมัติ (รูปแบบ PO-000001)
    $prefix = "PO-";

    // ค้นหาเลขที่ล่าสุดในฐานข้อมูล
    $sql_last = "SELECT doc_no FROM po WHERE doc_no LIKE '$prefix%' ORDER BY id DESC LIMIT 1";
    $res_last = mysqli_query($conn, $sql_last);

    if (mysqli_num_rows($res_last) > 0) {
        $row_last = mysqli_fetch_assoc($res_last);
        // ดึงเฉพาะตัวเลขออกมา (ตัด PO- ออก) แล้วบวกเพิ่ม 1
        $last_number = str_replace($prefix, "", $row_last['doc_no']);
        $next_number = intval($last_number) + 1;
    } else {
        // ถ้ายังไม่มีข้อมูลเลย ให้เริ่มที่ 1
        $next_number = 1;
    }

    // จัดรูปแบบให้เป็น 6 หลัก (000001)
    $doc_no = $prefix . str_pad($next_number, 6, '0', STR_PAD_LEFT);

    // 3. เริ่มต้น Transaction (เพื่อความปลอดภัยของข้อมูล)
    mysqli_begin_transaction($conn);

    try {
        // คำนวณยอดรวมเบื้องต้นจาก Array ที่ส่งมา
        $subtotal = 0;
        foreach ($_POST['item_qty'] as $key => $qty) {
            $price = $_POST['item_price'][$key];
            $subtotal += ($qty * $price);
        }
        $vat_amount = $subtotal * ($vat_percent / 100);
        $grand_total = $subtotal + $vat_amount;

        // 4. บันทึกลงตาราง po (หัวเอกสาร)
        $sql_po = "INSERT INTO po (
            doc_no, customer_id, supplier_id, reference_no, 
            due_date, payment_term, requested_by, contact_tel, 
            notes, subtotal, vat_amount, grand_total, status
        ) VALUES (
            '$doc_no', '$customer_id', '$supplier_id', '$reference_no', 
            '$due_date', '$payment_term', '$requested_by', '$contact_tel', 
            '$notes', '$subtotal', '$vat_amount', '$grand_total', 'pending'
        )";

        if (!mysqli_query($conn, $sql_po)) {
            throw new Exception("ไม่สามารถบันทึกหัวเอกสารได้: " . mysqli_error($conn));
        }

        $po_id = mysqli_insert_id($conn);

        // 5. บันทึกลงตาราง po_items (รายการสินค้า)
        foreach ($_POST['item_desc'] as $key => $desc) {
            $desc = mysqli_real_escape_string($conn, $desc);
            $qty = floatval($_POST['item_qty'][$key]);
            $unit = mysqli_real_escape_string($conn, $_POST['item_unit'][$key]);
            $price = floatval($_POST['item_price'][$key]);
            $total = $qty * $price;

            if (!empty($desc)) {
                $sql_item = "INSERT INTO po_items (
                    po_id, item_desc, item_qty, item_unit, item_price, total_price
                ) VALUES (
                    '$po_id', '$desc', '$qty', '$unit', '$price', '$total'
                )";

                if (!mysqli_query($conn, $sql_item)) {
                    throw new Exception("ไม่สามารถบันทึกรายการสินค้าได้: " . mysqli_error($conn));
                }
            }
        }

        // หากทุกอย่างสำเร็จ
        mysqli_commit($conn);

        $_SESSION['flash_msg'] = 'add_success';
        header("Location: ../po_list.php");

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาด ให้ยกเลิกที่ทำมาทั้งหมด
        mysqli_rollback($conn);
        $_SESSION['flash_msg'] = 'error';
        header("Location: ../po_list.php");
    }
}
?>