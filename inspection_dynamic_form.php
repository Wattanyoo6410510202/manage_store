<?php
$dynamicProject = $dynamic_context['project'] ?? [];
$dynamicChecklist = $dynamic_context['checklist'] ?? null;
$dynamicItems = $dynamic_context['items'] ?? [];
$dynamicRound = $dynamic_context['round'] ?? null;
$dynamicResultsAll = $dynamic_context['results'] ?? [];
$dynamicApprovals = $dynamic_context['approvals'] ?? [];
$dynamicRole = inspection_current_role();
$dynamicNextStep = inspection_next_step($dynamicRound, $dynamicApprovals);
$dynamicResultGroups = $dynamic_context['resultsByStep'] ?? inspection_results_by_step($dynamicResultsAll);
$dynamicResults = in_array($dynamicNextStep, ['inspector_1', 'inspector_2'], true)
    ? ($dynamicResultGroups[$dynamicNextStep] ?? [])
    : ($dynamicResultGroups['inspector_1'] ?? $dynamicResultsAll);
$dynamicPhotosByResult = [];
if ($dynamicRound) {
    $dynamicPhotoRows = inspection_fetch_all(
        $conn,
        "SELECT id, result_id, original_name, stored_name, mime_type
         FROM inspection_attachments
         WHERE round_id = ? AND mime_type LIKE 'image/%'
         ORDER BY id",
        'i',
        [(int)$dynamicRound['id']]
    );
    foreach ($dynamicPhotoRows as $dynamicPhotoRow) {
        $dynamicPhotosByResult[(int)$dynamicPhotoRow['result_id']][] = $dynamicPhotoRow;
    }
}
$dynamicResultSummary = inspection_status_from_results($dynamicResults);
$dynamicComparisonSummary = inspection_compare_results($dynamicResultsAll);
$dynamicFinalDocument = $dynamicRound ? inspection_fetch_one($conn, "SELECT id FROM inspection_documents WHERE round_id = ? AND status = 'final' ORDER BY id DESC LIMIT 1", 'i', [(int)$dynamicRound['id']]) : null;
$dynamicChecklistPassed = (!empty($dynamicResultsAll) && !empty($dynamicComparisonSummary['passed'])) || !empty($dynamicFinalDocument);
$dynamicCanManageChecklist = in_array($dynamicRole, ['admin', 'procure'], true);
$dynamicCanEditChecklist = false;
if ($dynamicRound && $dynamicCanManageChecklist) {
    $dynamicAnsweredCount = (int)(inspection_fetch_one($conn, "SELECT COUNT(*) AS total FROM inspection_round_results WHERE round_id = ? AND result_status IS NOT NULL", 'i', [(int)$dynamicRound['id']])['total'] ?? 0);
    $dynamicApprovalCount = (int)(inspection_fetch_one($conn, "SELECT COUNT(*) AS total FROM inspection_approvals WHERE round_id = ?", 'i', [(int)$dynamicRound['id']])['total'] ?? 0);
    $dynamicAttachmentCount = (int)(inspection_fetch_one($conn, "SELECT COUNT(*) AS total FROM inspection_attachments WHERE round_id = ?", 'i', [(int)$dynamicRound['id']])['total'] ?? 0);
    $dynamicCanEditChecklist = in_array($dynamicRound['status'] ?? '', ['draft', 'awaiting_inspector_1'], true)
        && $dynamicAnsweredCount === 0 && $dynamicApprovalCount === 0 && $dynamicAttachmentCount === 0;
}
$dynamicEditMode = $dynamicCanManageChecklist
    && isset($_GET['edit_checklist'])
    && (int)$_GET['edit_checklist'] === 1
    && (!$dynamicRound || $dynamicCanEditChecklist);
$dynamicStepLabels = [
    'checklist' => 'กำหนด Checklist',
    'inspector_1' => 'ผู้ตรวจรับ ครั้งที่ 1',
    'inspector_2' => 'ผู้ตรวจรับ ครั้งที่ 2',
    'procurement' => 'จัดซื้อยืนยัน',
    'md' => 'MD อนุมัติ',
    'gmacc' => 'GMACC ยืนยันบัญชี',
    'revision' => 'เริ่มรอบแก้ไข',
    'completed' => 'เสร็จสิ้น',
];
$dynamicNextStepLabel = $dynamicStepLabels[$dynamicNextStep] ?? $dynamicNextStep;
$dynamicFlowSteps = [
    'inspector_1' => ['label' => 'ผู้ตรวจรับ 1', 'icon' => 'fa-helmet-safety', 'assignment' => 'inspector_1_name'],
    'inspector_2' => ['label' => 'ผู้ตรวจรับ 2', 'icon' => 'fa-user-check', 'assignment' => 'inspector_2_name'],
    'procurement' => ['label' => 'จัดซื้อ', 'icon' => 'fa-file-invoice', 'assignment' => 'procurement_name'],
    'md' => ['label' => 'MD', 'icon' => 'fa-stamp', 'assignment' => 'md_name'],
    'gmacc' => ['label' => 'GMACC', 'icon' => 'fa-calculator', 'assignment' => 'gmacc_name'],
];
$dynamicCanActCurrentStep = in_array($dynamicNextStep, array_keys($dynamicFlowSteps), true)
    && inspection_can_act($dynamicChecklist ?: [], $dynamicNextStep, inspection_current_user_id(), $dynamicRole);
$dynamicCurrentAssignmentField = $dynamicFlowSteps[$dynamicNextStep]['assignment'] ?? null;
$dynamicCurrentAssigneeName = $dynamicCurrentAssignmentField ? trim((string)($dynamicChecklist[$dynamicCurrentAssignmentField] ?? '')) : '';
$dynamicFlowApprovals = [];
foreach (array_keys($dynamicFlowSteps) as $dynamicFlowStep) {
    $dynamicFlowApprovals[$dynamicFlowStep] = inspection_latest_approval($dynamicApprovals, $dynamicFlowStep);
}
$dynamicDefaultItems = [
    ['category' => 'งานโครงสร้างและสถาปัตย์', 'title' => 'งานดำเนินการครบตาม BOQ', 'detail' => '', 'contract_ref' => '', 'acceptance_criteria' => '', 'is_required' => 1],
    ['category' => 'งานโครงสร้างและสถาปัตย์', 'title' => 'งานเป็นไปตามแบบ', 'detail' => '', 'contract_ref' => '', 'acceptance_criteria' => '', 'is_required' => 1],
    ['category' => 'งานโครงสร้างและสถาปัตย์', 'title' => 'วัสดุเป็นไปตาม Spec', 'detail' => '', 'contract_ref' => '', 'acceptance_criteria' => '', 'is_required' => 1],
    ['category' => 'งานโครงสร้างและสถาปัตย์', 'title' => 'ปริมาณงานถูกต้อง', 'detail' => '', 'contract_ref' => '', 'acceptance_criteria' => '', 'is_required' => 1],
    ['category' => 'งานโครงสร้างและสถาปัตย์', 'title' => 'งานแล้วเสร็จตามงวด', 'detail' => '', 'contract_ref' => '', 'acceptance_criteria' => '', 'is_required' => 1],
    ['category' => 'งานโครงสร้างและสถาปัตย์', 'title' => 'งานเป็นไปตามสัญญาจ้าง', 'detail' => '', 'contract_ref' => '', 'acceptance_criteria' => '', 'is_required' => 1],
];
$dynamicUsers = inspection_fetch_all($conn, "SELECT id, username, name, role FROM users WHERE role <> 'viewer' ORDER BY name, id");
$dynamicAssignmentDefaults = inspection_default_assignment_values($dynamicUsers);
$dynamicHistory = inspection_fetch_all(
    $conn,
    "SELECT r.id, r.round_no, r.status, r.inspection_date, r.created_at,
            COUNT(DISTINCT CASE WHEN rr.result_status IN ('fail','conditional_pass') THEN rr.checklist_item_id END) AS issue_count
     FROM inspection_rounds r
     LEFT JOIN inspection_round_results rr ON rr.round_id = r.id
     WHERE r.milestone_id = ?
     GROUP BY r.id
     ORDER BY r.round_no DESC",
    'i',
    [$milestone_id]
);
$dynamicResultsById = [];
foreach ($dynamicResults as $result) {
    $dynamicResultsById[(int)$result['id']] = $result;
}
$dynamicUserOptions = function ($selected) use ($dynamicUsers) {
    $html = '<option value="">-- ยังไม่กำหนด --</option>';
    foreach ($dynamicUsers as $user) {
        $isSelected = (int)$selected === (int)$user['id'] ? ' selected' : '';
        $html .= '<option value="' . (int)$user['id'] . '"' . $isSelected . '>' . inspection_h($user['name']) . ' (' . inspection_h($user['role']) . ')</option>';
    }
    return $html;
};
$dynamicAssignmentValues = $dynamicAssignmentDefaults;
if ($dynamicChecklist) {
    foreach (array_keys($dynamicAssignmentValues) as $dynamicAssignmentField) {
        $dynamicAssignmentValues[$dynamicAssignmentField] = (int)($dynamicChecklist[$dynamicAssignmentField] ?? 0);
    }
}
?>

