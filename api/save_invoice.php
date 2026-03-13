<?php
require_once '../config.php';

// 1. ตั้งค่าโซนเวลาให้เป็นประเทศไทย (เพื่อให้เลขวันที่ในเลขที่เอกสารตรงกับเวลาจริง)
date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) session_start();

// ดึง ID ผู้ใช้งาน
$user_id = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // เริ่มต้น Transaction เพื่อความปลอดภัยของข้อมูล
    mysqli_begin_transaction($conn);

    try {
        // --- 2. รับค่าพื้นฐานจากฟอร์ม ---
        $customer_id  = (int)$_POST['customer_id'];
        $supplier_id  = (int)$_POST['supplier_id'];
        $invoice_date = mysqli_real_escape_string($conn, $_POST['invoice_date']);
        $due_date     = !empty($_POST['due_date']) ? mysqli_real_escape_string($conn, $_POST['due_date']) : null;
        $status       = mysqli_real_escape_string($conn, $_POST['status'] ?? 'pending');
        $remark       = mysqli_real_escape_string($conn, $_POST['remark']);

        // รับค่า % ภาษี และ % หัก ณ ที่จ่าย
        $vat_percent  = floatval($_POST['vat_percent'] ?? 0);
        $wht_percent  = floatval($_POST['wht_percent'] ?? 0);

        // --- 3. คำนวณยอดรวมใหม่ (Double Check เพื่อความแม่นยำ) ---
        $item_names      = $_POST['item_name'] ?? [];
        $item_qtys       = $_POST['qty'] ?? [];
        $item_prices     = $_POST['price_per_unit'] ?? [];
        $item_discounts  = $_POST['discount_amount'] ?? [];
        $unit_names      = $_POST['unit_name'] ?? [];

        $sub_total = 0;
        foreach ($item_names as $key => $name) {
            if (trim($name) === '') continue;
            $qty  = floatval($item_qtys[$key] ?? 0);
            $prc  = floatval($item_prices[$key] ?? 0);
            $disc = floatval($item_discounts[$key] ?? 0);
            
            // ยอดแต่ละแถว = (จำนวน * ราคา) - ส่วนลด
            $sub_total += ($qty * $prc) - $disc;
        }

        // คำนวณภาษีและยอดสุทธิ
        $vat_amount   = $sub_total * ($vat_percent / 100);
        $wht_amount   = $sub_total * ($wht_percent / 100);
        $total_amount = ($sub_total + $vat_amount) - $wht_amount;

        // --- 4. บันทึกหัวบิล (แก้ปัญหา Duplicate Key โดยการใส่ TEMP ล่วงหน้า) ---
        // ใช้ microtime เพื่อให้ได้เลขที่ไม่ซ้ำกันแน่นอนในจังหวะ Insert ครั้งแรก
        $temp_no = 'TEMP-' . microtime(true);

        $sql_invoice = "INSERT INTO invoices (
                            invoice_no, customer_id, supplier_id, invoice_date, due_date, 
                            sub_total, vat_percent, vat_amount, wht_percent, wht_amount, 
                            total_amount, status, remark, created_by, created_at
                        ) VALUES (
                            '$temp_no', '$customer_id', '$supplier_id', '$invoice_date', " . ($due_date ? "'$due_date'" : "NULL") . ", 
                            '$sub_total', '$vat_percent', '$vat_amount', '$wht_percent', '$wht_amount', 
                            '$total_amount', '$status', '$remark', '$user_id', NOW()
                        )";

        if (!mysqli_query($conn, $sql_invoice)) {
            throw new Exception("บันทึกหัว Invoice พัง: " . mysqli_error($conn));
        }

        // ดึง ID ที่เพิ่ง Insert เข้าไป
        $invoice_id = mysqli_insert_id($conn);

        // --- 5. เจนเลขที่เอกสารจริง (รูปแบบเดียวกับ PO) ---
        // ปี พ.ศ. 2 หลัก (2026 + 43 = 69) และ เดือน 2 หลัก
        $date_prefix = (date('y') + 43) . date('m'); 
        $invoice_no  = "INV-" . $date_prefix . str_pad($invoice_id, 4, '0', STR_PAD_LEFT);

        // UPDATE เลขที่เอกสารจริงกลับเข้าไปแทนที่ค่า TEMP
        $sql_update_no = "UPDATE invoices SET invoice_no = '$invoice_no' WHERE id = $invoice_id";
        if (!mysqli_query($conn, $sql_update_no)) {
            throw new Exception("Update เลขที่เอกสารจริงพัง: " . mysqli_error($conn));
        }

        // --- 6. บันทึกรายการสินค้า (Table: invoice_items) ---
        foreach ($item_names as $key => $name) {
            if (trim($name) === '') continue;

            $item_name      = mysqli_real_escape_string($conn, $name);
            $qty            = floatval($item_qtys[$key] ?? 0);
            $unit_name      = mysqli_real_escape_string($conn, $unit_names[$key] ?? '');
            $price_per_unit = floatval($item_prices[$key] ?? 0);
            $discount_item  = floatval($item_discounts[$key] ?? 0);
            
            // คำนวณราคารวมต่อบรรทัดเพื่อเก็บลง DB
            $total_price    = ($qty * $price_per_unit) - $discount_item;
            $sort_order     = $key + 1;

            $sql_item = "INSERT INTO invoice_items (
                            invoice_id, item_name, qty, unit_name, 
                            price_per_unit, discount_amount, total_price, sort_order
                         ) VALUES (
                            '$invoice_id', '$item_name', '$qty', '$unit_name', 
                            '$price_per_unit', '$discount_item', '$total_price', '$sort_order'
                         )";
            
            if (!mysqli_query($conn, $sql_item)) {
                throw new Exception("บันทึกรายการสินค้าแถวที่ $sort_order พัง: " . mysqli_error($conn));
            }
        }

        // ถ้ามาถึงตรงนี้แสดงว่าทุกอย่างปกติ ยืนยันการบันทึก
        mysqli_commit($conn);
        
        $_SESSION['flash_msg'] = 'success';
        header("Location: ../invoice_list.php?msg=saved&no=$invoice_no");
        exit();

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาดให้ยกเลิกสิ่งที่ทำมาทั้งหมดใน Transaction นี้
        mysqli_rollback($conn);
        
        // แจ้งเตือนข้อผิดพลาดและส่งกลับหน้าเดิม
        echo "<script>alert('เกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }
}
?>