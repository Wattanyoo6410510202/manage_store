<?php

require_once __DIR__ . '/stock_common.php';
stock_require_manager($stock_actor);

$companies = stock_companies($conn);
$selectedSupId = stock_selected_sup_id($stock_actor);
$selectedPoId = (int)($_GET['po_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'receive_po') {
    $redirectSupId = (int)($_POST['sup_id'] ?? 0);
    $redirectPoId = (int)($_POST['po_id'] ?? 0);
    try {
        stock_verify_csrf();
        if ($redirectSupId <= 0 || $redirectPoId <= 0) {
            throw new DomainException('กรุณาเลือกบริษัทและ PO');
        }
        $conn->begin_transaction();
        $poStmt = $conn->prepare('SELECT id, status, supplier_id FROM po WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $poStmt->bind_param('i', $redirectPoId);
        $poStmt->execute();
        $po = $poStmt->get_result()->fetch_assoc();
        $poStmt->close();
        if (!$po || $po['status'] !== 'approved') {
            throw new DomainException('รับพัสดุได้เฉพาะ PO ที่อนุมัติแล้ว');
        }
        $receiptSupId = stock_receipt_sup_id((int)$po['supplier_id'], $redirectSupId);

        $anyToReceive = false;
        foreach ((array)($_POST['quantity'] ?? []) as $poItemIdRaw => $quantityRaw) {
            $quantityRawStr = trim((string)$quantityRaw);
            if ($quantityRawStr === '' || !preg_match('/^\d+$/', $quantityRawStr)) {
                throw new DomainException('จำนวนรับต้องเป็นจำนวนเต็มที่ไม่ติดลบ');
            }
            if ((int)$quantityRawStr > 0) {
                $anyToReceive = true;
            }
        }
        if (!$anyToReceive) {
            throw new DomainException('ยังไม่มีรายการที่ระบุจำนวนรับ กรุณาระบุจำนวนรับ หรือกลับไปเลือกใบสั่งซื้ออื่น');
        }

        $receiptDocNo = stock_generate_doc_no($conn, 'SR', 'stock_receipts');
        $note = trim((string)($_POST['note'] ?? ''));
        $receiptStmt = $conn->prepare('INSERT INTO stock_receipts (doc_no, po_id, sup_id, received_by, note) VALUES (?, ?, ?, ?, NULLIF(?, \'\'))');
        $receiptStmt->bind_param('siiis', $receiptDocNo, $redirectPoId, $receiptSupId, $stock_actor['id'], $note);
        $receiptStmt->execute();
        $receiptId = (int)$conn->insert_id;
        $receiptStmt->close();

        $savedItems = 0;
        foreach ((array)($_POST['quantity'] ?? []) as $poItemIdRaw => $quantityRaw) {
            $poItemId = (int)$poItemIdRaw;
            $quantityRawStr = trim((string)$quantityRaw);
            if ($quantityRawStr === '' || !preg_match('/^\d+$/', $quantityRawStr)) {
                throw new DomainException('จำนวนรับต้องเป็นจำนวนเต็มที่ไม่ติดลบ');
            }
            $quantity = (int)$quantityRawStr;
            if ($quantity < 0) {
                throw new DomainException('จำนวนรับต้องไม่ติดลบ');
            }
            if ($quantity === 0) {
                continue;
            }
            $itemStmt = $conn->prepare(
                'SELECT pi.id, pi.item_desc, pi.item_qty, pi.item_unit,
                        settings.destination saved_destination, settings.sup_id saved_sup_id,
                        settings.product_id saved_product_id,
                        settings.purchase_unit saved_purchase_unit,
                        settings.stock_unit saved_stock_unit,
                        settings.units_per_purchase_unit saved_units_per_purchase_unit
                 FROM po_items pi
                 LEFT JOIN stock_po_item_settings settings ON settings.po_item_id = pi.id
                 WHERE pi.id = ? AND pi.po_id = ? FOR UPDATE'
            );
            $itemStmt->bind_param('ii', $poItemId, $redirectPoId);
            $itemStmt->execute();
            $poItem = $itemStmt->get_result()->fetch_assoc();
            $itemStmt->close();
            if (!$poItem) {
                throw new DomainException('พบรายการที่ไม่ได้อยู่ใน PO นี้');
            }
            $receivedStmt = $conn->prepare('SELECT COALESCE(SUM(received_quantity), 0) received_quantity FROM stock_receipt_items WHERE po_item_id = ?');
            $receivedStmt->bind_param('i', $poItemId);
            $receivedStmt->execute();
            $poItem['received_quantity'] = $receivedStmt->get_result()->fetch_assoc()['received_quantity'] ?? 0;
            $receivedStmt->close();
            $remaining = stock_po_receivable_remaining((float)$poItem['item_qty'], (float)$poItem['received_quantity']);
            if ($quantity > $remaining + 0.00001) {
                throw new DomainException('จำนวนรับของ ' . $poItem['item_desc'] . ' เกินยอดค้างรับ');
            }

            $destination = (($_POST['destination'][$poItemId] ?? '') === 'stock') ? 'stock' : 'direct';
            if ($poItem['saved_destination'] !== null && $destination !== $poItem['saved_destination']) {
                throw new DomainException('ปลายทางของรายการ ' . $poItem['item_desc'] . ' ถูกกำหนดไว้แล้วและเปลี่ยนระหว่างรับบางส่วนไม่ได้');
            }
            if ($poItem['saved_sup_id'] !== null && (int)$poItem['saved_sup_id'] !== $receiptSupId) {
                throw new DomainException('ข้อมูลบริษัทของรายการ PO ไม่ตรงกับการรับครั้งก่อน');
            }
            $productId = null;
            $purchaseUnit = trim((string)($poItem['item_unit'] ?: 'หน่วย'));
            $stockUnit = null;
            $unitsPerPurchaseUnit = 1.0;
            $stockQuantity = 0.0;
            if ($destination === 'stock') {
                $productId = $poItem['saved_product_id'] !== null
                    ? (int)$poItem['saved_product_id']
                    : 0;
                if ($productId > 0) {
                    $productCheck = $conn->prepare('SELECT id, unit FROM stock_products WHERE id = ? AND sup_id = ? AND is_active = 1');
                    $productCheck->bind_param('ii', $productId, $receiptSupId);
                    $productCheck->execute();
                    $validProduct = $productCheck->get_result()->fetch_assoc();
                    $productCheck->close();
                    if (!$validProduct) {
                        throw new DomainException('พัสดุ Stock ที่เลือกไม่อยู่ในบริษัทปลายทาง');
                    }
                    $stockUnit = (string)$validProduct['unit'];
                } else {
                    $productId = stock_resolve_receipt_product(
                        $conn,
                        $receiptSupId,
                        (int)$stock_actor['id'],
                        $poItemId,
                        (string)$poItem['item_desc'],
                        (string)($poItem['item_unit'] ?: 'หน่วย'),
                        $_POST
                    );
                }
                if ($stockUnit === null) {
                    $productUnitStmt = $conn->prepare('SELECT unit FROM stock_products WHERE id = ? AND sup_id = ? AND is_active = 1');
                    $productUnitStmt->bind_param('ii', $productId, $receiptSupId);
                    $productUnitStmt->execute();
                    $productRow = $productUnitStmt->get_result()->fetch_assoc();
                    $productUnitStmt->close();
                    if (!$productRow) {
                        throw new DomainException('ไม่พบหน่วย Stock ของพัสดุที่เลือก');
                    }
                    $stockUnit = (string)$productRow['unit'];
                }

                if ($poItem['saved_product_id'] !== null) {
                    $unitsPerPurchaseUnit = (float)($poItem['saved_units_per_purchase_unit'] ?? 1);
                } else {
                    $submittedFactor = trim((string)($_POST['units_per_purchase_unit'][$poItemId] ?? ''));
                    if ($submittedFactor === '') {
                        $rememberedFactor = stock_get_product_unit_conversion($conn, $productId, $purchaseUnit);
                        if ($rememberedFactor === null) {
                            throw new DomainException('กรุณาระบุจำนวน ' . $stockUnit . ' ต่อ 1 ' . $purchaseUnit . ' ของ ' . $poItem['item_desc']);
                        }
                        $unitsPerPurchaseUnit = $rememberedFactor;
                    } else {
                        $unitsPerPurchaseUnit = (float)$submittedFactor;
                    }
                }
                $stockQuantity = stock_convert_purchase_to_stock_quantity($quantity, $unitsPerPurchaseUnit);
                stock_save_product_unit_conversion(
                    $conn,
                    $productId,
                    $purchaseUnit,
                    $unitsPerPurchaseUnit,
                    (int)$stock_actor['id']
                );
            }

            $settingStmt = $conn->prepare(
                'INSERT INTO stock_po_item_settings
                    (po_item_id, destination, sup_id, product_id, purchase_unit, stock_unit, units_per_purchase_unit, decided_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE destination = VALUES(destination), sup_id = VALUES(sup_id),
                    product_id = VALUES(product_id), purchase_unit = VALUES(purchase_unit), stock_unit = VALUES(stock_unit),
                    units_per_purchase_unit = VALUES(units_per_purchase_unit), decided_by = VALUES(decided_by), decided_at = CURRENT_TIMESTAMP'
            );
            $settingStmt->bind_param('isiissdi', $poItemId, $destination, $receiptSupId, $productId, $purchaseUnit, $stockUnit, $unitsPerPurchaseUnit, $stock_actor['id']);
            $settingStmt->execute();
            $settingStmt->close();

            $receiptItemStmt = $conn->prepare(
                'INSERT INTO stock_receipt_items
                    (receipt_id, po_item_id, product_id, destination, received_quantity, units_per_purchase_unit, stock_quantity)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $receiptItemStmt->bind_param('iiisddd', $receiptId, $poItemId, $productId, $destination, $quantity, $unitsPerPurchaseUnit, $stockQuantity);
            $receiptItemStmt->execute();
            $receiptItemId = (int)$conn->insert_id;
            $receiptItemStmt->close();

            if ($destination === 'stock') {
                stock_apply_movement(
                    $conn,
                    $receiptSupId,
                    $productId,
                    'in_po',
                    $stockQuantity,
                    'stock_receipt_item',
                    $receiptItemId,
                    (int)$stock_actor['id'],
                    'รับเข้าจาก PO ' . number_format($quantity, 2) . ' ' . $purchaseUnit
                        . ' × ' . number_format($unitsPerPurchaseUnit, 4) . ' = '
                        . number_format($stockQuantity, 2) . ' ' . $stockUnit
                );
            }
            $savedItems++;
        }
        if ($savedItems === 0) {
            throw new DomainException('ยังไม่มีรายการที่ระบุจำนวนรับ กรุณาระบุจำนวนรับ หรือกลับไปเลือกใบสั่งซื้ออื่น');
        }
        $conn->commit();
        stock_flash('success', "บันทึกรับพัสดุ {$receiptDocNo} แล้ว");
    } catch (Throwable $exception) {
        $conn->rollback();
        stock_flash('error', stock_error_message($exception));
    }
    stock_redirect("stock_receiving.php?sup_id={$redirectSupId}&po_id={$redirectPoId}");
}

$poList = stock_list_receivable_pos($conn);

$poData = null;
$poItems = [];
$stockProducts = [];
if ($selectedPoId > 0) {
    $poStmt = $conn->prepare('SELECT p.*, s.company_name FROM po p INNER JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ? AND p.status = \'approved\' AND p.deleted_at IS NULL');
    $poStmt->bind_param('i', $selectedPoId);
    $poStmt->execute();
    $poData = $poStmt->get_result()->fetch_assoc();
    $poStmt->close();
    if ($poData) {
        $selectedSupId = (int)$poData['supplier_id'];
    }
    $itemStmt = $conn->prepare(
        'SELECT pi.*, (SELECT COALESCE(SUM(sri.received_quantity), 0)
                       FROM stock_receipt_items sri WHERE sri.po_item_id = pi.id) received_quantity,
                settings.destination saved_destination, settings.product_id saved_product_id,
                settings.purchase_unit saved_purchase_unit, settings.stock_unit saved_stock_unit,
                settings.units_per_purchase_unit saved_units_per_purchase_unit
         FROM po_items pi LEFT JOIN stock_po_item_settings settings ON settings.po_item_id = pi.id
         WHERE pi.po_id = ? ORDER BY pi.id'
    );
    $itemStmt->bind_param('i', $selectedPoId);
    $itemStmt->execute();
    $poItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemStmt->close();
}
if ($selectedSupId > 0) {
    $productStmt = $conn->prepare(
        'SELECT p.id, p.sku, p.name, p.unit, p.category, COALESCE(b.quantity, 0) quantity
         FROM stock_products p LEFT JOIN stock_balances b ON b.product_id = p.id
         WHERE p.sup_id = ? AND p.is_active = 1 ORDER BY p.name'
    );
    $productStmt->bind_param('i', $selectedSupId);
    $productStmt->execute();
    $stockProducts = $productStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $productStmt->close();
    $productConversions = stock_list_product_unit_conversions($conn, $selectedSupId);
    foreach ($stockProducts as &$stockProduct) {
        $stockProduct['unit_conversions'] = $productConversions[(int)$stockProduct['id']] ?? [];
    }
    unset($stockProduct);
}
$stockCategories = [];
if ($selectedSupId > 0) {
    $categoryStmt = $conn->prepare('SELECT id, name FROM stock_categories WHERE sup_id = ? AND is_active = 1 ORDER BY name');
    $categoryStmt->bind_param('i', $selectedSupId);
    $categoryStmt->execute();
    $stockCategories = $categoryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $categoryStmt->close();
}
$stockProductsById = [];
foreach ($stockProducts as $product) {
    $stockProductsById[(int)$product['id']] = $product;
}
$flash = stock_take_flash();
include __DIR__ . '/header.php';
?>

<div class="max-w-7xl mx-auto p-4 md:p-8 space-y-6" data-stock-ui="procurement">
    <div><h1 class="text-2xl font-black text-slate-800 tracking-tight">รับพัสดุเข้าจาก PO</h1><p class="mt-1 text-sm text-slate-500">จัดซื้อเป็นผู้เลือกว่าพัสดุแต่ละรายการเข้า Stock หรือนำไปใช้ทันที</p></div>
    <?php stock_render_flash($flash); ?>
    <form method="get" class="grid grid-cols-1 gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm md:grid-cols-2">
        <div><div class="text-sm font-semibold text-slate-700">บริษัทปลายทาง</div><div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-700"><?= $poData ? stock_e(supplier_display_name($poData['company_name'])) : 'ระบบกำหนดจากบริษัทเจ้าของ PO' ?></div></div>
        <label class="text-sm font-semibold text-slate-700">PO ที่อนุมัติแล้วและยังรับไม่ครบ<select name="po_id" required onchange="this.form.submit()" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm outline-none transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"><option value="">เลือก PO</option><?php foreach ($poList as $po): ?><option value="<?= (int)$po['id'] ?>" <?= $selectedPoId === (int)$po['id'] ? 'selected' : '' ?>><?= stock_e($po['doc_no']) ?> · <?= stock_e(supplier_display_name($po['company_name'])) ?> · ค้าง <?= (int)$po['remaining_item_count'] ?> รายการ</option><?php endforeach; ?></select></label>
        <div class="text-right md:col-span-2"><button class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-50">แสดงรายการ</button></div>
    </form>

    <?php if ($poData && $selectedSupId > 0): ?>
    <form method="post" class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>"><input type="hidden" name="action" value="receive_po"><input type="hidden" name="po_id" value="<?= $selectedPoId ?>"><input type="hidden" name="sup_id" value="<?= $selectedSupId ?>">
        <div class="flex flex-wrap justify-between gap-3 border-b border-slate-100 p-5"><div><div class="font-bold text-slate-900"><?= stock_e($poData['doc_no']) ?></div><div class="mt-1 text-xs text-slate-500">กรอกเฉพาะจำนวนที่ได้รับจริงในครั้งนี้</div></div><a href="view_po.php?id=<?= $selectedPoId ?>" class="text-sm font-bold text-indigo-600 transition-colors hover:text-indigo-800 hover:underline">ดูเอกสาร PO</a></div>
        <div class="overflow-x-auto"><table class="min-w-[1180px] w-full text-sm"><thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="text-left px-4 py-3">รายการจาก PO</th><th class="text-right px-4 py-3">สั่ง</th><th class="text-right px-4 py-3">รับแล้ว</th><th class="text-left px-4 py-3 w-32">รับครั้งนี้</th><th class="text-left px-4 py-3 w-40">ปลายทาง</th><th class="text-left px-4 py-3 w-[34rem]">พัสดุและหน่วย Stock</th></tr></thead><tbody class="divide-y divide-slate-100">
        <?php foreach ($poItems as $item): $remaining = max(0, (float)$item['item_qty'] - (float)$item['received_quantity']); $savedDestination = $item['saved_destination'] ?: 'stock'; ?>
            <tr class="align-top">
                <td class="px-4 py-3 font-semibold text-slate-900"><?= stock_e($item['item_desc']) ?><div class="text-xs text-slate-500"><?= stock_e($item['item_unit']) ?> · ค้างรับ <?= number_format($remaining, 2) ?></div></td>
                <td class="px-4 py-3 text-right"><?= number_format((float)$item['item_qty'], 2) ?></td>
                <td class="px-4 py-3 text-right"><?= number_format((float)$item['received_quantity'], 2) ?></td>
                <td class="px-4 py-3">
                    <input type="number" min="0" max="<?= $remaining ?>" step="1" name="quantity[<?= (int)$item['id'] ?>]" value="0" <?= $remaining <= 0 ? 'disabled' : '' ?> inputmode="numeric" class="w-full rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-100">
                    <div class="hidden mt-1 text-[11px] font-medium text-rose-600" data-qty-error>กรุณากรอกจำนวนเต็มที่ไม่ติดลบ</div>
                </td>
                <td class="px-4 py-3"><input type="hidden" name="destination[<?= (int)$item['id'] ?>]" value="<?= stock_e($savedDestination) ?>"><select class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-100" <?= $item['saved_destination'] ? 'disabled' : '' ?> onchange="this.previousElementSibling.value=this.value;document.getElementById('product-<?= (int)$item['id'] ?>').classList.toggle('hidden', this.value !== 'stock')"><option value="stock" <?= $savedDestination === 'stock' ? 'selected' : '' ?>>เข้า Stock</option><option value="direct" <?= $savedDestination === 'direct' ? 'selected' : '' ?>>ใช้ทันที</option></select><?php if ($item['saved_destination']): ?><div class="text-[11px] text-slate-500 mt-1">ล็อกจากการรับครั้งแรก</div><?php endif; ?></td>
                <td class="px-4 py-3">
                    <div id="product-<?= (int)$item['id'] ?>" class="<?= $savedDestination === 'stock' ? '' : 'hidden' ?>">
                    <?php if ($item['saved_product_id']): $savedProduct = $stockProductsById[(int)$item['saved_product_id']] ?? null; ?>
                        <input type="hidden" name="product_id[<?= (int)$item['id'] ?>]" value="<?= (int)$item['saved_product_id'] ?>">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-900"><?= stock_e($savedProduct['name'] ?? 'พัสดุเดิม') ?><div class="mt-0.5 text-[11px] font-normal text-emerald-700"><?= stock_e($savedProduct['category'] ?? '') ?> · 1 <?= stock_e($item['saved_purchase_unit'] ?: $item['item_unit']) ?> = <?= number_format((float)($item['saved_units_per_purchase_unit'] ?? 1), 4) ?> <?= stock_e($item['saved_stock_unit'] ?: ($savedProduct['unit'] ?? 'หน่วย')) ?> · ล็อกจากการรับครั้งแรก</div></div>
                    <?php else: ?>
                        <div data-stock-product-picker data-item-id="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="product_id[<?= (int)$item['id'] ?>]" value="0" data-product-id>
                            <label class="block text-[11px] font-semibold text-slate-600">ชื่อพัสดุ<input name="new_product_name[<?= (int)$item['id'] ?>]" value="<?= stock_e($item['item_desc']) ?>" autocomplete="off" data-product-name class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="พิมพ์ชื่อพัสดุ"></label>
                            <div data-product-suggestions class="hidden mt-2 max-h-44 overflow-y-auto rounded-lg border border-slate-200 bg-white"></div>
                            <div data-selected-product class="hidden mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-800"></div>
                            <div data-new-product-fields class="mt-2 rounded-lg bg-slate-50 p-3">
                                <label class="block text-[11px] font-semibold text-slate-600">หมวดหมู่พัสดุ <span class="text-rose-600">*</span><select name="category_id[<?= (int)$item['id'] ?>]" data-category-select class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs"><?php foreach ($stockCategories as $index => $category): ?><option value="<?= (int)$category['id'] ?>"><?= stock_e($category['name']) ?></option><?php endforeach; ?><option value="0" <?= !$stockCategories ? 'selected' : '' ?>>+ เพิ่มหมวดใหม่</option></select></label>
                                <label data-new-category class="<?= $stockCategories ? 'hidden ' : '' ?>mt-2 block text-[11px] font-semibold text-slate-600">ชื่อหมวดใหม่<input name="new_category_name[<?= (int)$item['id'] ?>]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs" placeholder="เช่น ของใช้ในห้องพัก"></label>
                            </div>
                            <div class="mt-2 rounded-xl border border-indigo-100 bg-indigo-50/60 p-3" data-unit-conversion data-purchase-unit="<?= stock_e($item['item_unit'] ?: 'หน่วย') ?>">
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="block text-[11px] font-semibold text-slate-600">หน่วย Stock/หน่วยเบิก <span class="text-rose-600">*</span><input name="stock_unit[<?= (int)$item['id'] ?>]" data-stock-unit maxlength="50" autocomplete="off" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="เช่น ชิ้น"><div data-stock-unit-dropdown class="hidden mt-1 w-full max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-md"></div></label>
                                    <label class="block text-[11px] font-semibold text-slate-600">1 <?= stock_e($item['item_unit'] ?: 'หน่วย') ?> เท่ากับ <span class="text-rose-600">*</span><input type="number" name="units_per_purchase_unit[<?= (int)$item['id'] ?>]" data-conversion-factor min="0.0001" step="0.0001" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="เช่น 24"></label>
                                </div>
                                <p class="mt-2 text-[11px] font-medium text-indigo-700" data-conversion-preview>ระบุว่า 1 <?= stock_e($item['item_unit'] ?: 'หน่วย') ?> มีทั้งหมดกี่หน่วยย่อย</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <div class="flex flex-col gap-4 border-t border-slate-100 p-5 md:flex-row md:items-end"><label class="flex-1 text-sm font-semibold text-slate-700">หมายเหตุ<textarea name="note" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"></textarea></label><div class="flex flex-wrap items-center gap-3"><a href="stock_receiving.php" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-50">กลับไปเลือกใบสั่งซื้อ</a><button class="rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-md shadow-indigo-200 transition-all hover:bg-indigo-700 active:scale-95">ยืนยันรับพัสดุ</button></div></div>
    </form>
    <?php endif; ?>
</div>
<script src="assets/js/stock-product-autocomplete.js"></script>
<script>
(() => {
    const products = <?= json_encode($stockProducts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const rankProducts = window.StockProductAutocomplete.rankStockProducts;
    const resolveUnitConversion = window.StockProductAutocomplete.resolveStockUnitConversion;
    document.querySelectorAll('[data-stock-product-picker]').forEach((picker) => {
        const input = picker.querySelector('[data-product-name]');
        const productId = picker.querySelector('[data-product-id]');
        const suggestions = picker.querySelector('[data-product-suggestions]');
        const selected = picker.querySelector('[data-selected-product]');
        const newFields = picker.querySelector('[data-new-product-fields]');
        const categorySelect = picker.querySelector('[data-category-select]');
        const newCategory = picker.querySelector('[data-new-category]');
        const conversion = picker.querySelector('[data-unit-conversion]');
        const purchaseUnit = conversion.dataset.purchaseUnit;
        const stockUnit = conversion.querySelector('[data-stock-unit]');
        const conversionFactor = conversion.querySelector('[data-conversion-factor]');
        const conversionPreview = conversion.querySelector('[data-conversion-preview]');

        const updateConversionPreview = () => {
            const factor = Number(conversionFactor.value);
            if (stockUnit.value.trim() && factor > 0) {
                conversionPreview.textContent = `1 ${purchaseUnit} = ${factor.toLocaleString('th-TH')} ${stockUnit.value.trim()}`;
            } else {
                conversionPreview.textContent = `ระบุว่า 1 ${purchaseUnit} มีทั้งหมดกี่หน่วยย่อย`;
            }
        };

        const renderSuggestions = (resetSelection) => {
            if (resetSelection) {
                productId.value = '0';
                selected.classList.add('hidden');
                newFields.classList.remove('hidden');
                stockUnit.value = '';
                stockUnit.readOnly = false;
                stockUnit.classList.remove('bg-slate-100', 'text-slate-500');
                conversionFactor.value = '';
                updateConversionPreview();
            }
            const matches = rankProducts(products, input.value);
            suggestions.replaceChildren();
            matches.forEach((product) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'block w-full border-b border-slate-100 px-3 py-2 text-left last:border-0 hover:bg-indigo-50 focus:bg-indigo-50';
                const name = document.createElement('div');
                name.className = 'text-sm font-semibold text-slate-900';
                name.textContent = product.name;
                const detail = document.createElement('div');
                detail.className = 'mt-0.5 text-[11px] text-slate-500';
                detail.textContent = `${product.category || 'ไม่ระบุหมวด'} · คงเหลือ ${Number(product.quantity).toLocaleString('th-TH')} ${product.unit}`;
                button.append(name, detail);
                button.addEventListener('click', () => {
                    productId.value = String(product.id);
                    input.value = product.name;
                    suggestions.classList.add('hidden');
                    selected.textContent = `เลือกพัสดุเดิม: ${product.name} · ${product.category || 'ไม่ระบุหมวด'}`;
                    selected.classList.remove('hidden');
                    newFields.classList.add('hidden');
                    const resolvedConversion = resolveUnitConversion(product, purchaseUnit);
                    stockUnit.value = resolvedConversion.stockUnit;
                    stockUnit.readOnly = true;
                    stockUnit.classList.add('bg-slate-100', 'text-slate-500');
                    conversionFactor.value = resolvedConversion.factor ?? '';
                    updateConversionPreview();
                });
                suggestions.appendChild(button);
            });
            suggestions.classList.toggle('hidden', matches.length === 0);
        };

        input.addEventListener('input', () => renderSuggestions(true));
        input.addEventListener('focus', () => renderSuggestions(false));
        stockUnit.addEventListener('input', updateConversionPreview);
        conversionFactor.addEventListener('input', updateConversionPreview);
        categorySelect.addEventListener('change', () => {
            newCategory.classList.toggle('hidden', categorySelect.value !== '0');
        });
    });

    const stockUnitOptions = ['ชิ้น','อัน','ตัว','เครื่อง','ชุด','กล่อง','แพ็ค','ลัง','ถุง','กระสอบ','ม้วน','หลอด','ขวด','กระป๋อง','แกลลอน','ใบ','แผ่น','เส้น','ก้อน','คู่','โหล','เมตร','เซนติเมตร','มิลลิเมตร','ตารางเมตร','ลูกบาศก์เมตร','ลิตร','มิลลิลิตร','กิโลกรัม','กรัม'];

    document.querySelectorAll('input[data-stock-unit]').forEach((unitInput) => {
        const dropdown = unitInput.parentElement.querySelector('[data-stock-unit-dropdown]');
        if (!dropdown) return;
        let activeIndex = -1;

        const renderUnits = (query) => {
            const matches = stockUnitOptions.filter((unit) => unit.includes(query.trim()));
            dropdown.replaceChildren();
            activeIndex = -1;
            matches.forEach((unit, index) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-indigo-50';
                item.textContent = unit;
                item.addEventListener('click', () => {
                    unitInput.value = unit;
                    closeUnitDropdown();
                    unitInput.dispatchEvent(new Event('input', { bubbles: true }));
                });
                item.setAttribute('data-index', String(index));
                dropdown.appendChild(item);
            });
        };

        const openUnitDropdown = () => {
            if (unitInput.readOnly || unitInput.disabled) return;
            renderUnits(unitInput.value);
            dropdown.classList.remove('hidden');
        };

        const closeUnitDropdown = () => {
            dropdown.classList.add('hidden');
            activeIndex = -1;
        };

        const setActive = (items) => {
            items.forEach((el, i) => el.classList.toggle('bg-indigo-50', i === activeIndex));
            if (items[activeIndex]) items[activeIndex].scrollIntoView({ block: 'nearest' });
        };

        unitInput.addEventListener('focus', openUnitDropdown);
        unitInput.addEventListener('click', openUnitDropdown);
        unitInput.addEventListener('input', () => {
            if (unitInput.readOnly) return;
            renderUnits(unitInput.value);
            dropdown.classList.remove('hidden');
        });
        unitInput.addEventListener('keydown', (e) => {
            if (dropdown.classList.contains('hidden')) {
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    openUnitDropdown();
                }
                return;
            }
            const items = dropdown.querySelectorAll('button');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                setActive(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + items.length) % items.length;
                setActive(items);
            } else if (e.key === 'Enter') {
                if (activeIndex >= 0 && items[activeIndex]) {
                    e.preventDefault();
                    items[activeIndex].click();
                }
            } else if (e.key === 'Escape') {
                closeUnitDropdown();
            }
        });

        document.addEventListener('click', (e) => {
            if (!unitInput.parentElement.contains(e.target)) closeUnitDropdown();
        });
    });

    document.querySelectorAll('input[name^="quantity["]').forEach((qty) => {
        const errorBox = qty.closest('td').querySelector('[data-qty-error]');
        qty.addEventListener('input', () => {
            if (qty.value === '' || qty.validity.valid) {
                errorBox.classList.add('hidden');
                return;
            }
            if (qty.validity.rangeOverflow) {
                errorBox.textContent = `จำนวนรับต้องไม่เกินจำนวนคงเหลือ ${qty.max}`;
            } else {
                errorBox.textContent = 'กรุณากรอกจำนวนเต็มที่ไม่ติดลบ';
            }
            errorBox.classList.remove('hidden');
        });
    });
})();
</script>
<?php include __DIR__ . '/footer.php'; ?>
