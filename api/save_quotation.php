<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $doc_date = mysqli_real_escape_string($conn, $_POST['doc_date']);
    $valid_until = (int) $_POST['valid_until'];
    $payment_term = mysqli_real_escape_string($conn, $_POST['payment_term']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    // รับค่า VAT และ WHT % จากฟอร์ม
    $vat_percent = floatval($_POST['vat_percent'] ?? 7);
    $wht_percent = floatval($_POST['wht_percent'] ?? 0);

    $subtotal = 0;
    if (isset($_POST['item_qty'])) {
        foreach ($_POST['item_qty'] as $key => $qty) {
            $price = floatval($_POST['item_price'][$key]);
            $discount = floatval($_POST['item_discount'][$key] ?? 0);
            $line_total = (floatval($qty) * $price) - $discount;
            $subtotal += $line_total;
        }
    }

    // คำนวณตามมาตรฐาน
    $vat_amount = $subtotal * ($vat_percent / 100);
    $grand_total = $subtotal + $vat_amount; // ยอดรวมก่อนหักภาษี ณ ที่จ่าย
    $wht_amount = $subtotal * ($wht_percent / 100);
    $total_after_wht = $grand_total - $wht_amount; // ยอดสุทธิหลังหัก WHT

    mysqli_begin_transaction($conn);

    try {
        // บันทึกโดยใช้ชื่อ Column ตาม Schema ของจารเป๊ะๆ
        $sql_quotation = "INSERT INTO quotations (
                            doc_no, doc_date, supplier_id, customer_id, 
                            valid_until, payment_term, notes, subtotal, 
                            vat, grand_total, vat_percent, wht_percent, 
                            wht_amount, total_after_wht, created_by
                          ) VALUES (
                            '', '$doc_date', '$supplier_id', '$customer_id', 
                            '$valid_until', '$payment_term', '$notes', '$subtotal', 
                            '$vat_amount', '$grand_total', '$vat_percent', '$wht_percent', 
                            '$wht_amount', '$total_after_wht', '$user_id'
                          )";

        if (!mysqli_query($conn, $sql_quotation)) throw new Exception(mysqli_error($conn));

        $quotation_id = mysqli_insert_id($conn);
        date_default_timezone_set('Asia/Bangkok');
         $date_prefix = (date('y') + 43) . date('m');
        $new_doc_no = "QTN-" . $date_prefix . str_pad($quotation_id, 4, '0', STR_PAD_LEFT);

        $update_doc_no = "UPDATE quotations SET doc_no = '$new_doc_no' WHERE id = $quotation_id";
        if (!mysqli_query($conn, $update_doc_no)) throw new Exception(mysqli_error($conn));

        // ส่วนของรายการสินค้า (Items)
        if (isset($_POST['item_desc'])) {
            foreach ($_POST['item_desc'] as $key => $desc) {
                if (trim($desc) == "") continue;
                $item_desc = mysqli_real_escape_string($conn, $desc);
                $item_qty = floatval($_POST['item_qty'][$key]);
                $item_price = floatval($_POST['item_price'][$key]);
                $item_discount = floatval($_POST['item_discount'][$key] ?? 0);
                $item_total = ($item_qty * $item_price) - $item_discount;

                $sql_item = "INSERT INTO quotation_items (
                                quotation_id, item_desc, item_qty, item_price, item_discount, item_total
                             ) VALUES (
                                '$quotation_id', '$item_desc', '$item_qty', '$item_price', '$item_discount', '$item_total'
                             )";
                if (!mysqli_query($conn, $sql_item)) throw new Exception(mysqli_error($conn));
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