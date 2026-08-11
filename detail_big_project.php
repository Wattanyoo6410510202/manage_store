<?php
require_once 'config.php';
include('header.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo "<div class='p-10 text-center text-red-500'>ไม่พบข้อมูล</div>";
    exit;
}

$sql = "SELECT bp.*, 
        (SELECT customer_name FROM customers WHERE id = bp.customer_id) as customer_name,
        u.name as created_by_name
        FROM big_projects bp 
        LEFT JOIN users u ON bp.created_by = u.id
        WHERE bp.id = $id AND bp.deleted_at IS NULL";
$res = mysqli_query($conn, $sql);
$bp = mysqli_fetch_assoc($res);

if (!$bp) {
    echo "<div class='p-10 text-center text-red-500'>ไม่พบข้อมูลโปรเจคใหญ่</div>";
    exit;
}

$summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(p.id) as project_count,
        COALESCE(SUM(p.contract_value), 0) as total_contract_value,
        COALESCE(SUM(p.net_contract_value), 0) as total_net_value,
        COALESCE(SUM(p.total_vat_amount), 0) as total_vat,
        COALESCE(SUM(p.total_wht_amount), 0) as total_wht,
        COALESCE((SELECT SUM(ms.net_amount) FROM project_milestones ms 
                  JOIN big_project_items bpi2 ON ms.project_id = bpi2.project_id 
                  WHERE bpi2.big_project_id = $id AND ms.status = 'paid'), 0) as total_paid,
        COALESCE((SELECT SUM(ms.net_amount) FROM project_milestones ms 
                  JOIN big_project_items bpi2 ON ms.project_id = bpi2.project_id 
                  WHERE bpi2.big_project_id = $id AND ms.status = 'pending'), 0) as total_pending,
        COALESCE((SELECT SUM(ms.retention_amount) FROM project_milestones ms 
                  JOIN big_project_items bpi2 ON ms.project_id = bpi2.project_id 
                  WHERE bpi2.big_project_id = $id), 0) as total_retention
    FROM projects p
    JOIN big_project_items bpi ON p.id = bpi.project_id
    WHERE bpi.big_project_id = $id AND p.deleted_at IS NULL
"));

$progress = 0;
if ($summary['total_contract_value'] > 0) {
    $progress = ($summary['total_paid'] / $summary['total_contract_value']) * 100;
}

