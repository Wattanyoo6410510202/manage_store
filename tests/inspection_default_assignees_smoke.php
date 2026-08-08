<?php
require_once __DIR__ . '/../inspection_workflow.php';

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$users = [
    ['id' => 24, 'username' => 'eng', 'name' => 'ชื่อช่างที่แก้ไขแล้ว', 'role' => 'tech_shotel'],
    ['id' => 12, 'username' => 'jane', 'name' => 'ชื่อผู้ตรวจ 2 ที่แก้ไขแล้ว', 'role' => 'procure'],
    ['id' => 31, 'username' => 'amn', 'name' => 'ชื่อจัดซื้อที่แก้ไขแล้ว', 'role' => 'procure'],
    ['id' => 41, 'username' => 'poy', 'name' => 'ชื่อ MD ที่แก้ไขแล้ว', 'role' => 'mgr'],
    ['id' => 21, 'username' => 'gmacc_current', 'name' => 'ชื่อ GMACC คนปัจจุบัน', 'role' => 'gmacc'],
];

$defaults = inspection_default_assignment_values($users);

assert_same(24, $defaults['inspector_1_user_id'], 'inspector 1 must resolve by username, not display name');
assert_same(12, $defaults['inspector_2_user_id'], 'inspector 2 must resolve by username, not display name');
assert_same(31, $defaults['procurement_user_id'], 'procurement must resolve by username, not display name');
assert_same(41, $defaults['md_user_id'], 'MD must resolve by username, not display name');
assert_same(21, $defaults['gmacc_user_id'], 'GMACC must resolve from the current gmacc role');

echo "inspection default assignees: PASS\n";
