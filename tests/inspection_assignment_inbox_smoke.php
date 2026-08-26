<?php
require_once __DIR__ . '/../inspection_workflow.php';

$checklist = [
    'inspector_1_user_id' => 10,
    'inspector_2_user_id' => 11,
    'procurement_user_id' => 12,
    'md_user_id' => 77,
    'gmacc_user_id' => 14,
];

$mdSteps = inspection_assignment_steps_for_user($checklist, 77);
if ($mdSteps !== ['md']) {
    fwrite(STDERR, "MD assignment was not resolved by user id\n");
    exit(1);
}

$planned = inspection_assignment_task_state($checklist, null, 77);
if (($planned['actionable'] ?? true) !== false || ($planned['current_step'] ?? '') !== 'checklist') {
    fwrite(STDERR, "Assigned MD should see a planned inspection before the round starts\n");
    exit(1);
}

$waitingForInspector = inspection_assignment_task_state($checklist, ['status' => 'awaiting_inspector_1'], 77);
if (($waitingForInspector['actionable'] ?? true) !== false || ($waitingForInspector['current_step'] ?? '') !== 'inspector_1') {
    fwrite(STDERR, "Assigned MD task did not expose the current workflow owner\n");
    exit(1);
}

$waitingForMd = inspection_assignment_task_state($checklist, ['status' => 'awaiting_md'], 77);
if (($waitingForMd['actionable'] ?? false) !== true || ($waitingForMd['current_step'] ?? '') !== 'md') {
    fwrite(STDERR, "Assigned MD task was not actionable when the flow reached MD\n");
    exit(1);
}

$pendingCount = inspection_count_actionable_tasks([$planned, $waitingForInspector, $waitingForMd]);
if ($pendingCount !== 1) {
    fwrite(STDERR, "Sidebar count must include only actionable assigned inspections\n");
    exit(1);
}

if (!inspection_can_access_pending_approval('tech_shotel', true)) {
    fwrite(STDERR, "Assigned user must be able to open the pending approval page\n");
    exit(1);
}
if (inspection_can_access_pending_approval('tech_shotel', false)) {
    fwrite(STDERR, "Unassigned technical user unexpectedly received approval access\n");
    exit(1);
}

echo "inspection assignment inbox smoke: PASS\n";
