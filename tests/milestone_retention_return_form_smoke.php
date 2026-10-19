<?php
declare(strict_types=1);

function assert_contains(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$partial = file_get_contents(__DIR__ . '/../partials/milestone_deduction_card.php');
$add = file_get_contents(__DIR__ . '/../add_milestone.php');
$edit = file_get_contents(__DIR__ . '/../edit_milestone.php');
$save = file_get_contents(__DIR__ . '/../api/save_milestone.php');
$update = file_get_contents(__DIR__ . '/../api/update_milestone_full.php');

assert_contains($partial, 'name="retention_return_due_date"', 'deduction card should render due-date input');
assert_contains($partial, 'required', 'deduction card should support required due date');
assert_contains($add, "include 'partials/milestone_deduction_card.php'", 'add milestone should include the deduction card');
assert_contains($edit, "include 'partials/milestone_deduction_card.php'", 'edit milestone should include the deduction card');
assert_contains($partial, 'toggleRetentionReturnDate', 'deduction card should wire retention state');
assert_contains($save, "require_once '../retention_return_schedule.php'", 'save API should load date validator');
assert_contains($update, "require_once '../retention_return_schedule.php'", 'update API should load date validator');
assert_contains($save, 'retention_return_due_date', 'save API should persist due date');
assert_contains($save, 'retention_returned_at', 'save API should initialize returned state');
assert_contains($update, 'retention_return_due_date', 'update API should persist due date');
assert_contains($update, 'retention_returned_at', 'update API should reset returned state');

echo "PASS\n";
