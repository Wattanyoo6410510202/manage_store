<?php
require_once __DIR__ . '/../customer_tax_defaults.php';

function assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$juristic = customer_tax_defaults_from_input([
    'entity_type' => 'juristic',
    'default_vat_mode' => 'inclusive',
    'default_wht_enabled' => '1',
    'default_wht_percent' => '3.00',
]);
assert_same('juristic', $juristic['entity_type'], 'entity type');
assert_same('inclusive', $juristic['default_vat_mode'], 'VAT mode');
assert_same(1, $juristic['default_wht_enabled'], 'WHT enabled');
assert_same(3.0, $juristic['default_wht_percent'], 'WHT percent');

$defaults = customer_tax_defaults_from_input([]);
assert_same('juristic', $defaults['entity_type'], 'default entity type');
assert_same('none', $defaults['default_vat_mode'], 'default VAT mode');
assert_same(0, $defaults['default_wht_enabled'], 'default WHT enabled');
assert_same(3.0, $defaults['default_wht_percent'], 'default WHT percent');

foreach ([
    ['entity_type' => 'company'],
    ['default_vat_mode' => 'inside'],
    ['default_wht_percent' => '-1'],
    ['default_wht_percent' => '101'],
] as $invalid) {
    try {
        customer_tax_defaults_from_input($invalid);
        throw new RuntimeException('invalid payload must throw');
    } catch (InvalidArgumentException $expected) {
    }
}

echo "vendor tax defaults: PASS\n";
