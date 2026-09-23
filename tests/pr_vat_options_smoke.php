<?php

function assert_same_values(array $expected, array $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual));
    }
}

function assert_contains(string $source, string $needle, string $label): void
{
    if (strpos($source, $needle) === false) {
        throw new RuntimeException($label . ': missing ' . $needle);
    }
}

$requestBuy = file_get_contents(__DIR__ . '/../request_buy.php');
$vatSelectPattern = '/<select\s+name="vat_percent".*?<\/select>/s';
if (!preg_match($vatSelectPattern, $requestBuy, $selectMatch)) {
    throw new RuntimeException('request_buy.php VAT selector not found');
}

preg_match_all('/<option\s+value="([^"]+)"/', $selectMatch[0], $optionMatches);
assert_same_values(['7', '0'], $optionMatches[1], 'request_buy.php VAT options');

$saveApi = file_get_contents(__DIR__ . '/../api/save_pr_new.php');
assert_contains($saveApi, 'in_array($vat_percent, [0.0, 7.0], true)', 'save_pr_new VAT allowlist');
assert_contains($saveApi, 'VAT must be 0% or 7%', 'save_pr_new invalid VAT response');

echo "PR VAT options: PASS\n";
