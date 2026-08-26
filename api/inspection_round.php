<?php
require_once '../config.php';
require_once '../inspection_workflow.php';

header('Content-Type: application/json; charset=utf-8');

function inspection_round_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function inspection_round_items_payload(): array
{
    $raw = $_POST['results'] ?? '[]';
    $items = is_array($raw) ? $raw : json_decode((string)$raw, true);
    return is_array($items) ? $items : [];
}

function inspection_round_require(int $roundId): ?array
{
    global $conn;
    return inspection_fetch_one($conn, "SELECT * FROM inspection_rounds WHERE id = ? LIMIT 1", 'i', [$roundId]);
}

function inspection_round_insert_approval(mysqli $conn, int $roundId, string $step, string $action, string $reason = ''): void
{
    $userId = inspection_current_user_id();
    $snapshot = inspection_user_snapshot($conn, $userId);
    $stmt = $conn->prepare(
        "INSERT INTO inspection_approvals (round_id, step, action, user_id, user_name_snapshot, role_snapshot, signature_snapshot, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'ississss',
        $roundId,
        $step,
        $action,
        $userId,
        $snapshot['name'],
        $snapshot['role'],
        $snapshot['signature'],
        $reason
    );
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException($error);
    }
    $stmt->close();
}

function inspection_round_set_status(mysqli $conn, int $roundId, string $status): void
{
    $stmt = $conn->prepare("UPDATE inspection_rounds SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $status, $roundId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException($error);
    }
    $stmt->close();
}

$action = (string)($_REQUEST['action'] ?? 'load');
$roundId = (int)($_REQUEST['round_id'] ?? 0);
$projectId = (int)($_REQUEST['project_id'] ?? 0);
$milestoneId = (int)($_REQUEST['milestone_id'] ?? 0);

