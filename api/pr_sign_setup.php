<?php
// จัดการลิงก์เซ็นอนุมัติใบขอซื้อ (ต้อง login): ดึงรายชื่อ / บันทึกผู้ลงนาม+ลำดับ / สร้างลิงก์ใหม่ / ยกเลิก
require_once '../config.php';
require_once '../pr_sign_lib.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

function prs_json(array $body): void
{
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) prs_json(['status' => 'error', 'msg' => 'กรุณาเข้าสู่ระบบใหม่']);
$actor_id = (int)$_SESSION['user_id'];
$actor = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id = $actor_id"));
$actor_role = (string)($actor['role'] ?? '');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $pr = pr_sign_fetch_pr($conn, (int)($_GET['id'] ?? 0));
        if (!$pr) prs_json(['status' => 'error', 'msg' => 'ไม่พบใบขอซื้อ']);
        $candidates = pr_sign_candidates($conn, $pr);
        $signers = pr_signers($conn, (int)$pr['id']);
        $current = array_column(pr_sign_current_rows($pr, $signers), 'id');
        foreach ($signers as &$s) {
            $s['done'] = pr_sign_row_done($s, $pr);
            $s['is_current'] = in_array($s['id'], $current, true);
            $s['locked'] = !empty($s['signed_at']) && $s['decision'] === 'approved';
            unset($s['signed_ip']);
        }
        unset($s);
        $stages = [];
        foreach (PR_SIGN_STAGES as $k => $def) $stages[] = ['key' => $k, 'label' => $def['label'], 'done' => !empty($pr[$k])];
        prs_json([
            'status' => 'success',
            'can_manage' => pr_sign_can_manage($pr, $actor_id, $actor_role),
            'closed_reason' => pr_sign_pr_closed_reason($pr),
            'stages' => $stages,
            'ack_label' => PR_SIGN_ACK_LABEL,
            'candidates' => $candidates,
            'defaults' => pr_sign_default_list($pr, $candidates),
            'signers' => $signers,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') prs_json(['status' => 'error', 'msg' => 'Invalid request']);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $list = json_decode((string)($_POST['list'] ?? '[]'), true);
        if (!is_array($list)) prs_json(['status' => 'error', 'msg' => 'ข้อมูลไม่ถูกต้อง']);
        prs_json(pr_sign_save_list($conn, (int)($_POST['id'] ?? 0), array_values($list), !empty($_POST['notify_line']), $actor_id, $actor_role));
    }
    if ($action === 'link') {
        prs_json(pr_sign_regenerate($conn, (int)($_POST['signer_id'] ?? 0), !empty($_POST['send_line']), $actor_id, $actor_role));
    }
    if ($action === 'cancel') {
        prs_json(pr_sign_cancel_all($conn, (int)($_POST['id'] ?? 0), $actor_id, $actor_role));
    }
    prs_json(['status' => 'error', 'msg' => 'Invalid action']);
} catch (Throwable $e) {
    error_log('pr_sign_setup: ' . $e->getMessage());
    prs_json(['status' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ยังไม่ได้อัปเดตฐานข้อมูล (ตั้งค่า → อัปเดตฐานข้อมูล) หรือระบบขัดข้อง']);
}
