<?php
declare(strict_types=1);

function storeBridgeSign(string $key, int $timestamp, string $body): string
{
    return hash_hmac('sha256', $timestamp . "\n" . $body, $key);
}

function storeBridgeVerify(string $key, string $timestamp, string $body, string $signature, ?int $now = null): void
{
    if (strlen($key) < 32 || !ctype_digit($timestamp) || abs(($now ?? time()) - (int)$timestamp) > 300
        || !hash_equals(storeBridgeSign($key, (int)$timestamp, $body), $signature)) {
        throw new InvalidArgumentException('ไม่สามารถยืนยันการเชื่อมต่อได้');
    }
}

function storeBridgeQuantities(array $quantities): array
{
    $result = [];
    foreach ($quantities as $product => $quantity) {
        if (!is_scalar($quantity) || !preg_match('/^[1-9][0-9]*$/D', (string)$product)
            || !preg_match('/^[0-9]{1,7}$/D', (string)$quantity)) {
            throw new InvalidArgumentException('จำนวนอะไหล่ต้องเป็นจำนวนเต็มตั้งแต่ 0 ถึง 9,999,999');
        }
        if ((int)$quantity > 0) { $result[(int)$product] = ['product_id'=>(int)$product, 'quantity'=>(int)$quantity]; }
    }
    if ($result === []) { throw new InvalidArgumentException('กรุณาเลือกอะไหล่อย่างน้อยหนึ่งรายการ'); }
    if (count($result) > 100) { throw new InvalidArgumentException('เลือกอะไหล่ได้ไม่เกิน 100 รายการ'); }
    ksort($result);
    return array_values($result);
}
