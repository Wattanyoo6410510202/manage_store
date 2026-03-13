<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = mysqli_real_escape_string($conn, $_POST['invoice_id']);

    // 1. รับข้อมูลหลักของ Invoice
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $invoice_date = mysqli_real_escape_string($conn, $_POST['invoice_date']);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $remark = mysqli_real_escape_string($conn, $_POST['remark']);

    // รับค่าตัวเลข (ยอดรวม)
    $sub_total = mysqli_real_escape_string($conn, $_POST['sub_total']);
    $vat_percent = mysqli_real_escape_string($conn, $_POST['vat_percent']);
    $vat_amount = mysqli_real_escape_string($conn, $_POST['vat_amount']);
    $wht_percent = mysqli_real_escape_string($conn, $_POST['wht_percent']);
    $wht_amount = mysqli_real_escape_string($conn, $_POST['wht_amount']);
    $total_amount = mysqli_real_escape_string($conn, $_POST['total_amount']);

    // เริ่ม Transaction เพื่อความปลอดภัย
    mysqli_begin_transaction($conn);

    try {
        // 2. Update ข้อมูลหลักในตาราง invoices
        $sql_update = "UPDATE invoices SET 
                        supplier_id = '$supplier_id',
                        invoice_date = '$invoice_date',
                        due_date = '$due_date',
                        status = '$status',
                        remark = '$remark',
                        sub_total = '$sub_total',
                        vat_percent = '$vat_percent',
                        vat_amount = '$vat_amount',
                        wht_percent = '$wht_percent',
                        wht_amount = '$wht_amount',
                        total_amount = '$total_amount',
                        updated_at = NOW()
                      WHERE id = '$invoice_id'";

        if (!mysqli_query($conn, $sql_update)) {
            throw new Exception("Update Invoice Failed: " . mysqli_error($conn));
        }

        // 3. จัดการรายการสินค้า (Items) 
        // วิธีที่ง่ายที่สุดคือ ลบของเก่าออกให้หมด แล้ว Insert ใหม่ตามที่ส่งมาจากฟอร์ม
        mysqli_query($conn, "DELETE FROM invoice_items WHERE invoice_id = '$invoice_id'");

        $item_names = $_POST['item_name'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $unit_names = $_POST['unit_name'] ?? [];
        $prices = $_POST['price_per_unit'] ?? [];
        $discounts = $_POST['discount_amount'] ?? [];

        foreach ($item_names as $key => $name) {
            if (empty($name))
                continue; // ข้ามแถวที่ไม่มีชื่อรายการ

            $qty = mysqli_real_escape_string($conn, $qtys[$key]);
            $unit = mysqli_real_escape_string($conn, $unit_names[$key]);
            $price = mysqli_real_escape_string($conn, $prices[$key]);
            $discount = mysqli_real_escape_string($conn, $discounts[$key]);
            $clean_name = mysqli_real_escape_string($conn, $name);

            $sql_item = "INSERT INTO invoice_items (invoice_id, item_name, qty, unit_name, price_per_unit, discount_amount) 
                         VALUES ('$invoice_id', '$clean_name', '$qty', '$unit', '$price', '$discount')";

            if (!mysqli_query($conn, $sql_item)) {
                throw new Exception("Insert Item Failed: " . mysqli_error($conn));
            }
        }
        mysqli_commit($conn);
        $_SESSION['flash_msg'] = 'update_success';
        header("Location: ../invoice_list.php");
        exit();

    } catch (Exception $e) {
        // ถ้าผิดพลาด ให้ยกเลิกการทำงานทั้งหมด (Rollback)
        mysqli_rollback($conn);
        $_SESSION['flash_msg'] = 'update_failed';
        header("Location: ../invoice_list.php");
        exit();
    }
}
?>