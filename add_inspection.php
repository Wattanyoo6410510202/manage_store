<?php
require_once 'config.php';
include('header.php');

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$milestone_id = isset($_GET['milestone_id']) ? intval($_GET['milestone_id']) : 0;

if (!$project_id || !$milestone_id) {
    die("<div class='p-10 text-center font-bold text-rose-500'>ข้อมูลไม่ครบถ้วนครับจาร!</div>");
}

// ดึงข้อมูลโปรเจกต์และงวดงานเพื่อแสดงหัวข้อ
$pj_res = mysqli_query($conn, "SELECT project_name FROM projects WHERE id = $project_id");
$pj = mysqli_fetch_assoc($pj_res);

$ms_res = mysqli_query($conn, "SELECT milestone_name FROM project_milestones WHERE id = $milestone_id");
$ms = mysqli_fetch_assoc($ms_res);

if (!$pj || !$ms) {
    die("<div class='p-10 text-center font-bold text-rose-500'>ไม่พบข้อมูลโครงการหรืองวดงานครับ!</div>");
}

// ตรวจสอบว่าเคยตรวจงานไปหรือยัง
$check_sql = "SELECT * FROM milestone_inspections WHERE milestone_id = $milestone_id";
$check_res = mysqli_query($conn, $check_sql);
$existing_inspection = mysqli_fetch_assoc($check_res);

$mode = $existing_inspection ? 'edit' : 'add';
$data = $existing_inspection ?: [];
?>

