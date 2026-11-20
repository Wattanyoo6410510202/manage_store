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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_product') {
    try {
        stock_verify_csrf();
        stock_require_manager($stock_actor);
        $supId = (int)($_POST['sup_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $conn->begin_transaction();
        stock_update_product_identity(
            $conn,
            $productId,
            $supId,
            (string)($_POST['name'] ?? ''),
            (string)($_POST['unit'] ?? '')
        );
        stock_update_product_category($conn, $productId, $supId, (string)($_POST['category'] ?? ''), (int)$stock_actor['id']);
        stock_update_product_plan_settings(
            $conn,
            $productId,
            $supId,
            (string)($_POST['abc_class'] ?? ''),
            (int)($_POST['lead_time_days'] ?? -1),
            (float)($_POST['min_quantity'] ?? -1)
        );
        $conn->commit();
        stock_flash('success', 'บันทึกการตั้งค่าพัสดุแล้ว');
    } catch (Throwable $exception) {
        $conn->rollback();
        stock_flash('error', stock_error_message($exception));
    }
    stock_redirect('stock.php?sup_id=' . (int)($_POST['sup_id'] ?? 0));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_product') {
    try {
        stock_verify_csrf();
        stock_require_manager($stock_actor);
        $supId = (int)($_POST['sup_id'] ?? 0);
        $minRaw = (string)($_POST['min_quantity'] ?? '0');
        $initialRaw = trim((string)($_POST['initial_quantity'] ?? ''));
        if ($initialRaw !== '' && !stock_can_approve_reconciliation((string)$stock_actor['role'])) {
            $initialRaw = '';
        }
        if (($minRaw !== '' && !is_numeric($minRaw)) || ($initialRaw !== '' && (!is_numeric($initialRaw) || (float)$initialRaw < 0))) {
            throw new DomainException('กรุณากรอกจำนวนให้ถูกต้อง');
        }
        $conn->begin_transaction();
        $sku = stock_generate_internal_sku($supId);
        $category = trim((string)($_POST['category'] ?? ''));
        if ($category === '__new__') {
            $category = trim((string)($_POST['new_category'] ?? ''));
        }
        if ($category === '') {
            throw new DomainException('กรุณาระบุหมวดหมู่');
        }
        $productId = stock_create_product(
            $conn,
            $supId,
            (int)$stock_actor['id'],
            $sku,
            (string)($_POST['name'] ?? ''),
            (string)($_POST['unit'] ?? ''),
            $category,
            (float)$minRaw
        );
        if ($initialRaw !== '' && (float)$initialRaw > 0) {
            stock_apply_manual_adjustment($conn, $supId, $productId, 0, (float)$initialRaw, (int)$stock_actor['id'], 'ยอดเริ่มต้นเมื่อเพิ่มพัสดุ');
        }
        $conn->commit();
        stock_flash('success', 'เพิ่มพัสดุแล้ว');
    } catch (Throwable $exception) {
        $conn->rollback();
        stock_flash('error', stock_error_message($exception));
    }
    stock_redirect('stock.php?sup_id=' . (int)($_POST['sup_id'] ?? 0));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_product') {
    try {
        stock_verify_csrf();
        stock_require_manager($stock_actor);
        $conn->begin_transaction();
        $canWriteOff = stock_can_approve_reconciliation((string)$stock_actor['role']);
        $expectedRaw = (string)($_POST['expected_quantity'] ?? '');
        $deletedName = stock_deactivate_product(
            $conn,
            (int)($_POST['product_id'] ?? 0),
            (int)($_POST['sup_id'] ?? 0),
            $canWriteOff ? (int)$stock_actor['id'] : 0,
            is_numeric($expectedRaw) ? (float)$expectedRaw : null
        );
        $conn->commit();
        stock_flash('success', 'ลบพัสดุ ' . $deletedName . ' แล้ว');
    } catch (Throwable $exception) {
        $conn->rollback();
        stock_flash('error', stock_error_message($exception));
    }
    stock_redirect('stock.php?sup_id=' . (int)($_POST['sup_id'] ?? 0));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust_stock') {
    try {
        stock_verify_csrf();
        stock_require_manager($stock_actor);
        if (!stock_can_approve_reconciliation((string)$stock_actor['role'])) {
            throw new DomainException('เฉพาะ GMHR หรือผู้ดูแลระบบเท่านั้นที่ปรับยอด Stock ได้');
        }
        $newBalance = (string)($_POST['new_quantity'] ?? '');
        $expectedBalance = (string)($_POST['expected_quantity'] ?? '');
        if (!is_numeric($newBalance) || !is_numeric($expectedBalance)) {
            throw new DomainException('กรุณากรอกยอดคงเหลือใหม่ให้ถูกต้อง');
        }
        $conn->begin_transaction();
        $after = stock_apply_manual_adjustment(
            $conn,
            (int)($_POST['sup_id'] ?? 0),
            (int)($_POST['product_id'] ?? 0),
            (float)$expectedBalance,
            (float)$newBalance,
            (int)$stock_actor['id'],
            (string)($_POST['reason'] ?? '')
        );
        $conn->commit();
        stock_flash('success', 'ปรับยอด Stock แล้ว คงเหลือ ' . stock_format_qty($after));
    } catch (Throwable $exception) {
        $conn->rollback();
        stock_flash('error', stock_error_message($exception));
    }
    stock_redirect('stock.php?sup_id=' . (int)($_POST['sup_id'] ?? 0));
}

$canManage = stock_can_manage($stock_actor['role']);
$canAdjust = $canManage && stock_can_approve_reconciliation((string)$stock_actor['role']);
$companies = stock_companies($conn);
$products = [];
if ($selectedSupId > 0) {
    $stmt = $conn->prepare(
        'SELECT p.*, COALESCE(b.quantity, 0) quantity
         FROM stock_products p
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
$productCategories = [];
foreach ($products as $product) {
    $category = trim((string)($product['category'] ?? ''));
    $productCategories[$category] = true;
    if ($category !== '') {
        $knownCategories[$category] = true;
    }
}
uksort($productCategories, 'strnatcasecmp');
uksort($knownCategories, 'strnatcasecmp');
$plans = stock_list_product_plans($conn, $selectedSupId, $products);

$selectedCompanyName = '';
foreach ($companies as $company) {
    if ((int)$company['id'] === $selectedSupId) {
        $selectedCompanyName = supplier_display_name($company['company_name']);
    }
}

$exportRows = [];
foreach ($products as $product) {
    $plan = $plans[(int)$product['id']];
    $exportRows[(int)$product['id']] = [
        trim((string)$product['name']),
        trim((string)($product['category'] ?? '')) ?: 'ไม่ระบุหมวด',
        (string)$product['unit'],
        (float)$product['quantity'],
        (float)$plan['on_order'],
        (float)$plan['back_order'],
        (float)$plan['avg_monthly_usage'],
        (string)$product['abc_class'],
        (int)$product['lead_time_days'],
        (int)$plan['cover_days_need'],
        $plan['cover_days'] !== null ? (float)$plan['cover_days'] : null,
        (float)$plan['min'],
        (float)$plan['max'],
        (float)$plan['suggested_order'],
        stock_plan_status_label($plan['status']),
    ];
}

$planStatusBadges = [
    'buy' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    'can_buy' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
    'no_buy' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'no_plan' => 'bg-slate-50 text-slate-500 ring-slate-500/20',
];
$flash = stock_take_flash();

include __DIR__ . '/header.php';

$qtyCell = static function (float $value, string $unit = ''): string {
    if (abs($value) < 0.00001) {
        return '<span class="text-slate-300">–</span>';
    }
    return '<span class="font-medium tabular-nums text-slate-700">' . stock_format_qty($value) . '</span>'
        . ($unit !== '' ? ' <span class="text-xs text-slate-400">' . stock_e($unit) . '</span>' : '');
};
?>

<div class="mx-auto max-w-screen-2xl space-y-6 p-4 md:p-8" data-stock-ui="procurement">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold text-cyan-700">คลังพัสดุ</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">ภาพรวม Stock</h1>
            <p class="mt-1 text-sm text-slate-500"><?= $selectedCompanyName !== '' ? stock_e($selectedCompanyName) . ' · ' : '' ?>ยอดคงเหลือและคำแนะนำการสั่งซื้อตาม Min / Max</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <?php if ($canManage): ?>
            <form method="get" class="sm:w-80">
                <label for="sup_id" class="sr-only">บริษัท/หน่วยงาน</label>
                <select id="sup_id" name="sup_id" onchange="this.form.submit()"
                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-800 shadow-sm outline-none transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    <option value="">เลือกบริษัท/หน่วยงาน</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= (int)$company['id'] ?>" <?= $selectedSupId === (int)$company['id'] ? 'selected' : '' ?>><?= stock_e(supplier_display_name($company['company_name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            <?php if ($canManage && $selectedSupId > 0): ?>
            <button type="button" data-stock-add-product class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-indigo-700">
                <i class="fas fa-plus"></i>เพิ่มพัสดุ
            </button>
            <?php endif; ?>
            <?php if ($canAdjust): ?>
            <a href="stock_import.php<?= $selectedSupId > 0 ? '?sup_id=' . $selectedSupId : '' ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                <i class="fas fa-file-import text-amber-600"></i>นำเข้ายอดนับ
            </a>
            <?php endif; ?>
            <?php if ($products): ?>
            <button type="button" id="stockExportExcel" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                <i class="fas fa-file-excel text-emerald-600"></i>Export Excel
            </button>
            <?php endif; ?>
        </div>
    </header>

    <?php stock_render_flash($flash); ?>

    <?php if ($selectedSupId <= 0): ?>
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
        <i class="fas fa-building text-3xl text-slate-300"></i>
        <p class="mt-3 font-semibold text-slate-700">เลือกบริษัท/หน่วยงานเพื่อดูยอด Stock</p>
        <p class="mt-1 text-sm text-slate-500">ใช้ตัวเลือกด้านบนขวา</p>
    </div>
    <?php else: ?>

    <div id="stockProductsOverview" data-stock-overview-filter class="space-y-4">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 md:flex-row md:items-center">
                <h2 class="font-bold text-slate-900 md:mr-2">พัสดุทั้งหมด</h2>
                <label class="relative block min-w-0 flex-1">
                    <span class="sr-only">ค้นหาพัสดุ</span>
                    <i class="fas fa-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                    <input type="search" data-stock-overview-search autocomplete="off" placeholder="ค้นหาชื่อพัสดุ..."
                        class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-900 outline-none transition-colors placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                </label>
                <label class="block md:w-60">
                    <span class="sr-only">หมวดหมู่</span>
                    <select data-stock-overview-category class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 outline-none transition-colors focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        <option value="">ทุกหมวดหมู่</option>
                        <?php foreach (array_keys($productCategories) as $category): ?>
                            <option value="<?= stock_e($category !== '' ? $category : '__uncategorized__') ?>"><?= stock_e($category !== '' ? $category : 'ไม่ระบุหมวด') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <span data-stock-overview-result-count class="whitespace-nowrap text-xs font-semibold text-slate-500">พบ <?= count($products) ?> จาก <?= count($products) ?> รายการ</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-sm">
                    <thead class="whitespace-nowrap bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">พัสดุ</th>
                            <th class="px-4 py-3 text-right">คงเหลือ</th>
                            <th class="px-4 py-3 text-right">ค้างรับ</th>
                            <th class="px-4 py-3 text-right">ค้างเบิก</th>
                            <th class="px-4 py-3 text-right">ใช้เฉลี่ย/เดือน</th>
                            <th class="px-4 py-3 text-right">Min – Max</th>
                            <th class="px-4 py-3 text-right">ควรสั่ง</th>
                            <th class="px-4 py-3 text-left">สถานะ</th>
                            <?php if ($canManage): ?><th class="<?= $canAdjust ? 'w-24' : 'w-12' ?> px-2 py-3"><span class="sr-only">ตั้งค่า</span></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($products as $product):
                        $qty = (float)$product['quantity'];
                        $plan = $plans[(int)$product['id']];
                        $category = trim((string)($product['category'] ?? ''));
                        $unit = (string)$product['unit'];
                    ?>
                        <tr data-stock-overview-row data-product-id="<?= (int)$product['id'] ?>" data-product-name="<?= stock_e($product['name']) ?>" data-category="<?= stock_e($category) ?>" data-status="<?= stock_e($plan['status']) ?>" data-out="<?= $qty <= 0 ? '1' : '0' ?>" class="align-top transition-colors hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <button type="button" data-stock-detail-product="<?= (int)$product['id'] ?>" class="text-left font-semibold text-slate-900 underline-offset-2 transition-colors hover:text-indigo-700 hover:underline focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-indigo-500" title="ดูรายละเอียดและประวัติ"><span data-stock-overview-name-text><?= stock_e($product['name']) ?></span></button>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                                    <span class="<?= $category === '' ? 'italic text-slate-400' : '' ?>"><?= stock_e($category !== '' ? $category : 'ไม่ระบุหมวด') ?></span>
                                    <span class="text-slate-300">·</span>
                                    <span title="<?= stock_e(stock_plan_class_label((string)$product['abc_class'])) ?>">Class <?= stock_e($product['abc_class']) ?> · Lead <?= (int)$product['lead_time_days'] ?> วัน</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <span class="text-base font-bold tabular-nums <?= $qty <= 0 ? 'text-rose-600' : 'text-slate-900' ?>"><?= stock_format_qty($qty) ?></span>
                                <span class="text-xs text-slate-500"><?= stock_e($unit) ?></span>
                                <?php if ($plan['cover_days'] !== null): ?><div class="mt-0.5 text-xs text-slate-400">พอใช้ ~<?= stock_format_qty((float)$plan['cover_days'], 1) ?> วัน</div><?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right"><?= $qtyCell((float)$plan['on_order']) ?></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right"><?= $qtyCell((float)$plan['back_order']) ?></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right"><?= $qtyCell((float)$plan['avg_monthly_usage']) ?></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700"><?= $plan['min'] > 0 ? stock_format_qty((float)$plan['min'], 0) . ' – ' . stock_format_qty((float)$plan['max'], 0) : '<span class="text-slate-300">–</span>' ?></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right"><?= $plan['suggested_order'] > 0 ? '<span class="font-bold tabular-nums text-indigo-700">' . stock_format_qty((float)$plan['suggested_order'], 0) . '</span> <span class="text-xs text-indigo-400">' . stock_e($unit) . '</span>' : '<span class="text-slate-300">–</span>' ?></td>
                            <td class="whitespace-nowrap px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset <?= $planStatusBadges[$plan['status']] ?>"><?= stock_e(stock_plan_status_label($plan['status'])) ?></span></td>
                            <?php if ($canManage): ?>
                            <td class="whitespace-nowrap px-2 py-2.5 text-right">
                                <button type="button" data-stock-edit-product
                                    data-id="<?= (int)$product['id'] ?>" data-name="<?= stock_e($product['name']) ?>" data-qty="<?= stock_e((string)$qty) ?>" data-category="<?= stock_e($category) ?>"
                                    data-class="<?= stock_e($product['abc_class']) ?>" data-lead="<?= (int)$product['lead_time_days'] ?>" data-min="<?= stock_e((string)(float)$product['min_quantity']) ?>" data-unit="<?= stock_e($unit) ?>"
                                    class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700" title="แก้ไข / ตั้งค่าพัสดุ" aria-label="แก้ไข <?= stock_e($product['name']) ?>">
                                    <i class="fas fa-sliders"></i>
                                </button>
                                <?php if ($canAdjust): ?>
                                <button type="button" data-stock-adjust-product
                                    data-id="<?= (int)$product['id'] ?>" data-name="<?= stock_e($product['name']) ?>"
                                    data-qty="<?= stock_e((string)$qty) ?>" data-unit="<?= stock_e($unit) ?>"
                                    class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-amber-50 hover:text-amber-700" title="ปรับยอด Stock" aria-label="ปรับยอด <?= stock_e($product['name']) ?>">
                                    <i class="fas fa-pen-to-square"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                        <tr data-stock-overview-empty class="hidden"><td colspan="<?= $canManage ? 9 : 8 ?>" class="px-4 py-14 text-center text-sm text-slate-500"><i class="fas fa-search mb-3 block text-2xl text-slate-300"></i>ไม่พบพัสดุที่ตรงกับตัวกรอง</td></tr>
                        <?php if (!$products): ?><tr><td colspan="<?= $canManage ? 9 : 8 ?>" class="px-4 py-14 text-center text-sm text-slate-500"><i class="fas fa-box-open mb-3 block text-2xl text-slate-300"></i>ยังไม่มีพัสดุในบริษัทนี้ — กด “เพิ่มพัสดุ” หรือรับเข้าจาก PO</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <details class="group border-t border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-600">
                <summary class="flex cursor-pointer list-none items-center gap-2 font-semibold text-slate-600 hover:text-slate-900"><i class="fas fa-circle-info text-slate-400"></i>หลักการคำนวณ Min / Max<i class="fas fa-chevron-down text-[10px] text-slate-400 transition-transform group-open:rotate-180"></i></summary>
                <ul class="mt-2 grid gap-1 pl-6 md:grid-cols-2">
                    <li><b>ใช้เฉลี่ย/เดือน</b> = ยอดเบิกจ่าย <?= STOCK_PLAN_HISTORY_MONTHS ?> เดือนย้อนหลัง ÷ จำนวนเดือน (พัสดุใหม่หารตามอายุจริง)</li>
                    <li><b>Cover Day</b> = วันตาม Class (<?php foreach (STOCK_PLAN_CLASS_DAYS as $planClass => $planDays): ?><?= $planClass ?> <?= $planDays ?><?= $planClass !== 'C' ? ', ' : '' ?><?php endforeach; ?> วัน) + Lead Time</li>
                    <li><b>Min</b> = ใช้เฉลี่ย/เดือน ÷ 30 × Cover Day + ค้างเบิก (ถ้าตั้ง Min ขั้นต่ำไว้สูงกว่าจะใช้ค่านั้น)</li>
                    <li><b>Max</b> = Min + <?= (int)(STOCK_PLAN_MAX_OVER_RATIO * 100) ?>% · <b>ควรสั่ง</b> = Max − (คงเหลือ + ค้างรับ)</li>
                </ul>
            </details>
        </section>
    </div>
    <?php endif; ?>
</div>

<?php if ($canManage && $products): ?>
<dialog id="stockProductDialog" class="w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/40">
    <form method="post" class="flex flex-col">
        <input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>">
        <input type="hidden" name="action" value="update_product">
        <input type="hidden" name="sup_id" value="<?= $selectedSupId ?>">
        <input type="hidden" name="product_id" data-field="id">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div class="min-w-0"><p class="text-xs font-semibold text-slate-500">แก้ไข / ตั้งค่าพัสดุ</p><h3 class="mt-0.5 truncate text-lg font-black text-slate-900" data-field="title"></h3></div>
            <button type="button" data-dialog-close class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="ปิด"><i class="fas fa-times"></i></button>
        </div>
        <div class="max-h-[70vh] space-y-5 overflow-y-auto px-5 py-5">
            <label class="block text-sm font-semibold text-slate-700">ชื่อพัสดุ
                <input type="text" name="name" data-field="name" required maxlength="255" class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            </label>
            <label class="block text-sm font-semibold text-slate-700">หน่วยนับ
                <input type="text" name="unit" data-field="unit-input" required maxlength="50" class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            </label>
            <p class="-mt-3 text-xs text-amber-700">เปลี่ยนหน่วยนับไม่ได้แปลงยอดคงเหลือให้ ถ้าหน่วยใหม่ต่างจากเดิมให้ปรับยอดด้วย</p>
            <label class="block text-sm font-semibold text-slate-700">หมวดหมู่
                <select name="category" required data-stock-category-select data-field="category" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    <option value="" disabled>เลือกหมวดหมู่</option>
                    <?php foreach (array_keys($knownCategories) as $category): ?><option value="<?= stock_e($category) ?>"><?= stock_e($category) ?></option><?php endforeach; ?>
                </select>
            </label>
            <fieldset>
                <legend class="text-sm font-semibold text-slate-700">Class การใช้งาน</legend>
                <div class="mt-1.5 grid grid-cols-3 gap-2">
                    <?php foreach (STOCK_PLAN_CLASS_DAYS as $planClass => $planDays): ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="abc_class" value="<?= $planClass ?>" class="peer sr-only" required>
                        <span class="block rounded-xl border border-slate-200 px-3 py-2.5 text-center transition-colors peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-800 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 hover:bg-slate-50">
                            <span class="block text-base font-black"><?= $planClass ?></span>
                            <span class="block text-[11px] text-slate-500">สำรอง <?= $planDays ?> วัน</span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p class="mt-1.5 text-xs text-slate-500">A ใช้สม่ำเสมอปริมาณมาก · B ใช้ต่อเนื่องมีขึ้น-ลง · C ใช้ไม่สม่ำเสมอ</p>
            </fieldset>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm font-semibold text-slate-700">Lead Time
                    <span class="mt-1.5 flex items-center rounded-xl border border-slate-200 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500">
                        <input type="number" name="lead_time_days" data-field="lead" min="0" max="365" step="1" required class="w-full min-w-0 rounded-xl px-4 py-2.5 text-right text-sm font-normal outline-none">
                        <span class="pr-4 text-sm font-normal text-slate-500">วัน</span>
                    </span>
                </label>
                <label class="block text-sm font-semibold text-slate-700">Min ขั้นต่ำ
                    <span class="mt-1.5 flex items-center rounded-xl border border-slate-200 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500">
                        <input type="number" name="min_quantity" data-field="min" min="0" step="0.01" required class="w-full min-w-0 rounded-xl px-4 py-2.5 text-right text-sm font-normal outline-none">
                        <span class="pr-4 text-sm font-normal text-slate-500" data-field="unit"></span>
                    </span>
                </label>
            </div>
            <p class="-mt-2 text-xs text-slate-500">Min ขั้นต่ำใช้แทนค่าที่คำนวณได้ เมื่อสูงกว่า · ใส่ 0 เพื่อใช้ค่าคำนวณ</p>
        </div>
        <div class="flex items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button type="submit" form="stockDeleteForm" data-field="delete" class="rounded-xl px-3 py-2.5 text-sm font-bold text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent"><i class="fas fa-trash-can mr-1.5"></i>ลบพัสดุ</button>
            <div class="flex gap-2">
                <button type="button" data-dialog-close class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">ยกเลิก</button>
                <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">บันทึก</button>
            </div>
        </div>
    </form>
</dialog>
<form method="post" id="stockDeleteForm" class="hidden">
    <input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>">
    <input type="hidden" name="action" value="delete_product">
    <input type="hidden" name="sup_id" value="<?= $selectedSupId ?>">
    <input type="hidden" name="product_id" data-delete-product-id>
    <input type="hidden" name="expected_quantity" data-delete-expected>
</form>
<script>
(() => {
    const dialog = document.getElementById('stockProductDialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const field = (name) => dialog.querySelector(`[data-field="${name}"]`);
    const deleteForm = document.getElementById('stockDeleteForm');
    const canWriteOff = <?= $canAdjust ? 'true' : 'false' ?>;
    deleteForm.addEventListener('submit', (event) => {
        const qty = Number(deleteForm.querySelector('[data-delete-expected]').value);
        const writeOff = Math.abs(qty) > 0.00001
            ? `\n\nยอดคงเหลือ ${qty.toLocaleString('th-TH', { maximumFractionDigits: 2 })} ${deleteForm.dataset.unit} จะถูกตัดเป็น 0 และบันทึกในประวัติ`
            : '';
        if (!window.confirm(`ลบพัสดุ "${deleteForm.dataset.name}" ออกจาก Stock?${writeOff}\n\n(ประวัติการเคลื่อนไหวยังเก็บไว้)`)) event.preventDefault();
    });
    field('unit-input').addEventListener('input', () => { field('unit').textContent = field('unit-input').value; });
    document.querySelectorAll('[data-stock-edit-product]').forEach((button) => {
        button.addEventListener('click', () => {
            const d = button.dataset;
            field('id').value = d.id;
            field('title').textContent = d.name;
            field('name').value = d.name;
            field('unit-input').value = d.unit;
            deleteForm.querySelector('[data-delete-product-id]').value = d.id;
            deleteForm.dataset.name = d.name;
            deleteForm.dataset.unit = d.unit;
            deleteForm.querySelector('[data-delete-expected]').value = d.qty;
            const blocked = !canWriteOff && Math.abs(Number(d.qty)) > 0.00001;
            field('delete').disabled = blocked;
            field('delete').title = blocked ? 'ลบได้เมื่อยอดคงเหลือเป็น 0 เท่านั้น (admin ลบพร้อมตัดยอดได้)' : '';
            field('lead').value = d.lead;
            field('min').value = d.min;
            field('unit').textContent = d.unit;
            const select = field('category');
            select.value = d.category;
            if (select.value !== d.category) select.value = '';
            const radio = dialog.querySelector(`input[name="abc_class"][value="${d.class}"]`);
            if (radio) radio.checked = true;
            dialog.showModal();
        });
    });
    dialog.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
})();
</script>
<?php endif; ?>

<?php if ($canAdjust && $products): ?>
<dialog id="stockAdjustDialog" class="w-[calc(100%-2rem)] max-w-md rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/40">
    <form method="post" class="flex flex-col">
        <input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>">
        <input type="hidden" name="action" value="adjust_stock">
        <input type="hidden" name="sup_id" value="<?= $selectedSupId ?>">
        <input type="hidden" name="product_id" data-adjust="id">
        <input type="hidden" name="expected_quantity" data-adjust="expected">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div class="min-w-0"><p class="text-xs font-semibold text-amber-600">ปรับยอด Stock ด้วยมือ</p><h3 class="mt-0.5 truncate text-lg font-black text-slate-900" data-adjust="name"></h3></div>
            <button type="button" data-dialog-close class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="ปิด"><i class="fas fa-times"></i></button>
        </div>
        <div class="space-y-4 px-5 py-5">
            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm">
                <span class="text-slate-500">ยอดในระบบตอนนี้</span>
                <span class="font-bold tabular-nums text-slate-900"><span data-adjust="current"></span> <span class="text-xs font-normal text-slate-500" data-adjust="unit"></span></span>
            </div>
            <label class="block text-sm font-semibold text-slate-700">ยอดคงเหลือใหม่ (ยอดนับจริง)
                <span class="mt-1.5 flex items-center rounded-xl border border-slate-200 focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-500">
                    <input type="number" name="new_quantity" data-adjust="new" min="0" step="0.01" required class="w-full min-w-0 rounded-xl px-4 py-2.5 text-right text-sm font-normal outline-none">
                    <span class="pr-4 text-sm font-normal text-slate-500" data-adjust="unit2"></span>
                </span>
            </label>
            <p class="-mt-2 text-sm">ผลต่าง: <span class="font-bold tabular-nums" data-adjust="diff">–</span></p>
            <label class="block text-sm font-semibold text-slate-700">เหตุผล
                <textarea name="reason" rows="2" required maxlength="500" placeholder="เช่น นับจริงไม่ตรง, ของเสียหาย, บันทึกผิด" class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-normal outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500"></textarea>
            </label>
            <p class="text-xs text-slate-500">การปรับยอดมีผลทันทีและถูกบันทึกในประวัติการเคลื่อนไหว Stock พร้อมชื่อผู้ปรับ</p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button type="button" data-dialog-close class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">ยกเลิก</button>
            <button class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-amber-700">ยืนยันปรับยอด</button>
        </div>
    </form>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('stockAdjustDialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const field = (name) => dialog.querySelector(`[data-adjust="${name}"]`);
    const fmt = (n) => n.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    const updateDiff = () => {
        const current = Number(field('expected').value);
        const raw = field('new').value;
        const diffEl = field('diff');
        if (raw === '' || Number.isNaN(Number(raw))) { diffEl.textContent = '–'; diffEl.className = 'font-bold tabular-nums text-slate-400'; return; }
        const diff = Math.round((Number(raw) - current) * 100) / 100;
        diffEl.textContent = (diff > 0 ? '+' : '') + fmt(diff);
        diffEl.className = 'font-bold tabular-nums ' + (diff > 0 ? 'text-emerald-600' : diff < 0 ? 'text-rose-600' : 'text-slate-400');
    };
    document.querySelectorAll('[data-stock-adjust-product]').forEach((button) => {
        button.addEventListener('click', () => {
            const d = button.dataset;
            dialog.querySelector('form').reset();
            field('id').value = d.id;
            field('expected').value = d.qty;
            field('name').textContent = d.name;
            field('current').textContent = fmt(Number(d.qty));
            field('unit').textContent = d.unit;
            field('unit2').textContent = d.unit;
            field('new').value = d.qty;
            updateDiff();
            dialog.showModal();
            field('new').select();
        });
    });
    field('new').addEventListener('input', updateDiff);
    dialog.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
})();
</script>
<?php endif; ?>

<?php if ($canManage && $selectedSupId > 0): ?>
<dialog id="stockAddDialog" class="w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/40">
    <form method="post" class="flex flex-col">
        <input type="hidden" name="csrf" value="<?= stock_e(stock_csrf_token()) ?>">
        <input type="hidden" name="action" value="create_product">
        <input type="hidden" name="sup_id" value="<?= $selectedSupId ?>">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div class="min-w-0"><p class="text-xs font-semibold text-slate-500"><?= stock_e($selectedCompanyName) ?></p><h3 class="mt-0.5 text-lg font-black text-slate-900">เพิ่มพัสดุ</h3></div>
            <button type="button" data-dialog-close class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="ปิด"><i class="fas fa-times"></i></button>
        </div>
        <div class="max-h-[70vh] space-y-4 overflow-y-auto px-5 py-5">
            <label class="block text-sm font-semibold text-slate-700">ชื่อพัสดุ
                <input type="text" name="name" required maxlength="255" class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            </label>
            <label class="block text-sm font-semibold text-slate-700">หน่วยนับ
                <input type="text" name="unit" required maxlength="50" placeholder="เช่น ชิ้น, แพ็ค" class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            </label>
            <label class="block text-sm font-semibold text-slate-700">หมวดหมู่
                <select name="category" required data-add-category class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    <?php foreach (array_keys($knownCategories) as $category): ?><option value="<?= stock_e($category) ?>"><?= stock_e($category) ?></option><?php endforeach; ?>
                    <option value="__new__" <?= !$knownCategories ? 'selected' : '' ?>>+ เพิ่มหมวดใหม่</option>
                </select>
            </label>
            <label data-add-new-category class="<?= $knownCategories ? 'hidden ' : '' ?>block text-sm font-semibold text-slate-700">ชื่อหมวดใหม่
                <input type="text" name="new_category" maxlength="120" placeholder="เช่น ของใช้ในห้องพัก" class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm font-semibold text-slate-700">Min ขั้นต่ำ
                    <input type="number" name="min_quantity" min="0" step="0.01" value="0" class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-right text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                </label>
                <?php if ($canAdjust): ?>
                <label class="block text-sm font-semibold text-slate-700">ยอดเริ่มต้น
                    <input type="number" name="initial_quantity" min="0" step="0.01" placeholder="0" class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-right text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                </label>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-500"><?= $canAdjust ? 'ยอดเริ่มต้นจะบันทึกเป็นการปรับยอดในประวัติ' : 'พัสดุใหม่เริ่มที่ยอด 0 ยอดจะเพิ่มเมื่อรับเข้าจาก PO' ?></p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button type="button" data-dialog-close class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">ยกเลิก</button>
            <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">เพิ่มพัสดุ</button>
        </div>
    </form>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('stockAddDialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const categorySelect = dialog.querySelector('[data-add-category]');
    const newCategory = dialog.querySelector('[data-add-new-category]');
    const syncCategory = () => {
        const isNew = categorySelect.value === '__new__';
        newCategory.classList.toggle('hidden', !isNew);
        newCategory.querySelector('input').required = isNew;
    };
    categorySelect.addEventListener('change', syncCategory);
    document.querySelectorAll('[data-stock-add-product]').forEach((button) => button.addEventListener('click', () => {
        dialog.querySelector('form').reset();
        syncCategory();
        dialog.showModal();
        dialog.querySelector('input[name="name"]').focus();
    }));
    dialog.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
})();
</script>
<?php endif; ?>

<?php if ($products): ?>
<dialog id="stockDetailDialog" class="m-0 ml-auto h-full max-h-none w-full max-w-2xl border-0 border-l border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/40">
    <div class="flex h-full flex-col bg-white">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-500">รายละเอียดพัสดุ</p>
                <h3 class="mt-0.5 text-lg font-black text-slate-900" data-detail="name"></h3>
                <p class="mt-0.5 text-xs text-slate-500" data-detail="meta"></p>
            </div>
            <button type="button" data-dialog-close class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="ปิด"><i class="fas fa-times"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-5" data-detail="body">
            <div data-detail="loading" class="py-16 text-center text-sm text-slate-500"><i class="fas fa-spinner fa-spin mb-3 block text-2xl text-slate-300"></i>กำลังโหลด…</div>
            <div data-detail="error" class="hidden rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>
            <div data-detail="content" class="hidden space-y-6">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4" data-detail="stats"></div>
                <section data-detail="settings-section">
                    <h4 class="mb-2 text-sm font-bold text-slate-700">ข้อมูลพัสดุ</h4>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 rounded-xl border border-slate-200 p-4 text-sm sm:grid-cols-3" data-detail="settings"></dl>
                </section>
                <section data-detail="open-section" class="hidden">
                    <h4 class="mb-2 text-sm font-bold text-slate-700">ใบเบิกที่ยังค้าง</h4>
                    <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200" data-detail="open"></ul>
                </section>
                <section data-detail="requesters-section" class="hidden">
                    <h4 class="mb-2 text-sm font-bold text-slate-700">ผู้เบิกมากที่สุด <span class="font-normal text-slate-400">(6 เดือนล่าสุด)</span></h4>
                    <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200" data-detail="requesters"></ul>
                </section>
                <section>
                    <h4 class="mb-3 text-sm font-bold text-slate-700">Timeline การเคลื่อนไหว</h4>
                    <p data-detail="timeline-empty" class="hidden rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">ยังไม่มีการเคลื่อนไหว</p>
                    <ol class="relative ml-3 border-l border-slate-200" data-detail="timeline"></ol>
                    <button type="button" data-detail="more" class="mt-3 hidden w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">โหลดเพิ่ม</button>
                </section>
            </div>
        </div>
    </div>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('stockDetailDialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const part = (name) => dialog.querySelector(`[data-detail="${name}"]`);
    const num = (v, d = 2) => Number(v || 0).toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: d });
    const signed = (v) => (Number(v) > 0 ? '+' : '') + num(v);
    const date = (v) => v ? new Date(String(v).replace(' ', 'T')).toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'short' }) : '–';
    const el = (tag, className, text) => { const n = document.createElement(tag); if (className) n.className = className; if (text !== undefined && text !== null) n.textContent = text; return n; };
    const link = (href, text) => { const a = el('a', 'font-semibold text-indigo-600 hover:underline', text); a.href = href; return a; };
    const TYPES = {
        in_po: { label: 'รับเข้าจาก PO', icon: 'fa-truck-ramp-box', dot: 'bg-emerald-500', qty: 'text-emerald-600' },
        out_withdrawal: { label: 'จ่ายตามใบเบิก', icon: 'fa-dolly', dot: 'bg-rose-500', qty: 'text-rose-600' },
        return: { label: 'คืนเข้า Stock', icon: 'fa-rotate-left', dot: 'bg-sky-500', qty: 'text-sky-600' },
        adjustment: { label: 'ปรับยอด', icon: 'fa-pen-to-square', dot: 'bg-amber-500', qty: 'text-amber-600' },
    };
    let current = { id: 0, offset: 0, unit: '', loading: false };

    const renderMovement = (m) => {
        const t = TYPES[m.movement_type] || TYPES.adjustment;
        const li = el('li', 'mb-5 ml-6');
        const dot = el('span', `absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white ${t.dot}`);
        dot.appendChild(el('i', `fas ${t.icon} text-[10px] text-white`));
        li.appendChild(dot);
        const head = el('div', 'flex flex-wrap items-baseline justify-between gap-x-3');
        let label = t.label;
        if (m.movement_type === 'adjustment') label = m.reference_type === 'reconciliation_item' ? 'ปรับยอดจากการกระทบยอด' : 'ปรับยอดด้วยมือ';
        head.appendChild(el('span', 'text-sm font-bold text-slate-900', label));
        const qty = el('span', `text-sm font-black tabular-nums ${t.qty}`, `${signed(m.quantity)} ${current.unit}`);
        head.appendChild(qty);
        li.appendChild(head);
        li.appendChild(el('div', 'mt-0.5 text-xs text-slate-500', `${date(m.created_at)} · โดย ${m.actor_name || 'ไม่ระบุ'} · คงเหลือ ${num(m.balance_before)} → ${num(m.balance_after)}`));
        const ref = el('div', 'mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-slate-600');
        if (m.withdrawal_id) {
            const w = el('span'); w.append('ใบเบิก '); w.appendChild(link(`stock_withdrawal_view.php?id=${encodeURIComponent(m.withdrawal_id)}`, m.withdrawal_doc_no)); ref.appendChild(w);
            if (m.requester_name) ref.appendChild(el('span', '', `ผู้ขอ: ${m.requester_name}`));
        }
        if (m.po_id) { const p = el('span'); p.append('PO '); p.appendChild(link(`view_po.php?id=${encodeURIComponent(m.po_id)}`, m.po_doc_no)); ref.appendChild(p); }
        if (m.receipt_doc_no) ref.appendChild(el('span', '', `ใบรับ ${m.receipt_doc_no}`));
        if (m.reconciliation_doc_no) ref.appendChild(el('span', '', `ใบกระทบยอด ${m.reconciliation_doc_no}`));
        if (ref.childNodes.length) li.appendChild(ref);
        if (m.withdrawal_purpose) li.appendChild(el('div', 'mt-1 text-xs text-slate-500', `วัตถุประสงค์: ${m.withdrawal_purpose}`));
        if (m.note && !m.withdrawal_id && !m.po_id) li.appendChild(el('div', 'mt-1 rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs text-slate-600', m.note));
        return li;
    };

    const renderHeader = (data) => {
        const p = data.product, s = data.summary;
        current.unit = p.unit;
        part('name').textContent = p.name;
        part('meta').textContent = p.category || 'ไม่ระบุหมวด';
        const stats = part('stats'); stats.replaceChildren();
        [
            ['คงเหลือ', num(p.quantity), Number(p.quantity) <= 0 ? 'text-rose-600' : 'text-slate-900'],
            ['รับเข้าทั้งหมด', num(s.total_in), 'text-emerald-600'],
            ['เบิกออกทั้งหมด', num(s.total_out), 'text-rose-600'],
            ['คืน / ปรับยอด', `${num(s.total_return)} / ${signed(s.total_adjustment)}`, 'text-slate-700'],
        ].forEach(([label, value, color]) => {
            const box = el('div', 'rounded-xl border border-slate-200 px-3 py-2.5');
            box.appendChild(el('div', 'text-[11px] font-semibold text-slate-500', label));
            const v = el('div', `mt-0.5 text-base font-black tabular-nums ${color}`, value);
            v.appendChild(el('span', 'ml-1 text-[11px] font-normal text-slate-400', p.unit));
            box.appendChild(v);
            stats.appendChild(box);
        });
        const settings = part('settings'); settings.replaceChildren();
        [
            ['หน่วย', p.unit], ['Class', p.abc_class], ['Lead Time', `${p.lead_time_days} วัน`],
            ['Min ขั้นต่ำ', Number(p.min_quantity) > 0 ? num(p.min_quantity) : 'ใช้ค่าคำนวณ'],
            ['สร้างเมื่อ', date(p.created_at)], ['เคลื่อนไหวล่าสุด', date(s.last_movement_at)],
        ].forEach(([k, v]) => { const d = el('div'); d.appendChild(el('dt', 'text-xs text-slate-500', k)); d.appendChild(el('dd', 'font-semibold text-slate-800', v)); settings.appendChild(d); });

        const open = part('open'); open.replaceChildren();
        part('open-section').classList.toggle('hidden', !data.open_withdrawals.length);
        data.open_withdrawals.forEach((w) => {
            const li = el('li', 'flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm');
            const left = el('div'); left.appendChild(link(`stock_withdrawal_view.php?id=${encodeURIComponent(w.id)}`, w.doc_no));
            left.appendChild(el('span', 'ml-2 text-xs text-slate-500', `${w.requester_name || ''} · ${date(w.created_at)}`));
            li.appendChild(left);
            li.appendChild(el('span', 'text-xs text-slate-600', `${w.status_label} · ขอ ${num(w.requested_quantity)} / จ่ายแล้ว ${num(w.issued_quantity)} / ค้าง ${num(w.remaining_quantity)}`));
            open.appendChild(li);
        });
        const req = part('requesters'); req.replaceChildren();
        part('requesters-section').classList.toggle('hidden', !data.top_requesters.length);
        data.top_requesters.forEach((r) => {
            const li = el('li', 'flex items-center justify-between px-4 py-2.5 text-sm');
            li.appendChild(el('span', 'font-semibold text-slate-800', r.requester_name || 'ไม่ระบุ'));
            li.appendChild(el('span', 'tabular-nums text-slate-600', `${num(r.issued_quantity)} ${p.unit} · ${r.withdrawal_count} ใบ`));
            req.appendChild(li);
        });
    };

    const load = async () => {
        if (current.loading) return;
        const req = current;
        req.loading = true;
        const more = part('more');
        more.disabled = true;
        try {
            const res = await fetch(`stock_product_detail.php?product_id=${encodeURIComponent(current.id)}&offset=${current.offset}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (req !== current) return;
            if (!res.ok) throw new Error(data.error || 'โหลดข้อมูลไม่สำเร็จ');
            if (current.offset === 0) renderHeader(data);
            const timeline = part('timeline');
            data.movements.forEach((m) => timeline.appendChild(renderMovement(m)));
            part('timeline-empty').classList.toggle('hidden', timeline.childElementCount > 0);
            current.offset = data.next_offset;
            more.classList.toggle('hidden', !data.has_more);
            part('loading').classList.add('hidden');
            part('content').classList.remove('hidden');
        } catch (error) {
            if (req !== current) return;
            part('loading').classList.add('hidden');
            part('error').textContent = error.message || 'โหลดข้อมูลไม่สำเร็จ';
            part('error').classList.remove('hidden');
        } finally {
            req.loading = false;
            more.disabled = false;
        }
    };

    document.querySelectorAll('[data-stock-detail-product]').forEach((button) => {
        button.addEventListener('click', () => {
            current = { id: button.dataset.stockDetailProduct, offset: 0, unit: '', loading: false };
            part('name').textContent = button.textContent.trim();
            part('meta').textContent = '';
            part('timeline').replaceChildren();
            part('more').classList.add('hidden');
            part('content').classList.add('hidden');
            part('error').classList.add('hidden');
            part('loading').classList.remove('hidden');
            dialog.showModal();
            load();
        });
    });
    part('more').addEventListener('click', load);
    dialog.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
})();
</script>
<?php endif; ?>

<script src="assets/js/stock-overview-filter.js?v=<?= (int)filemtime(__DIR__ . '/assets/js/stock-overview-filter.js') ?>"></script>
<script>StockOverviewFilter.mountStockOverviewFilter(document.querySelector('[data-stock-overview-filter]'));</script>
<?php if ($products): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
(() => {
    const button = document.getElementById('stockExportExcel');
    if (!button) return;
    const rows = <?= json_encode($exportRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const companyName = <?= json_encode($selectedCompanyName, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const headers = ['พัสดุ', 'หมวดหมู่', 'หน่วย', 'คงเหลือ', 'ค้างรับ (PO)', 'ค้างเบิก', 'ใช้เฉลี่ย/เดือน', 'Class', 'Lead Time (วัน)', 'Cover Day (วัน)', 'มีพอใช้ (วัน)', 'Min', 'Max', 'ควรสั่ง', 'สถานะ'];
    const numberFormats = { 3: '#,##0.00', 4: '#,##0.00', 5: '#,##0.00', 6: '#,##0.00', 10: '#,##0.0', 11: '#,##0', 12: '#,##0', 13: '#,##0' };

    button.addEventListener('click', () => {
        if (typeof XLSX === 'undefined') {
            Swal.fire({ icon: 'error', title: 'ไม่สามารถโหลดไลบรารี Excel ได้', text: 'กรุณาตรวจสอบอินเทอร์เน็ตแล้วลองใหม่' });
            return;
        }
        const total = Object.keys(rows).length;
        const visible = Array.from(document.querySelectorAll('[data-stock-overview-row]'))
            .filter((row) => !row.classList.contains('hidden'))
            .map((row) => rows[row.dataset.productId])
            .filter(Boolean);
        const now = new Date();
        const stamp = now.toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'short' });
        const aoa = [
            ['ภาพรวม Stock' + (companyName ? ' — ' + companyName : '')],
            ['ข้อมูล ณ ' + stamp + (visible.length !== total ? ` · ตามตัวกรอง ${visible.length} จาก ${total} รายการ` : ` · ทั้งหมด ${total} รายการ`)],
            [],
            headers,
            ...visible.map((row) => row.map((value) => value === null ? '' : value)),
        ];
        const ws = XLSX.utils.aoa_to_sheet(aoa);
        ws['!cols'] = headers.map((_, index) => ({ wch: index === 0 ? 36 : index === 1 ? 20 : index === 14 ? 18 : 13 }));
        ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: 3, c: 0 }, e: { r: Math.max(3, aoa.length - 1), c: headers.length - 1 } }) };
        for (let r = 4; r < aoa.length; r++) {
            Object.entries(numberFormats).forEach(([c, format]) => {
                const cell = ws[XLSX.utils.encode_cell({ r, c: Number(c) })];
                if (cell && typeof cell.v === 'number') cell.z = format;
            });
        }
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Stock');
        const pad = (n) => String(n).padStart(2, '0');
        const safeCompany = companyName.replace(/[\\/:*?"<>|]/g, ' ').trim();
        XLSX.writeFile(wb, `Stock${safeCompany ? '_' + safeCompany : ''}_${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}.xlsx`);
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
