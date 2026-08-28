<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inspection_workflow.php';

function inspection_checklist_oa_hr_label_fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$fixture = mysqli_query($conn, "
    SELECT m.id AS milestone_id, m.project_id, m.milestone_name, p.project_name
    FROM project_milestones m
    JOIN projects p ON p.id = m.project_id
    ORDER BY m.id DESC
    LIMIT 1
")->fetch_assoc();
if (!$fixture) {
    inspection_checklist_oa_hr_label_fail('no project milestone fixture is available');
}

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['user'] = 'admin';
$_GET = [];

$project_id = (int)$fixture['project_id'];
$milestone_id = (int)$fixture['milestone_id'];
$dynamic_context = [
    'project' => [
        'project_name' => (string)$fixture['project_name'],
        'milestone_name' => (string)$fixture['milestone_name'],
        'supplier_name' => '-',
    ],
    'checklist' => null,
    'items' => [],
    'round' => null,
    'results' => [],
    'approvals' => [],
    'resultsByStep' => [],
];

ob_start();
include __DIR__ . '/../inspection_dynamic_form.php';
$html = ob_get_clean();

$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
libxml_clear_errors();

$xpath = new DOMXPath($document);
$managerSelect = $xpath->query('//label[contains(normalize-space(.), "OA&HR Manager")]/select[@name="md_user_id"]');
if (!$managerSelect || $managerSelect->length !== 1) {
    inspection_checklist_oa_hr_label_fail('the checklist assignee field is not labeled OA&HR Manager');
}

echo "inspection checklist OA&HR Manager label: PASS\n";
