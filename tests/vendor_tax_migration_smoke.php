<?php
require_once __DIR__ . '/../config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Connection-local tables shadow the real names. The actual migration can run
// unchanged without writing any application records or altering permanent tables.
$conn->query('CREATE TEMPORARY TABLE customers (id INT PRIMARY KEY, customer_name VARCHAR(100))');
$conn->query('CREATE TEMPORARY TABLE projects (id INT PRIMARY KEY, has_vat VARCHAR(10) NULL, contract_value DECIMAL(12,2), total_vat_amount DECIMAL(12,2))');
$conn->query("INSERT INTO customers VALUES (1, 'Legacy default check')");
$conn->query("INSERT INTO projects VALUES
    (1, NULL, 10000, 700),
    (2, '0', 10000, 700),
    (3, '1', 0, 0),
    (4, 'no', 10000, 0),
    (5, 'yes', 10000, 700),
    (6, '', 10700, 700),
    (7, 'yes', 10700, 700)");
$sql = file_get_contents(__DIR__ . '/../add_vendor_tax_defaults.sql');
$expected = [1 => null, 2 => 0, 3 => 1, 4 => null, 5 => 1, 6 => 0, 7 => 0];
for ($run = 1; $run <= 2; $run++) {
    $conn->multi_query($sql);
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    $actual = [];
    foreach ($conn->query('SELECT id, has_vat FROM projects ORDER BY id') as $row) {
        $actual[(int)$row['id']] = $row['has_vat'] === null ? null : (int)$row['has_vat'];
    }
    if ($actual !== $expected) {
        throw new RuntimeException("migration run {$run} changed VAT meanings: " . json_encode($actual));
    }
    $vendor = $conn->query('SELECT * FROM customers WHERE id = 1')->fetch_assoc();
    if ($vendor['entity_type'] !== 'juristic' || $vendor['default_vat_mode'] !== 'none'
        || (int)$vendor['default_wht_enabled'] !== 0 || $vendor['default_wht_percent'] !== '3.00') {
        throw new RuntimeException('legacy Vendor defaults changed');
    }
    $column = $conn->query("SHOW COLUMNS FROM projects LIKE 'has_vat'")->fetch_assoc();
    if ($column['Type'] !== 'tinyint(1)' || $column['Null'] !== 'YES' || $column['Default'] !== null) {
        throw new RuntimeException('migrated VAT schema contract changed');
    }
}
$conn->close(); // Also discards both temporary tables, including on process exit.
echo "vendor tax migration (two executions, temporary tables only): PASS\n";