<div class="max-w-4xl mx-auto pb-20">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-2xl font-black text-slate-800">แบบฟอร์มตรวจรับงาน (Inspection Form)</h2>
            <p class="text-slate-500 mt-1">
                โครงการ: <span class="text-indigo-600 font-bold"><?= htmlspecialchars($pj['project_name']) ?></span> | 
                งวดงาน: <span class="text-emerald-600 font-bold"><?= htmlspecialchars($ms['milestone_name']) ?></span>
            </p>
        </div>
        <button onclick="history.back()" class="bg-slate-100 text-slate-600 px-4 py-2 rounded-xl hover:bg-slate-200 transition-all font-bold">
            <i class="fas fa-arrow-left mr-1"></i> ย้อนกลับ
        </button>
    </div>

    <form id="inspectionForm" class="space-y-6" enctype="multipart/form-data">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <input type="hidden" name="milestone_id" value="<?= $milestone_id ?>">
        <?php if ($mode === 'edit'): ?>
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
        <?php endif; ?>

        <!-- ส่วนที่ 1: ข้อมูลเบื้องต้น -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2 flex items-center gap-2">
                <i class="fas fa-info-circle text-indigo-500"></i> ข้อมูลเบื้องต้น
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">วันที่ตรวจรับงาน *</label>
                    <input type="date" name="inspection_date" required 
                           value="<?= $data['inspection_date'] ?? date('Y-m-d') ?>"
                           class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">แนบรูปภาพหรือเอกสาร (PDF/JPG/PNG)</label>
                    <input type="file" name="inspection_attachment" accept="image/*,.pdf"
                           class="w-full bg-slate-50 border-none rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    <?php if (!empty($data['inspection_attachment'])): ?>
                        <p class="mt-2 text-xs text-indigo-600 font-bold">
                            <i class="fas fa-file-alt mr-1"></i> ไฟล์เดิม: 
                            <a href="uploads/inspections/<?= $data['inspection_attachment'] ?>" target="_blank" class="underline">
                                <?= $data['inspection_attachment'] ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ส่วนที่ 2: รายการตรวจสอบ (Checklist) -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2 flex items-center gap-2">
                <i class="fas fa-tasks text-emerald-500"></i> รายการตรวจสอบ (Checklist)
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
                <!-- หมวดงานโครงสร้างและสถาปัตย์ -->
                <div class="space-y-3">
                    <p class="font-bold text-indigo-600 text-sm mb-2 uppercase tracking-wider">งานโครงสร้างและสถาปัตย์</p>
                    <?php
                    $checks = [
                        'is_boq_complete' => 'งานดำเนินการครบตาม BOQ',
                        'is_drawing_match' => 'งานเป็นไปตามแบบ (Drawing)',
                        'is_spec_match' => 'วัสดุเป็นไปตาม Spec',
                        'is_quantity_ok' => 'ปริมาณงานถูกต้อง',
                        'is_on_schedule' => 'งานแล้วเสร็จตามงวด',
                    ];
                    foreach ($checks as $key => $label):
                        $checked = ($data[$key] ?? 0) == 1 ? 'checked' : '';
                    ?>
                    <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-100 transition-all">
                        <input type="checkbox" name="<?= $key ?>" value="1" <?= $checked ?> class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-slate-700"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- หมวดระบบวิศวกรรม -->
                <div class="space-y-3">
                    <p class="font-bold text-emerald-600 text-sm mb-2 uppercase tracking-wider">ระบบวิศวกรรม</p>
                    <?php
                    $checks = [
                        'is_electrical_ok' => 'ระบบไฟฟ้าใช้งานได้',
                        'is_plumbing_ok' => 'ระบบประปาไม่มีการรั่วซึม',
                        'is_drainage_ok' => 'ระบบระบายน้ำทำงานปกติ',
                        'is_hvac_ok' => 'ระบบปรับอากาศ (ถ้ามี)',
                    ];
                    foreach ($checks as $key => $label):
                        $checked = ($data[$key] ?? 0) == 1 ? 'checked' : '';
                    ?>
                    <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-100 transition-all">
                        <input type="checkbox" name="<?= $key ?>" value="1" <?= $checked ?> class="w-5 h-5 rounded text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-slate-700"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                    <div class="pt-1">
                        <label class="block text-xs font-bold text-slate-500 mb-1">ระบุระบบอื่นๆ (ถ้ามี)</label>
                        <input type="text" name="other_system_note" value="<?= htmlspecialchars($data['other_system_note'] ?? '') ?>"
                               placeholder="เช่น ระบบแจ้งเหตุเพลิงไหม้"
                               class="w-full bg-slate-100 border-none rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <!-- หมวดงานความเรียบร้อยและปลอดภัย (ข้ามแถว) -->
                <div class="space-y-3 mt-4">
                    <p class="font-bold text-amber-600 text-sm mb-2 uppercase tracking-wider">ความเรียบร้อยและปลอดภัย</p>
                    <?php
                    $checks = [
                        'is_surface_ok' => 'ผิวงานเรียบร้อย ไม่มีตำหนิ',
                        'is_paint_ok' => 'งานสี/ผิวตกแต่งเรียบร้อย',
                        'is_cleaned' => 'ไม่มีเศษวัสดุ/ทำความสะอาดแล้ว',
                        'is_defect_fixed' => 'งานแก้ไข Defect ครบถ้วน',
                        'is_safety_ok' => 'ความปลอดภัยหน้างานเรียบร้อย',
                    ];
                    foreach ($checks as $key => $label):
                        $checked = ($data[$key] ?? 0) == 1 ? 'checked' : '';
                    ?>
                    <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-100 transition-all">
                        <input type="checkbox" name="<?= $key ?>" value="1" <?= $checked ?> class="w-5 h-5 rounded text-amber-600 focus:ring-amber-500">
                        <span class="text-sm font-medium text-slate-700"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ส่วนที่ 3: สรุปผลและรายการแก้ไข -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2 flex items-center gap-2">
                <i class="fas fa-file-contract text-rose-500"></i> สรุปผลและรายการแก้ไข
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">ผลการตรวจรับ *</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <?php
                        $statuses = [
                            'pass' => ['label' => 'ผ่านการตรวจรับ', 'color' => 'peer-checked:bg-emerald-500 peer-checked:text-white bg-emerald-50 text-emerald-600 border-emerald-100'],
                            'conditional_pass' => ['label' => 'ผ่านแบบมีเงื่อนไข', 'color' => 'peer-checked:bg-amber-500 peer-checked:text-white bg-amber-50 text-amber-600 border-amber-100'],
                            'fail' => ['label' => 'ไม่ผ่านการตรวจรับ', 'color' => 'peer-checked:bg-rose-500 peer-checked:text-white bg-rose-50 text-rose-600 border-rose-100'],
                        ];
                        $current_status = $data['result_status'] ?? 'fail';
                        foreach ($statuses as $val => $cfg):
                            $checked = $current_status === $val ? 'checked' : '';
                        ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="result_status" value="<?= $val ?>" <?= $checked ?> class="hidden peer">
                            <div class="p-4 rounded-2xl border text-center font-bold text-sm transition-all <?= $cfg['color'] ?> shadow-sm">
                                <?= $cfg['label'] ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Punch List (รายการที่ต้องแก้ไข)</label>
                    <textarea name="punch_list" rows="4" placeholder="ระบุรายการที่ต้องแก้ไข (ถ้ามี)..."
                              class="w-full bg-slate-50 border-none rounded-2xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none text-sm"><?= htmlspecialchars($data['punch_list'] ?? '') ?></textarea>
                </div>

                <div class="w-full md:w-1/3">
                    <label class="block text-sm font-bold text-slate-700 mb-2">กำหนดเสร็จภายใน (วัน)</label>
                    <div class="relative">
                        <input type="number" name="fix_within_days" value="<?= $data['fix_within_days'] ?? '' ?>"
                               class="w-full bg-slate-50 border-none rounded-xl pl-4 pr-12 py-3 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-slate-400 font-bold">วัน</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ส่วนที่ 4: รายชื่อคณะกรรมการ -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2 flex items-center gap-2">
                <i class="fas fa-users text-indigo-500"></i> คณะกรรมการตรวจรับ
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">ผู้ตรวจรับ 1</label>
                    <input type="text" name="inspector_name_1" value="<?= htmlspecialchars($data['inspector_name_1'] ?? '') ?>"
                           class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">ผู้ตรวจรับ 2</label>
                    <input type="text" name="inspector_name_2" value="<?= htmlspecialchars($data['inspector_name_2'] ?? '') ?>"
                           class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">เจ้าหน้าที่จัดซื้อ</label>
                    <input type="text" name="procurement_officer" value="<?= htmlspecialchars($data['procurement_officer'] ?? '') ?>"
                           class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                </div>
            </div>
            
            <div class="mt-6 p-4 bg-indigo-50 rounded-2xl border border-indigo-100 flex items-center gap-3">
                <input type="checkbox" name="is_md_approved" value="1" <?= ($data['is_md_approved'] ?? 0) == 1 ? 'checked' : '' ?>
                       class="w-6 h-6 rounded text-indigo-600 focus:ring-indigo-500">
                <div>
                    <p class="font-bold text-indigo-900 text-sm">อนุมัติโดยกรรมการผู้จัดการ (MD)</p>
                    <p class="text-indigo-600 text-xs">ติ๊กถูกเพื่อยืนยันว่าผ่านการอนุมัติระดับบริหารแล้ว</p>
                </div>
            </div>
        </div>

        <div class="flex gap-4">
            <button type="submit" class="flex-1 bg-indigo-600 text-white font-black py-4 rounded-2xl shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 transition-all text-lg">
                <i class="fas fa-save mr-2"></i> <?= $mode === 'edit' ? 'บันทึกการแก้ไข' : 'บันทึกผลการตรวจงาน' ?>
            </button>
            <button type="button" onclick="history.back()" class="px-8 bg-slate-100 text-slate-600 font-bold py-4 rounded-2xl hover:bg-slate-200 transition-all">
                ยกเลิก
            </button>
        </div>
    </form>
</div>

<script>
$('#inspectionForm').on('submit', function(e) {
    e.preventDefault();
    
    Swal.fire({
        title: 'ยืนยันการบันทึก?',
        text: "คุณตรวจสอบข้อมูลครบถ้วนแล้วใช่หรือไม่",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'ใช่, บันทึกเลย',
        cancelButtonText: 'ตรวจสอบอีกครั้ง',
        heightAuto: false
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'กำลังบันทึก...',
                allowOutsideClick: false,
                heightAuto: false,
                didOpen: () => { Swal.showLoading(); }
            });

            const formData = new FormData(this);
            
            $.ajax({
                url: 'api/save_inspection.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            title: 'สำเร็จ!',
                            text: res.message,
                            icon: 'success',
                            heightAuto: false
                        }).then(() => {
                            window.location.href = 'detail_project.php?id=<?= $project_id ?>';
                        });
                    } else {
                        Swal.fire({
                            title: 'เกิดข้อผิดพลาด',
                            text: res.message,
                            icon: 'error',
                            heightAuto: false
                        });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Error', text: 'ไม่สามารถเชื่อมต่อ API ได้ครับจาร!', icon: 'error', heightAuto: false });
                }
            });
        }
    });
});
</script>

<?php include('footer.php'); ?>