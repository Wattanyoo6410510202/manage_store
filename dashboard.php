<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supplier_display.php';
require_once __DIR__ . '/dashboard_lib.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (!dash_can_view((string)($_SESSION['role'] ?? ''))) {
    header('Location: e_service.php');
    exit;
}

$range = dash_resolve_period(
    (string)($_GET['period'] ?? 'monthly'),
    (string)($_GET['date'] ?? ''),
    (int)($_GET['month'] ?? 0),
    (int)($_GET['year'] ?? 0)
);
$supId = max(0, (int)($_GET['sup_id'] ?? 0));
$activeTab = ($_GET['tab'] ?? '') === 'reports' ? 'reports' : 'overview';

// ดึงตามช่วงกราฟแนวโน้ม (รายวันย้อน 14 วัน) แล้วตัดเฉพาะช่วงที่เลือกไว้สรุป เพื่อให้คลิกแท่งกราฟวันอื่นได้
$prTrendRows = dash_fetch_pr_rows($conn, $range['trend_start'], $range['end'], $supId);
$poTrendRows = dash_fetch_po_rows($conn, $range['trend_start'], $range['end'], $supId);
$inPeriod = fn ($row) => $row['created_at'] >= $range['start'];
$prRows = array_values(array_filter($prTrendRows, $inPeriod));
$poRows = array_values(array_filter($poTrendRows, $inPeriod));
$summary = dash_summarize($prRows, $poRows);
$prevSummary = dash_summarize(
    dash_fetch_pr_rows($conn, $range['prev_start'], $range['prev_end'], $supId),
    dash_fetch_po_rows($conn, $range['prev_start'], $range['prev_end'], $supId)
);
$itemRows = dash_fetch_item_rows($conn, $range['start'], $range['end'], $supId);
$trend = dash_fetch_trend($conn, $range, $supId);
$backlog = dash_fetch_backlog($conn, $supId);
$budgets = dash_fetch_budgets($conn, $range['year'], $supId);

$activePr = array_values(array_filter($prRows, fn ($r) => $r['status'] !== 'cancelled'));
$approvedPr = array_values(array_filter($prRows, fn ($r) => $r['status'] === 'approved'));
$activePo = array_values(array_filter($poRows, fn ($r) => $r['status'] !== 'cancelled'));

$companies = $conn->query('SELECT id, company_name FROM suppliers ORDER BY company_name')->fetch_all(MYSQLI_ASSOC);
$companyLabel = 'ทุกบริษัท/หน่วยงาน';
foreach ($companies as $company) {
    if ((int)$company['id'] === $supId) {
        $companyLabel = supplier_display_name($company['company_name']);
    }
}

$payload = [
    'range' => $range + ['company' => $companyLabel],
    'summary' => $summary,
    'prev' => $prevSummary,
    'prRows' => $prTrendRows,
    'poRows' => $poTrendRows,
    'items' => $itemRows,
    'trend' => $trend,
    'backlog' => $backlog,
    'budgets' => $budgets,
    'groups' => [
        'status' => dash_group($activePr, 'status_label'),
        'category' => dash_group($approvedPr, 'category'),
        'company' => dash_group($approvedPr, 'company'),
        'vendor' => dash_group($activePo, 'vendor'),
        'items' => dash_group($itemRows, 'item_desc', 'amount'),
    ],
    'generatedAt' => date('d/m/Y H:i'),
];

function dash_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dash_delta_badge(float $current, float $previous, string $prevLabel): string
{
    $change = dash_percent_change($current, $previous);
    if ($change === null) {
        return '<span class="text-xs text-slate-400">' . ($current > 0 ? 'ไม่มีข้อมูล' . dash_h($prevLabel) . 'ให้เทียบ' : '&nbsp;') . '</span>';
    }
    $up = $change >= 0;
    $class = $up ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700';
    return '<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold ' . $class . '">'
        . '<i class="fas fa-arrow-' . ($up ? 'up' : 'down') . ' text-[10px]"></i>' . number_format(abs($change), 1) . '%</span>'
        . '<span class="text-xs text-slate-400"> เทียบ' . dash_h($prevLabel) . '</span>';
}

$approvalRate = $summary['pr_count'] > 0 ? round($summary['pr_approved_count'] / $summary['pr_count'] * 100) : null;
$lateReceive = count(array_filter($backlog['waiting_receive'], fn ($r) => (int)$r['is_late'] === 1));
$pendingTotal = count($backlog['pending_pr']) + count($backlog['pending_po']);
$currentYear = (int)date('Y');

