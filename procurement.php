<?php
require_once 'config.php';
include('header.php');

// กรองตามบริษัท
$sup_id = isset($_GET['sup_id']) ? intval($_GET['sup_id']) : 0;
$sup_filter_pr = $sup_id > 0 ? " AND p.supplier_id = $sup_id " : "";
$sup_filter_base = $sup_id > 0 ? " AND b.sup_id = $sup_id " : "";

// 1. Stats
$total_po_sql = "SELECT SUM(grand_total) as total FROM po WHERE status = 'approved' AND deleted_at IS NULL " . ($sup_id > 0 ? " AND supplier_id = $sup_id" : "");
$total_po = mysqli_fetch_assoc(mysqli_query($conn, $total_po_sql))['total'] ?: 0;

$count_po_sql = "SELECT COUNT(*) as total FROM po WHERE deleted_at IS NULL " . ($sup_id > 0 ? " AND supplier_id = $sup_id" : "");
$count_po = mysqli_fetch_assoc(mysqli_query($conn, $count_po_sql))['total'] ?: 0;

$status_pr_sql = "SELECT COUNT(*) as total FROM pr WHERE status = 'pending' AND deleted_at IS NULL " . ($sup_id > 0 ? " AND supplier_id = $sup_id" : "");
$status_pr = mysqli_fetch_assoc(mysqli_query($conn, $status_pr_sql))['total'];

$status_po_sql = "SELECT COUNT(*) as total FROM po WHERE status = 'pending' AND deleted_at IS NULL " . ($sup_id > 0 ? " AND supplier_id = $sup_id" : "");
$status_po = mysqli_fetch_assoc(mysqli_query($conn, $status_po_sql))['total'];

// 2. Data สำหรับกราฟ และ ตาราง
$expense_query = "SELECT c.name, SUM(p.grand_total) as total 
                  FROM expense_categories c 
                  LEFT JOIN pr p ON p.expense_cat_id = c.id AND p.status = 'approved' AND p.deleted_at IS NULL $sup_filter_pr
                  WHERE 1 $sup_filter_base
                  GROUP BY c.id 
                  HAVING total > 0
                  ORDER BY total DESC";
$expense_res = mysqli_query($conn, $expense_query);
$exp_labels = []; $exp_vals = [];
while($r = mysqli_fetch_assoc($expense_res)) { $exp_labels[] = $r['name']; $exp_vals[] = (float)$r['total']; }

$budget_query = "SELECT b.name, SUM(p.grand_total) as total 
                 FROM budget_types b 
                 LEFT JOIN pr p ON p.budget_type_id = b.id AND p.status = 'approved' AND p.deleted_at IS NULL $sup_filter_pr
                 WHERE b.status = 'approved' $sup_filter_base
                 GROUP BY b.id 
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

