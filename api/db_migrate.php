<?php
// อัปเดตฐานข้อมูลจากหน้าตั้งค่า (เฉพาะ admin)
require_once '../config.php';
require_once '../db_migrations_lib.php';
header('Content-Type: application/json; charset=utf-8');

function db_migrate_json(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    db_migrate_json(405, ['status' => 'error', 'message' => 'Method not allowed']);
}
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    db_migrate_json(403, ['status' => 'error', 'message' => 'เฉพาะผู้ดูแลระบบ (admin) เท่านั้น']);
}
$token = (string)($_POST['token'] ?? '');
if ($token === '' || !hash_equals((string)($_SESSION['db_migrate_token'] ?? ''), $token)) {
    db_migrate_json(403, ['status' => 'error', 'message' => 'โทเคนไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่']);
}

@set_time_limit(300);
$results = db_migrations_run($conn, (int)$_SESSION['user_id'], ($_POST['mode'] ?? '') === 'all');
$failed = array_values(array_filter($results, fn($r) => !$r['ok']));

db_migrate_json(200, [
    'status' => $failed ? 'error' : 'success',
    'message' => $failed
        ? 'อัปเดตไม่สำเร็จที่ ' . $failed[0]['file'] . ': ' . $failed[0]['error']
        : ($results ? 'อัปเดตฐานข้อมูลเรียบร้อย ' . count($results) . ' รายการ' : 'ฐานข้อมูลเป็นเวอร์ชันล่าสุดแล้ว'),
    'results' => $results,
    'migrations' => db_migrations_status($conn),
]);
