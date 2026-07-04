<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function send_json($status, $message) {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    send_json('error', 'Unauthorized access');
}

$pr_id = (int)($_POST['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$reason = trim($_POST['reason'] ?? '');
$user_role = $_SESSION['role'] ?? '';

if ($pr_id <= 0) {
    send_json('error', 'Invalid PR ID');
}

if (empty($reason)) {
    send_json('error', 'กรุณาระบุเหตุผลในการปฏิเสธ');
}

// ตรวจสอบสิทธิ์ (เฉพาะ Mgr, GMACC, Admin หรือผู้ที่มีสิทธิ์อนุมัติ)
$allowed_roles = ['admin', 'mgr', 'mgr2', 'gmacc', 'gmhok', 'procure'];
if (!in_array($user_role, $allowed_roles) && strpos($user_role, 'gm') !== 0) {
    send_json('error', 'คุณไม่มีสิทธิ์ปฏิเสธรายการนี้');
}

$stmt = $conn->prepare("SELECT status, created_by, doc_no, grand_total, supplier_id FROM pr WHERE id = ?");
$stmt->bind_param("i", $pr_id);
$stmt->execute();
$pr = $stmt->get_result()->fetch_assoc();

if (!$pr) {
    send_json('error', 'ไม่พบข้อมูลใบขอซื้อ');
}

if ($pr['status'] === 'approved') {
    send_json('error', 'ไม่สามารถปฏิเสธใบขอซื้อที่ได้รับอนุมัติครบถ้วนแล้ว');
}

if ($pr['status'] === 'rejected') {
    send_json('error', 'ใบขอซื้อนี้ถูกปฏิเสธไปแล้ว');
}

$sql = "UPDATE pr SET status = 'rejected', reject_reason = ?, rejected_by = ?, rejected_at = NOW() WHERE id = ?";
$stmt_update = $conn->prepare($sql);
$stmt_update->bind_param("sii", $reason, $user_id, $pr_id);

if ($stmt_update->execute()) {
    // --- LINE NOTIFY แจ้งผู้สร้าง PR ว่าถูกปฏิเสธ ---
    try {
        require_once 'notify_helper.php';
        if ($pr['created_by']) {
            $rejector_name = $_SESSION['user_name'] ?? $_SESSION['user'] ?? 'ผู้บริหาร';
            $msg = "❌ ใบขอซื้อถูกปฏิเสธ\n";
            $msg .= "เลขที่: " . $pr['doc_no'] . "\n";
            $msg .= "ปฏิเสธโดย: $rejector_name\n";
            $msg .= "เหตุผล: $reason\n";
            $msg .= "เปิดดู: " . getPRUrl($pr_id) . "\n";
            $msg .= "กรุณาติดต่อผู้ปฏิเสธเพื่อขอรายละเอียดเพิ่มเติม";
            notifyUserLine($pr['created_by'], $msg);
        }
    } catch (Exception $e) {}

    send_json('success', 'ปฏิเสธใบขอซื้อเรียบร้อยแล้ว');
} else {
    send_json('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $conn->error);
}

$conn->close();
?>