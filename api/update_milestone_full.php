<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าพื้นฐาน
    $id = intval($_POST['id']);
    $project_id = intval($_POST['project_id']);
    $milestone_name = mysqli_real_escape_string($conn, $_POST['milestone_name']);
    $amount = floatval($_POST['amount']);
    $vat_amount = floatval($_POST['vat_amount']);
    $wht_amount = floatval($_POST['wht_amount']);
    
    // ใช้ยอดสุทธิที่คำนวณจาก JS ส่งมา (หรือจะคำนวณใหม่ใน PHP ก็ได้เพื่อความชัวร์)
    $net_amount = floatval($_POST['total_request_amount']); 
    $remaining_balance = floatval($_POST['remaining_balance']);
    
    $claim_date = $_POST['claim_date'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    // 2. จัดการเรื่องไฟล์แนบ (Attachment)
    $update_file_query = "";
    if (isset($_FILES['claim_attachment']) && $_FILES['claim_attachment']['error'] == 0) {
        $target_dir = "../uploads/claims/";
        
        // สร้าง Folder ถ้ายังไม่มี
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // ดึงชื่อไฟล์เก่ามาลบทิ้งก่อน (ถ้ามี)
        $old_file_res = mysqli_query($conn, "SELECT claim_attachment FROM project_milestones WHERE id = $id");
        $old_file_row = mysqli_fetch_assoc($old_file_res);
        if (!empty($old_file_row['claim_attachment'])) {
            @unlink($target_dir . $old_file_row['claim_attachment']);
        }

        // ตั้งชื่อไฟล์ใหม่
        $file_ext = pathinfo($_FILES["claim_attachment"]["name"], PATHINFO_EXTENSION);
        $new_file_name = "UPD_CLAIM_" . $id . "_" . time() . "." . $file_ext;
        
        if (move_uploaded_file($_FILES["claim_attachment"]["tmp_name"], $target_dir . $new_file_name)) {
            $update_file_query = ", claim_attachment = '$new_file_name'";
        }
    }

    // 3. ยิง SQL Update (ครอบคลุมทุก Field)
    $sql = "UPDATE project_milestones SET 
                milestone_name = '$milestone_name',
                amount = '$amount',
                vat_amount = '$vat_amount',
                wht_amount = '$wht_amount',
                net_amount = '$net_amount',
                total_request_amount = '$net_amount',
                remaining_balance = '$remaining_balance',
                claim_date = '$claim_date',
                status = '$status',
                remarks = '$remarks'
                $update_file_query
            WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        // อัพเดตสำเร็จ กลับไปที่หน้าหลักโปรเจกต์ พร้อมส่ง Alert
        header("Location: ../detail_project.php?id=$project_id&update=success");
        exit;
    } else {
        // ถ้าพัง ให้แจ้ง Error
        echo "Database Error: " . mysqli_error($conn);
    }
} else {
    echo "Invalid Request Method";
}
?>