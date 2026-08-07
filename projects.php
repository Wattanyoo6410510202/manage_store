<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

// ดึงข้อมูลโปรเจกต์
$sql = "SELECT p.*, created_by,
        (SELECT SUM(net_amount) FROM project_milestones WHERE project_id = p.id AND status = 'paid') as collected_money,
        -- เพิ่มบรรทัดนี้ครับจาร เพื่อรวบ ID งวดงานทั้งหมด
        (SELECT GROUP_CONCAT(id) FROM project_milestones WHERE project_id = p.id) as all_milestone_ids
        FROM projects p ORDER BY p.id DESC";
if (!empty($inspection_only_access)) {
    $inspection_user_id = (int)($_SESSION['user_id'] ?? 0);
    $sql = "SELECT p.*, created_by,
            (SELECT SUM(net_amount) FROM project_milestones WHERE project_id = p.id AND status = 'paid') as collected_money,
            (SELECT GROUP_CONCAT(id) FROM project_milestones WHERE project_id = p.id) as all_milestone_ids
            FROM projects p
            WHERE EXISTS (
                SELECT 1
                FROM project_milestones pm
                INNER JOIN inspection_checklists ic ON ic.milestone_id = pm.id
                WHERE pm.project_id = p.id
                  AND ic.status IN ('active', 'draft')
                  AND (ic.inspector_1_user_id = $inspection_user_id OR ic.inspector_2_user_id = $inspection_user_id)
            )
            ORDER BY p.id DESC";
}
$result = mysqli_query($conn, $sql);
?>

