<?php

function stock_is_global_manager(string $role): bool
{
    return in_array($role, ['procure', 'admin'], true);
}

function stock_visible_sup_id(string $role, int $actorSupId, int $requestedSupId = 0): int
{
    if (stock_is_global_manager($role) && $requestedSupId > 0) {
        return $requestedSupId;
    }

    return $actorSupId;
}

function stock_can_manage(string $role): bool
{
    return stock_is_global_manager($role);
}

function stock_can_view_overview(string $role): bool
{
    return in_array($role, ['procure', 'admin', 'mgr', 'mgr2'], true);
}

function stock_can_approve_reconciliation(string $role): bool
{
    return in_array($role, ['gmhr', 'admin'], true);
}

function stock_receipt_sup_id(int $poSupId, int $requestedSupId): int
{
    if ($poSupId <= 0 || $requestedSupId !== $poSupId) {
        throw new DomainException('บริษัทปลายทางต้องตรงกับบริษัทเจ้าของ PO');
    }
    return $poSupId;
}

function stock_issue_discrepancy_quantity(float $issued, float $received): float
{
    if ($issued <= 0 || $received < 0 || $received > $issued + 0.00001) {
        throw new DomainException('จำนวนรับจริงไม่ถูกต้อง');
    }
    return round(max(0, $issued - $received), 2);
}

function stock_validate_movement_quantity(string $movementType, float $quantity): float
{
    if (!in_array($movementType, ['in_po', 'out_withdrawal', 'adjustment', 'return'], true) || abs($quantity) < 0.00001) {
        throw new DomainException('ประเภทหรือจำนวนการเคลื่อนไหวไม่ถูกต้อง');
    }
    if (in_array($movementType, ['in_po', 'return'], true) && $quantity < 0) {
        throw new DomainException('รายการรับเข้าต้องเพิ่มยอด Stock');
    }
    if ($movementType === 'out_withdrawal' && $quantity > 0) {
        throw new DomainException('รายการเบิกต้องลดยอด Stock');
    }
    return round($quantity, 2);
}

function stock_generate_internal_sku(int $supId): string
{
    if ($supId <= 0) {
        throw new DomainException('ไม่พบบริษัทสำหรับสร้างรหัสพัสดุ');
    }
    return sprintf('STK-%d-%s-%s', $supId, date('ymdHis'), strtoupper(bin2hex(random_bytes(3))));
}

function stock_issue_quantity(float $remainingRequested, float $available, float $requestedIssue): float
{
    if ($requestedIssue <= 0) {
        throw new DomainException('จำนวนที่จ่ายต้องมากกว่า 0');
    }
    if (abs($requestedIssue - round($requestedIssue)) > 0.00001) {
        throw new DomainException('จำนวนที่จ่ายต้องเป็นจำนวนเต็ม');
    }
    if ($requestedIssue > $remainingRequested + 0.00001) {
        throw new DomainException('จำนวนที่จ่ายเกินจำนวนที่ยังค้างในใบเบิก');
    }
    if ($requestedIssue > $available + 0.00001) {
        throw new DomainException('จำนวนพัสดุใน Stock ไม่เพียงพอ');
    }

    return (float)round($requestedIssue);
}

function stock_validate_withdrawal_request_quantity(float $requested, float $available): float
{
    if ($requested <= 0) {
        throw new DomainException('จำนวนที่ขอเบิกต้องมากกว่า 0');
    }
    if (abs($requested - round($requested)) > 0.00001) {
        throw new DomainException('จำนวนที่ขอเบิกต้องเป็นจำนวนเต็ม');
    }
    if ($available <= 0 || $requested > $available + 0.00001) {
        throw new DomainException('จำนวนที่ขอเบิกเกินยอดคงเหลือใน Stock');
    }

    return (float)round($requested);
}

function stock_validate_withdrawal_received_quantity(float $received, float $issued): float
{
    if ($received < 0 || $received > $issued + 0.00001) {
        throw new DomainException('จำนวนที่ยืนยันรับไม่ถูกต้อง');
    }
    if (abs($received - round($received)) > 0.00001) {
        throw new DomainException('จำนวนที่ยืนยันรับต้องเป็นจำนวนเต็ม');
    }

    return (float)round($received);
}

