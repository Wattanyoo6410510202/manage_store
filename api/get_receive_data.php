<?php
require_once '../config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) { echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ']); exit; }

$pr_id = (int)($_GET['pr_id'] ?? 0);
if (!$pr_id) { echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสเอกสาร']); exit; }

$pr = mysqli_query($conn, "
    SELECT p.*, s.company_name as supplier_name, s.address as supplier_address
    FROM pr p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.id = $pr_id AND p.deleted_at IS NULL
")->fetch_assoc();

if (!$pr) { echo json_encode(['status' => 'error', 'message' => 'ไม่พบเอกสาร']); exit; }

$po = mysqli_query($conn, "
    SELECT id, doc_no FROM po
    WHERE reference_no = '{$pr['doc_no']}' AND deleted_at IS NULL
    LIMIT 1
")->fetch_assoc();

$pr_items = [];
$res = mysqli_query($conn, "SELECT * FROM pr_items WHERE pr_id = $pr_id ORDER BY id ASC");
while ($r = mysqli_fetch_assoc($res)) $pr_items[] = $r;

$po_items = [];
if ($po) {
    $res = mysqli_query($conn, "SELECT * FROM po_items WHERE po_id = {$po['id']} ORDER BY id ASC");
    while ($r = mysqli_fetch_assoc($res)) $po_items[] = $r;
}

echo json_encode([
    'status' => 'success',
    'pr' => $pr,
    'po' => $po,
    'pr_items' => $pr_items,
    'po_items' => $po_items
], JSON_UNESCAPED_UNICODE);