<div>
    <div
        class="bg-white p-3 rounded-xl shadow-sm border border-slate-200 mb-4 flex flex-wrap gap-2 items-center">
        <div class="flex flex-wrap gap-2 items-center flex-1">
            <div class="relative min-w-[200px] flex-1 max-w-sm">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="projectSearch" placeholder="ค้นหาชื่อโครงการ..."
                    class="w-full pl-9 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[12px] focus:ring-2 focus:ring-indigo-500 transition-all"
                    onkeyup="filterProjects()">
            </div>

            <div class="relative min-w-[150px]">
                <select id="userFilter" onchange="filterProjects()"
                    class="w-full bg-slate-50 border border-slate-200 rounded-lg text-[12px] py-1.5 px-3 focus:ring-2 focus:ring-indigo-500 cursor-pointer transition-all">
                    <option value="">ผู้ใช้งานทั้งหมด</option>
                    <?php
                    $my_id = $_SESSION['user_id'] ?? '';
                    $user_query = $conn->query("SELECT id, name FROM users ORDER BY name ASC");
                    while ($u = $user_query->fetch_assoc()):
                        // ถ้าไม่ใช่ admin และไม่ใช่ viewer ให้เลือกตัวเองเป็นค่าเริ่มต้น
                        // Procurement manages checklists across projects, so do not hide
                        // every project behind a default "created by me" filter.
                        $selected = ($u['id'] == $my_id && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'procure' && !is_viewer()) ? 'selected' : '';
                        ?>
                        <option value="<?= $u['id'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="relative min-w-[150px]">
                <select id="companyFilter" onchange="filterProjects()"
                    class="w-full bg-slate-50 border border-slate-200 rounded-lg text-[12px] py-1.5 px-3 focus:ring-2 focus:ring-indigo-500 cursor-pointer transition-all">
                    <option value="">ทุกบริษัท/คู่ค้า</option>
                    <option value="none">-- ไม่มีบริษัท --</option>
                    <?php
                    $supplier_query = $conn->query("SELECT id, company_name FROM suppliers ORDER BY company_name ASC");
                    while ($s = $supplier_query->fetch_assoc()):
                        ?>
                        <option value="<?= $s['id'] ?>">
                            <?= htmlspecialchars($s['company_name']) ?>
                        </option>
                    <?php endwhile; ?>
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

        <?php if (!is_viewer()): ?>
        <button onclick="location.href='add_project.php'"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg flex items-center gap-2 transition-all text-[12px] font-bold shadow-sm">
            <i class="fas fa-plus-circle"></i> สร้างงานใหม่
        </button>
        <?php endif; ?>
    </div>

    <!-- Grid View -->
    <div id="gridView" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php 
        mysqli_data_seek($result, 0);
        while ($row = mysqli_fetch_assoc($result)):
            $progress = ($row['contract_value'] > 0) ? ($row['collected_money'] / $row['contract_value']) * 100 : 0;
            $pj_id = $row['id'];
            ?>
            <div class="project-item project-card group bg-white rounded-2xl border border-slate-200 p-4 transition-all "
                data-user="<?= $row['created_by'] ?>" data-name="<?= htmlspecialchars($row['project_name']) ?>"
                data-company="<?= $row['supplier_id'] ?>">
                <div class="flex gap-4">

                    <div class="w-[40%] flex flex-col">
                        <?php if ($row['attachment_path']):
                            $ext = strtolower(pathinfo($row['attachment_path'], PATHINFO_EXTENSION));
                            ?>
                            <a href="uploads/projects/<?= $row['project_no'] ?>/<?= $row['attachment_path'] ?>" target="_blank"
                                class="block group/preview">

                                <div
                                    class=" overflow-hidden relative flex items-center justify-center p-2 transition-all duration-300 ">

                                    <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                        <img src="uploads/projects/<?= $row['project_no'] ?>/<?= $row['attachment_path'] ?>"
                                            class="w-full h-full object-contain transition-transform duration-500 "
                                            alt="Image Preview">

                                    <?php elseif ($ext === 'pdf'): ?>
                                        <iframe src="uploads/projects/<?= $row['project_no'] ?>/<?= $row['attachment_path'] ?>#toolbar=0&navpanes=0&view=Fit"
                                            class="w-full h-full border-0 pointer-events-none" frameborder="0">
                                        </iframe>
                                        <div class="absolute inset-0 z-10"></div>

                                    <?php else: ?>
                                        <div class="text-center transition-transform duration-300 ">
                                            <?php
                                            $icon = 'fa-file-alt';
                                            $color = 'text-slate-300';
                                            if (in_array($ext, ['doc', 'docx'])) {
                                                $icon = 'fa-file-word';
                                                $color = 'text-blue-500';
                                            } elseif (in_array($ext, ['xls', 'xlsx'])) {
                                                $icon = 'fa-file-excel';
                                                $color = 'text-emerald-500';
                                            } elseif (in_array($ext, ['zip', 'rar'])) {
                                                $icon = 'fa-file-archive';
                                                $color = 'text-amber-500';
                                            }
                                            ?>
                                            <i class="fas <?= $icon ?> <?= $color ?> text-5xl mb-2 opacity-50"></i>
                                            <p class="text-[12px] font-bold text-slate-800 uppercase"><?= $ext ?> FILE</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php else: ?>
                            <div
                                class="h-[220px] flex flex-col items-center justify-center border-2 border-dashed border-slate-100 rounded-xl bg-slate-50/50">
                                <i class="fas fa-file-circle-exclamation text-slate-200 text-3xl mb-2"></i>
                                <p class="text-[12px] text-slate-800 uppercase font-bold tracking-widest">No Attachment</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="w-[60%] flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start mb-3">

                                <div class="flex flex-wrap gap-1.5 items-center">
                                    <?php if (empty($inspection_only_access) && $row['project_status'] == 'active'): ?>
                                        <button onclick="viewProjectDetails(<?= $pj_id ?>)"
                                            class="group flex items-center gap-1 px-2 py-1 text-[11px] font-bold rounded-lg bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                            เบิกงวด
                                        </button>
                                    <?php endif; ?>

                                    <?php if (empty($inspection_only_access) && $row['project_status'] != 'on_hold'): ?>
                                    <a href="view_milstones.php?ids=<?= $row['all_milestone_ids'] ?>&type=summary"
                                        class="group flex items-center gap-1 px-2 py-1 text-[11px] font-bold rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-100 hover:bg-emerald-600 hover:text-white transition-all shadow-sm">
                                        ดูงวด
                                    </a>
                                    <?php endif; ?>

                                    <a href="<?= !empty($inspection_only_access) ? 'detail_project.php?id=' . $pj_id : (!empty($row['check_work_url']) ? htmlspecialchars($row['check_work_url']) : 'detail_project.php?id=' . $pj_id) ?>"
                                        class="group flex items-center gap-1 px-2 py-1 text-[11px] font-bold rounded-lg bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                        <i class="fas fa-clipboard-check text-xs"></i> ตรวจงาน
                                    </a>

                                    <?php if (!is_viewer()): ?>
                                        <a href="edit_project.php?id=<?= $pj_id ?>"
                                            class="group flex items-center gap-1 px-2 py-1 text-[11px] font-bold rounded-lg bg-amber-50 text-amber-600 border border-amber-100 hover:bg-amber-500 hover:text-white transition-all shadow-sm">
                                            แก้ไข
                                        </a>

                                        <button onclick="deleteProject(<?= $pj_id ?>, '<?= $row['project_name'] ?>')"
                                            class="group flex items-center gap-1 px-2 py-1 text-[11px] font-bold rounded-lg bg-rose-50 text-rose-500 border border-rose-100 hover:bg-rose-600 hover:text-white transition-all shadow-sm">
                                            ลบ
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <a href="<?= !empty($inspection_only_access) ? 'detail_project.php?id=' . $pj_id : 'view_approval.php?id=' . $pj_id ?>" class="block font-bold text-slate-800 text-sm mb-0.5 truncate hover:text-indigo-600 transition-colors" title="<?= $row['project_name'] ?>">
                                <?= $row['project_name'] ?>
                            </a>
                            <p class="text-[12px] text-slate-800 mb-3">
                                <i class="far fa-calendar-alt mr-1"></i> จบงาน:
                                <?= (!empty($row['end_date']) && $row['end_date'] != '0000-00-00') ? date('d/m/Y', strtotime($row['end_date'])) : '-' ?>
                            </p>
                            <div class="space-y-2.5">
                                <div>
                                    <div class="flex justify-between text-[12px] mb-1">
                                        <span class="text-slate-800">การเบิกเงิน</span>
                                        <span class="font-bold text-indigo-600"><?= number_format($progress, 1) ?>%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-indigo-500 h-full transition-all duration-1000"
                                            style="width: <?= $progress ?>%"></div>
                                    </div>
                                </div>

                                <div class="bg-slate-50 rounded-xl p-2.5 space-y-1">
                                    <div class="flex justify-between items-center">
                                        <p class="text-[9px] text-slate-800 uppercase font-medium">สัญญา</p>
                                        <p class="text-[11px] font-bold text-slate-700">
                                            <?= number_format($row['contract_value'] ?? 0, 2) ?>
                                        </p>
                                    </div>
                                    <div class="flex justify-between items-center border-t border-slate-100 pt-1">
                                        <p class="text-[9px] text-slate-800 uppercase font-medium">รับแล้ว</p>
                                        <p class="text-[11px] font-bold text-emerald-600">
                                            <?= number_format($row['collected_money'] ?? 0, 2) ?>
                                        </p>
                                    </div>
                                    
                                    <!-- ไฟล์แนบ (Text Links) -->
                                    <div class="mt-2 pt-1 border-t border-slate-200 space-y-1">
                                        <?php if ($row['attachment_contract']): ?>
                                            <div class="truncate text-[9px]">
                                                <a href="uploads/projects/<?= $row['project_no'] ?>/<?= $row['attachment_contract'] ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                                                    <i class="fas fa-file-contract"></i> <?= htmlspecialchars($row['attachment_contract']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($row['attachment_boq']): ?>
                                            <div class="truncate text-[9px]">
                                                <a href="uploads/projects/<?= $row['project_no'] ?>/<?= $row['attachment_boq'] ?>" target="_blank" class="text-emerald-600 hover:text-emerald-800 flex items-center gap-1">
                                                    <i class="fas fa-file-excel"></i> <?= htmlspecialchars($row['attachment_boq']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Status Buttons Only -->
                                    <?php if (in_array($user_role, ['admin', 'procure']) && !is_viewer()): ?>
                                        <div class="mt-2 w-full">
                                            <?php if ($row['project_status'] == 'on_hold'): ?>
                                                <button onclick="changeProjectStatus(<?= $pj_id ?>, 'active', 'ยืนยันการอนุมัติงาน?')"
                                                    class="w-full py-1.5 text-[11px] font-bold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-all shadow-sm">
                                                    อนุมัติงาน
                                                </button>
                                            <?php elseif ($row['project_status'] == 'active'): ?>
                                                <button onclick="changeProjectStatus(<?= $pj_id ?>, 'completed', 'ยืนยันการปิดงานโครงการนี้?')"
                                                    class="w-full py-1.5 text-[11px] font-bold rounded-lg bg-slate-700 text-white hover:bg-slate-800 transition-all shadow-sm">
                                                    ปิดงาน
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex justify-between items-center">
                            <span
                                class="text-[9px] px-2 py-0.5 rounded-md <?= $row['project_status'] == 'active' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-slate-100 text-slate-800 border-slate-200' ?> font-bold border">
                                <?php
                                if ($row['project_status'] == 'active') {
                                    echo 'กำลังดำเนินการ';
                                } elseif ($row['project_status'] == 'completed') {
                                    echo 'เสร็จสิ้น';
                                } else {
                                    echo 'รอดำเนินการ';
                                }
                                ?>
                            </span>

                            <span
                                class="text-[9px] font-bold px-1.5 py-0.5 bg-indigo-50 text-indigo-600 rounded-md uppercase tracking-wider border border-indigo-100">
                                <?= $row['project_no'] ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- Table View -->
    <div id="tableView" class="hidden overflow-x-auto bg-white rounded-2xl border border-slate-200">
        <table class="w-full text-left border-collapse min-w-[1000px]">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider">โครงการ</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">สถานะ</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">มูลค่าสัญญา</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">รับแล้ว</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider">ความคืบหน้า</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php 
                mysqli_data_seek($result, 0);
                while ($row = mysqli_fetch_assoc($result)):
                    $progress = ($row['contract_value'] > 0) ? ($row['collected_money'] / $row['contract_value']) * 100 : 0;
                    $pj_id = $row['id'];
                    ?>
                    <tr class="project-item project-row hover:bg-slate-50/50 transition-colors"
                        data-user="<?= $row['created_by'] ?>" data-name="<?= htmlspecialchars($row['project_name']) ?>"
                        data-company="<?= $row['supplier_id'] ?>">
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-800 line-clamp-1" title="<?= $row['project_name'] ?>">
                                        <?= $row['project_name'] ?>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-medium uppercase tracking-wider mt-0.5">
                                        <?= $row['project_no'] ?> • จบงาน: <?= (!empty($row['end_date']) && $row['end_date'] != '0000-00-00') ? date('d/m/Y', strtotime($row['end_date'])) : '-' ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 text-center">
                            <span class="inline-block text-[10px] px-2 py-0.5 rounded-md font-bold border <?= $row['project_status'] == 'active' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-slate-100 text-slate-800 border-slate-200' ?>">
                                <?php
                                if ($row['project_status'] == 'active') echo 'กำลังดำเนินการ';
                                elseif ($row['project_status'] == 'completed') echo 'เสร็จสิ้น';
                                else echo 'รอดำเนินการ';
                                ?>
                            </span>
                        </td>
                        <td class="p-4 text-right">
                            <div class="text-sm font-bold text-slate-700"><?= number_format($row['contract_value'] ?? 0, 2) ?></div>
                        </td>
                        <td class="p-4 text-right">
                            <div class="text-sm font-bold text-emerald-600"><?= number_format($row['collected_money'] ?? 0, 2) ?></div>
                        </td>
                        <td class="p-4 min-w-[150px]">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500 rounded-full" style="width: <?= $progress ?>%"></div>
                                </div>
                                <span class="text-[11px] font-bold text-indigo-600 whitespace-nowrap"><?= number_format($progress ?? 0, 1) ?>%</span>
                            </div>
                        </td>
                        <td class="p-4">
                                <div class="flex justify-center items-center gap-1.5">
                                    <?php if (empty($inspection_only_access)): ?><button onclick="viewProjectDetails(<?= $pj_id ?>)"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shadow-sm" title="เบิกงวด">
                                        <i class="fas fa-file-invoice-dollar text-xs"></i>
                                    </button><?php endif; ?>

                                <?php if (empty($inspection_only_access)): ?><a href="view_milstones.php?ids=<?= $row['all_milestone_ids'] ?>&type=summary"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition-all shadow-sm" title="ดูงวด">
                                    <i class="fas fa-eye text-xs"></i>
                                </a><?php endif; ?>

                                <a href="<?= !empty($inspection_only_access) ? 'detail_project.php?id=' . $pj_id : (!empty($row['check_work_url']) ? htmlspecialchars($row['check_work_url']) : 'detail_project.php?id=' . $pj_id) ?>"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shadow-sm" title="ตรวจงาน">
                                    <i class="fas fa-clipboard-check text-xs"></i>
                                </a>

                                <?php if (!is_viewer()): ?>
                                    <a href="edit_project.php?id=<?= $pj_id ?>"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all shadow-sm" title="แก้ไข">
                                        <i class="fas fa-edit text-xs"></i>
                                    </a>
                                    <button onclick="deleteProject(<?= $pj_id ?>, '<?= $row['project_name'] ?>')"
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

<div id="viewModal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-4xl max-h-[90vh] overflow-hidden  animate-fade-in-up">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-xl font-bold text-slate-800" id="modalTitle">รายละเอียดโครงการ</h3>
            <button onclick="closeModal()"
                class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-white  text-slate-800 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto" id="modalContent" style="max-height: calc(90vh - 80px);">
            <div class="text-center py-10">
                <i class="fas fa-circle-notch fa-spin text-3xl text-indigo-500"></i>
                <p class="mt-2 text-slate-800">กำลังโหลดข้อมูล...</p>
            </div>
        </div>
    </div>
</div>
<style>
    .swal2-container { z-index: 99999 !important; }
</style>
<script>
    function setViewMode(mode) {
        localStorage.setItem('project_view_mode', mode);
        updateViewUI();
    }

    function updateViewUI() {
        const mode = localStorage.getItem('project_view_mode') || 'grid';
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

    function viewProjectDetails(id) {
        // แทนที่จะเปิด Modal เราจะย้ายหน้าไปที่ไฟล์รายละเอียดโครงการแทน
        // โดยส่ง ID ผ่าน URL Parameter ครับจาร
        window.location.href = 'detail_project.php?id=' + id;
    }

    function closeModal() {
        $('#viewModal').addClass('hidden').removeClass('flex');
    }

    // ปิด Modal เมื่อคลิกพื้นหลัง
    $(window).on('click', function (e) {
        if ($(e.target).is('#viewModal')) closeModal();
    });

    function deleteProject(id, name) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: `คุณกำลังจะลบโครงการ "${name}" และข้อมูลที่เกี่ยวข้องทั้งหมด (รวมถึงไฟล์แนบ)`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก',

            heightAuto: false // กันหน้าดีด
        }).then((result) => {
            if (result.isConfirmed) {
                // ส่งไปที่ไฟล์ลบ
                window.location.href = `api/delete_project.php?id=${id}`;
            }
        })
    }

    function togglePreview() {
        const container = document.getElementById('preview_container');
        if (container.classList.contains('hidden')) {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }
    function filterProjects() {
        const search = document.getElementById('projectSearch').value.toLowerCase();
        const user = document.getElementById('userFilter').value;
        const company = document.getElementById('companyFilter').value;
        const items = document.querySelectorAll('.project-item');

        items.forEach(item => {
            const name = item.getAttribute('data-name').toLowerCase();
            const userId = item.getAttribute('data-user');
            const companyId = item.getAttribute('data-company');

            const matchSearch = name.includes(search);
            const matchUser = (user === "" || userId === user);

            let matchCompany = false;
            if (company === "") {
                matchCompany = true;
            } else if (company === "none") {
                matchCompany = (companyId === "" || companyId === "0" || companyId === "null" || companyId === null);
            } else {
                matchCompany = (companyId === company);
            }

            if (matchSearch && matchUser && matchCompany) {
                item.style.display = "";
            } else {
                item.style.display = "none";
            }
        });
    }
    function resetFilters() {
        document.getElementById('projectSearch').value = '';
        document.getElementById('userFilter').value = '';
        document.getElementById('companyFilter').value = '';
        filterProjects();
    }

    function changeProjectStatus(id, targetStatus, confirmText) {
        Swal.fire({
            title: confirmText,
            text: targetStatus === 'completed' ? "โครงการที่ปิดแล้วจะถือว่าเสร็จสมบูรณ์" : "เมื่ออนุมัติแล้ว สถานะจะเปลี่ยนเป็น 'กำลังดำเนินการ'",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: targetStatus === 'active' ? '#10b981' : '#64748b',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก',
            heightAuto: false,
            width: '400px'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('project_id', id);
                fd.append('status', targetStatus);

                fetch('api/update_project_status.php', {
                    method: 'POST',
                    body: fd
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({title: 'สำเร็จ!', text: 'อัปเดตสถานะเรียบร้อยแล้ว', icon: 'success', heightAuto: false, width: '400px'})
                        .then(() => location.reload());
                    } else {
                        Swal.fire({title: 'ผิดพลาด!', text: data.message || 'ไม่สามารถอัปเดตได้', icon: 'error', heightAuto: false, width: '400px'});
                    }
                });
            }
        })
    }

    document.addEventListener("DOMContentLoaded", function () {
        updateViewUI();
        filterProjects();
    });
</script>

<?php include('footer.php'); ?>
