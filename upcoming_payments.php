<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

// ===== Stats =====
$now = date('Y-m-d');
$seven_days = date('Y-m-d', strtotime('+7 days'));

// Total pending amount
$sql_total = "SELECT COALESCE(SUM(m.total_request_amount), 0) as total_pending
              FROM project_milestones m
              JOIN projects p ON m.project_id = p.id
              WHERE m.status = 'pending' AND p.project_status = 'active'";
$total_pending = mysqli_fetch_assoc(mysqli_query($conn, $sql_total))['total_pending'];

// Overdue (past claim_date)
$sql_overdue = "SELECT COUNT(*) as cnt, COALESCE(SUM(m.total_request_amount), 0) as total
                FROM project_milestones m
                JOIN projects p ON m.project_id = p.id
                WHERE m.status = 'pending' AND p.project_status = 'active' AND m.claim_date < '$now'";
$overdue = mysqli_fetch_assoc(mysqli_query($conn, $sql_overdue));

// Due within 7 days
$sql_upcoming = "SELECT COUNT(*) as cnt, COALESCE(SUM(m.total_request_amount), 0) as total
                 FROM project_milestones m
                 JOIN projects p ON m.project_id = p.id
                 WHERE m.status = 'pending' AND p.project_status = 'active'
                 AND m.claim_date BETWEEN '$now' AND '$seven_days'";
$upcoming = mysqli_fetch_assoc(mysqli_query($conn, $sql_upcoming));

// Past 7 days
$sql_later = "SELECT COUNT(*) as cnt, COALESCE(SUM(m.total_request_amount), 0) as total
              FROM project_milestones m
              JOIN projects p ON m.project_id = p.id
              WHERE m.status = 'pending' AND p.project_status = 'active'
              AND m.claim_date > '$seven_days'";
$later = mysqli_fetch_assoc(mysqli_query($conn, $sql_later));

// ===== Main Query =====
$project_filter = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$where = "m.status = 'pending' AND p.project_status = 'active'";
if ($project_filter > 0) {
    $where .= " AND p.id = $project_filter";
}

$sql = "SELECT m.*, p.id as pid, p.project_name, p.project_no, p.contractor_name, p.end_date as project_end
        FROM project_milestones m
        JOIN projects p ON m.project_id = p.id
        WHERE $where
        ORDER BY m.claim_date ASC";
$result = mysqli_query($conn, $sql);
$milestones = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Group by project
$grouped = [];
foreach ($milestones as $m) {
    $gid = $m['pid'];
    if (!isset($grouped[$gid])) {
        $grouped[$gid] = [
            'project_name' => $m['project_name'],
            'project_no' => $m['project_no'],
            'contractor_name' => $m['contractor_name'],
            'project_end' => $m['project_end'],
            'milestones' => []
        ];
    }
    $grouped[$gid]['milestones'][] = $m;
}

