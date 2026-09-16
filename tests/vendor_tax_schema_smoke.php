<?php
require_once __DIR__ . '/../config.php';

function fail_schema(string $message): void {
    fwrite(STDERR, "vendor tax schema: FAIL - {$message}\n");
    exit(1);
}

function column_map(mysqli $conn, string $table): array {
    $result = $conn->query("SHOW COLUMNS FROM {$table}");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = $row;
    }
    return $columns;
}

$customers = column_map($conn, 'customers');
$projects = column_map($conn, 'projects');

$expectedCustomerColumns = [
    'entity_type' => "enum('juristic','individual')",
    'default_vat_mode' => "enum('none','exclusive','inclusive')",
    'default_wht_enabled' => 'tinyint(1)',
    'default_wht_percent' => 'decimal(5,2)',
];

foreach ($expectedCustomerColumns as $name => $type) {
    if (!isset($customers[$name]) || strtolower($customers[$name]['Type']) !== $type) {
        fail_schema("customers.{$name} must be {$type}");
    }
}

if (strtolower($projects['has_vat']['Type'] ?? '') !== 'tinyint(1)' || ($projects['has_vat']['Null'] ?? '') !== 'YES') {
    fail_schema('projects.has_vat must be nullable tinyint(1)');
}

$invalid = $conn->query("SELECT COUNT(*) AS total FROM projects WHERE has_vat IS NOT NULL AND has_vat NOT IN (0, 1)")->fetch_assoc();
if ((int)$invalid['total'] !== 0) {
    fail_schema('projects.has_vat contains values outside NULL/0/1');
}

echo "vendor tax schema: PASS\n";
