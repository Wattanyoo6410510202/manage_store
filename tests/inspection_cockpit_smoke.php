<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inspection_workflow.php';

$_SESSION['user'] = 'smoke';
$_SESSION['user_id'] = 24;
$_SESSION['role'] = 'tech_shotel';
$_GET = ['project_id' => 113, 'milestone_id' => 229];

$project_id = 113;
$milestone_id = 229;
$dynamic_context = inspection_load_context($conn, $project_id, $milestone_id);

ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$html = ob_get_clean();

$required = [
    'inspectionCockpitHeader',
    'inspectionFlowStepper',
    'inspectionResultsSummary',
    'dynamicRoundForm',
    'dynamicResults',
    'inspectionProgress',
    'roundPunchList',
    'roundFixDaysMinus',
    'roundFixDaysPlus',
    'inspectionActionBar',
    'inspectionChecklistWorkspace',
    'inspectionChecklistToolbar',
];

foreach ($required as $id) {
if (strpos($html, 'id="' . $id . '"') === false) {
        fwrite(STDERR, "Missing {$id}\n");
        exit(1);
    }
}

if (strpos($html, 'aria-valuemax="100"') === false) {
    fwrite(STDERR, "Missing progress accessibility attributes\n");
    exit(1);
}
if (strpos($html, 'lg:grid-cols-[minmax(0,1fr)_340px]') === false || strpos($html, 'lg:col-span-full') === false) {
    fwrite(STDERR, "Missing desktop checklist card layout\n");
    exit(1);
}
if (strpos($html, 'aria-pressed="false"') === false || strpos($html, 'min-h-11') === false) {
    fwrite(STDERR, "Missing accessible status button state\n");
    exit(1);
}

$_SESSION['user_id'] = 12;
$_SESSION['role'] = 'procure';
$dynamic_context = inspection_load_context($conn, $project_id, $milestone_id);
ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$readOnlyHtml = ob_get_clean();

if (strpos($readOnlyHtml, 'id="inspectionActionBar"') !== false) {
    fwrite(STDERR, "Read-only user unexpectedly received action bar\n");
    exit(1);
}
if (preg_match('/class="status-choice[^>]*disabled/', $readOnlyHtml) !== 1) {
    fwrite(STDERR, "Read-only status controls are not disabled\n");
    exit(1);
}
if (strpos($readOnlyHtml, 'edit_checklist=1') === false) {
    fwrite(STDERR, "Procurement role cannot access checklist editor\n");
    exit(1);
}

$_GET['edit_checklist'] = 1;
$dynamic_context = inspection_load_context($conn, $project_id, $milestone_id);
ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$procureEditHtml = ob_get_clean();
if (strpos($procureEditHtml, 'id="dynamicChecklistForm"') === false || strpos($procureEditHtml, 'บันทึกการแก้ไข Checklist') === false) {
    fwrite(STDERR, "Procurement role cannot edit checklist\n");
    exit(1);
}

$_SESSION['user_id'] = 24;
$_SESSION['role'] = 'admin';
$_GET['edit_checklist'] = 1;
$dynamic_context = inspection_load_context($conn, $project_id, $milestone_id);
ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$editHtml = ob_get_clean();

foreach (['dynamicChecklistForm', 'checklistRows', 'checklistRowCount', 'checklistDirtyState', 'addChecklistRow'] as $id) {
    if (strpos($editHtml, 'id="' . $id . '"') === false) {
        fwrite(STDERR, "Missing checklist editor {$id}\n");
        exit(1);
    }
}
foreach (['pb-40 sm:pb-28', 'checklist-advanced', 'รายการจะถูกลบเมื่อกดบันทึก'] as $marker) {
    if (strpos($editHtml, $marker) === false) {
        fwrite(STDERR, "Missing checklist editor refinement {$marker}\n");
        exit(1);
    }
}

echo "inspection cockpit smoke: PASS\n";
echo "inspection cockpit read-only smoke: PASS\n";
echo "inspection checklist editor smoke: PASS\n";
