<?php
/**
 * ลิงก์เซ็นอนุมัติใบขอซื้อ (เปิดได้โดยไม่ต้อง login)
 *
 * - ผู้สร้าง PR / จัดซื้อ / admin เลือกผู้ลงนามและลำดับเอง (ค่าเริ่มต้นตาม role ของขั้นอนุมัติที่ต้องผ่าน)
 * - ผู้ลงนามแต่ละคนได้ลิงก์ของตัวเอง (?t=token, เก็บเฉพาะ sha256) เซ็นได้เมื่อถึงคิวเท่านั้น
 * - แถวที่ผูกกับขั้นอนุมัติ (stage) เมื่อเซ็นอนุมัติ = อนุมัติขั้นนั้นในระบบจริง (pr.approved_by_x)
 *   ครบตามวงเงิน = PR อนุมัติ + สร้าง PO อัตโนมัติ เหมือนอนุมัติในระบบ
 * - ไม่อนุมัติ = PR ถูกปฏิเสธ
 */
require_once __DIR__ . '/pr_approval_authorization.php';
require_once __DIR__ . '/pr_approval_lib.php';

const PR_SIGN_LINK_DAYS = 14;
const PR_SIGN_DIR = 'uploads/pr_signatures/';

/** ขั้นอนุมัติ => ป้ายชื่อ + role หลัก (admin/gmhok สำรองได้ทุกขั้น) */
const PR_SIGN_STAGES = [
    'approved_by_0' => ['label' => 'หัวหน้างาน', 'roles' => []], // GM ของทีมผู้ขอ (คำนวณแยก)
    'approved_by' => ['label' => 'จัดซื้อ', 'roles' => ['procure']],
    'approved_by_1' => ['label' => 'GM ฝ่ายบัญชี', 'roles' => ['gmacc']],
    'approved_by_2' => ['label' => 'ผู้จัดการ (MGR)', 'roles' => ['mgr']],
    'approved_by_3' => ['label' => 'ผู้บริหาร (MGR2)', 'roles' => ['mgr2']],
];
const PR_SIGN_BACKUP_ROLES = ['admin', 'gmhok'];
const PR_SIGN_ACK_LABEL = 'ผู้ลงนาม / รับทราบ';

function pr_sign_e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function pr_sign_stage_label(?string $stage): string
{
    return $stage ? (PR_SIGN_STAGES[$stage]['label'] ?? $stage) : PR_SIGN_ACK_LABEL;
}

