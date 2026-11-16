<?php
// บันทึกการเซ็นผ่านลิงก์ (ไม่ต้อง login — ตัวตนผู้เซ็นมาจาก token ในลิงก์เท่านั้น)
require_once '../config.php';
require_once '../pr_sign_lib.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = pr_sign_submit(
        $conn,
        (string)($_POST['token'] ?? ''),
        (string)($_POST['signature'] ?? ''),
        (string)($_POST['decision'] ?? ''),
        (string)($_POST['comment'] ?? ''),
        !empty($_POST['allow_resubmit'])
    );
} catch (Throwable $e) {
    error_log('pr_sign_submit: ' . $e->getMessage());
    $result = ['status' => 'error', 'msg' => 'บันทึกไม่สำเร็จ กรุณาลองใหม่'];
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);
