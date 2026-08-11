<?php
require_once __DIR__ . '/../inspection_workflow.php';

function assert_project_access_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

if (!function_exists('inspection_should_restrict_project_access')) {
    fwrite(STDERR, "FAIL: role-aware inspection project access rule is not implemented\n");
    exit(1);
}

$permissions = [
    'procure' => ['dashboard', 'projects'],
    'tech_shotel' => ['dashboard', 'compare'],
];

assert_project_access_same(
    false,
    inspection_should_restrict_project_access('procure', $permissions, true),
    'assigned procurement users must retain normal project access'
);
assert_project_access_same(
    true,
    inspection_should_restrict_project_access('tech_shotel', $permissions, true),
    'assigned users without project permission must remain inspection-only'
);
assert_project_access_same(
    false,
    inspection_should_restrict_project_access('procure', $permissions, false),
    'unassigned procurement users must retain normal project access'
);
assert_project_access_same(
    false,
    inspection_should_restrict_project_access('tech_shotel', $permissions, false),
    'unassigned users must not receive inspection-only access'
);

echo "inspection project access: PASS\n";
