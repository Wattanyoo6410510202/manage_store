<?php
declare(strict_types=1);

function assert_contains(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$source = file_get_contents(__DIR__ . '/../projects.php');
assert_contains($source, 'retention_return_due_date', 'projects query/card should include due date');
assert_contains($source, 'retention_returned_at', 'projects query/card should include returned state');
assert_contains($source, 'retentionReturnAlerts', 'projects page should render reminder payload');
assert_contains($source, 'projectName', 'reminder payload should include project name');
assert_contains($source, 'milestoneName', 'reminder payload should include milestone name');
assert_contains($source, 'assets/js/retention_return_reminder.js', 'projects page should load reminder module');
assert_contains($source, 'คืนเงินประกัน', 'project card should render retention status');

echo "PASS\n";
