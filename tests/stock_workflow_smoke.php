<?php

require_once __DIR__ . '/../stock_workflow.php';

function stock_assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function stock_assert_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (DomainException $exception) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\nExpected DomainException but none was thrown.\n");
    exit(1);
}

stock_assert_same(7, stock_visible_sup_id('staff_hr', 7, 12), 'employees must remain scoped to their own company');
stock_assert_same(12, stock_visible_sup_id('procure', 7, 12), 'procurement must be able to select every company');
stock_assert_same(12, stock_visible_sup_id('admin', 7, 12), 'admin must be able to select every company');
stock_assert_same(7, stock_visible_sup_id('gmhr', 7, 12), 'GMHR stock view must remain scoped outside reconciliation');

stock_assert_same(true, stock_can_manage('procure'), 'procurement must manage stock');
stock_assert_same(false, stock_can_manage('gmhr'), 'GMHR must not issue or receive stock');
stock_assert_same(true, stock_can_view_overview('procure'), 'procurement must see the Stock overview');
stock_assert_same(true, stock_can_view_overview('admin'), 'admin must see the Stock overview');
stock_assert_same(true, stock_can_view_overview('mgr'), 'MGR must see the Stock overview');
stock_assert_same(true, stock_can_view_overview('mgr2'), 'MGR2 must see the Stock overview');
stock_assert_same(false, stock_can_view_overview('gmhr'), 'other roles must not see the Stock overview');
stock_assert_same(false, stock_can_view_overview('staff'), 'employees must not see the Stock overview');
stock_assert_same(true, stock_can_approve_reconciliation('gmhr'), 'GMHR must approve reconciliation differences');
stock_assert_same(false, stock_can_approve_reconciliation('procure'), 'procurement must not approve its own reconciliation');

stock_assert_same(9, stock_receipt_sup_id(9, 9), 'PO receipt must use the PO owner company');
stock_assert_throws(fn () => stock_receipt_sup_id(9, 12), 'PO receipt must reject a different destination company');
stock_assert_same(2.0, stock_issue_discrepancy_quantity(5, 3), 'actual receipt shortage must become an explicit discrepancy');
stock_assert_throws(fn () => stock_issue_discrepancy_quantity(5, 6), 'actual received quantity must not exceed issued quantity');

stock_assert_same(5.0, stock_validate_movement_quantity('in_po', 5), 'PO receipt movement must be positive');
stock_assert_same(-2.0, stock_validate_movement_quantity('out_withdrawal', -2), 'withdrawal movement must be negative');
stock_assert_throws(fn () => stock_validate_movement_quantity('in_po', -1), 'PO receipt must not decrease stock');
stock_assert_throws(fn () => stock_validate_movement_quantity('out_withdrawal', 1), 'withdrawal must not increase stock');
stock_assert_throws(fn () => stock_validate_movement_quantity('return', -1), 'return must not decrease stock');

$generatedSkuA = stock_generate_internal_sku(5);
$generatedSkuB = stock_generate_internal_sku(5);
stock_assert_same(true, str_starts_with($generatedSkuA, 'STK-5-'), 'internal SKU must identify its company without user input');
stock_assert_same(false, $generatedSkuA === $generatedSkuB, 'automatic internal SKUs must be unique');

stock_assert_same(2.0, stock_issue_quantity(5, 2, 2), 'partial issue must allow the available quantity');
stock_assert_throws(fn () => stock_issue_quantity(5, 2, 3), 'issue must never exceed available stock');
stock_assert_same(2.0, stock_validate_withdrawal_request_quantity(2, 2), 'withdrawal request may use all available stock');
stock_assert_same(1.5, stock_validate_withdrawal_request_quantity(1.5, 2), 'withdrawal request may use part of available stock');
stock_assert_throws(fn () => stock_validate_withdrawal_request_quantity(3, 2), 'withdrawal request must not exceed available stock');
stock_assert_throws(fn () => stock_validate_withdrawal_request_quantity(1, 0), 'out-of-stock product must not be requested');
stock_assert_same(48.0, stock_convert_purchase_to_stock_quantity(2, 24), 'two cases of 24 must add 48 base units');
stock_assert_same(2.5, stock_convert_purchase_to_stock_quantity(1, 2.5), 'conversion may produce a fractional base quantity');
stock_assert_throws(fn () => stock_convert_purchase_to_stock_quantity(1, 0), 'conversion factor must be positive');
stock_assert_throws(fn () => stock_convert_purchase_to_stock_quantity(0, 24), 'received purchase quantity must be positive');
stock_assert_throws(fn () => stock_issue_quantity(2, 10, 3), 'issue must never exceed the remaining request');
stock_assert_throws(fn () => stock_issue_quantity(5, 5, 0), 'issue quantity must be positive');

stock_assert_same(3.0, stock_po_receivable_remaining(5, 2), 'PO receipt must keep the unreceived quantity');
stock_assert_throws(fn () => stock_po_receivable_remaining(5, 6), 'PO receipt total must never exceed ordered quantity');

stock_assert_same('waiting_issue', stock_withdrawal_status(5, 0, 0, 0), 'new request must wait for issue');
stock_assert_same('waiting_confirmation', stock_withdrawal_status(5, 2, 0, 0), 'issued stock must wait for requester confirmation');
stock_assert_same('partially_fulfilled', stock_withdrawal_status(5, 2, 2, 0), 'confirmed partial issue must keep the same request open');
stock_assert_same('completed', stock_withdrawal_status(5, 5, 5, 0), 'fully received request must complete');
stock_assert_same('completed', stock_withdrawal_status(5, 2, 2, 3), 'cancelling the remainder after partial receipt must complete');
stock_assert_same('cancelled', stock_withdrawal_status(5, 0, 0, 5), 'fully cancelled request must be cancelled');
stock_assert_throws(fn () => stock_withdrawal_status(5, 4, 5, 0), 'received quantity must never exceed issued quantity');

stock_assert_same(-3.0, stock_reconciliation_difference(5, 2), 'short stock must produce a negative difference');
stock_assert_same(4.0, stock_reconciliation_difference(5, 9), 'surplus stock must produce a positive difference');
stock_assert_same(false, stock_reconciliation_requires_approval(5, 5), 'matching physical count must close without approval');
stock_assert_same(true, stock_reconciliation_requires_approval(5, 2), 'a difference must require GMHR approval');

stock_assert_same(true, stock_can_view_withdrawal('procure', 9, 1, 44, 7), 'procurement must see withdrawals from every company');
stock_assert_same(true, stock_can_view_withdrawal('staff_hr', 9, 7, 9, 7), 'requester must see their own withdrawal in their company');
stock_assert_same(false, stock_can_view_withdrawal('staff_hr', 9, 7, 10, 7), 'employee must not see another employee withdrawal');
stock_assert_same(false, stock_can_view_withdrawal('staff_hr', 9, 7, 9, 8), 'employee must not cross company scope');

stock_assert_same(true, stock_can_view_reconciliation('gmhr', 9, 15), 'GMHR must see reconciliation from every company');
stock_assert_same(true, stock_can_view_reconciliation('procure', 9, 15), 'procurement must see reconciliation from every company');
stock_assert_same(false, stock_can_view_reconciliation('staff_hr', 9, 9), 'employees must not access reconciliation reports');

echo "Stock workflow smoke tests passed.\n";
