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

$dynamic_round_id = 2;
ob_start();
include __DIR__ . '/../inspection_dynamic_view.php';
$documentHtml = ob_get_clean();
if (strpos($documentHtml, 'result-compare-table') === false || strpos($documentHtml, 'ผลตรวจแยกตามผู้ตรวจ') === false) {
    fwrite(STDERR, "document comparison rendering failed\n");
    exit(1);
}

echo "inspection inspector decision smoke: PASS\n";
