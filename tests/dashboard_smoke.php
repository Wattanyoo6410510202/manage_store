<?php

require_once __DIR__ . '/../dashboard_lib.php';

function dash_assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$today = new DateTimeImmutable('2026-10-28');

$daily = dash_resolve_period('daily', '2026-03-01', 0, 0, $today);
dash_assert_same('2026-03-01', $daily['start'], 'daily range must start on the selected date');
dash_assert_same('2026-03-02', $daily['end'], 'daily range must end before the next day');
dash_assert_same('2026-02-28', $daily['prev_start'], 'daily comparison must be the previous day');
dash_assert_same('2026-02-16', $daily['trend_start'], 'daily trend must show the last 14 days');
dash_assert_same(14, count(dash_trend_buckets($daily)), 'daily trend must have one bucket per day');

$monthly = dash_resolve_period('monthly', '', 2, 2026, $today);
dash_assert_same('2026-02-01', $monthly['start'], 'monthly range must start on the first day');
dash_assert_same('2026-03-01', $monthly['end'], 'monthly range must cover the whole month');
dash_assert_same('2026-01-01', $monthly['prev_start'], 'monthly comparison must be the previous month');
dash_assert_same(28, count(dash_trend_buckets($monthly)), 'monthly trend must list every day of the month');
dash_assert_same('กุมภาพันธ์ 2569', $monthly['label'], 'monthly label must use the Thai month and Buddhist year');

$yearly = dash_resolve_period('yearly', '', 0, 2026, $today);
dash_assert_same('2027-01-01', $yearly['end'], 'yearly range must cover the whole year');
dash_assert_same(12, count(dash_trend_buckets($yearly)), 'yearly trend must list twelve months');

$fallback = dash_resolve_period('weekly', 'not-a-date', 13, 1990, $today);
dash_assert_same('monthly', $fallback['period'], 'unknown period must fall back to monthly');
dash_assert_same('2026-10-01', $fallback['start'], 'invalid month and year must fall back to today');
dash_assert_same('2026-10-28', dash_resolve_period('daily', '2026-02-30', 0, 0, $today)['start'], 'invalid dates must fall back to today');

dash_assert_same(50.0, dash_percent_change(150, 100), 'growth must be a percentage of the previous value');
dash_assert_same(null, dash_percent_change(10, 0), 'growth from zero must not divide by zero');

$groups = dash_group([
    ['category' => 'IT', 'grand_total' => 100.005],
    ['category' => '', 'grand_total' => 50],
    ['category' => 'IT', 'grand_total' => 20],
], 'category');
dash_assert_same('IT', $groups[0]['label'], 'groups must be sorted by value');
dash_assert_same(2, $groups[0]['count'], 'groups must count their rows');
dash_assert_same(120.01, $groups[0]['value'], 'group values must be rounded to satang');
dash_assert_same('ไม่ระบุ', $groups[1]['label'], 'blank keys must be labelled as unspecified');

$summary = dash_summarize([
    ['status' => 'approved', 'grand_total' => 100, 'created_at' => '2026-10-01 08:00:00', 'approved_at' => '2026-10-03 08:00:00'],
    ['status' => 'pending', 'grand_total' => 40, 'created_at' => '2026-10-02 08:00:00', 'approved_at' => null],
    ['status' => 'rejected', 'grand_total' => 10, 'created_at' => '2026-10-02 08:00:00', 'approved_at' => null],
    ['status' => 'cancelled', 'grand_total' => 999, 'created_at' => '2026-10-02 08:00:00', 'approved_at' => null],
], [
    ['status' => 'approved', 'grand_total' => 90],
    ['status' => 'pending', 'grand_total' => 5],
]);
dash_assert_same(3, $summary['pr_count'], 'cancelled PRs must not be counted');
dash_assert_same(150.0, $summary['pr_value'], 'PR value must exclude cancelled documents');
dash_assert_same(100.0, $summary['pr_approved_value'], 'approved PR value must only include approved PRs');
dash_assert_same(2.0, $summary['approval_days_avg'], 'approval time must be measured from creation to approval');
dash_assert_same(95.0, $summary['po_value'], 'PO value must include pending and approved POs');
dash_assert_same(90.0, $summary['po_approved_value'], 'approved PO value must only include approved POs');

echo "Dashboard smoke tests passed.\n";
