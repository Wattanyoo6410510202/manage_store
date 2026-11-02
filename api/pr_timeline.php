<?php
// คืน timeline ของใบขอซื้อเป็น JSON
require_once '../config.php';
require_once '../pr_timeline_lib.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$timeline = pr_timeline_build($conn, (int)($_GET['id'] ?? 0));
if ($timeline === null) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบใบขอซื้อ'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['status' => 'success'] + $timeline, JSON_UNESCAPED_UNICODE);
