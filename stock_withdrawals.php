<?php

require_once __DIR__ . '/stock_common.php';

$requestSupId = (int)($stock_actor['sup_id'] ?? 0);
if ($requestSupId <= 0) {
    http_response_code(403);
    die('กรุณาผูกบัญชีผู้ใช้กับบริษัท/หน่วยงานก่อนสร้างใบเบิก');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_withdrawal') {
    try {
        stock_verify_csrf();
        $requestSupId = (int)$stock_actor['sup_id'];
        if ($requestSupId <= 0 || (int)($_POST['sup_id'] ?? 0) !== $requestSupId || trim((string)$_POST['purpose']) === '') {
            throw new DomainException('กรุณาระบุบริษัทและวัตถุประสงค์การเบิก');
        }
        $conn->begin_transaction();
        $docNo = stock_generate_doc_no($conn, 'WD', 'stock_withdrawals');
        $purpose = trim((string)$_POST['purpose']);
        $headerStmt = $conn->prepare('INSERT INTO stock_withdrawals (doc_no, sup_id, requester_id, purpose) VALUES (?, ?, ?, ?)');
        $headerStmt->bind_param('siis', $docNo, $requestSupId, $stock_actor['id'], $purpose);
        $headerStmt->execute();
        $withdrawalId = (int)$conn->insert_id;
        $headerStmt->close();
        $itemCount = 0;
        foreach ((array)($_POST['quantity'] ?? []) as $productIdRaw => $quantityRaw) {
            $productId = (int)$productIdRaw;
            $quantity = (float)$quantityRaw;
            if ($quantity <= 0) {
                continue;
            }
            $check = $conn->prepare('SELECT id, name FROM stock_products WHERE id = ? AND sup_id = ? AND is_active = 1 FOR UPDATE');
            $check->bind_param('ii', $productId, $requestSupId);
            $check->execute();
            $valid = $check->get_result()->fetch_assoc();
            $check->close();
            if (!$valid) {
                throw new DomainException('พบสินค้าที่ไม่อยู่ในบริษัทของใบเบิก');
            }
            $balanceStmt = $conn->prepare('SELECT quantity FROM stock_balances WHERE product_id = ? FOR UPDATE');
            $balanceStmt->bind_param('i', $productId);
            $balanceStmt->execute();
            $balance = $balanceStmt->get_result()->fetch_assoc();
            $balanceStmt->close();
            try {
                $quantity = stock_validate_withdrawal_request_quantity($quantity, (float)($balance['quantity'] ?? 0));
            } catch (DomainException $exception) {
                throw new DomainException((string)$valid['name'] . ': ' . $exception->getMessage());
            }
            $itemStmt = $conn->prepare('INSERT INTO stock_withdrawal_items (withdrawal_id, product_id, requested_quantity) VALUES (?, ?, ?)');
            $itemStmt->bind_param('iid', $withdrawalId, $productId, $quantity);
            $itemStmt->execute();
            $itemStmt->close();
            $itemCount++;
        }
        if ($itemCount === 0) {
            throw new DomainException('กรุณาระบุจำนวนที่ต้องการอย่างน้อย 1 รายการ');
        }
        $conn->commit();
        stock_flash('success', "สร้างใบเบิก {$docNo} แล้ว และส่งให้ผู้ดูแล Stock");
        stock_redirect('stock_withdrawal_view.php?id=' . $withdrawalId);
    } catch (Throwable $exception) {
        $conn->rollback();
        stock_flash('error', stock_error_message($exception));
        stock_redirect('stock_withdrawals.php');
    }
}

