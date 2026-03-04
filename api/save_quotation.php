<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $doc_date = mysqli_real_escape_string($conn, $_POST['doc_date']);
    $valid_until = (int) $_POST['valid_until'];
    $payment_term = mysqli_real_escape_string($conn, $_POST['payment_term']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    // รับค่า VAT % จากฟอร์ม (ที่เราเพิ่มเข้าไปใหม่)
    $vat_percent = floatval($_POST['vat_percent'] ?? 7);

    $subtotal = 0;
    // คำนวณยอดรวมใหม่ โดยหักส่วนลดรายบรรทัดออกก่อน
    if (isset($_POST['item_qty'])) {
        foreach ($_POST['item_qty'] as $key => $qty) {
            $price = floatval($_POST['item_price'][$key]);
            $discount = floatval($_POST['item_discount'][$key] ?? 0); // รับค่าส่วนลดรายบรรทัด
            $line_total = (floatval($qty) * $price) - $discount;
            $subtotal += $line_total;
        }
    }

    // คำนวณภาษีจากยอดที่หักส่วนลดแล้ว (Tax Base)
    $vat_amount = $subtotal * ($vat_percent / 100);
    $grand_total = $subtotal + $vat_amount;

    mysqli_begin_transaction($conn);

    try {
        // 1. บันทึกข้อมูลหลัก (เพิ่ม vat_percent ลงไปด้วย)
        $sql_quotation = "INSERT INTO quotations (
                            doc_no, doc_date, supplier_id, customer_id, 
                            valid_until, payment_term, notes, subtotal, 
                            vat_percent, vat, grand_total, created_by
                          ) VALUES (
                            '', '$doc_date', '$supplier_id', '$customer_id', 
                            '$valid_until', '$payment_term', '$notes', '$subtotal', 
                            '$vat_percent', '$vat_amount', '$grand_total', '$user_id'
                          )";

        if (!mysqli_query($conn, $sql_quotation))
            throw new Exception(mysqli_error($conn));

        $quotation_id = mysqli_insert_id($conn);
        // 1. ดึงวันที่ปัจจุบันมาทำ Prefix (YYMMDD)
// ณ วั// 1. ตั้งค่าโซนเวลาเป็นประเทศไทย (สำคัญมาก!)
        date_default_timezone_set('Asia/Bangkok');

        // 2. ดึงวันที่ปัจจุบัน (ตอนนี้จะเป็นวันที่ 05 แน่นอนครับจาร)
        $date_prefix = date('ymd');

        // 3. สร้างเลขที่เอกสาร (Prefix + YYMMDD + ID 6 หลัก)
// จารใส่ 6 หลักตามโค้ดล่าสุด ผลลัพธ์จะเป็น QTN-260305000084
        $new_doc_no = "QTN-" . $date_prefix . str_pad($quotation_id, 6, '0', STR_PAD_LEFT);

        // 3. อัปเดตกลับลงฐานข้อมูล
        $update_doc_no = "UPDATE quotations SET doc_no = '$new_doc_no' WHERE id = $quotation_id";

        $update_doc_no = "UPDATE quotations SET doc_no = '$new_doc_no' WHERE id = $quotation_id";
        if (!mysqli_query($conn, $update_doc_no))
            throw new Exception(mysqli_error($conn));

        // 2. บันทึกรายการสินค้า (เพิ่ม item_discount ลงไปด้วย)
        if (isset($_POST['item_desc'])) {
            foreach ($_POST['item_desc'] as $key => $desc) {
                if (trim($desc) == "")
                    continue;

                $item_desc = mysqli_real_escape_string($conn, $desc);
                $item_qty = floatval($_POST['item_qty'][$key]);
                $item_price = floatval($_POST['item_price'][$key]);
                $item_discount = floatval($_POST['item_discount'][$key] ?? 0); // รับค่าส่วนลด

                // ยอดรวมต่อบรรทัด (หักส่วนลดแล้ว)
                $item_total = ($item_qty * $item_price) - $item_discount;

                $sql_item = "INSERT INTO quotation_items (
                                quotation_id, item_desc, item_qty, item_price, item_discount, item_total
                             ) VALUES (
                                '$quotation_id', '$item_desc', '$item_qty', '$item_price', '$item_discount', '$item_total'
                             )";

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
        header("Location: ../doc_list.php");
        exit();
    }
}
?>