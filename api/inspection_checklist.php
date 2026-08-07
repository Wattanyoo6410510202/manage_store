<?php
require_once '../config.php';
require_once '../inspection_workflow.php';

header('Content-Type: application/json; charset=utf-8');

function inspection_api_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = (string)($_REQUEST['action'] ?? 'load');
$projectId = (int)($_REQUEST['project_id'] ?? 0);
$milestoneId = (int)($_REQUEST['milestone_id'] ?? 0);

if ($projectId <= 0 || $milestoneId <= 0) {
    inspection_api_response(['status' => 'error', 'message' => 'ข้อมูลโครงการหรืองวดงานไม่ถูกต้อง'], 422);
}

$context = inspection_load_context($conn, $projectId, $milestoneId);
if (!$context['project']) {
    inspection_api_response(['status' => 'error', 'message' => 'ไม่พบโครงการหรืองวดงานที่ระบุ'], 404);
}

if ($action === 'load') {
    inspection_api_response([
        'status' => 'success',
        'context' => $context,
        'next_step' => inspection_next_step($context['round'], $context['approvals']),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    inspection_api_response(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$userId = inspection_current_user_id();
$role = inspection_current_role();
if ($userId <= 0 || !in_array($role, ['admin', 'procure'], true)) {
    inspection_api_response(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์กำหนด Checklist'], 403);
}

if ($action !== 'save') {
    inspection_api_response(['status' => 'error', 'message' => 'ไม่รู้จักคำสั่ง Checklist'], 400);
}

$itemsRaw = $_POST['items'] ?? '[]';
$items = is_array($itemsRaw) ? $itemsRaw : json_decode((string)$itemsRaw, true);
if (!is_array($items) || count($items) === 0) {
    inspection_api_response(['status' => 'error', 'message' => 'กรุณาเพิ่มรายการตรวจอย่างน้อย 1 ข้อ'], 422);
}

$cleanItems = [];
foreach ($items as $index => $item) {
    if (!is_array($item)) {
        inspection_api_response(['status' => 'error', 'message' => 'รูปแบบรายการตรวจไม่ถูกต้อง'], 422);
    }
    $title = trim((string)($item['title'] ?? ''));
    if ($title === '') {
        inspection_api_response(['status' => 'error', 'message' => 'รายการตรวจข้อที่ ' . ((int)$index + 1) . ' ยังไม่มีหัวข้อ'], 422);
    }
    $cleanItems[] = [
        'category' => trim((string)($item['category'] ?? 'ทั่วไป')) ?: 'ทั่วไป',
        'item_order' => count($cleanItems) + 1,
        'title' => $title,
        'detail' => trim((string)($item['detail'] ?? '')),
        'contract_ref' => trim((string)($item['contract_ref'] ?? '')),
        'acceptance_criteria' => trim((string)($item['acceptance_criteria'] ?? '')),
        'is_required' => !empty($item['is_required']) ? 1 : 0,
    ];
}

$assignmentFields = ['inspector_1_user_id', 'inspector_2_user_id', 'procurement_user_id', 'md_user_id', 'gmacc_user_id'];
$assignments = [];
foreach ($assignmentFields as $field) {
    $assignments[$field] = (int)($_POST[$field] ?? 0) ?: null;
}
foreach ($assignmentFields as $field) {
    if (!$assignments[$field]) {
        inspection_api_response(['status' => 'error', 'message' => 'กรุณากำหนดผู้รับผิดชอบให้ครบทุกขั้นตอนของ Flow ก่อนบันทึก Checklist'], 422);
    }
}

$existing = inspection_fetch_one(
    $conn,
    "SELECT * FROM inspection_checklists WHERE milestone_id = ? AND status IN ('active','draft') ORDER BY FIELD(status, 'active','draft'), version DESC, id DESC LIMIT 1",
    'i',
    [$milestoneId]
);
if ($existing && !in_array($role, ['admin', 'procure'], true)) {
    inspection_api_response(['status' => 'error', 'message' => 'เฉพาะผู้ดูแลระบบเท่านั้นที่แก้ไข Checklist เดิมได้'], 403);
}
$roundCount = (int)(inspection_fetch_one($conn, "SELECT COUNT(*) AS total FROM inspection_rounds WHERE milestone_id = ?", 'i', [$milestoneId])['total'] ?? 0);
$editableRoundId = 0;
if ($roundCount > 0) {
    if (!in_array($role, ['admin', 'procure'], true)) {
        inspection_api_response(['status' => 'error', 'message' => 'เฉพาะผู้ดูแลระบบเท่านั้นที่แก้ Checklist ได้'], 403);
    }
    if ($roundCount !== 1) {
        inspection_api_response(['status' => 'error', 'message' => 'มีประวัติหลายรอบแล้ว ไม่สามารถแก้ Checklist เดิมทับประวัติได้'], 409);
    }
    $editableRound = inspection_fetch_one($conn, "SELECT id, status FROM inspection_rounds WHERE milestone_id = ? ORDER BY id DESC LIMIT 1", 'i', [$milestoneId]);
    $editableRoundId = (int)($editableRound['id'] ?? 0);
    $editableAnswered = (int)(inspection_fetch_one($conn, "SELECT COUNT(*) AS total FROM inspection_round_results WHERE round_id = ? AND result_status IS NOT NULL", 'i', [$editableRoundId])['total'] ?? 0);
    $editableApprovals = (int)(inspection_fetch_one($conn, "SELECT COUNT(*) AS total FROM inspection_approvals WHERE round_id = ?", 'i', [$editableRoundId])['total'] ?? 0);
    $editableAttachments = (int)(inspection_fetch_one($conn, "SELECT COUNT(*) AS total FROM inspection_attachments WHERE round_id = ?", 'i', [$editableRoundId])['total'] ?? 0);
    if (!$editableRound || !in_array($editableRound['status'], ['draft', 'awaiting_inspector_1'], true) || $editableAnswered > 0 || $editableApprovals > 0 || $editableAttachments > 0) {
        inspection_api_response(['status' => 'error', 'message' => 'รอบตรวจนี้มีการบันทึกผลหรือหลักฐานแล้ว จึงไม่สามารถแก้ Checklist เดิมทับประวัติได้'], 409);
    }
}

$conn->begin_transaction();
try {
    if ($existing) {
        $checklistId = (int)$existing['id'];
        $stmt = $conn->prepare(
            "UPDATE inspection_checklists SET status = 'active', inspector_1_user_id = NULLIF(?,0), inspector_2_user_id = NULLIF(?,0), procurement_user_id = NULLIF(?,0), md_user_id = NULLIF(?,0), gmacc_user_id = NULLIF(?,0), created_by = ? WHERE id = ?"
        );
        $stmt->bind_param(
            'iiiiiii',
            $assignments['inspector_1_user_id'],
            $assignments['inspector_2_user_id'],
            $assignments['procurement_user_id'],
            $assignments['md_user_id'],
            $assignments['gmacc_user_id'],
            $userId,
            $checklistId
        );
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $stmt->close();
        if ($editableRoundId > 0) {
            if (!$conn->query("DELETE FROM inspection_round_results WHERE round_id = " . $editableRoundId)) {
                throw new RuntimeException($conn->error);
            }
        }
        $conn->query("DELETE FROM inspection_checklist_items WHERE checklist_id = " . $checklistId);
    } else {
        $versionRow = inspection_fetch_one($conn, "SELECT COALESCE(MAX(version), 0) + 1 AS next_version FROM inspection_checklists WHERE milestone_id = ?", 'i', [$milestoneId]);
        $version = (int)($versionRow['next_version'] ?? 1);
        $stmt = $conn->prepare(
            "INSERT INTO inspection_checklists (milestone_id, version, status, inspector_1_user_id, inspector_2_user_id, procurement_user_id, md_user_id, gmacc_user_id, created_by) VALUES (?, ?, 'active', NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), ?)"
        );
        $stmt->bind_param(
            'iiiiiiii',
            $milestoneId,
            $version,
            $assignments['inspector_1_user_id'],
            $assignments['inspector_2_user_id'],
            $assignments['procurement_user_id'],
            $assignments['md_user_id'],
            $assignments['gmacc_user_id'],
            $userId
        );
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $checklistId = (int)$stmt->insert_id;
        $stmt->close();
    }

    $itemStmt = $conn->prepare(
        "INSERT INTO inspection_checklist_items (checklist_id, category, item_order, title, detail, contract_ref, acceptance_criteria, is_required) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $roundResultStmt = $editableRoundId > 0 ? $conn->prepare(
        "INSERT INTO inspection_round_results (round_id, checklist_item_id, category, item_order, title, detail, contract_ref, acceptance_criteria, is_required) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    ) : null;
    foreach ($cleanItems as $item) {
        $itemStmt->bind_param(
            'isissssi',
            $checklistId,
            $item['category'],
            $item['item_order'],
            $item['title'],
            $item['detail'],
            $item['contract_ref'],
            $item['acceptance_criteria'],
            $item['is_required']
        );
        if (!$itemStmt->execute()) {
            throw new RuntimeException($itemStmt->error);
        }
        if ($roundResultStmt) {
            $roundResultItemId = (int)$itemStmt->insert_id;
            $roundIdValue = $editableRoundId;
            $categoryValue = $item['category'];
            $orderValue = $item['item_order'];
            $titleValue = $item['title'];
            $detailValue = $item['detail'];
            $contractRefValue = $item['contract_ref'];
            $criteriaValue = $item['acceptance_criteria'];
            $requiredValue = $item['is_required'];
            $roundResultStmt->bind_param('iisissssi', $roundIdValue, $roundResultItemId, $categoryValue, $orderValue, $titleValue, $detailValue, $contractRefValue, $criteriaValue, $requiredValue);
            if (!$roundResultStmt->execute()) {
                throw new RuntimeException($roundResultStmt->error);
            }
        }
    }
    $itemStmt->close();
    if ($roundResultStmt) {
        $roundResultStmt->close();
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    inspection_api_response(['status' => 'error', 'message' => $e->getMessage()], 500);
}

$fresh = inspection_load_context($conn, $projectId, $milestoneId);
inspection_api_response([
    'status' => 'success',
    'message' => 'บันทึก Checklist ตามสัญญาเรียบร้อยแล้ว',
    'checklist_id' => $checklistId,
    'context' => $fresh,
]);