<div class="max-w-6xl mx-auto pb-20">
    <div id="inspectionCockpitHeader" class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-[2px] text-indigo-500 mb-2">
                <i class="fas fa-file-signature"></i> Contract Inspection Workflow
            </div>
            <h2 class="text-2xl md:text-3xl font-black text-slate-800">ตรวจรับงานตามสัญญา</h2>
            <p class="text-slate-500 mt-1">
                โครงการ: <span class="text-indigo-600 font-bold"><?= inspection_h($dynamicProject['project_name'] ?? '') ?></span>
                <span class="mx-2 text-slate-300">|</span>
                งวด: <span class="text-emerald-600 font-bold"><?= inspection_h($dynamicProject['milestone_name'] ?? '') ?></span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="detail_project.php?id=<?= (int)$project_id ?>" class="bg-slate-100 text-slate-600 px-4 py-2 rounded-xl hover:bg-slate-200 transition-all font-bold">
                <i class="fas fa-arrow-left mr-1"></i> กลับโครงการ
            </a>
            <?php if ($dynamicCanEditChecklist): ?>
                <a href="add_inspection.php?project_id=<?= (int)$project_id ?>&milestone_id=<?= (int)$milestone_id ?>&edit_checklist=1" class="bg-indigo-50 text-indigo-700 px-4 py-2 rounded-xl hover:bg-indigo-100 transition-all font-bold">
                    <i class="fas fa-pen mr-1"></i> แก้ไขรายการตรวจ
                </a>
            <?php endif; ?>
            <?php if ($dynamicRound && $dynamicChecklistPassed): ?>
                <a href="view_inspection.php?round_id=<?= (int)$dynamicRound['id'] ?>" class="bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition-all font-bold">
                    <i class="fas fa-file-pdf mr-1"></i> ดูเอกสาร
                </a>
            <?php elseif ($dynamicRound): ?>
                <span class="inline-flex items-center rounded-xl bg-amber-50 px-4 py-2 text-sm font-bold text-amber-700" title="ต้องตรวจ Checklist ให้ผ่านก่อน">
                    <i class="fas fa-lock mr-1"></i> เอกสารจะเปิดหลัง Checklist ผ่าน
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm">
            <p class="text-[10px] uppercase tracking-widest text-slate-400 font-black">มูลค่างวด</p>
            <p class="text-xl font-black text-slate-800 mt-1"><?= number_format((float)($dynamicProject['milestone_amount'] ?? 0), 2) ?> บาท</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm">
            <p class="text-[10px] uppercase tracking-widest text-slate-400 font-black">ผู้รับจ้าง</p>
            <p class="font-bold text-slate-700 mt-1 truncate"><?= inspection_h($dynamicProject['supplier_name'] ?: ($dynamicProject['contractor_name'] ?? '-')) ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm">
            <p class="text-[10px] uppercase tracking-widest text-slate-400 font-black">รอบตรวจล่าสุด</p>
            <p class="text-xl font-black text-indigo-600 mt-1"><?= $dynamicRound ? 'รอบที่ ' . (int)$dynamicRound['round_no'] : '-' ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm">
            <p class="text-[10px] uppercase tracking-widest text-slate-400 font-black">ขั้นตอนปัจจุบัน</p>
            <p class="font-black text-amber-600 mt-1"><?= inspection_h($dynamicNextStepLabel) ?></p>
        </div>
    </div>

    <?php if ($dynamicChecklist): ?>
        <?php if ($dynamicNextStep === 'revision'): ?>
            <div class="mb-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900" role="status">
                <i class="fas fa-rotate-right mt-0.5 text-amber-600"></i>
                <div><p class="font-black">มีรายการต้องแก้ไขก่อนดำเนินการต่อ</p><p class="mt-0.5 text-sm text-amber-800">ระบบจะเปิดรอบตรวจแก้ไขใหม่และเก็บประวัติรอบเดิมไว้</p></div>
            </div>
        <?php endif; ?>
        <section id="inspectionFlowStepper" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 md:p-5" aria-label="ลำดับการตรวจรับ">
            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="text-xs font-black uppercase tracking-[1.5px] text-slate-400">ขั้นตอนการตรวจรับ</p><h3 class="text-lg font-black text-slate-800">ผู้รับผิดชอบตาม Flow</h3></div>
                <p class="text-xs font-bold text-slate-500">ขั้นตอนปัจจุบัน: <span class="text-indigo-600"><?= inspection_h($dynamicNextStepLabel) ?></span></p>
            </div>
            <div class="flex gap-2 overflow-x-auto pb-1" role="list">
                <?php foreach ($dynamicFlowSteps as $dynamicFlowStep => $dynamicFlowConfig): ?>
                    <?php
                    $dynamicFlowApproval = $dynamicFlowApprovals[$dynamicFlowStep] ?? null;
                    $dynamicFlowApproved = $dynamicFlowApproval && ($dynamicFlowApproval['action'] ?? '') === 'approve';
                    $dynamicFlowCurrent = $dynamicNextStep === $dynamicFlowStep;
                    $dynamicFlowState = $dynamicFlowApproved ? 'completed' : ($dynamicFlowCurrent ? 'current' : 'locked');
                    $dynamicFlowStateClasses = [
                        'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                        'current' => 'border-indigo-200 bg-indigo-50 text-indigo-800',
                        'locked' => 'border-slate-200 bg-slate-50 text-slate-500',
                    ];
                    $dynamicFlowIconClasses = [
                        'completed' => 'bg-emerald-600 text-white',
                        'current' => 'bg-indigo-600 text-white',
                        'locked' => 'bg-slate-200 text-slate-500',
                    ];
                    $dynamicFlowAssignee = trim((string)($dynamicChecklist[$dynamicFlowConfig['assignment']] ?? '')) ?: 'ยังไม่กำหนด';
                    $dynamicFlowApprovedBy = trim((string)($dynamicFlowApproval['user_name_snapshot'] ?? '')) ?: $dynamicFlowAssignee;
                    $dynamicFlowTime = $dynamicFlowApproval['created_at'] ?? '';
                    ?>
                    <div class="min-w-[150px] flex-1 rounded-xl border px-3 py-3 <?= $dynamicFlowStateClasses[$dynamicFlowState] ?>" role="listitem" aria-current="<?= $dynamicFlowCurrent ? 'step' : 'false' ?>">
                        <div class="flex items-center gap-2"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg <?= $dynamicFlowIconClasses[$dynamicFlowState] ?>"><i class="fas <?= inspection_h($dynamicFlowConfig['icon']) ?> text-xs"></i></span><span class="text-sm font-black"><?= inspection_h($dynamicFlowConfig['label']) ?></span></div>
                        <p class="mt-2 truncate text-xs font-bold" title="<?= inspection_h($dynamicFlowState === 'completed' ? $dynamicFlowApprovedBy : $dynamicFlowAssignee) ?>"><?= inspection_h($dynamicFlowState === 'completed' ? $dynamicFlowApprovedBy : $dynamicFlowAssignee) ?></p>
                        <p class="mt-0.5 text-[11px] font-medium"><?= $dynamicFlowState === 'completed' ? inspection_h($dynamicFlowTime ?: 'ยืนยันแล้ว') : ($dynamicFlowState === 'current' ? 'กำลังดำเนินการ' : 'รอขั้นตอนก่อนหน้า') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!$dynamicChecklist || $dynamicEditMode): ?>
        <form id="dynamicChecklistForm" class="space-y-6 pb-40 sm:pb-28">
            <input type="hidden" name="project_id" value="<?= (int)$project_id ?>">
            <input type="hidden" name="milestone_id" value="<?= (int)$milestone_id ?>">
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-4 sm:p-6">
                <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 mb-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-black text-slate-800"><i class="fas fa-list-check text-indigo-500 mr-2"></i><?= $dynamicEditMode ? 'แก้ไขรายการตรวจตามสัญญา' : 'กำหนด Checklist จากสัญญา' ?></h3>
                        <p class="text-sm text-slate-500 mt-1">ใส่เกณฑ์ที่ต้องตรวจของงวดนี้ เช่น ข้อสัญญา BOQ หรือ Scope of Work<?= $dynamicEditMode ? ' · แก้ไขได้เฉพาะก่อนเริ่มรอบตรวจ' : '' ?></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span id="checklistRowCount" class="rounded-full bg-slate-100 px-3 py-2 text-xs font-black text-slate-600" aria-live="polite">0 รายการ</span>
                        <button type="button" id="addChecklistRow" class="inline-flex min-h-11 items-center rounded-xl bg-indigo-600 px-4 py-2.5 font-black text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"><i class="fas fa-plus mr-2"></i>เพิ่มรายการ</button>
                    </div>
                </div>
                <div id="checklistRows" class="space-y-4"></div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6">
                <h3 class="text-lg font-black text-slate-800 border-b border-slate-100 pb-4 mb-5"><i class="fas fa-users text-emerald-500 mr-2"></i>ผู้รับผิดชอบ</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="text-sm font-bold text-slate-700">ผู้ตรวจรับ 1<select name="inspector_1_user_id" class="mt-2 w-full bg-slate-50 border-0 rounded-xl px-3 py-3"><?= $dynamicUserOptions($dynamicAssignmentValues['inspector_1_user_id']) ?></select></label>
                    <label class="text-sm font-bold text-slate-700">ผู้ตรวจรับ 2<select name="inspector_2_user_id" class="mt-2 w-full bg-slate-50 border-0 rounded-xl px-3 py-3"><?= $dynamicUserOptions($dynamicAssignmentValues['inspector_2_user_id']) ?></select></label>
                    <label class="text-sm font-bold text-slate-700">เจ้าหน้าที่จัดซื้อ<select name="procurement_user_id" class="mt-2 w-full bg-slate-50 border-0 rounded-xl px-3 py-3"><?= $dynamicUserOptions($dynamicAssignmentValues['procurement_user_id']) ?></select></label>
                    <label class="text-sm font-bold text-slate-700">ผู้อนุมัติ MD<select name="md_user_id" class="mt-2 w-full bg-slate-50 border-0 rounded-xl px-3 py-3"><?= $dynamicUserOptions($dynamicAssignmentValues['md_user_id']) ?></select></label>
                    <label class="text-sm font-bold text-slate-700 md:col-span-2">GMACC (หัวหน้าบัญชี)<select name="gmacc_user_id" class="mt-2 w-full md:w-1/2 bg-slate-50 border-0 rounded-xl px-3 py-3"><?= $dynamicUserOptions($dynamicAssignmentValues['gmacc_user_id']) ?></select></label>
                </div>
            </div>
            <div class="sticky bottom-2 z-20 flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur-sm md:bottom-3 md:flex-row md:items-center md:justify-between">
                <span id="checklistDirtyState" class="px-3 text-xs font-bold text-slate-500" aria-live="polite">ยังไม่มีการแก้ไข</span>
                <div class="flex flex-col gap-2 sm:flex-row md:shrink-0">
                <button type="submit" id="checklistSubmitButton" class="min-h-12 flex-1 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:text-base"><i class="fas fa-save mr-2"></i><?= $dynamicEditMode ? 'บันทึกการแก้ไข Checklist' : 'บันทึก Checklist และเริ่มเตรียมตรวจ' ?></button>
                <?php if ($dynamicEditMode): ?><a href="add_inspection.php?project_id=<?= (int)$project_id ?>&milestone_id=<?= (int)$milestone_id ?>" class="flex min-h-12 items-center justify-center rounded-xl bg-slate-100 px-5 py-3 text-sm font-black text-slate-600 hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2 sm:text-base">ยกเลิก</a><?php endif; ?>
                </div>
            </div>
        </form>
    <?php elseif (!$dynamicRound): ?>
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-8 text-center">
            <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4"><i class="fas fa-clipboard-check text-3xl"></i></div>
            <h3 class="text-xl font-black text-slate-800">Checklist พร้อมตรวจแล้ว</h3>
            <p class="text-slate-500 mt-2">มี <?= count($dynamicItems) ?> รายการตรวจใน Checklist รุ่น <?= (int)$dynamicChecklist['version'] ?></p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <button type="button" id="startRoundButton" class="bg-emerald-600 text-white px-6 py-3 rounded-xl font-black hover:bg-emerald-700"><i class="fas fa-play mr-2"></i>เริ่มตรวจรอบที่ 1</button>
                <?php if ($dynamicCanManageChecklist): ?><a href="add_inspection.php?project_id=<?= (int)$project_id ?>&milestone_id=<?= (int)$milestone_id ?>&edit_checklist=1" class="bg-indigo-50 text-indigo-700 px-6 py-3 rounded-xl font-black hover:bg-indigo-100"><i class="fas fa-pen mr-2"></i>แก้ไขรายการตรวจ</a><?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <?php
        $isInspectorStep = in_array($dynamicNextStep, ['inspector_1', 'inspector_2'], true);
        $dynamicCanEditResults = $isInspectorStep && ($dynamicRole === 'admin' || inspection_can_act($dynamicChecklist ?: [], $dynamicNextStep, inspection_current_user_id(), $dynamicRole));
        ?>
        <div id="inspectionResultsSummary" class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h3 class="text-lg font-black text-slate-800">รอบตรวจที่ <?= (int)$dynamicRound['round_no'] ?></h3>
                    <p class="text-sm text-slate-500 mt-1">สถานะ: <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-black text-indigo-700"><?= inspection_h($dynamicNextStepLabel) ?></span></p>
                </div>
                <div class="text-right text-sm text-slate-500">ตรวจวันที่ <?= inspection_h($dynamicRound['inspection_date']) ?><br><span class="font-bold text-slate-700">ขั้นตอน: <?= inspection_h($dynamicNextStepLabel) ?></span></div>
            </div>
            <div class="h-3 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="ความคืบหน้าการตรวจ"><div id="inspectionProgress" class="h-full rounded-full bg-indigo-500 transition-all" style="width:0%" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div></div>
            <p id="inspectionProgressText" class="text-xs font-bold text-slate-500 mt-2">กำลังโหลดความคืบหน้า...</p>
        </div>

        <?php if (in_array($dynamicNextStep, ['procurement', 'md', 'gmacc'], true)): ?>
            <?php
            $dynamicComparisonReviewLabel = [
                'procurement' => 'Procurement Review',
                'md' => 'MD Review',
                'gmacc' => 'GMACC Review',
            ][$dynamicNextStep];
            $dynamicConflictCount = count(array_filter($dynamicComparisonSummary['items'], static function (array $item): bool {
                return !empty($item['conflict']);
            }));
            $comparisonStatusMeta = static function (string $status): array {
                return [
                    'pass' => ['label' => 'ผ่าน', 'class' => 'bg-emerald-100 text-emerald-800', 'icon' => 'fa-check'],
                    'fail' => ['label' => 'ไม่ผ่าน', 'class' => 'bg-rose-100 text-rose-800', 'icon' => 'fa-xmark'],
                    'conditional_pass' => ['label' => 'มีเงื่อนไข', 'class' => 'bg-amber-100 text-amber-800', 'icon' => 'fa-triangle-exclamation'],
                    'not_applicable' => ['label' => 'ไม่เกี่ยวข้อง', 'class' => 'bg-slate-200 text-slate-800', 'icon' => 'fa-minus'],
                    '' => ['label' => 'ยังไม่ระบุ', 'class' => 'bg-slate-100 text-slate-500', 'icon' => 'fa-clock'],
                ][$status] ?? ['label' => 'ยังไม่ระบุ', 'class' => 'bg-slate-100 text-slate-500', 'icon' => 'fa-clock'];
            };
            ?>
            <section id="procurementDecisionPanel" class="mb-6 rounded-3xl border border-indigo-100 bg-indigo-50/60 p-5 md:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[1.5px] text-indigo-500"><?= inspection_h($dynamicComparisonReviewLabel) ?></p>
                        <h3 class="mt-1 text-xl font-black text-slate-900">เปรียบเทียบผลตรวจผู้ตรวจรับ 1 และ 2</h3>
                        <p class="mt-1 text-sm text-slate-600">ผลของผู้ตรวจทั้งสองคนถูกเก็บแยกกัน ผู้อนุมัติสามารถตรวจสอบผล หมายเหตุ และรูปหลักฐานได้โดยไม่แก้ทับผลเดิม</p>
                    </div>
                    <span class="inline-flex items-center rounded-full <?= $dynamicComparisonSummary['passed'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' ?> px-3 py-1.5 text-xs font-black">
                        <?= $dynamicComparisonSummary['passed'] ? 'ผลตรงกันและผ่าน' : ($dynamicComparisonSummary['has_conflict'] ? 'ผลตรวจไม่ตรงกัน' : 'มีรายการต้องพิจารณา') ?>
                    </span>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2" role="group" aria-label="กรองผลเปรียบเทียบ">
                    <button type="button" data-comparison-filter="all" aria-pressed="true" class="comparison-filter rounded-xl bg-slate-800 px-3 py-2 text-xs font-black text-white transition focus:outline-none focus:ring-2 focus:ring-slate-500">ทั้งหมด <?= (int)$dynamicComparisonSummary['item_count'] ?></button>
                    <button type="button" data-comparison-filter="conflict" aria-pressed="false" class="comparison-filter rounded-xl bg-white px-3 py-2 text-xs font-black text-slate-600 transition focus:outline-none focus:ring-2 focus:ring-rose-400">เฉพาะผลต่าง <?= $dynamicConflictCount ?></button>
                </div>

                <div class="mt-4 hidden overflow-x-auto rounded-2xl border border-indigo-100 bg-white md:block">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-black text-slate-500">
                            <tr><th class="w-[28%] px-4 py-3">รายการตรวจ</th><th class="w-[36%] px-4 py-3">ผู้ตรวจ 1 · <?= inspection_h($dynamicChecklist['inspector_1_name'] ?? '-') ?></th><th class="w-[36%] px-4 py-3">ผู้ตรวจ 2 · <?= inspection_h($dynamicChecklist['inspector_2_name'] ?? '-') ?></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($dynamicComparisonSummary['items'] as $comparisonItem): ?>
                                <?php
                                $firstResult = $comparisonItem['inspector_1'] ?? [];
                                $secondResult = $comparisonItem['inspector_2'] ?? [];
                                $firstMeta = $comparisonStatusMeta((string)$comparisonItem['inspector_1_status']);
                                $secondMeta = $comparisonStatusMeta((string)$comparisonItem['inspector_2_status']);
                                $firstPhotos = $dynamicPhotosByResult[(int)($firstResult['id'] ?? 0)] ?? [];
                                $secondPhotos = $dynamicPhotosByResult[(int)($secondResult['id'] ?? 0)] ?? [];
                                $firstPhoto = $firstPhotos ? end($firstPhotos) : null;
                                $secondPhoto = $secondPhotos ? end($secondPhotos) : null;
                                $isConflict = !empty($comparisonItem['conflict']);
                                ?>
                                <tr class="comparison-item <?= $isConflict ? 'comparison-conflict bg-rose-50/70' : 'bg-white' ?>" data-comparison-conflict="<?= $isConflict ? '1' : '0' ?>">
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex items-start justify-between gap-2"><span class="font-black text-slate-900"><?= inspection_h($firstResult['title'] ?? ($secondResult['title'] ?? '-')) ?></span><?php if ($isConflict): ?><span class="shrink-0 rounded-full bg-rose-600 px-2 py-1 text-[10px] font-black text-white">ผลต่าง</span><?php endif; ?></div>
                                        <p class="mt-1 text-xs text-slate-500"><?= inspection_h($firstResult['category'] ?? ($secondResult['category'] ?? '')) ?> · ข้อ <?= (int)($firstResult['item_order'] ?? ($secondResult['item_order'] ?? 0)) ?></p>
                                    </td>
                                    <?php foreach ([['result' => $firstResult, 'meta' => $firstMeta, 'photos' => $firstPhotos, 'photo' => $firstPhoto, 'class' => 'comparison-inspector-1'], ['result' => $secondResult, 'meta' => $secondMeta, 'photos' => $secondPhotos, 'photo' => $secondPhoto, 'class' => 'comparison-inspector-2']] as $side): ?>
                                        <td class="<?= $side['class'] ?> px-4 py-4 align-top">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-black <?= $side['meta']['class'] ?>"><i class="fas <?= $side['meta']['icon'] ?>" aria-hidden="true"></i><?= inspection_h($side['meta']['label']) ?></span>
                                            <p class="mt-2 text-xs leading-relaxed text-slate-600"><span class="font-black text-slate-500">หมายเหตุ:</span> <?= inspection_h(trim((string)($side['result']['note'] ?? '')) ?: '-') ?></p>
                                            <div class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                                                <?php if ($side['photo']): ?><a href="uploads/inspections/<?= rawurlencode(basename((string)$side['photo']['stored_name'])) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 font-bold text-indigo-700 hover:text-indigo-900"><img src="uploads/inspections/<?= rawurlencode(basename((string)$side['photo']['stored_name'])) ?>" alt="รูปหลักฐาน" class="h-10 w-10 rounded-lg border border-slate-200 object-cover">รูปหลักฐาน <?= count($side['photos']) ?> รูป</a><?php else: ?><span class="inline-flex items-center gap-1.5"><i class="fas fa-image text-slate-400" aria-hidden="true"></i>ไม่มีรูปหลักฐาน</span><?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 space-y-3 md:hidden">
                    <?php foreach ($dynamicComparisonSummary['items'] as $comparisonItem): ?>
                        <?php
                        $firstResult = $comparisonItem['inspector_1'] ?? [];
                        $secondResult = $comparisonItem['inspector_2'] ?? [];
                        $firstMeta = $comparisonStatusMeta((string)$comparisonItem['inspector_1_status']);
                        $secondMeta = $comparisonStatusMeta((string)$comparisonItem['inspector_2_status']);
                        $firstPhotos = $dynamicPhotosByResult[(int)($firstResult['id'] ?? 0)] ?? [];
                        $secondPhotos = $dynamicPhotosByResult[(int)($secondResult['id'] ?? 0)] ?? [];
                        $firstPhoto = $firstPhotos ? end($firstPhotos) : null;
                        $secondPhoto = $secondPhotos ? end($secondPhotos) : null;
                        $isConflict = !empty($comparisonItem['conflict']);
                        ?>
                        <article class="comparison-item comparison-mobile-card overflow-hidden rounded-2xl border <?= $isConflict ? 'comparison-conflict border-rose-300 bg-rose-50/70' : 'border-slate-200 bg-white' ?>" data-comparison-conflict="<?= $isConflict ? '1' : '0' ?>">
                            <header class="flex items-start justify-between gap-3 border-b <?= $isConflict ? 'border-rose-200' : 'border-slate-100' ?> p-4"><div><h4 class="font-black leading-snug text-slate-900"><?= inspection_h($firstResult['title'] ?? ($secondResult['title'] ?? '-')) ?></h4><p class="mt-1 text-xs text-slate-500"><?= inspection_h($firstResult['category'] ?? ($secondResult['category'] ?? '')) ?> · ข้อ <?= (int)($firstResult['item_order'] ?? ($secondResult['item_order'] ?? 0)) ?></p></div><?php if ($isConflict): ?><span class="shrink-0 rounded-full bg-rose-600 px-2.5 py-1 text-[10px] font-black text-white">ผลต่าง</span><?php endif; ?></header>
                            <div class="divide-y <?= $isConflict ? 'divide-rose-200' : 'divide-slate-100' ?>">
                                <?php foreach ([['label' => 'ผู้ตรวจ 1', 'name' => $dynamicChecklist['inspector_1_name'] ?? '-', 'result' => $firstResult, 'meta' => $firstMeta, 'photos' => $firstPhotos, 'photo' => $firstPhoto, 'class' => 'comparison-inspector-1'], ['label' => 'ผู้ตรวจ 2', 'name' => $dynamicChecklist['inspector_2_name'] ?? '-', 'result' => $secondResult, 'meta' => $secondMeta, 'photos' => $secondPhotos, 'photo' => $secondPhoto, 'class' => 'comparison-inspector-2']] as $side): ?>
                                    <section class="<?= $side['class'] ?> p-4">
                                        <div class="flex items-center justify-between gap-2"><p class="text-xs font-black text-slate-700"><?= inspection_h($side['label']) ?> · <?= inspection_h($side['name']) ?></p><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-black <?= $side['meta']['class'] ?>"><i class="fas <?= $side['meta']['icon'] ?>" aria-hidden="true"></i><?= inspection_h($side['meta']['label']) ?></span></div>
                                        <p class="mt-2 text-xs leading-relaxed text-slate-600"><span class="font-black">หมายเหตุ:</span> <?= inspection_h(trim((string)($side['result']['note'] ?? '')) ?: '-') ?></p>
                                        <div class="mt-3"><?php if ($side['photo']): ?><a href="uploads/inspections/<?= rawurlencode(basename((string)$side['photo']['stored_name'])) ?>" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-indigo-200 bg-white px-2.5 py-2 text-xs font-black text-indigo-700"><img src="uploads/inspections/<?= rawurlencode(basename((string)$side['photo']['stored_name'])) ?>" alt="รูปหลักฐาน" class="h-9 w-9 rounded-lg object-cover">ดูรูป <?= count($side['photos']) ?> รูป</a><?php else: ?><span class="inline-flex min-h-11 items-center gap-2 text-xs text-slate-500"><i class="fas fa-image" aria-hidden="true"></i>ไม่มีรูปหลักฐาน</span><?php endif; ?></div>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section id="inspectionChecklistWorkspace" class="relative">
        <form id="dynamicRoundForm" class="space-y-5" enctype="multipart/form-data">
            <input type="hidden" name="round_id" value="<?= (int)$dynamicRound['id'] ?>">
            <div id="inspectionChecklistToolbar" class="mb-3 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-xs font-black uppercase tracking-[1.5px] text-slate-400">Checklist</p><p class="text-sm font-bold text-slate-700">แตะผลตรวจเพื่อบันทึกสถานะรายข้อ</p></div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="result-filter active rounded-xl bg-slate-800 px-3 py-2 text-xs font-black text-white" data-filter="all">ทั้งหมด</button>
                    <button type="button" class="result-filter rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-600" data-filter="open">รอตรวจ/ไม่ผ่าน</button>
                    <button type="button" class="result-filter rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-600" data-filter="pass">ผ่านแล้ว</button>
                </div>
            </div>
            <div id="dynamicResults" class="space-y-4">
                <?php foreach ($dynamicResults as $result): ?>
                    <?php
                    $resultStatus = (string)($result['result_status'] ?? '');
                    $resultPhotos = $dynamicPhotosByResult[(int)$result['id']] ?? [];
                    $resultPhotoCount = count($resultPhotos);
                    $resultPhoto = $resultPhotos ? end($resultPhotos) : null;
                    $hasIssue = in_array($resultStatus, ['fail', 'conditional_pass'], true);
                    $hasDetails = $hasIssue || !empty($result['note']) || !empty($result['responsible_person']) || !empty($result['due_date']) || !empty($result['correction_note']);
                    $hasContractContext = !empty($result['detail']) || !empty($result['contract_ref']) || !empty($result['acceptance_criteria']) || !empty($result['correction_note']);
                    $cardGridClass = $hasContractContext
                        ? 'lg:grid-cols-[220px_minmax(0,1fr)_340px] lg:gap-5 xl:grid-cols-[230px_minmax(0,1fr)_360px] xl:gap-7'
                        : 'lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-5 xl:grid-cols-[minmax(0,1fr)_360px] xl:gap-7';
                    // Keep the expandable follow-up fields aligned to the full card width.
                    // Starting at column 2 left an empty block under the item title on desktop.
                    $detailsPlacementClass = 'lg:col-span-full';
                    $statusButtonClasses = [
                        'pass' => 'border-emerald-600 bg-emerald-600 text-white ring-2 ring-emerald-200',
                        'fail' => 'border-rose-600 bg-rose-600 text-white ring-2 ring-rose-200',
                        'conditional_pass' => 'border-amber-500 bg-amber-500 text-white ring-2 ring-amber-200',
                        'not_applicable' => 'border-slate-600 bg-slate-600 text-white ring-2 ring-slate-200',
                    ];
                    $statusInactiveClasses = [
                        'pass' => 'border-emerald-300 bg-emerald-50 text-emerald-700',
                        'fail' => 'border-rose-300 bg-rose-50 text-rose-700',
                        'conditional_pass' => 'border-amber-300 bg-amber-50 text-amber-700',
                        'not_applicable' => 'border-slate-300 bg-slate-100 text-slate-700',
                    ];
                    ?>
                    <div class="result-row bg-white rounded-2xl border <?= $resultStatus === 'fail' ? 'border-rose-200' : ($resultStatus === 'conditional_pass' ? 'border-amber-200' : 'border-slate-200') ?> p-4 md:p-5 lg:p-4 xl:p-5 transition-colors" data-result-id="<?= (int)$result['id'] ?>" data-result-status="<?= inspection_h($resultStatus ?: 'open') ?>" data-photo-count="<?= $resultPhotoCount ?>" data-photo-uploading="false">
                        <div class="grid grid-cols-1 items-start gap-4 <?= $cardGridClass ?>">
                            <div class="min-w-0 lg:pt-1">
                                <div class="flex items-center gap-2 lg:flex-col lg:items-start lg:gap-2">
                                    <span class="inline-flex max-w-full items-center rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-black text-indigo-700"><?= inspection_h($result['category']) ?></span>
                                    <span class="text-xs font-bold text-slate-400">ข้อ <?= (int)$result['item_order'] ?></span>
                                </div>
                                <h4 class="mt-2 font-black text-slate-900 text-base md:text-lg leading-snug"><?= inspection_h($result['title']) ?></h4>
                            </div>
                            <?php if ($hasContractContext): ?>
                                <div class="min-w-0">
                                    <p class="mb-2 text-[11px] font-black uppercase tracking-[1.2px] text-slate-500">รายละเอียดและเกณฑ์ตรวจ</p>
                                    <?php if (!empty($result['detail'])): ?><p class="text-sm text-slate-600 leading-relaxed"><?= nl2br(inspection_h($result['detail'])) ?></p><?php endif; ?>
                                    <?php if (!empty($result['contract_ref']) || !empty($result['acceptance_criteria'])): ?><div class="mt-3 rounded-xl border border-indigo-100 bg-indigo-50/70 p-3 text-xs leading-relaxed text-indigo-800"><b>อ้างอิง:</b> <?= inspection_h($result['contract_ref'] ?: '-') ?><?php if (!empty($result['acceptance_criteria'])): ?><br><b>เกณฑ์ผ่าน:</b> <?= nl2br(inspection_h($result['acceptance_criteria'])) ?><?php endif; ?></div><?php endif; ?>
                                    <?php if (!empty($result['correction_note'])): ?><div class="mt-3 rounded-xl border border-amber-100 bg-amber-50 p-3 text-xs leading-relaxed text-amber-800"><b>หมายเหตุจากรอบก่อน:</b> <?= nl2br(inspection_h($result['correction_note'])) ?></div><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="w-full lg:border-l lg:border-slate-100 lg:pl-5 xl:pl-6">
                                <div class="mb-2 flex items-center justify-between"><span class="text-xs font-bold text-slate-500">ผลตรวจ</span><span class="result-status-label text-xs font-black text-slate-500"><?= $resultStatus === 'pass' ? 'ผ่าน' : ($resultStatus === 'fail' ? 'ไม่ผ่าน' : ($resultStatus === 'conditional_pass' ? 'มีเงื่อนไข' : ($resultStatus === 'not_applicable' ? 'ไม่เกี่ยวข้อง' : 'ยังไม่ระบุ'))) ?></span></div>
                                <div class="quick-status grid grid-cols-4 gap-1.5 sm:gap-2" role="group" aria-label="เลือกผลตรวจ">
                                    <button type="button" class="status-choice status-choice-pass group flex min-h-11 min-w-0 flex-col items-center gap-1.5 rounded-lg px-0.5 py-1 text-[11px] font-bold text-emerald-700 transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60" data-value="pass" aria-label="ผ่าน" aria-pressed="<?= $resultStatus === 'pass' ? 'true' : 'false' ?>" <?= $dynamicCanEditResults ? '' : 'disabled' ?>><span class="status-choice-circle flex h-12 w-12 items-center justify-center rounded-full border-2 text-lg transition sm:h-14 sm:w-14 sm:text-xl <?= $resultStatus === 'pass' ? $statusButtonClasses['pass'] : $statusInactiveClasses['pass'] ?>"><i class="fas fa-check" aria-hidden="true"></i></span><span>ผ่าน</span></button>
                                    <button type="button" class="status-choice status-choice-fail group flex min-h-11 min-w-0 flex-col items-center gap-1.5 rounded-lg px-0.5 py-1 text-[11px] font-bold text-rose-700 transition focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60" data-value="fail" aria-label="ไม่ผ่าน" aria-pressed="<?= $resultStatus === 'fail' ? 'true' : 'false' ?>" <?= $dynamicCanEditResults ? '' : 'disabled' ?>><span class="status-choice-circle flex h-12 w-12 items-center justify-center rounded-full border-2 text-lg transition sm:h-14 sm:w-14 sm:text-xl <?= $resultStatus === 'fail' ? $statusButtonClasses['fail'] : $statusInactiveClasses['fail'] ?>"><i class="fas fa-xmark" aria-hidden="true"></i></span><span>ไม่ผ่าน</span></button>
                                    <button type="button" class="status-choice status-choice-conditional group flex min-h-11 min-w-0 flex-col items-center gap-1.5 rounded-lg px-0.5 py-1 text-[11px] font-bold text-amber-700 transition focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60" data-value="conditional_pass" aria-label="มีเงื่อนไข" aria-pressed="<?= $resultStatus === 'conditional_pass' ? 'true' : 'false' ?>" <?= $dynamicCanEditResults ? '' : 'disabled' ?>><span class="status-choice-circle flex h-12 w-12 items-center justify-center rounded-full border-2 text-lg transition sm:h-14 sm:w-14 sm:text-xl <?= $resultStatus === 'conditional_pass' ? $statusButtonClasses['conditional_pass'] : $statusInactiveClasses['conditional_pass'] ?>"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i></span><span>มีเงื่อนไข</span></button>
                                    <button type="button" class="status-choice status-choice-na group flex min-h-11 min-w-0 flex-col items-center gap-1.5 rounded-lg px-0.5 py-1 text-[11px] font-bold text-slate-700 transition focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60" data-value="not_applicable" aria-label="ไม่เกี่ยวข้อง" aria-pressed="<?= $resultStatus === 'not_applicable' ? 'true' : 'false' ?>" <?= $dynamicCanEditResults ? '' : 'disabled' ?>><span class="status-choice-circle flex h-12 w-12 items-center justify-center rounded-full border-2 text-lg transition sm:h-14 sm:w-14 sm:text-xl <?= $resultStatus === 'not_applicable' ? $statusButtonClasses['not_applicable'] : $statusInactiveClasses['not_applicable'] ?>"><i class="fas fa-minus" aria-hidden="true"></i></span><span>ไม่เกี่ยวข้อง</span></button>
                                </div>
                                <select class="result-status sr-only" <?= $dynamicCanEditResults ? '' : 'disabled' ?>><option value="">-- เลือกผลตรวจ --</option><option value="pass" <?= $resultStatus === 'pass' ? 'selected' : '' ?>>ผ่าน</option><option value="fail" <?= $resultStatus === 'fail' ? 'selected' : '' ?>>ไม่ผ่าน</option><option value="conditional_pass" <?= $resultStatus === 'conditional_pass' ? 'selected' : '' ?>>ผ่านแบบมีเงื่อนไข</option><option value="not_applicable" <?= $resultStatus === 'not_applicable' ? 'selected' : '' ?>>ไม่เกี่ยวข้อง</option></select>
                                <div class="result-photo-state mt-4 rounded-xl border <?= $resultPhotoCount > 0 ? 'border-emerald-200 bg-emerald-50/70' : 'border-slate-200 bg-slate-50' ?> p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="result-photo-preview flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white text-slate-400">
                                            <?php if ($resultPhoto): ?><img src="uploads/inspections/<?= rawurlencode(basename((string)$resultPhoto['stored_name'])) ?>" alt="รูปหลักฐานข้อ <?= (int)$result['item_order'] ?>" class="h-full w-full object-cover"><?php else: ?><i class="fas fa-camera text-xl" aria-hidden="true"></i><?php endif; ?>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="result-photo-message text-xs font-black <?= $resultPhotoCount > 0 ? 'text-emerald-700' : 'text-slate-700' ?>"><?= $resultPhotoCount > 0 ? 'แนบรูปแล้ว ' . $resultPhotoCount . ' รูป' : ($resultStatus === 'not_applicable' ? 'ข้อไม่เกี่ยวข้อง ไม่บังคับแนบรูป' : 'ต้องแนบรูปอย่างน้อย 1 รูป') ?></p>
                                            <p class="result-photo-required mt-0.5 text-[11px] text-slate-500 <?= $resultStatus === 'not_applicable' ? 'hidden' : '' ?>">ใช้เป็นหลักฐานก่อนบันทึกผลตรวจ</p>
                                        </div>
                                        <?php if ($dynamicCanEditResults): ?>
                                            <label class="inline-flex min-h-11 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-indigo-600 px-3 py-2 text-xs font-black text-white transition hover:bg-indigo-700 focus-within:ring-2 focus-within:ring-indigo-400 focus-within:ring-offset-2"><i class="fas fa-camera mr-1.5" aria-hidden="true"></i><span class="hidden sm:inline">ถ่ายรูป</span><span class="sm:hidden">รูป</span><input type="file" class="result-file sr-only" accept="image/*" capture="environment" data-result-id="<?= (int)$result['id'] ?>"></label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <details class="result-more mt-1 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 <?= $detailsPlacementClass ?>" <?= $hasDetails ? 'open' : '' ?>><summary class="flex cursor-pointer items-center justify-between gap-3 text-xs font-bold text-slate-600"><span class="inline-flex items-center gap-2"><i class="fas fa-sliders-h text-indigo-500" aria-hidden="true"></i>รายละเอียดเพิ่มเติม</span><span class="rounded-full bg-white px-2 py-1 text-[11px] font-bold text-slate-500"><?= $hasDetails ? 'มีข้อมูล' : 'ยังไม่มีข้อมูล' ?></span></summary><div class="grid grid-cols-1 gap-2 pt-3 md:grid-cols-2 lg:grid-cols-3"><textarea class="result-note min-h-20 w-full resize-y rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm md:col-span-2" rows="2" placeholder="หมายเหตุ / เหตุผล" <?= $dynamicCanEditResults ? '' : 'disabled' ?>><?= inspection_h($result['note'] ?? '') ?></textarea><input class="result-responsible w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="ผู้รับผิดชอบแก้ไข" value="<?= inspection_h($result['responsible_person'] ?? '') ?>" <?= $dynamicCanEditResults ? '' : 'disabled' ?> /><input type="date" class="result-due w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" value="<?= inspection_h($result['due_date'] ?? '') ?>" <?= $dynamicCanEditResults ? '' : 'disabled' ?> /></div></details>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6">
                <div class="space-y-4">
                    <label class="text-sm font-bold text-slate-700">Punch List / รายการแก้ไข<textarea id="roundPunchList" rows="2" class="mt-2 min-h-20 max-h-36 w-full resize-y bg-slate-50 border-0 rounded-xl px-3 py-2.5 text-sm" <?= $dynamicCanEditResults ? '' : 'disabled' ?>><?= inspection_h($dynamicRound['punch_list'] ?? '') ?></textarea></label>
                    <label class="block max-w-sm text-sm font-bold text-slate-700">กำหนดแก้ไขภายใน (วัน)<span class="mt-2 flex items-center gap-1 rounded-xl bg-slate-50 p-1"><button type="button" id="roundFixDaysMinus" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-lg font-black text-slate-600 shadow-sm transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50" aria-label="ลดจำนวนวัน" <?= $dynamicCanEditResults ? '' : 'disabled' ?>>−</button><input id="roundFixDays" type="number" min="0" step="1" inputmode="numeric" class="h-10 w-full appearance-none border-0 bg-transparent px-2 text-center text-base font-black text-slate-800 outline-none focus:ring-0" value="<?= (int)($dynamicRound['fix_within_days'] ?? 0) ?>" <?= $dynamicCanEditResults ? '' : 'disabled' ?>><button type="button" id="roundFixDaysPlus" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-lg font-black text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50" aria-label="เพิ่มจำนวนวัน" <?= $dynamicCanEditResults ? '' : 'disabled' ?>>+</button></span></label>
                </div>
            </div>
            <?php if ($dynamicCanEditResults): ?>
                <div id="inspectionActionBar" class="sticky bottom-3 z-10 flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm md:static md:flex-row md:rounded-none md:border-0 md:bg-transparent md:p-0 md:shadow-none"><button type="button" id="saveRoundButton" class="flex-1 rounded-xl bg-slate-800 py-3.5 font-black text-white transition hover:bg-slate-950 focus:outline-none focus:ring-2 focus:ring-slate-400"><i class="fas fa-save mr-2"></i>บันทึกร่าง</button><button type="button" id="approveRoundButton" class="flex-1 rounded-xl bg-indigo-600 py-3.5 font-black text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400"><i class="fas fa-check mr-2"></i>ยืนยัน <?= inspection_h($dynamicNextStepLabel) ?></button></div>
            <?php elseif ($isInspectorStep): ?>
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 text-center"><p class="font-black text-slate-800">รอผู้ตรวจรับที่ได้รับมอบหมาย</p><p class="text-sm text-slate-500 mt-1">หน้านี้แสดงผลแบบอ่านอย่างเดียว ผู้ตรวจรับตามรายชื่อจะเป็นผู้กรอกผลตรวจ</p></div>
            <?php elseif ($dynamicNextStep === 'revision'): ?>
                <div id="inspectionActionBar" class="sticky bottom-3 z-10"><button type="button" id="revisionButton" class="w-full rounded-xl bg-amber-500 py-3.5 font-black text-white transition hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400"><i class="fas fa-rotate-right mr-2"></i>เริ่มตรวจงานแก้รอบใหม่</button></div>
            <?php elseif ($dynamicNextStep !== 'completed' && $dynamicCanActCurrentStep): ?>
                <div id="inspectionActionBar" class="sticky bottom-3 z-10 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm"><p class="font-black text-amber-900">รอการยืนยันขั้นตอน: <?= inspection_h($dynamicNextStepLabel) ?></p><p class="text-sm text-amber-700 mt-1">ผู้ที่ได้รับมอบหมายจะเห็นปุ่มยืนยันเมื่อเข้าสู่ระบบ</p><div class="mt-4 flex flex-col gap-2 sm:flex-row"><button type="button" id="approveRoundButton" class="rounded-xl bg-emerald-600 px-5 py-3 font-black text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400"><i class="fas fa-check mr-2"></i>ยืนยันขั้นตอนนี้</button><button type="button" id="returnRoundButton" class="rounded-xl bg-rose-100 px-5 py-3 font-black text-rose-700 transition hover:bg-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-300"><i class="fas fa-rotate-left mr-2"></i>ตีกลับ</button></div></div>
            <?php elseif ($dynamicNextStep !== 'completed'): ?>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-center"><p class="font-black text-slate-800">รอผู้รับผิดชอบขั้นตอนนี้</p><p class="mt-1 text-sm text-slate-600">ขั้นตอนปัจจุบัน: <span class="font-black text-indigo-700"><?= inspection_h($dynamicNextStepLabel) ?></span><?php if ($dynamicCurrentAssigneeName !== ''): ?><br>ผู้ได้รับมอบหมาย: <span class="font-black text-slate-800"><?= inspection_h($dynamicCurrentAssigneeName) ?></span><?php endif; ?></p><p class="mt-2 text-xs text-slate-500">คุณสามารถดูความคืบหน้าได้ แต่ไม่สามารถยืนยันหรือตีกลับแทนผู้ได้รับมอบหมาย</p></div>
            <?php else: ?>
                <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-5 text-center"><p class="font-black text-emerald-900 text-lg"><i class="fas fa-circle-check mr-2"></i>GMACC ยืนยันแล้ว กระบวนการตรวจรับเสร็จสิ้น</p></div>
            <?php endif; ?>
        </form>
        </section>
    <?php endif; ?>

    <?php if (count($dynamicHistory) > 0): ?>
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 mt-6">
            <h3 class="text-lg font-black text-slate-800 border-b border-slate-100 pb-4 mb-4"><i class="fas fa-clock-rotate-left text-amber-500 mr-2"></i>ประวัติรอบตรวจ</h3>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-xs uppercase tracking-wider text-slate-400 border-b"><th class="py-3">รอบ</th><th>วันที่</th><th>สถานะ</th><th>ข้อมีปัญหา</th><th class="text-right">ดูย้อนหลัง</th></tr></thead><tbody><?php foreach ($dynamicHistory as $history): ?><?php $historyStatus = (string)($history['status'] ?? ''); $historyStatusClass = ['completed' => 'bg-emerald-50 text-emerald-700', 'correction_required' => 'bg-amber-50 text-amber-800', 'returned' => 'bg-rose-50 text-rose-700'][$historyStatus] ?? 'bg-indigo-50 text-indigo-700'; ?><tr class="border-b last:border-0"><td class="py-3 font-black">#<?= (int)$history['round_no'] ?></td><td><?= inspection_h($history['inspection_date']) ?></td><td><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black <?= $historyStatusClass ?>"><?= inspection_h(inspection_status_label($historyStatus)) ?></span></td><td><?= (int)$history['issue_count'] ?></td><td class="text-right"><a class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-xs font-black text-indigo-700 transition hover:bg-indigo-50" href="inspection_history_checklist.php?round_id=<?= (int)$history['id'] ?>" aria-label="ดู Checklist รอบที่ <?= (int)$history['round_no'] ?>"><i class="fas fa-list-check" aria-hidden="true"></i> ดู Checklist</a></td></tr><?php endforeach; ?></tbody></table></div>
        </div>
    <?php endif; ?>
