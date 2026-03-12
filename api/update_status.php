<?php
// 1. ปิดการแสดงผล Error ออกทางหน้าจอ (ป้องกันไม่ให้พ่นแท็ก <b> ออกมา)
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
require_once '../config.php'; // <--- เช็คดูว่าไฟล์นี้อยู่ถอยหลังไป 1 Step จริงไหม
session_start();

$id = $_GET['id'] ?? '';
$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 1;

// เตรียมตัวแปร Response ไว้ก่อน
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ'];

if ($id && $action === 'approved') {
    // 2. เช็คว่าตัวแปร $conn มีอยู่จริงและเชื่อมต่อได้ไหม
    if (!isset($conn) || !$conn) {
        $response['message'] = 'Database connection failed';
    } else {
        $id = mysqli_real_escape_string($conn, $id); // ป้องกัน SQL Injection ด้วยนะจาร
        
        $sql = "UPDATE quotations SET 
                status = 'approved', 
                approved_by = '$user_id', 
                approved_at = NOW() 
                WHERE id = '$id'";

        if (mysqli_query($conn, $sql)) {
            $response = ['status' => 'success'];
        } else {
            $response['message'] = mysqli_error($conn);
        }
    }
} else {
    $response['message'] = 'Invalid Request: ข้อมูลไม่ครบถ้วน';
}

// 3. พ่น JSON ออกไปแค่ครั้งเดียวตอนจบ
echo json_encode($response);
exit;