<?php
$authorizationPath = __DIR__ . '/../project_authorization.php';
if (is_file($authorizationPath)) {
    require_once $authorizationPath;
}

function assert_project_management_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

if (!function_exists('project_user_can_manage')) {
    fwrite(STDERR, "FAIL: project management authorization is not implemented\n");
    exit(1);
}

assert_project_management_same(true, project_user_can_manage(12, 'procure'), 'procurement users must manage projects');
assert_project_management_same(true, project_user_can_manage(1, 'admin'), 'administrators must manage projects');
assert_project_management_same(false, project_user_can_manage(24, 'tech_shotel'), 'technical inspectors must remain read-only');
assert_project_management_same(false, project_user_can_manage(9, 'viewer'), 'viewer users must remain read-only');
assert_project_management_same(false, project_user_can_manage(0, 'admin'), 'anonymous requests must not manage projects');

echo "project management authorization: PASS\n";
