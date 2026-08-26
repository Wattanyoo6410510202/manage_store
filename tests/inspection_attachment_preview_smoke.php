<?php
require_once __DIR__ . '/../inspection_workflow.php';

$imageHtml = inspection_render_attachment_preview([
    'stored_name' => '../INS evidence 1.jpg',
    'original_name' => 'ภาพหน้างาน <1>.jpg',
    'mime_type' => 'image/jpeg',
]);

if (strpos($imageHtml, '<img ') === false) {
    fwrite(STDERR, "Image evidence was not rendered as an inline preview\n");
    exit(1);
}

if (strpos($imageHtml, 'uploads/inspections/INS%20evidence%201.jpg') === false) {
    fwrite(STDERR, "Image preview did not use a safe encoded attachment path\n");
    exit(1);
}

if (strpos($imageHtml, 'loading="lazy"') === false || strpos($imageHtml, 'target="_blank"') === false) {
    fwrite(STDERR, "Image preview must lazy-load and open the full image in a new tab\n");
    exit(1);
}

if (strpos($imageHtml, '<1>') !== false || strpos($imageHtml, '&lt;1&gt;') === false) {
    fwrite(STDERR, "Image preview did not escape the original filename\n");
    exit(1);
}

$documentHtml = inspection_render_attachment_preview([
    'stored_name' => 'inspection-report.pdf',
    'original_name' => 'รายงานตรวจ.pdf',
    'mime_type' => 'application/pdf',
]);

if (strpos($documentHtml, '<img ') !== false || strpos($documentHtml, 'รายงานตรวจ.pdf') === false) {
    fwrite(STDERR, "Non-image evidence must remain a named file link\n");
    exit(1);
}

echo "inspection attachment preview smoke: PASS\n";
