<?php
// เพิ่มร้านค้าใหม่จากหน้า PR (ใบเทียบราคา) แล้วคืนค่าเป็น JSON
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

function quick_store_json(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    quick_store_json(405, ['status' => 'error', 'message' => 'Method not allowed']);
}
if (empty($_SESSION['user_id'])) {
    quick_store_json(401, ['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
}
if (($_SESSION['role'] ?? '') === 'viewer') {
    quick_store_json(403, ['status' => 'error', 'message' => 'ไม่มีสิทธิ์เพิ่มร้านค้า']);
}

$store_name = trim((string)($_POST['store_name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$contact_person = trim((string)($_POST['contact_person'] ?? ''));

if ($store_name === '') {
    quick_store_json(422, ['status' => 'error', 'message' => 'กรุณาระบุชื่อร้านค้า']);
}
$store_name = mb_substr($store_name, 0, 255);

// ถ้ามีร้านชื่อเดียวกันอยู่แล้ว ใช้ร้านเดิม ไม่สร้างซ้ำ
$stmt = $conn->prepare("SELECT id, store_name FROM stores WHERE store_name = ? LIMIT 1");
$stmt->bind_param('s', $store_name);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
if ($existing) {
    quick_store_json(200, ['status' => 'success', 'id' => (int)$existing['id'], 'store_name' => $existing['store_name'], 'existing' => true]);
}

$phone = mb_substr($phone, 0, 50);
$contact_person = mb_substr($contact_person, 0, 255);
$stmt = $conn->prepare("INSERT INTO stores (store_name, phone, contact_person) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $store_name, $phone, $contact_person);
$stmt->execute();

quick_store_json(200, ['status' => 'success', 'id' => (int)$conn->insert_id, 'store_name' => $store_name, 'existing' => false]);