$suppliers = mysqli_query($conn, "SELECT id, company_name FROM suppliers");
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Procurement Dashboard</h2>
            <p class="text-slate-500 text-sm">สรุปภาพรวมการจัดซื้อและงบประมาณ</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <select id="supFilter" class="bg-white border-slate-200 border rounded-xl px-4 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 transition-all" onchange="filterDashboard()">
                <option value="0">ทุกหน่วยงาน/บริษัท</option>
                <?php 
                mysqli_data_seek($suppliers, 0);
                while($s = mysqli_fetch_assoc($suppliers)) echo "<option value='".$s['id']."' ".($sup_id == $s['id'] ? 'selected':'').">".$s['company_name']."</option>"; 
                ?>
            </select>
            <button onclick="filterDashboard()" class="bg-indigo-600 text-white px-6 py-2 rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all active:scale-95">กรองข้อมูล</button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 p-6 rounded-3xl shadow-xl shadow-indigo-100 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
            <p class="text-indigo-100 text-xs font-bold uppercase tracking-wider mb-1">ยอดใช้จ่ายรวม (เบิกแล้ว)</p>
            <h3 class="text-3xl font-black text-white"><?= number_format($total_budget_spent, 2) ?></h3>
            <div class="mt-4 flex items-center text-indigo-100 text-[10px] font-bold">
                <i class="fas fa-chart-line mr-1"></i> รวมจากงบประมาณทั้งหมด
            </div>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm relative overflow-hidden group">
            <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">ค่าเฉลี่ยต่องบประมาณ</p>
            <h3 class="text-3xl font-black text-slate-800"><?= number_format($avg_expenditure, 2) ?></h3>
            <div class="mt-4 flex items-center text-emerald-500 text-[10px] font-bold">
                <i class="fas fa-calculator mr-1"></i> คำนวณจากประเภทงบที่มี
            </div>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm relative overflow-hidden group">
            <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">PR รอเบิก</p>
            <h3 class="text-3xl font-black text-rose-500"><?= number_format($status_pr) ?> <span class="text-sm font-bold text-slate-400">รายการ</span></h3>
            <div class="mt-4 flex items-center text-rose-400 text-[10px] font-bold">
                <i class="fas fa-clock mr-1"></i> อยู่ระหว่างรอการอนุมัติ
            </div>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm relative overflow-hidden group">
            <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">ยอดใช้จ่ายสูงสุด</p>
            <h3 class="text-3xl font-black text-indigo-600"><?= number_format($max_expenditure, 2) ?></h3>
            <div class="mt-4 flex items-center text-indigo-400 text-[10px] font-bold">
                <i class="fas fa-arrow-up mr-1"></i> งบประมาณที่ใช้เยอะที่สุด
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="font-black text-slate-800">หมวดหมู่ค่าใช้จ่าย</h3>
                    <p class="text-xs text-slate-400">สัดส่วนการใช้จ่ายแยกตามหมวดหมู่</p>
                </div>
                <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
            <div class="h-[300px] flex items-center justify-center">
                <?php if(empty($exp_vals)): ?>
                    <div class="text-slate-300 text-center">
                        <i class="fas fa-chart-pie text-4xl mb-2"></i>
                        <p class="text-sm font-bold">ไม่มีข้อมูล</p>
                    </div>
                <?php else: ?>
                    <canvas id="expenseChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="font-black text-slate-800">ประเภทงบประมาณ</h3>
                    <p class="text-xs text-slate-400">สัดส่วนตามประเภทงบประมาณ</p>
                </div>
                <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
            <div class="h-[300px] flex items-center justify-center">
                <?php if(empty($bud_vals)): ?>
                    <div class="text-slate-300 text-center">
                        <i class="fas fa-chart-pie text-4xl mb-2"></i>
                        <p class="text-sm font-bold">ไม่มีข้อมูล</p>
                    </div>
                <?php else: ?>
                    <canvas id="budgetChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Budget Table Section -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-50 flex justify-between items-center">
            <div>
                <h3 class="font-black text-slate-800">รายละเอียดงบประมาณ</h3>
                <p class="text-xs text-slate-400">ตารางแสดงรายการงบประมาณและการใช้จ่าย</p>
            </div>
            <button onclick="window.print()" class="text-slate-400 hover:text-indigo-600 transition-colors">
                <i class="fas fa-print"></i>
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">งบประมาณ</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">ยอดใช้จ่าย (เบิกแล้ว)</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">สัดส่วน (%)</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">สถานะความร้อนแรง</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach($budgets as $b): 
                        $pct = $total_budget_spent > 0 ? ($b['total'] / $total_budget_spent) * 100 : 0;
                        $is_max = ($b['total'] > 0 && $b['total'] == $max_expenditure);
                    ?>
                    <tr class="<?= $is_max ? 'bg-indigo-50/30' : '' ?> hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full <?= $is_max ? 'bg-indigo-500 animate-pulse' : 'bg-slate-300' ?>"></div>
                                <span class="font-bold text-slate-700"><?= htmlspecialchars($b['name']) ?></span>
                                <?php if($is_max): ?>
                                    <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-600 text-[8px] font-black uppercase">ใช้เยอะสุด</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="font-mono font-bold text-slate-900"><?= number_format($b['total'], 2) ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-24 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                    <div class="h-full <?= $is_max ? 'bg-indigo-500' : 'bg-slate-300' ?>" style="width: <?= $pct ?>%"></div>
                                </div>
                                <span class="text-[10px] font-bold text-slate-500"><?= number_format($pct, 1) ?>%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' }, padding: 20 } }
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
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' }, padding: 20 } }
            }
        }
    });
    <?php endif; ?>

    function filterDashboard() {
        const sup = $('#supFilter').val();
        window.location.href = `procurement.php?sup_id=${sup}`;
    }
</script>

<?php include('footer.php'); ?>