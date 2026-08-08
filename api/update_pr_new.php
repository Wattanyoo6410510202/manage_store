<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pr_id             = (int)$_POST['pr_id'];
    $supplier_id       = (int)$_POST['supplier_id'];

    // ถ้าไม่เลือกบริษัท ให้ใช้บริษัทของตัวเอง (sup_id) เป็นค่าเริ่มต้น
    $user_sup_id = (int)($_SESSION['sup_id'] ?? 0);
    if ($supplier_id <= 0 && $user_sup_id > 0) {
        $supplier_id = $user_sup_id;
    }

$store_id          = (int)($_POST['store_id'] ?? 0);
if ($store_id === 0) $store_id = null;
    $customer_id       = (int)($_POST['customer_id'] ?? 0);
    $due_date          = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $priority          = $_POST['priority'] ?? 'ปานกลาง';
    $reference_no      = $_POST['reference_no'] ?? '';
    $payment_term      = $_POST['payment_term'] ?? '';
    $payment_method    = $_POST['payment_method'] ?? '';
    $installment_period = !empty($_POST['installment_period']) ? (int)$_POST['installment_period'] : 0;
    $requested_by      = $_POST['requested_by'] ?? '';
    $contact_tel       = $_POST['contact_tel'] ?? '';
    $notes             = $_POST['notes'] ?? '';
    
    $expense_cat_id    = (int)($_POST['expense_cat_id'] ?? 0);
    $budget_type_id    = (int)($_POST['budget_type_id'] ?? 0);
    $objective_id      = (int)($_POST['objective_id'] ?? 0);
    $budget_limit_type = $_POST['budget_limit_type'] ?? '';
    $budget_amount     = floatval($_POST['budget_amount'] ?? 0);
    $budget_details    = $_POST['budget_details'] ?? '';
    $expectation       = $_POST['expectation'] ?? '';
    $practice_method   = $_POST['practice_method'] ?? '';

    $vat_percent  = floatval($_POST['vat_percent'] ?? 7);
    $wht_percent  = floatval($_POST['wht_percent'] ?? 0); 

    $item_descs     = $_POST['item_desc'] ?? [];
    $item_qtys      = $_POST['item_qty'] ?? [];
    $item_units     = $_POST['item_unit'] ?? [];
    $item_prices    = $_POST['item_price'] ?? [];
    $item_discounts = $_POST['item_discount'] ?? [];

    $is_internal = ($customer_id === 0) ? 1 : 0;

    // File Management
    function uploadPRFile($file_key) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/pr/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
            $new_name = 'PR_' . date('Ymd_His') . '_' . uniqid() . '.' . $file_ext;
            if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $upload_dir . $new_name)) return $new_name;
        }
        return null;
    }

    $res_old = mysqli_query($conn, "SELECT attachment_1, attachment_2 FROM pr WHERE id = $pr_id");
    $old_data = mysqli_fetch_assoc($res_old);

    // ลบไฟล์เก่าหากมีการสั่งลบจากหน้าบ้าน
    $attachment_1 = $old_data['attachment_1'];
    if (isset($_POST['delete_file_1']) && $_POST['delete_file_1'] == '1') {
        if ($attachment_1) @unlink('../uploads/pr/' . $attachment_1);
        $attachment_1 = null;
    }
    $new_file1 = uploadPRFile('attachment_1');
    if ($new_file1) {
        if ($old_data['attachment_1']) @unlink('../uploads/pr/' . $old_data['attachment_1']);
        $attachment_1 = $new_file1;
    }

    $attachment_2 = $old_data['attachment_2'];
    if (isset($_POST['delete_file_2']) && $_POST['delete_file_2'] == '1') {
        if ($attachment_2) @unlink('../uploads/pr/' . $attachment_2);
        $attachment_2 = null;
    }
    $new_file2 = uploadPRFile('attachment_2');
    if ($new_file2) {
        if ($old_data['attachment_2']) @unlink('../uploads/pr/' . $old_data['attachment_2']);
        $attachment_2 = $new_file2;
    }

    // Payment slip handling
    function uploadPaymentSlip() {
        if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/payments/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
            $new_name = 'SLIP_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $upload_dir . $new_name)) return $new_name;
        }
        return null;
    }
    $payment_slip = $old_data['payment_slip'] ?? '';
    if (isset($_POST['delete_payment_slip']) && $_POST['delete_payment_slip'] == '1') {
        if ($payment_slip) @unlink('../uploads/payments/' . $payment_slip);
        $payment_slip = '';
    }
    $new_slip = uploadPaymentSlip();
    if ($new_slip) {
        if ($old_data['payment_slip']) @unlink('../uploads/payments/' . $old_data['payment_slip']);
        $payment_slip = $new_slip;
    }

    mysqli_begin_transaction($conn);
    try {
        $new_subtotal = 0;
        foreach ($item_descs as $key => $desc) {
            if (trim($desc) === '') continue;
            $new_subtotal += (floatval($item_qtys[$key]) * floatval($item_prices[$key])) - floatval($item_discounts[$key] ?? 0);
        }
        $new_vat        = $new_subtotal * ($vat_percent / 100);
        $new_wht_amount = $new_subtotal * ($wht_percent / 100);
        $net_grand_total = ($new_subtotal + $new_vat) - $new_wht_amount;

        $sql_main = "UPDATE pr SET 
                        supplier_id = ?, store_id = ?, customer_id = ?, is_internal = ?, due_date = ?, 
                        priority = ?, reference_no = ?, payment_term = ?, payment_method = ?, installment_period = ?, payment_slip = ?,
                        requested_by = ?, contact_tel = ?, 
                        notes = ?, expense_cat_id = ?, budget_type_id = ?, objective_id = ?, 
                        budget_limit_type = ?, budget_amount = ?, budget_details = ?, 
                        expectation = ?, practice_method = ?, subtotal = ?, vat = ?, 
                        vat_percent = ?, wht_percent = ?, wht_amount = ?, grand_total = ?, 
                        attachment_1 = ?, attachment_2 = ?, updated_at = NOW(), allow_resubmit = 3 
                    WHERE id = ?";
        
        $stmt = $conn->prepare($sql_main);
        $stmt->bind_param(
            "iiiisssssisssiiisdssssddddddssi",
            $supplier_id, $store_id, $customer_id, $is_internal, $due_date, $priority, $reference_no, $payment_term,
            $payment_method, $installment_period, $payment_slip,
            $requested_by, $contact_tel, $notes, $expense_cat_id, $budget_type_id, $objective_id,
            $budget_limit_type, $budget_amount, $budget_details, $expectation, $practice_method,
            $new_subtotal, $new_vat, $vat_percent, $wht_percent, $new_wht_amount, $net_grand_total,
            $attachment_1, $attachment_2, $pr_id
        );

        if (!$stmt->execute()) throw new Exception("Error Update Header: " . $stmt->error);

        mysqli_query($conn, "DELETE FROM pr_items WHERE pr_id = $pr_id");
        $stmt_item = $conn->prepare("INSERT INTO pr_items (pr_id, item_desc, item_qty, item_unit, item_price, item_discount, item_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($item_descs as $key => $desc) {
            if (trim($desc) === '') continue;
            $q = floatval($item_qtys[$key]); $u = $item_units[$key]; $p = floatval($item_prices[$key]);
            $disc = floatval($item_discounts[$key] ?? 0); $total = ($q * $p) - $disc;
            $stmt_item->bind_param("isdsddd", $pr_id, $desc, $q, $u, $p, $disc, $total);
            $stmt_item->execute();
        }

        // --- บันทึกกำหนดการผ่อนชำระ ---
        mysqli_query($conn, "DELETE FROM installment_schedule WHERE pr_id = $pr_id");
        if ($installment_period > 0) {
            $dueDates = $_POST['installment_due_date'] ?? [];
            $amounts  = $_POST['installment_amount'] ?? [];
            $statuses = $_POST['installment_status'] ?? [];
            $stmt_ins = $conn->prepare("INSERT INTO installment_schedule (pr_id, installment_no, due_date, amount, status) VALUES (?, ?, ?, ?, ?)");
            for ($i = 0; $i < $installment_period; $i++) {
                $no = $i + 1;
                $dd = $dueDates[$i] ?? date('Y-m-d', strtotime("+30 days"));
                $am = floatval($amounts[$i] ?? 0);
                $st = $statuses[$i] ?? 'pending';
                $stmt_ins->bind_param("iisds", $pr_id, $no, $dd, $am, $st);
                $stmt_ins->execute();
            }
        }

        mysqli_commit($conn);
          $_SESSION['flash_msg'] = 'update_success';
        header("Location: ../request_buy_history.php");
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.history.back();</script>";
    }
}
$conn->close();
?>