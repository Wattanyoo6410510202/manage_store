<?php
// ฟังก์ชันกลางของ "ใบขออนุมัติโครงการ" (budget_projects) ใช้ร่วมกันระหว่าง my_budget.php, pending_budget.php และ budget_project_print.php
require_once __DIR__ . '/supplier_display.php';

// ยอดที่กันให้โครงการแล้ว (ไม่นับโครงการที่ถูกปฏิเสธ) — ต้องใช้ในคิวรีที่มี alias bt = budget_types
const BUDGET_PROJECT_ALLOCATED_SQL = "COALESCE((SELECT SUM(bp.amount) FROM budget_projects bp WHERE bp.budget_type_id = bt.id AND bp.status <> 'rejected'), 0)";
const BUDGET_TOTAL_SQL = "(bt.budget_amount + COALESCE((SELECT SUM(a.amount) FROM budget_adjustments a WHERE a.budget_type_id = bt.id AND a.status = 'approved'), 0))";

// PR ที่ถือว่าใช้เงินแล้ว: ยังไม่ถูกลบ และไม่ถูกปฏิเสธ (รวมที่รออนุมัติ เพื่อกันออก PR เกินงบพร้อมกัน) — alias p = pr
const BUDGET_PR_COMMITTED_COND = "p.deleted_at IS NULL AND COALESCE(p.status, '') <> 'rejected'";

/**
 * ยอดงบที่ยังว่าง (alias bt) = งบรวม − ยอดที่กันให้โครงการ − PR ที่ไม่ได้ผูกโครงการ
 * ใช้ทั้งตอนกันเงินให้โครงการใหม่ และตอนออก PR ที่ไม่เลือกโครงการ
 */
function budget_free_sql(int $exclude_project_id = 0, int $exclude_pr_id = 0): string
{
    $allocated = $exclude_project_id > 0
        ? str_replace("bp.status <> 'rejected'", "bp.status <> 'rejected' AND bp.id <> $exclude_project_id", BUDGET_PROJECT_ALLOCATED_SQL)
        : BUDGET_PROJECT_ALLOCATED_SQL;
    return "(" . BUDGET_TOTAL_SQL . " - $allocated - " . budget_general_pr_sql($exclude_pr_id) . ")";
}

function budget_general_pr_sql(int $exclude_pr_id = 0): string
{
    return "COALESCE((SELECT SUM(p.grand_total) FROM pr p WHERE p.budget_type_id = bt.id AND p.budget_project_id IS NULL AND "
        . BUDGET_PR_COMMITTED_COND . " AND p.id <> $exclude_pr_id), 0)";
}

/** ยอด PR ที่ใช้ไปในโครงการ (alias bp = budget_projects) */
function budget_project_pr_used_sql(int $exclude_pr_id = 0): string
{
    return "COALESCE((SELECT SUM(p.grand_total) FROM pr p WHERE p.budget_project_id = bp.id AND "
        . BUDGET_PR_COMMITTED_COND . " AND p.id <> $exclude_pr_id), 0)";
}

/**
 * ตรวจว่าออก PR ยอด $amount จากงบ/โครงการนี้ได้หรือไม่ (เรียกภายใน transaction — ล็อกแถวงบไว้)
 * คืนข้อความ error หรือ null ถ้าผ่าน; ไม่เลือกงบ = ไม่ตรวจ
 */
