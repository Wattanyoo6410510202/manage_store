<?php

function milestone_gross_amount(float $amount, float $vatAmount, int $hasVat): float
{
    $amount = max(0.0, $amount);
    $vatAmount = max(0.0, $vatAmount);

    // VAT inclusive (has_vat = 0) already includes VAT in the entered amount.
    // VAT exclusive (has_vat = 1) needs the VAT amount added to get the gross total.
    return $amount + ($hasVat === 1 ? $vatAmount : 0.0);
}

function project_gross_amount(float $contractValue, float $vatAmount, ?int $hasVat): float
{
    $contractValue = max(0.0, $contractValue);
    $vatAmount = max(0.0, $vatAmount);

    // VAT inclusive is already part of contract_value. Only VAT exclusive is added.
    return $contractValue + ($hasVat === 1 ? $vatAmount : 0.0);
}

function project_retention_amount(
    float $contractValue,
    float $projectVatAmount,
    ?int $projectHasVat,
    float $retentionPercent
): float {
    $retentionPercent = max(0.0, $retentionPercent);

    return round(
        project_gross_amount($contractValue, $projectVatAmount, $projectHasVat) * $retentionPercent / 100,
        2
    );
}
