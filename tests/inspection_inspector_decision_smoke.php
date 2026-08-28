<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inspection_workflow.php';

$results = [
    ['id' => 101, 'round_id' => 7, 'checklist_item_id' => 11, 'inspection_step' => 'inspector_1', 'item_order' => 1, 'title' => 'งานตาม BOQ', 'is_required' => 1, 'result_status' => 'fail'],
    ['id' => 102, 'round_id' => 7, 'checklist_item_id' => 11, 'inspection_step' => 'inspector_2', 'item_order' => 1, 'title' => 'งานตาม BOQ', 'is_required' => 1, 'result_status' => 'pass'],
];

$grouped = inspection_results_by_step($results);
if (count($grouped['inspector_1'] ?? []) !== 1 || count($grouped['inspector_2'] ?? []) !== 1) {
    fwrite(STDERR, "result grouping failed\n");
    exit(1);
}

$comparison = inspection_compare_results($results);
if (empty($comparison['has_conflict']) || !empty($comparison['passed']) || empty($comparison['has_fail'])) {
    fwrite(STDERR, "fail/pass comparison failed\n");
    exit(1);
}

$roundRows = inspection_fetch_all($conn, "SELECT inspection_step, COUNT(*) AS total FROM inspection_round_results WHERE round_id = 1 GROUP BY inspection_step", '', []);
$roundCounts = [];
foreach ($roundRows as $roundRow) {
    $roundCounts[$roundRow['inspection_step']] = (int)$roundRow['total'];
}
if (empty($roundCounts['inspector_1']) || empty($roundCounts['inspector_2']) || $roundCounts['inspector_1'] !== $roundCounts['inspector_2']) {
    fwrite(STDERR, "database inspector result separation failed\n");
    exit(1);
}

$_SESSION['user'] = 'eng';
$_SESSION['user_id'] = 24;
$_SESSION['role'] = 'tech_shotel';
$project_id = 113;
$milestone_id = 229;
$dynamic_context = inspection_load_context($conn, $project_id, $milestone_id);
ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$inspectorHtml = ob_get_clean();
preg_match_all('/class="result-row /', $inspectorHtml, $rowMatches);
if (count($rowMatches[0]) !== count($dynamic_context['resultsByStep']['inspector_1'] ?? [])) {
    fwrite(STDERR, "inspector-specific form rows failed\n");
    exit(1);
}
if (strpos($inspectorHtml, 'id="procurementDecisionPanel"') !== false) {
    fwrite(STDERR, "procurement panel leaked into inspector view\n");
    exit(1);
}

$procureResults = [
    ['id' => 201, 'round_id' => 7, 'checklist_item_id' => 11, 'inspection_step' => 'inspector_1', 'item_order' => 1, 'title' => 'งานตาม BOQ', 'is_required' => 1, 'result_status' => 'fail', 'note' => 'ต้องแก้'],
    ['id' => 202, 'round_id' => 7, 'checklist_item_id' => 11, 'inspection_step' => 'inspector_2', 'item_order' => 1, 'title' => 'งานตาม BOQ', 'is_required' => 1, 'result_status' => 'pass', 'note' => 'ตรวจซ้ำแล้ว'],
    ['id' => 203, 'round_id' => 7, 'checklist_item_id' => 12, 'inspection_step' => 'inspector_1', 'item_order' => 2, 'title' => 'วัสดุตรงตามสเปก', 'is_required' => 1, 'result_status' => 'pass', 'note' => 'ตรงตามแบบ'],
    ['id' => 204, 'round_id' => 7, 'checklist_item_id' => 12, 'inspection_step' => 'inspector_2', 'item_order' => 2, 'title' => 'วัสดุตรงตามสเปก', 'is_required' => 1, 'result_status' => 'pass', 'note' => 'ตรวจแล้ว'],
];
$procureContext = $dynamic_context;
$procureContext['round'] = array_merge($dynamic_context['round'] ?? [], ['status' => 'awaiting_procurement']);
$procureContext['results'] = $procureResults;
$procureContext['resultsByStep'] = inspection_results_by_step($procureResults);
$_SESSION['user_id'] = 31;
$_SESSION['role'] = 'procure';
$dynamic_context = $procureContext;
ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$procureHtml = ob_get_clean();
if (strpos($procureHtml, 'id="procurementDecisionPanel"') === false || strpos($procureHtml, 'ผลตรวจไม่ตรงกัน') === false) {
    fwrite(STDERR, "procurement comparison panel failed\n");
    exit(1);
}
foreach (['data-comparison-filter="all"', 'data-comparison-conflict="1"', 'data-comparison-conflict="0"', 'comparison-inspector-1', 'comparison-inspector-2', 'comparison-mobile-card', 'comparisonFilterButtons'] as $marker) {
    if (strpos($procureHtml, $marker) === false) {
        fwrite(STDERR, "procurement comparison missing {$marker}\n");
        exit(1);
    }
}