function budget_pr_check(mysqli $conn, int $budget_type_id, int $project_id, float $amount, int $exclude_pr_id = 0): ?string
{
    if ($budget_type_id <= 0) {
        return $project_id > 0 ? 'กรุณาเลือกประเภทงบประมาณของโครงการ' : null;
    }
    $lock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, status FROM budget_types WHERE id = $budget_type_id FOR UPDATE"));
    if (!$lock) return 'ไม่พบประเภทงบประมาณที่เลือก';
    if ($lock['status'] !== 'approved') return 'ประเภทงบประมาณนี้ยังไม่ได้รับอนุมัติ';

    if ($project_id > 0) {
        $project = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT bp.id, bp.name, bp.status, bp.budget_type_id, bp.amount, " . budget_project_pr_used_sql($exclude_pr_id) . " as used
             FROM budget_projects bp WHERE bp.id = $project_id"));
        if (!$project) return 'ไม่พบโครงการที่เลือก';
        if ((int)$project['budget_type_id'] !== $budget_type_id) return 'โครงการที่เลือกไม่ได้อยู่ในงบประมาณนี้';
        if ($project['status'] !== 'approved') return 'โครงการ "' . $project['name'] . '" ยังไม่ได้รับอนุมัติ';
        $available = (float)$project['amount'] - (float)$project['used'];
        if ($amount > $available + 0.001) {
            return 'ยอด PR ' . number_format($amount, 2) . ' บาท เกินงบคงเหลือของโครงการ "' . $project['name'] . '" (คงเหลือ ' . number_format($available, 2) . ' บาท)';
        }
        return null;
    }

    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT " . budget_free_sql(0, $exclude_pr_id) . " as available FROM budget_types bt WHERE bt.id = $budget_type_id"));
    $available = (float)($row['available'] ?? 0);
    if ($amount > $available + 0.001) {
        return 'ยอด PR ' . number_format($amount, 2) . ' บาท เกินงบคงเหลือ (คงเหลือ ' . number_format($available, 2)
            . ' บาท หลังหักยอดที่กันให้โครงการแล้ว — ถ้าเป็นค่าใช้จ่ายของโครงการ ให้เลือกโครงการ)';
    }
    return null;
}

// ช่องลงนามตามแบบฟอร์ม เรียงตามลำดับการเซ็น (step เดียวกันเซ็นพร้อมกันได้)
const BUDGET_PROJECT_SIGNER_ROLES = [
    'responsible' => ['label' => 'ผู้รับผิดชอบโครงการ', 'step' => 1],
    'checker'     => ['label' => 'ผู้ตรวจสอบโครงการ', 'step' => 2],
    'endorser'    => ['label' => 'ผู้เห็นชอบโครงการ', 'step' => 3],
    'approver'    => ['label' => 'ผู้อนุมัติ', 'step' => 4],
    'hr'          => ['label' => 'รับทราบ ฝ่ายทรัพยากรบุคคล', 'step' => 5],
    'accounting'  => ['label' => 'รับทราบ ฝ่ายบัญชี', 'step' => 5],
];
const BUDGET_PROJECT_APPROVER_STEP = 4;
const BUDGET_PROJECT_SIGNATURE_DIR = 'uploads/budgets/signatures/';

/**
 * SQL หาแถวผู้ลงนามที่ "ถึงคิว" ให้ผู้ใช้เซ็น (alias s = budget_project_signers, p = budget_projects)
 * ถึงคิวเมื่อ: ยังไม่เซ็น, ทุกคนในลำดับก่อนหน้าเซ็นครบแล้ว, และสถานะโครงการตรงกับลำดับ (ก่อน/หลังอนุมัติ)
 */
function budget_project_my_turn_sql(int $user_id): string
{
    $approver_step = BUDGET_PROJECT_APPROVER_STEP;
    return "s.user_id = $user_id AND s.signed_at IS NULL
            AND ((s.step <= $approver_step AND p.status = 'pending') OR (s.step > $approver_step AND p.status = 'approved'))
            AND NOT EXISTS (SELECT 1 FROM budget_project_signers s2 WHERE s2.project_id = s.project_id AND s2.step < s.step AND s2.signed_at IS NULL)";
}

function budget_project_pending_sign_count(mysqli $conn, int $user_id): int
{
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(DISTINCT s.project_id) as total FROM budget_project_signers s
         JOIN budget_projects p ON p.id = s.project_id
         WHERE " . budget_project_my_turn_sql($user_id)));
    return (int)($row['total'] ?? 0);
}

