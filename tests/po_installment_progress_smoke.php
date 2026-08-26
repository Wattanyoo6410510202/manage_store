<?php

require_once __DIR__ . '/../po_installment_progress.php';

function assert_po_progress_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$today = '2026-08-15';

$pendingApproval = po_installment_progress([
    'status' => 'pending',
    'installment_total' => 3,
    'installment_paid' => 0,
    'next_installment_no' => 1,
    'next_installment_due_date' => '2026-08-20',
], $today);
assert_po_progress_same('approval_pending', $pendingApproval['key'], 'PO approval must take priority over installment progress');
assert_po_progress_same('รออนุมัติ', $pendingApproval['label'], 'Pending PO must keep the existing approval label');
assert_po_progress_same(null, $pendingApproval['next_due_date'], 'Pending PO must not expose an active installment due date');

$cancelled = po_installment_progress([
    'status' => 'cancelled',
    'installment_total' => 3,
    'installment_paid' => 1,
    'next_installment_no' => 2,
    'next_installment_due_date' => '2026-08-20',
], $today);
assert_po_progress_same('cancelled', $cancelled['key'], 'Cancelled PO must not show active installment progress');
assert_po_progress_same('ยกเลิก', $cancelled['label'], 'Cancelled PO must keep its terminal label');
assert_po_progress_same(null, $cancelled['next_due_date'], 'Cancelled PO must not expose an active installment due date');

$bangkokBoundary = new DateTimeImmutable('2026-08-14 18:30:00', new DateTimeZone('UTC'));
assert_po_progress_same(
    '2026-08-15',
    po_installment_business_date($bangkokBoundary),
    'Installment business date must use Asia/Bangkok across the UTC date boundary'
);

$approvedWithoutInstallments = po_installment_progress([
    'status' => 'approved',
    'installment_total' => 0,
    'installment_paid' => 0,
], $today);
assert_po_progress_same('approved', $approvedWithoutInstallments['key'], 'Non-installment PO must keep the approved state');
assert_po_progress_same('อนุมัติแล้ว', $approvedWithoutInstallments['label'], 'Non-installment PO must keep the approved label');
assert_po_progress_same(null, $approvedWithoutInstallments['percent'], 'Non-installment PO must not show a payment progress bar');

$waitingFirst = po_installment_progress([
    'status' => 'approved',
    'installment_total' => 3,
    'installment_paid' => 0,
    'next_installment_no' => 1,
    'next_installment_due_date' => '2026-08-20',
], $today);
assert_po_progress_same('installment_waiting', $waitingFirst['key'], 'Unpaid installment PO must show the next payment state');
assert_po_progress_same('รอชำระงวด 1/3', $waitingFirst['label'], 'First unpaid installment must be identified');
assert_po_progress_same(0, $waitingFirst['percent'], 'Unpaid installment PO must start at zero percent');

$inProgress = po_installment_progress([
    'status' => 'approved',
    'installment_total' => 3,
    'installment_paid' => 1,
    'next_installment_no' => 2,
    'next_installment_due_date' => '2026-08-20',
], $today);
assert_po_progress_same('installment_progress', $inProgress['key'], 'Partly paid PO must show payment progress');
assert_po_progress_same('ชำระแล้ว 1/3', $inProgress['label'], 'Partly paid PO must show the paid installment count');
assert_po_progress_same('รอชำระงวด 2', $inProgress['detail'], 'Partly paid PO must identify the next installment');
assert_po_progress_same(33, $inProgress['percent'], 'Progress percent must be rounded to a whole number');

$overdue = po_installment_progress([
    'status' => 'approved',
    'installment_total' => 3,
    'installment_paid' => 1,
    'next_installment_no' => 2,
    'next_installment_due_date' => '2026-08-14',
], $today);
assert_po_progress_same('installment_overdue', $overdue['key'], 'Past due unpaid installment must be overdue');
assert_po_progress_same('เกินกำหนด งวด 2/3', $overdue['label'], 'Overdue label must identify the delayed installment');

$complete = po_installment_progress([
    'status' => 'approved',
    'installment_total' => 3,
    'installment_paid' => 3,
    'next_installment_no' => null,
    'next_installment_due_date' => null,
], $today);
assert_po_progress_same('installment_complete', $complete['key'], 'Fully paid PO must be complete');
assert_po_progress_same('ชำระครบ 3/3', $complete['label'], 'Complete label must show all installments paid');
assert_po_progress_same(100, $complete['percent'], 'Complete installment PO must show one hundred percent');

echo "PO installment progress: PASS\n";
