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
        throw new DomainException('กรุณาระบุพัสดุและหน่วยซื้อให้ครบถ้วน');
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
        throw new DomainException('กรุณากรอกข้อมูลพัสดุให้ครบถ้วน');
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
        throw new DomainException('กรุณาระบุหมวดหมู่ของพัสดุใหม่');
    }

    $stockUnit = trim((string)($input['stock_unit'][$poItemId] ?? $itemUnit));
    if ($stockUnit === '') {
        throw new DomainException('กรุณาระบุหน่วย Stock/หน่วยเบิกของพัสดุใหม่');
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
        throw new DomainException('กรุณาระบุหมวดหมู่พัสดุ');
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
        throw new DomainException('กรุณาระบุหมวดหมู่พัสดุ');
    }
    $checkStmt = $conn->prepare('SELECT id FROM stock_products WHERE id = ? AND sup_id = ? FOR UPDATE');
    $checkStmt->bind_param('ii', $productId, $supId);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    if (!$exists) {
        throw new DomainException('ไม่พบพัสดุในบริษัทที่เลือก');
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
            throw new DomainException('พัสดุที่เลือกไม่อยู่ในบริษัทปลายทาง');
        }
        return $selectedProductId;
    }

    $name = trim((string)($input['new_product_name'][$poItemId] ?? $defaultName));
    if ($name === '') {
        throw new DomainException('กรุณาระบุชื่อพัสดุ');
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
        throw new DomainException('กรุณาเลือกหรือเพิ่มหมวดหมู่พัสดุ');
    }

    $stockUnit = trim((string)($input['stock_unit'][$poItemId] ?? ''));
    if ($stockUnit === '') {
        throw new DomainException('กรุณาระบุหน่วย Stock/หน่วยเบิกของพัสดุใหม่');
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
        throw new DomainException('ไม่พบยอด Stock ของพัสดุ');
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
        throw new DomainException('พัสดุไม่อยู่ในบริษัทที่เลือก');
    }

    $before = (float)$balanceRow['quantity'];
    $after = round($before + $quantity, 2);
    if ($after < -0.00001) {
        throw new DomainException('จำนวนพัสดุใน Stock ไม่เพียงพอ');
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
        throw new DomainException('พัสดุไม่อยู่ในบริษัทของรายงานกระทบยอด');
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

function stock_apply_manual_adjustment(
    mysqli $conn,
    int $supId,
    int $productId,
    float $expectedBalance,
    float $newBalance,
    int $actorId,
    string $reason
): float {
    $reason = trim($reason);
    if ($reason === '') {
        throw new DomainException('กรุณาระบุเหตุผลการปรับยอด');
    }
    if ($newBalance < 0) {
        throw new DomainException('ยอดคงเหลือใหม่ต้องไม่ติดลบ');
    }
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
        throw new DomainException('พัสดุไม่อยู่ในบริษัทที่เลือก');
    }
    if (abs((float)$row['quantity'] - $expectedBalance) > 0.00001) {
        throw new DomainException('ยอด Stock เปลี่ยนแปลงระหว่างที่แก้ไข กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    $difference = round($newBalance - (float)$row['quantity'], 2);
    if (abs($difference) < 0.00001) {
        throw new DomainException('ยอดคงเหลือใหม่เท่ากับยอดเดิม');
    }
    $refStmt = $conn->prepare(
        "SELECT COALESCE(MAX(reference_id), 0) + 1 next_id FROM stock_movements
         WHERE reference_type = 'manual_adjustment' FOR UPDATE"
    );
    $refStmt->execute();
    $referenceId = (int)$refStmt->get_result()->fetch_assoc()['next_id'];
    $refStmt->close();

    return stock_apply_movement(
        $conn,
        $supId,
        $productId,
        'adjustment',
        $difference,
        'manual_adjustment',
        $referenceId,
        $actorId,
        $reason
    );
}

function stock_import_normalize_key(string $value): string
{
    return mb_strtolower(preg_replace('/\s+/u', '', $value) ?? '', 'UTF-8');
}

/**
 * Match counted rows (from an uploaded count sheet) to the company's products: SKU = ItemCode first, then exact name.
 * Returns the company's product list (for manual re-mapping) and a suggested product id per row index.
 */
function stock_import_match_rows(mysqli $conn, int $supId, array $rows): array
{
    $stmt = $conn->prepare(
        'SELECT p.id, p.sku, p.name, p.unit, p.category, COALESCE(b.quantity, 0) quantity
         FROM stock_products p LEFT JOIN stock_balances b ON b.product_id = p.id
         WHERE p.sup_id = ? AND p.is_active = 1 ORDER BY p.name'
    );
    $stmt->bind_param('i', $supId);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $bySku = [];
    $byName = [];
    foreach ($products as $product) {
        $bySku[stock_import_normalize_key((string)$product['sku'])] = (int)$product['id'];
        $byName[stock_import_normalize_key((string)$product['name'])] ??= (int)$product['id'];
    }
    $matches = [];
    foreach ($rows as $index => $row) {
        $code = stock_import_normalize_key((string)($row['code'] ?? ''));
        $name = stock_import_normalize_key((string)($row['name'] ?? ''));
        if ($code !== '' && isset($bySku[$code])) {
            $matches[$index] = ['product_id' => $bySku[$code], 'by' => 'sku'];
        } elseif ($name !== '' && isset($byName[$name])) {
            $matches[$index] = ['product_id' => $byName[$name], 'by' => 'name'];
        } else {
            $matches[$index] = ['product_id' => null, 'by' => null];
        }
    }

    // Second pass: suggest a product whose name contains (or is contained in) the row name, only when unambiguous.
    $taken = [];
    foreach ($matches as $match) {
        if ($match['product_id'] !== null) {
            $taken[$match['product_id']] = true;
        }
    }
    foreach ($rows as $index => $row) {
        if ($matches[$index]['product_id'] !== null) {
            continue;
        }
        $name = stock_import_normalize_key((string)($row['name'] ?? ''));
        $candidates = [];
        foreach ($products as $product) {
            $productName = stock_import_normalize_key((string)$product['name']);
            $shorter = mb_strlen($name) <= mb_strlen($productName) ? $name : $productName;
            if (mb_strlen($shorter) < 4 || isset($taken[(int)$product['id']])) {
                continue;
            }
            if (str_contains($productName, $name) || str_contains($name, $productName)) {
                $candidates[] = (int)$product['id'];
            }
        }
        if (count($candidates) === 1) {
            $matches[$index] = ['product_id' => $candidates[0], 'by' => 'similar'];
            $taken[$candidates[0]] = true;
        }
    }
    return ['products' => $products, 'matches' => $matches];
}

/**
 * Apply a physical count import. Each item: action = 'create' | 'skip' | product id (string/int),
 * quantity (counted), expected (balance shown in preview, for existing products), code, name, unit, category, min.
 */
function stock_import_counts(mysqli $conn, int $supId, int $actorId, array $items, string $reason, bool $applyMin): array
{
    $reason = trim($reason);
    if ($supId <= 0 || $reason === '') {
        throw new DomainException('ข้อมูลการนำเข้าไม่ครบถ้วน');
    }
    $result = ['created' => 0, 'adjusted' => 0, 'unchanged' => 0, 'skipped' => 0];
    $usedProducts = [];
    foreach ($items as $position => $item) {
        $rowLabel = 'แถว ' . ((int)$position + 1) . ' (' . trim((string)($item['name'] ?? '')) . ')';
        $action = (string)($item['action'] ?? 'skip');
        if ($action === 'skip') {
            $result['skipped']++;
            continue;
        }
        $quantityRaw = $item['quantity'] ?? null;
        if (!is_numeric($quantityRaw) || (float)$quantityRaw < 0) {
            throw new DomainException($rowLabel . ': ยอดนับต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป');
        }
        $quantity = round((float)$quantityRaw, 2);
        $minRaw = $item['min'] ?? null;
        $min = is_numeric($minRaw) && (float)$minRaw >= 0 ? (float)$minRaw : null;

        if ($action === 'create') {
            $code = trim((string)($item['code'] ?? ''));
            $sku = $code !== '' ? $code : stock_generate_internal_sku($supId);
            $dupStmt = $conn->prepare('SELECT id FROM stock_products WHERE sup_id = ? AND sku = ?');
            $dupStmt->bind_param('is', $supId, $sku);
            $dupStmt->execute();
            $duplicate = $dupStmt->get_result()->fetch_assoc();
            $dupStmt->close();
            if ($duplicate) {
                throw new DomainException($rowLabel . ': รหัส ' . $sku . ' มีอยู่ในระบบแล้ว กรุณาเลือกจับคู่กับพัสดุเดิม');
            }
            $productId = stock_create_product(
                $conn,
                $supId,
                $actorId,
                $sku,
                (string)($item['name'] ?? ''),
                (string)($item['unit'] ?? ''),
                (string)($item['category'] ?? ''),
                $applyMin && $min !== null ? $min : 0
            );
            $result['created']++;
            if ($quantity > 0) {
                stock_apply_manual_adjustment($conn, $supId, $productId, 0, $quantity, $actorId, $reason);
            }
            continue;
        }

        if (!ctype_digit($action)) {
            throw new DomainException($rowLabel . ': รูปแบบการจับคู่ไม่ถูกต้อง');
        }
        $productId = (int)$action;
        if (isset($usedProducts[$productId])) {
            throw new DomainException($rowLabel . ': จับคู่กับพัสดุเดียวกับ ' . $usedProducts[$productId] . ' กรุณาเลือกให้ไม่ซ้ำกัน');
        }
        $usedProducts[$productId] = $rowLabel;
        if (!is_numeric($item['expected'] ?? null)) {
            throw new DomainException($rowLabel . ': ไม่พบยอดในระบบที่ใช้เปรียบเทียบ กรุณาตรวจสอบใหม่');
        }
        $expected = (float)$item['expected'];
        if ($applyMin && $min !== null) {
            $minStmt = $conn->prepare('UPDATE stock_products SET min_quantity = ? WHERE id = ? AND sup_id = ?');
            $minStmt->bind_param('dii', $min, $productId, $supId);
            $minStmt->execute();
            $minStmt->close();
        }
        if (abs($quantity - $expected) < 0.00001) {
            // Still verify the product belongs to this company and the balance hasn't moved.
            $current = stock_get_balance($conn, $productId);
            $ownerStmt = $conn->prepare('SELECT sup_id FROM stock_products WHERE id = ?');
            $ownerStmt->bind_param('i', $productId);
            $ownerStmt->execute();
            $owner = $ownerStmt->get_result()->fetch_assoc();
            $ownerStmt->close();
            if (!$owner || (int)$owner['sup_id'] !== $supId) {
                throw new DomainException($rowLabel . ': พัสดุไม่อยู่ในบริษัทที่เลือก');
            }
            if (abs($current - $expected) > 0.00001) {
                throw new DomainException($rowLabel . ': ยอด Stock เปลี่ยนแปลงระหว่างที่ตรวจสอบ กรุณาตรวจสอบไฟล์ใหม่อีกครั้ง');
            }
            $result['unchanged']++;
            continue;
        }
        try {
            stock_apply_manual_adjustment($conn, $supId, $productId, $expected, $quantity, $actorId, $reason);
        } catch (DomainException $exception) {
            throw new DomainException($rowLabel . ': ' . $exception->getMessage(), 0, $exception);
        }
        $result['adjusted']++;
    }
    return $result;
}

function stock_get_product_detail(mysqli $conn, int $productId): ?array
{
    $stmt = $conn->prepare(
        'SELECT p.id, p.sup_id, p.sku, p.name, p.category, p.unit, p.abc_class, p.lead_time_days, p.min_quantity,
                p.created_at, COALESCE(b.quantity, 0) quantity, u.name created_by_name
         FROM stock_products p
         LEFT JOIN stock_balances b ON b.product_id = p.id
         LEFT JOIN users u ON u.id = p.created_by
         WHERE p.id = ? AND p.is_active = 1'
    );
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $product ?: null;
}

function stock_product_movement_summary(mysqli $conn, int $productId): array
{
    $stmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN movement_type = 'in_po' THEN quantity END), 0) total_in,
            COALESCE(-SUM(CASE WHEN movement_type = 'out_withdrawal' THEN quantity END), 0) total_out,
            COALESCE(SUM(CASE WHEN movement_type = 'return' THEN quantity END), 0) total_return,
            COALESCE(SUM(CASE WHEN movement_type = 'adjustment' THEN quantity END), 0) total_adjustment,
            COUNT(*) movement_count,
            MAX(created_at) last_movement_at
         FROM stock_movements WHERE product_id = ?"
    );
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return [
        'total_in' => (float)$row['total_in'],
        'total_out' => (float)$row['total_out'],
        'total_return' => (float)$row['total_return'],
        'total_adjustment' => (float)$row['total_adjustment'],
        'movement_count' => (int)$row['movement_count'],
        'last_movement_at' => $row['last_movement_at'],
    ];
}

