<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'inspection_workflow.php';

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$milestone_id = isset($_GET['milestone_id']) ? intval($_GET['milestone_id']) : 0;

// New contract-based inspections use the authenticated workflow so that
// assigned inspectors, procurement, MD and GMACC follow the same audit trail.
if ($project_id > 0 && $milestone_id > 0) {
    $legacyInspection = inspection_fetch_one($conn, "SELECT id FROM milestone_inspections WHERE milestone_id = ? LIMIT 1", 'i', [$milestone_id]);
    $dynamicChecklist = inspection_fetch_one($conn, "SELECT id FROM inspection_checklists WHERE milestone_id = ? AND status = 'active' LIMIT 1", 'i', [$milestone_id]);
    if (!$legacyInspection && $dynamicChecklist) {
        header("Location: add_inspection.php?project_id={$project_id}&milestone_id={$milestone_id}");
        exit;
    }
}

// จัดการการ Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: view_inspection_public.php?project_id=$project_id&milestone_id=$milestone_id");
    exit;
}

// รายชื่อผู้ที่มีสิทธิ์ตรวจงาน (บุคคลที่ 2 เพิ่มตรงนี้)
$inspectors = [
    'pao' => ['pass' => '1234', 'name' => 'มณต์ชัย (ช่างเป่า)', 'step' => 1],
    'jane' => ['pass' => '1234', 'name' => 'เจนวิทย์ คชศิริ', 'step' => 2]
];

if (!$project_id || !$milestone_id) {
    die("<div class='p-10 text-center font-bold text-rose-500'>ข้อมูลไม่ครบถ้วนครับจาร!</div>");
}

// จัดการการ Login
if (isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    
    if (isset($inspectors[$user]) && $inspectors[$user]['pass'] === $pass) {
        $_SESSION['ins_auth'] = true;
        $_SESSION['auth_fullname'] = $inspectors[$user]['name'];
        $_SESSION['auth_step'] = $inspectors[$user]['step'];
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['ins_auth']) || $_SESSION['ins_auth'] !== true) {
    // ล้าง session เมื่อไม่ได้ login หรือโหลดหน้าใหม่
    session_unset();
    session_destroy();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <title>เข้าสู่ระบบตรวจรับงาน</title>
    <style>body { font-family: 'Sarabun', sans-serif; }</style>
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-sm border border-slate-200">
        <div class="text-center mb-8">
            <div class="bg-indigo-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 text-indigo-600">
                <i class="fas fa-user-check text-2xl"></i>
            </div>
            <h2 class="text-2xl font-black text-slate-800">เข้าสู่ระบบตรวจงาน</h2>
            <p class="text-slate-500 text-sm">สำหรับคณะกรรมการตรวจรับงาน</p>
        </div>
        
        <?php if(isset($error)) echo "<div class='bg-rose-50 text-rose-600 p-3 rounded-xl text-sm mb-4 text-center font-bold border border-rose-100'>$error</div>"; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1 ml-1 uppercase">Username</label>
                <input type="text" name="username" placeholder="ระบุชื่อผู้ใช้งาน" required 
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1 ml-1 uppercase">Password</label>
                <input type="password" name="password" placeholder="ระบุรหัสผ่าน" required 
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
            </div>
            <button type="submit" name="login" class="w-full bg-indigo-600 text-white font-black py-4 rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-600/20 transition-all text-lg">
                เข้าสู่ระบบตรวจงาน
            </button>
        </form>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
<?php
    exit;
}

// ส่วนเนื้อหาตรวจงาน
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

