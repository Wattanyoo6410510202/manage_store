<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inspection_workflow.php';

$context = inspection_load_context($conn, 117, 234);
$reference = (string)($context['project']['contract_reference_no'] ?? '');
if ($reference !== '1212131') {
    fwrite(STDERR, "linked document number was not selected as contract reference\n");
    exit(1);
}

$viewSource = file_get_contents(__DIR__ . '/../inspection_dynamic_view.php');
if (strpos($viewSource, '$contractReferenceNo') === false) {
    fwrite(STDERR, "inspection document does not use the contract reference field\n");
    exit(1);
}

echo "inspection contract reference smoke: PASS\n";