/** PR พร้อม role/sup_id ของผู้ขอ */
function pr_sign_fetch_pr(mysqli $conn, int $pr_id, bool $for_update = false): ?array
{
    $sql = "SELECT pr.*, u.role AS requester_role, u.sup_id AS requester_sup_id
            FROM pr LEFT JOIN users u ON u.id = pr.created_by WHERE pr.id = ?" . ($for_update ? ' FOR UPDATE' : '');
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $pr_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

/** ใครส่งลิงก์ได้: ผู้สร้าง PR, จัดซื้อ, admin, gmhok */
function pr_sign_can_manage(array $pr, int $user_id, string $role): bool
{
    return (int)$pr['created_by'] === $user_id || in_array(strtolower($role), ['admin', 'procure', 'gmhok'], true);
}

/** PR ยังส่งให้เซ็นได้ไหม — null = ได้ */
function pr_sign_pr_closed_reason(array $pr): ?string
{
    if (!empty($pr['deleted_at'])) return 'ใบขอซื้อนี้ถูกลบแล้ว';
    if ($pr['status'] === 'approved') return 'ใบขอซื้อนี้อนุมัติครบแล้ว';
    if ($pr['status'] === 'rejected') return 'ใบขอซื้อนี้ถูกปฏิเสธแล้ว';
    return null;
}

/** ผู้ใช้ที่เลือกได้ในแต่ละขั้น: ['approved_by_0' => [...], ..., '' => ทุกคน] (ไม่รวมผู้สร้าง PR) */
function pr_sign_candidates(mysqli $conn, array $pr): array
{
    $users = mysqli_fetch_all(mysqli_query($conn, "SELECT id, name, role, sup_id FROM users ORDER BY name ASC"), MYSQLI_ASSOC);
    $creator = (int)$pr['created_by'];
    $out = ['' => []];
    foreach (array_keys(PR_SIGN_STAGES) as $stage) $out[$stage] = [];

    foreach ($users as $u) {
        $uid = (int)$u['id'];
        if ($uid === $creator) continue;
        $role = strtolower(trim((string)$u['role']));
        $row = ['id' => $uid, 'name' => (string)$u['name'], 'role' => (string)$u['role'], 'primary' => false];
        $out[''][] = $row;
        foreach (PR_SIGN_STAGES as $stage => $def) {
            if ($stage === 'approved_by_0') {
                $primary = pr_approval_is_team_gm($role)
                    && (int)$u['sup_id'] > 0 && (int)$u['sup_id'] === (int)$pr['requester_sup_id']
                    && pr_approval_gm_manages_role($role, (string)$pr['requester_role']);
            } else {
                $primary = in_array($role, $def['roles'], true);
            }
            // เลือกได้ทุกคน — rank: 0 = role ตรงขั้น, 1 = admin/gmhok, 2 = คนอื่น
            $rank = $primary ? 0 : (in_array($role, PR_SIGN_BACKUP_ROLES, true) ? 1 : 2);
            $out[$stage][] = ['primary' => $primary, 'rank' => $rank] + $row;
        }
    }
    // role ตรงขั้นขึ้นก่อน แล้ว admin/gmhok แล้วคนอื่น
    foreach (array_keys(PR_SIGN_STAGES) as $stage) {
        usort($out[$stage], fn($a, $b) => [$a['rank'], $a['name']] <=> [$b['rank'], $b['name']]);
    }
    return $out;
}

/** รายชื่อเริ่มต้น: ขั้นที่ต้องผ่านตามวงเงิน (ที่ยังไม่อนุมัติ) เรียงตามลำดับ role ปกติ */
function pr_sign_default_list(array $pr, array $candidates): array
{
    $list = [];
    foreach (pr_required_stages((string)($pr['budget_limit_type'] ?? '')) as $stage) {
        if (!empty($pr[$stage])) continue;
        // ค่าเริ่มต้นเฉพาะคนที่ role ตรงขั้น หรือ admin/gmhok — ไม่มีก็ให้ผู้ส่งเลือกเอง
        $first = $candidates[$stage][0] ?? null;
        $list[] = ['stage' => $stage, 'user_id' => $first && $first['rank'] < 2 ? $first['id'] : 0];
    }
    return $list;
}

/** ผู้ลงนามของ PR เรียงตามลำดับ (ไม่ส่ง hash ออกไป) */
function pr_signers(mysqli $conn, int $pr_id): array
{
    $stmt = $conn->prepare("SELECT s.*, u.name AS user_name, u.role AS user_role,
                                   (u.line_user_id IS NOT NULL AND u.line_user_id <> '') OR (u.line_token IS NOT NULL AND u.line_token <> '') AS has_line
                            FROM pr_signers s LEFT JOIN users u ON u.id = s.user_id
                            WHERE s.pr_id = ? ORDER BY s.step ASC, s.id ASC");
    $stmt->bind_param('i', $pr_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row['label'] = pr_sign_stage_label($row['stage']);
        $row['has_active_link'] = !empty($row['sign_token_hash']) && !empty($row['sign_token_expires']) && strtotime($row['sign_token_expires']) > time();
        $row['has_line'] = (bool)$row['has_line'];
        unset($row['sign_token_hash']);
    }
    return $rows;
}

/** แถวนี้ผ่านแล้วหรือยัง (เซ็นผ่านลิงก์ หรือขั้นนั้นถูกอนุมัติในระบบไปแล้ว) */
function pr_sign_row_done(array $row, array $pr): bool
{
    return !empty($row['signed_at']) || (!empty($row['stage']) && !empty($pr[$row['stage']]));
}

/** แถว $row เซ็นได้ตอนนี้ไหม — null = ได้ */
function pr_sign_block_reason(array $pr, array $signers, array $row): ?string
{
    if ($reason = pr_sign_pr_closed_reason($pr)) return $reason;
    if (!empty($row['signed_at'])) return 'คุณเซ็นเอกสารนี้แล้ว';
    if (!empty($row['stage']) && !empty($pr[$row['stage']])) return 'ขั้น ' . pr_sign_stage_label($row['stage']) . ' ได้รับการอนุมัติในระบบแล้ว';
    foreach ($signers as $other) {
        if ((int)$other['step'] < (int)$row['step'] && !pr_sign_row_done($other, $pr)) {
            return 'รอ ' . $other['label'] . ' (' . ($other['user_name'] ?? '-') . ') เซ็นก่อน';
        }
    }
    return null;
}

/** แถวที่ถึงคิวเซ็นตอนนี้ */
function pr_sign_current_rows(array $pr, array $signers): array
{
    return array_values(array_filter($signers, fn($s) => pr_sign_block_reason($pr, $signers, $s) === null));
}

/**
 * บันทึกรายชื่อผู้ลงนามใหม่ ($list = [['stage' => ''|approved_by_x, 'user_id' => n], ...] ตามลำดับเซ็น)
 * แถวที่เซ็นอนุมัติไปแล้วถูกล็อกไว้ด้านบนสุด แถวที่ยังไม่เซ็นจะถูกแทนที่ทั้งหมด
 */
function pr_sign_save_list(mysqli $conn, int $pr_id, array $list, bool $notify_line, int $actor_id, string $actor_role): array
{
    $pr = pr_sign_fetch_pr($conn, $pr_id);
    if (!$pr) return ['status' => 'error', 'msg' => 'ไม่พบใบขอซื้อ'];
    if (!pr_sign_can_manage($pr, $actor_id, $actor_role)) return ['status' => 'error', 'msg' => 'เฉพาะผู้สร้างใบขอซื้อ จัดซื้อ หรือ admin เท่านั้น'];
    if ($reason = pr_sign_pr_closed_reason($pr)) return ['status' => 'error', 'msg' => $reason];
    if (!$list) return ['status' => 'error', 'msg' => 'กรุณาเลือกผู้ลงนามอย่างน้อย 1 คน'];
    if (count($list) > 12) return ['status' => 'error', 'msg' => 'ผู้ลงนามมากเกินไป'];

    // แถวที่ยังใช้ได้: เซ็นอนุมัติแล้ว และ (ไม่ผูกขั้น หรือขั้นนั้นยังเป็นของคนนี้ — กันกรณี PR ถูกยื่นใหม่แล้วล้างการอนุมัติ)
    $kept = array_values(array_filter(pr_signers($conn, $pr_id), fn($s) =>
        !empty($s['signed_at']) && $s['decision'] === 'approved'
        && (empty($s['stage']) || (int)($pr[$s['stage']] ?? 0) === (int)$s['user_id'])));

    $candidates = pr_sign_candidates($conn, $pr);
    $used_users = array_map(fn($s) => (int)$s['user_id'], $kept);
    $used_stages = array_filter(array_map(fn($s) => (string)$s['stage'], $kept));
    $clean = [];
    foreach ($list as $i => $item) {
        $stage = (string)($item['stage'] ?? '');
        $uid = (int)($item['user_id'] ?? 0);
        $n = $i + 1;
        if ($stage !== '' && !isset(PR_SIGN_STAGES[$stage])) return ['status' => 'error', 'msg' => "ลำดับที่ $n: ขั้นอนุมัติไม่ถูกต้อง"];
        if ($uid <= 0) return ['status' => 'error', 'msg' => "ลำดับที่ $n: กรุณาเลือกผู้ลงนาม"];
        if ($uid === (int)$pr['created_by']) return ['status' => 'error', 'msg' => "ลำดับที่ $n: ผู้สร้างใบขอซื้อลงนามอนุมัติใบของตัวเองไม่ได้"];
        if (!in_array($uid, array_column($candidates[$stage], 'id'), true)) {
            return ['status' => 'error', 'msg' => "ลำดับที่ $n: ผู้ใช้นี้ไม่มีสิทธิ์อนุมัติขั้น " . pr_sign_stage_label($stage ?: null)];
        }
        if (in_array($uid, $used_users, true)) return ['status' => 'error', 'msg' => "ลำดับที่ $n: เลือกผู้ลงนามซ้ำ (1 คนเซ็นได้ 1 ช่อง)"];
        if ($stage !== '') {
            if (in_array($stage, $used_stages, true)) return ['status' => 'error', 'msg' => "ลำดับที่ $n: ขั้น " . pr_sign_stage_label($stage) . ' ถูกเลือกซ้ำ'];
            if (!empty($pr[$stage])) return ['status' => 'error', 'msg' => "ลำดับที่ $n: ขั้น " . pr_sign_stage_label($stage) . ' อนุมัติในระบบแล้ว'];
            foreach (PR_APPROVAL_STAGE_COLS as $col) {
                if ((int)($pr[$col] ?? 0) === $uid) return ['status' => 'error', 'msg' => "ลำดับที่ $n: ผู้ใช้นี้อนุมัติใบขอซื้อนี้ไปแล้ว"];
            }
            $used_stages[] = $stage;
        }
        $used_users[] = $uid;
        $clean[] = ['stage' => $stage !== '' ? $stage : null, 'user_id' => $uid];
    }

    mysqli_begin_transaction($conn);
    try {
        $keep_ids = array_map(fn($s) => (int)$s['id'], $kept);
        $old = mysqli_fetch_all(mysqli_query($conn, "SELECT id, signature_path FROM pr_signers WHERE pr_id = $pr_id"
            . ($keep_ids ? ' AND id NOT IN (' . implode(',', $keep_ids) . ')' : '')), MYSQLI_ASSOC);
        if ($old) {
            mysqli_query($conn, "DELETE FROM pr_signers WHERE id IN (" . implode(',', array_map(fn($r) => (int)$r['id'], $old)) . ")");
        }
        // แถวที่ล็อกไว้เรียงใหม่เป็น 1..k แล้วต่อด้วยรายชื่อใหม่
        $step = 0;
        foreach ($kept as $s) {
            $step++;
            mysqli_query($conn, "UPDATE pr_signers SET step = $step WHERE id = " . (int)$s['id']);
        }
        $stmt = $conn->prepare("INSERT INTO pr_signers (pr_id, step, stage, user_id, notify_line, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $nl = $notify_line ? 1 : 0;
        foreach ($clean as $c) {
            $step++;
            $stmt->bind_param('iisiii', $pr_id, $step, $c['stage'], $c['user_id'], $nl, $actor_id);
            $stmt->execute();
        }
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return ['status' => 'error', 'msg' => 'บันทึกผู้ลงนามไม่สำเร็จ'];
    }
    foreach ($old as $r) pr_sign_unlink_file($r['signature_path']);
    pr_log_activity($conn, $pr_id, 'sign_link_sent', $actor_id, 'ส่งลิงก์ให้เซ็น ' . count($clean) . ' คน');

    // ออกลิงก์ให้ทุกคน (ลิงก์ของคนที่ยังไม่ถึงคิวจะแสดงสถานะรอ) + ส่ง LINE ให้คนที่ถึงคิว
    $links = [];
    $pr = pr_sign_fetch_pr($conn, $pr_id);
    $signers = pr_signers($conn, $pr_id);
    foreach ($signers as $s) {
        if (!empty($s['signed_at'])) continue;
        $token = pr_sign_issue_token($conn, (int)$s['id']);
        $links[(int)$s['id']] = pr_sign_url($token);
        if ($notify_line && pr_sign_block_reason($pr, $signers, $s) === null) {
            pr_sign_send_line($conn, $pr, $s, $links[(int)$s['id']]);
        }
    }
    return ['status' => 'success', 'msg' => 'บันทึกผู้ลงนามและสร้างลิงก์แล้ว', 'links' => $links];
}

/** สร้าง token ใหม่ให้แถวผู้ลงนาม (ลิงก์เดิมใช้ไม่ได้ทันที) */
function pr_sign_issue_token(mysqli $conn, int $signer_id): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $days = PR_SIGN_LINK_DAYS;
    $stmt = $conn->prepare("UPDATE pr_signers SET sign_token_hash = ?, sign_token_expires = DATE_ADD(NOW(), INTERVAL $days DAY) WHERE id = ? AND signed_at IS NULL");
    $stmt->bind_param('si', $hash, $signer_id);
    $stmt->execute();
    return $token;
}

/** สร้างลิงก์ใหม่ให้ผู้ลงนาม 1 คน (ผู้จัดการลิงก์เท่านั้น) */
function pr_sign_regenerate(mysqli $conn, int $signer_id, bool $send_line, int $actor_id, string $actor_role): array
{
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pr_signers WHERE id = $signer_id"));
    if (!$row) return ['status' => 'error', 'msg' => 'ไม่พบผู้ลงนาม'];
    $pr = pr_sign_fetch_pr($conn, (int)$row['pr_id']);
    if (!$pr || !pr_sign_can_manage($pr, $actor_id, $actor_role)) return ['status' => 'error', 'msg' => 'ไม่มีสิทธิ์'];
    if ($reason = pr_sign_pr_closed_reason($pr)) return ['status' => 'error', 'msg' => $reason];
    if (!empty($row['signed_at'])) return ['status' => 'error', 'msg' => 'ผู้ลงนามนี้เซ็นแล้ว'];
    $url = pr_sign_url(pr_sign_issue_token($conn, $signer_id));
    $sent = false;
    if ($send_line) {
        foreach (pr_signers($conn, (int)$row['pr_id']) as $s) {
            if ((int)$s['id'] === $signer_id) $sent = pr_sign_send_line($conn, $pr, $s, $url);
        }
    }
    return ['status' => 'success', 'url' => $url, 'line_sent' => $sent];
}

/** ยกเลิกลิงก์ทั้งหมดที่ยังไม่เซ็น */
function pr_sign_cancel_all(mysqli $conn, int $pr_id, int $actor_id, string $actor_role): array
{
    $pr = pr_sign_fetch_pr($conn, $pr_id);
    if (!$pr || !pr_sign_can_manage($pr, $actor_id, $actor_role)) return ['status' => 'error', 'msg' => 'ไม่มีสิทธิ์'];
    mysqli_query($conn, "DELETE FROM pr_signers WHERE pr_id = $pr_id AND signed_at IS NULL");
    pr_log_activity($conn, $pr_id, 'sign_link_cancelled', $actor_id);
    return ['status' => 'success', 'msg' => 'ยกเลิกลิงก์ที่ยังไม่เซ็นแล้ว'];
}

/** หาแถวผู้ลงนามจาก token — null ถ้าไม่พบ / หมดอายุ */
function pr_sign_by_token(mysqli $conn, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT s.*, u.name AS user_name FROM pr_signers s LEFT JOIN users u ON u.id = s.user_id
                            WHERE s.sign_token_hash = ? AND s.sign_token_expires > NOW()");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

/** URL เต็มของหน้าเซ็น (เรียกได้ทั้งจากรากโปรเจกต์และ api/) */
function pr_sign_url(string $token): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(str_replace(chr(92), '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $dir = preg_replace('#/api$#', '', $dir);
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/pr_sign.php?t=' . $token;
}

function pr_sign_send_line(mysqli $conn, array $pr, array $signer, string $url): bool
{
    try {
        require_once __DIR__ . '/api/notify_helper.php';
        $msg = "✍️ ขอเชิญเซ็นอนุมัติใบขอซื้อ\n";
        $msg .= "เลขที่: " . $pr['doc_no'] . "\n";
        $msg .= "ในฐานะ: " . pr_sign_stage_label($signer['stage'] ?: null) . "\n";
        $msg .= "ยอดสุทธิ: " . number_format((float)$pr['grand_total'], 2) . " บาท\n";
        $msg .= "เปิดเซ็น (ไม่ต้อง login): " . $url;
        return (bool)notifyUserLine((int)$signer['user_id'], $msg);
    } catch (Throwable $e) {
        return false;
    }
}

/** path ลายเซ็นที่บันทึกไว้ในระบบของผู้ใช้ (ตาราง signatures) */
function pr_sign_saved_signature(mysqli $conn, int $user_id): ?string
{
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT path FROM signatures WHERE users_id = $user_id ORDER BY id DESC LIMIT 1"));
    $path = $row['path'] ?? '';
    if ($path === '' || !is_file(__DIR__ . '/uploads/signatures/' . basename($path))) return null;
    return 'uploads/signatures/' . basename($path);
}

function pr_sign_unlink_file(?string $path): void
{
    if ($path && strpos($path, PR_SIGN_DIR) === 0 && is_file(__DIR__ . '/' . $path)) @unlink(__DIR__ . '/' . $path);
}

/**
 * เซ็นผ่านลิงก์
 * $signature = data URL PNG จาก canvas หรือ 'saved' = ใช้ลายเซ็นที่บันทึกไว้ในระบบ
 */
function pr_sign_submit(mysqli $conn, string $token, string $signature, string $decision, string $comment, bool $allow_resubmit): array
{
    $row = pr_sign_by_token($conn, $token);
    if (!$row) return ['status' => 'error', 'msg' => 'ลิงก์หมดอายุ หรือถูกยกเลิกแล้ว'];
    if (!in_array($decision, ['approved', 'rejected'], true)) return ['status' => 'error', 'msg' => 'กรุณาเลือก อนุมัติ หรือ ไม่อนุมัติ'];
    $comment = trim($comment);
    if ($decision === 'rejected' && $comment === '') return ['status' => 'error', 'msg' => 'กรุณาระบุเหตุผลที่ไม่อนุมัติ'];

    if ($signature === 'saved') {
        $saved = pr_sign_saved_signature($conn, (int)$row['user_id']);
        if (!$saved) return ['status' => 'error', 'msg' => 'ไม่พบลายเซ็นที่บันทึกไว้ กรุณาเซ็นในกรอบ'];
        $png = file_get_contents(__DIR__ . '/' . $saved);
        $ext = strtolower(pathinfo($saved, PATHINFO_EXTENSION)) ?: 'png';
    } else {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $signature, $m)) return ['status' => 'error', 'msg' => 'กรุณาเซ็นชื่อในกรอบ'];
        $png = base64_decode($m[1], true);
        if ($png === false || strlen($png) > 1024 * 1024 || !@getimagesizefromstring($png)) return ['status' => 'error', 'msg' => 'ไฟล์ลายเซ็นไม่ถูกต้องหรือใหญ่เกินไป'];
        $ext = 'png';
    }
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) $ext = 'png';

    $pr_id = (int)$row['pr_id'];
    $signer_id = (int)$row['id'];
    $user_id = (int)$row['user_id'];
    $path = null;
    mysqli_begin_transaction($conn);
    try {
        $pr = pr_sign_fetch_pr($conn, $pr_id, true); // ล็อก PR กันเซ็นซ้อน
        if (!$pr) throw new RuntimeException('ไม่พบใบขอซื้อ');
        $signers = pr_signers($conn, $pr_id);
        $me = null;
        foreach ($signers as $s) if ((int)$s['id'] === $signer_id) $me = $s;
        if (!$me) throw new RuntimeException('ไม่พบผู้ลงนาม');
        if ($reason = pr_sign_block_reason($pr, $signers, $me)) throw new RuntimeException($reason);

        if (!is_dir(__DIR__ . '/' . PR_SIGN_DIR)) mkdir(__DIR__ . '/' . PR_SIGN_DIR, 0777, true);
        $path = PR_SIGN_DIR . 'pr_' . $pr_id . '_' . $signer_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (file_put_contents(__DIR__ . '/' . $path, $png) === false) throw new RuntimeException('บันทึกไฟล์ลายเซ็นไม่สำเร็จ');

        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $comment_db = $comment !== '' ? $comment : null;
        $stmt = $conn->prepare("UPDATE pr_signers SET signature_path = ?, signed_at = NOW(), decision = ?, comment = ?, signed_ip = ?,
                                sign_token_hash = NULL, sign_token_expires = NULL WHERE id = ?");
        $stmt->bind_param('ssssi', $path, $decision, $comment_db, $ip, $signer_id);
        $stmt->execute();

        $full = false;
        if ($decision === 'approved') {
            if (!empty($me['stage'])) {
                $full = pr_approval_apply_stage($conn, $pr_id, $me['stage'], $user_id, 'เซ็นผ่านลิงก์' . ($comment !== '' ? ': ' . $comment : ''))['full_approved'];
            } else {
                pr_log_activity($conn, $pr_id, 'sign_link_signed', $user_id, $comment_db);
            }
        } else {
            $ar = $allow_resubmit ? 1 : 0;
            $stmt = $conn->prepare("UPDATE pr SET status = 'rejected', reject_reason = ?, rejected_by = ?, rejected_at = NOW(), allow_resubmit = ? WHERE id = ?");
            $stmt->bind_param('siii', $comment, $user_id, $ar, $pr_id);
            $stmt->execute();
            pr_log_activity($conn, $pr_id, 'rejected', $user_id, $comment . ' (เซ็นผ่านลิงก์)', $me['stage'] ?: null);
        }
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        if ($path && is_file(__DIR__ . '/' . $path)) @unlink(__DIR__ . '/' . $path);
        return ['status' => 'error', 'msg' => $e instanceof RuntimeException ? $e->getMessage() : 'บันทึกลายเซ็นไม่สำเร็จ'];
    }

    // หลัง commit: แจ้งเตือน
    $pr = pr_sign_fetch_pr($conn, $pr_id);
    try {
        require_once __DIR__ . '/api/notify_helper.php';
        if ($decision === 'rejected' && $pr['created_by']) {
            $msg = "❌ ใบขอซื้อถูกปฏิเสธ (ผ่านลิงก์เซ็น)\nเลขที่: " . $pr['doc_no'] . "\nปฏิเสธโดย: " . $row['user_name'] . "\nเหตุผล: $comment\n"
                . ($allow_resubmit ? "คุณแก้ไขและยื่นใหม่ได้\n" : '') . "เปิดดู: " . getPRUrl($pr_id);
            notifyUserLine($pr['created_by'], $msg);
        } elseif ($decision === 'approved' && empty($row['stage']) && $pr['created_by']) {
            notifyUserLine($pr['created_by'], "✍️ " . $row['user_name'] . " เซ็นใบขอซื้อ " . $pr['doc_no'] . " แล้ว\nเปิดดู: " . getPRUrl($pr_id));
        }
        // ส่งลิงก์ให้คนถัดไปที่ถึงคิว (ถ้าตั้งให้ส่ง LINE)
        if ($decision === 'approved' && !pr_sign_pr_closed_reason($pr)) {
            $signers = pr_signers($conn, $pr_id);
            foreach (pr_sign_current_rows($pr, $signers) as $next) {
                if ((int)$next['notify_line'] === 1) {
                    pr_sign_send_line($conn, $pr, $next, pr_sign_url(pr_sign_issue_token($conn, (int)$next['id'])));
                }
            }
        }
    } catch (Throwable $e) {}

    if ($decision === 'rejected') return ['status' => 'success', 'msg' => 'บันทึกการไม่อนุมัติเรียบร้อย', 'signature' => $path];
    return ['status' => 'success', 'msg' => $full ? 'เซ็นอนุมัติเรียบร้อย — ใบขอซื้ออนุมัติครบแล้ว' : 'เซ็นอนุมัติเรียบร้อย', 'signature' => $path];
}
