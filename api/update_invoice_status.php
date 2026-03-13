<?php
require_once '../config.php';
header('Content-Type: application/json');

// ปิดการแสดง error ออกหน้าจอเพื่อไม่ให้ JSON พัง
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

// 1. เช็ค Login
$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? '';

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ กรุณา Login ใหม่']);
    exit;
}

// 2. รับค่าจาก JS
$id = $_GET['id'] ?? $_POST['id'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// รายการสถานะที่รองรับใน enum('pending', 'paid', 'canceled', 'approved')
$allowed_actions = ['approved', 'paid', 'pending', 'canceled'];

if (!$id || !in_array($action, $allowed_actions)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลที่ส่งมาไม่ถูกต้อง']);
    exit;
}

try {
    // 3. ตรวจสอบสถานะปัจจุบัน
    $stmt = $conn->prepare("SELECT status FROM invoices WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $current_status = $result['status'] ?? '';

    if ($current_status === $action) {
        echo json_encode(['status' => 'error', 'message' => 'รายการนี้อยู่ในสถานะ ' . $action . ' อยู่แล้ว']);
        exit;
    }

    // 4. แยก Logic ตามความสำคัญ
    if ($action === 'approved') {
        // --- กรณี "อนุมัติ": ต้องเช็คสิทธิ์ และเก็บชื่อผู้อนุมัติ ---
        if (!in_array($user_role, ['admin', 'gmhok'])) {
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ในการอนุมัติรายการนี้']);
            exit;
        }

        $update_sql = "UPDATE invoices SET 
                       status = ?, 
                       approved_by = ?, 
                       approved_at = NOW(), 
                       updated_at = NOW() 
                       WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sii", $action, $user_id, $id);

    } else {
        // --- กรณีอื่นๆ (paid, pending, canceled): เปลี่ยนสถานะอย่างเดียว ---
        $update_sql = "UPDATE invoices SET 
                       status = ?, 
                       updated_at = NOW() 
                       WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $action, $id);
    }

    // 5. ประมวลผลและส่งคำตอบ
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'เปลี่ยนสถานะเป็น ' . $action . ' เรียบร้อยแล้ว',
            'new_status' => $action
        ]);
    } else {
        throw new Exception($conn->error);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดที่ระบบ: ' . $e->getMessage()
    ]);
}

if (isset($stmt)) { $stmt->close(); }
$conn->close();