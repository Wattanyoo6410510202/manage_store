<?php
session_start();
require_once '../config.php';
date_default_timezone_set('Asia/Bangkok');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าและทำความสะอาดข้อมูลพื้นฐาน
    $po_id            = mysqli_real_escape_string($conn, $_POST['po_id']);
    $supplier_id      = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $customer_id      = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $reference_no     = mysqli_real_escape_string($conn, $_POST['reference_no']);
    $express_ref_code = mysqli_real_escape_string($conn, $_POST['express_ref_code'] ?? '');
    $payment_term     = mysqli_real_escape_string($conn, $_POST['payment_term']);
    $notes            = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // รับค่า VAT และ WHT Percent จากฟอร์ม
    $vat_percent  = isset($_POST['vat_percent']) ? floatval($_POST['vat_percent']) : 0;
    $wht_percent  = isset($_POST['wht_percent']) ? floatval($_POST['wht_percent']) : 0;

    $item_descs     = $_POST['item_desc'] ?? [];
    $item_qtys      = $_POST['item_qty'] ?? [];
    $item_units     = $_POST['item_unit'] ?? [];
    $item_prices    = $_POST['item_price'] ?? [];
    $item_discounts = $_POST['item_discount'] ?? [];

    // --- จัดการไฟล์แนบ ---
    $upload_dir = "../uploads/po/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    // ดึงข้อมูลเดิมมาเช็คเรื่องไฟล์
    $sql_old = "SELECT attachment_1, attachment_2 FROM po WHERE id = '$po_id'";
    $res_old = mysqli_query($conn, $sql_old);
    $old_data = mysqli_fetch_assoc($res_old);

    $attachment_1 = $old_data['attachment_1'];
    $attachment_2 = $old_data['attachment_2'];

    // จัดการลบไฟล์เดิม (ถ้ามีการติ๊กสั่งลบ)
    if (($_POST['delete_attachment_1'] ?? '0') === '1') {
        if ($attachment_1 && file_exists($upload_dir . $attachment_1)) unlink($upload_dir . $attachment_1);
        $attachment_1 = null;
    }
    if (($_POST['delete_attachment_2'] ?? '0') === '1') {
        if ($attachment_2 && file_exists($upload_dir . $attachment_2)) unlink($upload_dir . $attachment_2);
        $attachment_2 = null;
    }

    // อัปโหลดไฟล์ใหม่ (ถ้ามี)
    if (!empty($_FILES['attachment_1']['name'])) {
        if ($attachment_1 && file_exists($upload_dir . $attachment_1)) unlink($upload_dir . $attachment_1);
        $ext = pathinfo($_FILES['attachment_1']['name'], PATHINFO_EXTENSION);
        $attachment_1 = "po_att1_" . time() . "_" . rand(1000, 9999) . "." . $ext;
        move_uploaded_file($_FILES['attachment_1']['tmp_name'], $upload_dir . $attachment_1);
    }
    if (!empty($_FILES['attachment_2']['name'])) {
        if ($attachment_2 && file_exists($upload_dir . $attachment_2)) unlink($upload_dir . $attachment_2);
        $ext = pathinfo($_FILES['attachment_2']['name'], PATHINFO_EXTENSION);
        $attachment_2 = "po_att2_" . time() . "_" . rand(1000, 9999) . "." . $ext;
        move_uploaded_file($_FILES['attachment_2']['tmp_name'], $upload_dir . $attachment_2);
    }

    mysqli_begin_transaction($conn);

    try {
        // 2. คำนวณยอดรวมใหม่ทั้งหมด
        $calc_subtotal = 0;
        foreach ($item_qtys as $index => $qty) {
            if (empty(trim($item_descs[$index]))) continue;
            $price    = floatval($item_prices[$index] ?? 0);
            $discount = floatval($item_discounts[$index] ?? 0);
            $qty_val  = floatval($qty);
            $calc_subtotal += (($qty_val * $price) - $discount);
        }
        
        $vat_amount  = $calc_subtotal * ($vat_percent / 100);
        $wht_amount  = $calc_subtotal * ($wht_percent / 100);
        $grand_total = ($calc_subtotal + $vat_amount) - $wht_amount;

        // 3. UPDATE ตาราง po
        $sql_update_po = "UPDATE po SET 
                            supplier_id      = '$supplier_id',
                            customer_id      = '$customer_id',
                            reference_no     = '$reference_no',
                            express_ref_code = '$express_ref_code',
                            payment_term     = '$payment_term',
                            notes            = '$notes',
                            subtotal         = '$calc_subtotal',
                            vat_percent      = '$vat_percent', 
                            vat_amount       = '$vat_amount',
                            wht_percent      = '$wht_percent', 
                            wht_amount       = '$wht_amount',
                            grand_total      = '$grand_total',
                            attachment_1     = " . ($attachment_1 ? "'$attachment_1'" : "NULL") . ",
                            attachment_2     = " . ($attachment_2 ? "'$attachment_2'" : "NULL") . ",
                            updated_at       = NOW() 
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