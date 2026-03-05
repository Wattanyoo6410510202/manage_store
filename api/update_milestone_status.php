<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $update_file_sql = "";

    // จัดการไฟล์แนบ (ถ้ามีการส่งมา)
    if (isset($_FILES['claim_attachment']) && $_FILES['claim_attachment']['error'] == 0) {
        $target_dir = "../uploads/claims/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $file_ext = pathinfo($_FILES["claim_attachment"]["name"], PATHINFO_EXTENSION);
        $file_name = "PAYMENT_" . $id . "_" . time() . "." . $file_ext;
        
        if (move_uploaded_file($_FILES["claim_attachment"]["tmp_name"], $target_dir . $file_name)) {
            // เตรียม SQL สำหรับอัปเดตชื่อไฟล์ด้วย
            $update_file_sql = ", claim_attachment = '$file_name'";
        }
    }

    // อัปเดตทั้งสถานะ และไฟล์ (ถ้ามี)
    $sql = "UPDATE project_milestones SET 
            status = '$status' 
            $update_file_sql 
            WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        echo "success";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>