$companies = stock_companies($conn);
$requestCompanyName = '-';
foreach ($companies as $company) {
    if ((int)$company['id'] === $requestSupId) {
        $requestCompanyName = (string)$company['company_name'];
        break;
    }
}
$productStmt = $conn->prepare(
    'SELECT p.id, p.sku, p.name, p.unit, p.category, COALESCE(b.quantity, 0) quantity
     FROM stock_products p LEFT JOIN stock_balances b ON b.product_id = p.id
     WHERE p.sup_id = ? AND p.is_active = 1 ORDER BY p.name'
);
$productStmt->bind_param('i', $requestSupId);
$productStmt->execute();
$products = $productStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$productStmt->close();
$withdrawalCategories = [];
foreach ($products as $product) {
    $category = trim((string)($product['category'] ?? ''));
    if ($category !== '') {
        $withdrawalCategories[$category] = true;
    }
}
uksort($withdrawalCategories, 'strnatcasecmp');

if (stock_can_manage($stock_actor['role'])) {
    $listStmt = $conn->prepare(
        'SELECT w.*, s.company_name, u.name requester_name,
                COUNT(wi.id) item_count, COALESCE(SUM(wi.requested_quantity), 0) requested_total,
                COALESCE(SUM(wi.received_quantity), 0) received_total
         FROM stock_withdrawals w INNER JOIN suppliers s ON s.id = w.sup_id INNER JOIN users u ON u.id = w.requester_id
         LEFT JOIN stock_withdrawal_items wi ON wi.withdrawal_id = w.id
         WHERE (? = 0 OR w.sup_id = ?) GROUP BY w.id ORDER BY w.id DESC LIMIT 150'
    );
    $filterSup = (int)($_GET['sup_id'] ?? 0);
    $listStmt->bind_param('ii', $filterSup, $filterSup);
    $listStmt->execute();
    $withdrawals = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $listStmt->close();
}
$flash = stock_take_flash();
include __DIR__ . '/header.php';
?>

