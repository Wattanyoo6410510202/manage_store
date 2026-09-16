<?php
$index = file_get_contents(__DIR__ . '/../index.php');
$js = file_get_contents(__DIR__ . '/../assets/js/index.js');
$tutorial = file_get_contents(__DIR__ . '/../assets/js/tutorial.js');
$api = file_get_contents(__DIR__ . '/../api/process_customer.php');

$requiredFields = [
    'name="entity_type"',
    'name="default_vat_mode"',
    'name="default_wht_enabled"',
    'name="default_wht_percent"',
];

foreach ($requiredFields as $field) {
    if (strpos($index, $field) === false) {
        throw new RuntimeException("missing vendor field {$field}");
    }
}

foreach (['เพิ่ม Vendor', 'รายชื่อ Vendor', 'ชื่อ Vendor'] as $copy) {
    if (strpos($index . $js . $tutorial, $copy) === false) {
        throw new RuntimeException("missing Vendor copy: {$copy}");
    }
}

foreach (['entity_type', 'default_vat_mode', 'default_wht_enabled', 'default_wht_percent'] as $key) {
    if (strpos($js, $key) === false) {
        throw new RuntimeException("index.js does not preserve {$key}");
    }
}

if (strpos($api, "'vendor' => \$taxDefaults") !== false || strpos($api, "'vendor' => \$vendor") === false) {
    throw new RuntimeException('process_customer.php must return the complete server-processed vendor');
}

echo "vendor tax form: PASS\n";
