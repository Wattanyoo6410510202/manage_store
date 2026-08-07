<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inspection_workflow.php';

if (!function_exists('inspection_payment_summary')) {
    fwrite(STDERR, "payment summary helper is missing\n");
    exit(1);
}

$summary = inspection_payment_summary([
    'milestone_amount' => 10000,
    'vat_amount' => 700,
    'wht_percent' => 3,
    'wht_amount' => 300,
    'retention_percent' => 0,
    'retention_amount' => 0,
    'other_deduction_amount' => 0,
    'total_request_amount' => 10400,
    'net_amount' => 10400,
    'payment_days_after_acceptance' => null,
]);

if ((float)$summary['wht_percent'] !== 3.0 || (float)$summary['retention_percent'] !== 0.0) {
    fwrite(STDERR, "payment deduction defaults failed\n");
    exit(1);
}
if ((float)$summary['wht_amount'] !== 300.0 || (float)$summary['net_transfer_amount'] !== 10400.0) {
    fwrite(STDERR, "payment amount summary failed\n");
    exit(1);
}

$viewSource = file_get_contents(__DIR__ . '/../inspection_dynamic_view.php');
foreach (['ยอดโอนสุทธิ', 'Retention', 'payment_days_after_acceptance'] as $needle) {
    if (strpos($viewSource, $needle) === false) {
        fwrite(STDERR, "payment document field missing: {$needle}\n");
        exit(1);
    }
}

$apiSource = file_get_contents(__DIR__ . '/../api/update_milestone_status.php');
foreach (['paid_at', 'paid_amount', 'payment_reference', 'payment_bank'] as $needle) {
    if (strpos($apiSource, $needle) === false) {
        fwrite(STDERR, "payment transfer field missing in API: {$needle}\n");
        exit(1);
    }
}

$detailSource = file_get_contents(__DIR__ . '/../detail_project.php');
if (strpos($detailSource, 'dynamic_status') === false || strpos($detailSource, 'updatePaymentStatus') === false) {
    fwrite(STDERR, "payment acceptance gate is missing\n");
    exit(1);
}

echo "inspection payment transfer smoke: PASS\n";
