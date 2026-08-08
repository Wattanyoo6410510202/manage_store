<?php
$partialPath = __DIR__ . '/../partials/milestone_deduction_card.php';
if (!is_file($partialPath)) {
    fwrite(STDERR, "FAIL: shared milestone deduction card is missing\n");
    exit(1);
}

$deductionChecked = true;
$retentionPercentValue = 5;
$deductionNoteValue = 'เงินประกันผลงาน';

ob_start();
include $partialPath;
$html = ob_get_clean();

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="utf-8" ?><main>' . $html . '</main>');
libxml_clear_errors();
$xpath = new DOMXPath($dom);

$card = $xpath->query('//*[@id="deduction_card"]')->item(0);
if (!$card) {
    fwrite(STDERR, "FAIL: deduction card is not rendered\n");
    exit(1);
}

$gridClasses = 'grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4';
$title = trim($xpath->evaluate('string(.//label[@for="use_deduction"])', $card));
$toggle = $xpath->query('.//input[@id="use_deduction" and @checked]', $card)->item(0);
$percent = $xpath->query('.//input[@id="retention_percent" and @value="5"]', $card)->item(0);
$note = $xpath->query('.//input[@id="deduction_note" and @value="เงินประกันผลงาน"]', $card)->item(0);
$amount = $xpath->query('.//*[@id="deduction_total_display" and contains(concat(" ", normalize-space(@class), " "), " tabular-nums ")]', $card)->item(0);
$footer = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " border-t ") and contains(concat(" ", normalize-space(@class), " "), " pt-3 ")]', $card)->item(0);

if ($title !== 'เงินประกัน / หักอื่นๆ') {
    fwrite(STDERR, "FAIL: deduction title is not in the card header\n");
    exit(1);
}
if (!$toggle || !$percent || !$note || !$amount || !$footer) {
    fwrite(STDERR, "FAIL: deduction card controls do not follow the approved hierarchy\n");
    exit(1);
}
if (strpos($html, 'text-[9px]') !== false) {
    fwrite(STDERR, "FAIL: deduction card still uses unreadably small labels\n");
    exit(1);
}

foreach (['add_milestone.php', 'edit_milestone.php'] as $page) {
    $source = file_get_contents(__DIR__ . '/../' . $page);
    if (strpos($source, $gridClasses) === false || strpos($source, "include 'partials/milestone_deduction_card.php'") === false) {
        fwrite(STDERR, "FAIL: {$page} does not use the responsive shared deduction card\n");
        exit(1);
    }
}

echo "milestone deduction card layout: PASS\n";