function stock_list_product_movements(mysqli $conn, int $productId, int $limit, int $offset): array
{
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $stmt = $conn->prepare(
        "SELECT m.id, m.movement_type, m.quantity, m.balance_before, m.balance_after, m.reference_type,
                m.note, m.created_at, actor.name actor_name,
                r.doc_no receipt_doc_no, po.id po_id, po.doc_no po_doc_no,
                w.id withdrawal_id, w.doc_no withdrawal_doc_no, w.purpose withdrawal_purpose, requester.name requester_name,
                rc.id reconciliation_id, rc.doc_no reconciliation_doc_no
         FROM stock_movements m
         LEFT JOIN users actor ON actor.id = m.actor_id
         LEFT JOIN stock_receipt_items ri ON m.reference_type = 'stock_receipt_item' AND ri.id = m.reference_id
         LEFT JOIN stock_receipts r ON r.id = ri.receipt_id
         LEFT JOIN po ON po.id = r.po_id
         LEFT JOIN stock_withdrawal_issues wi ON m.reference_type = 'withdrawal_issue' AND wi.id = m.reference_id
         LEFT JOIN stock_withdrawal_items wit ON wit.id = wi.withdrawal_item_id
         LEFT JOIN stock_withdrawals w ON w.id = wit.withdrawal_id
         LEFT JOIN users requester ON requester.id = w.requester_id
         LEFT JOIN stock_reconciliation_items rci ON m.reference_type = 'reconciliation_item' AND rci.id = m.reference_id
         LEFT JOIN stock_reconciliations rc ON rc.id = rci.reconciliation_id
         WHERE m.product_id = ?
         ORDER BY m.created_at DESC, m.id DESC
         LIMIT ? OFFSET ?"
    );
    $fetch = $limit + 1;
    $stmt->bind_param('iii', $productId, $fetch, $offset);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $hasMore = count($rows) > $limit;
    return ['rows' => array_slice($rows, 0, $limit), 'has_more' => $hasMore];
}

