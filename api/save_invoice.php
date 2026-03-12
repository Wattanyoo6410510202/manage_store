<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ดึง ID ผู้ใช้งาน (ถ้ามีระบบ Login)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่าพื้นฐานจากฟอร์ม
    $supplier_id  = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $customer_id  = mysqli_real_escape_string($conn, $_POST['customer_id']); // ในตารางจารไม่มีคอลัมน์นี้ แต่จำเป็นต้องใช้ หรือจะใช้ project_id แทนก็ได้
    $project_id   = (int) ($_POST['project_id'] ?? 0);
    $invoice_date = mysqli_real_escape_string($conn, $_POST['doc_date']); // วันที่ออกบิล
    $due_date     = mysqli_real_escape_string($conn, $_POST['due_date'] ?? ''); 
    $remark       = mysqli_real_escape_string($conn, $_POST['notes']);

    // 2. รับค่า VAT และ WHT %
    $vat_percent = floatval($_POST['vat_percent'] ?? 7);
    $wht_percent = floatval($_POST['wht_percent'] ?? 0);

    // 3. คำนวณ Subtotal (ยอดรวมก่อนภาษี)
    $subtotal = 0;
    if (isset($_POST['item_qty'])) {
        foreach ($_POST['item_qty'] as $key => $qty) {
            $price      = floatval($_POST['item_price'][$key]);
            $discount   = floatval($_POST['item_discount'][$key] ?? 0); // ถ้าจารมีส่วนลดรายบรรทัด
            $line_total = (floatval($qty) * $price) - $discount;
            $subtotal  += $line_total;
        }
    }

    // 4. คำนวณยอดทางบัญชี
    $vat_amount   = $subtotal * ($vat_percent / 100);
    $wht_amount   = $subtotal * ($wht_percent / 100);
    // ยอดสุทธิ = (ยอดรวม + VAT) - หัก ณ ที่จ่าย
    $total_amount = ($subtotal + $vat_amount) - $wht_amount;

    mysqli_begin_transaction($conn);

    try {
        // 5. บันทึกหัวบิล (Table: invoices) 
        // ใช้ชื่อ Column ตาม Schema: id, invoice_no, project_id, supplier_id, invoice_date, due_date, sub_total, vat_percent, vat_amount, wht_percent, wht_amount, total_amount, status
        $sql_invoice = "INSERT INTO invoices (
                            invoice_no, project_id, supplier_id, invoice_date, due_date, 
                            sub_total, vat_percent, vat_amount, wht_percent, wht_amount, 
                            total_amount, status
                        ) VALUES (
                            '', '$project_id', '$supplier_id', '$invoice_date', '$due_date', 
                            '$subtotal', '$vat_percent', '$vat_amount', '$wht_percent', '$wht_amount', 
                            '$total_amount', 'pending'
                        )";

        if (!mysqli_query($conn, $sql_invoice)) throw new Exception(mysqli_error($conn));

        $invoice_id = mysqli_insert_id($conn);

        // 6. Gen เลขที่ใบแจ้งหนี้อัตโนมัติ (เช่น INV26030001)
        date_default_timezone_set('Asia/Bangkok');
        $date_prefix = (date('y') + 43) . date('m'); // พ.ศ. ย่อ + เดือน
        $new_inv_no  = "INV" . $date_prefix . str_pad($invoice_id, 4, '0', STR_PAD_LEFT);

        $update_no = "UPDATE invoices SET invoice_no = '$new_inv_no' WHERE id = $invoice_id";
        if (!mysqli_query($conn, $update_no)) throw new Exception(mysqli_error($conn));

        // 7. บันทึกรายการสินค้า (Table: invoice_items)
        if (isset($_POST['item_desc'])) {
            foreach ($_POST['item_desc'] as $key => $desc) {
                if (trim($desc) == "") continue;

                $item_desc  = mysqli_real_escape_string($conn, $desc);
                $item_qty   = floatval($_POST['item_qty'][$key]);
                $item_price = floatval($_POST['item_price'][$key]);
                $item_amount = $item_qty * $item_price; // ยอดบรรทัดนี้

                $sql_item = "INSERT INTO invoice_items (
                                invoice_id, description, quantity, unit_price, amount
                             ) VALUES (
                                '$invoice_id', '$item_desc', '$item_qty', '$item_price', '$item_amount'
                             )";
                
                if (!mysqli_query($conn, $sql_item)) throw new Exception(mysqli_error($conn));
            }
        }

        mysqli_commit($conn);
        $_SESSION['flash_msg'] = 'add_success';
        header("Location: ../invoice_list.php"); // ไปหน้าเขียว
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['flash_msg'] = 'error';
        // echo "Error: " . $e->getMessage(); // เปิดไว้ดูตอน Debug
        header("Location: ../invoice_list.php");
        exit();
    }
}
?>