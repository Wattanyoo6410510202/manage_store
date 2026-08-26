<?php

function po_installment_business_date(?DateTimeInterface $instant = null): string
{
    $bangkok = new DateTimeZone('Asia/Bangkok');
    if ($instant === null) {
        return (new DateTimeImmutable('now', $bangkok))->format('Y-m-d');
    }

    return DateTimeImmutable::createFromInterface($instant)
        ->setTimezone($bangkok)
        ->format('Y-m-d');
}

function po_installment_progress(array $po, ?string $today = null): array
{
    $status = strtolower(trim((string)($po['status'] ?? 'pending')));
    $total = max(0, (int)($po['installment_total'] ?? 0));
    $paid = min($total, max(0, (int)($po['installment_paid'] ?? 0)));
    $nextNo = max(0, (int)($po['next_installment_no'] ?? 0));
    $nextDueDate = trim((string)($po['next_installment_due_date'] ?? ''));
    $today = $today ?: po_installment_business_date();

    $result = [
        'key' => 'approval_pending',
        'label' => 'รออนุมัติ',
        'detail' => null,
        'percent' => null,
        'paid' => $paid,
        'total' => $total,
        'next_no' => $nextNo ?: null,
        'next_due_date' => $nextDueDate ?: null,
    ];

    if ($status === 'cancelled') {
        $result['key'] = 'cancelled';
        $result['label'] = 'ยกเลิก';
        $result['next_no'] = null;
        $result['next_due_date'] = null;
        return $result;
    }

    if ($status !== 'approved') {
        $result['next_no'] = null;
        $result['next_due_date'] = null;
        return $result;
    }

    if ($total === 0) {
        $result['key'] = 'approved';
        $result['label'] = 'อนุมัติแล้ว';
        return $result;
    }

    $result['percent'] = (int)round(($paid / $total) * 100);

    if ($paid >= $total) {
        $result['key'] = 'installment_complete';
        $result['label'] = "ชำระครบ {$paid}/{$total}";
        $result['detail'] = 'ชำระเงินครบทุกงวดแล้ว';
        $result['next_no'] = null;
        $result['next_due_date'] = null;
        return $result;
    }

    if ($nextNo === 0) {
        $nextNo = min($total, $paid + 1);
        $result['next_no'] = $nextNo;
    }

    $isOverdue = $nextDueDate !== '' && $nextDueDate < $today;
    if ($isOverdue) {
        $result['key'] = 'installment_overdue';
        $result['label'] = "เกินกำหนด งวด {$nextNo}/{$total}";
        $result['detail'] = "ชำระแล้ว {$paid}/{$total}";
        return $result;
    }

    if ($paid === 0) {
        $result['key'] = 'installment_waiting';
        $result['label'] = "รอชำระงวด {$nextNo}/{$total}";
        return $result;
    }

    $result['key'] = 'installment_progress';
    $result['label'] = "ชำระแล้ว {$paid}/{$total}";
    $result['detail'] = "รอชำระงวด {$nextNo}";
    return $result;
}
