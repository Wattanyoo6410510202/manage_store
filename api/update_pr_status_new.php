<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../config.php';
require_once '../pr_approval_authorization.php';
require_once '../pr_timeline_lib.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function send_json($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

function shutdown_handler() {
    $last_error = error_get_last();
    if ($last_error !== null && in_array($last_error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean();
        send_json('error', 'PHP Critical Error: ' . $last_error['message']);
    }
}
register_shutdown_function('shutdown_handler');

if (!isset($_SESSION['user_id'])) {
    send_json('error', 'Unauthorized access');
}

$pr_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$user_role = $_SESSION['role'] ?? '';

if ($pr_id <= 0) {
    send_json('error', 'Invalid PR ID');
}

if ($action !== 'approved') {
    send_json('error', 'Invalid action');
}

// Fetch PR data with requester role and sup_id
$stmt = $conn->prepare("
    SELECT pr.*, u.role as requester_role, u.sup_id as requester_sup_id
    FROM pr 
    LEFT JOIN users u ON pr.created_by = u.id 
    WHERE pr.id = ?
");
$stmt->bind_param("i", $pr_id);
$stmt->execute();
$result = $stmt->get_result();
$pr = $result->fetch_assoc();

if (!$pr) {
    send_json('error', 'PR not found');
}

if ($pr['deleted_at'] !== null) {
    send_json('error', 'ใบขอซื้อนี้ถูกลบไปแล้ว');
}

if ($pr['status'] === 'approved') {
    send_json('error', 'ใบขอซื้อนี้ได้รับอนุมัติครบถ้วนแล้ว');
}

if ($pr['status'] === 'rejected' && $pr['allow_resubmit'] != 3) {
    send_json('error', 'ใบขอซื้อนี้ถูกปฏิเสธไปแล้ว');
}

// Check if user already approved in any level
if (
    $pr['approved_by_0'] == $user_id ||
    $pr['approved_by'] == $user_id || 
    $pr['approved_by_1'] == $user_id || 
    $pr['approved_by_2'] == $user_id || 
    $pr['approved_by_3'] == $user_id
) {
    send_json('error', 'คุณได้ทำการอนุมัติรายการนี้ไปแล้ว');
}

$update_col = "";
$time_col = "";

// --- Dynamic Level 0 (Supervisor) Mapping by requester team vs approver team ---

// ดึง sup_id ของผู้อนุมัติ
$approver_sup_id = 0;
$approver_stmt = $conn->prepare("SELECT role, sup_id FROM users WHERE id = ?");
$approver_stmt->bind_param("i", $user_id);
$approver_stmt->execute();
$approver_result = $approver_stmt->get_result()->fetch_assoc();
if (!$approver_result) {
    $approver_stmt->close();
    send_json('error', 'Unauthorized access');
}
$approver_context = pr_approval_resolve_actor_context($_SESSION, $approver_result);
$approver_sup_id = $approver_context['sup_id'];
$user_role = $approver_context['role'];
$approver_stmt->close();

// Check for Level 0 Approval: the requester's GM, or Admin/GMHOK as backup.
if (empty($pr['approved_by_0'])) {
    $requester_sup_id = intval($pr['requester_sup_id'] ?? 0);
    $requester_role = (string)($pr['requester_role'] ?? '');
    if (pr_approval_can_approve_supervisor_step($user_role, $approver_sup_id, $requester_sup_id, $requester_role)) {
        $update_col = "approved_by_0";
        $time_col = "approved_at_0";
    }
}

// Role-based approval mapping for other levels
if (!$update_col) {
    if ($user_role === 'procure') {
        if (empty($pr['approved_by'])) { $update_col = "approved_by"; $time_col = "approved_at"; }
    } elseif ($user_role === 'gmacc') {
        if (empty($pr['approved_by_1'])) { $update_col = "approved_by_1"; $time_col = "approved_at_1"; }
    } elseif ($user_role === 'mgr') {
        if (empty($pr['approved_by_2'])) { $update_col = "approved_by_2"; $time_col = "approved_at_2"; }
    } elseif ($user_role === 'mgr2') {
        if (empty($pr['approved_by_3'])) { $update_col = "approved_by_3"; $time_col = "approved_at_3"; }
    } elseif (in_array($user_role, ['admin', 'gmhok'])) {
        // Admin/GMHOK can act as backup for remaining levels
        if (empty($pr['approved_by'])) { $update_col = "approved_by"; $time_col = "approved_at"; }
        elseif (empty($pr['approved_by_1'])) { $update_col = "approved_by_1"; $time_col = "approved_at_1"; }
        elseif (empty($pr['approved_by_2'])) { $update_col = "approved_by_2"; $time_col = "approved_at_2"; }
        elseif (empty($pr['approved_by_3'])) { $update_col = "approved_by_3"; $time_col = "approved_at_3"; }
    }
}

if (!$update_col) {
    send_json('error', 'คุณไม่มีสิทธิ์อนุมัติในขั้นตอนนี้ หรือมีการอนุมัติในส่วนของคุณไปแล้ว');
}

// Update the approval column, check fully approved (auto-create PO) — shared with sign links (pr_approval_lib.php)
require_once '../pr_approval_lib.php';
try {
    $applied = pr_approval_apply_stage($conn, $pr_id, $update_col, (int)$user_id);
} catch (Throwable $e) {
    send_json('error', 'Database update failed: ' . $e->getMessage());
}

send_json('success', 'อนุมัติเรียบร้อยแล้ว', [
    'full_approved' => $applied['full_approved'],
    'column' => $update_col,
    'approved_date' => date('d/m/y'),
    'approver_count' => $applied['approver_count']
]);
