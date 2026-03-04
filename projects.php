<?php
require_once 'config.php';
include('header.php');

// ดึงข้อมูลโปรเจกต์
$sql = "SELECT p.*, 
        (SELECT SUM(net_amount) FROM project_milestones WHERE project_id = p.id AND status = 'paid') as collected_money
        FROM projects p ORDER BY p.id DESC";
$result = mysqli_query($conn, $sql);
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">บริหารโครงการ & งวดงาน</h2>
            <p class="text-slate-500 text-sm">คลิกที่การ์ดเพื่อดูรายละเอียดเอกสารที่เชื่อมโยง</p>
        </div>
        <button onclick="location.href='add_project.php'"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl flex items-center gap-2 transition-all shadow-lg shadow-indigo-200">
            <i class="fas fa-plus-circle"></i> สร้างโครงการใหม่
        </button>
        <button type="button" onclick="randomProject()"
            class="bg-amber-100 hover:bg-amber-200 text-amber-700 px-5 py-2.5 rounded-xl font-bold border border-amber-200 transition-all">
            <i class="fas fa-dice mr-2"></i> สุ่มข้อมูล & บันทึก
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php while ($row = mysqli_fetch_assoc($result)):
            $progress = ($row['contract_value'] > 0) ? ($row['collected_money'] / $row['contract_value']) * 100 : 0;
            $pj_id = $row['id'];
            ?>
            <div class="group bg-white rounded-2xl shadow-sm border border-slate-200 p-5 hover:shadow-md transition-all">
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
                                <i class="fas fa-paperclip mr-1"></i> ไฟล์สัญญา
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
    <div class="bg-white rounded-3xl w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl animate-fade-in-up">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-xl font-bold text-slate-800" id="modalTitle">รายละเอียดโครงการ</h3>
            <button onclick="closeModal()"
                class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-white hover:shadow-sm text-slate-400 transition-all">
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
        $('#viewModal').removeClass('hidden').addClass('flex');

        // โหลดข้อมูลจากไฟล์ API (เดี๋ยวจารต้องสร้างไฟล์ get_project_view.php ตามข้างล่างนะ)
        $.ajax({
            url: 'api/get_project_view.php',
            type: 'GET',
            data: { id: id },
            success: function (response) {
                $('#modalContent').html(response);
            },
            error: function () {
                $('#modalContent').html('<p class="text-red-500 text-center">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>');
            }
        });
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

    // ฟังก์ชันสุ่มและบันทึกทันที (สำหรับหน้า List)
    function randomProject() {
        const names = ["ปรับปรุงอาคารสำนักงาน A", "ติดตั้งระบบ Solar Cell", "วางระบบ Network", "งานตกแต่งภายใน", "ซ่อมบำรุงระบบประปา"];
        const contractors = ["บริษัท สุเทพ เอ็นจิเนียริ่ง จำกัด", "หจก. รวมช่างรุ่งเรือง", "บจก. เทคโนโลยีล้ำสมัย"];
        const banks = ["กสิกรไทย", "ไทยพาณิชย์", "กรุงเทพ", "กรุงไทย"];

        // สร้าง FormData จำลองการส่งจาก Form
        const formData = new FormData();
        formData.append('project_no', 'PJ-' + Math.floor(1000 + Math.random() * 9000));
        formData.append('project_name', names[Math.floor(Math.random() * names.length)] + " (สุ่ม " + Math.floor(Math.random() * 100) + ")");
        formData.append('customer_id', 1); // สมมติว่ามีลูกค้า ID 1 อยู่ในระบบ
        formData.append('contractor_name', contractors[Math.floor(Math.random() * contractors.length)]);
        formData.append('contract_value', (Math.floor(Math.random() * 20) + 1) * 50000);
        formData.append('start_date', new Date().toISOString().split('T')[0]);
        formData.append('end_date', new Date(Date.now() + 15552000000).toISOString().split('T')[0]); // อีก 6 เดือน
        formData.append('bank_name', banks[Math.floor(Math.random() * banks.length)]);
        formData.append('bank_account_no', Math.floor(Math.random() * 9000000000) + 1000000000);
        formData.append('bank_account_name', "บัญชีบริษัททดสอบ");
        formData.append('project_status', 'active');

        if (confirm("ต้องการสุ่มข้อมูลโครงการใหม่และบันทึกลงฐานข้อมูลทันทีหรือไม่?")) {
            fetch('api/save_project.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    // หลังจากบันทึกเสร็จ ให้ Refresh หน้าเพื่อดูผลลัพธ์
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('เกิดข้อผิดพลาดในการบันทึก');
                });
        }
    }
</script>

<?php include('footer.php'); ?>