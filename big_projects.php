<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

$sql = "SELECT bp.*, 
        (SELECT GROUP_CONCAT(project_id) FROM big_project_items WHERE big_project_id = bp.id) as linked_project_ids,
        (SELECT COUNT(*) FROM big_project_items WHERE big_project_id = bp.id) as project_count,
        (SELECT customer_name FROM customers WHERE id = bp.customer_id) as customer_name,
        COALESCE((SELECT SUM(ms.net_amount) FROM project_milestones ms 
                  JOIN big_project_items bpi2 ON ms.project_id = bpi2.project_id 
                  WHERE bpi2.big_project_id = bp.id AND ms.status = 'paid'), 0) as total_paid,
        COALESCE((SELECT SUM(ms.net_amount) FROM project_milestones ms 
                  JOIN big_project_items bpi2 ON ms.project_id = bpi2.project_id 
                  WHERE bpi2.big_project_id = bp.id AND ms.status = 'pending'), 0) as total_pending
        FROM big_projects bp 
        WHERE bp.deleted_at IS NULL 
        ORDER BY bp.id DESC";
$result = mysqli_query($conn, $sql);
?>

<div>
    <div class="bg-white p-3 rounded-xl shadow-sm border border-slate-200 mb-4 flex flex-wrap gap-2 items-center">
        <div class="flex flex-wrap gap-2 items-center flex-1">
            <div class="relative min-w-[200px] flex-1 max-w-sm">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="bpSearch" placeholder="ค้นหาชื่อโปรเจคใหญ่..."
                    class="w-full pl-9 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[12px] focus:ring-2 focus:ring-indigo-500 transition-all"
                    onkeyup="filterBigProjects()">
            </div>

            <div class="relative min-w-[150px]">
                <select id="statusFilter" onchange="filterBigProjects()"
                    class="w-full bg-slate-50 border border-slate-200 rounded-lg text-[12px] py-1.5 px-3 focus:ring-2 focus:ring-indigo-500 cursor-pointer transition-all">
                    <option value="">ทุกสถานะ</option>
                    <option value="active">กำลังดำเนินการ</option>
                    <option value="completed">เสร็จสิ้น</option>
                    <option value="cancelled">ยกเลิก</option>
                </select>
            </div>

            <button onclick="resetFilters()"
                class="text-slate-500 hover:text-indigo-600 text-[11px] font-bold transition-colors px-1">
                <i class="fas fa-undo-alt mr-1"></i> ล้าง
            </button>
        </div>

        <div class="flex items-center gap-2">
            <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-lg">
                <button onclick="setViewMode('grid')" id="gridBtn"
                    class="w-7 h-7 flex items-center justify-center rounded-md transition-all text-xs">
                    <i class="fas fa-th-large"></i>
                </button>
                <button onclick="setViewMode('table')" id="tableBtn"
                    class="w-7 h-7 flex items-center justify-center rounded-md transition-all text-xs">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>

        <?php if (can_manage_projects()): ?>
        <button onclick="location.href='add_big_project.php'"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg flex items-center gap-2 transition-all text-[12px] font-bold shadow-sm">
            <i class="fas fa-plus-circle"></i> สร้างโปรเจคใหญ่
        </button>
        <?php endif; ?>
    </div>

    <div id="gridView" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php while ($row = mysqli_fetch_assoc($result)):
            $bp_id = $row['id'];
            $progress = 0;
            if ($row['contract_value'] > 0) {
                $progress = ($row['total_paid'] / $row['contract_value']) * 100;
            }
        ?>
            <div class="bp-item bp-card group bg-white rounded-2xl border border-slate-200 p-5 transition-all"
                data-name="<?= htmlspecialchars($row['name']) ?>"
                data-status="<?= $row['status'] ?>">
                <div class="flex flex-col h-full">
                    <div class="flex justify-between items-start mb-3">
                        <span class="text-[9px] px-2 py-0.5 rounded-md font-bold border <?= $row['status'] == 'active' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : ($row['status'] == 'completed' ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-rose-50 text-rose-600 border-rose-200') ?>">
                            <?php
                            if ($row['status'] == 'active') echo 'กำลังดำเนินการ';
                            elseif ($row['status'] == 'completed') echo 'เสร็จสิ้น';
                            else echo 'ยกเลิก';
                            ?>
                        </span>
                        <div class="flex gap-1">
                            <?php if (can_manage_projects()): ?>
                                <a href="add_big_project.php?id=<?= $bp_id ?>"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all" title="แก้ไข">
                                    <i class="fas fa-edit text-xs"></i>
                                </a>
                                <button onclick="deleteBigProject(<?= $bp_id ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-600 hover:text-white transition-all" title="ลบ">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <a href="detail_big_project.php?id=<?= $bp_id ?>" class="block font-bold text-slate-800 text-sm mb-1 hover:text-indigo-600 transition-colors">
                        <?= htmlspecialchars($row['name']) ?>
                    </a>

                    <?php if ($row['customer_name']): ?>
                        <p class="text-[11px] text-slate-500 mb-2">
                            <i class="fas fa-building mr-1"></i> <?= htmlspecialchars($row['customer_name']) ?>
                        </p>
                    <?php endif; ?>

                    <div class="flex items-center gap-2 text-[11px] text-slate-500 mb-3">
                        <i class="fas fa-layer-group"></i>
                        <span><?= $row['project_count'] ?> โปรเจคย่อย</span>
                    </div>

                    <?php if (!empty($row['description'])): ?>
                        <p class="text-[11px] text-slate-600 mb-3 line-clamp-2"><?= nl2br(htmlspecialchars($row['description'])) ?></p>
                    <?php endif; ?>

                    <div class="mt-auto space-y-2">
                        <div class="bg-slate-50 rounded-xl p-3 space-y-1.5">
                            <div class="flex justify-between text-[10px]">
                                <span class="text-slate-500">มูลค่ารวม</span>
                                <span class="font-bold text-slate-700"><?= number_format($row['contract_value'] ?? 0, 2) ?></span>
                            </div>
                            <div class="flex justify-between text-[10px]">
                                <span class="text-slate-500">รับเงินแล้ว</span>
                                <span class="font-bold text-emerald-600"><?= number_format($row['total_paid'] ?? 0, 2) ?></span>
                            </div>
                            <div class="flex justify-between text-[10px]">
                                <span class="text-slate-500">ค้างรับ</span>
                                <span class="font-bold text-amber-600"><?= number_format($row['total_pending'] ?? 0, 2) ?></span>
                            </div>
                            <div class="pt-1.5 border-t border-slate-200">
                                <div class="flex justify-between text-[10px] mb-1">
                                    <span class="text-slate-500">ความคืบหน้า</span>
                                    <span class="font-bold text-indigo-600"><?= number_format($progress, 1) ?>%</span>
                                </div>
                                <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: <?= min($progress, 100) ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($row['start_date']) && $row['start_date'] != '0000-00-00'): ?>
                            <p class="text-[10px] text-slate-400">
                                <i class="far fa-calendar-alt mr-1"></i> 
                                <?= date('d/m/Y', strtotime($row['start_date'])) ?> 
                                <?php if (!empty($row['end_date']) && $row['end_date'] != '0000-00-00'): ?>
                                    - <?= date('d/m/Y', strtotime($row['end_date'])) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100">
                        <a href="detail_big_project.php?id=<?= $bp_id ?>" 
                           class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 transition-colors">
                            <i class="fas fa-arrow-right mr-1"></i> ดูรายละเอียด
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <div id="tableView" class="hidden overflow-x-auto bg-white rounded-2xl border border-slate-200">
        <table class="w-full text-left border-collapse min-w-[800px]">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider">ชื่อโปรเจค</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">สถานะ</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider">ลูกค้า</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">โปรเจคย่อย</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">มูลค่ารวม</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">รับแล้ว</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">ค้างรับ</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                mysqli_data_seek($result, 0);
                while ($row = mysqli_fetch_assoc($result)):
                    $bp_id = $row['id'];
                ?>
                    <tr class="bp-item bp-row hover:bg-slate-50/50 transition-colors"
                        data-name="<?= htmlspecialchars($row['name']) ?>"
                        data-status="<?= $row['status'] ?>">
                        <td class="p-4">
                            <a href="detail_big_project.php?id=<?= $bp_id ?>" class="text-sm font-bold text-slate-800 hover:text-indigo-600">
                                <?= htmlspecialchars($row['name']) ?>
                            </a>
                        </td>
                        <td class="p-4 text-center">
                            <span class="inline-block text-[10px] px-2 py-0.5 rounded-md font-bold border <?= $row['status'] == 'active' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : ($row['status'] == 'completed' ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-rose-50 text-rose-600 border-rose-200') ?>">
                                <?php
                                if ($row['status'] == 'active') echo 'กำลังดำเนินการ';
                                elseif ($row['status'] == 'completed') echo 'เสร็จสิ้น';
                                else echo 'ยกเลิก';
                                ?>
                            </span>
                        </td>
                        <td class="p-4 text-[12px] text-slate-600"><?= htmlspecialchars($row['customer_name'] ?? '-') ?></td>
                        <td class="p-4 text-center text-[12px] font-bold text-indigo-600"><?= $row['project_count'] ?></td>
                        <td class="p-4 text-right text-sm font-bold text-slate-700"><?= number_format($row['contract_value'] ?? 0, 2) ?></td>
                        <td class="p-4 text-right text-sm font-bold text-emerald-600"><?= number_format($row['total_paid'] ?? 0, 2) ?></td>
                        <td class="p-4 text-right text-sm font-bold text-amber-600"><?= number_format($row['total_pending'] ?? 0, 2) ?></td>
                        <td class="p-4">
                            <div class="flex justify-center items-center gap-1.5">
                                <a href="detail_big_project.php?id=<?= $bp_id ?>"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shadow-sm" title="ดูรายละเอียด">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                                <?php if (can_manage_projects()): ?>
                                    <a href="add_big_project.php?id=<?= $bp_id ?>"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all shadow-sm" title="แก้ไข">
                                        <i class="fas fa-edit text-xs"></i>
                                    </a>
                                    <button onclick="deleteBigProject(<?= $bp_id ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-600 hover:text-white transition-all shadow-sm" title="ลบ">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function setViewMode(mode) {
        localStorage.setItem('bp_view_mode', mode);
        updateViewUI();
    }

    function updateViewUI() {
        const mode = localStorage.getItem('bp_view_mode') || 'grid';
        const gridView = document.getElementById('gridView');
        const tableView = document.getElementById('tableView');
        const gridBtn = document.getElementById('gridBtn');
        const tableBtn = document.getElementById('tableBtn');

        if (mode === 'grid') {
            gridView.classList.remove('hidden');
            tableView.classList.add('hidden');
            gridBtn.classList.add('bg-white', 'text-indigo-600', 'shadow-sm');
            gridBtn.classList.remove('text-slate-400');
            tableBtn.classList.remove('bg-white', 'text-indigo-600', 'shadow-sm');
            tableBtn.classList.add('text-slate-400');
        } else {
            gridView.classList.add('hidden');
            tableView.classList.remove('hidden');
            tableBtn.classList.add('bg-white', 'text-indigo-600', 'shadow-sm');
            tableBtn.classList.remove('text-slate-400');
            gridBtn.classList.remove('bg-white', 'text-indigo-600', 'shadow-sm');
            gridBtn.classList.add('text-slate-400');
        }
    }

    function filterBigProjects() {
        const search = document.getElementById('bpSearch').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        const items = document.querySelectorAll('.bp-item');

        items.forEach(item => {
            const name = item.getAttribute('data-name').toLowerCase();
            const itemStatus = item.getAttribute('data-status');
            const matchSearch = name.includes(search);
            const matchStatus = (status === "" || itemStatus === status);
            item.style.display = (matchSearch && matchStatus) ? "" : "none";
        });
    }

    function resetFilters() {
        document.getElementById('bpSearch').value = '';
        document.getElementById('statusFilter').value = '';
        filterBigProjects();
    }

    function deleteBigProject(id, name) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: `คุณกำลังจะลบโปรเจคใหญ่ "${name}"`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก',
            heightAuto: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `api/save_big_project.php?action=delete&id=${id}`;
            }
        })
    }

    document.addEventListener("DOMContentLoaded", function () {
        updateViewUI();
        filterBigProjects();
    });
</script>

<style>
    .swal2-container { z-index: 99999 !important; }
</style>

<?php include('footer.php'); ?>