/** ผู้ลงนามของโครงการ keyed ด้วย role_key */
function budget_project_signers(mysqli $conn, int $project_id): array
{
    $rows = mysqli_fetch_all(mysqli_query($conn,
        "SELECT s.*, u.name as user_name FROM budget_project_signers s
         LEFT JOIN users u ON s.user_id = u.id
         WHERE s.project_id = $project_id ORDER BY s.step ASC, s.id ASC"), MYSQLI_ASSOC);
    $signers = [];
    foreach ($rows as $row) {
        $row['label'] = BUDGET_PROJECT_SIGNER_ROLES[$row['role_key']]['label'] ?? $row['role_key'];
        $signers[$row['role_key']] = $row;
    }
    return $signers;
}

/** ผู้ใช้เซ็นช่อง $role ได้ตอนนี้หรือไม่ — คืนข้อความเหตุผลถ้าเซ็นไม่ได้, null ถ้าเซ็นได้ */
function budget_project_sign_block_reason(array $project, array $signers, string $role, int $user_id): ?string
{
    $signer = $signers[$role] ?? null;
    if (!$signer) return 'ไม่มีช่องลงนามนี้';
    if ((int)$signer['user_id'] !== $user_id) return 'คุณไม่ใช่ผู้ลงนามในช่องนี้';
    if ($signer['signed_at']) return 'ช่องนี้เซ็นแล้ว';
    $step = (int)$signer['step'];
    if ($step <= BUDGET_PROJECT_APPROVER_STEP && $project['status'] !== 'pending') return 'โครงการนี้พิจารณาเสร็จแล้ว';
    if ($step > BUDGET_PROJECT_APPROVER_STEP && $project['status'] !== 'approved') return 'ต้องรอให้โครงการได้รับอนุมัติก่อน';
    foreach ($signers as $other) {
        if ((int)$other['step'] < $step && !$other['signed_at']) {
            return 'ต้องรอ ' . $other['label'] . ' (' . ($other['user_name'] ?? '-') . ') เซ็นก่อน';
        }
    }
    return null;
}

/** ช่องที่ผู้ใช้เซ็นได้ตอนนี้ */
function budget_project_my_sign_roles(array $project, array $signers, int $user_id): array
{
    $roles = [];
    foreach (array_keys($signers) as $role) {
        if (budget_project_sign_block_reason($project, $signers, $role, $user_id) === null) $roles[] = $role;
    }
    return $roles;
}

/** ผู้ที่ถึงคิวเซ็นถัดไป (ใช้แสดงสถานะ) */
function budget_project_next_signers(array $project, array $signers): array
{
    if ($project['status'] === 'rejected') return [];
    $next = [];
    foreach ($signers as $role => $signer) {
        if ($signer['signed_at']) continue;
        if (budget_project_sign_block_reason($project, $signers, $role, (int)$signer['user_id']) === null) $next[] = $signer;
    }
    return $next;
}

function budget_project_signature_files(mysqli $conn, int $project_id): array
{
    $rows = mysqli_fetch_all(mysqli_query($conn,
        "SELECT signature_path FROM budget_project_signers WHERE project_id = $project_id AND signature_path IS NOT NULL"), MYSQLI_ASSOC);
    return array_column($rows, 'signature_path');
}

function budget_project_unlink_signature_files(array $paths): void
{
    foreach ($paths as $path) {
        if (strpos((string)$path, BUDGET_PROJECT_SIGNATURE_DIR) === 0 && is_file(__DIR__ . '/' . $path)) @unlink(__DIR__ . '/' . $path);
    }
}

/**
 * บันทึกลายเซ็น (data URL PNG จาก canvas) ของผู้ใช้ในช่อง $role
 * ผู้อนุมัติต้องส่ง $decision = approved|rejected ซึ่งจะเปลี่ยนสถานะโครงการ
 */
function budget_project_sign(mysqli $conn, int $project_id, string $role, int $user_id, string $data_url, ?string $decision, string $comment): array
{
    if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $data_url, $m)) {
        return ['status' => 'error', 'msg' => 'รูปแบบลายเซ็นไม่ถูกต้อง'];
    }
    $png = base64_decode($m[1], true);
    if ($png === false || strlen($png) > 1024 * 1024 || !@getimagesizefromstring($png)) {
        return ['status' => 'error', 'msg' => 'ไฟล์ลายเซ็นไม่ถูกต้องหรือใหญ่เกินไป'];
    }
    $is_approver = $role === 'approver';
    if ($is_approver && !in_array($decision, ['approved', 'rejected'], true)) {
        return ['status' => 'error', 'msg' => 'กรุณาเลือก อนุมัติ หรือ ไม่อนุมัติ'];
    }
    if ($is_approver && $decision === 'rejected' && trim($comment) === '') {
        return ['status' => 'error', 'msg' => 'กรุณาระบุเหตุผลที่ไม่อนุมัติ'];
    }

    mysqli_begin_transaction($conn);
    try {
        // ล็อกโครงการ กันเซ็นซ้อนกัน
        $project = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM budget_projects WHERE id = $project_id FOR UPDATE"));
        if (!$project) throw new RuntimeException('ไม่พบโครงการ');
        $signers = budget_project_signers($conn, $project_id);
        $reason = budget_project_sign_block_reason($project, $signers, $role, $user_id);
        if ($reason !== null) throw new RuntimeException($reason);

        if (!is_dir(__DIR__ . '/' . BUDGET_PROJECT_SIGNATURE_DIR)) mkdir(__DIR__ . '/' . BUDGET_PROJECT_SIGNATURE_DIR, 0777, true);
        $path = BUDGET_PROJECT_SIGNATURE_DIR . 'project_' . $project_id . '_' . $role . '_' . time() . '_' . rand(1000, 9999) . '.png';
        if (file_put_contents(__DIR__ . '/' . $path, $png) === false) throw new RuntimeException('บันทึกไฟล์ลายเซ็นไม่สำเร็จ');

        $decision_db = $is_approver ? $decision : null;
        $comment_db = trim($comment) !== '' ? trim($comment) : null;
        $signer_id = (int)$signers[$role]['id'];
        $stmt = mysqli_prepare($conn, "UPDATE budget_project_signers SET signature_path = ?, signed_at = NOW(), decision = ?, comment = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'sssi', $path, $decision_db, $comment_db, $signer_id);
        mysqli_stmt_execute($stmt);

        if ($is_approver && $decision === 'approved') {
            mysqli_query($conn, "UPDATE budget_projects SET status = 'approved' WHERE id = $project_id");
        } elseif ($is_approver) {
            $stmt = mysqli_prepare($conn, "UPDATE budget_projects SET status = 'rejected', reject_reason = ?, rejected_by = ?, rejected_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $comment_db, $user_id, $project_id);
            mysqli_stmt_execute($stmt);
        }

        mysqli_commit($conn);
        $msg = $is_approver ? ($decision === 'approved' ? 'เซ็นอนุมัติโครงการเรียบร้อย' : 'บันทึกการไม่อนุมัติเรียบร้อย') : 'บันทึกลายเซ็นเรียบร้อย';
        return ['status' => 'success', 'msg' => $msg];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        if (!empty($path) && is_file(__DIR__ . '/' . $path)) @unlink(__DIR__ . '/' . $path);
        return ['status' => 'error', 'msg' => $e instanceof RuntimeException ? $e->getMessage() : 'บันทึกลายเซ็นไม่สำเร็จ'];
    }
}

function budget_project_date_or_null($value): ?string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value) ? (string)$value : null;
}

