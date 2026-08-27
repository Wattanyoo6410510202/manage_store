<?php
require_once 'config.php';
include('header.php');

$sup_id = isset($_GET['sup_id']) ? intval($_GET['sup_id']) : 0;
$sup_filter_pr = $sup_id > 0 ? " AND p.supplier_id = $sup_id " : "";

$month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year = isset($_GET['year']) ? intval($_GET['year']) : 0;
$month_filter_pr = ($month > 0 && $year > 0) ? " AND MONTH(p.created_at) = $month AND YEAR(p.created_at) = $year " : "";
$month_filter_po = ($month > 0 && $year > 0) ? " AND MONTH(created_at) = $month AND YEAR(created_at) = $year " : "";
$month_filter_pr_noalias = ($month > 0 && $year > 0) ? " AND MONTH(created_at) = $month AND YEAR(created_at) = $year " : "";

$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$filter_label = ($month > 0 && $year > 0) ? $thai_months[$month] . ' ' . ($year + 543) : 'ทุกช่วงเวลา';

$total_po_sql = "SELECT SUM(grand_total) as total FROM po WHERE status = 'approved' AND deleted_at IS NULL " . ($sup_id > 0 ? " AND supplier_id = $sup_id" : "") . $month_filter_po;
$total_po = mysqli_fetch_assoc(mysqli_query($conn, $total_po_sql))['total'] ?: 0;

$count_po_sql = "SELECT COUNT(*) as total FROM po WHERE deleted_at IS NULL " . ($sup_id > 0 ? " AND supplier_id = $sup_id" : "") . $month_filter_po;
$count_po = mysqli_fetch_assoc(mysqli_query($conn, $count_po_sql))['total'] ?: 0;

$status_pr_sql = "SELECT COUNT(*) as total FROM pr WHERE status = 'pending' AND deleted_at IS NULL " . ($sup_id > 0 ? " AND supplier_id = $sup_id" : "") . $month_filter_pr_noalias;
$status_pr = mysqli_fetch_assoc(mysqli_query($conn, $status_pr_sql))['total'];

$status_po_sql = "SELECT COUNT(*) as total FROM po WHERE status = 'pending' AND deleted_at IS NULL " . ($sup_id > 0 ? " AND supplier_id = $sup_id" : "") . $month_filter_po;
$status_po = mysqli_fetch_assoc(mysqli_query($conn, $status_po_sql))['total'];

$count_po_from_pr_sql = "SELECT COUNT(*) as total FROM po WHERE reference_no LIKE 'PR-%' AND deleted_at IS NULL " . ($sup_id > 0 ? " AND supplier_id = $sup_id" : "") . $month_filter_po;
$count_po_from_pr = mysqli_fetch_assoc(mysqli_query($conn, $count_po_from_pr_sql))['total'];

$expense_query = "SELECT c.name, COALESCE(SUM(p.grand_total), 0) as total 
                  FROM expense_categories c 
                  LEFT JOIN pr p ON p.expense_cat_id = c.id AND p.status = 'approved' AND p.deleted_at IS NULL $sup_filter_pr $month_filter_pr
                  WHERE 1
                  GROUP BY c.id 
                  ORDER BY total DESC";
$expense_res = mysqli_query($conn, $expense_query);
$exp_labels = []; $exp_vals = [];
while($r = mysqli_fetch_assoc($expense_res)) {
    $total = (float)$r['total'];
    if ($total > 0) { $exp_labels[] = $r['name']; $exp_vals[] = $total; }
}

$budget_query = "SELECT b.name, SUM(p.grand_total) as total 
                 FROM budget_types b 
                 LEFT JOIN pr p ON p.budget_type_id = b.id AND p.status = 'approved' AND p.deleted_at IS NULL $sup_filter_pr $month_filter_pr
                 WHERE b.status = 'approved'
                 GROUP BY b.name 
                 ORDER BY total DESC";
$budget_res = mysqli_query($conn, $budget_query);
$budgets = [];
$total_budget_spent = 0;
while($r = mysqli_fetch_assoc($budget_res)) {
    $r['total'] = (float)$r['total'];
    $budgets[] = $r;
    $total_budget_spent += $r['total'];
}

