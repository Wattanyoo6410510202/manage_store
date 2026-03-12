<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    
    // --- เพิ่มส่วนนี้: เช็คว่าสั่งลบหรือไม่ ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $sql = "DELETE FROM project_milestones WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            echo "success";
        } else {
            echo "Error: " . mysqli_error($conn);
        }
        exit; // จบการทำงานตรงนี้เลยถ้าเป็นการลบ
    }
    // -----------------------------------

    // ถ้าไม่ใช่การลบ (เป็นการอัปเดตสถานะปกติ)
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $update_file_sql = "";

    if (isset($_FILES['claim_attachment']) && $_FILES['claim_attachment']['error'] == 0) {
        $target_dir = "../uploads/claims/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $file_ext = pathinfo($_FILES["claim_attachment"]["name"], PATHINFO_EXTENSION);
        $file_name = "PAYMENT_" . $id . "_" . time() . "." . $file_ext;
        
        if (move_uploaded_file($_FILES["claim_attachment"]["tmp_name"], $target_dir . $file_name)) {
            $update_file_sql = ", claim_attachment = '$file_name'";
        }
    }

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