// ถ้ามีการ Login แล้ว ให้ระบุชื่อตามสิทธิ์
if (isset($_SESSION['auth_fullname'])) {
    if ($_SESSION['auth_step'] == 1) {
        $data['inspector_name_1'] = $_SESSION['auth_fullname'];
    } else if ($_SESSION['auth_step'] == 2) {
        $data['inspector_name_2'] = $_SESSION['auth_fullname'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจงานออนไลน์</title>

    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="shopping-cart.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Sarabun', sans-serif; }
    </style>
</head>

<body class="bg-slate-50 font-sarabun min-h-screen">
<div class="max-w-4xl mx-auto pb-20 p-4">
    <?php if ($mode === 'edit'): ?>
        <div class="bg-gradient-to-r from-amber-50 to-amber-100 border border-amber-200 text-amber-800 p-5 rounded-2xl mb-6 shadow-sm flex items-start gap-4">
            <div class="bg-amber-500 text-white p-2 rounded-xl">
                <i class="fas fa-check-double text-lg"></i>
            </div>
            <div>
                <p class="font-black text-amber-900">รายการนี้มีข้อมูลเดิมอยู่แล้ว</p>
                <p class="text-xs md:text-sm text-amber-700 mt-0.5">คุณสามารถปรับปรุงข้อมูลและบันทึกความคืบหน้าการตรวจรับได้</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-2xl font-black text-slate-800">แบบฟอร์มตรวจรับงาน (Public)</h2>
            <div class="text-slate-500 mt-1">
                <p>โครงการ: <span class="text-indigo-600 font-bold"><?= htmlspecialchars($pj['project_name']) ?></span> | 
                งวดงาน: <span class="text-emerald-600 font-bold"><?= htmlspecialchars($ms['milestone_name']) ?></span></p>
                <p class="text-xs mt-1">
                    <i class="fas fa-calendar-alt mr-1"></i> ระยะเวลาโครงการ: 
                    <span class="font-bold text-slate-700">
                        <?= (!empty($pj['start_date']) ? date('d/m/Y', strtotime($pj['start_date'])) : '-') ?> 
                        ถึง 
                        <?= (!empty($pj['end_date']) ? date('d/m/Y', strtotime($pj['end_date'])) : '-') ?>
                    </span>
                </p>
            </div>
        </div>
        <div class="no-print flex gap-2">
            <button onclick="location.reload()" class="bg-slate-200 text-slate-700 px-4 py-2 rounded-xl font-bold hover:bg-slate-300 transition-all">
                <i class="fas fa-sync-alt mr-1"></i> รีเฟรช
            </button>
            <a href="?logout=1&project_id=<?= $project_id ?>&milestone_id=<?= $milestone_id ?>" 
               class="bg-rose-100 text-rose-600 px-4 py-2 rounded-xl font-bold hover:bg-rose-200 transition-all flex items-center">
                <i class="fas fa-sign-out-alt mr-1"></i> ออกจากระบบ
            </a>
        </div>
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
                           class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">แนบรูปภาพหรือเอกสาร (PDF/JPG/PNG)</label>
                    <input type="file" name="inspection_attachment" accept="image/*,.pdf"
                           class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
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

        <!-- ส่วนที่ 2: Checklist -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2 flex items-center gap-2">
                <i class="fas fa-tasks text-emerald-500"></i> รายการตรวจสอบ (Checklist)
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
                <?php
                $groups = [
                    'งานโครงสร้างและสถาปัตย์' => [
                        'is_boq_complete' => 'งานดำเนินการครบตาม BOQ',
                        'is_drawing_match' => 'งานเป็นไปตามแบบ (Drawing)',
                        'is_spec_match' => 'วัสดุเป็นไปตาม Spec',
                        'is_quantity_ok' => 'ปริมาณงานถูกต้อง',
                        'is_on_schedule' => 'งานแล้วเสร็จตามงวด',
                    ],
                    'ระบบวิศวกรรม' => [
                        'is_electrical_ok' => 'ระบบไฟฟ้าใช้งานได้',
                        'is_plumbing_ok' => 'ระบบประปาไม่มีการรั่วซึม',
                        'is_drainage_ok' => 'ระบบระบายน้ำทำงานปกติ',
                        'is_hvac_ok' => 'ระบบปรับอากาศ (ถ้ามี)',
                    ],
                    'ความเรียบร้อย' => [
                        'is_surface_ok' => 'ผิวงานเรียบร้อย ไม่มีตำหนิ',
                        'is_paint_ok' => 'งานสี/ผิวตกแต่งเรียบร้อย',
                        'is_cleaned' => 'ไม่มีเศษวัสดุ/ทำความสะอาดแล้ว',
                        'is_defect_fixed' => 'งานแก้ไข Defect ครบถ้วน',
                        'is_safety_ok' => 'ความปลอดภัยหน้างานเรียบร้อย',
                    ]
                ];

                foreach ($groups as $groupName => $checks): ?>
                    <div class="mb-4">
                        <p class="font-bold text-indigo-600 text-sm mb-2 uppercase tracking-wider"><?= $groupName ?></p>
                        <div class="space-y-2">
                            <?php foreach ($checks as $key => $label): 
                                $checked = ($data[$key] ?? 0) == 1 ? 'checked' : '';
                            ?>
                            <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-100 transition-all border border-slate-100">
                                <input type="checkbox" name="<?= $key ?>" value="1" <?= $checked ?> class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm font-medium text-slate-700"><?= $label ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ส่วนที่ 3: สรุปผล -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2 flex items-center gap-2">
                <i class="fas fa-file-contract text-rose-500"></i> สรุปผลและรายการแก้ไข
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">ผลการตรวจรับ *</label>
                    <div id="status_container" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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
                        <label class="cursor-pointer status-option" data-status="<?= $val ?>">
                            <input type="radio" name="result_status" value="<?= $val ?>" <?= $checked ?> class="hidden peer">
                            <div class="p-4 rounded-2xl border text-center font-bold text-sm transition-all <?= $cfg['color'] ?> shadow-sm">
                                <?= $cfg['label'] ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p id="status_locked_msg" class="text-xs text-rose-500 font-bold mt-2 hidden">
                        <i class="fas fa-lock mr-1"></i> จะเลือก "ผ่าน" ได้เมื่อผ่านการตรวจครั้งที่ 2 แล้วเท่านั้น
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Punch List / รายการที่ต้องแก้ไข</label>
                    <textarea name="punch_list" rows="4" placeholder="ระบุรายละเอียด..."
                              class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none text-sm"><?= htmlspecialchars($data['punch_list'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">กำหนดเสร็จภายใน (วัน)</label>
                    <input type="number" name="fix_within_days" value="<?= htmlspecialchars($data['fix_within_days'] ?? '') ?>"
                           placeholder="ระบุจำนวนวัน..."
                           class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                </div>
            </div>
        </div>

        <!-- ส่วนที่ 4: รายชื่อคณะกรรมการ -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2 flex items-center gap-2">
                <i class="fas fa-users text-indigo-500"></i> คณะกรรมการตรวจรับ (Public)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-3">
                    <label class="block text-xs font-bold text-slate-500 mb-1 uppercase ml-1">ผู้ตรวจรับ (ครั้งที่ 1)</label>
                    <input type="text" name="inspector_name_1" value="<?= htmlspecialchars($data['inspector_name_1'] ?? '') ?>"
                           <?= $_SESSION['auth_step'] != 1 ? 'readonly' : '' ?>
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none text-sm <?= $_SESSION['auth_step'] != 1 ? 'opacity-75 bg-slate-100' : '' ?>">
                    <label class="flex items-center gap-2 p-2 bg-emerald-50 rounded-xl cursor-pointer border border-emerald-100">
                        <input type="checkbox" name="is_inspector_1_approved" id="is_inspector_1_approved" value="1" 
                               <?= ($data['is_inspector_1_approved'] ?? 0) == 1 ? 'checked' : '' ?>
                               <?= $_SESSION['auth_step'] != 1 ? 'disabled' : '' ?>
                               class="w-5 h-5 rounded text-emerald-600 focus:ring-emerald-500">
                        <span class="text-xs font-bold text-emerald-700 text-center flex-1">ยืนยันการตรวจครั้งที่ 1</span>
                    </label>
                </div>
                
                <div class="space-y-3">
                    <label class="block text-xs font-bold text-slate-500 mb-1 uppercase ml-1">ผู้ตรวจรับ (ครั้งที่ 2)</label>
                    <input type="text" name="inspector_name_2" value="<?= htmlspecialchars($data['inspector_name_2'] ?? '') ?>"
                           <?= $_SESSION['auth_step'] != 2 ? 'readonly' : '' ?>
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none text-sm <?= $_SESSION['auth_step'] != 2 ? 'opacity-75 bg-slate-100' : '' ?>">
                    <label id="inspector_2_label" class="flex items-center gap-2 p-2 rounded-xl cursor-pointer border transition-all">
                        <input type="checkbox" name="is_inspector_2_approved" id="is_inspector_2_approved" value="1" 
                               <?= ($data['is_inspector_2_approved'] ?? 0) == 1 ? 'checked' : '' ?>
                               <?= $_SESSION['auth_step'] != 2 ? 'disabled' : '' ?>
                               class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-bold text-center flex-1" id="inspector_2_text">ยืนยันการตรวจครั้งที่ 2</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex gap-4">
            <button type="submit" class="flex-1 bg-indigo-600 text-white font-black py-4 rounded-2xl shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 transition-all text-lg uppercase tracking-wider">
                <i class="fas fa-save mr-2"></i> บันทึกผลการตรวจงาน (<?= $_SESSION['auth_fullname'] ?>)
            </button>
        </div>
    </form>
</div>

<script>
function updateInspectorStatus() {
    const isApproved1 = $('#is_inspector_1_approved').is(':checked');
    const isApproved2 = $('#is_inspector_2_approved').is(':checked');
    const authStep = <?= $_SESSION['auth_step'] ?? 0 ?>;
    
    const $box2 = $('#is_inspector_2_approved');
    const $label2 = $('#inspector_2_label');
    const $text2 = $('#inspector_2_text');

    // Logic สำหรับการตรวจครั้งที่ 2
    if (!isApproved1) {
        $box2.prop('disabled', true);
        $label2.addClass('bg-slate-100 border-slate-200 opacity-50 cursor-not-allowed').removeClass('bg-indigo-50 border-indigo-100 text-indigo-700');
        $text2.text('รอตรวจครั้งที่ 1 ก่อน').addClass('text-slate-400');
    } else {
        if (authStep == 2) {
            $box2.prop('disabled', false);
        }
        $label2.removeClass('bg-slate-100 border-slate-200 opacity-50 cursor-not-allowed').addClass('bg-indigo-50 border-indigo-100 text-indigo-700');
        $text2.text('ยืนยันการตรวจครั้งที่ 2').removeClass('text-slate-400');
    }

    // Logic สำหรับการเลือกสถานะ "ผ่าน"
    const $passOption = $('.status-option[data-status="pass"]');
    const $passInput = $passOption.find('input');
    
    if (!isApproved1 || !isApproved2) {
        if ($passInput.is(':checked')) {
            $('input[name="result_status"][value="fail"]').prop('checked', true);
        }
        $passOption.addClass('opacity-30 grayscale cursor-not-allowed pointer-events-none');
        $('#status_locked_msg').removeClass('hidden');
    } else {
        $passOption.removeClass('opacity-30 grayscale cursor-not-allowed pointer-events-none');
        $('#status_locked_msg').addClass('hidden');
    }
}

$('#is_inspector_1_approved, #is_inspector_2_approved').on('change', updateInspectorStatus);
updateInspectorStatus(); // Run on load

$('#inspectionForm').on('submit', function(e) {
    e.preventDefault();
    
    Swal.fire({
        title: 'ยืนยันการบันทึก?',
        text: "คุณยืนยันข้อมูลการตรวจรับงานในส่วนของคุณใช่หรือไม่",
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
                url: 'api/save_inspection_publice.php',
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
                            window.location.reload();
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
</body>
</html>

