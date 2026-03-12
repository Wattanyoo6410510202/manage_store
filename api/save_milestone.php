<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าจาก Form และจัดการความปลอดภัย
    $project_id = intval($_POST['project_id']);
    $milestone_name = mysqli_real_escape_string($conn, $_POST['milestone_name']);
    $claim_date = !empty($_POST['claim_date']) ? mysqli_real_escape_string($conn, $_POST['claim_date']) : date('Y-m-d');
    $status = isset($_POST['status']) ? mysqli_real_escape_string($conn, $_POST['status']) : 'pending';
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    $amount = floatval($_POST['amount']); // ยอดเงินต้น
    $vat_amount = floatval($_POST['vat_amount']);
    $wht_amount = floatval($_POST['wht_amount']);
    $total_request_amount = floatval($_POST['total_request_amount']);
    $net_amount = isset($_POST['net_amount']) ? floatval($_POST['net_amount']) : $total_request_amount;
    $remaining_balance = floatval($_POST['remaining_balance']);
    // ... (หลังบรรทัด $wht_amount)
    $retention_percent = floatval($_POST['retention_percent']);
    $retention_amount = floatval($_POST['retention_amount']);

    // เพิ่มส่วนหักอื่นๆ ตามโครงสร้างตารางใหม่
    $other_deduction_amount = floatval($_POST['other_deduction_amount'] ?? 0);
    $deduction_note = mysqli_real_escape_string($conn, $_POST['deduction_note']);

    // ยอดจ่ายสุทธิ (net_amount) คือยอดที่หักทุกอย่างแล้ว
    $net_amount = floatval($_POST['total_request_amount']);

    $remaining_balance = floatval($_POST['remaining_balance']);


    // 3. จัดการไฟล์แนบ (ถ้ามี)
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

    $sql = "INSERT INTO project_milestones (
            project_id, 
            milestone_name, 
            amount, 
            retention_percent, 
            retention_amount, 
            net_amount, 
            claim_date, 
            status, 
            claim_attachment, 
            remarks, 
            deduction_note, 
            other_deduction_amount, 
            vat_percent, 
            vat_amount, 
            wht_percent, 
            wht_amount, 
            total_request_amount, 
            remaining_balance, 
            created_at
        ) VALUES (
            '$project_id', 
            '$milestone_name', 
            '$amount', 
            '$retention_percent', 
            '$retention_amount', 
            '$net_amount', 
            '$claim_date', 
            '$status', 
            '$attachment_name', 
            '$remarks', 
            '$deduction_note', 
            '$other_deduction_amount', 
            '7.00', 
            '$vat_amount', 
            '3.00', 
            '$wht_amount', 
            '$net_amount', -- ตรงกับยอดสุทธิที่คำนวณจากหน้าบ้าน
            '$remaining_balance', 
            NOW()
        )";

    if (mysqli_query($conn, $sql)) {
        // บันทึกสำเร็จ ส่งกลับไปหน้าเดิม (ถ้าเรียกผ่าน AJAX ฟังก์ชัน JS จะตรวจเช็ค Location เอง)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo "success"; // ตอบกลับสำหรับ AJAX
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