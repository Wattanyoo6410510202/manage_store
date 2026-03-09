<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

// ดึงข้อมูลโปรเจกต์
$sql = "SELECT p.*, 
        (SELECT SUM(net_amount) FROM project_milestones WHERE project_id = p.id AND status = 'paid') as collected_money,
        -- เพิ่มบรรทัดนี้ครับจาร เพื่อรวบ ID งวดงานทั้งหมด
        (SELECT GROUP_CONCAT(id) FROM project_milestones WHERE project_id = p.id) as all_milestone_ids
        FROM projects p ORDER BY p.id DESC";
$result = mysqli_query($conn, $sql);
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">บริหารโครงการ & งวดงาน</h2>
            <p class="text-slate-500 text-sm">คลิกที่การ์ดเพื่อดูรายละเอียดเอกสารที่เชื่อมโยง</p>
        </div>
        <button onclick="location.href='add_project.php'"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl flex items-center gap-2 transition-all ">
            <i class="fas fa-plus-circle"></i> สร้างงานใหม่
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php while ($row = mysqli_fetch_assoc($result)):
            $progress = ($row['contract_value'] > 0) ? ($row['collected_money'] / $row['contract_value']) * 100 : 0;
            $pj_id = $row['id'];
            ?>
        <div class="group bg-white rounded-2xl border border-slate-200 p-4 transition-all ">
            <div class="flex gap-4">

                <div class="w-[40%] flex flex-col">
                    <?php if ($row['attachment_path']):
                            $ext = strtolower(pathinfo($row['attachment_path'], PATHINFO_EXTENSION));
                            ?>
                    <a href="uploads/projects/<?= $row['attachment_path'] ?>" target="_blank"
                        class="block group/preview">

                        <div
                            class=" overflow-hidden relative flex items-center justify-center p-2 transition-all duration-300 ">

                            <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                            <img src="uploads/projects/<?= $row['attachment_path'] ?>"
                                class="w-full h-full object-contain transition-transform duration-500 "
                                alt="Image Preview">

                            <?php elseif ($ext === 'pdf'): ?>
                            <iframe src="uploads/projects/<?= $row['attachment_path'] ?>#toolbar=0&navpanes=0&view=Fit"
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
                                <p class="text-[10px] font-bold text-slate-400 uppercase"><?= $ext ?> FILE</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php else: ?>
                    <div
                        class="h-[220px] flex flex-col items-center justify-center border-2 border-dashed border-slate-100 rounded-xl bg-slate-50/50">
                        <i class="fas fa-file-circle-exclamation text-slate-200 text-3xl mb-2"></i>
                        <p class="text-[10px] text-slate-400 uppercase font-bold tracking-widest">No Attachment</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="w-[60%] flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start mb-3">

                            <div class="flex gap-2 items-center">
                                <button onclick="viewProjectDetails(<?= $pj_id ?>)"
                                    class="group flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold rounded-lg bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all duration-200 shadow-sm active:transform active:scale-95">
                                    <i
                                        class="fas fa-file-invoice-dollar text-[9px] opacity-70 group-hover:opacity-100"></i>
                                    เบิกงวด
                                </button>

                                <a href="view_milstones.php?ids=<?= $row['all_milestone_ids'] ?>"
                                    class="group flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-100 hover:bg-emerald-600 hover:text-white hover:border-emerald-600 transition-all duration-200 shadow-sm active:transform active:scale-95">
                                    <i class="fas fa-eye text-[9px] opacity-70 group-hover:opacity-100"></i>
                                    ดูงวด
                                </a>

                                <a href="edit_project.php?id=<?= $pj_id ?>"
                                    class="group flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold rounded-lg bg-amber-50 text-amber-600 border border-amber-100 hover:bg-amber-500 hover:text-white hover:border-amber-500 transition-all duration-200 shadow-sm active:transform active:scale-95">
                                    <i class="fas fa-edit text-[9px] opacity-70 group-hover:opacity-100"></i>
                                    แก้ไข
                                </a>

                                <button onclick="deleteProject(<?= $pj_id ?>, '<?= $row['project_name'] ?>')"
                                    class="group flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold rounded-lg bg-rose-50 text-rose-500 border border-rose-100 hover:bg-rose-600 hover:text-white hover:border-rose-600 transition-all duration-200 shadow-sm active:transform active:scale-95">
                                    <i class="fas fa-trash-alt text-[9px] opacity-70 group-hover:opacity-100"></i>
                                    ลบ
                                </button>
                            </div>
                        </div>

                        <h3 class="font-bold text-slate-800 text-sm mb-0.5 truncate"
                            title="<?= $row['project_name'] ?>"><?= $row['project_name'] ?></h3>
                        <p class="text-[10px] text-slate-400 mb-3">
                            <i class="far fa-calendar-alt mr-1"></i> จบงาน:
                            <?= (!empty($row['end_date']) && $row['end_date'] != '0000-00-00') ? date('d/m/Y', strtotime($row['end_date'])) : '-' ?>
                        </p>
                        <div class="space-y-2.5">
                            <div>
                                <div class="flex justify-between text-[10px] mb-1">
                                    <span class="text-slate-500">การเบิกเงิน</span>
                                    <span class="font-bold text-indigo-600"><?= number_format($progress, 1) ?>%</span>
                                </div>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-indigo-500 h-full transition-all duration-1000"
                                        style="width: <?= $progress ?>%"></div>
                                </div>
                            </div>

                            <div class="bg-slate-50 rounded-xl p-2.5 space-y-1">
                                <div class="flex justify-between items-center">
                                    <p class="text-[9px] text-slate-400 uppercase font-medium">สัญญา</p>
                                    <p class="text-[11px] font-bold text-slate-700">
                                        <?= number_format($row['contract_value'], 2) ?>
                                    </p>
                                </div>
                                <div class="flex justify-between items-center border-t border-slate-100 pt-1">
                                    <p class="text-[9px] text-slate-400 uppercase font-medium">รับแล้ว</p>
                                    <p class="text-[11px] font-bold text-emerald-600">
                                        <?= number_format($row['collected_money'], 2) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex justify-between items-center">
                        <span
                            class="text-[9px] px-2 py-0.5 rounded-md <?= $row['project_status'] == 'active' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' ?> font-bold border">
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
</div>

<div id="viewModal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-4xl max-h-[90vh] overflow-hidden  animate-fade-in-up">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-xl font-bold text-slate-800" id="modalTitle">รายละเอียดโครงการ</h3>
            <button onclick="closeModal()"
                class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-white  text-slate-400 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto" id="modalContent" style="max-height: calc(90vh - 80px);">
            <div class="text-center py-10">
                <i class="fas fa-circle-notch fa-spin text-3xl text-indigo-500"></i>
                <p class="mt-2 text-slate-500">กำลังโหลดข้อมูล...</p>
            </div>
        </div>
    </div>
</div>

<script>
function viewProjectDetails(id) {
    // แทนที่จะเปิด Modal เราจะย้ายหน้าไปที่ไฟล์รายละเอียดโครงการแทน
    // โดยส่ง ID ผ่าน URL Parameter ครับจาร
    window.location.href = 'detail_project.php?id=' + id;
}

function closeModal() {
    $('#viewModal').addClass('hidden').removeClass('flex');
}

// ปิด Modal เมื่อคลิกพื้นหลัง
$(window).on('click', function(e) {
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
</script>

<?php include('footer.php'); ?>