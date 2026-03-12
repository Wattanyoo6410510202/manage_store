<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ดึง ID ผู้ใช้งาน (สมมติว่าใช้บันทึกใน created_by)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่าพื้นฐานจากฟอร์ม (ตรวจสอบชื่อตัวแปรให้ตรงกับหน้า Form)
    $customer_id  = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $supplier_id  = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $invoice_no   = mysqli_real_escape_string($conn, $_POST['invoice_no']);
    $invoice_date = mysqli_real_escape_string($conn, $_POST['invoice_date']);
    $due_date     = mysqli_real_escape_string($conn, $_POST['due_date']);
    $status       = mysqli_real_escape_string($conn, $_POST['status'] ?? 'pending');
    $remark       = mysqli_real_escape_string($conn, $_POST['remark']);

    // 2. รับค่าที่คำนวณจากหน้าเครื่อง (Hidden Inputs)
    $sub_total    = floatval($_POST['sub_total'] ?? 0);
    $vat_percent  = floatval($_POST['vat_percent'] ?? 0);
    $vat_amount   = floatval($_POST['vat_amount'] ?? 0);
    $wht_percent  = floatval($_POST['wht_percent'] ?? 0);
    $wht_amount   = floatval($_POST['wht_amount'] ?? 0);
    $total_amount = floatval($_POST['total_amount'] ?? 0);

    // เริ่ม Transaction เพื่อป้องกันข้อมูลบันทึกไม่ครบ
    mysqli_begin_transaction($conn);

    try {
        // 3. บันทึกหัวบิล (Table: invoices)
        // หมายเหตุ: ใช้ชื่อคอลัมน์ตามโครงสร้างที่คุณให้มาตอนแรก
        $sql_invoice = "INSERT INTO invoices (
                            invoice_no, customer_id, supplier_id, invoice_date, due_date, 
                            sub_total, vat_percent, vat_amount, wht_percent, wht_amount, 
                            total_amount, status, remark, created_by, created_at
                        ) VALUES (
                            '$invoice_no', '$customer_id', '$supplier_id', '$invoice_date', '$due_date', 
                            '$sub_total', '$vat_percent', '$vat_amount', '$wht_percent', '$wht_amount', 
                            '$total_amount', '$status', '$remark', '$user_id', NOW()
                        )";

        if (!mysqli_query($conn, $sql_invoice)) {
            throw new Exception("Error Invoice: " . mysqli_error($conn));
        }

        $invoice_id = mysqli_insert_id($conn);

        // 4. บันทึกรายการสินค้า (Table: invoice_items)
        if (isset($_POST['item_name']) && is_array($_POST['item_name'])) {
            foreach ($_POST['item_name'] as $key => $name) {
                // ข้ามรายการที่ชื่อสินค้าว่าง
                if (trim($name) == "") continue;

                $item_name      = mysqli_real_escape_string($conn, $name);
                $qty            = floatval($_POST['qty'][$key]);
                $unit_name      = mysqli_real_escape_string($conn, $_POST['unit_name'][$key] ?? '');
                $price_per_unit = floatval($_POST['price_per_unit'][$key]);
                
                // คำนวณยอดรวมต่อบรรทัด (Total Price)
                $total_price    = $qty * $price_per_unit;
                $sort_order     = $key + 1;

                $sql_item = "INSERT INTO invoice_items (
                                invoice_id, item_name, qty, unit_name, 
                                price_per_unit, total_price, sort_order
                             ) VALUES (
                                '$invoice_id', '$item_name', '$qty', '$unit_name', 
                                '$price_per_unit', '$total_price', '$sort_order'
                             )";
                
                if (!mysqli_query($conn, $sql_item)) {
                    throw new Exception("Error Items: " . mysqli_error($conn));
                }
            }
        }

        // หากทุกอย่างถูกต้อง ให้ยืนยันการบันทึก
        mysqli_commit($conn);
        
        $_SESSION['flash_msg'] = 'success';
        header("Location: ../invoice_list.php?msg=saved");
        exit();

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาด ให้ยกเลิกการบันทึกทั้งหมด (Rollback)
        mysqli_rollback($conn);
        
        // เก็บ Error ไว้ดูเพื่อ Debug
        $_SESSION['error_log'] = $e->getMessage();
        $_SESSION['flash_msg'] = 'error';
        
        header("Location: ../invoice_list.php?msg=failed");
        exit();
    }
}
?>