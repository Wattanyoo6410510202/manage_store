<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inspection_workflow.php';

$expectedStatuses = [
    'awaiting_inspector_1' => 'รอผู้ตรวจรับ 1',
    'awaiting_inspector_2' => 'รอผู้ตรวจรับ 2',
    'awaiting_procurement' => 'รอจัดซื้อพิจารณา',
    'awaiting_md' => 'รอ OA&HR Manager อนุมัติ',
    'awaiting_gmacc' => 'รอ GMACC ยืนยัน',
    'correction_required' => 'ต้องแก้ไข',
    'returned' => 'ถูกตีกลับ',
    'completed' => 'เสร็จสิ้น',
];

foreach ($expectedStatuses as $status => $label) {
    if (!function_exists('inspection_status_label') || inspection_status_label($status) !== $label) {
        fwrite(STDERR, "status label mapping failed for {$status}\n");
        exit(1);
    }
}

if (inspection_step_label('md') !== 'OA&HR Manager') {
    fwrite(STDERR, "OA&HR Manager workflow step label failed\n");
    exit(1);
}

$_SESSION['user_id'] = 17;
$_SESSION['role'] = 'admin';
$_SESSION['user'] = 'admin';
$project_id = 113;
$milestone_id = 229;
$dynamic_context = inspection_load_context($conn, $project_id, $milestone_id);
ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$html = ob_get_clean();
if (strpos($html, 'รอผู้ตรวจรับ 1') === false || strpos($html, 'inspection_history_checklist.php?round_id=1') === false) {
    fwrite(STDERR, "history status/link rendering failed\n");
    exit(1);
}

$historyChecklistSource = file_get_contents(__DIR__ . '/../inspection_history_checklist.php');
if (strpos($historyChecklistSource, 'อ่านอย่างเดียว') === false || strpos($historyChecklistSource, 'ผู้ตรวจรับ 1') === false || strpos($historyChecklistSource, 'ผู้ตรวจรับ 2') === false) {
    fwrite(STDERR, "read-only checklist history view failed\n");
    exit(1);
}
$_GET['round_id'] = 1;
ob_start();
include __DIR__ . '/../inspection_history_checklist.php';
$historyHtml = ob_get_clean();
if (strpos($historyHtml, 'Checklist รอบตรวจที่ 1') === false || strpos($historyHtml, 'ดูข้อมูลย้อนหลังแบบอ่านอย่างเดียว') === false) {
    fwrite(STDERR, "read-only checklist history page did not render\n");
    exit(1);
}

$documentSource = file_get_contents(__DIR__ . '/../inspection_dynamic_view.php');
if (strpos($documentSource, 'result-compare-table" style="display:none"') === false) {
    fwrite(STDERR, "document comparison block was not removed\n");
    exit(1);
}

$viewSource = file_get_contents(__DIR__ . '/../view_inspection.php');
if (strpos($viewSource, '$_GET[\'history\']') === false || strpos($viewSource, 'dynamicHistoryView') === false) {
    fwrite(STDERR, "history view gate failed\n");
    exit(1);
}
if (strpos($viewSource, '$dynamicGateResults') === false || strpos($viewSource, 'WHERE round_id = ?') === false) {
    fwrite(STDERR, "history view must gate against the requested round\n");
    exit(1);
}

echo "inspection history smoke: PASS\n";
