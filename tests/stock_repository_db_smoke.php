<?php

session_save_path(sys_get_temp_dir());

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../stock_repository.php';

function stock_db_fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$actor = $conn->query("SELECT id, sup_id FROM users WHERE sup_id IS NOT NULL ORDER BY id LIMIT 1")->fetch_assoc();
if (!$actor) {
    stock_db_fail('test database needs one user with a company');
}

$conn->begin_transaction();
try {
    $customer = $conn->query('SELECT id FROM customers ORDER BY id LIMIT 1')->fetch_assoc();
    if (!$customer) {
        stock_db_fail('test database needs one customer for PO receipt filtering');
    }
    $poDoc = 'PO-TDD-FILTER-' . bin2hex(random_bytes(3));
    $approvedStatus = 'approved';
    $poStmt = $conn->prepare('INSERT INTO po (doc_no, customer_id, supplier_id, status, created_by) VALUES (?, ?, ?, ?, ?)');
    $poStmt->bind_param('siisi', $poDoc, $customer['id'], $actor['sup_id'], $approvedStatus, $actor['id']);
    $poStmt->execute();
    $receivablePoId = (int)$conn->insert_id;
    $poStmt->close();
    $poItemName = 'รายการทดสอบกรอง PO';
    $poUnit = 'ชิ้น';
    $poItemStmt = $conn->prepare('INSERT INTO po_items (po_id, item_desc, item_qty, item_unit) VALUES (?, ?, 5, ?)');
    $poItemStmt->bind_param('iss', $receivablePoId, $poItemName, $poUnit);
    $poItemStmt->execute();
    $receivablePoItemId = (int)$conn->insert_id;
    $poItemStmt->close();

    if (!in_array($receivablePoId, array_map('intval', array_column(stock_list_receivable_pos($conn), 'id')), true)) {
        stock_db_fail('an approved PO with unreceived quantity must appear in the receipt dropdown');
    }

    $partialReceiptDoc = 'SR-TDD-PART-' . bin2hex(random_bytes(3));
    $partialReceiptStmt = $conn->prepare('INSERT INTO stock_receipts (doc_no, po_id, sup_id, received_by) VALUES (?, ?, ?, ?)');
    $partialReceiptStmt->bind_param('siii', $partialReceiptDoc, $receivablePoId, $actor['sup_id'], $actor['id']);
    $partialReceiptStmt->execute();
    $partialReceiptId = (int)$conn->insert_id;
    $partialReceiptStmt->close();
    $conn->query("INSERT INTO stock_receipt_items (receipt_id, po_item_id, destination, received_quantity) VALUES ({$partialReceiptId}, {$receivablePoItemId}, 'direct', 2)");
    if (!in_array($receivablePoId, array_map('intval', array_column(stock_list_receivable_pos($conn), 'id')), true)) {
        stock_db_fail('a partially received PO must remain in the receipt dropdown');
    }

    $finalReceiptDoc = 'SR-TDD-FULL-' . bin2hex(random_bytes(3));
    $finalReceiptStmt = $conn->prepare('INSERT INTO stock_receipts (doc_no, po_id, sup_id, received_by) VALUES (?, ?, ?, ?)');
    $finalReceiptStmt->bind_param('siii', $finalReceiptDoc, $receivablePoId, $actor['sup_id'], $actor['id']);
    $finalReceiptStmt->execute();
    $finalReceiptId = (int)$conn->insert_id;
    $finalReceiptStmt->close();
    $conn->query("INSERT INTO stock_receipt_items (receipt_id, po_item_id, destination, received_quantity) VALUES ({$finalReceiptId}, {$receivablePoItemId}, 'direct', 3)");
    if (in_array($receivablePoId, array_map('intval', array_column(stock_list_receivable_pos($conn), 'id')), true)) {
        stock_db_fail('a fully received PO must disappear from the receipt dropdown');
    }

    $sku = 'TDD-' . bin2hex(random_bytes(4));
    $productId = stock_create_product(
        $conn,
        (int)$actor['sup_id'],
        (int)$actor['id'],
        $sku,
        'สินค้าทดสอบ Stock',
        'ชิ้น',
        'ทดสอบ',
        1
    );
    stock_save_product_unit_conversion($conn, $productId, 'ลัง', 24, (int)$actor['id']);
    if (stock_get_product_unit_conversion($conn, $productId, 'ลัง') !== 24.0) {
        stock_db_fail('receipt conversion must be remembered by product and purchase unit');
    }
    stock_save_product_unit_conversion($conn, $productId, 'ลัง', 12, (int)$actor['id']);
    if (stock_get_product_unit_conversion($conn, $productId, 'ลัง') !== 12.0) {
        stock_db_fail('procurement must be able to update conversion for a later PO');
    }

    $receiptProductId = stock_create_product_from_receipt(
        $conn,
        (int)$actor['sup_id'],
        (int)$actor['id'],
        7788,
        'น้ำยาทำความสะอาดจาก PO',
        'ขวด',
        [
            'new_sku' => [7788 => 'TDD-CAT-' . bin2hex(random_bytes(3))],
            'new_category' => [7788 => 'แม่บ้าน'],
        ]
    );
    $savedCategory = $conn->query("SELECT category FROM stock_products WHERE id = {$receiptProductId}")->fetch_assoc()['category'] ?? null;
    if ($savedCategory !== 'แม่บ้าน') {
        stock_db_fail('a product created during PO receipt must persist the selected category');
    }
    stock_update_product_category($conn, $receiptProductId, (int)$actor['sup_id'], 'ของใช้ในห้องพัก');
    $updatedCategory = $conn->query("SELECT category FROM stock_products WHERE id = {$receiptProductId}")->fetch_assoc()['category'] ?? null;
    if ($updatedCategory !== 'ของใช้ในห้องพัก') {
        stock_db_fail('procurement must be able to assign a category to an existing product without changing stock');
    }

    $caseItemName = 'น้ำยาทำความสะอาดแบบลัง';
    $caseUnit = 'ลัง';
    $caseItemStmt = $conn->prepare('INSERT INTO po_items (po_id, item_desc, item_qty, item_unit) VALUES (?, ?, 2, ?)');
    $caseItemStmt->bind_param('iss', $receivablePoId, $caseItemName, $caseUnit);
    $caseItemStmt->execute();
    $casePoItemId = (int)$conn->insert_id;
    $caseItemStmt->close();
    $convertedReceiptDoc = 'SR-TDD-CONVERT-' . bin2hex(random_bytes(3));
    $convertedReceiptStmt = $conn->prepare('INSERT INTO stock_receipts (doc_no, po_id, sup_id, received_by) VALUES (?, ?, ?, ?)');
    $convertedReceiptStmt->bind_param('siii', $convertedReceiptDoc, $receivablePoId, $actor['sup_id'], $actor['id']);
    $convertedReceiptStmt->execute();
    $convertedReceiptId = (int)$conn->insert_id;
    $convertedReceiptStmt->close();
    $purchaseQuantity = 2.0;
    $conversionFactor = 12.0;
    $convertedStockQuantity = stock_convert_purchase_to_stock_quantity($purchaseQuantity, $conversionFactor);
    $convertedItemStmt = $conn->prepare(
        'INSERT INTO stock_receipt_items
            (receipt_id, po_item_id, product_id, destination, received_quantity, units_per_purchase_unit, stock_quantity)
         VALUES (?, ?, ?, \'stock\', ?, ?, ?)'
    );
    $convertedItemStmt->bind_param('iiiddd', $convertedReceiptId, $casePoItemId, $receiptProductId, $purchaseQuantity, $conversionFactor, $convertedStockQuantity);
    $convertedItemStmt->execute();
    $convertedReceiptItemId = (int)$conn->insert_id;
    $convertedItemStmt->close();
    stock_save_product_unit_conversion($conn, $receiptProductId, $caseUnit, $conversionFactor, (int)$actor['id']);
    stock_apply_movement($conn, (int)$actor['sup_id'], $receiptProductId, 'in_po', $convertedStockQuantity, 'stock_receipt_item', $convertedReceiptItemId, (int)$actor['id'], '2 ลัง × 12 ขวด');
    if (stock_get_balance($conn, $receiptProductId) !== 24.0) {
        stock_db_fail('two cases of twelve must add twenty-four base units to Stock');
    }
    $convertedAudit = $conn->query("SELECT received_quantity, units_per_purchase_unit, stock_quantity FROM stock_receipt_items WHERE id = {$convertedReceiptItemId}")->fetch_assoc();
    if ((float)$convertedAudit['received_quantity'] !== 2.0 || (float)$convertedAudit['units_per_purchase_unit'] !== 12.0 || (float)$convertedAudit['stock_quantity'] !== 24.0) {
        stock_db_fail('receipt audit must retain purchase quantity, conversion factor, and Stock quantity');
    }

    $categoryId = stock_get_or_create_category($conn, (int)$actor['sup_id'], (int)$actor['id'], 'เครื่องใช้แขก');
    $sameCategoryId = stock_get_or_create_category($conn, (int)$actor['sup_id'], (int)$actor['id'], ' เครื่องใช้แขก ');
    if ($categoryId !== $sameCategoryId) {
        stock_db_fail('the same category name in one company must reuse the existing category');
    }

    $resolvedProductId = stock_resolve_receipt_product(
        $conn,
        (int)$actor['sup_id'],
        (int)$actor['id'],
        8899,
        'สบู่เหลวกลิ่นใหม่',
        'ขวด',
        [
            'product_id' => [8899 => 0],
            'new_product_name' => [8899 => 'สบู่เหลวกลิ่นใหม่'],
            'category_id' => [8899 => $categoryId],
            'stock_unit' => [8899 => 'ชิ้น'],
        ]
    );
    $resolvedProduct = $conn->query("SELECT sku, name, category, category_id FROM stock_products WHERE id = {$resolvedProductId}")->fetch_assoc();
    if (!$resolvedProduct || !str_starts_with($resolvedProduct['sku'], 'STK-' . $actor['sup_id'] . '-')) {
        stock_db_fail('a new receipt product must receive an automatic internal SKU');
    }
    if ($resolvedProduct['name'] !== 'สบู่เหลวกลิ่นใหม่' || (int)$resolvedProduct['category_id'] !== $categoryId) {
        stock_db_fail('a new receipt product must persist its typed name and selected company category');
    }
    $reusedProductId = stock_resolve_receipt_product(
        $conn,
        (int)$actor['sup_id'],
        (int)$actor['id'],
        8899,
        'ชื่อจาก PO ที่ไม่ควรใช้',
        'ขวด',
        [
            'product_id' => [8899 => $resolvedProductId],
            'new_product_name' => [8899 => 'ชื่อใหม่ที่ไม่ควรถูกสร้าง'],
            'category_id' => [8899 => $categoryId],
        ]
    );
    if ($reusedProductId !== $resolvedProductId) {
        stock_db_fail('selecting a similar existing product must reuse it instead of creating a duplicate');
    }

    stock_apply_movement(
        $conn,
        (int)$actor['sup_id'],
        $productId,
        'in_po',
        5,
        'test',
        1001,
        (int)$actor['id'],
        'รับเข้าทดสอบ'
    );
    if (stock_get_balance($conn, $productId) !== 5.0) {
        stock_db_fail('PO receipt must increase balance');
    }

    stock_apply_movement(
        $conn,
        (int)$actor['sup_id'],
        $productId,
        'out_withdrawal',
        -2,
        'test',
        1002,
        (int)$actor['id'],
        'จ่ายบางส่วน'
    );
    if (stock_get_balance($conn, $productId) !== 3.0) {
        stock_db_fail('partial issue must decrease balance');
    }

    try {
        stock_apply_movement(
            $conn,
            (int)$actor['sup_id'],
            $productId,
            'out_withdrawal',
            -4,
            'test',
            1003,
            (int)$actor['id'],
            'ต้องไม่ผ่าน'
        );
        stock_db_fail('movement must reject a negative ending balance');
    } catch (DomainException $exception) {
        // Expected: the balance remains unchanged.
    }

    if (stock_get_balance($conn, $productId) !== 3.0) {
        stock_db_fail('rejected movement must not change balance');
    }

    stock_apply_movement(
        $conn,
        (int)$actor['sup_id'],
        $productId,
        'adjustment',
        2,
        'test',
        1004,
        (int)$actor['id'],
        'ปรับหลังอนุมัติ'
    );
    if (stock_get_balance($conn, $productId) !== 5.0) {
        stock_db_fail('approved reconciliation adjustment must change balance');
    }

    $movementCount = (int)$conn->query("SELECT COUNT(*) total FROM stock_movements WHERE product_id = {$productId}")->fetch_assoc()['total'];
    if ($movementCount !== 3) {
        stock_db_fail('only successful movements must be recorded in the immutable ledger');
    }

    $pendingWithdrawalBaseline = stock_count_pending_withdrawals($conn);
    $withdrawalDoc = 'WD-TDD-' . bin2hex(random_bytes(4));
    $purpose = 'ทดสอบการเบิกบางส่วน';
    $withdrawalStmt = $conn->prepare('INSERT INTO stock_withdrawals (doc_no, sup_id, requester_id, purpose) VALUES (?, ?, ?, ?)');
    $withdrawalStmt->bind_param('siis', $withdrawalDoc, $actor['sup_id'], $actor['id'], $purpose);
    $withdrawalStmt->execute();
    $withdrawalId = (int)$conn->insert_id;
    $withdrawalStmt->close();
    stock_lock_withdrawal($conn, $withdrawalId);
    $withdrawalItemStmt = $conn->prepare('INSERT INTO stock_withdrawal_items (withdrawal_id, product_id, requested_quantity) VALUES (?, ?, 5)');
    $withdrawalItemStmt->bind_param('ii', $withdrawalId, $productId);
    $withdrawalItemStmt->execute();
    $withdrawalItemId = (int)$conn->insert_id;
    $withdrawalItemStmt->close();
    if (stock_count_pending_withdrawals($conn) !== $pendingWithdrawalBaseline + 1) {
        stock_db_fail('a withdrawal with quantity waiting to issue must increase the Stock badge');
    }

    $issueStmt = $conn->prepare('INSERT INTO stock_withdrawal_issues (withdrawal_item_id, issued_quantity, issued_by) VALUES (?, 2, ?)');
    $issueStmt->bind_param('ii', $withdrawalItemId, $actor['id']);
    $issueStmt->execute();
    $issueId = (int)$conn->insert_id;
    $issueStmt->close();
    $conn->query("UPDATE stock_withdrawal_items SET issued_quantity = 2 WHERE id = {$withdrawalItemId}");
    stock_apply_movement($conn, (int)$actor['sup_id'], $productId, 'out_withdrawal', -2, 'withdrawal_issue', $issueId, (int)$actor['id'], 'ทดสอบจ่ายบางส่วน');
    if (stock_refresh_withdrawal_status($conn, $withdrawalId) !== 'waiting_confirmation') {
        stock_db_fail('partial issue must wait for requester confirmation');
    }

    $conn->query("UPDATE stock_withdrawal_issues SET received_quantity = 2, received_by = {$actor['id']}, received_at = NOW(), status = 'confirmed' WHERE id = {$issueId}");
    $conn->query("UPDATE stock_withdrawal_items SET received_quantity = 2 WHERE id = {$withdrawalItemId}");
    if (stock_refresh_withdrawal_status($conn, $withdrawalId) !== 'partially_fulfilled') {
        stock_db_fail('confirmed partial issue must retain the original withdrawal');
    }

    $conn->query("UPDATE stock_withdrawal_items SET cancelled_quantity = 3 WHERE id = {$withdrawalItemId}");
    if (stock_refresh_withdrawal_status($conn, $withdrawalId) !== 'completed') {
        stock_db_fail('cancelling the remaining quantity must complete the same withdrawal');
    }
    if (stock_count_pending_withdrawals($conn) !== $pendingWithdrawalBaseline) {
        stock_db_fail('a withdrawal without quantity waiting to issue must leave the Stock badge');
    }

    $reconciliationDoc = 'RC-TDD-' . bin2hex(random_bytes(4));
    $reconciliationStatus = 'pending_approval';
    $reason = 'ทดสอบยอดขาด';
    $reconciliationStmt = $conn->prepare('INSERT INTO stock_reconciliations (doc_no, sup_id, status, reason, counted_by) VALUES (?, ?, ?, ?, ?)');
    $reconciliationStmt->bind_param('sissi', $reconciliationDoc, $actor['sup_id'], $reconciliationStatus, $reason, $actor['id']);
    $reconciliationStmt->execute();
    $reconciliationId = (int)$conn->insert_id;
    $reconciliationStmt->close();
    $reconciliationItemStmt = $conn->prepare('INSERT INTO stock_reconciliation_items (reconciliation_id, product_id, system_quantity, physical_quantity, difference_quantity) VALUES (?, ?, 3, 2, -1)');
    $reconciliationItemStmt->bind_param('ii', $reconciliationId, $productId);
    $reconciliationItemStmt->execute();
    $reconciliationItemId = (int)$conn->insert_id;
    $reconciliationItemStmt->close();
    if (stock_get_balance($conn, $productId) !== 3.0) {
        stock_db_fail('pending reconciliation must not change stock');
    }
    stock_apply_movement($conn, (int)$actor['sup_id'], $productId, 'adjustment', -1, 'reconciliation_item', $reconciliationItemId, (int)$actor['id'], 'ทดสอบอนุมัติผลต่าง');
    if (stock_get_balance($conn, $productId) !== 2.0) {
        stock_db_fail('approved reconciliation must adjust stock to the physical count');
    }

    try {
        stock_apply_reconciliation_adjustment(
            $conn,
            (int)$actor['sup_id'],
            $productId,
            3,
            -1,
            999999,
            (int)$actor['id'],
            'ยอด snapshot เก่า'
        );
        stock_db_fail('stale reconciliation snapshot must be rejected while the balance lock is held');
    } catch (DomainException $exception) {
        // Expected because current balance is 2, not the expected snapshot 3.
    }

    $discrepancyDoc = 'WD-TDD-DIFF-' . bin2hex(random_bytes(3));
    $discrepancyStmt = $conn->prepare('INSERT INTO stock_withdrawals (doc_no, sup_id, requester_id, purpose) VALUES (?, ?, ?, ?)');
    $discrepancyStmt->bind_param('siis', $discrepancyDoc, $actor['sup_id'], $actor['id'], $purpose);
    $discrepancyStmt->execute();
    $discrepancyWithdrawalId = (int)$conn->insert_id;
    $discrepancyStmt->close();
    $conn->query("INSERT INTO stock_withdrawal_items (withdrawal_id, product_id, requested_quantity, issued_quantity) VALUES ({$discrepancyWithdrawalId}, {$productId}, 5, 2)");
    $discrepancyItemId = (int)$conn->insert_id;
    $conn->query("INSERT INTO stock_withdrawal_issues (withdrawal_item_id, issued_quantity, issued_by) VALUES ({$discrepancyItemId}, 2, {$actor['id']})");
    $discrepancyIssueId = (int)$conn->insert_id;
    stock_apply_movement($conn, (int)$actor['sup_id'], $productId, 'out_withdrawal', -2, 'withdrawal_issue', $discrepancyIssueId, (int)$actor['id'], 'ทดสอบผลต่าง');
    $conn->query("UPDATE stock_withdrawal_issues SET received_quantity = 1, received_by = {$actor['id']}, received_at = NOW(), status = 'discrepancy' WHERE id = {$discrepancyIssueId}");
    $conn->query("UPDATE stock_withdrawal_items SET received_quantity = 1 WHERE id = {$discrepancyItemId}");
    if (stock_refresh_withdrawal_status($conn, $discrepancyWithdrawalId) !== 'discrepancy') {
        stock_db_fail('short actual receipt must enter an explicit discrepancy state');
    }
    stock_apply_movement($conn, (int)$actor['sup_id'], $productId, 'return', 1, 'withdrawal_issue', $discrepancyIssueId, (int)$actor['id'], 'คืนผลต่าง');
    $conn->query("UPDATE stock_withdrawal_items SET issued_quantity = issued_quantity - 1 WHERE id = {$discrepancyItemId}");
    $conn->query("UPDATE stock_withdrawal_issues SET status = 'resolved', resolution_type = 'returned_to_stock', resolution_quantity = 1, resolved_by = {$actor['id']}, resolved_at = NOW() WHERE id = {$discrepancyIssueId}");
    if (stock_refresh_withdrawal_status($conn, $discrepancyWithdrawalId) !== 'partially_fulfilled') {
        stock_db_fail('returned discrepancy must restore the missing quantity to the same request remainder');
    }

    $conn->rollback();
    echo "Stock repository DB smoke tests passed.\n";
} catch (Throwable $exception) {
    $conn->rollback();
    throw $exception;
}