$kpis = [
    ['key' => 'pr', 'icon' => 'fa-file-signature', 'tone' => 'indigo', 'title' => 'ยอดขอซื้อ (PR)',
     'value' => '฿' . number_format($summary['pr_value'], 2), 'sub' => number_format($summary['pr_count']) . ' ใบขอซื้อ',
     'foot' => dash_delta_badge($summary['pr_value'], $prevSummary['pr_value'], $range['prev_label'])],
    ['key' => 'po', 'icon' => 'fa-cart-shopping', 'tone' => 'sky', 'title' => 'ยอดสั่งซื้อ (PO)',
     'value' => '฿' . number_format($summary['po_value'], 2), 'sub' => number_format($summary['po_count']) . ' ใบสั่งซื้อ',
     'foot' => dash_delta_badge($summary['po_value'], $prevSummary['po_value'], $range['prev_label'])],
    ['key' => 'approved', 'icon' => 'fa-circle-check', 'tone' => 'emerald', 'title' => 'ขอซื้อที่อนุมัติแล้ว',
     'value' => '฿' . number_format($summary['pr_approved_value'], 2), 'sub' => number_format($summary['pr_approved_count']) . ' ใบ',
     'foot' => $approvalRate === null ? '<span class="text-xs text-slate-400">&nbsp;</span>' : '<span class="text-xs text-slate-500">อนุมัติ ' . $approvalRate . '% ของคำขอทั้งหมด</span>'],
    ['key' => 'approval_time', 'icon' => 'fa-stopwatch', 'tone' => 'violet', 'title' => 'เวลาอนุมัติเฉลี่ย',
     'value' => $summary['approval_days_avg'] === null ? '-' : number_format($summary['approval_days_avg'], 1) . ' วัน',
     'sub' => 'ตั้งแต่ส่งคำขอจนอนุมัติครบ',
     'foot' => $prevSummary['approval_days_avg'] === null ? '<span class="text-xs text-slate-400">&nbsp;</span>' : '<span class="text-xs text-slate-500">' . dash_h($range['prev_label']) . ' ' . number_format($prevSummary['approval_days_avg'], 1) . ' วัน</span>'],
    ['key' => 'pending', 'icon' => 'fa-hourglass-half', 'tone' => 'amber', 'title' => 'รออนุมัติ (ตอนนี้)',
     'value' => number_format($pendingTotal) . ' ใบ', 'sub' => 'PR ' . count($backlog['pending_pr']) . ' · PO ' . count($backlog['pending_po']),
     'foot' => '<span class="text-xs text-slate-500">ยอดสะสม ณ ปัจจุบัน ไม่ขึ้นกับช่วงเวลา</span>'],
    ['key' => 'receive', 'icon' => 'fa-truck-ramp-box', 'tone' => 'rose', 'title' => 'รอรับของ (ตอนนี้)',
     'value' => number_format(count($backlog['waiting_receive'])) . ' ใบ', 'sub' => 'อนุมัติแล้วแต่ยังรับของไม่ครบ',
     'foot' => $lateReceive > 0 ? '<span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-700">เลยกำหนดส่ง ' . $lateReceive . ' ใบ</span>' : '<span class="text-xs text-emerald-600">ไม่มีรายการเลยกำหนด</span>'],
];
$toneClasses = [
    'indigo' => 'bg-indigo-50 text-indigo-600',
    'sky' => 'bg-sky-50 text-sky-600',
    'emerald' => 'bg-emerald-50 text-emerald-600',
    'violet' => 'bg-violet-50 text-violet-600',
    'amber' => 'bg-amber-50 text-amber-600',
    'rose' => 'bg-rose-50 text-rose-600',
];

include __DIR__ . '/header.php';
?>

