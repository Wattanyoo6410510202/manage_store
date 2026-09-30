<?php
// ดึงรหัสผ่านโปรแกรมของ "ตัวเอง" เท่านั้น (ใช้ในหน้า E-Service ตอนกดดู/คัดลอก)
require 'config.php';
require_once __DIR__ . '/company_programs_lib.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'msg' => 'กรุณาเข้าสู่ระบบใหม่']);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$program_id = (int)($_GET['program_id'] ?? 0);

if (($_GET['action'] ?? '') === 'password') {
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT a.login_password_enc FROM company_program_accounts a
         JOIN company_programs p ON p.id = a.program_id AND p.is_active = 1
         WHERE a.program_id = $program_id AND a.user_id = $user_id"));
    if (!$row) {
        echo json_encode(['status' => 'error', 'msg' => 'ไม่พบบัญชีของคุณในโปรแกรมนี้']);
        exit;
    }
    echo json_encode(['status' => 'success', 'password' => company_program_decrypt($row['login_password_enc'])]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