</div>

<style>
    #roundFixDays::-webkit-inner-spin-button,
    #roundFixDays::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    #roundFixDays {
        -moz-appearance: textfield;
    }
    #checklistRows .item-category,
    #checklistRows .item-title,
    #checklistRows .checklist-advanced .item-ref,
    #checklistRows .checklist-advanced .item-detail,
    #checklistRows .checklist-advanced .item-criteria {
        height: 4rem;
        min-height: 4rem;
    }
    #checklistRows .checklist-advanced > summary {
        list-style: none;
        cursor: pointer;
    }
    #checklistRows .checklist-advanced > summary::-webkit-details-marker {
        display: none;
    }
    #checklistRows .checklist-advanced > summary::after {
        content: '+';
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.5rem;
        height: 1.5rem;
        margin-left: auto;
        border-radius: 0.5rem;
        background: #eef2ff;
        color: #4f46e5;
        font-size: 1rem;
        font-weight: 900;
        line-height: 1;
    }
    #checklistRows .checklist-advanced[open] > summary::after {
        content: '−';
    }
    #checklistRows .checklist-advanced > summary:focus-visible {
        outline: 2px solid #818cf8;
        outline-offset: 2px;
    }
</style>

<script src="assets/js/inspection-checklist-submit.js"></script>
<script>
(function () {
    const checklistForm = document.getElementById('dynamicChecklistForm');
    const rows = document.getElementById('checklistRows');
    const checklistSubmitButton = document.getElementById('checklistSubmitButton');
    const checklistSubmitTools = window.InspectionChecklistSubmit;
    const roundForm = document.getElementById('dynamicRoundForm');
    const roundId = <?= (int)($dynamicRound['id'] ?? 0) ?>;
    const nextStep = <?= json_encode($dynamicNextStep, JSON_UNESCAPED_UNICODE) ?>;

    function esc(value) { return $('<div>').text(value || '').html(); }
    function updateChecklistMeta() {
        if (!rows) return;
        const items = [...rows.querySelectorAll('.check-row')];
        items.forEach((row, index) => {
            const number = row.querySelector('.row-number');
            if (number) number.textContent = String(index + 1).padStart(2, '0');
        });
        const counter = document.getElementById('checklistRowCount');
        if (counter) counter.textContent = `${items.length} รายการ`;
    }
    function rebuildChecklistGroups() {
        if (!rows) return;
        const items = [...rows.querySelectorAll('.check-row')];
        rows.querySelectorAll(':scope > .checklist-category-group').forEach(group => group.remove());
        const groups = new Map();
        items.forEach(row => {
            const category = row.querySelector('.item-category')?.value.trim() || 'ทั่วไป';
            let group = groups.get(category);
            if (!group) {
                const wrapper = document.createElement('section');
                wrapper.className = 'checklist-category-group rounded-2xl border border-indigo-100 bg-indigo-50/40 p-3 sm:p-4';
                wrapper.innerHTML = '<div class="mb-3 flex items-center justify-between gap-3"><div><p class="text-[11px] font-black uppercase tracking-[1.2px] text-indigo-600">หมวดงาน</p><h4 class="category-title mt-0.5 font-black text-slate-800"></h4></div><span class="category-count rounded-full bg-white px-2.5 py-1 text-[11px] font-black text-slate-500"></span></div><div class="category-items space-y-3"></div>';
                wrapper.querySelector('.category-title').textContent = category;
                rows.appendChild(wrapper);
                group = { wrapper, items: wrapper.querySelector('.category-items') };
                groups.set(category, group);
            }
            group.items.appendChild(row);
        });
        groups.forEach(group => {
            const count = group.items.querySelectorAll('.check-row').length;
            group.wrapper.querySelector('.category-count').textContent = `${count} รายการ`;
        });
    }
    function markChecklistDirty() {
        const state = document.getElementById('checklistDirtyState');
        if (!state) return;
        state.textContent = 'มีการแก้ไขที่ยังไม่ได้บันทึก';
        state.classList.remove('text-slate-500');
        state.classList.add('text-amber-700');
    }
    function compactChecklistRow(row, item) {
        const fieldGrid = row.querySelector(':scope > .grid');
        if (!fieldGrid) return;
        const titleField = row.querySelector('.item-title')?.closest('label');
        const categoryField = row.querySelector('.item-category')?.closest('label');
        const detailField = row.querySelector('.item-detail')?.closest('label');
        const refField = row.querySelector('.item-ref')?.closest('label');
        const criteriaField = row.querySelector('.item-criteria')?.closest('label');
        const requiredField = row.querySelector('.item-required')?.closest('label');
        const advanced = document.createElement('details');
        advanced.className = 'checklist-advanced rounded-xl border border-slate-200 bg-white/70 md:col-span-2';
        advanced.open = Boolean(String(item.detail || '').trim() || String(item.contract_ref || '').trim() || String(item.acceptance_criteria || '').trim());
        advanced.innerHTML = '<summary class="flex min-h-11 items-center gap-3 px-3 py-2.5 text-sm font-black text-slate-700 focus:outline-none"><span>รายละเอียดสัญญาและเกณฑ์เพิ่มเติม</span><span class="text-xs font-medium text-slate-400">เปิดเมื่อจำเป็น</span></summary>';
        const advancedGrid = document.createElement('div');
        advancedGrid.className = 'grid grid-cols-1 gap-3 border-t border-slate-200 p-3 md:grid-cols-2';
        [detailField, refField].forEach(field => {
            if (field) {
                field.classList.add('flex', 'h-full', 'flex-col');
                const control = field.querySelector('input, textarea');
                if (control) control.classList.add('flex-none');
                advancedGrid.appendChild(field);
            }
        });
        if (criteriaField) {
            criteriaField.classList.add('flex', 'h-full', 'flex-col', 'md:col-span-2');
            const control = criteriaField.querySelector('input, textarea');
            if (control) control.classList.add('flex-none');
            advancedGrid.appendChild(criteriaField);
        }
        if (requiredField) {
            requiredField.className = 'md:col-span-2 flex items-center gap-2 px-1 pt-1 text-sm font-bold text-slate-700';
            const requiredInput = requiredField.querySelector('.item-required');
            if (requiredInput) requiredInput.classList.remove('min-h-24');
            const requiredText = requiredField.querySelector('span > span');
            if (requiredText) requiredText.classList.add('leading-snug');
            advancedGrid.appendChild(requiredField);
        }
        advanced.appendChild(advancedGrid);
        [categoryField, titleField].forEach(field => {
            if (field) {
                field.classList.add('flex', 'h-full', 'flex-col');
                const control = field.querySelector('input, textarea');
                if (control) control.classList.add('flex-none');
            }
        });
        fieldGrid.className = 'grid grid-cols-1 gap-3 md:grid-cols-2';
        fieldGrid.replaceChildren(categoryField, titleField, advanced);
        const hint = row.querySelector('.text-xs.text-slate-500');
        if (hint) hint.textContent = 'กรอกหัวข้อหลัก แล้วเปิดเกณฑ์เพิ่มเติมเมื่อจำเป็น';
    }
    function addChecklistRow(item = {}) {
        const row = document.createElement('div');
        row.className = 'check-row rounded-2xl border border-slate-200 bg-slate-50/70 p-4 transition-colors sm:p-5';
        const required = item.is_required === false || item.is_required === 0 || item.is_required === '0' ? '' : 'checked';
        row.innerHTML = `<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200/80 pb-4"><div class="flex min-w-0 items-center gap-3"><span class="row-number flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-xs font-black text-indigo-700">01</span><div class="min-w-0"><p class="text-sm font-black text-slate-800">รายการตรวจ</p><p class="text-xs text-slate-500">กำหนดหัวข้อและเกณฑ์อ้างอิงของรายการนี้</p></div></div><button type="button" class="remove-row inline-flex min-h-10 shrink-0 items-center rounded-lg px-3 py-2 text-xs font-black text-rose-600 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-1" aria-label="ลบรายการตรวจ"><i class="fas fa-trash mr-1.5" aria-hidden="true"></i>ลบ</button></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2"><label class="block text-xs font-black text-slate-600"><span>หมวดงาน</span><input class="item-category mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" placeholder="เช่น งานโครงสร้างและสถาปัตย์" value="${esc(item.category || 'ทั่วไป')}"></label><label class="block text-xs font-black text-slate-600"><span>หัวข้อที่ต้องตรวจ <b class="text-rose-500">*</b></span><input class="item-title mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" placeholder="เช่น งานดำเนินการครบตาม BOQ" value="${esc(item.title)}"></label><label class="block text-xs font-black text-slate-600"><span>รายละเอียดงาน</span><textarea class="item-detail mt-1.5 min-h-24 w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" rows="2" placeholder="อธิบายขอบเขตงานที่ต้องตรวจ">${esc(item.detail)}</textarea></label><label class="block text-xs font-black text-slate-600"><span>อ้างอิงสัญญา / BOQ</span><input class="item-ref mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" placeholder="เช่น ข้อ 3.2 หรือ BOQ หมวด 2" value="${esc(item.contract_ref)}"></label><label class="block text-xs font-black text-slate-600"><span>เกณฑ์การผ่าน</span><textarea class="item-criteria mt-1.5 min-h-24 w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" rows="2" placeholder="ระบุเงื่อนไขที่ถือว่าผ่านการตรวจ">${esc(item.acceptance_criteria)}</textarea></label><label class="flex min-h-24 items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-700"><input type="checkbox" class="item-required h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" ${required}><span><span class="block">เป็นข้อบังคับ</span><span class="mt-1 block text-xs font-normal text-slate-500">ต้องตอบผลตรวจข้อนี้ก่อนยืนยัน</span></span></label></div>`;
        compactChecklistRow(row, item);
        row.querySelector('.remove-row').addEventListener('click', () => {
            const remove = () => {
                row.remove();
                rebuildChecklistGroups();
                updateChecklistMeta();
                markChecklistDirty();
            };
            if (window.Swal && typeof Swal.fire === 'function') {
                Swal.fire({ title: 'ลบรายการตรวจนี้หรือไม่', text: 'รายการจะถูกลบเมื่อกดบันทึก', icon: 'warning', showCancelButton: true, confirmButtonText: 'ลบรายการ', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#e11d48' }).then(result => {
                    if (result.isConfirmed) remove();
                });
            } else if (window.confirm('ลบรายการตรวจนี้หรือไม่')) {
                remove();
            }
        });
        row.querySelector('.item-category').addEventListener('change', () => {
            rebuildChecklistGroups();
            updateChecklistMeta();
            markChecklistDirty();
        });
        rows.appendChild(row);
        rebuildChecklistGroups();
        updateChecklistMeta();
    }
    if (rows) {
        const initialItems = <?= json_encode($dynamicItems ?: $dynamicDefaultItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        initialItems.forEach(addChecklistRow);
        if (!rows.children.length) addChecklistRow();
        document.getElementById('addChecklistRow').addEventListener('click', () => addChecklistRow());
        updateChecklistMeta();
    }
    if (checklistForm) {
        checklistForm.addEventListener('input', markChecklistDirty);
        checklistForm.addEventListener('change', markChecklistDirty);
        checklistForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (checklistSubmitButton && checklistSubmitButton.disabled) return;
            if (!checklistSubmitTools) {
                Swal.fire('ไม่สามารถบันทึกได้', 'ระบบบันทึก Checklist โหลดไม่สมบูรณ์ กรุณารีเฟรชหน้าแล้วลองใหม่', 'error');
                return;
            }
            const items = [...rows.querySelectorAll('.check-row')].map(row => ({
                category: row.querySelector('.item-category').value,
                title: row.querySelector('.item-title').value,
                detail: row.querySelector('.item-detail').value,
                contract_ref: row.querySelector('.item-ref').value,
                acceptance_criteria: row.querySelector('.item-criteria').value,
                is_required: row.querySelector('.item-required').checked ? 1 : 0
            }));
            const assignmentNames = ['inspector_1_user_id','inspector_2_user_id','procurement_user_id','md_user_id','gmacc_user_id'];
            const assignments = {};
            assignmentNames.forEach(name => assignments[name] = checklistForm.querySelector(`[name="${name}"]`).value);
            const validationError = checklistSubmitTools.validateChecklistSubmission(items, assignments);
            if (validationError) {
                if (Number.isInteger(validationError.itemIndex)) {
                    rows.querySelectorAll('.check-row')[validationError.itemIndex]?.querySelector('.item-title')?.focus();
                } else if (validationError.fieldName) {
                    checklistForm.querySelector(`[name="${validationError.fieldName}"]`)?.focus();
                }
                Swal.fire('ข้อมูลยังไม่ครบ', validationError.message, 'warning');
                return;
            }

            const payload = { project_id: <?= (int)$project_id ?>, milestone_id: <?= (int)$milestone_id ?>, items: JSON.stringify(items), ...assignments };
            let saveSucceeded = false;
            checklistSubmitTools.setChecklistSubmitting(checklistSubmitButton, true);
            $.ajax({
                url: 'api/inspection_checklist.php?action=save',
                type: 'POST',
                data: payload,
                dataType: 'json'
            }).done(function (res) {
                if (res.status === 'success') {
                    saveSucceeded = true;
                    Swal.fire({ icon: 'success', title: 'บันทึกแล้ว', text: res.message, confirmButtonColor: '#4f46e5' }).then(() => location.reload());
                    return;
                }
                Swal.fire('ไม่สามารถบันทึกได้', res.message || 'ระบบไม่สามารถบันทึก Checklist ได้', 'error');
            }).fail(function (xhr) {
                Swal.fire('ไม่สามารถบันทึกได้', checklistSubmitTools.getChecklistRequestError(xhr), 'error');
            }).always(function () {
                if (!saveSucceeded) checklistSubmitTools.setChecklistSubmitting(checklistSubmitButton, false);
            });
        });
    }
    const startButton = document.getElementById('startRoundButton');
    if (startButton) startButton.addEventListener('click', function () { $.post('api/inspection_round.php?action=start', { project_id: <?= (int)$project_id ?>, milestone_id: <?= (int)$milestone_id ?> }, function (res) { if (res.status === 'success') location.reload(); else Swal.fire('เริ่มตรวจไม่ได้', res.message, 'error'); }, 'json'); });
    function collectResults() { return [...document.querySelectorAll('.result-row')].map(row => ({ id: row.dataset.resultId, result_status: row.querySelector('.result-status').value, note: row.querySelector('.result-note').value, responsible_person: row.querySelector('.result-responsible').value, due_date: row.querySelector('.result-due').value })); }
    const statusLabels = { pass: 'ผ่าน', fail: 'ไม่ผ่าน', conditional_pass: 'มีเงื่อนไข', not_applicable: 'ไม่เกี่ยวข้อง', '': 'ยังไม่ระบุ' };
    const statusClasses = {
        pass: 'border-emerald-600 bg-emerald-600 text-white ring-2 ring-emerald-200',
        fail: 'border-rose-600 bg-rose-600 text-white ring-2 ring-rose-200',
        conditional_pass: 'border-amber-500 bg-amber-500 text-white ring-2 ring-amber-200',
        not_applicable: 'border-slate-600 bg-slate-600 text-white ring-2 ring-slate-200'
    };
    const statusInactiveClasses = {
        pass: 'border-emerald-300 bg-emerald-50 text-emerald-700',
        fail: 'border-rose-300 bg-rose-50 text-rose-700',
        conditional_pass: 'border-amber-300 bg-amber-50 text-amber-700',
        not_applicable: 'border-slate-300 bg-slate-100 text-slate-700'
    };
    function syncPhotoState(row) {
        const status = row.querySelector('.result-status').value;
        const photoCount = Number.parseInt(row.dataset.photoCount || '0', 10);
        const isOptional = status === 'not_applicable';
        const state = row.querySelector('.result-photo-state');
        const message = row.querySelector('.result-photo-message');
        const required = row.querySelector('.result-photo-required');
        if (!state || !message || !required) return;
        state.classList.toggle('border-emerald-200', photoCount > 0);
        state.classList.toggle('bg-emerald-50/70', photoCount > 0);
        state.classList.toggle('border-slate-200', photoCount < 1);
        state.classList.toggle('bg-slate-50', photoCount < 1);
        message.classList.toggle('text-emerald-700', photoCount > 0);
        message.classList.toggle('text-slate-700', photoCount < 1);
        message.textContent = photoCount > 0
            ? `แนบรูปแล้ว ${photoCount} รูป`
            : (isOptional ? 'ข้อไม่เกี่ยวข้อง ไม่บังคับแนบรูป' : 'ต้องแนบรูปอย่างน้อย 1 รูป');
        required.classList.toggle('hidden', isOptional);
    }
    function syncStatusRow(row, value) {
        row.dataset.resultStatus = value || 'open';
        row.classList.remove('border-slate-200', 'border-rose-200', 'border-amber-200');
        row.classList.add(value === 'fail' ? 'border-rose-200' : (value === 'conditional_pass' ? 'border-amber-200' : 'border-slate-200'));
        const label = row.querySelector('.result-status-label');
        if (label) label.textContent = statusLabels[value] || statusLabels[''];
        row.querySelectorAll('.status-choice').forEach(button => {
            const circle = button.querySelector('.status-choice-circle');
            const allStateClasses = [...Object.values(statusClasses), ...Object.values(statusInactiveClasses)].flatMap(classes => classes.split(' '));
            circle.classList.remove(...allStateClasses);
            circle.classList.add(...(button.dataset.value === value ? statusClasses[value] : statusInactiveClasses[button.dataset.value]).split(' '));
            button.setAttribute('aria-pressed', button.dataset.value === value ? 'true' : 'false');
        });
        syncPhotoState(row);
        if (['fail', 'conditional_pass'].includes(value)) {
            const details = row.querySelector('.result-more');
            if (details) details.open = true;
        }
    }
    function updateProgress() {
        const resultRows = document.querySelectorAll('.result-row');
        const answered = [...resultRows].filter(row => row.querySelector('.result-status').value).length;
        const passed = [...resultRows].filter(row => ['pass', 'not_applicable'].includes(row.querySelector('.result-status').value)).length;
        const progress = document.getElementById('inspectionProgress');
        const progressText = document.getElementById('inspectionProgressText');
        if (resultRows.length && progress && progressText) {
            const percent = Math.round((passed / resultRows.length) * 100);
            progress.style.width = percent + '%';
            progress.setAttribute('aria-valuenow', percent);
            progressText.textContent = `ตรวจแล้ว ${answered} / ${resultRows.length} ข้อ · ผ่าน/ไม่เกี่ยวข้อง ${passed} ข้อ`;
        }
    }
    const fixDaysInput = document.getElementById('roundFixDays');
    function adjustFixDays(delta) {
        if (!fixDaysInput || fixDaysInput.disabled) return;
        const current = Number.parseInt(fixDaysInput.value, 10);
        fixDaysInput.value = Math.max(0, (Number.isFinite(current) ? current : 0) + delta);
        fixDaysInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    const fixDaysMinus = document.getElementById('roundFixDaysMinus');
    const fixDaysPlus = document.getElementById('roundFixDaysPlus');
    if (fixDaysMinus) fixDaysMinus.addEventListener('click', () => adjustFixDays(-1));
    if (fixDaysPlus) fixDaysPlus.addEventListener('click', () => adjustFixDays(1));
    document.querySelectorAll('.status-choice').forEach(button => button.addEventListener('click', function () {
        if (this.disabled) return;
        const row = this.closest('.result-row');
        const select = row.querySelector('.result-status');
        select.value = this.dataset.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }));
    document.querySelectorAll('.result-status').forEach(select => {
        select.addEventListener('change', function () {
            const row = this.closest('.result-row');
            syncStatusRow(row, this.value);
            updateProgress();
        });
        syncStatusRow(select.closest('.result-row'), select.value);
    });
    function validatePhotoRequirements() {
        const invalidRow = [...document.querySelectorAll('.result-row')].find(row => {
            const status = row.querySelector('.result-status').value;
            return ['pass', 'fail', 'conditional_pass'].includes(status) && Number.parseInt(row.dataset.photoCount || '0', 10) < 1;
        });
        const uploadingRow = [...document.querySelectorAll('.result-row')].find(row => row.dataset.photoUploading === 'true');
        if (uploadingRow) {
            Swal.fire('กำลังอัปโหลดรูป', 'กรุณารอให้อัปโหลดรูปเสร็จก่อนบันทึก', 'info');
            uploadingRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
        if (invalidRow) {
            Swal.fire('ต้องแนบรูปหลักฐาน', 'กรุณาแนบรูปอย่างน้อย 1 รูปในข้อที่ตรวจแล้ว ยกเว้นข้อที่ไม่เกี่ยวข้อง', 'warning');
            invalidRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
        return true;
    }
    function saveResults(callback) {
        if (!validatePhotoRequirements()) return;
        $.post('api/inspection_round.php?action=save_results', { round_id: roundId, results: JSON.stringify(collectResults()), punch_list: $('#roundPunchList').val(), fix_within_days: $('#roundFixDays').val() }, function (res) { if (res.status === 'success') { if (callback) callback(); else Swal.fire({ icon: 'success', title: 'บันทึกร่างแล้ว', confirmButtonColor: '#4f46e5' }); } else Swal.fire('บันทึกไม่ได้', res.message, 'error'); }, 'json');
    }
    const saveButton = document.getElementById('saveRoundButton');
    if (saveButton) saveButton.addEventListener('click', () => saveResults());
    function approve(action = 'approve') { const needsProcurementReason = action === 'approve' && nextStep === 'procurement' && !<?= $dynamicComparisonSummary['passed'] ? 'true' : 'false' ?>; const reasonPrompt = action === 'approve' && needsProcurementReason ? 'กรุณาระบุเหตุผลประกอบการตัดสินของจัดซื้อ' : (action === 'approve' ? '' : prompt('กรุณาระบุเหตุผลที่ตีกลับ')); const send = () => { if ((needsProcurementReason && !reasonPrompt) || (action !== 'approve' && !reasonPrompt)) { Swal.fire('ต้องระบุเหตุผล', 'กรุณาระบุเหตุผลก่อนบันทึกการตัดสิน', 'warning'); return; } $.post('api/inspection_round.php?action=approve', { round_id: roundId, step: nextStep, approval_action: action, reason: reasonPrompt }, function (res) { if (res.status === 'success') Swal.fire({ icon: 'success', title: action === 'approve' ? 'ยืนยันแล้ว' : 'ตีกลับแล้ว', text: res.message, confirmButtonColor: '#4f46e5' }).then(() => location.reload()); else Swal.fire('ดำเนินการไม่ได้', res.message, 'error'); }, 'json'); }; if (action === 'approve' && ['inspector_1','inspector_2'].includes(nextStep)) saveResults(send); else send(); }
    const approveButton = document.getElementById('approveRoundButton');
    if (approveButton) approveButton.addEventListener('click', () => approve('approve'));
    const returnButton = document.getElementById('returnRoundButton');
    if (returnButton) returnButton.addEventListener('click', () => approve('return'));
    const revisionButton = document.getElementById('revisionButton');
    if (revisionButton) revisionButton.addEventListener('click', function () { $.post('api/inspection_round.php?action=revision', { round_id: roundId }, function (res) { if (res.status === 'success') location.reload(); else Swal.fire('สร้างรอบใหม่ไม่ได้', res.message, 'error'); }, 'json'); });
    document.querySelectorAll('.result-file').forEach(input => input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const row = this.closest('.result-row');
        const data = new FormData();
        data.append('round_id', roundId);
        data.append('result_id', this.dataset.resultId);
        data.append('file', file);
        row.dataset.photoUploading = 'true';
        this.disabled = true;
        $.ajax({
            url: 'api/inspection_round.php?action=upload',
            type: 'POST',
            data,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(res => {
            if (res.status !== 'success') {
                Swal.fire('แนบรูปไม่ได้', res.message, 'error');
                return;
            }
            row.dataset.photoCount = String(Number.parseInt(row.dataset.photoCount || '0', 10) + 1);
            const preview = row.querySelector('.result-photo-preview');
            const imageUrl = URL.createObjectURL(file);
            preview.innerHTML = `<img src="${imageUrl}" alt="รูปหลักฐานล่าสุด" class="h-full w-full object-cover">`;
            preview.querySelector('img').addEventListener('load', () => URL.revokeObjectURL(imageUrl), { once: true });
            syncPhotoState(row);
            Swal.fire({ icon: 'success', title: 'แนบรูปแล้ว', timer: 1200, showConfirmButton: false });
        }).fail(xhr => {
            Swal.fire('แนบรูปไม่ได้', xhr.responseJSON?.message || 'ไม่สามารถอัปโหลดรูปได้', 'error');
        }).always(() => {
            row.dataset.photoUploading = 'false';
            this.disabled = false;
            this.value = '';
        });
    }));
    const comparisonFilterButtons = document.querySelectorAll('[data-comparison-filter]');
    comparisonFilterButtons.forEach(button => button.addEventListener('click', function () {
        const filter = this.dataset.comparisonFilter;
        comparisonFilterButtons.forEach(filterButton => {
            const isActive = filterButton === this;
            filterButton.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            filterButton.classList.toggle('bg-slate-800', isActive);
            filterButton.classList.toggle('text-white', isActive);
            filterButton.classList.toggle('bg-white', !isActive);
            filterButton.classList.toggle('text-slate-600', !isActive);
        });
        document.querySelectorAll('.comparison-item').forEach(item => {
            item.classList.toggle('hidden', filter === 'conflict' && item.dataset.comparisonConflict !== '1');
        });
    }));
    document.querySelectorAll('.result-filter').forEach(button => button.addEventListener('click', function () { document.querySelectorAll('.result-filter').forEach(b => b.classList.remove('bg-slate-800','text-white')); document.querySelectorAll('.result-filter').forEach(b => b.classList.add('bg-slate-100','text-slate-600')); this.classList.add('bg-slate-800','text-white'); this.classList.remove('bg-slate-100','text-slate-600'); const filter = this.dataset.filter; document.querySelectorAll('.result-row').forEach(row => { const status = row.querySelector('.result-status').value; row.style.display = filter === 'all' || (filter === 'open' && !['pass', 'not_applicable'].includes(status)) || (filter === 'pass' && status === 'pass') ? '' : 'none'; }); }));
    updateProgress();
})();
</script>
