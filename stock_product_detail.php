<?php

require_once __DIR__ . '/stock_common.php';

header('Content-Type: application/json; charset=utf-8');

$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

if (!stock_can_view_overview((string)$stock_actor['role'])) {
    $respond(403, ['error' => 'คุณไม่มีสิทธิ์ดูรายละเอียดพัสดุ']);
}

$productId = (int)($_GET['product_id'] ?? 0);
$product = $productId > 0 ? stock_get_product_detail($conn, $productId) : null;
if (!$product) {
    $respond(404, ['error' => 'ไม่พบพัสดุ']);
}

$productSupId = (int)$product['sup_id'];
$visibleSupId = stock_visible_sup_id((string)$stock_actor['role'], (int)($stock_actor['sup_id'] ?? 0), $productSupId);
if ($visibleSupId !== $productSupId) {
    $respond(403, ['error' => 'คุณไม่มีสิทธิ์ดูพัสดุของบริษัทนี้']);
}

$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = 30;
$movements = stock_list_product_movements($conn, $productId, $limit, $offset);

$payload = [
    'movements' => $movements['rows'],
    'has_more' => $movements['has_more'],
    'next_offset' => $offset + count($movements['rows']),
];
if ($offset === 0) {
    $payload['product'] = $product;
    $payload['summary'] = stock_product_movement_summary($conn, $productId);
    $payload['open_withdrawals'] = array_map(static fn(array $row): array => $row + ['status_label' => stock_status_label((string)$row['status'])], stock_list_product_open_withdrawals($conn, $productId));
    $payload['top_requesters'] = stock_list_product_top_requesters($conn, $productId);
}

$respond(200, $payload);