function stock_list_product_open_withdrawals(mysqli $conn, int $productId): array
{
    $stmt = $conn->prepare(
        "SELECT w.id, w.doc_no, w.status, w.created_at, u.name requester_name,
                wit.requested_quantity, wit.issued_quantity, wit.cancelled_quantity,
                GREATEST(wit.requested_quantity - wit.issued_quantity - wit.cancelled_quantity, 0) remaining_quantity
         FROM stock_withdrawal_items wit
         INNER JOIN stock_withdrawals w ON w.id = wit.withdrawal_id
         LEFT JOIN users u ON u.id = w.requester_id
         WHERE wit.product_id = ? AND w.status NOT IN ('completed', 'cancelled')
         ORDER BY w.created_at ASC"
    );
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function stock_list_product_top_requesters(mysqli $conn, int $productId, int $months = 6): array
{
    $stmt = $conn->prepare(
        "SELECT u.name requester_name, COUNT(DISTINCT w.id) withdrawal_count, SUM(wi.issued_quantity) issued_quantity
         FROM stock_withdrawal_issues wi
         INNER JOIN stock_withdrawal_items wit ON wit.id = wi.withdrawal_item_id
         INNER JOIN stock_withdrawals w ON w.id = wit.withdrawal_id
         LEFT JOIN users u ON u.id = w.requester_id
         WHERE wit.product_id = ? AND wi.issued_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
         GROUP BY w.requester_id, u.name
         ORDER BY issued_quantity DESC
         LIMIT 5"
    );
    $stmt->bind_param('ii', $productId, $months);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
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

function stock_list_product_plans(mysqli $conn, int $supId, array $products): array
{
    if ($supId <= 0 || !$products) {
        return [];
    }
    $historyMonths = STOCK_PLAN_HISTORY_MONTHS;
    $stmt = $conn->prepare(
        "SELECT p.id,
                LEAST(?, GREATEST(1, CEIL(DATEDIFF(NOW(), p.created_at) / 30))) history_months,
                COALESCE(usage_totals.used, 0) used,
                COALESCE(back_orders.back_order, 0) back_order,
                COALESCE(on_orders.on_order, 0) on_order
         FROM stock_products p
         LEFT JOIN (
             SELECT product_id, SUM(-quantity) used
             FROM stock_movements
             WHERE sup_id = ? AND movement_type = 'out_withdrawal' AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY product_id
         ) usage_totals ON usage_totals.product_id = p.id
         LEFT JOIN (
             SELECT wi.product_id, SUM(GREATEST(wi.requested_quantity - wi.issued_quantity - wi.cancelled_quantity, 0)) back_order
             FROM stock_withdrawal_items wi
             INNER JOIN stock_withdrawals w ON w.id = wi.withdrawal_id
             WHERE w.status NOT IN ('completed', 'cancelled')
             GROUP BY wi.product_id
         ) back_orders ON back_orders.product_id = p.id
         LEFT JOIN (
             SELECT ps.product_id,
                    SUM(GREATEST(pi.item_qty - COALESCE(received.received_quantity, 0), 0) * ps.units_per_purchase_unit) on_order
             FROM stock_po_item_settings ps
             INNER JOIN po_items pi ON pi.id = ps.po_item_id
             INNER JOIN po ON po.id = pi.po_id
             LEFT JOIN (
                 SELECT po_item_id, SUM(received_quantity) received_quantity
                 FROM stock_receipt_items
                 GROUP BY po_item_id
             ) received ON received.po_item_id = pi.id
             WHERE ps.destination = 'stock' AND ps.product_id IS NOT NULL
               AND po.status = 'approved' AND po.deleted_at IS NULL
             GROUP BY ps.product_id
         ) on_orders ON on_orders.product_id = p.id
         WHERE p.sup_id = ?"
    );
    $stmt->bind_param('iiii', $historyMonths, $supId, $historyMonths, $supId);
    $stmt->execute();
    $metrics = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $metrics[(int)$row['id']] = $row;
    }
    $stmt->close();

    $plans = [];
    foreach ($products as $product) {
        $productId = (int)$product['id'];
        $metric = $metrics[$productId] ?? ['history_months' => 1, 'used' => 0, 'back_order' => 0, 'on_order' => 0];
        $plans[$productId] = stock_plan_min_max(
            (string)$product['abc_class'],
            (int)$product['lead_time_days'],
            (float)$metric['used'],
            (int)$metric['history_months'],
            (float)$metric['back_order'],
            (float)$product['quantity'],
            (float)$metric['on_order'],
            (float)$product['min_quantity']
        ) + ['back_order' => (float)$metric['back_order'], 'on_order' => (float)$metric['on_order']];
    }
    return $plans;
}

function stock_update_product_plan_settings(
    mysqli $conn,
    int $productId,
    int $supId,
    string $class,
    int $leadTimeDays,
    float $manualMin
): void {
    if (!isset(STOCK_PLAN_CLASS_DAYS[$class]) || $leadTimeDays < 0 || $leadTimeDays > 365 || $manualMin < 0) {
        throw new DomainException('กรุณาระบุ Class, Lead Time และ Min ขั้นต่ำให้ถูกต้อง');
    }
    $checkStmt = $conn->prepare('SELECT id FROM stock_products WHERE id = ? AND sup_id = ? FOR UPDATE');
    $checkStmt->bind_param('ii', $productId, $supId);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    if (!$exists) {
        throw new DomainException('ไม่พบพัสดุในบริษัทที่เลือก');
    }
    $stmt = $conn->prepare(
        'UPDATE stock_products SET abc_class = ?, lead_time_days = ?, min_quantity = ? WHERE id = ? AND sup_id = ?'
    );
    $stmt->bind_param('sidii', $class, $leadTimeDays, $manualMin, $productId, $supId);
    $stmt->execute();
    $stmt->close();
}
