<?php
require_once __DIR__ . '/../supplier_display.php';

$expectedNames = [
    'MANONTA Budget Hotel' => 'บริษัท นรพล กรุ๊ป จำกัด',
    'NIJUNI' => 'บริษัท 2 ธันวา 2497 จำกัด',
    'SHotel Hatyai' => 'บริษัท ดับเบิ้ลเอส กรุ๊ป จำกัด',
];

foreach ($expectedNames as $source => $expected) {
    $actual = supplier_display_name($source);
    if ($actual !== $expected) {
        throw new RuntimeException("supplier_display_name failed for {$source}");
    }
}

$requestBuySource = file_get_contents(__DIR__ . '/../request_buy.php');
if ($requestBuySource === false) {
    throw new RuntimeException('Unable to read request_buy.php');
}

if (strpos($requestBuySource, 'supplier_display_name($sup[\'company_name\'])') === false) {
    throw new RuntimeException('request_buy.php does not use company display mapping for supplier options');
}

if (strpos($requestBuySource, "data.display_name || data.company_name") === false) {
    throw new RuntimeException('request_buy.php does not use the mapped company name in the supplier summary');
}

$milestoneSource = file_get_contents(__DIR__ . '/../view_milstones.php');
if ($milestoneSource === false) {
    throw new RuntimeException('Unable to read view_milstones.php');
}

$addressLabelPosition = strpos($milestoneSource, '<strong>ที่อยู่:</strong>');
$addressBlock = $addressLabelPosition === false ? '' : substr($milestoneSource, $addressLabelPosition, 400);
if ($addressLabelPosition === false || strpos($milestoneSource, "!empty(\$first['my_address'])") === false || strpos($milestoneSource, ": '-' ?>") === false || strpos($addressBlock, '<span style="color: #334155;">') === false) {
    throw new RuntimeException('view_milstones.php does not render a fallback address below the contractor name');
}

if (strpos($milestoneSource, '>ผู้รับจ้าง</p>') === false || strpos($milestoneSource, '>ผู้ว่าจ้าง</p>') === false) {
    throw new RuntimeException('view_milstones.php does not use the correct signature labels');
}

if (strpos($milestoneSource, '>ผู้จัดทำ</p>') !== false || strpos($milestoneSource, '>ลูกค้า</p>') !== false) {
    throw new RuntimeException('view_milstones.php still uses the old signature labels');
}

foreach (['add_project.php', 'edit_project.php'] as $projectForm) {
    $projectFormSource = file_get_contents(__DIR__ . '/../' . $projectForm);
    if ($projectFormSource === false) {
        throw new RuntimeException("Unable to read {$projectForm}");
    }

    if (strpos($projectFormSource, '<select name="bank_name"') === false) {
        throw new RuntimeException("{$projectForm} does not use a bank dropdown");
    }
}

echo "supplier display smoke: PASS\n";