// Projects for filter
$projects = mysqli_query($conn, "SELECT id, project_name FROM projects WHERE project_status = 'active' ORDER BY project_name ASC");
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">ตรวจสอบยอดค้างชำระ</h2>
            <p class="text-slate-500 text-sm">รายการงวดงานที่ยังไม่ชำระเงิน แยกตามโครงการ</p>
        </div>
        <div class="flex gap-2">
            <select onchange="window.location.href='?project_id='+this.value"
                class="bg-white border-slate-200 border rounded-xl px-4 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 transition-all outline-none">
                <option value="0">ทุกโครงการ</option>
                <?php while ($p = mysqli_fetch_assoc($projects)): ?>
                    <option value="<?= $p['id'] ?>" <?= $project_filter == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['project_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden group hover:shadow-lg transition-all">
            <div class="absolute -right-6 -top-6 w-20 h-20 bg-indigo-50 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">ยอดค้างชำระรวม</p>
            <h3 class="text-2xl font-black text-slate-800"><?= number_format($total_pending, 2) ?></h3>
            <div class="mt-2 flex items-center text-[10px] font-bold text-indigo-500">
                <i class="fas fa-coins mr-1"></i> จากทุกรายการที่รอชำระ
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-red-200 shadow-sm relative overflow-hidden group hover:shadow-lg transition-all">
            <div class="absolute -right-6 -top-6 w-20 h-20 bg-red-50 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <p class="text-xs font-bold text-red-400 uppercase tracking-wider mb-1">เลยกำหนด</p>
            <h3 class="text-2xl font-black text-red-600"><?= number_format($overdue['total'], 2) ?></h3>
            <div class="mt-2 flex items-center text-[10px] font-bold text-red-500">
                <i class="fas fa-exclamation-triangle mr-1"></i> <?= $overdue['cnt'] ?> รายการ ที่เลยกำหนดชำระ
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-amber-200 shadow-sm relative overflow-hidden group hover:shadow-lg transition-all">
            <div class="absolute -right-6 -top-6 w-20 h-20 bg-amber-50 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <p class="text-xs font-bold text-amber-400 uppercase tracking-wider mb-1">ใกล้ถึงกำหนด (7 วัน)</p>
            <h3 class="text-2xl font-black text-amber-600"><?= number_format($upcoming['total'], 2) ?></h3>
            <div class="mt-2 flex items-center text-[10px] font-bold text-amber-500">
                <i class="fas fa-clock mr-1"></i> <?= $upcoming['cnt'] ?> รายการ ที่ต้องชำระภายใน 7 วัน
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden group hover:shadow-lg transition-all">
            <div class="absolute -right-6 -top-6 w-20 h-20 bg-slate-50 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">เกิน 7 วัน</p>
            <h3 class="text-2xl font-black text-slate-600"><?= number_format($later['total'], 2) ?></h3>
            <div class="mt-2 flex items-center text-[10px] font-bold text-slate-400">
                <i class="fas fa-calendar mr-1"></i> <?= $later['cnt'] ?> รายการ
            </div>
        </div>
    </div>

    <!-- Table -->
    <?php if (empty($grouped)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
            <i class="fas fa-check-circle text-5xl text-emerald-300 mb-4"></i>
            <p class="text-lg font-bold text-slate-400">ไม่มีรายการค้างชำระ</p>
            <p class="text-sm text-slate-300">ทุกรายการได้รับการชำระเงินแล้ว</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($grouped as $gid => $g): 
                $g_total = array_sum(array_column($g['milestones'], 'total_request_amount'));
                $g_overdue = 0;
                $g_upcoming = 0;
                foreach ($g['milestones'] as $m) {
                    if ($m['claim_date'] < $now) $g_overdue++;
                    elseif ($m['claim_date'] <= $thirty_days) $g_upcoming++;
                }
            ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <!-- Project Header -->
                    <div class="px-6 py-4 bg-gradient-to-r from-indigo-50/50 to-transparent border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-slate-800"><?= htmlspecialchars($g['project_name']) ?></h3>
                                <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-md font-bold"><?= htmlspecialchars($g['project_no']) ?></span>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">
                                ผู้รับเหมา: <?= htmlspecialchars($g['contractor_name'] ?: '-') ?>
                                <?php if ($g['project_end']): ?>
                                    <span class="mx-2">|</span> สิ้นสุดสัญญา: <?= date('d/m/Y', strtotime($g['project_end'])) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <?php if ($g_overdue > 0): ?>
                                <span class="text-[10px] font-bold text-red-500 bg-red-50 px-2.5 py-1 rounded-full">เลยกำหนด <?= $g_overdue ?> รายการ</span>
                            <?php endif; ?>
                            <?php if ($g_upcoming > 0): ?>
                                <span class="text-[10px] font-bold text-amber-500 bg-amber-50 px-2.5 py-1 rounded-full">ใกล้ถึง <?= $g_upcoming ?> รายการ</span>
                            <?php endif; ?>
                            <span class="text-sm font-bold text-slate-700"><?= number_format($g_total, 2) ?>฿</span>
                        </div>
                    </div>

                    <!-- Milestones Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">งวดงาน</th>
                                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">กำหนดชำระ</th>
                                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">จำนวนเงิน</th>
                                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">VAT</th>
                                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">WHT</th>
                                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">ยอดสุทธิขอเบิก</th>
                                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">สถานะ</th>
                                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach ($g['milestones'] as $m): 
                                    $days_diff = (new DateTime($m['claim_date']))->diff(new DateTime())->days;
                                    $is_overdue = $m['claim_date'] < $now;
                                    $is_upcoming = !$is_overdue && $m['claim_date'] <= $thirty_days;
                                ?>
                                    <tr class="hover:bg-indigo-50/30 transition-colors">
                                        <td class="px-6 py-3">
                                            <span class="font-semibold text-slate-700 text-sm"><?= htmlspecialchars($m['milestone_name']) ?></span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <span class="font-mono text-sm <?= $is_overdue ? 'text-red-600 font-bold' : 'text-slate-600' ?>">
                                                <?= date('d/m/Y', strtotime($m['claim_date'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono text-sm text-slate-700"><?= number_format($m['amount'], 2) ?></td>
                                        <td class="px-6 py-3 text-right font-mono text-sm text-slate-500"><?= number_format($m['vat_amount'], 2) ?></td>
                                        <td class="px-6 py-3 text-right font-mono text-sm text-slate-500"><?= number_format($m['wht_amount'], 2) ?></td>
                                        <td class="px-6 py-3 text-right font-mono text-sm font-bold text-slate-800"><?= number_format($m['total_request_amount'], 2) ?></td>
                                        <td class="px-6 py-3 text-center">
                                            <?php if ($is_overdue): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 text-red-600 text-[10px] font-bold rounded-full">
                                                    <i class="fas fa-exclamation-circle"></i> เลยกำหนด <?= $days_diff ?> วัน
                                                </span>
                                            <?php elseif ($is_upcoming): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-amber-600 text-[10px] font-bold rounded-full">
                                                    <i class="fas fa-clock"></i> อีก <?= $days_diff ?> วัน
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-50 text-slate-500 text-[10px] font-bold rounded-full">
                                                    <i class="fas fa-calendar"></i> อีก <?= $days_diff ?> วัน
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <a href="detail_project.php?id=<?= $m['project_id'] ?>"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-50 text-indigo-600 text-[10px] font-bold rounded-lg hover:bg-indigo-100 transition-all">
                                                <i class="fas fa-external-link-alt"></i> ดูโครงการ
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include('footer.php'); ?>