$bud_labels = []; $bud_vals = [];
foreach($budgets as $b) {
    if ($b['total'] > 0) {
        $bud_labels[] = $b['name'];
        $bud_vals[] = $b['total'];
    }
}

$avg_expenditure = count($budgets) > 0 ? $total_budget_spent / count($budgets) : 0;
$max_expenditure = 0;
foreach($budgets as $b) { if($b['total'] > $max_expenditure) $max_expenditure = $b['total']; }

$expense_items_query = "SELECT ec.name as category_name, pi.item_desc, pi.item_unit,
                               SUM(pi.item_qty) as total_qty,
                               SUM(pi.item_qty * COALESCE(NULLIF(pi.item_price, 0),
                                   (SELECT poi.item_price FROM po_items poi
                                    INNER JOIN po ON po.id = poi.po_id
                                    WHERE po.reference_no = p.doc_no AND poi.item_desc = pi.item_desc
                                    LIMIT 1), 0)) as total_amount,
                               COUNT(DISTINCT p.id) as pr_count
                        FROM expense_categories ec
                        INNER JOIN pr p ON p.expense_cat_id = ec.id AND p.status = 'approved' AND p.deleted_at IS NULL
                        INNER JOIN pr_items pi ON pi.pr_id = p.id
                        WHERE 1 $sup_filter_pr $month_filter_pr
                        GROUP BY ec.name, pi.item_desc, pi.item_unit
                        ORDER BY total_amount DESC";
$expense_items_res = mysqli_query($conn, $expense_items_query);
$expense_items = [];
while($r = mysqli_fetch_assoc($expense_items_res)) {
    $r['total_qty'] = (float)$r['total_qty'];
    $r['total_amount'] = (float)$r['total_amount'];
    $r['pr_count'] = (int)$r['pr_count'];
    $expense_items[] = $r;
}

$budget_items_query = "SELECT bt.name as budget_name, pi.item_desc, pi.item_unit,
                               SUM(pi.item_qty) as total_qty,
                               SUM(pi.item_qty * COALESCE(NULLIF(pi.item_price, 0),
                                   (SELECT poi.item_price FROM po_items poi
                                    INNER JOIN po ON po.id = poi.po_id
                                    WHERE po.reference_no = p.doc_no AND poi.item_desc = pi.item_desc
                                    LIMIT 1), 0)) as total_amount,
                               COUNT(DISTINCT p.id) as pr_count
                        FROM budget_types bt
                        INNER JOIN pr p ON p.budget_type_id = bt.id AND p.status = 'approved' AND p.deleted_at IS NULL
                        INNER JOIN pr_items pi ON pi.pr_id = p.id
                        WHERE bt.status = 'approved' $sup_filter_pr $month_filter_pr
                        GROUP BY bt.name, pi.item_desc, pi.item_unit
                        ORDER BY total_amount DESC";
$budget_items_res = mysqli_query($conn, $budget_items_query);
$budget_items = [];
while($r = mysqli_fetch_assoc($budget_items_res)) {
    $r['total_qty'] = (float)$r['total_qty'];
    $r['total_amount'] = (float)$r['total_amount'];
    $r['pr_count'] = (int)$r['pr_count'];
    $budget_items[] = $r;
}

$all_expense_cats = mysqli_query($conn, "SELECT name FROM expense_categories ORDER BY name ASC");
$expense_categories = [];
while($r = mysqli_fetch_assoc($all_expense_cats)) { $expense_categories[] = $r['name']; }
$budget_names = array_unique(array_map(function($i) { return $i['budget_name']; }, $budget_items));