function stock_format_withdrawal_quantity(float $quantity): string
{
    return number_format($quantity, 0);
}

function stock_convert_purchase_to_stock_quantity(float $purchaseQuantity, float $unitsPerPurchaseUnit): float
{
    if ($purchaseQuantity <= 0) {
        throw new DomainException('จำนวนรับพัสดุต้องมากกว่า 0');
    }
    if ($unitsPerPurchaseUnit <= 0) {
        throw new DomainException('จำนวนหน่วย Stock ต่อหน่วยซื้อต้องมากกว่า 0');
    }

    $stockQuantity = round($purchaseQuantity * $unitsPerPurchaseUnit, 2);
    if ($stockQuantity <= 0) {
        throw new DomainException('จำนวนที่แปลงเข้า Stock ไม่ถูกต้อง');
    }
    return $stockQuantity;
}

function stock_po_receivable_remaining(float $ordered, float $received): float
{
    if ($ordered < 0 || $received < 0 || $received > $ordered + 0.00001) {
        throw new DomainException('ยอดรับพัสดุจาก PO ไม่ถูกต้อง');
    }

    return round(max(0, $ordered - $received), 2);
}

function stock_withdrawal_status(float $requested, float $issued, float $received, float $cancelled): string
{
    if ($requested <= 0 || min($issued, $received, $cancelled) < 0) {
        throw new DomainException('ยอดใบเบิกไม่ถูกต้อง');
    }
    if ($received > $issued + 0.00001 || $issued + $cancelled > $requested + 0.00001) {
        throw new DomainException('ยอดใบเบิกไม่สัมพันธ์กัน');
    }
    if ($cancelled >= $requested - 0.00001 && $received <= 0.00001) {
        return 'cancelled';
    }
    if ($received + $cancelled >= $requested - 0.00001) {
        return 'completed';
    }
    if ($issued > $received + 0.00001) {
        return 'waiting_confirmation';
    }
    if ($received > 0.00001) {
        return 'partially_fulfilled';
    }

    return 'waiting_issue';
}

function stock_reconciliation_difference(float $systemQuantity, float $physicalQuantity): float
{
    if ($systemQuantity < 0 || $physicalQuantity < 0) {
        throw new DomainException('ยอด Stock ต้องไม่ติดลบ');
    }

    return round($physicalQuantity - $systemQuantity, 2);
}

function stock_reconciliation_requires_approval(float $systemQuantity, float $physicalQuantity): bool
{
    return abs(stock_reconciliation_difference($systemQuantity, $physicalQuantity)) > 0.00001;
}

function stock_can_view_withdrawal(
    string $role,
    int $actorUserId,
    int $actorSupId,
    int $requesterUserId,
    int $requestSupId
): bool {
    if (stock_is_global_manager($role)) {
        return true;
    }

    return $actorUserId > 0
        && $actorUserId === $requesterUserId
        && $actorSupId > 0
        && $actorSupId === $requestSupId;
}

function stock_can_view_reconciliation(string $role, int $actorSupId, int $reportSupId): bool
{
    return in_array($role, ['procure', 'gmhr', 'admin'], true);
}

function stock_status_label(string $status): string
{
    return [
        'waiting_issue' => 'รอจ่ายพัสดุ',
        'waiting_confirmation' => 'รอยืนยันรับพัสดุ',
        'discrepancy' => 'พบผลต่างรอตรวจสอบ',
        'partially_fulfilled' => 'รับแล้วบางส่วน',
        'completed' => 'เสร็จสิ้น',
        'cancelled' => 'ยกเลิก',
        'pending_approval' => 'รอ GMHR อนุมัติ',
        'approved' => 'อนุมัติแล้ว',
        'rejected' => 'ไม่อนุมัติ',
        'matched' => 'ยอดตรง',
    ][$status] ?? $status;
}
