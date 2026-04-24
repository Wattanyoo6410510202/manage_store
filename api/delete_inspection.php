<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

// 1. ดึงชื่อไฟล์แนบก่อนลบ Record
$sql = "SELECT inspection_attachment FROM milestone_inspections WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if ($data) {
    $attachment = $data['inspection_attachment'];
    
    // 2. ลบ Record จาก Database
    $delete_sql = "DELETE FROM milestone_inspections WHERE id = ?";
    $del_stmt = $conn->prepare($delete_sql);
    $del_stmt->bind_param("i", $id);
    
    if ($del_stmt->execute()) {
        // 3. ถ้าลบสำเร็จ ให้ไปลบไฟล์จริงๆ ในโฟลเดอร์ด้วย
        if (!empty($attachment)) {
            $file_path = '../uploads/inspections/' . $attachment;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลการตรวจรับงานและไฟล์แนบเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถลบข้อมูลจากฐานข้อมูลได้: ' . $del_stmt->error]);
    }
    $del_stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลที่ต้องการลบ']);
}

$stmt->close();
$conn->close();