function budget_project_fetch(mysqli $conn, int $id): ?array
{
    $project = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT bp.*, bt.name as budget_name, s.company_name, parent.project_no as parent_project_no, parent.name as parent_name,
                u.name as creator_name, u_gmacc.name as gmacc_name, u_mgr.name as mgr_name, u_rej.name as rejected_by_name
         FROM budget_projects bp
         JOIN budget_types bt ON bp.budget_type_id = bt.id
         JOIN suppliers s ON bt.sup_id = s.id
         LEFT JOIN budget_projects parent ON bp.parent_project_id = parent.id
         LEFT JOIN users u ON bp.created_by = u.id
         LEFT JOIN users u_gmacc ON bp.approved_by_gmacc = u_gmacc.id
         LEFT JOIN users u_mgr ON bp.approved_by_mgr = u_mgr.id
         LEFT JOIN users u_rej ON bp.rejected_by = u_rej.id
         WHERE bp.id = $id"));
    if (!$project) return null;
    $project['items'] = mysqli_fetch_all(mysqli_query($conn,
        "SELECT line_no, description, amount FROM budget_project_items WHERE project_id = $id ORDER BY line_no ASC"), MYSQLI_ASSOC);
    // PR ที่ใช้เงินโครงการนี้
    $project['prs'] = mysqli_fetch_all(mysqli_query($conn,
        "SELECT p.id, p.doc_no, p.doc_date, p.grand_total, p.status, u.name as creator_name
         FROM pr p LEFT JOIN users u ON p.created_by = u.id
         WHERE p.budget_project_id = $id AND p.deleted_at IS NULL ORDER BY p.created_at DESC"), MYSQLI_ASSOC);
    $project['pr_used'] = array_sum(array_map(fn($p) => $p['status'] === 'rejected' ? 0 : (float)$p['grand_total'], $project['prs']));
    $project['signers'] = budget_project_signers($conn, $id);
    $project['signed_count'] = count(array_filter($project['signers'], fn($s) => !empty($s['signed_at'])));
    return $project;
}

// ผู้สร้างแก้ไข/ลบได้เมื่อยังไม่มีใครอนุมัติหรือเซ็น หรือถูกปฏิเสธแล้ว ส่วน admin แก้ไขได้จนกว่าจะอนุมัติ และลบได้ทุกสถานะ
// ($p['signed_count'] = จำนวนผู้ที่เซ็นแล้ว)
function budget_project_can_edit(array $p, int $user_id, string $role): bool
{
    if ($p['status'] === 'approved') return false;
    if ($role === 'admin') return true;
    if ((int)$p['created_by'] !== $user_id) return false;
    if ($p['status'] === 'rejected') return true;
    return !$p['approved_by_gmacc'] && !$p['approved_by_mgr'] && (int)($p['signed_count'] ?? 0) === 0;
}

function budget_project_can_delete(array $p, int $user_id, string $role): bool
{
    return $role === 'admin' || budget_project_can_edit($p, $user_id, $role);
}

/**
 * สร้างหรือแก้ไขโครงการจากข้อมูลฟอร์ม ($id = 0 คือสร้างใหม่)
 * การแก้ไขจะรีเซ็ตสถานะกลับเป็นรออนุมัติ เพื่อให้ผู้อนุมัติตรวจข้อมูลใหม่
 */
function budget_project_save(mysqli $conn, int $id, array $post, array $files, int $user_id): array
{
    $project_type = ($post['project_type'] ?? 'new') === 'additional' ? 'additional' : 'new';
    $parent_project_id = $project_type === 'additional' ? (int)($post['parent_project_id'] ?? 0) : null;
    $budget_type_id = (int)($post['budget_type_id'] ?? 0);
    $request_date = budget_project_date_or_null($post['request_date'] ?? '') ?? date('Y-m-d');
    $department = trim((string)($post['department'] ?? ''));
    $division = trim((string)($post['division'] ?? ''));
    $project_no = trim((string)($post['project_no'] ?? ''));
    $name = trim((string)($post['name'] ?? ''));
    $responsible_name = trim((string)($post['responsible_name'] ?? ''));
    $objectives = trim((string)($post['objectives'] ?? ''));
    $expected_results = trim((string)($post['expected_results'] ?? ''));
    $duration_value = (int)($post['duration_value'] ?? 0) ?: null;
    $duration_unit = in_array($post['duration_unit'] ?? '', ['day', 'month', 'year'], true) ? $post['duration_unit'] : 'month';

    $items = [];
    $prices = (array)($post['item_amount'] ?? []);
    foreach ((array)($post['item_description'] ?? []) as $i => $desc) {
        $desc = trim((string)$desc);
        $price = round((float)($prices[$i] ?? 0), 2);
        if ($desc === '' && $price == 0) continue;
        if ($price < 0) return ['status' => 'error', 'msg' => 'ราคาในรายละเอียดโครงการต้องไม่ติดลบ'];
        $items[] = ['description' => $desc, 'amount' => $price];
    }
    $amount = round(array_sum(array_column($items, 'amount')), 2);

    if ($name === '' || !$items || $amount <= 0) {
        return ['status' => 'error', 'msg' => 'กรุณากรอกชื่อโครงการ และรายละเอียดค่าใช้จ่ายอย่างน้อย 1 รายการ'];
    }
    if ($project_type === 'additional' && !$parent_project_id) {
        return ['status' => 'error', 'msg' => 'กรุณาเลือกโครงการเดิมสำหรับโครงการเพิ่มเติม'];
    }
    if ($id > 0 && $parent_project_id === $id) {
        return ['status' => 'error', 'msg' => 'เลือกโครงการเดิมเป็นโครงการตัวเองไม่ได้'];
    }

    // ผู้ลงนาม: signer[role_key] = user_id (ผู้อนุมัติบังคับ)
    $signer_input = (array)($post['signer'] ?? []);
    $signer_ids = [];
    foreach (BUDGET_PROJECT_SIGNER_ROLES as $role_key => $role_info) {
        $uid = (int)($signer_input[$role_key] ?? 0);
        if ($uid > 0) $signer_ids[$role_key] = $uid;
    }
    if (empty($signer_ids['approver'])) {
        return ['status' => 'error', 'msg' => 'กรุณาเลือกผู้อนุมัติ'];
    }
    $uid_list = implode(',', array_unique($signer_ids));
    $found = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE id IN ($uid_list)"));
    if ((int)$found['total'] !== count(array_unique($signer_ids))) {
        return ['status' => 'error', 'msg' => 'ไม่พบผู้ใช้ที่เลือกเป็นผู้ลงนาม'];
    }

    mysqli_begin_transaction($conn);
    try {
        if ($project_type === 'additional') {
            // โครงการเพิ่มเติมใช้งบเดียวกับโครงการเดิม
            $parent = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT budget_type_id FROM budget_projects WHERE id = $parent_project_id AND status = 'approved' AND project_type = 'new'"));
            if (!$parent) throw new RuntimeException('ไม่พบโครงการเดิมที่อนุมัติแล้ว');
            $budget_type_id = (int)$parent['budget_type_id'];
        }
        if ($budget_type_id <= 0) throw new RuntimeException('กรุณาเลือกงบประมาณที่อ้างอิง');

        // ล็อกแถวงบเพื่อกันการกันยอดเกินพร้อมกันหลายคน
        $lock = mysqli_query($conn, "SELECT id FROM budget_types WHERE id = $budget_type_id AND status = 'approved' FOR UPDATE");
        if (!mysqli_fetch_assoc($lock)) throw new RuntimeException('ไม่พบงบประมาณที่อนุมัติแล้ว');

        // ยอดว่าง = งบรวม − ที่กันให้โครงการอื่น − PR ที่ไม่ผูกโครงการ (ตอนแก้ไข ไม่นับยอดเดิมของโครงการนี้)
        $check = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT " . budget_free_sql($id) . " as available FROM budget_types bt WHERE bt.id = $budget_type_id"));
        $available = (float)($check['available'] ?? 0);
        if ($amount > $available + 0.001) {
            throw new RuntimeException('ค่าใช้จ่ายเกินงบคงเหลือ (คงเหลือ ' . number_format($available, 2) . ' บาท)');
        }

        if ($project_no !== '') {
            $stmt = mysqli_prepare($conn, "SELECT id FROM budget_projects WHERE project_no = ? AND id <> ?");
            mysqli_stmt_bind_param($stmt, 'si', $project_no, $id);
            mysqli_stmt_execute($stmt);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) throw new RuntimeException('เลขที่โครงการนี้ถูกใช้แล้ว');
        }

        $file_path = null;
        if (isset($files['attachment']) && $files['attachment']['error'] == 0) {
            $ext = strtolower(pathinfo($files['attachment']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'], true)) {
                throw new RuntimeException('ไฟล์แนบรองรับเฉพาะ PDF, รูปภาพ, Word, Excel');
            }
            $upload_dir = 'uploads/budgets/projects/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $target = $upload_dir . 'project_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($files['attachment']['tmp_name'], $target)) $file_path = $target;
        }

        $project_no_db = $project_no !== '' ? $project_no : null;
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE budget_projects SET
                budget_type_id = ?, project_type = ?, parent_project_id = ?, request_date = ?, department = ?, division = ?, project_no = ?, name = ?,
                responsible_name = ?, objectives = ?, duration_value = ?, duration_unit = ?, amount = ?, expected_results = ?,
                file_path = COALESCE(?, file_path), status = 'pending', reject_reason = NULL, rejected_by = NULL, rejected_at = NULL,
                approved_by_gmacc = NULL, approved_at_gmacc = NULL, approved_by_mgr = NULL, approved_at_mgr = NULL
                WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'isisssssssisdssi',
                $budget_type_id, $project_type, $parent_project_id, $request_date, $department, $division, $project_no_db, $name,
                $responsible_name, $objectives, $duration_value, $duration_unit, $amount, $expected_results, $file_path, $id);
            mysqli_stmt_execute($stmt);
            mysqli_query($conn, "DELETE FROM budget_project_items WHERE project_id = $id");
            $project_id = $id;
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO budget_projects
                (budget_type_id, project_type, parent_project_id, request_date, department, division, project_no, name,
                 responsible_name, objectives, duration_value, duration_unit, amount, expected_results, file_path, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            mysqli_stmt_bind_param($stmt, 'isisssssssisdssi',
                $budget_type_id, $project_type, $parent_project_id, $request_date, $department, $division, $project_no_db, $name,
                $responsible_name, $objectives, $duration_value, $duration_unit, $amount, $expected_results, $file_path, $user_id);
            mysqli_stmt_execute($stmt);
            $project_id = mysqli_insert_id($conn);
        }

        $item_stmt = mysqli_prepare($conn, "INSERT INTO budget_project_items (project_id, line_no, description, amount) VALUES (?, ?, ?, ?)");
        foreach ($items as $i => $item) {
            $line_no = $i + 1;
            mysqli_stmt_bind_param($item_stmt, 'iisd', $project_id, $line_no, $item['description'], $item['amount']);
            mysqli_stmt_execute($item_stmt);
        }

        // แก้ไขข้อมูลแล้วต้องเซ็นใหม่ทั้งหมด
        $old_signature_files = [];
        if ($id > 0) {
            $old_signature_files = budget_project_signature_files($conn, $project_id);
            mysqli_query($conn, "DELETE FROM budget_project_signers WHERE project_id = $project_id");
        }
        $signer_stmt = mysqli_prepare($conn, "INSERT INTO budget_project_signers (project_id, role_key, step, user_id) VALUES (?, ?, ?, ?)");
        foreach ($signer_ids as $role_key => $uid) {
            $step = BUDGET_PROJECT_SIGNER_ROLES[$role_key]['step'];
            mysqli_stmt_bind_param($signer_stmt, 'isii', $project_id, $role_key, $step, $uid);
            mysqli_stmt_execute($signer_stmt);
        }

        mysqli_commit($conn);
        budget_project_unlink_signature_files($old_signature_files);
        return ['status' => 'success', 'msg' => $id > 0 ? 'บันทึกการแก้ไขเรียบร้อย ส่งกลับไปรออนุมัติ' : 'ส่งใบขออนุมัติโครงการเรียบร้อย รอการอนุมัติ'];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return ['status' => 'error', 'msg' => $e instanceof RuntimeException ? $e->getMessage() : 'บันทึกไม่สำเร็จ'];
    }
}

