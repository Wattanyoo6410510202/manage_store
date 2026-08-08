<?php
/**
 * Shared helpers for the dynamic contract inspection workflow.
 * The legacy milestone_inspections helpers remain separate so old records keep
 * their original semantics and rendering.
 */

if (!function_exists('inspection_current_user_id')) {
    function inspection_current_user_id(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!function_exists('inspection_current_role')) {
    function inspection_current_role(): string
    {
        return (string)($_SESSION['role'] ?? 'viewer');
    }
}

if (!function_exists('inspection_default_assignment_values')) {
    /**
     * Resolve new-checklist assignees from stable usernames. Display names may
     * change, but the selection must remain valid. GMACC stays role-based so
     * replacing that person requires no code change.
     */
    function inspection_default_assignment_values(array $users): array
    {
        $defaults = [
            'inspector_1_user_id' => 0,
            'inspector_2_user_id' => 0,
            'procurement_user_id' => 0,
            'md_user_id' => 0,
            'gmacc_user_id' => 0,
        ];
        $fixedUsernames = [
            'inspector_1_user_id' => 'eng',
            'inspector_2_user_id' => 'jane',
            'procurement_user_id' => 'amn',
            'md_user_id' => 'poy',
        ];

        foreach ($users as $user) {
            $userId = (int)($user['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $username = strtolower(trim((string)($user['username'] ?? '')));
            foreach ($fixedUsernames as $field => $defaultUsername) {
                if ($defaults[$field] === 0 && $username === $defaultUsername) {
                    $defaults[$field] = $userId;
                }
            }

            if ($defaults['gmacc_user_id'] === 0 && strtolower((string)($user['role'] ?? '')) === 'gmacc') {
                $defaults['gmacc_user_id'] = $userId;
            }
        }

        return $defaults;
    }
}

if (!function_exists('inspection_status_label')) {
    function inspection_status_label(string $status): string
    {
        return [
            'awaiting_inspector_1' => 'รอผู้ตรวจรับ 1',
            'awaiting_inspector_2' => 'รอผู้ตรวจรับ 2',
            'awaiting_procurement' => 'รอจัดซื้อพิจารณา',
            'awaiting_md' => 'รอ MD อนุมัติ',
            'awaiting_gmacc' => 'รอ GMACC ยืนยัน',
            'correction_required' => 'ต้องแก้ไข',
            'returned' => 'ถูกตีกลับ',
            'completed' => 'เสร็จสิ้น',
            'draft' => 'ฉบับร่าง',
        ][$status] ?? 'ไม่ระบุสถานะ';
    }
}

if (!function_exists('inspection_payment_summary')) {
    /**
     * Normalise milestone payment values for the acceptance document and the
     * post-acceptance transfer form. Stored milestone amounts remain the
     * source of truth; missing percentages fall back to WHT 3% and Retention 0%.
     */
    function inspection_payment_summary(array $project): array
    {
        $number = static function ($value, float $fallback = 0.0): float {
            return ($value === null || $value === '') ? $fallback : (float)$value;
        };
        $hasValue = static function (array $source, string $key): bool {
            return array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '';
        };

        $milestoneAmount = max(0.0, $number($project['milestone_amount'] ?? ($project['amount'] ?? 0)));
        $vatAmount = max(0.0, $number($project['vat_amount'] ?? 0));
        $whtPercent = max(0.0, $number($project['wht_percent'] ?? null, 3.0));
        $retentionPercent = max(0.0, $number($project['retention_percent'] ?? null, 0.0));
        $otherDeductionAmount = max(0.0, $number($project['other_deduction_amount'] ?? 0));

        $whtAmount = $hasValue($project, 'wht_amount')
            ? max(0.0, $number($project['wht_amount']))
            : round($milestoneAmount * $whtPercent / 100, 2);
        $retentionAmount = $hasValue($project, 'retention_amount')
            ? max(0.0, $number($project['retention_amount']))
            : round($milestoneAmount * $retentionPercent / 100, 2);

        $storedNet = $hasValue($project, 'net_amount')
            ? $number($project['net_amount'])
            : ($hasValue($project, 'total_request_amount') ? $number($project['total_request_amount']) : null);
        $netTransferAmount = $storedNet !== null
            ? max(0.0, $storedNet)
            : max(0.0, $milestoneAmount + $vatAmount - $whtAmount - $retentionAmount - $otherDeductionAmount);

        return [
            'milestone_amount' => $milestoneAmount,
            'vat_amount' => $vatAmount,
            'wht_percent' => $whtPercent,
            'wht_amount' => $whtAmount,
            'retention_percent' => $retentionPercent,
            'retention_amount' => $retentionAmount,
            'other_deduction_amount' => $otherDeductionAmount,
            'net_transfer_amount' => $netTransferAmount,
            'payment_days_after_acceptance' => max(0, (int)($project['payment_days_after_acceptance'] ?? 0)),
        ];
    }
}

if (!function_exists('inspection_h')) {
    function inspection_h($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('inspection_fetch_one')) {
    function inspection_fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('inspection_fetch_all')) {
    function inspection_fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('inspection_user_has_assignment')) {
    /**
     * Inspection access is assignment-based, not role-based. This lets a
     * technical user open the inspection workspace without granting the
     * entire technical department access to project administration.
     */
    function inspection_user_has_assignment(mysqli $conn, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $row = inspection_fetch_one(
            $conn,
            "SELECT c.id
             FROM inspection_checklists c
             WHERE c.status IN ('active', 'draft')
               AND (c.inspector_1_user_id = ? OR c.inspector_2_user_id = ?)
             LIMIT 1",
            'ii',
            [$userId, $userId]
        );

        return !empty($row);
    }
}

if (!function_exists('inspection_user_assigned_to_project')) {
    function inspection_user_assigned_to_project(mysqli $conn, int $projectId, int $userId): bool
    {
        if ($projectId <= 0 || $userId <= 0) {
            return false;
        }

        $row = inspection_fetch_one(
            $conn,
            "SELECT c.id
             FROM inspection_checklists c
             INNER JOIN project_milestones m ON m.id = c.milestone_id
             WHERE m.project_id = ?
               AND c.status IN ('active', 'draft')
               AND (c.inspector_1_user_id = ? OR c.inspector_2_user_id = ?)
             LIMIT 1",
            'iii',
            [$projectId, $userId, $userId]
        );

        return !empty($row);
    }
}

if (!function_exists('inspection_user_assigned_to_milestones')) {
    function inspection_user_assigned_to_milestones(mysqli $conn, array $milestoneIds, int $userId): bool
    {
        $milestoneIds = array_values(array_unique(array_filter(array_map('intval', $milestoneIds), static function ($id) {
            return $id > 0;
        })));
        if (!$milestoneIds || $userId <= 0) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($milestoneIds), '?'));
        $types = str_repeat('i', count($milestoneIds)) . 'ii';
        $params = array_merge($milestoneIds, [$userId, $userId]);
        $row = inspection_fetch_one(
            $conn,
            "SELECT c.id
             FROM inspection_checklists c
             WHERE c.milestone_id IN ($placeholders)
               AND c.status IN ('active', 'draft')
               AND (c.inspector_1_user_id = ? OR c.inspector_2_user_id = ?)
             LIMIT 1",
            $types,
            $params
        );

        return !empty($row);
    }
}

if (!function_exists('inspection_load_context')) {
    function inspection_load_context(mysqli $conn, int $projectId, int $milestoneId): array
    {
        $project = inspection_fetch_one(
            $conn,
            "SELECT p.*,
                    COALESCE(NULLIF(p.contract_no, ''),
                        (SELECT pd.doc_no
                         FROM project_documents pd
                         WHERE pd.project_id = p.id
                         ORDER BY CASE pd.doc_type
                                  WHEN 'po' THEN 1
                                  WHEN 'pr' THEN 2
                                  WHEN 'quotation' THEN 3
                                  ELSE 4
                              END, pd.id DESC
                         LIMIT 1)
                    ) AS contract_reference_no,
                    m.id AS milestone_id, m.milestone_no, m.milestone_name,
                    m.work_start_date, m.amount AS milestone_amount, m.retention_percent,
                    m.retention_amount, m.net_amount, m.claim_date, m.remarks AS milestone_remarks,
                    m.vat_percent, m.vat_amount, m.wht_percent, m.wht_amount, m.total_request_amount,
                    m.other_deduction_amount, m.deduction_note,
                    s.company_name AS supplier_name, s.address AS supplier_address, s.tax_id AS supplier_tax_id
             FROM projects p
             INNER JOIN project_milestones m ON m.project_id = p.id
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.id = ? AND m.id = ?
             LIMIT 1",
            'ii',
            [$projectId, $milestoneId]
        );

        if (!$project) {
            return ['project' => null, 'checklist' => null, 'items' => [], 'round' => null, 'results' => [], 'approvals' => []];
        }

        $checklist = inspection_fetch_one(
            $conn,
            "SELECT c.*, u1.name AS inspector_1_name, u2.name AS inspector_2_name,
                    up.name AS procurement_name, um.name AS md_name, ug.name AS gmacc_name
             FROM inspection_checklists c
             LEFT JOIN users u1 ON u1.id = c.inspector_1_user_id
             LEFT JOIN users u2 ON u2.id = c.inspector_2_user_id
             LEFT JOIN users up ON up.id = c.procurement_user_id
             LEFT JOIN users um ON um.id = c.md_user_id
             LEFT JOIN users ug ON ug.id = c.gmacc_user_id
             WHERE c.milestone_id = ? AND c.status IN ('active','draft')
             ORDER BY FIELD(c.status, 'active', 'draft'), c.version DESC, c.id DESC
             LIMIT 1",
            'i',
            [$milestoneId]
        );

        $items = $checklist ? inspection_fetch_all(
            $conn,
            "SELECT * FROM inspection_checklist_items WHERE checklist_id = ? ORDER BY category, item_order, id",
            'i',
            [(int)$checklist['id']]
        ) : [];

        $round = $checklist ? inspection_fetch_one(
            $conn,
            "SELECT * FROM inspection_rounds WHERE milestone_id = ? ORDER BY id DESC LIMIT 1",
            'i',
            [$milestoneId]
        ) : null;

        $results = $round ? inspection_fetch_all(
            $conn,
            "SELECT * FROM inspection_round_results WHERE round_id = ? ORDER BY category, item_order, id",
            'i',
            [(int)$round['id']]
        ) : [];

        $approvals = $round ? inspection_fetch_all(
            $conn,
            "SELECT * FROM inspection_approvals WHERE round_id = ? ORDER BY created_at, id",
            'i',
            [(int)$round['id']]
        ) : [];

        $resultsByStep = inspection_results_by_step($results);
        return compact('project', 'checklist', 'items', 'round', 'results', 'resultsByStep', 'approvals');
    }
}

if (!function_exists('inspection_user_snapshot')) {
    function inspection_user_snapshot(mysqli $conn, int $userId): array
    {
        if ($userId <= 0) {
            return ['id' => 0, 'name' => '', 'role' => '', 'signature' => ''];
        }
        $user = inspection_fetch_one($conn, "SELECT id, name, role FROM users WHERE id = ? LIMIT 1", 'i', [$userId]);
        if (!$user) {
            return ['id' => $userId, 'name' => '', 'role' => '', 'signature' => ''];
        }
        $signature = inspection_fetch_one(
            $conn,
            "SELECT path FROM signatures WHERE users_id = ? ORDER BY id DESC LIMIT 1",
            'i',
            [$userId]
        );
        return [
            'id' => (int)$user['id'],
            'name' => (string)$user['name'],
            'role' => (string)$user['role'],
            'signature' => (string)($signature['path'] ?? ''),
        ];
    }
}

if (!function_exists('inspection_can_act')) {
    function inspection_can_act(array $checklist, string $step, int $userId, string $role): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if ($role === 'admin') {
            return true;
        }
        $assignment = [
            'inspector_1' => 'inspector_1_user_id',
            'inspector_2' => 'inspector_2_user_id',
            'procurement' => 'procurement_user_id',
            'md' => 'md_user_id',
            'gmacc' => 'gmacc_user_id',
        ][$step] ?? null;
        if ($assignment && !empty($checklist[$assignment])) {
            return (int)$checklist[$assignment] === $userId;
        }
        if ($step === 'procurement') {
            return in_array($role, ['procure', 'admin'], true);
        }
        if ($step === 'gmacc') {
            return $role === 'gmacc';
        }
        if ($step === 'md') {
            return strpos($role, 'gm') === 0 && $role !== 'gmacc';
        }
        return false;
    }
}

if (!function_exists('inspection_latest_approval')) {
    function inspection_latest_approval(array $approvals, string $step): ?array
    {
        for ($i = count($approvals) - 1; $i >= 0; $i--) {
            if (($approvals[$i]['step'] ?? '') === $step) {
                return $approvals[$i];
            }
        }
        return null;
    }
}

if (!function_exists('inspection_next_step')) {
    function inspection_next_step(?array $round, array $approvals): string
    {
        if (!$round) {
            return 'checklist';
        }
        $statusMap = [
            'awaiting_inspector_1' => 'inspector_1',
            'awaiting_inspector_2' => 'inspector_2',
            'awaiting_procurement' => 'procurement',
            'awaiting_md' => 'md',
            'awaiting_gmacc' => 'gmacc',
            'returned' => 'procurement',
            'correction_required' => 'revision',
            'completed' => 'completed',
        ];
        if (isset($statusMap[$round['status'] ?? ''])) {
            return $statusMap[$round['status']];
        }
        foreach (['inspector_1', 'inspector_2', 'procurement', 'md', 'gmacc'] as $step) {
            $approval = inspection_latest_approval($approvals, $step);
            if (!$approval || $approval['action'] !== 'approve') {
                return $step;
            }
        }
        return 'completed';
    }
}

if (!function_exists('inspection_validate_result')) {
    function inspection_validate_result(array $item, array $result): ?string
    {
        $status = (string)($result['result_status'] ?? '');
        if (!empty($item['is_required']) && $status === '') {
            return 'กรุณาระบุผลตรวจให้ครบทุกข้อบังคับ';
        }
        if ($status === 'not_applicable' && trim((string)($result['note'] ?? '')) === '') {
            return 'กรุณาระบุเหตุผลสำหรับข้อที่ไม่เกี่ยวข้อง';
        }
        if (in_array($status, ['fail', 'conditional_pass'], true)) {
            if (trim((string)($result['note'] ?? '')) === '') {
                return 'ข้อที่ไม่ผ่านหรือผ่านแบบมีเงื่อนไขต้องมีหมายเหตุ';
            }
            if (empty($result['due_date'])) {
                return 'กรุณาระบุกำหนดแก้ไข';
            }
        }
        return null;
    }
}

if (!function_exists('inspection_status_from_results')) {
    function inspection_status_from_results(array $results): array
    {
        $required = 0;
        $answered = 0;
        $issues = 0;
        foreach ($results as $result) {
            if (!empty($result['is_required'])) {
                $required++;
                if (!empty($result['result_status'])) {
                    $answered++;
                }
            }
            if (in_array($result['result_status'] ?? '', ['fail', 'conditional_pass'], true)) {
                $issues++;
            }
        }
        return [
            'required_count' => $required,
            'answered_count' => $answered,
            'issue_count' => $issues,
            'complete' => $required === $answered,
            'passed' => $required === $answered && $issues === 0,
        ];
    }
}

if (!function_exists('inspection_results_by_step')) {
    function inspection_results_by_step(array $results): array
    {
        $grouped = [
            'inspector_1' => [],
            'inspector_2' => [],
        ];
        foreach ($results as $result) {
            $step = (string)($result['inspection_step'] ?? 'inspector_1');
            if (!isset($grouped[$step])) {
                $step = 'inspector_1';
            }
            $grouped[$step][] = $result;
        }
        return $grouped;
    }
}

if (!function_exists('inspection_compare_results')) {
    function inspection_compare_results(array $results): array
    {
        $grouped = inspection_results_by_step($results);
        $legacySingleStep = empty($grouped['inspector_2']);
        $byItem = [];
        foreach (['inspector_1', 'inspector_2'] as $step) {
            foreach ($grouped[$step] as $result) {
                $itemId = (int)($result['checklist_item_id'] ?? 0);
                if (!$itemId) {
                    $itemId = -((int)($result['item_order'] ?? 0));
                }
                $byItem[$itemId][$step] = $result;
            }
        }

        $comparison = [];
        $hasConflict = false;
        $hasFail = false;
        $issueCount = 0;
        $bothComplete = true;
        $requiredCount = 0;
        foreach ($byItem as $itemId => $item) {
            $first = $item['inspector_1'] ?? null;
            $second = $item['inspector_2'] ?? null;
            $firstStatus = (string)($first['result_status'] ?? '');
            $secondStatus = (string)($second['result_status'] ?? '');
            $required = !empty($first['is_required']) || !empty($second['is_required']);
            if ($required) {
                $requiredCount++;
            }
            if (!$first || !$second || ($required && ($firstStatus === '' || $secondStatus === ''))) {
                $bothComplete = false;
            }
            if ($firstStatus !== '' && $secondStatus !== '' && $firstStatus !== $secondStatus) {
                $hasConflict = true;
            }
            if (in_array($firstStatus, ['fail', 'conditional_pass'], true) || in_array($secondStatus, ['fail', 'conditional_pass'], true)) {
                $hasFail = true;
                $issueCount++;
            }
            $comparison[] = [
                'item_id' => $itemId,
                'inspector_1' => $first,
                'inspector_2' => $second,
                'inspector_1_status' => $firstStatus,
                'inspector_2_status' => $secondStatus,
                'conflict' => $firstStatus !== '' && $secondStatus !== '' && $firstStatus !== $secondStatus,
            ];
        }

        $singleStepSummary = inspection_status_from_results($grouped['inspector_1']);
        if ($legacySingleStep) {
            $bothComplete = $singleStepSummary['complete'];
            $passed = $singleStepSummary['passed'];
        } else {
            $passed = $bothComplete && !$hasFail && !$hasConflict && $requiredCount > 0;
        }
        foreach ($comparison as $item) {
            foreach (['inspector_1_status', 'inspector_2_status'] as $statusKey) {
                if ($legacySingleStep && $statusKey === 'inspector_2_status') {
                    continue;
                }
                if (!in_array($item[$statusKey], ['pass', 'not_applicable'], true)) {
                    $passed = false;
                }
            }
        }

        return [
            'items' => $comparison,
            'item_count' => count($comparison),
            'required_count' => $requiredCount,
            'both_complete' => $bothComplete,
            'has_conflict' => $hasConflict,
            'has_fail' => $hasFail,
            'issue_count' => $issueCount,
            'legacy_single_step' => $legacySingleStep,
            'passed' => $passed,
        ];
    }
}

if (!function_exists('inspection_snapshot_document')) {
    function inspection_snapshot_document(mysqli $conn, int $roundId, string $status = 'draft'): int
    {
        $round = inspection_fetch_one($conn, "SELECT * FROM inspection_rounds WHERE id = ? LIMIT 1", 'i', [$roundId]);
        if (!$round) {
            throw new RuntimeException('ไม่พบรอบตรวจรับงาน');
        }

        $context = inspection_load_context($conn, (int)$round['project_id'], (int)$round['milestone_id']);
        $attachments = inspection_fetch_all(
            $conn,
            "SELECT * FROM inspection_attachments WHERE round_id = ? ORDER BY id",
            'i',
            [$roundId]
        );
        $snapshot = [
            'round' => $round,
            'project' => $context['project'],
            'checklist' => $context['checklist'],
            'items' => $context['results'],
            'approvals' => $context['approvals'],
            'attachments' => $attachments,
            'generated_at' => date('c'),
        ];

        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('ไม่สามารถสร้างข้อมูลเอกสารตรวจรับ');
        }

        $existing = inspection_fetch_one(
            $conn,
            "SELECT id, status FROM inspection_documents WHERE round_id = ? ORDER BY id DESC LIMIT 1",
            'i',
            [$roundId]
        );
        $documentNo = 'INS-' . str_pad((string)$roundId, 5, '0', STR_PAD_LEFT);
        $userId = inspection_current_user_id();

        if ($existing && $existing['status'] !== 'final') {
            $stmt = $conn->prepare(
                "UPDATE inspection_documents SET status = ?, snapshot_json = ?, created_by = ?, finalized_at = CASE WHEN ? = 'final' THEN NOW() ELSE finalized_at END WHERE id = ?"
            );
            $stmt->bind_param('ssisi', $status, $json, $userId, $status, $existing['id']);
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new RuntimeException($error);
            }
            $stmt->close();
            return (int)$existing['id'];
        }

        $stmt = $conn->prepare(
            "INSERT INTO inspection_documents (round_id, document_no, version, status, snapshot_json, created_by, finalized_at) VALUES (?, ?, 1, ?, ?, ?, CASE WHEN ? = 'final' THEN NOW() ELSE NULL END)"
        );
        $stmt->bind_param('isssis', $roundId, $documentNo, $status, $json, $userId, $status);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error);
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }
}
