<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../config.php';
require_once '../pr_approval_authorization.php';
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
$allow_resubmit = isset($_POST['allow_resubmit']) ? (int)$_POST['allow_resubmit'] : 0;
$user_role = $_SESSION['role'] ?? '';

if ($pr_id <= 0) {
    send_json('error', 'Invalid PR ID');
}

if (empty($reason)) {
    send_json('error', 'กรุณาระบุเหตุผลในการปฏิเสธ');
}

$actor_stmt = $conn->prepare("SELECT role, sup_id FROM users WHERE id = ? LIMIT 1");
$actor_stmt->bind_param('i', $user_id);
$actor_stmt->execute();
$actor_database_user = $actor_stmt->get_result()->fetch_assoc();
$actor_stmt->close();
if (!$actor_database_user) {
    send_json('error', 'Unauthorized access');
}
$actor_context = pr_approval_resolve_actor_context($_SESSION, $actor_database_user);
$user_role = $actor_context['role'];
$approver_sup_id = $actor_context['sup_id'];

$stmt = $conn->prepare("
    SELECT pr.status, pr.created_by, pr.doc_no, pr.grand_total, pr.supplier_id,
           pr.approved_by_0, u.role AS requester_role, u.sup_id AS requester_sup_id
    FROM pr
    LEFT JOIN users u ON u.id = pr.created_by
    WHERE pr.id = ?
");
$stmt->bind_param("i", $pr_id);
$stmt->execute();
$pr = $stmt->get_result()->fetch_assoc();

if (!$pr) {
    send_json('error', 'ไม่พบข้อมูลใบขอซื้อ');
}

$can_reject = pr_approval_can_reject_request(
    $user_role,
    $approver_sup_id,
    (int)($pr['requester_sup_id'] ?? 0),
    (string)($pr['requester_role'] ?? ''),
    !empty($pr['approved_by_0'])
);
if (!$can_reject) {
    send_json('error', 'คุณไม่มีสิทธิ์ปฏิเสธรายการนี้');
}

if ($pr['status'] === 'approved') {
    send_json('error', 'ไม่สามารถปฏิเสธใบขอซื้อที่ได้รับอนุมัติครบถ้วนแล้ว');
}

if ($pr['status'] === 'rejected') {
    send_json('error', 'ใบขอซื้อนี้ถูกปฏิเสธไปแล้ว');
}

$sql = "UPDATE pr SET status = 'rejected', reject_reason = ?, rejected_by = ?, rejected_at = NOW(), allow_resubmit = ? WHERE id = ?";
$stmt_update = $conn->prepare($sql);
$stmt_update->bind_param("siii", $reason, $user_id, $allow_resubmit, $pr_id);

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
            if ($allow_resubmit) {
                $msg .= "คุณได้รับอนุญาตให้แก้ไขใบขอซื้อนี้และยื่นใหม่ได้\n";
            } else {
                $msg .= "ไม่ได้รับอนุญาตให้ยื่นใบขอซื้อใหม่\n";
            }
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
