<?php
require_once '../config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? '';
$user_name = $_SESSION['user_name'] ?? '';

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ กรุณา Login ใหม่']);
    exit;
}

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$action = $_POST['action'] ?? $_GET['action'] ?? 'received';

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสเอกสาร']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, status, received_status, created_by FROM pr WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $pr = $stmt->get_result()->fetch_assoc();

    if (!$pr) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบเอกสาร']);
        exit;
    }

    if ($pr['status'] !== 'approved') {
        echo json_encode(['status' => 'error', 'message' => 'เอกสารยังไม่ได้รับการอนุมัติ']);
        exit;
    }

    if ($pr['received_status'] === 'received') {
        echo json_encode(['status' => 'error', 'message' => 'รายการนี้รับของแล้ว']);
        exit;
    }

    $allowed_actions = ['received', 'partial'];
    if (!in_array($action, $allowed_actions)) {
        $action = 'received';
    }

    $stmt = $conn->prepare("UPDATE pr SET received_status = ?, received_at = NOW(), received_by = ? WHERE id = ?");
    $stmt->bind_param("sii", $action, $user_id, $id);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => $action === 'received' ? 'บันทึกการรับของเรียบร้อยแล้ว' : 'บันทึกการรับของบางส่วนแล้ว',
            'received_status' => $action,
            'received_at' => date('d/m/Y H:i')
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

if (isset($stmt)) $stmt->close();
$conn->close();