function budget_project_delete(mysqli $conn, int $id): array
{
    $child = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM budget_projects WHERE parent_project_id = $id"));
    if ((int)$child['total'] > 0) {
        return ['status' => 'error', 'msg' => 'ลบไม่ได้ เพราะมีโครงการเพิ่มเติมอ้างอิงโครงการนี้อยู่'];
    }
    $pr_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pr WHERE budget_project_id = $id AND deleted_at IS NULL"));
    if ((int)$pr_count['total'] > 0) {
        return ['status' => 'error', 'msg' => 'ลบไม่ได้ เพราะมีใบขอซื้อ (PR) ใช้เงินโครงการนี้อยู่ ' . (int)$pr_count['total'] . ' ใบ'];
    }
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT file_path FROM budget_projects WHERE id = $id"));
    $signature_files = budget_project_signature_files($conn, $id);
    mysqli_query($conn, "DELETE FROM budget_projects WHERE id = $id"); // รายการย่อยและผู้ลงนามลบตาม ON DELETE CASCADE
    budget_project_unlink_signature_files($signature_files);
    if (!empty($row['file_path']) && strpos($row['file_path'], 'uploads/budgets/projects/') === 0 && is_file(__DIR__ . '/' . $row['file_path'])) {
        @unlink(__DIR__ . '/' . $row['file_path']);
    }
    return ['status' => 'success', 'msg' => 'ลบโครงการเรียบร้อย'];
}
