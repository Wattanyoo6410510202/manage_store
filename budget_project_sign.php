<?php
// API บันทึกลายเซ็นออนไลน์ของใบขออนุมัติโครงการ (เรียกจาก budget_project_print.php)
// เซ็นได้ 2 แบบ: login อยู่แล้ว หรือเปิดจากลิงก์เซ็น (ส่ง token มา ไม่ต้อง login)
require 'config.php';
require_once __DIR__ . '/budget_projects_lib.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
    exit;
}

$token = (string)($_POST['token'] ?? '');
if ($token !== '') {
    // ลิงก์เซ็น: ช่อง ผู้ลงนาม และโครงการ มาจาก token เท่านั้น
    $signer = budget_project_signer_by_token($conn, $token);
    if (!$signer) {
        echo json_encode(['status' => 'error', 'msg' => 'ลิงก์หมดอายุ ถูกยกเลิก หรือเซ็นไปแล้ว']);
        exit;
    }
    $project_id = (int)$signer['project_id'];
    $role = (string)$signer['role_key'];
    $user_id = (int)$signer['user_id'];
    $via = 'link';
} else {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['status' => 'error', 'msg' => 'กรุณาเข้าสู่ระบบใหม่']);
        exit;
    }
    $project_id = (int)($_POST['id'] ?? 0);
    $role = (string)($_POST['role'] ?? '');
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $via = 'login';
}

if (!isset(BUDGET_PROJECT_SIGNER_ROLES[$role])) {
    echo json_encode(['status' => 'error', 'msg' => 'ช่องลงนามไม่ถูกต้อง']);
    exit;
}

echo json_encode(budget_project_sign(
    $conn,
    $project_id,
    $role,
    $user_id,
    (string)($_POST['signature'] ?? ''),
    isset($_POST['decision']) ? (string)$_POST['decision'] : null,
    (string)($_POST['comment'] ?? ''),
    $via
));
