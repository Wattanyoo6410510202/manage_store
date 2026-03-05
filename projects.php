<?php
require_once 'config.php';
include('header.php');

// ดึงข้อมูลโปรเจกต์
$sql = "SELECT p.*, 
        (SELECT SUM(net_amount) FROM project_milestones WHERE project_id = p.id AND status = 'paid') as collected_money
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
            <div class="group bg-white rounded-2xl border border-slate-200 p-5  transition-all">
                <div class="flex justify-between items-start mb-4">
                    <span
                        class="text-[10px] font-bold px-2 py-1 bg-indigo-50 text-indigo-600 rounded-md uppercase tracking-wider"><?= $row['project_no'] ?></span>
                    <div class="flex gap-2">
                        <button onclick="viewProjectDetails(<?= $pj_id ?>)"
                            class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:bg-indigo-600 hover:text-white transition-all"
                            title="ดูด่วน">
                            <i class="fas fa-search-plus text-xs"></i>
                        </button>

                        <a href="view_project.php?id=<?= $pj_id ?>"
                            class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-emerald-500 hover:bg-emerald-600 hover:text-white transition-all"
                            title="ดูหน้าเต็ม/พิมพ์เอกสาร">
                            <i class="fas fa-eye text-xs"></i>
                        </a>

                        <a href="edit_project.php?id=<?= $pj_id ?>"
                            class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-amber-500 hover:bg-amber-500 hover:text-white transition-all"
                            title="แก้ไขโครงการ">
                            <i class="fas fa-edit text-xs"></i>
                        </a>

                        <button onclick="deleteProject(<?= $pj_id ?>, '<?= $row['project_name'] ?>')"
                            class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-rose-400 hover:bg-rose-600 hover:text-white transition-all"
                            title="ลบโครงการ">
                            <i class="fas fa-trash-alt text-xs"></i>
                        </button>
                    </div>
                </div>

                <h3 class="font-bold text-slate-800 mb-1 truncate"><?= $row['project_name'] ?></h3>
                <p class="text-xs text-slate-400 mb-4"><i class="far fa-calendar-alt mr-1"></i> จบงาน:
                    <?= date('d/m/Y', strtotime($row['end_date'])) ?>
                </p>

                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-500">การเบิกเงิน</span>
                            <span class="font-bold text-indigo-600"><?= number_format($progress, 1) ?>%</span>
                        </div>
                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div class="bg-indigo-500 h-full transition-all duration-1000" style="width: <?= $progress ?>%">
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-3 flex justify-between items-center">
                        <div>
                            <p class="text-[10px] text-slate-400 uppercase">ยอดสัญญา</p>
                            <p class="text-sm font-bold text-slate-700"><?= number_format($row['contract_value'], 2) ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-slate-400 uppercase">รับแล้ว</p>
                            <p class="text-sm font-bold text-emerald-600"><?= number_format($row['collected_money'], 2) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-100 flex justify-between items-center">
                    <span
                        class="text-[10px] px-2 py-1 rounded-lg <?= $row['project_status'] == 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' ?> font-bold uppercase">
                        <?= $row['project_status'] ?>
                    </span>
                    <div class="flex gap-1">
                        <?php if ($row['attachment_path']): ?>
                            <a href="uploads/projects/<?= $row['attachment_path'] ?>" target="_blank"
                                class="text-slate-400 hover:text-indigo-600 text-sm">
                                <i class="fas fa-paperclip mr-1"></i> ไฟล์ประกอบ
                            </a>
                        <?php endif; ?>
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
</script>

<?php include('footer.php'); ?>