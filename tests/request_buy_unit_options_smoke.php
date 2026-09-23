<?php

$source = file_get_contents(__DIR__ . '/../request_buy.php');
$expectedOption = '<option value="งาน">งาน</option>';
$optionCount = substr_count($source, $expectedOption);

if ($optionCount !== 2) {
    throw new RuntimeException('request_buy.php must expose งาน in both the initial and dynamic item-unit selectors; found ' . $optionCount);
}

echo "request_buy unit options: PASS\n";
