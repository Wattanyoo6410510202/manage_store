<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quotation_id = mysqli_real_escape_string($conn, $_POST['quotation_id']);
    $supplier_id  = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $doc_date     = mysqli_real_escape_string($conn, $_POST['doc_date']);
    $valid_until  = mysqli_real_escape_string($conn, $_POST['valid_until']);
    $payment_term = mysqli_real_escape_string($conn, $_POST['payment_term']);
    $notes         = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // --- รับค่าภาษีที่จารสั่งให้จำไว้ ---
    $vat_percent  = floatval($_POST['vat_percent'] ?? 7);
    $wht_percent  = floatval($_POST['wht_percent'] ?? 0); // เพิ่มรับค่าหัก ณ ที่จ่าย

    mysqli_begin_transaction($conn);

    try {
        // 1. คำนวณยอดรวมใหม่ (Subtotal)
        $subtotal = 0;
        if (isset($_POST['item_desc'])) {
            foreach ($_POST['item_desc'] as $key => $desc) {
                if (trim($desc) == "") continue;

                $qty      = floatval($_POST['item_qty'][$key]);
                $price    = floatval($_POST['item_price'][$key]);
                $discount = floatval($_POST['item_discount'][$key] ?? 0);
                
                $line_total = ($qty * $price) - $discount;
                $subtotal += $line_total;
            }
        }

        // --- คำนวณภาษีและหัก ณ ที่จ่าย ---
        $vat_amount = $subtotal * ($vat_percent / 100);
        $wht_amount = $subtotal * ($wht_percent / 100); // หัก ณ ที่จ่ายคิดจากยอดก่อน VAT
        
        // ยอดสุทธิ = (ยอดก่อน VAT + VAT) - หัก ณ ที่จ่าย
        $grand_total = ($subtotal + $vat_amount) - $wht_amount;

        // 2. อัปเดตข้อมูลหลัก (เพิ่มฟิลด์ WHT เข้าไป)
        $sql_update = "UPDATE quotations SET 
                        supplier_id = '$supplier_id',
                        doc_date = '$doc_date',
                        valid_until = '$valid_until',
                        payment_term = '$payment_term',
                        subtotal = '$subtotal',
                        vat_percent = '$vat_percent',
                        vat = '$vat_amount',
                        wht_percent = '$wht_percent',
                        wht_amount = '$wht_amount',
                        grand_total = '$grand_total',
                        notes = '$notes',
                        updated_at = NOW()
                       WHERE id = '$quotation_id' AND deleted_at IS NULL";
        
        if (!mysqli_query($conn, $sql_update)) {
            throw new Exception("ไม่สามารถอัปเดตข้อมูลหลักได้: " . mysqli_error($conn));
        }

        // 3. จัดการรายการสินค้า (ลบของเดิมทิ้ง)
        $sql_delete_items = "DELETE FROM quotation_items WHERE quotation_id = '$quotation_id'";
        mysqli_query($conn, $sql_delete_items);

        // 4. เพิ่มรายการสินค้าใหม่
        if (isset($_POST['item_desc'])) {
            foreach ($_POST['item_desc'] as $key => $desc) {
                $item_desc = trim($desc);
                if (empty($item_desc)) continue;

                $item_desc     = mysqli_real_escape_string($conn, $item_desc);
                $item_qty      = floatval($_POST['item_qty'][$key]);
                $item_price    = floatval($_POST['item_price'][$key]);
                $item_discount = floatval($_POST['item_discount'][$key] ?? 0);
                $item_total    = ($item_qty * $item_price) - $item_discount;

                $sql_item = "INSERT INTO quotation_items (
                                quotation_id, item_desc, item_qty, item_price, item_discount, item_total
                             ) VALUES (
                                '$quotation_id', '$item_desc', '$item_qty', '$item_price', '$item_discount', '$item_total'
                             )";
                
                if (!mysqli_query($conn, $sql_item)) {
                    throw new Exception("บันทึกรายการสินค้าล้มเหลว");
                }
            }
        }

        mysqli_commit($conn);
        $_SESSION['flash_msg'] = 'update_success';
        header("Location: ../doc_list.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['flash_msg'] = 'update_failed';
        header("Location: ../doc_list.php");
        exit();
    }
}