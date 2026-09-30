<?php
// API บันทึกลายเซ็นออนไลน์ของใบขออนุมัติโครงการ (เรียกจาก budget_project_print.php)
require 'config.php';
require_once __DIR__ . '/budget_projects_lib.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'msg' => 'กรุณาเข้าสู่ระบบใหม่']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
    exit;
}

$role = (string)($_POST['role'] ?? '');
if (!isset(BUDGET_PROJECT_SIGNER_ROLES[$role])) {
    echo json_encode(['status' => 'error', 'msg' => 'ช่องลงนามไม่ถูกต้อง']);
    exit;
}

echo json_encode(budget_project_sign(
    $conn,
    (int)($_POST['id'] ?? 0),
    $role,
    (int)($_SESSION['user_id'] ?? 0),
    (string)($_POST['signature'] ?? ''),
    isset($_POST['decision']) ? (string)$_POST['decision'] : null,
    (string)($_POST['comment'] ?? '')
));
