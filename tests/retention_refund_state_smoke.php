<?php
declare(strict_types=1);

function assert_contains(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$detail = file_get_contents(__DIR__ . '/../detail_project.php');
$endpoint = file_get_contents(__DIR__ . '/../api/update_refund_milestone.php');

assert_contains($detail, 'retention_return_due_date', 'detail should display planned return date');
assert_contains($detail, 'retention_returned_at', 'detail should display returned state');
assert_contains($detail, 'retention_amount', 'detail should restrict refund to positive retention');
assert_contains($endpoint, 'retention_amount', 'refund endpoint should validate retention amount');
assert_contains($endpoint, 'retention_returned_at', 'refund endpoint should write returned timestamp');
assert_contains($endpoint, 'retention_return_due_date', 'refund endpoint should preserve due date');
assert_contains($endpoint, 'ยืนยันคืนเงินประกันแล้ว', 'refund endpoint should reject repeated confirmation');

echo "PASS\n";
