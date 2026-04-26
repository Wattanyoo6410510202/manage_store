<?php
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']); 
    $user_id = $_SESSION['user_id'] ?? 0; // ดึง ID ผู้ใช้งานปัจจุบัน

    // บันทึกสถานะ พร้อมอัปเดต approved_by เป็นผู้ที่ทำการกดเปลี่ยนสถานะ
    $sql = "UPDATE projects SET 
            project_status = '$status', 
            approved_by = '$user_id', 
            updated_at = NOW() 
            WHERE id = '$project_id'";

    if (mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
}
?>