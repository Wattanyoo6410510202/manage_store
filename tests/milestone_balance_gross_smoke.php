<?php

function assert_same_number(float $expected, float $actual, string $label): void
{
    if (abs($expected - $actual) > 0.00001) {
        throw new RuntimeException($label . ': expected ' . $expected . ', got ' . $actual);
    }
}

function assert_contains(string $source, string $needle, string $label): void
{
    if (strpos($source, $needle) === false) {
        throw new RuntimeException($label . ': missing ' . $needle);
    }
}

function assert_not_contains(string $source, string $needle, string $label): void
{
    if (strpos($source, $needle) !== false) {
        throw new RuntimeException($label . ': unexpected ' . $needle);
    }
}

$contractValue = 10000.00;
$grossClaim = 10000.00;
$netPayment = 9719.63;
$withholdingTax = 280.37;

assert_same_number(0.00, $contractValue - $grossClaim, 'full gross claim remaining balance');
assert_same_number($withholdingTax, $contractValue - $netPayment, 'withholding is not unclaimed work');

$detailProject = file_get_contents(__DIR__ . '/../detail_project.php');
$addMilestone = file_get_contents(__DIR__ . '/../add_milestone.php');
$editMilestone = file_get_contents(__DIR__ . '/../edit_milestone.php');
$projects = file_get_contents(__DIR__ . '/../projects.php');
$testPage = file_get_contents(__DIR__ . '/../test.php');

assert_contains($detailProject, 'SUM(amount) as total_base', 'detail project work balance');
assert_not_contains($detailProject, 'SUM(total_request_amount) as total_base', 'detail project work balance');
assert_contains($addMilestone, 'SUM(amount) as total', 'new milestone work balance');
assert_not_contains($addMilestone, 'SUM(total_request_amount) as total', 'new milestone work balance');
assert_contains($editMilestone, 'SUM(amount) as total', 'edit milestone work balance');
assert_not_contains($editMilestone, 'SUM(total_request_amount) as total', 'edit milestone work balance');
assert_contains($projects, 'SUM(amount) FROM project_milestones', 'project list work progress');
assert_not_contains($projects, 'SUM(net_amount) FROM project_milestones', 'project list work progress');
assert_contains($testPage, 'SUM(amount) FROM project_milestones', 'test page work progress');
assert_not_contains($testPage, 'SUM(net_amount) FROM project_milestones', 'test page work progress');

echo "milestone gross balance calculation: PASS\n";
