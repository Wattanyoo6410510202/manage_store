<?php
require_once __DIR__ . '/../config.php';

// Execute the real API validation/calculation blocks, excluding authorization,
// file uploads and permanent writes. DB CAST models the actual DECIMAL storage.
function calculate_api_tax(string $api, array $input): array {
    $source = file_get_contents(__DIR__ . '/../api/' . $api . '.php');
    if (!preg_match('/    if \(!array_key_exists\(\'wht_percent\'.*?(?=    \$(?:supplier_id|is_vat_enabled))/s', $source, $validation)
        || !preg_match('/    \$is_vat_enabled = .*?\$has_vat_sql = [^;]+;/s', $source, $calculation)) {
        throw new RuntimeException('Could not locate API tax blocks: ' . $api);
    }
    $_POST = $input;
    $contract_value = (float)$input['contract_value'];
    // Turn the endpoint's rejection exit into a catchable result in this harness.
    $code = str_replace('exit;', 'throw new RuntimeException("rejected", http_response_code());', $validation[0]);
    ob_start();
    try {
        eval($code . $calculation[0]);
    } finally {
        ob_end_clean();
    }
    return compact('wht_percent', 'total_wht_amount', 'net_contract_value', 'has_vat');
}

foreach (['save_project', 'update_project'] as $api) {
    foreach ([['3.004', 3.0, 300.0], ['3.006', 3.01, 301.0], ['0', 0.0, 0.0], ['100', 100.0, 10000.0], [null, 3.0, 300.0]] as [$raw, $percent, $amount]) {
        foreach ([['yes', '0', 10700, 0, 10700], ['yes', '1', 10000, 1, 10700], ['no', '1', 10000, null, 10000]] as [$vat, $mode, $contract, $hasVat, $gross]) {
            $input = ['contract_value' => $contract, 'include_vat' => $vat, 'vat_type_status' => $mode, 'include_wht' => 'yes'];
            if ($raw !== null) $input['wht_percent'] = $raw;
            $first = calculate_api_tax($api, $input);
            if ($first['wht_percent'] !== $percent || abs($first['total_wht_amount'] - $amount) > 0.000001
                || abs($first['net_contract_value'] - ($gross - $amount)) > 0.000001 || $first['has_vat'] !== $hasVat) {
                throw new RuntimeException("{$api}: precision/VAT calculation mismatch for " . json_encode($input) . ': ' . json_encode($first));
            }
            $stored = $conn->query('SELECT CAST(' . $first['wht_percent'] . ' AS DECIMAL(5,2)) AS percent')->fetch_assoc()['percent'];
            $second = calculate_api_tax($api, array_merge($input, ['wht_percent' => $stored]));
            if ($first !== $second) throw new RuntimeException("{$api}: DECIMAL roundtrip changes totals");
            $disabled = calculate_api_tax($api, array_merge($input, ['include_wht' => 'no']));
            if ((float)$disabled['total_wht_amount'] !== 0.0 || (float)$disabled['net_contract_value'] !== (float)$gross) {
                throw new RuntimeException("{$api}: disabled WHT changes totals");
            }
        }
    }
    foreach (['-0.004', '100.004', 'abc', [], '1e999'] as $raw) {
        try {
            calculate_api_tax($api, ['contract_value' => 10000, 'wht_percent' => $raw]);
            throw new RuntimeException("{$api}: accepted invalid WHT");
        } catch (RuntimeException $error) {
            if ($error->getCode() !== 422) throw $error;
        }
    }
}
echo "project WHT precision (both APIs, DECIMAL roundtrip): PASS\n";
