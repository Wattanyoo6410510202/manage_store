<?php
// 1. ป้องกันการพ่น Error ออกหน้าจอจน JSON พัง
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
require_once '../config.php'; 
session_start();

// รับค่าจาก Fetch/URL
$id = $_GET['id'] ?? '';
$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 1; // ID คนที่ล็อกอินอยู่ (ผู้อนุมัติ)

$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ'];

if ($id && $action === 'approved') {
    if (!isset($conn) || !$conn) {
        $response['message'] = 'เชื่อมต่อฐานข้อมูลล้มเหลว';
    } else {
        $id = mysqli_real_escape_string($conn, $id);
        
        // 2. อัปเดตสถานะ PR พร้อมบันทึกว่าใครอนุมัติและเมื่อไหร่
        // จารเช็คชื่อ Column ในตาราง pr ของจารอีกทีนะครับ (status, approved_by, approved_at)
        $sql = "UPDATE pr SET 
                status = 'approved', 
                approved_by = '$user_id', 
                approved_at = NOW() 
                WHERE id = '$id'";

        if (mysqli_query($conn, $sql)) {
            $response = ['status' => 'success', 'message' => 'อนุมัติใบขอซื้อเรียบร้อยแล้ว'];
        } else {
            $response['message'] = 'Database Error: ' . mysqli_error($conn);
        }
    }
} else {
    $response['message'] = 'Invalid Request: ข้อมูลไม่ครบถ้วนหรือ Action ไม่ถูกต้อง';
}

// 3. ส่ง JSON กลับไปให้ JavaScript
echo json_encode($response);
exit;