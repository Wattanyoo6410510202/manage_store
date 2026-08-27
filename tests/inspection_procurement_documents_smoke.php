<?php

function inspection_procurement_documents_fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$helperPath = __DIR__ . '/../inspection_document_options.php';
if (!is_file($helperPath)) {
    inspection_procurement_documents_fail('document selection helper is missing');
}
require_once $helperPath;

$options = inspection_document_options();
$expectedKeys = ['boq', 'drawing', 'progress', 'site_photo', 'invoice', 'other'];
if (array_keys($options) !== $expectedKeys) {
    inspection_procurement_documents_fail('document options do not preserve the expected order');
}

$selection = inspection_normalize_document_selection('["boq","other","boq","unknown"]', 'หนังสือรับรองการส่งมอบงาน');
if ($selection !== [
    'selected' => ['boq', 'other'],
    'other_detail' => 'หนังสือรับรองการส่งมอบงาน',
]) {
    inspection_procurement_documents_fail('document selection normalization failed');
}

$withoutOther = inspection_normalize_document_selection(['invoice'], 'ข้อความนี้ต้องถูกล้าง');
if ($withoutOther !== ['selected' => ['invoice'], 'other_detail' => '']) {
    inspection_procurement_documents_fail('other document detail was retained when other was not selected');
}

$formSource = file_get_contents(__DIR__ . '/../inspection_dynamic_form.php');
$viewSource = file_get_contents(__DIR__ . '/../inspection_dynamic_view.php');
$apiSource = file_get_contents(__DIR__ . '/../api/inspection_round.php');
$migrationSource = file_get_contents(__DIR__ . '/../add_inspection_approval_documents.sql');
if ($formSource === false || $viewSource === false || $apiSource === false || $migrationSource === false) {
    inspection_procurement_documents_fail('unable to read inspection feature sources');
}

foreach (['id="procurementAttachmentChecklist"', 'other_document_detail', 'selected_documents'] as $marker) {
    if (strpos($formSource, $marker) === false) {
        inspection_procurement_documents_fail("procurement form is missing {$marker}");
    }
}
foreach (['gmacc', 'signature_snapshot', 'selected_documents_json', 'inspection_document_options'] as $marker) {
    if (strpos($viewSource, $marker) === false) {
        inspection_procurement_documents_fail("inspection document is missing {$marker}");
    }
}
foreach (['grid-template-columns: repeat(6, minmax(0, 1fr))', 'signature:nth-child(4)', 'signature:nth-child(5)', 'font-size: 10.5pt', 'line-height: 1.35', 'padding: 8mm 11mm 7mm', 'min-height: 0'] as $marker) {
    if (strpos($viewSource, $marker) === false) {
        inspection_procurement_documents_fail("signature layout is missing {$marker}");
    }
}
foreach (['inspection_normalize_document_selection', 'selected_documents_json', 'other_document_detail'] as $marker) {
    if (strpos($apiSource, $marker) === false) {
        inspection_procurement_documents_fail("inspection API is missing {$marker}");
    }
}
if (stripos($migrationSource, 'selected_documents_json') === false || stripos($migrationSource, 'inspection_approvals') === false) {
    inspection_procurement_documents_fail('inspection approval document migration is missing');
}

echo "inspection procurement documents: PASS\n";
