<?php
declare(strict_types=1);

require_once __DIR__ . '/../retention_return_schedule.php';
require_once __DIR__ . '/../config.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assert_true(retention_return_date_is_valid('2026-10-19'), 'valid ISO date should pass');
assert_true(!retention_return_date_is_valid('2026-02-29'), 'invalid calendar date should fail');
assert_true(!retention_return_date_is_valid('19/10/2026'), 'non ISO date should fail');
assert_true(!retention_return_date_is_valid(''), 'empty date should fail');
assert_true(!retention_return_date_is_valid(null), 'null date should fail');

$result = mysqli_query($conn, "SHOW COLUMNS FROM project_milestones");
assert_true($result !== false, 'project_milestones schema should be readable');
$columns = [];
while ($column = mysqli_fetch_assoc($result)) {
    $columns[$column['Field']] = $column;
}

assert_true(isset($columns['retention_return_due_date']), 'due date column should exist');
assert_true($columns['retention_return_due_date']['Type'] === 'date', 'due date column should be DATE');
assert_true($columns['retention_return_due_date']['Null'] === 'YES', 'due date column should be nullable');
assert_true(isset($columns['retention_returned_at']), 'returned timestamp column should exist');
assert_true($columns['retention_returned_at']['Type'] === 'datetime', 'returned timestamp column should be DATETIME');
assert_true($columns['retention_returned_at']['Null'] === 'YES', 'returned timestamp column should be nullable');

echo "PASS\n";