$items = mysqli_query($conn, "
    SELECT p.*, bpi.id as link_id,
        COALESCE((SELECT SUM(ms.net_amount) FROM project_milestones ms WHERE ms.project_id = p.id AND ms.status = 'paid'), 0) as project_paid,
        COALESCE((SELECT SUM(ms.net_amount) FROM project_milestones ms WHERE ms.project_id = p.id AND ms.status = 'pending'), 0) as project_pending
    FROM big_project_items bpi
    JOIN projects p ON bpi.project_id = p.id
    WHERE bpi.big_project_id = $id
    ORDER BY p.id DESC
");
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="big_projects.php" class="text-[12px] text-indigo-600 hover:text-indigo-800 font-bold mb-1 inline-block">
                <i class="fas fa-arrow-left mr-1"></i> กลับไปรายการโปรเจคใหญ่
            </a>
            <h2 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($bp['name']) ?></h2>
        </div>
        <div class="flex gap-2">
            <?php if (can_manage_projects()): ?>
                <a href="add_big_project.php?id=<?= $id ?>"
                    class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-xl font-bold text-[12px] transition-all">
                    <i class="fas fa-edit mr-1"></i> แก้ไข
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-[11px] text-slate-500 font-bold uppercase tracking-wide mb-1">มูลค่างานรวม</p>
            <p class="text-2xl font-black text-slate-800"><?= number_format($summary['total_contract_value'], 2) ?></p>
            <p class="text-[10px] text-slate-400 mt-1">
                <i class="fas fa-layer-group mr-1"></i> <?= $summary['project_count'] ?> โปรเจคย่อย
            </p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-[11px] text-slate-500 font-bold uppercase tracking-wide mb-1">รับเงินแล้ว</p>
            <p class="text-2xl font-black text-emerald-600"><?= number_format($summary['total_paid'], 2) ?></p>
            <div class="flex items-center gap-2 mt-1">
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full" style="width: <?= min($progress, 100) ?>%"></div>
                </div>
                <span class="text-[11px] font-bold text-emerald-600"><?= number_format($progress, 1) ?>%</span>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-[11px] text-slate-500 font-bold uppercase tracking-wide mb-1">ค้างรับ (Pending)</p>
            <p class="text-2xl font-black text-amber-600"><?= number_format($summary['total_pending'], 2) ?></p>
            <p class="text-[10px] text-slate-400 mt-1">จำนวน <?= $summary['project_count'] ?> โปรเจค</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-[11px] text-slate-500 font-bold uppercase tracking-wide mb-1">หลักประกัน (Retention)</p>
            <p class="text-2xl font-black text-indigo-600"><?= number_format($summary['total_retention'], 2) ?></p>
            <p class="text-[10px] text-slate-400 mt-1">รวมทุกรายการ</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle text-indigo-500"></i> ข้อมูลทั่วไป
                </h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-slate-500 text-[11px]">ลูกค้า</p>
                        <p class="font-bold text-slate-800"><?= htmlspecialchars($bp['customer_name'] ?? '-') ?></p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-[11px]">สถานะ</p>
                        <span class="inline-block text-[11px] px-2 py-0.5 rounded-md font-bold border mt-1 <?= $bp['status'] == 'active' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : ($bp['status'] == 'completed' ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-rose-50 text-rose-600 border-rose-200') ?>">
                            <?php if ($bp['status'] == 'active') echo 'กำลังดำเนินการ';
                            elseif ($bp['status'] == 'completed') echo 'เสร็จสิ้น';
                            else echo 'ยกเลิก'; ?>
                        </span>
                    </div>
                    <?php if (!empty($bp['start_date']) && $bp['start_date'] != '0000-00-00'): ?>
                    <div>
                        <p class="text-slate-500 text-[11px]">วันที่เริ่ม</p>
                        <p class="font-bold text-slate-800"><?= date('d/m/Y', strtotime($bp['start_date'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bp['end_date']) && $bp['end_date'] != '0000-00-00'): ?>
                    <div>
                        <p class="text-slate-500 text-[11px]">วันที่สิ้นสุด</p>
                        <p class="font-bold text-slate-800"><?= date('d/m/Y', strtotime($bp['end_date'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <p class="text-slate-500 text-[11px]">สร้างโดย</p>
                        <p class="font-bold text-slate-800"><?= htmlspecialchars($bp['created_by_name'] ?? '-') ?></p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-[11px]">สร้างเมื่อ</p>
                        <p class="font-bold text-slate-800"><?= date('d/m/Y H:i', strtotime($bp['created_at'])) ?></p>
                    </div>
                </div>
                <?php if (!empty($bp['description'])): ?>
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <p class="text-slate-500 text-[11px] mb-1">รายละเอียด</p>
                        <p class="text-slate-700"><?= nl2br(htmlspecialchars($bp['description'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($bp['remarks'])): ?>
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <p class="text-slate-500 text-[11px] mb-1">หมายเหตุ</p>
                        <p class="text-slate-700"><?= nl2br(htmlspecialchars($bp['remarks'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-layer-group text-indigo-500"></i> โปรเจคย่อยที่รวมอยู่
                </h3>
                <?php if (mysqli_num_rows($items) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 text-[11px] text-slate-500 uppercase font-bold">
                                    <th class="pb-3">ชื่อโปรเจค</th>
                                    <th class="pb-3 text-center">สถานะ</th>
                                    <th class="pb-3 text-right">มูลค่า</th>
                                    <th class="pb-3 text-right">สุทธิ</th>
                                    <th class="pb-3 text-right">รับแล้ว</th>
                                    <th class="pb-3 text-right">ค้างรับ</th>
                                    <th class="pb-3 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php while ($item = mysqli_fetch_assoc($items)): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="py-3 pr-4">
                                            <a href="detail_project.php?id=<?= $item['id'] ?>" class="text-sm font-bold text-slate-800 hover:text-indigo-600">
                                                <?= htmlspecialchars($item['project_name']) ?>
                                            </a>
                                            <p class="text-[10px] text-slate-400"><?= htmlspecialchars($item['project_no']) ?></p>
                                        </td>
                                        <td class="py-3 text-center">
                                            <span class="text-[10px] px-2 py-0.5 rounded-md font-bold border <?= $item['project_status'] == 'active' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' ?>">
                                                <?php if ($item['project_status'] == 'active') echo 'กำลังดำเนินการ';
                                                elseif ($item['project_status'] == 'completed') echo 'เสร็จสิ้น';
                                                else echo 'รอดำเนินการ'; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-right text-sm font-bold text-slate-700"><?= number_format($item['contract_value'] ?? 0, 2) ?></td>
                                        <td class="py-3 text-right text-sm font-bold text-indigo-600"><?= number_format($item['net_contract_value'] ?? 0, 2) ?></td>
                                        <td class="py-3 text-right text-sm font-bold text-emerald-600"><?= number_format($item['project_paid'] ?? 0, 2) ?></td>
                                        <td class="py-3 text-right text-sm font-bold text-amber-600"><?= number_format($item['project_pending'] ?? 0, 2) ?></td>
                                        <td class="py-3 text-center">
                                            <a href="detail_project.php?id=<?= $item['id'] ?>"
                                                class="text-indigo-600 hover:text-indigo-800 text-xs" title="ดูรายละเอียด">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 bg-slate-50 rounded-xl">
                        <i class="fas fa-folder-open text-slate-300 text-3xl mb-2"></i>
                        <p class="text-slate-500 text-sm">ยังไม่มีโปรเจคย่อยในโปรเจคใหญ่นี้</p>
                        <a href="add_big_project.php?id=<?= $id ?>" class="text-indigo-600 hover:text-indigo-800 font-bold text-[12px] inline-block mt-2">
                            <i class="fas fa-plus-circle mr-1"></i> เพิ่มโปรเจคย่อย
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-slate-900 rounded-2xl p-6 text-white">
                <h3 class="font-bold text-indigo-300 mb-4 border-b border-indigo-800 pb-2">สรุปมูลค่า</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-400">มูลค่างานรวม:</span>
                        <span class="font-bold"><?= number_format($summary['total_contract_value'] ?? 0, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">VAT (7%):</span>
                        <span class="text-indigo-400">+ <?= number_format($summary['total_vat'] ?? 0, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">หัก ณ ที่จ่าย (3%):</span>
                        <span class="text-rose-400">- <?= number_format($summary['total_wht'] ?? 0, 2) ?></span>
                    </div>
                    <div class="flex justify-between border-t border-slate-800 pt-2">
                        <span class="font-bold text-slate-200">มูลค่าสุทธิ:</span>
                        <span class="font-bold text-lg text-indigo-400"><?= number_format($summary['total_net_value'] ?? 0, 2) ?></span>
                    </div>

                    <div class="border-t border-slate-800 pt-3 mt-3 space-y-2">
                        <div class="flex justify-between text-emerald-400">
                            <span class="text-slate-400">รับเงินแล้ว:</span>
                            <span class="font-bold"><?= number_format($summary['total_paid'] ?? 0, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-amber-400">
                            <span class="text-slate-400">ค้างรับ:</span>
                            <span class="font-bold"><?= number_format($summary['total_pending'] ?? 0, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-indigo-400">
                            <span class="text-slate-400">หลักประกัน:</span>
                            <span class="font-bold"><?= number_format($summary['total_retention'] ?? 0, 2) ?></span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-800">
                    <div class="flex justify-between text-[11px] mb-1">
                        <span class="text-slate-400">ความคืบหน้า</span>
                        <span class="font-bold text-emerald-400"><?= number_format($progress, 1) ?>%</span>
                    </div>
                    <div class="w-full bg-slate-700 h-2 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full transition-all duration-1000" style="width: <?= min($progress, 100) ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
