<?php
require_once __DIR__ . '/../milestone_tax_calculation.php';

function assert_same_number(float $expected, float $actual, string $label): void
{
    if (abs($expected - $actual) > 0.00001) {
        throw new RuntimeException($label . ': expected ' . $expected . ', got ' . $actual);
    }
}

$cases = [
    ['VAT inclusive', 10000, 700, 0, 5, 10000, 500],
    ['VAT exclusive', 10000, 700, 1, 5, 10700, 535],
    ['No VAT', 10000, 0, 1, 5, 10000, 500],
];

foreach ($cases as [$label, $amount, $vatAmount, $hasVat, $percent, $expectedGross, $expectedRetention]) {
    assert_same_number($expectedGross, milestone_gross_amount($amount, $vatAmount, $hasVat), $label . ' gross amount');
    assert_same_number($expectedRetention, milestone_retention_amount($amount, $vatAmount, $hasVat, $percent), $label . ' retention');
}

foreach (['api/save_milestone.php', 'api/update_milestone_full.php'] as $api) {
    $source = file_get_contents(__DIR__ . '/../' . $api);
    if (strpos($source, "require_once '../milestone_tax_calculation.php';") === false
        || strpos($source, 'milestone_retention_amount(') === false
        || strpos($source, '$use_deduction') === false) {
        throw new RuntimeException($api . ' does not use the shared gross-base retention calculation');
    }
}

$partial = file_get_contents(__DIR__ . '/../partials/milestone_deduction_card.php');
if (strpos($partial, 'name="use_deduction"') === false) {
    throw new RuntimeException('retention checkbox is not submitted to the milestone APIs');
}

echo "milestone retention PHP gross-base calculation: PASS\n";