if ($action === 'start') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        inspection_round_response(['status' => 'error', 'message' => 'Method not allowed'], 405);
    }
    $userId = inspection_current_user_id();
    $role = inspection_current_role();
    if ($userId <= 0 || !in_array($role, ['admin', 'procure'], true)) {
        inspection_round_response(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เริ่มรอบตรวจรับ'], 403);
    }
    $context = inspection_load_context($conn, $projectId, $milestoneId);
    if (!$context['project'] || !$context['checklist'] || count($context['items']) === 0) {
        inspection_round_response(['status' => 'error', 'message' => 'กรุณากำหนด Checklist ก่อนเริ่มตรวจ'], 422);
    }
    if ($context['round'] && !in_array($context['round']['status'], ['correction_required'], true)) {
        inspection_round_response([
            'status' => 'success',
            'message' => 'มีรอบตรวจที่ใช้งานอยู่แล้ว',
            'round_id' => (int)$context['round']['id'],
            'next_step' => inspection_next_step($context['round'], $context['approvals']),
        ]);
    }
    $nextRoundRow = inspection_fetch_one($conn, "SELECT COALESCE(MAX(round_no), 0) + 1 AS next_round FROM inspection_rounds WHERE milestone_id = ?", 'i', [$milestoneId]);
    $roundNo = (int)($nextRoundRow['next_round'] ?? 1);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "INSERT INTO inspection_rounds (project_id, milestone_id, checklist_id, round_no, status, inspection_date, created_by) VALUES (?, ?, ?, ?, 'awaiting_inspector_1', ?, ?)"
        );
        $inspectionDate = $_POST['inspection_date'] ?? date('Y-m-d');
        $checklistId = (int)$context['checklist']['id'];
        $stmt->bind_param('iiiisi', $projectId, $milestoneId, $checklistId, $roundNo, $inspectionDate, $userId);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $newRoundId = (int)$stmt->insert_id;
        $stmt->close();

        $itemStmt = $conn->prepare(
            "INSERT INTO inspection_round_results (round_id, checklist_item_id, category, item_order, title, detail, contract_ref, acceptance_criteria, is_required, inspection_step) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($context['items'] as $item) {
            $itemId = (int)$item['id'];
            $category = (string)$item['category'];
            $order = (int)$item['item_order'];
            $title = (string)$item['title'];
            $detail = (string)($item['detail'] ?? '');
            $contractRef = (string)($item['contract_ref'] ?? '');
            $criteria = (string)($item['acceptance_criteria'] ?? '');
            $required = (int)$item['is_required'];
            foreach (['inspector_1', 'inspector_2'] as $inspectionStep) {
                $itemStmt->bind_param('iisissssis', $newRoundId, $itemId, $category, $order, $title, $detail, $contractRef, $criteria, $required, $inspectionStep);
                if (!$itemStmt->execute()) {
                    throw new RuntimeException($itemStmt->error);
                }
            }
        }
        $itemStmt->close();
        $conn->commit();
        inspection_round_response(['status' => 'success', 'round_id' => $newRoundId, 'next_step' => 'inspector_1']);
    } catch (Throwable $e) {
        $conn->rollback();
        inspection_round_response(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

if ($roundId <= 0) {
    inspection_round_response(['status' => 'error', 'message' => 'ไม่พบรอบตรวจรับ'], 422);
}
$round = inspection_round_require($roundId);
if (!$round) {
    inspection_round_response(['status' => 'error', 'message' => 'ไม่พบรอบตรวจรับที่ระบุ'], 404);
}
$context = inspection_load_context($conn, (int)$round['project_id'], (int)$round['milestone_id']);
$nextStep = inspection_next_step($round, $context['approvals']);

if ($action === 'load') {
    inspection_round_response(['status' => 'success', 'context' => $context, 'next_step' => $nextStep]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    inspection_round_response(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$userId = inspection_current_user_id();
$role = inspection_current_role();
if ($userId <= 0) {
    inspection_round_response(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบใหม่'], 401);
}
if (($round['status'] ?? '') === 'completed') {
    inspection_round_response(['status' => 'error', 'message' => 'รอบตรวจนี้เสร็จสิ้นและถูกล็อกแล้ว'], 409);
}

if ($action === 'save_results') {
    if (!in_array($nextStep, ['inspector_1', 'inspector_2'], true) && $role !== 'admin') {
        inspection_round_response(['status' => 'error', 'message' => 'ยังไม่ถึงขั้นตอนบันทึกผลตรวจของคุณ'], 403);
    }
    if ($role !== 'admin' && !inspection_can_act($context['checklist'], $nextStep, $userId, $role)) {
        inspection_round_response(['status' => 'error', 'message' => 'คุณไม่ได้รับมอบหมายให้ตรวจรอบนี้'], 403);
    }
    $posted = inspection_round_items_payload();
    $existingResults = [];
    foreach ($context['results'] as $item) {
        if ((string)($item['inspection_step'] ?? 'inspector_1') !== $nextStep) {
            continue;
        }
        $existingResults[(int)$item['id']] = $item;
    }
    $photoCounts = inspection_photo_counts_for_round($conn, $roundId);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "UPDATE inspection_round_results SET result_status = ?, note = ?, responsible_person = ?, due_date = NULLIF(?, ''), updated_by = ? WHERE id = ? AND round_id = ? AND inspection_step = ?"
        );
        foreach ($posted as $row) {
            $resultId = (int)($row['id'] ?? 0);
            if (!$resultId || !isset($existingResults[$resultId])) {
                throw new RuntimeException('พบรายการตรวจที่ไม่อยู่ในรอบนี้');
            }
            $status = (string)($row['result_status'] ?? '');
            $allowed = ['pass', 'fail', 'conditional_pass', 'not_applicable'];
            if ($status !== '' && !in_array($status, $allowed, true)) {
                throw new RuntimeException('ผลตรวจไม่ถูกต้อง');
            }
            $validation = inspection_validate_result($existingResults[$resultId], $row);
            if ($validation) {
                throw new RuntimeException($validation);
            }
            $photoValidation = inspection_validate_result_photo($status, $photoCounts[$resultId] ?? 0);
            if ($photoValidation) {
                throw new RuntimeException($photoValidation);
            }
            $note = trim((string)($row['note'] ?? ''));
            $responsible = trim((string)($row['responsible_person'] ?? ''));
            $dueDate = (string)($row['due_date'] ?? '');
            $stmt->bind_param('ssssiiis', $status, $note, $responsible, $dueDate, $userId, $resultId, $roundId, $nextStep);
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
        }
        $stmt->close();
        $punchList = trim((string)($_POST['punch_list'] ?? ''));
        $fixDays = max(0, (int)($_POST['fix_within_days'] ?? 0));
        $update = $conn->prepare("UPDATE inspection_rounds SET inspection_date = COALESCE(NULLIF(?, ''), inspection_date), punch_list = ?, fix_within_days = NULLIF(?, 0), updated_at = NOW() WHERE id = ?");
        $date = (string)($_POST['inspection_date'] ?? '');
        $update->bind_param('ssii', $date, $punchList, $fixDays, $roundId);
        if (!$update->execute()) {
            throw new RuntimeException($update->error);
        }
        $update->close();
        inspection_snapshot_document($conn, $roundId, 'draft');
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        inspection_round_response(['status' => 'error', 'message' => $e->getMessage()], 422);
    }
    $fresh = inspection_load_context($conn, (int)$round['project_id'], (int)$round['milestone_id']);
    inspection_round_response(['status' => 'success', 'message' => 'บันทึกผลตรวจแล้ว', 'summary' => inspection_compare_results($fresh['results']), 'context' => $fresh]);
}

if ($action === 'upload') {
    if (!in_array($nextStep, ['inspector_1', 'inspector_2'], true) && $role !== 'admin') {
        inspection_round_response(['status' => 'error', 'message' => 'ยังไม่ถึงขั้นตอนแนบหลักฐาน'], 403);
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        inspection_round_response(['status' => 'error', 'message' => 'ไม่พบไฟล์หลักฐาน'], 422);
    }
    $resultId = (int)($_POST['result_id'] ?? 0);
    $belongs = inspection_fetch_one($conn, "SELECT id FROM inspection_round_results WHERE id = ? AND round_id = ? AND inspection_step = ?", 'iis', [$resultId, $roundId, $nextStep]);
    if (!$belongs) {
        inspection_round_response(['status' => 'error', 'message' => 'รายการตรวจไม่อยู่ในรอบนี้'], 422);
    }
    $tmp = $_FILES['file']['tmp_name'];
    $original = basename((string)$_FILES['file']['name']);
    $mime = function_exists('finfo_open') ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : (string)$_FILES['file']['type'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        inspection_round_response(['status' => 'error', 'message' => 'หลักฐานต้องเป็นรูป JPG, PNG, GIF หรือ WEBP'], 422);
    }
    $dir = dirname(__DIR__) . '/uploads/inspections/';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        inspection_round_response(['status' => 'error', 'message' => 'ไม่สามารถสร้างโฟลเดอร์ไฟล์แนบ'], 500);
    }
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $stored = 'INS_' . $roundId . '_' . bin2hex(random_bytes(6)) . ($extension ? '.' . $extension : '');
    if (!move_uploaded_file($tmp, $dir . $stored)) {
        inspection_round_response(['status' => 'error', 'message' => 'ไม่สามารถจัดเก็บไฟล์แนบ'], 500);
    }
    $size = (int)$_FILES['file']['size'];
    $stmt = $conn->prepare("INSERT INTO inspection_attachments (round_id, result_id, original_name, stored_name, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iisssii', $roundId, $resultId, $original, $stored, $mime, $size, $userId);
    if (!$stmt->execute()) {
        @unlink($dir . $stored);
        $error = $stmt->error;
        $stmt->close();
        inspection_round_response(['status' => 'error', 'message' => $error], 500);
    }
    $attachmentId = (int)$stmt->insert_id;
    $stmt->close();
    inspection_round_response(['status' => 'success', 'attachment_id' => $attachmentId, 'stored_name' => $stored]);
}

if ($action === 'revision') {
    if ($round['status'] !== 'correction_required') {
        inspection_round_response(['status' => 'error', 'message' => 'รอบนี้ยังไม่อยู่ในสถานะตรวจงานแก้'], 409);
    }
    if ($role !== 'admin' && !in_array($role, ['procure'], true)) {
        inspection_round_response(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เริ่มรอบตรวจแก้'], 403);
    }
    $conn->begin_transaction();
    try {
        $nextRoundRow = inspection_fetch_one($conn, "SELECT COALESCE(MAX(round_no), 0) + 1 AS next_round FROM inspection_rounds WHERE milestone_id = ?", 'i', [(int)$round['milestone_id']]);
        $roundNo = (int)($nextRoundRow['next_round'] ?? ((int)$round['round_no'] + 1));
        $stmt = $conn->prepare("INSERT INTO inspection_rounds (project_id, milestone_id, checklist_id, round_no, status, inspection_date, created_by) VALUES (?, ?, ?, ?, 'awaiting_inspector_1', ?, ?)");
        $date = date('Y-m-d');
        $stmt->bind_param('iiiisi', $round['project_id'], $round['milestone_id'], $round['checklist_id'], $roundNo, $date, $userId);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $newRoundId = (int)$stmt->insert_id;
        $stmt->close();
        $oldResults = [];
        foreach ((inspection_results_by_step($context['results'])['inspector_1'] ?? []) as $result) {
            $oldResults[(int)$result['checklist_item_id']] = $result;
        }
        $items = inspection_fetch_all($conn, "SELECT * FROM inspection_checklist_items WHERE checklist_id = ? ORDER BY category, item_order, id", 'i', [(int)$round['checklist_id']]);
        $itemStmt = $conn->prepare("INSERT INTO inspection_round_results (round_id, checklist_item_id, category, item_order, title, detail, contract_ref, acceptance_criteria, is_required, inspection_step, correction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $previous = $oldResults[(int)$item['id']] ?? null;
            $correctionNote = $previous ? trim((string)$previous['note']) : '';
            $itemId = (int)$item['id'];
            $category = (string)$item['category'];
            $order = (int)$item['item_order'];
            $title = (string)$item['title'];
            $detail = (string)($item['detail'] ?? '');
            $contractRef = (string)($item['contract_ref'] ?? '');
            $criteria = (string)($item['acceptance_criteria'] ?? '');
            $required = (int)$item['is_required'];
            foreach (['inspector_1', 'inspector_2'] as $inspectionStep) {
                $itemStmt->bind_param('iisissssiss', $newRoundId, $itemId, $category, $order, $title, $detail, $contractRef, $criteria, $required, $inspectionStep, $correctionNote);
                if (!$itemStmt->execute()) {
                    throw new RuntimeException($itemStmt->error);
                }
            }
        }
        $itemStmt->close();
        $conn->commit();
        inspection_round_response(['status' => 'success', 'round_id' => $newRoundId, 'next_step' => 'inspector_1']);
    } catch (Throwable $e) {
        $conn->rollback();
        inspection_round_response(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'approve') {
    $step = (string)($_POST['step'] ?? '');
    $approvalAction = (string)($_POST['approval_action'] ?? 'approve');
    $reason = trim((string)($_POST['reason'] ?? ''));
    if (!in_array($step, ['inspector_1', 'inspector_2', 'procurement', 'md', 'gmacc'], true)) {
        inspection_round_response(['status' => 'error', 'message' => 'ขั้นอนุมัติไม่ถูกต้อง'], 422);
    }
    if ($step !== $nextStep && $role !== 'admin') {
        inspection_round_response(['status' => 'error', 'message' => 'ยังไม่ถึงขั้นตอนอนุมัตินี้'], 409);
    }
    if ($role !== 'admin' && !inspection_can_act($context['checklist'], $step, $userId, $role)) {
        inspection_round_response(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์อนุมัติขั้นตอนนี้'], 403);
    }
    if (in_array($approvalAction, ['reject', 'return'], true) && $reason === '') {
        inspection_round_response(['status' => 'error', 'message' => 'กรุณาระบุเหตุผลที่ตีกลับ'], 422);
    }
    if (in_array($step, ['inspector_1', 'inspector_2'], true) && $approvalAction === 'approve') {
        $stepResults = array_values(array_filter($context['results'], static function (array $result) use ($step): bool {
            return (string)($result['inspection_step'] ?? 'inspector_1') === $step;
        }));
        foreach ($stepResults as $stepResult) {
            $resultValidation = inspection_validate_result($stepResult, $stepResult);
            if ($resultValidation !== null) {
                inspection_round_response(['status' => 'error', 'message' => $resultValidation], 422);
            }
        }
        $photoValidation = inspection_validate_step_photos(
            $stepResults,
            inspection_photo_counts_for_round($conn, $roundId)
        );
        if ($photoValidation !== null) {
            inspection_round_response(['status' => 'error', 'message' => $photoValidation], 422);
        }
    }
    $summary = inspection_compare_results($context['results']);
    if ($step === 'procurement' && $approvalAction === 'approve' && !$summary['passed'] && $reason === '') {
        inspection_round_response(['status' => 'error', 'message' => 'กรุณาระบุเหตุผลประกอบการตัดสินของจัดซื้อเมื่อผลตรวจไม่ตรงกันหรือไม่ผ่าน'], 422);
    }
    $conn->begin_transaction();
    try {
        inspection_round_insert_approval($conn, $roundId, $step, $approvalAction, $reason);
        if ($approvalAction !== 'approve') {
            $returnStatus = in_array($step, ['md', 'gmacc'], true) ? 'returned' : 'correction_required';
            inspection_round_set_status($conn, $roundId, $returnStatus);
            $conn->commit();
            inspection_round_response(['status' => 'success', 'message' => 'บันทึกการตีกลับแล้ว', 'round_status' => $returnStatus]);
        }
        // Kept unreachable for backward compatibility; procurement may adjudicate non-passed results.
        if (false) {
            throw new RuntimeException('ยังมี Checklist ที่ไม่ผ่านหรือยังตรวจไม่ครบ');
        }
        $nextStatus = [
            'inspector_1' => 'awaiting_inspector_2',
            'inspector_2' => 'awaiting_procurement',
            'procurement' => 'awaiting_md',
            'md' => 'awaiting_gmacc',
            'gmacc' => 'completed',
        ][$step];
        if ($step === 'gmacc') {
            $stmt = $conn->prepare("UPDATE inspection_rounds SET status = 'completed', finalized_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $roundId);
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
            $stmt->close();
            $documentId = inspection_snapshot_document($conn, $roundId, 'final');
            $conn->commit();
            inspection_round_response(['status' => 'success', 'message' => 'GMACC ยืนยันแล้ว กระบวนการตรวจรับเสร็จสิ้น', 'round_status' => 'completed', 'document_id' => $documentId]);
        }
        inspection_round_set_status($conn, $roundId, $nextStatus);
        $conn->commit();
        inspection_round_response(['status' => 'success', 'message' => 'ยืนยันขั้นตอนเรียบร้อยแล้ว', 'round_status' => $nextStatus]);
    } catch (Throwable $e) {
        $conn->rollback();
        inspection_round_response(['status' => 'error', 'message' => $e->getMessage()], 422);
    }
}

inspection_round_response(['status' => 'error', 'message' => 'ไม่รู้จักคำสั่งรอบตรวจรับ'], 400);
