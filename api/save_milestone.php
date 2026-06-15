<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าและจัดการความปลอดภัย
    $project_id = intval($_POST['project_id']);
    $milestone_name = mysqli_real_escape_string($conn, $_POST['milestone_name']);
    $claim_date = !empty($_POST['claim_date']) ? mysqli_real_escape_string($conn, $_POST['claim_date']) : date('Y-m-d');
    $work_start_date = !empty($_POST['work_start_date']) ? mysqli_real_escape_string($conn, $_POST['work_start_date']) : NULL;
    $status = isset($_POST['status']) ? mysqli_real_escape_string($conn, $_POST['status']) : 'pending';
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    
    // ยอดเงินต่างๆ
    $amount = floatval($_POST['amount']); 
    $vat_amount = floatval($_POST['vat_amount'] ?? 0);
    $wht_amount = floatval($_POST['wht_amount'] ?? 0);
    $retention_percent = floatval($_POST['retention_percent'] ?? 0);
    $retention_amount = floatval($_POST['retention_amount'] ?? 0);
    $other_deduction_amount = floatval($_POST['other_deduction_amount'] ?? 0);
    $deduction_note = mysqli_real_escape_string($conn, $_POST['deduction_note'] ?? '');
    
    // ยอดสุทธิและยอดคงเหลือ
    $total_request_amount = floatval($_POST['total_request_amount']);
    $remaining_balance = floatval($_POST['remaining_balance']);

    // --- ส่วนที่จารต้องการเพิ่ม: บันทึกว่า VAT นอก (1) หรือ VAT ใน (0) ---
    // รับค่าจาก <input type="hidden" name="has_vat" id="vat_type_status">
    $has_vat = isset($_POST['has_vat']) ? intval($_POST['has_vat']) : 1; 

    // เช็ค % เพื่อความถูกต้องใน DB
    $vat_percent = ($vat_amount > 0) ? 7.00 : 0.00;
    $wht_percent = ($wht_amount > 0) ? 3.00 : 0.00;

    // 2. จัดการไฟล์แนบ
    $attachment_name = "";
    if (isset($_FILES['claim_attachment']) && $_FILES['claim_attachment']['error'] == 0) {
        $target_dir = "../uploads/claims/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_ext = pathinfo($_FILES["claim_attachment"]["name"], PATHINFO_EXTENSION);
        $attachment_name = "CLAIM_" . $project_id . "_" . time() . "." . $file_ext;
        move_uploaded_file($_FILES["claim_attachment"]["tmp_name"], $target_dir . $attachment_name);
    }

    // 3. SQL INSERT (เพิ่มคอลัมน์ has_vat, work_start_date)
    $sql = "INSERT INTO project_milestones (
            project_id, 
            milestone_name, 
            amount, 
            retention_percent, 
            retention_amount, 
            net_amount, 
            claim_date, 
            work_start_date, -- เพิ่ม field นี้
            status, 
            claim_attachment, 
            remarks, 
            deduction_note, 
            other_deduction_amount, 
            vat_percent, 
            vat_amount, 
            wht_percent, 
            wht_amount, 
            has_vat,
            total_request_amount, 
            remaining_balance, 
            created_at
        ) VALUES (
            '$project_id', 
            '$milestone_name', 
            '$amount', 
            '$retention_percent', 
            '$retention_amount', 
            '$total_request_amount', 
            '$claim_date', 
            " . ($work_start_date ? "'$work_start_date'" : "NULL") . ", -- บันทึกวันที่เริ่มงาน
            '$status', 
            '$attachment_name', 
            '$remarks', 
            '$deduction_note', 
            '$other_deduction_amount', 
            '$vat_percent', 
            '$vat_amount', 
            '$wht_percent', 
            '$wht_amount', 
            '$has_vat', 
            '$total_request_amount', 
            '$remaining_balance', 
            NOW()
        )";

    if (mysqli_query($conn, $sql)) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo "success";
        } else {
            $_SESSION['flash_msg'] = 'add_success';
            header("Location: ../detail_project.php?id=$project_id");
            exit();
        }
    } else {
        http_response_code(500);
        echo "SQL Error: " . mysqli_error($conn);
    }
}
?>