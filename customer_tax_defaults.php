<?php
function customer_tax_defaults_from_input(array $input): array {
    $entityType = (string)($input['entity_type'] ?? 'juristic');
    $vatMode = (string)($input['default_vat_mode'] ?? 'none');
    $whtEnabled = (string)($input['default_wht_enabled'] ?? '0') === '1' ? 1 : 0;
    $rawPercent = $input['default_wht_percent'] ?? '3.00';

    if (!in_array($entityType, ['juristic', 'individual'], true)) {
        throw new InvalidArgumentException('ประเภท Vendor ไม่ถูกต้อง');
    }
    if (!in_array($vatMode, ['none', 'exclusive', 'inclusive'], true)) {
        throw new InvalidArgumentException('รูปแบบ VAT ไม่ถูกต้อง');
    }
    if (!is_numeric($rawPercent)) {
        throw new InvalidArgumentException('อัตราหัก ณ ที่จ่ายต้องเป็นตัวเลข');
    }

    $whtPercent = (float)$rawPercent;
    if (!is_finite($whtPercent) || $whtPercent < 0 || $whtPercent > 100) {
        throw new InvalidArgumentException('อัตราหัก ณ ที่จ่ายต้องอยู่ระหว่าง 0 ถึง 100');
    }

    return [
        'entity_type' => $entityType,
        'default_vat_mode' => $vatMode,
        'default_wht_enabled' => $whtEnabled,
        'default_wht_percent' => round($whtPercent, 2),
    ];
}
