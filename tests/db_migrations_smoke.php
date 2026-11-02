<?php
require_once __DIR__ . '/../db_migrations_lib.php';

function mig_assert($cond, string $label): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $label);
    }
}

// ทุกไฟล์ในรายการต้องมีอยู่ และต้อง "รันซ้ำได้" เพราะปุ่มอัปเดตอาจถูกกดหลายครั้ง
foreach (DB_MIGRATIONS as $file => $label) {
    $path = __DIR__ . '/../' . $file;
    mig_assert(is_file($path), "$file exists");
    $sql = preg_replace('/--[^\n]*/', '', file_get_contents($path));
    mig_assert(!preg_match('/CREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS)/i', $sql), "$file: CREATE TABLE uses IF NOT EXISTS");
    mig_assert(!preg_match('/ADD\s+COLUMN\s+(?!IF\s+NOT\s+EXISTS)/i', $sql), "$file: ADD COLUMN uses IF NOT EXISTS");
    mig_assert(!preg_match('/ADD\s+(UNIQUE\s+)?(KEY|INDEX)\s+(?!IF\s+NOT\s+EXISTS)/i', $sql), "$file: ADD KEY uses IF NOT EXISTS");
    mig_assert(!preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $sql), "$file: no destructive statements");
}

// ไฟล์ .sql ใหม่ในโฟลเดอร์ sql/ ต้องถูกเพิ่มเข้ารายการ
foreach (glob(__DIR__ . '/../sql/*.sql') as $path) {
    mig_assert(isset(DB_MIGRATIONS['sql/' . basename($path)]), 'sql/' . basename($path) . ' is listed in DB_MIGRATIONS');
}

$api = file_get_contents(__DIR__ . '/../api/db_migrate.php');
mig_assert(strpos($api, "!== 'admin'") !== false, 'API is admin-only');
mig_assert(strpos($api, 'hash_equals') !== false, 'API checks token');
mig_assert(strpos($api, "!== 'POST'") !== false, 'API requires POST');

echo "DB migrations: PASS\n";
