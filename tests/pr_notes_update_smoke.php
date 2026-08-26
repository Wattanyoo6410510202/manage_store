<?php

$source = file_get_contents(__DIR__ . '/../api/update_pr_new.php');
if ($source === false) {
    fwrite(STDERR, "FAIL: cannot read PR update endpoint\n");
    exit(1);
}

if (!preg_match('/\$stmt->bind_param\(\s*"([isdb]+)"\s*,\s*\$supplier_id/s', $source, $match)) {
    fwrite(STDERR, "FAIL: cannot find PR header binding definition\n");
    exit(1);
}

$expectedTypes = [
    'supplier_id' => 'i',
    'store_id' => 'i',
    'customer_id' => 'i',
    'is_internal' => 'i',
    'due_date' => 's',
    'priority' => 's',
    'reference_no' => 's',
    'payment_term' => 's',
    'payment_method' => 's',
    'installment_period' => 'i',
    'payment_slip' => 's',
    'requested_by' => 's',
    'contact_tel' => 's',
    'notes' => 's',
    'expense_cat_id' => 'i',
    'budget_type_id' => 'i',
    'objective_id' => 'i',
    'budget_limit_type' => 's',
    'budget_amount' => 'd',
    'budget_details' => 's',
    'expectation' => 's',
    'practice_method' => 's',
    'subtotal' => 'd',
    'vat' => 'd',
    'vat_percent' => 'd',
    'wht_percent' => 'd',
    'wht_amount' => 'd',
    'grand_total' => 'd',
    'attachment_1' => 's',
    'attachment_2' => 's',
    'pr_id' => 'i',
];

$actualTypes = $match[1];
if (strlen($actualTypes) !== count($expectedTypes)) {
    fwrite(STDERR, "FAIL: PR header binding has an unexpected number of parameter types\n");
    exit(1);
}

foreach (array_values($expectedTypes) as $index => $expectedType) {
    if ($actualTypes[$index] !== $expectedType) {
        $field = array_keys($expectedTypes)[$index];
        fwrite(STDERR, "FAIL: {$field} must be bound as {$expectedType}; received {$actualTypes[$index]}\n");
        exit(1);
    }
}

echo "PR notes update binding: PASS\n";