<div class="max-w-7xl mx-auto p-4 md:p-8 space-y-6" data-stock-ui="procurement">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"><div><h1 class="text-2xl font-black text-slate-800 tracking-tight">ใบเบิกสินค้า</h1><p class="mt-1 text-sm text-slate-500">จำนวนที่ขอเบิกต้องไม่เกินยอดคงเหลือใน Stock ปัจจุบัน</p></div><?php if (stock_can_manage($stock_actor['role'])): ?><form method="get" class="w-full md:w-80"><label class="mb-1 block text-sm font-semibold text-slate-700">บริษัท/หน่วยงาน</label><select name="sup_id" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm outline-none transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"><option value="0">ทุกบริษัท</option><?php foreach ($companies as $company): ?><option value="<?= (int)$company['id'] ?>" <?= (int)($_GET['sup_id'] ?? 0) === (int)$company['id'] ? 'selected' : '' ?>><?= stock_e($company['company_name']) ?></option><?php endforeach; ?></select></form><?php endif; ?></div>
    <?php stock_render_flash($flash); ?>

    <form method="post" data-stock-withdrawal-cart>
        <details class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" open>
            <summary class="flex cursor-pointer list-none justify-between p-5 font-bold text-slate-800"><span><i class="fas fa-shopping-basket mr-2 text-indigo-600"></i>เลือกสินค้าเพื่อสร้างใบเบิก</span><i class="fas fa-chevron-down text-xs text-slate-400 transition-transform group-open:rotate-180"></i></summary>
            <div class="border-t border-slate-200">
            <input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>"><input type="hidden" name="action" value="create_withdrawal">
            <input type="hidden" name="sup_id" value="<?= $requestSupId ?>">
            <div data-stock-withdrawal-form-grid class="border-b border-slate-200 bg-slate-50/70 p-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <div class="mb-1 text-sm font-semibold text-slate-700">บริษัทของใบเบิก</div>
                        <div class="flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm"><i class="fas fa-building mr-2 text-indigo-500"></i><?= stock_e($requestCompanyName) ?></div>
                    </div>
                    <label class="block text-sm font-semibold text-slate-700">ค้นหาสินค้า<span class="relative mt-1 block"><i class="fas fa-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i><input type="search" data-stock-withdrawal-search autocomplete="off" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm font-normal text-slate-900 shadow-sm outline-none transition-colors placeholder:text-slate-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="พิมพ์ชื่อสินค้าเพื่อกรองทันที..."></span></label>
                    <label class="block text-sm font-semibold text-slate-700">หมวดหมู่สินค้า<select data-stock-category-filter class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm outline-none transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"><option value="">ทุกหมวดหมู่</option><?php foreach (array_keys($withdrawalCategories) as $category): ?><option value="<?= stock_e($category) ?>"><?= stock_e($category) ?></option><?php endforeach; ?></select></label>
                </div>
            </div>

            <div class="p-5">
                <div class="mb-3 flex items-center justify-between gap-3"><div><h3 class="font-bold text-slate-800">สินค้าที่เบิกได้</h3><p class="mt-0.5 text-xs text-slate-500">เลือกจำนวนแล้วเพิ่มสินค้าลงตะกร้า</p></div><span data-stock-withdrawal-result-count class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">พบ <?= count($products) ?> จาก <?= count($products) ?> รายการ</span></div>
                <div data-stock-product-responsive-list class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="hidden bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 md:grid md:grid-cols-[minmax(0,1fr)_9rem_18rem] md:gap-4"><div>สินค้า</div><div class="text-right">คงเหลือ</div><div>เลือกจำนวน</div></div>
                    <?php foreach ($products as $product): $availableQuantity = max(0, (float)$product['quantity']); ?>
                    <div data-stock-product-row data-product-id="<?= (int)$product['id'] ?>" data-product-name="<?= stock_e($product['name']) ?>" data-unit="<?= stock_e($product['unit']) ?>" data-available="<?= $availableQuantity ?>" data-category="<?= stock_e(trim((string)($product['category'] ?? ''))) ?>" class="grid grid-cols-1 gap-3 border-t border-slate-200 p-4 first:border-t-0 transition-colors hover:bg-slate-50 md:grid-cols-[minmax(0,1fr)_9rem_18rem] md:items-center md:gap-4">
                        <div class="min-w-0"><div data-stock-product-name-text class="font-semibold text-slate-900"><?= stock_e($product['name']) ?></div><span class="mt-1 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600"><?= stock_e($product['category'] ?: 'ไม่ระบุหมวด') ?></span></div>
                        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 md:block md:border-0 md:bg-transparent md:p-0 md:text-right"><span class="text-xs font-semibold text-slate-500 md:hidden">คงเหลือ</span><span class="font-bold <?= $availableQuantity <= 0 ? 'text-rose-600' : 'text-slate-800' ?>"><?= number_format($availableQuantity, 2) ?> <?= stock_e($product['unit']) ?></span></div>
                        <div>
                            <span class="mb-1.5 block text-xs font-semibold text-slate-500 md:hidden">จำนวนที่ต้องการ</span>
                            <div class="flex gap-2">
                                <div class="flex min-w-0 flex-1 items-center overflow-hidden rounded-xl border border-slate-200 bg-white">
                                    <button type="button" data-stock-product-decrement <?= $availableQuantity <= 0 ? 'disabled' : '' ?> class="h-11 w-10 shrink-0 text-slate-600 transition-colors hover:bg-slate-100 disabled:text-slate-300" aria-label="ลดจำนวน"><i class="fas fa-minus text-xs"></i></button>
                                    <input data-stock-product-quantity type="number" min="0.01" max="<?= $availableQuantity ?>" step="0.01" value="<?= $availableQuantity > 0 ? min(1, $availableQuantity) : 0 ?>" <?= $availableQuantity <= 0 ? 'disabled' : '' ?> class="h-11 min-w-0 flex-1 border-x border-slate-200 text-center font-bold text-slate-900 outline-none focus:bg-indigo-50 disabled:bg-slate-100 disabled:text-slate-400">
                                    <button type="button" data-stock-product-increment <?= $availableQuantity <= 0 ? 'disabled' : '' ?> class="h-11 w-10 shrink-0 text-slate-600 transition-colors hover:bg-slate-100 disabled:text-slate-300" aria-label="เพิ่มจำนวน"><i class="fas fa-plus text-xs"></i></button>
                                </div>
                                <button type="button" data-stock-add-to-cart <?= $availableQuantity <= 0 ? 'disabled' : '' ?> class="min-w-[8.5rem] rounded-xl bg-indigo-600 px-3 py-2.5 text-sm font-bold text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300"><i class="fas fa-cart-plus mr-1.5"></i><?= $availableQuantity > 0 ? 'เพิ่มลงตะกร้า' : 'สินค้าหมด' ?></button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($products): ?><div data-stock-category-empty class="hidden border-t border-slate-200 px-4 py-10 text-center text-sm text-slate-500"><i class="fas fa-search mb-3 block text-2xl text-slate-300"></i>ไม่พบสินค้าที่ตรงกับคำค้นหาและหมวดหมู่</div><?php else: ?><div class="px-4 py-12 text-center text-sm text-slate-500"><i class="fas fa-box-open mb-3 block text-2xl text-slate-300"></i>บริษัทนี้ยังไม่มีสินค้าใน Stock</div><?php endif; ?>
                </div>
            </div>
            <div class="border-t border-slate-200 bg-slate-50/70 px-5 py-4 text-xs text-slate-500"><i class="fas fa-circle-info mr-1 text-indigo-500"></i>เพิ่มสินค้าได้หลายรายการ และจำนวนรวมต้องไม่เกินยอดคงเหลือ</div>
            </div>
        </details>

        <button type="button" data-stock-cart-button aria-controls="stock-withdrawal-cart-panel" aria-expanded="false" class="fixed bottom-5 right-5 z-40 inline-flex min-h-14 items-center gap-3 rounded-full bg-indigo-600 px-5 py-3 font-bold text-white shadow-xl shadow-indigo-300/60 transition-all hover:bg-indigo-700 active:scale-95">
            <span class="relative"><i class="fas fa-shopping-cart text-lg"></i><span data-stock-cart-count class="absolute -right-3 -top-3 hidden min-w-5 rounded-full bg-rose-500 px-1.5 py-0.5 text-center text-[10px] leading-4 text-white ring-2 ring-white">0</span></span>
            <span>ตะกร้าเบิก</span>
        </button>

        <div data-stock-cart-backdrop class="fixed inset-0 z-40 hidden bg-slate-950/45 opacity-0 backdrop-blur-[1px] transition-opacity"></div>
        <aside id="stock-withdrawal-cart-panel" data-stock-cart-panel aria-hidden="true" aria-labelledby="stock-withdrawal-cart-title" class="fixed inset-x-0 bottom-0 z-50 flex max-h-[88vh] translate-y-full flex-col rounded-t-3xl border border-slate-200 bg-slate-50 shadow-2xl transition-transform duration-200 md:inset-y-0 md:left-auto md:right-0 md:max-h-none md:w-[28rem] md:translate-y-0 md:translate-x-full md:rounded-none md:rounded-l-3xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 md:rounded-tl-3xl">
                <div><h2 id="stock-withdrawal-cart-title" class="text-lg font-black text-slate-900"><i class="fas fa-shopping-cart mr-2 text-indigo-600"></i>ตะกร้าใบเบิก</h2><p data-stock-cart-summary class="mt-1 text-sm text-slate-500">ยังไม่มีสินค้า</p></div>
                <button type="button" data-stock-cart-close class="rounded-xl border border-slate-200 bg-white p-2.5 text-slate-500 transition-colors hover:bg-slate-100" aria-label="ปิดตะกร้า"><i class="fas fa-times"></i></button>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto p-5">
                <div data-stock-cart-empty class="rounded-2xl border-2 border-dashed border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-500"><i class="fas fa-basket-shopping mb-3 block text-3xl text-slate-300"></i>ยังไม่มีสินค้าในตะกร้า<br><span class="text-xs">เลือกจำนวนแล้วกด “เพิ่มลงตะกร้า”</span></div>
                <div data-stock-cart-items class="space-y-3"></div>
            </div>

            <div class="border-t border-slate-200 bg-white p-5 md:rounded-bl-3xl">
                <label class="block text-sm font-bold text-slate-800">วัตถุประสงค์การเบิก <span class="text-rose-500">*</span><textarea name="purpose" required rows="3" maxlength="1000" class="mt-2 w-full resize-none rounded-xl border border-slate-300 bg-white px-4 py-3 font-normal outline-none transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="นำไปใช้ที่ไหน หรือใช้สำหรับงานอะไร"></textarea></label>
                <p class="mt-2 text-xs text-slate-500"><i class="fas fa-building mr-1 text-indigo-500"></i><?= stock_e($requestCompanyName) ?></p>
                <button type="submit" data-stock-cart-submit disabled class="mt-4 w-full rounded-xl bg-indigo-600 px-5 py-3.5 text-sm font-bold text-white shadow-md shadow-indigo-200 transition-all hover:bg-indigo-700 active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"><i class="fas fa-paper-plane mr-2"></i>ยืนยันส่งใบเบิก</button>
            </div>
        </aside>
        <span data-stock-cart-live class="sr-only" aria-live="polite"></span>
    </form>

    <?php if (stock_can_manage($stock_actor['role'])): ?>
    <div data-stock-all-withdrawals class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="text-left px-4 py-3">เลขที่ใบเบิก</th><th class="text-left px-4 py-3">ผู้ขอ / บริษัท</th><th class="text-right px-4 py-3">รายการ</th><th class="text-right px-4 py-3">รับแล้ว</th><th class="text-center px-4 py-3">สถานะ</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100"><?php foreach ($withdrawals as $row): ?><tr class="transition-colors hover:bg-slate-50"><td class="px-4 py-3 font-semibold text-slate-900"><?= stock_e($row['doc_no']) ?><div class="text-xs font-normal text-slate-500"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></div></td><td class="px-4 py-3"><?= stock_e($row['requester_name']) ?><div class="text-xs text-slate-500"><?= stock_e($row['company_name']) ?></div></td><td class="px-4 py-3 text-right"><?= (int)$row['item_count'] ?></td><td class="px-4 py-3 text-right"><?= number_format((float)$row['received_total'], 2) ?> / <?= number_format((float)$row['requested_total'], 2) ?></td><td class="px-4 py-3 text-center"><span data-stock-withdrawal-status class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= stock_withdrawal_status_badge_class((string)$row['status']) ?>"><?= stock_e(stock_status_label($row['status'])) ?></span></td><td class="px-4 py-3 text-right"><a href="stock_withdrawal_view.php?id=<?= (int)$row['id'] ?>" class="inline-flex rounded-xl bg-indigo-50 px-3 py-2 font-bold text-indigo-700 transition-colors hover:bg-indigo-100">เปิด</a></td></tr><?php endforeach; ?><?php if (!$withdrawals): ?><tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">ยังไม่มีใบเบิก</td></tr><?php endif; ?></tbody></table></div></div>
    <?php endif; ?>
</div>
<script src="assets/js/stock-withdrawal-filter.js"></script>
<script src="assets/js/stock-withdrawal-cart.js"></script>
<script>
StockWithdrawalFilter.mountWithdrawalFilter(document.querySelector('[data-stock-withdrawal-cart]'));
StockWithdrawalCart.mountWithdrawalCart(document.querySelector('[data-stock-withdrawal-cart]'));
</script>
<?php include __DIR__ . '/footer.php'; ?>
