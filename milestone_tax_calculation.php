<?php

function milestone_gross_amount(float $amount, float $vatAmount, int $hasVat): float
{
    $amount = max(0.0, $amount);
    $vatAmount = max(0.0, $vatAmount);

    // VAT inclusive (has_vat = 0) already includes VAT in the entered amount.
    // VAT exclusive (has_vat = 1) needs the VAT amount added to get the gross total.
    return $amount + ($hasVat === 1 ? $vatAmount : 0.0);
}

function milestone_retention_amount(
    float $amount,
    float $vatAmount,
    int $hasVat,
    float $retentionPercent
): float {
    $retentionPercent = max(0.0, $retentionPercent);

    return round(
        milestone_gross_amount($amount, $vatAmount, $hasVat) * $retentionPercent / 100,
        2
    );
}