<style>
    .dash-card { transition: box-shadow .2s, transform .2s, border-color .2s; }
    .dash-card[data-detail]:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(15, 23, 42, .08); border-color: #c7d2fe; cursor: pointer; }
    .dash-card[data-detail]:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; }
    .dash-tab[aria-selected="true"] { background: #4f46e5; color: #fff; box-shadow: 0 6px 16px rgba(79, 70, 229, .25); }
    .dash-seg[aria-pressed="true"] { background: #fff; color: #4338ca; box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
    .dash-chart { position: relative; height: 280px; }
    @media print { .dash-noprint { display: none !important; } }
</style>

<div class="mx-auto max-w-[1500px] space-y-6 p-4 md:p-6" id="dashboardApp">
    <!-- หัวเรื่อง + แท็บ -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[2px] text-indigo-500">Procurement Dashboard</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 md:text-3xl">ภาพรวมการจัดซื้อ</h1>
            <p class="mt-1 text-sm text-slate-500">
                <span class="font-bold text-slate-700"><?= dash_h($range['label']) ?></span> · <?= dash_h($companyLabel) ?>
                · <span class="text-slate-400">ข้อมูล ณ <?= dash_h($payload['generatedAt']) ?></span>
            </p>
        </div>
        <div class="dash-noprint inline-flex rounded-2xl bg-slate-100 p-1" role="tablist">
            <button type="button" class="dash-tab rounded-xl px-5 py-2.5 text-sm font-bold text-slate-600 transition-all" role="tab" data-tab="overview" aria-selected="<?= $activeTab === 'overview' ? 'true' : 'false' ?>"><i class="fas fa-chart-pie mr-2"></i>ภาพรวม</button>
            <button type="button" class="dash-tab rounded-xl px-5 py-2.5 text-sm font-bold text-slate-600 transition-all" role="tab" data-tab="reports" aria-selected="<?= $activeTab === 'reports' ? 'true' : 'false' ?>"><i class="fas fa-file-excel mr-2"></i>รายงาน / Excel</button>
        </div>
    </div>

    <!-- ตัวกรอง -->
    <form method="get" id="dashFilter" class="dash-noprint rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <input type="hidden" name="period" value="<?= dash_h($range['period']) ?>">
        <input type="hidden" name="tab" value="<?= dash_h($activeTab) ?>">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end">
            <div>
                <span class="mb-1.5 block text-xs font-bold text-slate-500">ดูข้อมูลแบบ</span>
                <div class="inline-flex rounded-xl bg-slate-100 p-1">
                    <?php foreach (['daily' => 'รายวัน', 'monthly' => 'รายเดือน', 'yearly' => 'รายปี'] as $periodKey => $periodName): ?>
                        <button type="button" class="dash-seg rounded-lg px-4 py-2 text-sm font-bold text-slate-500 transition-all" data-period="<?= $periodKey ?>" aria-pressed="<?= $range['period'] === $periodKey ? 'true' : 'false' ?>"><?= $periodName ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <label class="block" data-show-for="daily">
                <span class="mb-1.5 block text-xs font-bold text-slate-500">วันที่</span>
                <input type="date" name="date" value="<?= dash_h($range['date']) ?>" class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            </label>
            <label class="block" data-show-for="monthly">
                <span class="mb-1.5 block text-xs font-bold text-slate-500">เดือน</span>
                <select name="month" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $range['month'] === $m ? 'selected' : '' ?>><?= DASH_THAI_MONTHS[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label class="block" data-show-for="monthly yearly">
                <span class="mb-1.5 block text-xs font-bold text-slate-500">ปี (พ.ศ.)</span>
                <select name="year" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <?php for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $range['year'] === $y ? 'selected' : '' ?>><?= $y + 543 ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label class="block min-w-0 xl:w-72">
                <span class="mb-1.5 block text-xs font-bold text-slate-500">บริษัท/หน่วยงาน</span>
                <select name="sup_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <option value="0">ทุกบริษัท/หน่วยงาน</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= (int)$company['id'] ?>" <?= $supId === (int)$company['id'] ? 'selected' : '' ?>><?= dash_h(supplier_display_name($company['company_name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition-all hover:bg-indigo-700"><i class="fas fa-magnifying-glass mr-1.5"></i>แสดงผล</button>
                <a href="dashboard.php?period=daily" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">วันนี้</a>
                <a href="dashboard.php?period=monthly" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">เดือนนี้</a>
                <a href="dashboard.php?period=yearly" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">ปีนี้</a>
            </div>
        </div>
    </form>

    <!-- ═══ แท็บภาพรวม ═══ -->
    <section data-panel="overview" class="<?= $activeTab === 'overview' ? '' : 'hidden' ?> space-y-6">
        <p class="dash-noprint flex items-center gap-2 rounded-xl bg-indigo-50 px-4 py-2.5 text-sm text-indigo-800">
            <i class="fas fa-hand-pointer"></i>คลิกที่การ์ดหรือกราฟเพื่อดูรายการเอกสารและคำอธิบายว่าตัวเลขนั้นมาจากไหน
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <?php foreach ($kpis as $kpi): ?>
                <div class="dash-card rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-detail="kpi:<?= $kpi['key'] ?>" tabindex="0" role="button" aria-label="ดูรายละเอียด <?= dash_h($kpi['title']) ?>">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-bold text-slate-600"><?= dash_h($kpi['title']) ?></p>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl <?= $toneClasses[$kpi['tone']] ?>"><i class="fas <?= $kpi['icon'] ?>"></i></span>
                    </div>
                    <p class="mt-2 text-2xl font-black tracking-tight text-slate-900"><?= $kpi['value'] ?></p>
                    <p class="text-sm text-slate-500"><?= dash_h($kpi['sub']) ?></p>
                    <div class="mt-3 flex flex-wrap items-center gap-1 border-t border-slate-100 pt-3"><?= $kpi['foot'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="dash-card rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-black text-slate-900">แนวโน้มยอดขอซื้อและสั่งซื้อ</h2>
                        <p class="text-xs text-slate-500"><?= $range['period'] === 'daily' ? '14 วันล่าสุด' : ($range['period'] === 'monthly' ? 'แยกรายวันใน' . dash_h($range['label']) : 'แยกรายเดือนใน' . dash_h($range['label'])) ?> · คลิกแท่งเพื่อดูเอกสารวันนั้น</p>
                    </div>
                    <button type="button" class="dash-noprint text-slate-400 hover:text-indigo-600" data-explain="trend" aria-label="คำอธิบาย"><i class="fas fa-circle-info"></i></button>
                </div>
                <div class="dash-chart"><canvas id="trendChart"></canvas></div>
            </div>
            <div class="dash-card rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-black text-slate-900">สถานะใบขอซื้อ</h2>
                        <p class="text-xs text-slate-500">PR ที่สร้างใน<?= dash_h($range['label']) ?></p>
                    </div>
                    <button type="button" class="dash-noprint text-slate-400 hover:text-indigo-600" data-explain="status" aria-label="คำอธิบาย"><i class="fas fa-circle-info"></i></button>
                </div>
                <div class="dash-chart"><canvas id="statusChart"></canvas></div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <?php foreach ([
                ['category', 'ค่าใช้จ่ายแยกตามหมวด', 'PR ที่อนุมัติแล้ว แยกตามหมวดค่าใช้จ่าย'],
                ['company', 'ค่าใช้จ่ายแยกตามบริษัท/หน่วยงาน', 'PR ที่อนุมัติแล้ว แยกตามหน่วยงานผู้ขอ'],
                ['vendor', 'ผู้ขายที่สั่งซื้อมากที่สุด', 'มูลค่า PO แยกตามผู้ขาย (Vendor) 10 อันดับ'],
            ] as [$groupKey, $groupTitle, $groupDesc]): ?>
                <div class="dash-card rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-black text-slate-900"><?= $groupTitle ?></h2>
                            <p class="text-xs text-slate-500"><?= $groupDesc ?></p>
                        </div>
                        <button type="button" class="dash-noprint text-slate-400 hover:text-indigo-600" data-explain="<?= $groupKey ?>" aria-label="คำอธิบาย"><i class="fas fa-circle-info"></i></button>
                    </div>
                    <div class="dash-chart"><canvas id="<?= $groupKey ?>Chart"></canvas></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="dash-card rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-black text-slate-900">การใช้งบประมาณ ปี <?= $range['year'] + 543 ?></h2>
                        <p class="text-xs text-slate-500">งบทั้งปี เทียบกับยอด PR ที่อนุมัติแล้วในปีเดียวกัน</p>
                    </div>
                    <button type="button" class="dash-noprint text-slate-400 hover:text-indigo-600" data-explain="budget" aria-label="คำอธิบาย"><i class="fas fa-circle-info"></i></button>
                </div>
                <div class="space-y-4">
                    <?php foreach ($budgets as $budget):
                        $pct = $budget['used_pct'] ?? 0;
                        $bar = $pct > 90 ? 'bg-rose-500' : ($pct > 75 ? 'bg-amber-500' : 'bg-emerald-500');
                    ?>
                        <div class="dash-card -mx-2 rounded-xl p-2" data-detail="budget:<?= dash_h($budget['name']) ?>" tabindex="0" role="button">
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="font-bold text-slate-800"><?= dash_h($budget['name']) ?></span>
                                <span class="text-slate-500">ใช้ <b class="text-slate-800">฿<?= number_format($budget['spent'], 2) ?></b> จาก ฿<?= number_format($budget['total_budget'], 2) ?></span>
                            </div>
                            <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full <?= $bar ?>" style="width: <?= min(100, $pct) ?>%"></div></div>
                            <div class="mt-1 flex justify-between text-xs text-slate-500"><span><?= $budget['used_pct'] === null ? 'ยังไม่ได้ตั้งวงเงิน' : number_format($pct, 1) . '% ของงบ' ?></span><span class="<?= $budget['remaining'] < 0 ? 'font-bold text-rose-600' : '' ?>">คงเหลือ ฿<?= number_format($budget['remaining'], 2) ?></span></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$budgets): ?><p class="py-8 text-center text-sm text-slate-400">ยังไม่มีประเภทงบประมาณที่อนุมัติ</p><?php endif; ?>
                </div>
            </div>
            <div class="dash-card rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-black text-slate-900">งานที่ค้างนานที่สุด</h2>
                        <p class="text-xs text-slate-500">เอกสารรออนุมัติและรอรับของ ณ ตอนนี้ เรียงจากรอนานสุด</p>
                    </div>
                    <button type="button" class="dash-noprint text-slate-400 hover:text-indigo-600" data-explain="backlog" aria-label="คำอธิบาย"><i class="fas fa-circle-info"></i></button>
                </div>
                <div id="backlogList" class="divide-y divide-slate-100"></div>
                <div class="mt-3 flex gap-2 text-sm">
                    <button type="button" class="font-bold text-indigo-600 hover:underline" data-detail="kpi:pending">ดูรออนุมัติทั้งหมด</button>
                    <span class="text-slate-300">|</span>
                    <button type="button" class="font-bold text-indigo-600 hover:underline" data-detail="kpi:receive">ดูรอรับของทั้งหมด</button>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ แท็บรายงาน ═══ -->
    <section data-panel="reports" class="<?= $activeTab === 'reports' ? '' : 'hidden' ?> grid grid-cols-1 gap-6 lg:grid-cols-[300px_1fr]">
        <div class="space-y-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:self-start">
            <p class="px-2 pb-1 pt-2 text-xs font-bold uppercase tracking-wider text-slate-400">เลือกรายงาน</p>
            <div id="reportMenu" class="space-y-1"></div>
            <button type="button" id="exportAll" class="mt-3 w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-emerald-700"><i class="fas fa-file-excel mr-2"></i>ดาวน์โหลดทุกรายงาน (Excel)</button>
            <p class="px-2 text-xs text-slate-400">ไฟล์เดียวหลายชีต ตามช่วงเวลาและบริษัทที่เลือกด้านบน</p>
        </div>
        <div class="min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-5 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 id="reportTitle" class="text-lg font-black text-slate-900"></h2>
                    <p id="reportDesc" class="mt-1 text-sm text-slate-500"></p>
                    <p class="mt-1 text-xs text-slate-400"><?= dash_h($range['label']) ?> · <?= dash_h($companyLabel) ?></p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <input type="search" id="reportSearch" placeholder="ค้นหาในรายงาน..." class="w-48 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <button type="button" id="exportReport" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700"><i class="fas fa-download mr-1.5"></i>Excel</button>
                </div>
            </div>
            <div class="max-h-[640px] overflow-auto">
                <table class="min-w-full text-sm">
                    <thead id="reportHead" class="sticky top-0 bg-slate-50 text-xs font-bold text-slate-500"></thead>
                    <tbody id="reportBody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
            <div id="reportFoot" class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500"></div>
        </div>
    </section>
</div>

<!-- หน้าต่างรายละเอียด -->
<div id="detailModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="detailTitle">
    <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 p-6">
            <div>
                <h3 id="detailTitle" class="text-xl font-black text-slate-900"></h3>
                <p id="detailSubtitle" class="mt-1 text-sm text-slate-500"></p>
            </div>
            <button type="button" data-close-modal class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="ปิด"><i class="fas fa-xmark text-lg"></i></button>
        </div>
        <div class="overflow-y-auto p-6">
            <div class="rounded-2xl bg-indigo-50 p-4 text-sm leading-relaxed text-indigo-900">
                <p class="mb-1 font-bold"><i class="fas fa-lightbulb mr-1.5"></i>ตัวเลขนี้คืออะไร</p>
                <div id="detailExplain"></div>
            </div>
            <div id="detailStats" class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4"></div>
            <div id="detailTableWrap" class="mt-5">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <p id="detailCount" class="text-sm font-bold text-slate-700"></p>
                    <button type="button" id="detailExport" class="rounded-xl border border-emerald-200 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50"><i class="fas fa-file-excel mr-1"></i>Excel</button>
                </div>
                <div class="max-h-[420px] overflow-auto rounded-xl border border-slate-100">
                    <table class="min-w-full text-sm">
                        <thead id="detailHead" class="sticky top-0 bg-slate-50 text-xs font-bold text-slate-500"></thead>
                        <tbody id="detailBody" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>window.DASHBOARD_DATA = <?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/js/dashboard.js"></script>

<?php include __DIR__ . '/footer.php'; ?>
