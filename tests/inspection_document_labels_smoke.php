<?php

require_once __DIR__ . '/../config.php';

function inspection_document_labels_fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$round = mysqli_query($conn, "SELECT id FROM inspection_rounds ORDER BY id DESC LIMIT 1")->fetch_assoc();
if (!$round) {
    inspection_document_labels_fail('no inspection round fixture is available');
}

$dynamic_round_id = (int)$round['id'];
ob_start();
include __DIR__ . '/../inspection_dynamic_view.php';
$html = ob_get_clean();

$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
libxml_clear_errors();

$xpath = new DOMXPath($document);
$headings = $xpath->query('//main[@id="dynamic-inspection-document"]//h1');
if (!$headings || $headings->length !== 1 || trim((string)$headings->item(0)->textContent) !== 'ใบส่งมอบและตรวจรับงาน') {
    inspection_document_labels_fail('the document heading is not the requested handover and acceptance title');
}

$managerRoles = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " signature-role ")][normalize-space(.)="OA&HR Manager"]');
if (!$managerRoles || $managerRoles->length !== 1) {
    inspection_document_labels_fail('the MD signature position is not labeled OA&HR Manager');
}

echo "inspection document labels: PASS\n";
