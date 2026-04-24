<?php
require_once 'config.php';
include('header.php');

// กรองตามบริษัท
$sup_filter = isset($_GET['sup_id']) && $_GET['sup_id'] > 0 ? "AND supplier_id = " . intval($_GET['sup_id']) : "";

// 1. Stats
$total_po = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(grand_total) as total FROM po WHERE status = 'approved' AND deleted_at IS NULL $sup_filter"))['total'] ?: 0;
$count_po = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM po WHERE deleted_at IS NULL $sup_filter"))['total'] ?: 0;
$status_pr = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pr WHERE status = 'pending' AND deleted_at IS NULL $sup_filter"))['total'];
$status_po = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM po WHERE status = 'pending' AND deleted_at IS NULL $sup_filter"))['total'];
$pr_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pr WHERE deleted_at IS NULL $sup_filter"))['total'];

// 2. Data สำหรับกราฟ
$expense_data = mysqli_query($conn, "SELECT c.name, SUM(p.grand_total) as total FROM expense_categories c LEFT JOIN po p ON p.supplier_id = c.sup_id WHERE 1 $sup_filter GROUP BY c.id");
$budget_data = mysqli_query($conn, "SELECT b.name, SUM(p.grand_total) as total FROM budget_types b LEFT JOIN po p ON p.supplier_id = b.sup_id WHERE 1 $sup_filter GROUP BY b.id");

$exp_labels = []; $exp_vals = [];
while($r = mysqli_fetch_assoc($expense_data)) { $exp_labels[] = $r['name']; $exp_vals[] = $r['total'] ?: 0; }
$bud_labels = []; $bud_vals = [];
while($r = mysqli_fetch_assoc($budget_data)) { $bud_labels[] = $r['name']; $bud_vals[] = $r['total'] ?: 0; }

$suppliers = mysqli_query($conn, "SELECT id, company_name FROM suppliers");
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-black text-slate-800">Procurement Dashboard</h2>
        <div class="flex gap-2">
            <select id="supFilter" class="bg-white border rounded-lg px-3 py-2 text-sm" onchange="filterDashboard()">
                <option value="0">ทุกบริษัท</option>
                <?php while($s = mysqli_fetch_assoc($suppliers)) echo "<option value='".$s['id']."' ".(isset($_GET['sup_id']) && $_GET['sup_id']==$s['id']?'selected':'').">".$s['company_name']."</option>"; ?>
            </select>
            <input type="date" id="startDate" class="bg-white border rounded-lg px-3 py-2 text-sm" value="<?= $_GET['start'] ?? '' ?>">
            <input type="date" id="endDate" class="bg-white border rounded-lg px-3 py-2 text-sm" value="<?= $_GET['end'] ?? '' ?>">
            <button onclick="filterDashboard()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700">กรอง</button>
        </div>
    </div>

    <!-- Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-indigo-50 p-6 rounded-2xl border border-indigo-100 shadow-sm">
            <p class="text-indigo-900 text-xs font-bold uppercase tracking-wider">ยอดสั่งซื้อรวม (Approved)</p>
            <h3 class="text-3xl font-black text-indigo-700 mt-2"><?= number_format($total_po, 2) ?></h3>
        </div>
        <div class="bg-emerald-50 p-6 rounded-2xl border border-emerald-100 shadow-sm">
            <p class="text-emerald-900 text-xs font-bold uppercase tracking-wider">จำนวน PO ทั้งหมด</p>
            <h3 class="text-3xl font-black text-emerald-700 mt-2"><?= number_format($count_po) ?> ใบ</h3>
        </div>
        <div class="bg-rose-50 p-6 rounded-2xl border border-rose-100 shadow-sm">
            <p class="text-rose-900 text-xs font-bold uppercase tracking-wider">PR รออนุมัติ</p>
            <h3 class="text-3xl font-black text-rose-700 mt-2"><?= number_format($status_pr) ?> รายการ</h3>
        </div>
        <div class="bg-amber-50 p-6 rounded-2xl border border-amber-100 shadow-sm">
            <p class="text-amber-900 text-xs font-bold uppercase tracking-wider">PO รออนุมัติ</p>
            <h3 class="text-3xl font-black text-amber-700 mt-2"><?= number_format($status_po) ?> รายการ</h3>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-4 rounded-2xl border shadow-sm">
            <h3 class="font-black text-slate-800 text-sm">เปรียบเทียบเอกสาร</h3>
            <p class="text-[10px] text-slate-500 mb-2">PR vs PO</p>
            <canvas id="barChart" height="200"></canvas>
        </div>
        <div class="bg-white p-4 rounded-2xl border shadow-sm">
            <h3 class="font-black text-slate-800 text-sm">แนวโน้มค่าใช้จ่าย</h3>
            <p class="text-[10px] text-slate-500 mb-2">ยอดสั่งซื้อรายเดือน</p>
            <canvas id="lineChart" height="200"></canvas>
        </div>
        <div class="bg-white p-4 rounded-2xl border shadow-sm">
            <h3 class="font-black text-slate-800 text-sm">หมวดค่าใช้จ่าย</h3>
            <p class="text-[10px] text-slate-500 mb-2">แยกตามหมวดหมู่</p>
            <canvas id="expenseChart" height="200"></canvas>
        </div>
        <div class="bg-white p-4 rounded-2xl border shadow-sm">
            <h3 class="font-black text-slate-800 text-sm">ประเภทงบประมาณ</h3>
            <p class="text-[10px] text-slate-500 mb-2">สัดส่วนตามประเภทงบ</p>
            <canvas id="budgetChart" height="200"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const colors = ['#4f46e5', '#10b981', '#f59e0b', '#e11d48', '#8b5cf6', '#06b6d4'];
    
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: { labels: ['PR', 'PO'], datasets: [{ label: 'ปริมาณ', data: [<?= $pr_count ?>, <?= $count_po ?>], backgroundColor: colors }] }
    });

    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: { labels: ['Jan', 'Feb', 'Mar', 'Apr'], datasets: [{ label: 'ยอดใช้จ่าย', data: [10, 20, 15, 25], borderColor: '#4f46e5', fill: true }] }
    });

    new Chart(document.getElementById('expenseChart'), {
        type: 'doughnut',
        data: { labels: <?= json_encode($exp_labels) ?>, datasets: [{ data: <?= json_encode($exp_vals) ?>, backgroundColor: colors }] }
    });

    new Chart(document.getElementById('budgetChart'), {
        type: 'pie',
        data: { labels: <?= json_encode($bud_labels) ?>, datasets: [{ data: <?= json_encode($bud_vals) ?>, backgroundColor: colors }] }
    });

    function filterDashboard() {
        const sup = $('#supFilter').val();
        const start = $('#startDate').val();
        const end = $('#endDate').val();
        window.location.href = `procurement.php?sup_id=${sup}&start=${start}&end=${end}`;
    }
</script>

<?php include('footer.php'); ?>