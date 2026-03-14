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

// 2. รับค่าจาก JS (รองรับทั้ง GET สำหรับสถานะทั่วไป และ POST สำหรับการอัปโหลดไฟล์)
$id = $_POST['id'] ?? $_GET['id'] ?? null;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// รายการสถานะที่รองรับ
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

    if ($current_status === $action && $action !== 'paid') {
        echo json_encode(['status' => 'error', 'message' => 'รายการนี้อยู่ในสถานะ ' . $action . ' อยู่แล้ว']);
        exit;
    }

    // 4. แยก Logic ตามความสำคัญ
    if ($action === 'approved') {
        // --- กรณี "อนุมัติ" ---
        if (!in_array($user_role, ['admin', 'gmhok'])) {
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ในการอนุมัติรายการนี้']);
            exit;
        }

        $update_sql = "UPDATE invoices SET status = ?, approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sii", $action, $user_id, $id);

    } elseif ($action === 'paid') {
        // --- กรณี "ชำระเงิน": รองรับการแนบไฟล์หลักฐาน และบันทึกเวลาจ่าย ---
        $file_name = null;
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
            $upload_dir = "../uploads/payments/";
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

            $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
            $file_name = "pay_" . $id . "_" . date('YmdHis') . "." . $ext;
            
            if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_dir . $file_name)) {
                throw new Exception("ไม่สามารถย้ายไฟล์ไปยังที่จัดเก็บได้");
            }
        }

        // บันทึกสถานะ, ชื่อไฟล์หลักฐาน และเวลาที่ชำระเงิน (paid_at)
        $update_sql = "UPDATE invoices SET 
                       status = ?, 
                       payment_proof = IFNULL(?, payment_proof), 
                       paid_at = NOW(), 
                       updated_at = NOW() 
                       WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssi", $action, $file_name, $id);

    } else {
        // --- กรณีอื่นๆ (pending, canceled) ---
        $update_sql = "UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $action, $id);
    }

    // 5. ประมวลผลและส่งคำตอบ
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึกสถานะ ' . $action . ' เรียบร้อยแล้ว',
            'new_status' => $action,
            'paid_at' => ($action === 'paid') ? date('d/m/Y H:i') : null
        ]);
    } else {
        throw new Exception($conn->error);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}

if (isset($stmt)) { $stmt->close(); }
$conn->close();