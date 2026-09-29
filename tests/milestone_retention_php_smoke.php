<?php
require_once __DIR__ . '/../milestone_tax_calculation.php';

function assert_same_number(float $expected, float $actual, string $label): void
{
    if (abs($expected - $actual) > 0.00001) {
        throw new RuntimeException($label . ': expected ' . $expected . ', got ' . $actual);
    }
}

$cases = [
    ['Project VAT inclusive', 100000, 6542.06, 0, 5, 100000, 5000],
    ['Project VAT exclusive', 100000, 7000, 1, 5, 107000, 5350],
    ['Project without VAT', 100000, 0, null, 5, 100000, 5000],
];

foreach ($cases as [$label, $contractValue, $projectVatAmount, $projectHasVat, $percent, $expectedGross, $expectedRetention]) {
    assert_same_number(
        $expectedGross,
        project_gross_amount($contractValue, $projectVatAmount, $projectHasVat),
        $label . ' gross amount'
    );
    assert_same_number(
        $expectedRetention,
        project_retention_amount($contractValue, $projectVatAmount, $projectHasVat, $percent),
        $label . ' retention'
    );
}

foreach (['api/save_milestone.php', 'api/update_milestone_full.php'] as $api) {
    $source = file_get_contents(__DIR__ . '/../' . $api);
    if (strpos($source, "require_once '../milestone_tax_calculation.php';") === false
        || strpos($source, 'project_retention_amount(') === false
        || strpos($source, '$use_deduction') === false) {
        throw new RuntimeException($api . ' does not use the shared project-value retention calculation');
    }
}

$partial = file_get_contents(__DIR__ . '/../partials/milestone_deduction_card.php');
if (strpos($partial, 'name="use_deduction"') === false) {
    throw new RuntimeException('retention checkbox is not submitted to the milestone APIs');
}

echo "milestone retention PHP project-value calculation: PASS\n";
