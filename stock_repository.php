<?php

require_once __DIR__ . '/stock_workflow.php';

function stock_count_pending_withdrawals(mysqli $conn): int
{
    $result = $conn->query(
        "SELECT COUNT(DISTINCT wi.withdrawal_id) total
         FROM stock_withdrawal_items wi
         INNER JOIN stock_withdrawals w ON w.id = wi.withdrawal_id
         WHERE w.status NOT IN ('completed', 'cancelled')
           AND wi.requested_quantity > wi.issued_quantity + wi.cancelled_quantity + 0.00001"
    );

    return (int)($result->fetch_assoc()['total'] ?? 0);
}

function stock_save_product_unit_conversion(
    mysqli $conn,
    int $productId,
    string $purchaseUnit,
    float $unitsPerPurchaseUnit,
    int $actorId = 0
): void {
    $purchaseUnit = trim($purchaseUnit);
    stock_convert_purchase_to_stock_quantity(1, $unitsPerPurchaseUnit);
    if ($productId <= 0 || $purchaseUnit === '') {
        throw new DomainException('กรุณาระบุสินค้าและหน่วยซื้อให้ครบถ้วน');
    }
    $updatedBy = $actorId > 0 ? $actorId : null;
    $stmt = $conn->prepare(
        'INSERT INTO stock_product_unit_conversions (product_id, purchase_unit, units_per_purchase_unit, updated_by)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE units_per_purchase_unit = VALUES(units_per_purchase_unit), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->bind_param('isdi', $productId, $purchaseUnit, $unitsPerPurchaseUnit, $updatedBy);
    $stmt->execute();
    $stmt->close();
}

function stock_get_product_unit_conversion(mysqli $conn, int $productId, string $purchaseUnit): ?float
{
    $purchaseUnit = trim($purchaseUnit);
    if ($productId <= 0 || $purchaseUnit === '') {
        return null;
    }
    $stmt = $conn->prepare(
        'SELECT units_per_purchase_unit FROM stock_product_unit_conversions
         WHERE product_id = ? AND purchase_unit = ? LIMIT 1'
    );
    $stmt->bind_param('is', $productId, $purchaseUnit);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (float)$row['units_per_purchase_unit'] : null;
}

function stock_list_product_unit_conversions(mysqli $conn, int $supId): array
{
    if ($supId <= 0) {
        return [];
    }
    $stmt = $conn->prepare(
        'SELECT c.product_id, c.purchase_unit, c.units_per_purchase_unit
         FROM stock_product_unit_conversions c
         INNER JOIN stock_products p ON p.id = c.product_id
         WHERE p.sup_id = ? AND p.is_active = 1'
    );
    $stmt->bind_param('i', $supId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $result = [];
    foreach ($rows as $row) {
        $result[(int)$row['product_id']][(string)$row['purchase_unit']] = (float)$row['units_per_purchase_unit'];
    }
    return $result;
}

function stock_list_receivable_pos(mysqli $conn): array
{
    $sql = "SELECT p.id, p.doc_no, p.supplier_id, s.company_name, p.created_at,
                   totals.item_count, totals.remaining_item_count, totals.remaining_quantity
            FROM po p
            INNER JOIN suppliers s ON s.id = p.supplier_id
            INNER JOIN (
                SELECT pi.po_id,
                       COUNT(*) item_count,
                       SUM(CASE WHEN pi.item_qty > COALESCE(received.received_quantity, 0) + 0.00001 THEN 1 ELSE 0 END) remaining_item_count,
                       SUM(GREATEST(pi.item_qty - COALESCE(received.received_quantity, 0), 0)) remaining_quantity
                FROM po_items pi
                LEFT JOIN (
                    SELECT po_item_id, SUM(received_quantity) received_quantity
                    FROM stock_receipt_items
                    GROUP BY po_item_id
                ) received ON received.po_item_id = pi.id
                GROUP BY pi.po_id
            ) totals ON totals.po_id = p.id
            WHERE p.status = 'approved'
              AND p.deleted_at IS NULL
              AND totals.remaining_item_count > 0
            ORDER BY p.id DESC";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function stock_create_product(
    mysqli $conn,
    int $supId,
    int $actorId,
    string $sku,
    string $name,
    string $unit,
    string $category = '',
    float $minQuantity = 0
): int {
    $sku = trim($sku);
    $name = trim($name);
    $unit = trim($unit);
    $category = trim($category);
    if ($supId <= 0 || $sku === '' || $name === '' || $unit === '' || $minQuantity < 0) {
        throw new DomainException('กรุณากรอกข้อมูลสินค้าให้ครบถ้วน');
    }

    $categoryId = null;
    if ($category !== '') {
        $categoryId = stock_get_or_create_category($conn, $supId, $actorId, $category);
    }
    $stmt = $conn->prepare(
        'INSERT INTO stock_products (sup_id, sku, name, category, category_id, unit, min_quantity, created_by)
         VALUES (?, ?, ?, NULLIF(?, \'\'), ?, ?, ?, ?)'
    );
    $stmt->bind_param('isssisdi', $supId, $sku, $name, $category, $categoryId, $unit, $minQuantity, $actorId);
    $stmt->execute();
    $productId = (int)$conn->insert_id;
    $stmt->close();

    $balanceStmt = $conn->prepare('INSERT INTO stock_balances (product_id, quantity) VALUES (?, 0)');
    $balanceStmt->bind_param('i', $productId);
    $balanceStmt->execute();
    $balanceStmt->close();

    return $productId;
}

function stock_create_product_from_receipt(
    mysqli $conn,
    int $supId,
    int $actorId,
    int $poItemId,
    string $itemDescription,
    string $itemUnit,
    array $input
): int {
    $sku = trim((string)($input['new_sku'][$poItemId] ?? ''));
    $category = trim((string)($input['new_category'][$poItemId] ?? ''));
    if ($category === '') {
        throw new DomainException('กรุณาระบุหมวดหมู่ของสินค้าใหม่');
    }

    $stockUnit = trim((string)($input['stock_unit'][$poItemId] ?? $itemUnit));
    if ($stockUnit === '') {
        throw new DomainException('กรุณาระบุหน่วย Stock/หน่วยเบิกของสินค้าใหม่');
    }

    return stock_create_product(
        $conn,
        $supId,
        $actorId,
        $sku,
        $itemDescription,
        $stockUnit,
        $category,
        0
    );
}

function stock_get_or_create_category(mysqli $conn, int $supId, int $actorId, string $name): int
{
    $name = trim($name);
    if ($supId <= 0 || $name === '') {
        throw new DomainException('กรุณาระบุหมวดหมู่สินค้า');
    }
    $creatorId = $actorId > 0 ? $actorId : null;
    $stmt = $conn->prepare(
        'INSERT INTO stock_categories (sup_id, name, created_by)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), is_active = 1'
    );
    $stmt->bind_param('isi', $supId, $name, $creatorId);
    $stmt->execute();
    $categoryId = (int)$conn->insert_id;
    $stmt->close();
    return $categoryId;
}

function stock_update_product_category(mysqli $conn, int $productId, int $supId, string $category, int $actorId = 0): void
{
    $category = trim($category);
    if ($productId <= 0 || $supId <= 0 || $category === '') {
        throw new DomainException('กรุณาระบุหมวดหมู่สินค้า');
    }
    $checkStmt = $conn->prepare('SELECT id FROM stock_products WHERE id = ? AND sup_id = ? FOR UPDATE');
    $checkStmt->bind_param('ii', $productId, $supId);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    if (!$exists) {
        throw new DomainException('ไม่พบสินค้าในบริษัทที่เลือก');
    }
    $categoryId = stock_get_or_create_category($conn, $supId, $actorId, $category);
    $updateStmt = $conn->prepare('UPDATE stock_products SET category = ?, category_id = ? WHERE id = ? AND sup_id = ?');
    $updateStmt->bind_param('siii', $category, $categoryId, $productId, $supId);
    $updateStmt->execute();
    $updateStmt->close();
}

function stock_resolve_receipt_product(
    mysqli $conn,
    int $supId,
    int $actorId,
    int $poItemId,
    string $defaultName,
    string $unit,
    array $input
): int {
    $selectedProductId = (int)($input['product_id'][$poItemId] ?? 0);
    if ($selectedProductId > 0) {
        $stmt = $conn->prepare('SELECT id FROM stock_products WHERE id = ? AND sup_id = ? AND is_active = 1');
        $stmt->bind_param('ii', $selectedProductId, $supId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$product) {
            throw new DomainException('สินค้าที่เลือกไม่อยู่ในบริษัทปลายทาง');
        }
        return $selectedProductId;
    }

    $name = trim((string)($input['new_product_name'][$poItemId] ?? $defaultName));
    if ($name === '') {
        throw new DomainException('กรุณาระบุชื่อสินค้า');
    }
    $exactStmt = $conn->prepare('SELECT id FROM stock_products WHERE sup_id = ? AND name = ? AND is_active = 1 LIMIT 1');
    $exactStmt->bind_param('is', $supId, $name);
    $exactStmt->execute();
    $exact = $exactStmt->get_result()->fetch_assoc();
    $exactStmt->close();
    if ($exact) {
        return (int)$exact['id'];
    }

    $categoryId = (int)($input['category_id'][$poItemId] ?? 0);
    $categoryName = trim((string)($input['new_category_name'][$poItemId] ?? ''));
    if ($categoryId > 0) {
        $categoryStmt = $conn->prepare('SELECT id, name FROM stock_categories WHERE id = ? AND sup_id = ? AND is_active = 1');
        $categoryStmt->bind_param('ii', $categoryId, $supId);
        $categoryStmt->execute();
        $category = $categoryStmt->get_result()->fetch_assoc();
        $categoryStmt->close();
        if (!$category) {
            throw new DomainException('หมวดหมู่ไม่อยู่ในบริษัทปลายทาง');
        }
        $categoryName = $category['name'];
    } elseif ($categoryName !== '') {
        stock_get_or_create_category($conn, $supId, $actorId, $categoryName);
    } else {
        throw new DomainException('กรุณาเลือกหรือเพิ่มหมวดหมู่สินค้า');
    }

    $stockUnit = trim((string)($input['stock_unit'][$poItemId] ?? ''));
    if ($stockUnit === '') {
        throw new DomainException('กรุณาระบุหน่วย Stock/หน่วยเบิกของสินค้าใหม่');
    }

    return stock_create_product(
        $conn,
        $supId,
        $actorId,
        stock_generate_internal_sku($supId),
        $name,
        $stockUnit,
        $categoryName,
        0
    );
}

function stock_get_balance(mysqli $conn, int $productId): float
{
    $stmt = $conn->prepare('SELECT quantity FROM stock_balances WHERE product_id = ?');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new DomainException('ไม่พบยอด Stock ของสินค้า');
    }

    return (float)$row['quantity'];
}

function stock_apply_movement(
    mysqli $conn,
    int $supId,
    int $productId,
    string $movementType,
    float $quantity,
    string $referenceType,
    int $referenceId,
    int $actorId,
    string $note = ''
): float {
    $quantity = stock_validate_movement_quantity($movementType, $quantity);

    $lockStmt = $conn->prepare(
        'SELECT b.quantity, p.sup_id
         FROM stock_balances b
         INNER JOIN stock_products p ON p.id = b.product_id
         WHERE b.product_id = ? FOR UPDATE'
    );
    $lockStmt->bind_param('i', $productId);
    $lockStmt->execute();
    $balanceRow = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$balanceRow || (int)$balanceRow['sup_id'] !== $supId) {
        throw new DomainException('สินค้าไม่อยู่ในบริษัทที่เลือก');
    }

    $before = (float)$balanceRow['quantity'];
    $after = round($before + $quantity, 2);
    if ($after < -0.00001) {
        throw new DomainException('จำนวนสินค้าใน Stock ไม่เพียงพอ');
    }
    $after = max(0, $after);

    $movementStmt = $conn->prepare(
        'INSERT INTO stock_movements
         (sup_id, product_id, movement_type, quantity, balance_before, balance_after, reference_type, reference_id, actor_id, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, \'\'))'
    );
    $movementStmt->bind_param(
        'iisdddsiis',
        $supId,
        $productId,
        $movementType,
        $quantity,
        $before,
        $after,
        $referenceType,
        $referenceId,
        $actorId,
        $note
    );
    $movementStmt->execute();
    $movementStmt->close();

    $balanceStmt = $conn->prepare('UPDATE stock_balances SET quantity = ? WHERE product_id = ?');
    $balanceStmt->bind_param('di', $after, $productId);
    $balanceStmt->execute();
    $balanceStmt->close();

    return $after;
}

function stock_apply_reconciliation_adjustment(
    mysqli $conn,
    int $supId,
    int $productId,
    float $expectedBalance,
    float $difference,
    int $reconciliationItemId,
    int $actorId,
    string $note
): float {
    $lockStmt = $conn->prepare(
        'SELECT b.quantity, p.sup_id
         FROM stock_balances b INNER JOIN stock_products p ON p.id = b.product_id
         WHERE b.product_id = ? FOR UPDATE'
    );
    $lockStmt->bind_param('i', $productId);
    $lockStmt->execute();
    $row = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();
    if (!$row || (int)$row['sup_id'] !== $supId) {
        throw new DomainException('สินค้าไม่อยู่ในบริษัทของรายงานกระทบยอด');
    }
    if (abs((float)$row['quantity'] - $expectedBalance) > 0.00001) {
        throw new DomainException('ยอด Stock เปลี่ยนหลังตรวจนับ กรุณาให้จัดซื้อนับและส่งใหม่');
    }
    if (abs($difference) < 0.00001) {
        return (float)$row['quantity'];
    }
    return stock_apply_movement(
        $conn,
        $supId,
        $productId,
        'adjustment',
        $difference,
        'reconciliation_item',
        $reconciliationItemId,
        $actorId,
        $note
    );
}

function stock_generate_doc_no(mysqli $conn, string $prefix, string $table): string
{
    $allowedTables = ['stock_receipts', 'stock_withdrawals', 'stock_reconciliations'];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('ตารางเลขที่เอกสารไม่ถูกต้อง');
    }
    $datePart = date('ym');
    $like = $prefix . '-' . $datePart . '%';
    $stmt = $conn->prepare("SELECT doc_no FROM {$table} WHERE doc_no LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $last = $stmt->get_result()->fetch_assoc()['doc_no'] ?? '';
    $stmt->close();
    $sequence = $last === '' ? 1 : ((int)substr($last, -5) + 1);

    return sprintf('%s-%s%05d', $prefix, $datePart, $sequence);
}

function stock_lock_withdrawal(mysqli $conn, int $withdrawalId): void
{
    $stmt = $conn->prepare('SELECT id FROM stock_withdrawals WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $withdrawalId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$exists) {
        throw new DomainException('ไม่พบใบเบิก');
    }
}

function stock_refresh_withdrawal_status(mysqli $conn, int $withdrawalId): string
{
    $stmt = $conn->prepare(
        'SELECT COALESCE(SUM(requested_quantity), 0) requested,
                COALESCE(SUM(issued_quantity), 0) issued,
                COALESCE(SUM(received_quantity), 0) received,
                COALESCE(SUM(cancelled_quantity), 0) cancelled,
                (SELECT COUNT(*) FROM stock_withdrawal_issues issues
                 INNER JOIN stock_withdrawal_items issue_items ON issue_items.id = issues.withdrawal_item_id
                 WHERE issue_items.withdrawal_id = ? AND issues.status = \'waiting_confirmation\') pending_confirmations,
                (SELECT COUNT(*) FROM stock_withdrawal_issues issues
                 INNER JOIN stock_withdrawal_items issue_items ON issue_items.id = issues.withdrawal_item_id
                 WHERE issue_items.withdrawal_id = ? AND issues.status = \'discrepancy\') unresolved_discrepancies,
                (SELECT COALESCE(SUM(issues.resolution_quantity), 0) FROM stock_withdrawal_issues issues
                 INNER JOIN stock_withdrawal_items issue_items ON issue_items.id = issues.withdrawal_item_id
                 WHERE issue_items.withdrawal_id = ? AND issues.status = \'resolved\' AND issues.resolution_type = \'accepted_loss\') accepted_loss
         FROM stock_withdrawal_items WHERE withdrawal_id = ?'
    );
    $stmt->bind_param('iiii', $withdrawalId, $withdrawalId, $withdrawalId, $withdrawalId);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ((int)$totals['unresolved_discrepancies'] > 0) {
        $status = 'discrepancy';
    } elseif ((int)$totals['pending_confirmations'] > 0) {
        $status = 'waiting_confirmation';
    } else {
        $status = stock_withdrawal_status(
            (float)$totals['requested'],
            (float)$totals['issued'],
            (float)$totals['received'] + (float)$totals['accepted_loss'],
            (float)$totals['cancelled']
        );
    }
    $update = $conn->prepare('UPDATE stock_withdrawals SET status = ? WHERE id = ?');
    $update->bind_param('si', $status, $withdrawalId);
    $update->execute();
    $update->close();

    return $status;
}
