<?php

require_once __DIR__ . '/stock_common.php';

if (!stock_can_view_overview((string)$stock_actor['role'])) {
    stock_redirect('stock_my_withdrawals.php');
}

$selectedSupId = stock_selected_sup_id($stock_actor);
if ($selectedSupId <= 0 && !stock_can_manage($stock_actor['role'])) {
    http_response_code(403);
    die('บัญชีผู้ใช้ยังไม่ได้ผูกกับบริษัท/หน่วยงาน');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_category') {
    try {
        stock_verify_csrf();
        stock_require_manager($stock_actor);
        $supId = (int)($_POST['sup_id'] ?? 0);
        $conn->begin_transaction();
        stock_update_product_category(
            $conn,
            (int)($_POST['product_id'] ?? 0),
            $supId,
            (string)($_POST['category'] ?? ''),
            (int)$stock_actor['id']
        );
        $conn->commit();
        stock_flash('success', 'อัปเดตหมวดหมู่พัสดุแล้ว');
    } catch (Throwable $exception) {
        $conn->rollback();
        stock_flash('error', stock_error_message($exception));
    }
    stock_redirect('stock.php?sup_id=' . (int)($_POST['sup_id'] ?? 0));
}

$companies = stock_companies($conn);
$products = [];
if ($selectedSupId > 0) {
    $stmt = $conn->prepare(
        'SELECT p.*, COALESCE(b.quantity, 0) quantity, s.company_name
         FROM stock_products p
         INNER JOIN suppliers s ON s.id = p.sup_id
         LEFT JOIN stock_balances b ON b.product_id = p.id
         WHERE p.sup_id = ? AND p.is_active = 1
         ORDER BY p.name'
    );
    $stmt->bind_param('i', $selectedSupId);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$knownCategories = [];
if ($selectedSupId > 0) {
    $categoryStmt = $conn->prepare('SELECT name FROM stock_categories WHERE sup_id = ? AND is_active = 1 ORDER BY name');
    $categoryStmt->bind_param('i', $selectedSupId);
    $categoryStmt->execute();
    foreach ($categoryStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $categoryRow) {
        $knownCategories[(string)$categoryRow['name']] = true;
    }
    $categoryStmt->close();
}
foreach ($products as $product) {
    $category = trim((string)($product['category'] ?? ''));
    if ($category !== '') {
        $knownCategories[$category] = true;
    }
}
$productCategories = [];
foreach ($products as $product) {
    $category = trim((string)($product['category'] ?? ''));
    $productCategories[$category] = true;
}
uksort($productCategories, 'strnatcasecmp');
$flash = stock_take_flash();

include __DIR__ . '/header.php';
?>

<div class="max-w-7xl mx-auto p-4 md:p-8 space-y-6" data-stock-ui="procurement">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight">ภาพรวม Stock</h1>
            <p class="mt-1 text-sm text-slate-500">ยอดคงเหลือปัจจุบัน แยกตามบริษัท/หน่วยงาน</p>
        </div>
        <?php if (stock_can_manage($stock_actor['role'])): ?>
        <form method="get" class="w-full md:w-80">
            <label for="sup_id" class="block text-sm font-semibold text-slate-700 mb-1">บริษัท/หน่วยงาน</label>
            <select id="sup_id" name="sup_id" onchange="this.form.submit()"
                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm outline-none transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                <option value="">เลือกบริษัท</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= (int)$company['id'] ?>" <?= $selectedSupId === (int)$company['id'] ? 'selected' : '' ?>><?= stock_e(supplier_display_name($company['company_name'])) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>

    <?php stock_render_flash($flash); ?>

    <div id="stockProductsOverview" data-stock-overview-filter class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-4 md:px-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-black text-slate-900">พัสดุทั้งหมด</h2>
                <span data-stock-overview-result-count class="text-xs font-semibold text-slate-500">พบ <?= count($products) ?> จาก <?= count($products) ?> รายการ</span>
            </div>
            <div class="mt-4 flex flex-col gap-3 md:flex-row">
                <label class="block min-w-0 flex-1">
                    <span class="mb-1 block text-sm font-semibold text-slate-700">ค้นหาพัสดุ</span>
                    <span class="relative block"><i class="fas fa-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i><input type="search" data-stock-overview-search autocomplete="off" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-900 shadow-sm outline-none transition-colors placeholder:text-slate-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="พิมพ์ชื่อพัสดุเพื่อกรองทันที..."></span>
                </label>
                <label class="block md:w-64">
                    <span class="mb-1 block text-sm font-semibold text-slate-700">หมวดหมู่</span>
                    <select data-stock-overview-category class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition-colors focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        <option value="">ทุกหมวดหมู่</option>
                        <?php foreach (array_keys($productCategories) as $category): ?>
                            <option value="<?= stock_e($category !== '' ? $category : '__uncategorized__') ?>"><?= stock_e($category !== '' ? $category : 'ไม่ระบุหมวด') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="text-left px-4 py-3">พัสดุ</th><th class="text-left px-4 py-3">หมวดหมู่</th><th class="text-right px-4 py-3">คงเหลือ</th><th class="text-right px-4 py-3">จุดสั่งซื้อ</th><th class="text-center px-4 py-3">สถานะ</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($products as $product): $qty = (float)$product['quantity']; $low = $qty <= (float)$product['min_quantity']; ?>
                    <tr data-stock-overview-row data-product-name="<?= stock_e($product['name']) ?>" data-category="<?= stock_e(trim((string)($product['category'] ?? ''))) ?>" class="transition-colors hover:bg-slate-50"><td class="px-4 py-3 font-semibold text-slate-900"><span data-stock-overview-name-text><?= stock_e($product['name']) ?></span><div class="text-xs text-slate-500 mt-0.5"><?= stock_e(supplier_display_name($product['company_name'])) ?></div></td><td class="px-4 py-3 text-slate-600"><?php if (stock_can_manage($stock_actor['role'])): ?><form method="post" class="flex min-w-48 gap-2"><input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>"><input type="hidden" name="action" value="update_category"><input type="hidden" name="sup_id" value="<?= $selectedSupId ?>"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>"><select name="category" required data-stock-category-select class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs shadow-sm outline-none transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"><option value="" <?= empty($product['category']) ? 'selected' : '' ?> disabled>เลือกหมวดหมู่</option><?php foreach (array_keys($knownCategories) as $category): ?><option value="<?= stock_e($category) ?>" <?= $product['category'] === $category ? 'selected' : '' ?>><?= stock_e($category) ?></option><?php endforeach; ?></select><button class="rounded-xl border border-indigo-200 px-3 py-2 text-xs font-bold text-indigo-700 transition-colors hover:bg-indigo-50">บันทึก</button></form><?php else: ?><?= stock_e($product['category'] ?: 'ไม่ระบุหมวด') ?><?php endif; ?></td><td class="px-4 py-3 text-right font-bold <?= $qty <= 0 ? 'text-rose-700' : 'text-slate-900' ?>"><?= number_format($qty, 2) ?> <?= stock_e($product['unit']) ?></td><td class="px-4 py-3 text-right text-slate-600"><?= number_format((float)$product['min_quantity'], 2) ?></td><td class="px-4 py-3 text-center"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $low ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>"><?= $low ? 'ควรเติม Stock' : 'พร้อมเบิก' ?></span></td></tr>
                <?php endforeach; ?>
                <tr data-stock-overview-empty class="hidden"><td colspan="5" class="px-4 py-12 text-center text-slate-500"><i class="fas fa-search mb-3 block text-2xl text-slate-300"></i>ไม่พบพัสดุที่ตรงกับตัวกรอง</td></tr>
                <?php if (!$products): ?><tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">ยังไม่มีพัสดุในบริษัทนี้</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="assets/js/stock-overview-filter.js"></script>
<script>StockOverviewFilter.mountStockOverviewFilter(document.querySelector('[data-stock-overview-filter]'));</script>

<?php include __DIR__ . '/footer.php'; ?>
