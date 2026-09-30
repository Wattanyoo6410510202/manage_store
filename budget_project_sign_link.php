<?php
// API สร้าง / ยกเลิกลิงก์เซ็นเอกสารโดยไม่ต้อง login (ผู้สร้างโครงการหรือ admin เท่านั้น)
require 'config.php';
require_once __DIR__ . '/budget_projects_lib.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'กรุณาเข้าสู่ระบบใหม่']);
    exit;
}

$project_id = (int)($_POST['id'] ?? 0);
$role = (string)($_POST['role'] ?? '');
$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = (string)($_SESSION['role'] ?? '');

if (!isset(BUDGET_PROJECT_SIGNER_ROLES[$role])) {
    echo json_encode(['status' => 'error', 'msg' => 'ช่องลงนามไม่ถูกต้อง']);
    exit;
}

if (($_POST['action'] ?? 'create') === 'revoke') {
    $project = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, created_by FROM budget_projects WHERE id = $project_id"));
    if (!$project || !budget_project_can_manage_links($project, $user_id, $user_role)) {
        echo json_encode(['status' => 'error', 'msg' => 'ไม่มีสิทธิ์']);
        exit;
    }
    budget_project_revoke_sign_link($conn, $project_id, $role);
    echo json_encode(['status' => 'success', 'msg' => 'ยกเลิกลิงก์แล้ว']);
    exit;
}

$result = budget_project_create_sign_link($conn, $project_id, $role, $user_id, $user_role);
if ($result['status'] === 'success') {
    $result['url'] = budget_project_sign_link_url($result['token']);
    unset($result['token']);
}
echo json_encode($result);