$suppliers = mysqli_query($conn, "SELECT id, company_name FROM suppliers");
$current_year_be = $year > 0 ? $year : (intval(date('Y')) + 543);
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Procurement Dashboard</h2>
            <p class="text-slate-500 text-sm">สรุปภาพรวมการจัดซื้อและงบประมาณ — <span class="font-bold text-indigo-600"><?= $filter_label ?></span></p>
        </div>
        <div class="flex flex-wrap gap-2 items-center">
            <select id="supFilter" class="bg-white border-slate-200 border rounded-xl px-4 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 transition-all">
                <option value="0">ทุกหน่วยงาน/บริษัท</option>
                <?php 
                mysqli_data_seek($suppliers, 0);
                while($s = mysqli_fetch_assoc($suppliers)) echo "<option value='".$s['id']."' ".($sup_id == $s['id'] ? 'selected':'').">".htmlspecialchars(supplier_display_name($s['company_name']), ENT_QUOTES, 'UTF-8')."</option>";
                ?>
            </select>
            <select id="monthFilter" class="bg-white border-slate-200 border rounded-xl px-4 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 transition-all">
                <option value="0">ทุกเดือน</option>
                <?php for($i = 1; $i <= 12; $i++): ?>
                    <option value="<?= $i ?>" <?= $month == $i ? 'selected' : '' ?>><?= $thai_months[$i] ?></option>
                <?php endfor; ?>
            </select>
            <select id="yearFilter" class="bg-white border-slate-200 border rounded-xl px-4 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 transition-all">
                <option value="0">ทุกปี</option>
                <?php 
                $current_year = intval(date('Y'));
                for($y = $current_year; $y >= $current_year - 5; $y--):
                    $be = $y + 543;
                ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>>พ.ศ. <?= $be ?></option>
                <?php endfor; ?>
            </select>
            <button onclick="filterDashboard()" class="bg-indigo-600 text-white px-6 py-2 rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all active:scale-95">
                <i class="fas fa-filter mr-1"></i>กรอง
            </button>
            <div class="h-6 w-px bg-slate-200 mx-1 hidden md:block"></div>
            <button onclick="exportExcel()" class="bg-emerald-500 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-emerald-600 shadow-sm transition-all" title="Export Excel">
                <i class="fas fa-file-excel mr-1"></i>Excel
            </button>
            <button onclick="exportPDF()" class="bg-rose-500 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-rose-600 shadow-sm transition-all" title="Export PDF">
                <i class="fas fa-file-pdf mr-1"></i>PDF
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 p-5 rounded-2xl shadow-sm relative overflow-hidden">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
            <p class="text-indigo-100 text-[10px] font-bold uppercase tracking-wider mb-1">ยอดใช้จ่ายรวม</p>
            <h3 class="text-2xl font-black text-white"><?= number_format($total_budget_spent, 2) ?></h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-1">ค่าเฉลี่ยต่องบ</p>
            <h3 class="text-2xl font-black text-slate-800"><?= number_format($avg_expenditure, 2) ?></h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-1">PO จาก PR</p>
            <h3 class="text-2xl font-black text-cyan-600"><?= number_format($count_po_from_pr) ?> <span class="text-sm font-bold text-slate-400">รายการ</span></h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-1">PR รอเบิก</p>
            <h3 class="text-2xl font-black text-rose-500"><?= number_format($status_pr) ?> <span class="text-sm font-bold text-slate-400">รายการ</span></h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-1">ยอดใช้จ่ายสูงสุด</p>
            <h3 class="text-2xl font-black text-indigo-600"><?= number_format($max_expenditure, 2) ?></h3>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">หมวดหมู่ค่าใช้จ่าย</h3>
                    <p class="text-[10px] text-slate-400">สัดส่วนการใช้จ่ายแยกตามหมวดหมู่</p>
                </div>
                <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
            <div class="h-[280px] flex items-center justify-center">
                <?php if(empty($exp_vals)): ?>
                    <div class="text-slate-300 text-center">
                        <i class="fas fa-chart-pie text-3xl mb-2"></i>
                        <p class="text-sm font-bold">ไม่มีข้อมูล</p>
                    </div>
                <?php else: ?>
                    <canvas id="expenseChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">ประเภทงบประมาณ</h3>
                    <p class="text-[10px] text-slate-400">สัดส่วนตามประเภทงบประมาณ</p>
                </div>
                <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
            <div class="h-[280px] flex items-center justify-center">
                <?php if(empty($bud_vals)): ?>
                    <div class="text-slate-300 text-center">
                        <i class="fas fa-chart-pie text-3xl mb-2"></i>
                        <p class="text-sm font-bold">ไม่มีข้อมูล</p>
                    </div>
                <?php else: ?>
                    <canvas id="budgetChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <div class="flex-1">
                    <h3 class="font-bold text-slate-800 text-sm">รายการแยกตามหมวดค่าใช้จ่าย</h3>
                    <p class="text-[10px] text-slate-400">เลือกหมวดเพื่อดูรายการ</p>
                </div>
                <select id="expCatFilter" onchange="filterExpenseItems()" class="bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-[11px] font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="">ทั้งหมด</option>
                    <?php foreach($expense_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
                    <i class="fas fa-list"></i>
                </div>
            </div>
            <div class="h-[280px] flex items-center justify-center">
                <?php if(empty($expense_items)): ?>
                    <div class="text-slate-300 text-center">
                        <i class="fas fa-chart-bar text-3xl mb-2"></i>
                        <p class="text-sm font-bold">ไม่มีข้อมูล</p>
                    </div>
                <?php else: ?>
                    <canvas id="expenseItemChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <div class="flex-1">
                    <h3 class="font-bold text-slate-800 text-sm">รายการแยกตามประเภทงบประมาณ</h3>
                    <p class="text-[10px] text-slate-400">เลือกงบเพื่อดูรายการ</p>
                </div>
                <select id="budgetNameFilter" onchange="filterBudgetItems()" class="bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-[11px] font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="">ทั้งหมด</option>
                    <?php foreach($budget_names as $bn): ?>
                        <option value="<?= htmlspecialchars($bn) ?>"><?= htmlspecialchars($bn) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
                    <i class="fas fa-list"></i>
                </div>
            </div>
            <div class="h-[280px] flex items-center justify-center">
                <?php if(empty($budget_items)): ?>
                    <div class="text-slate-300 text-center">
                        <i class="fas fa-chart-bar text-3xl mb-2"></i>
                        <p class="text-sm font-bold">ไม่มีข้อมูล</p>
                    </div>
                <?php else: ?>
                    <canvas id="budgetItemChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-50">
                <h3 class="font-bold text-slate-800 text-sm">รายละเอียดรายการ — หมวดค่าใช้จ่าย</h3>
                <p class="text-[10px] text-slate-400">รายการสั่งซื้อ <span id="expFilterLabel" class="font-semibold text-indigo-600">ทั้งหมด</span></p>
            </div>
            <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">หมวด</th>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">รายการ</th>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-right">จำนวน</th>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">หน่วย</th>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-right">ยอดรวม</th>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-center">PR</th>
                        </tr>
                    </thead>
                    <tbody id="expenseItemTableBody" class="divide-y divide-slate-50"></tbody>
                </table>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-50">
                <h3 class="font-bold text-slate-800 text-sm">รายละเอียดรายการ — ประเภทงบประมาณ</h3>
                <p class="text-[10px] text-slate-400">รายการสั่งซื้อ <span id="budgetFilterLabel" class="font-semibold text-indigo-600">ทั้งหมด</span></p>
            </div>
            <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">งบ</th>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">รายการ</th>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-right">จำนวน</th>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">หน่วย</th>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-right">ยอดรวม</th>
                            <th class="px-4 py-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-center">PR</th>
                        </tr>
                    </thead>
                    <tbody id="budgetItemTableBody" class="divide-y divide-slate-50"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-50 flex justify-between items-center">
            <div>
                <h3 class="font-bold text-slate-800 text-sm">รายละเอียดงบประมาณ</h3>
                <p class="text-[10px] text-slate-400">ตารางแสดงรายการงบประมาณและการใช้จ่าย</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50">
                        <th class="px-5 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">งบประมาณ</th>
                        <th class="px-5 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-right">ยอดใช้จ่าย</th>
                        <th class="px-5 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-center">สัดส่วน</th>
                        <th class="px-5 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">สถานะ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach($budgets as $b): 
                        $pct = $total_budget_spent > 0 ? ($b['total'] / $total_budget_spent) * 100 : 0;
                        $is_max = ($b['total'] > 0 && $b['total'] == $max_expenditure);
                    ?>
                    <tr class="<?= $is_max ? 'bg-indigo-50/30' : '' ?> hover:bg-slate-50/50 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full <?= $is_max ? 'bg-indigo-500 animate-pulse' : 'bg-slate-300' ?>"></div>
                                <span class="font-bold text-slate-700 text-sm"><?= htmlspecialchars($b['name']) ?></span>
                                <?php if($is_max): ?>
                                    <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-600 text-[8px] font-bold uppercase">สูงสุด</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="font-mono font-bold text-slate-900"><?= number_format($b['total'], 2) ?></span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-20 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                    <div class="h-full <?= $is_max ? 'bg-indigo-500' : 'bg-slate-300' ?>" style="width: <?= $pct ?>%"></div>
                                </div>
                                <span class="text-[10px] font-bold text-slate-500"><?= number_format($pct, 1) ?>%</span>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <?php if($b['total'] > $avg_expenditure): ?>
                                <span class="flex items-center gap-1 text-orange-500 text-[10px] font-bold">
                                    <i class="fas fa-fire"></i> สูงกว่าค่าเฉลี่ย
                                </span>
                            <?php elseif($b['total'] > 0): ?>
                                <span class="flex items-center gap-1 text-emerald-500 text-[10px] font-bold">
                                    <i class="fas fa-check-circle"></i> ปกติ
                                </span>
                            <?php else: ?>
                                <span class="text-slate-300 text-[10px]">ยังไม่มีการใช้</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#3b82f6'];

    <?php if(!empty($exp_vals)): ?>
    new Chart(document.getElementById('expenseChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($exp_labels) ?>,
            datasets: [{
                data: <?= json_encode($exp_vals) ?>,
                backgroundColor: colors,
                borderWidth: 0,
                hoverOffset: 20
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' }, padding: 15 } }
            },
            cutout: '70%'
        }
    });
    <?php endif; ?>

    <?php if(!empty($bud_vals)): ?>
    new Chart(document.getElementById('budgetChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($bud_labels) ?>,
            datasets: [{
                data: <?= json_encode($bud_vals) ?>,
                backgroundColor: colors,
                borderWidth: 0
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' }, padding: 15 } }
            }
        }
    });
    <?php endif; ?>

    const EXPENSE_ITEMS = <?= json_encode($expense_items, JSON_UNESCAPED_UNICODE) ?>;
    const BUDGET_ITEMS = <?= json_encode($budget_items, JSON_UNESCAPED_UNICODE) ?>;

    let expItemChartInstance = null;
    let budItemChartInstance = null;

    function renderExpenseItemChart(data) {
        const ctx = document.getElementById('expenseItemChart');
        if (!ctx) return;
        if (expItemChartInstance) expItemChartInstance.destroy();

        const top = data.slice(0, 10);
        const labels = top.map(i => i.item_desc.length > 40 ? i.item_desc.substring(0, 40) + '...' : i.item_desc);
        const vals = top.map(i => i.total_amount);
        const cats = top.map(i => i.category_name);

        if (top.length === 0) {
            expItemChartInstance = null;
            return;
        }

        expItemChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ยอดรวม',
                    data: vals,
                    backgroundColor: colors.slice(0, vals.length),
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(ctx) {
                                return 'หมวด: ' + cats[ctx.dataIndex];
                            }
                        }
                    }
                },
                scales: {
                    x: { ticks: { font: { size: 9 } }, grid: { display: false } },
                    y: { ticks: { font: { size: 8 } }, grid: { display: false } }
                }
            }
        });
    }

    function renderBudgetItemChart(data) {
        const ctx = document.getElementById('budgetItemChart');
        if (!ctx) return;
        if (budItemChartInstance) budItemChartInstance.destroy();

        const top = data.slice(0, 10);
        const labels = top.map(i => i.item_desc.length > 40 ? i.item_desc.substring(0, 40) + '...' : i.item_desc);
        const vals = top.map(i => i.total_amount);
        const cats = top.map(i => i.budget_name);

        if (top.length === 0) {
            budItemChartInstance = null;
            return;
        }

        budItemChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ยอดรวม',
                    data: vals,
                    backgroundColor: colors.slice(0, vals.length),
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(ctx) {
                                return 'งบ: ' + cats[ctx.dataIndex];
                            }
                        }
                    }
                },
                scales: {
                    x: { ticks: { font: { size: 9 } }, grid: { display: false } },
                    y: { ticks: { font: { size: 8 } }, grid: { display: false } }
                }
            }
        });
    }

    function renderExpenseTable(data) {
        const tbody = document.getElementById('expenseItemTableBody');
        if (!data.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-300">ไม่มีข้อมูล</td></tr>';
            return;
        }
        tbody.innerHTML = data.map(i => `
            <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-4 py-2 text-[11px] font-semibold text-slate-600">${escHtml(i.category_name)}</td>
                <td class="px-4 py-2 text-[12px] text-slate-700 max-w-[200px] truncate" title="${escHtml(i.item_desc)}">${escHtml(i.item_desc)}</td>
                <td class="px-4 py-2 text-[12px] text-slate-700 text-right font-mono">${Number(i.total_qty).toLocaleString()}</td>
                <td class="px-4 py-2 text-[11px] text-slate-500">${i.item_unit ? escHtml(i.item_unit) : '-'}</td>
                <td class="px-4 py-2 text-[12px] text-slate-800 text-right font-mono font-bold">${Number(i.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="px-4 py-2 text-[11px] text-slate-500 text-center">${i.pr_count}</td>
            </tr>
        `).join('');
    }

    function renderBudgetTable(data) {
        const tbody = document.getElementById('budgetItemTableBody');
        if (!data.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-300">ไม่มีข้อมูล</td></tr>';
            return;
        }
        tbody.innerHTML = data.map(i => `
            <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-4 py-2 text-[11px] font-semibold text-slate-600">${escHtml(i.budget_name)}</td>
                <td class="px-4 py-2 text-[12px] text-slate-700 max-w-[200px] truncate" title="${escHtml(i.item_desc)}">${escHtml(i.item_desc)}</td>
                <td class="px-4 py-2 text-[12px] text-slate-700 text-right font-mono">${Number(i.total_qty).toLocaleString()}</td>
                <td class="px-4 py-2 text-[11px] text-slate-500">${i.item_unit ? escHtml(i.item_unit) : '-'}</td>
                <td class="px-4 py-2 text-[12px] text-slate-800 text-right font-mono font-bold">${Number(i.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="px-4 py-2 text-[11px] text-slate-500 text-center">${i.pr_count}</td>
            </tr>
        `).join('');
    }

    function filterExpenseItems(category) {
        const sel = document.getElementById('expCatFilter');
        if (category !== undefined) sel.value = category;
        const val = sel.value;
        document.getElementById('expFilterLabel').textContent = val || 'ทั้งหมด';
        const filtered = val ? EXPENSE_ITEMS.filter(i => i.category_name === val) : EXPENSE_ITEMS;
        renderExpenseItemChart(filtered);
        renderExpenseTable(filtered);
    }

    function filterBudgetItems(name) {
        const sel = document.getElementById('budgetNameFilter');
        if (name !== undefined) sel.value = name;
        const val = sel.value;
        document.getElementById('budgetFilterLabel').textContent = val || 'ทั้งหมด';
        const filtered = val ? BUDGET_ITEMS.filter(i => i.budget_name === val) : BUDGET_ITEMS;
        renderBudgetItemChart(filtered);
        renderBudgetTable(filtered);
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    $(document).ready(function () {
        renderExpenseItemChart(EXPENSE_ITEMS);
        renderBudgetItemChart(BUDGET_ITEMS);
        renderExpenseTable(EXPENSE_ITEMS);
        renderBudgetTable(BUDGET_ITEMS);

        <?php if(!empty($exp_vals)): ?>
        const expChart = Chart.getChart('expenseChart');
        if (expChart) {
            expChart.options.onClick = function(e, el) {
                if (el.length > 0) {
                    const idx = el[0].index;
                    const cat = expChart.data.labels[idx];
                    filterExpenseItems(cat);
                }
            };
        }
        <?php endif; ?>

        <?php if(!empty($bud_vals)): ?>
        const budChart = Chart.getChart('budgetChart');
        if (budChart) {
            budChart.options.onClick = function(e, el) {
                if (el.length > 0) {
                    const idx = el[0].index;
                    const name = budChart.data.labels[idx];
                    filterBudgetItems(name);
                }
            };
        }
        <?php endif; ?>
    });

    function filterDashboard() {
        const sup = $('#supFilter').val();
        const month = $('#monthFilter').val();
        const year = $('#yearFilter').val();
        window.location.href = `procurement.php?sup_id=${sup}&month=${month}&year=${year}`;
    }

    function exportExcel() {
        const wb = XLSX.utils.book_new();

        const summaryData = [
            ['Procurement Dashboard Report'],
            ['ช่วงเวลา', '<?= addslashes($filter_label) ?>'],
            [],
            ['รายการ', 'มูลค่า'],
            ['ยอดใช้จ่ายรวม', <?= $total_budget_spent ?>],
            ['ค่าเฉลี่ยต่องบประมาณ', <?= $avg_expenditure ?>],
            ['PO จาก PR (รายการ)', <?= $count_po_from_pr ?>],
            ['PR รอเบิก (รายการ)', <?= $status_pr ?>],
            ['ยอดใช้จ่ายสูงสุด', <?= $max_expenditure ?>],
        ];
        const ws1 = XLSX.utils.aoa_to_sheet(summaryData);
        ws1['!cols'] = [{ wch: 30 }, { wch: 20 }];
        XLSX.utils.book_append_sheet(wb, ws1, 'สรุป');

        const expItemData = [['หมวดค่าใช้จ่าย', 'รายการ', 'จำนวน', 'หน่วย', 'ยอดรวม', 'จำนวน PR']];
        <?php foreach($expense_items as $item): ?>
        expItemData.push(['<?= addslashes($item['category_name']) ?>', '<?= addslashes($item['item_desc']) ?>', <?= $item['total_qty'] ?>, '<?= addslashes($item['item_unit']) ?>', <?= $item['total_amount'] ?>, <?= $item['pr_count'] ?>]);
        <?php endforeach; ?>
        const ws3 = XLSX.utils.aoa_to_sheet(expItemData);
        ws3['!cols'] = [{ wch: 20 }, { wch: 40 }, { wch: 10 }, { wch: 8 }, { wch: 15 }, { wch: 10 }];
        XLSX.utils.book_append_sheet(wb, ws3, 'รายการแยกตามหมวด');

        const budgetItemData = [['ประเภทงบประมาณ', 'รายการ', 'จำนวน', 'หน่วย', 'ยอดรวม', 'จำนวน PR']];
        <?php foreach($budget_items as $item): ?>
        budgetItemData.push(['<?= addslashes($item['budget_name']) ?>', '<?= addslashes($item['item_desc']) ?>', <?= $item['total_qty'] ?>, '<?= addslashes($item['item_unit']) ?>', <?= $item['total_amount'] ?>, <?= $item['pr_count'] ?>]);
        <?php endforeach; ?>
        const ws4 = XLSX.utils.aoa_to_sheet(budgetItemData);
        ws4['!cols'] = [{ wch: 20 }, { wch: 40 }, { wch: 10 }, { wch: 8 }, { wch: 15 }, { wch: 10 }];
        XLSX.utils.book_append_sheet(wb, ws4, 'รายการแยกตามงบ');

        const budgetData = [['งบประมาณ', 'ยอดใช้จ่าย', 'สัดส่วน (%)', 'สถานะ']];
        <?php foreach($budgets as $b): 
            $pct = $total_budget_spent > 0 ? ($b['total'] / $total_budget_spent) * 100 : 0;
            $status = $b['total'] > $avg_expenditure ? 'สูงกว่าค่าเฉลี่ย' : ($b['total'] > 0 ? 'ปกติ' : 'ยังไม่มีการใช้');
        ?>
            budgetData.push(['<?= addslashes($b['name']) ?>', <?= $b['total'] ?>, <?= round($pct, 1) ?>, '<?= $status ?>']);
        <?php endforeach; ?>
        const ws2 = XLSX.utils.aoa_to_sheet(budgetData);
        ws2['!cols'] = [{ wch: 30 }, { wch: 20 }, { wch: 12 }, { wch: 20 }];
        XLSX.utils.book_append_sheet(wb, ws2, 'งบประมาณ');

        const filename = 'procurement_report_<?= $year > 0 ? $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) : date('Y-m') ?>.xlsx';
        XLSX.writeFile(wb, filename);
    }

    function exportPDF() {
        const reportHTML = `
            <div style="font-family: 'Sarabun', sans-serif; padding: 30px; color: #1e293b;">
                <div style="text-align: center; margin-bottom: 30px; border-bottom: 3px solid #6366f1; padding-bottom: 20px;">
                    <h1 style="font-size: 22px; font-weight: 900; color: #1e293b; margin: 0;">Procurement Dashboard Report</h1>
                    <p style="font-size: 14px; color: #64748b; margin: 5px 0 0;">รายงานสรุปภาพรวมการจัดซื้อและงบประมาณ</p>
                    <p style="font-size: 13px; color: #6366f1; font-weight: bold; margin: 5px 0 0;">ช่วงเวลา: <?= $filter_label ?></p>
                </div>
                <div style="display: flex; gap: 15px; margin-bottom: 25px;">
                    <div style="flex: 1; background: #6366f1; color: white; padding: 15px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 11px; opacity: 0.8;">ยอดใช้จ่ายรวม</div>
                        <div style="font-size: 20px; font-weight: 900;"><?= number_format($total_budget_spent, 2) ?></div>
                    </div>
                    <div style="flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 11px; color: #94a3b8;">ค่าเฉลี่ยต่องบ</div>
                        <div style="font-size: 20px; font-weight: 900; color: #1e293b;"><?= number_format($avg_expenditure, 2) ?></div>
                    </div>
                    <div style="flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 11px; color: #94a3b8;">PO จาก PR</div>
                        <div style="font-size: 20px; font-weight: 900; color: #0891b2;"><?= number_format($count_po_from_pr) ?> รายการ</div>
                    </div>
                    <div style="flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 11px; color: #94a3b8;">PR รอเบิก</div>
                        <div style="font-size: 20px; font-weight: 900; color: #e11d48;"><?= number_format($status_pr) ?> รายการ</div>
                    </div>
                    <div style="flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 11px; color: #94a3b8;">ยอดใช้จ่ายสูงสุด</div>
                        <div style="font-size: 20px; font-weight: 900; color: #6366f1;"><?= number_format($max_expenditure, 2) ?></div>
                    </div>
                </div>
                <h3 style="font-size: 16px; font-weight: 900; margin-bottom: 10px; color: #1e293b;">รายละเอียดงบประมาณ</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #f1f5f9;">
                            <th style="padding: 10px; text-align: left; font-weight: 900; color: #64748b; font-size: 11px; border-bottom: 2px solid #e2e8f0;">งบประมาณ</th>
                            <th style="padding: 10px; text-align: right; font-weight: 900; color: #64748b; font-size: 11px; border-bottom: 2px solid #e2e8f0;">ยอดใช้จ่าย</th>
                            <th style="padding: 10px; text-align: center; font-weight: 900; color: #64748b; font-size: 11px; border-bottom: 2px solid #e2e8f0;">สัดส่วน (%)</th>
                            <th style="padding: 10px; text-align: left; font-weight: 900; color: #64748b; font-size: 11px; border-bottom: 2px solid #e2e8f0;">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($budgets as $b): 
                            $pct = $total_budget_spent > 0 ? ($b['total'] / $total_budget_spent) * 100 : 0;
                            $is_max = ($b['total'] > 0 && $b['total'] == $max_expenditure);
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px; font-weight: bold;"><?= htmlspecialchars($b['name']) ?><?= $is_max ? ' ⭐' : '' ?></td>
                            <td style="padding: 10px; text-align: right; font-family: monospace; font-weight: bold;"><?= number_format($b['total'], 2) ?></td>
                            <td style="padding: 10px; text-align: center;"><?= number_format($pct, 1) ?>%</td>
                            <td style="padding: 10px;">
                                <?php if($b['total'] > $avg_expenditure): ?>
                                    🔥 สูงกว่าค่าเฉลี่ย
                                <?php elseif($b['total'] > 0): ?>
                                    ✅ ปกติ
                                <?php else: ?>
                                    — ยังไม่มีการใช้
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="text-align: center; color: #94a3b8; font-size: 11px; margin-top: 25px;">พิมพ์วันที่: <?= date('d/m/Y H:i') ?> | ระบบจัดซื้อจัดจ้าง</p>
            </div>
        `;
        const blob = new Blob([reportHTML], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        const w = window.open(url);
        setTimeout(() => { w.print(); }, 500);
    }
</script>

<?php include('footer.php'); ?>