$gmaccContext = $procureContext;
$gmaccContext['round'] = array_merge($procureContext['round'] ?? [], ['status' => 'awaiting_gmacc']);
$gmaccContext['checklist'] = array_merge($procureContext['checklist'] ?? [], ['gmacc_user_id' => 99, 'gmacc_name' => 'ผู้ยืนยันบัญชี']);
$_SESSION['user_id'] = 31;
$_SESSION['role'] = 'procure';
$dynamic_context = $gmaccContext;
ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$unauthorizedGmaccHtml = ob_get_clean();
if (strpos($unauthorizedGmaccHtml, 'id="approveRoundButton"') !== false || strpos($unauthorizedGmaccHtml, 'id="returnRoundButton"') !== false) {
    fwrite(STDERR, "Procurement user received GMACC decision buttons\n");
    exit(1);
}
if (strpos($unauthorizedGmaccHtml, 'ผู้ยืนยันบัญชี') === false || strpos($unauthorizedGmaccHtml, 'รอผู้รับผิดชอบขั้นตอนนี้') === false) {
    fwrite(STDERR, "Unauthorized user did not receive the read-only GMACC owner state\n");
    exit(1);
}

$_SESSION['user_id'] = 99;
$_SESSION['role'] = 'gmacc';
$dynamic_context = $gmaccContext;
ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$assignedGmaccHtml = ob_get_clean();
if (strpos($assignedGmaccHtml, 'id="approveRoundButton"') === false || strpos($assignedGmaccHtml, 'id="returnRoundButton"') === false) {
    fwrite(STDERR, "Assigned GMACC user did not receive decision buttons\n");
    exit(1);
}
if (strpos($assignedGmaccHtml, 'id="procurementDecisionPanel"') === false || strpos($assignedGmaccHtml, 'GMACC Review') === false) {
    fwrite(STDERR, "GMACC step did not receive the inspector comparison panel\n");
    exit(1);
}

$mdContext = $procureContext;
$mdContext['round'] = array_merge($procureContext['round'] ?? [], ['status' => 'awaiting_md']);
$mdContext['checklist'] = array_merge($procureContext['checklist'] ?? [], ['md_user_id' => 88, 'md_name' => 'ผู้อนุมัติ MD']);
$_SESSION['user_id'] = 88;
$_SESSION['role'] = 'gmshotel';
$dynamic_context = $mdContext;
ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$assignedMdHtml = ob_get_clean();
if (strpos($assignedMdHtml, 'id="procurementDecisionPanel"') === false || strpos($assignedMdHtml, 'OA&amp;HR Manager Review') === false) {
    fwrite(STDERR, "OA&HR Manager step did not receive the inspector comparison panel\n");
    exit(1);
}
if (strpos($assignedMdHtml, 'OA&amp;HR Manager อนุมัติ') === false) {
    fwrite(STDERR, "OA&HR Manager current workflow label was not rendered\n");
    exit(1);
}

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$dynamic_context = $gmaccContext;
ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$adminGmaccHtml = ob_get_clean();
if (strpos($adminGmaccHtml, 'id="approveRoundButton"') === false || strpos($adminGmaccHtml, 'id="returnRoundButton"') === false) {
    fwrite(STDERR, "Admin override did not receive GMACC decision buttons\n");
    exit(1);
}

$dynamic_round_id = 2;
ob_start();
include __DIR__ . '/../inspection_dynamic_view.php';
$documentHtml = ob_get_clean();
if (strpos($documentHtml, 'result-compare-table') === false || strpos($documentHtml, 'ผลตรวจแยกตามผู้ตรวจ') === false) {
    fwrite(STDERR, "document comparison rendering failed\n");
    exit(1);
}

echo "inspection inspector decision smoke: PASS\n";
