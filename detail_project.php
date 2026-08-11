<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 1. ดึงข้อมูลโครงการ
$pj_sql = "SELECT * FROM projects WHERE id = $id";
$pj_res = mysqli_query($conn, $pj_sql);
$pj = mysqli_fetch_assoc($pj_res);

if (!$pj) {
    die("<div class='p-10 text-center font-bold text-rose-500'>ไม่พบข้อมูลโครงการครับจาร!</div>");
}
// 2. คำนวณยอดเบิกสะสม (ดึงมาครบทั้ง ฐานเงินต้น, VAT, และ WHT)
$collected_sql = mysqli_query($conn, "
    SELECT 
        SUM(total_request_amount) as total_base, 
        SUM(vat_amount) as total_vat, 
        SUM(wht_amount) as total_wht,
        SUM(other_deduction_amount) as total_other,
        SUM(COALESCE(paid_amount, net_amount)) as total_net
    FROM project_milestones 
    WHERE project_id = $id AND status = 'paid'
");
$collected_data = mysqli_fetch_assoc($collected_sql);

// กำหนดตัวแปรไว้ใช้ในหน้า UI
$total_paid_base = $collected_data['total_base'] ?? 0;
$total_paid_vat = $collected_data['total_vat'] ?? 0;
$total_paid_wht = $collected_data['total_wht'] ?? 0;
$total_paid_other = $collected_data['total_other'] ?? 0;
$total_paid_net = $collected_data['total_net'] ?? 0;

// คำนวณยอดคงเหลือ (อ้างอิงจากฐานสัญญาหลัก)
$current_balance = $pj['contract_value'] - $total_paid_base;

// ยอดคงเหลือเบิกได้ (Base Value)
$current_balance = $pj['contract_value'] - $total_paid_base;

// 3. ดึงเอกสารที่ผูกไว้
$docs = mysqli_query($conn, "SELECT * FROM project_documents WHERE project_id = $id");

// 4. ดึงงวดงาน (เรียงตามวันที่ล่าสุดขึ้นก่อน) พร้อมข้อมูลการตรวจรับ
$milestones_sql = "
    SELECT m.*, i.id as inspection_id, i.result_status,
        (SELECT c.id FROM inspection_checklists c
            WHERE c.milestone_id = m.id AND c.status = 'active'
            ORDER BY c.id DESC LIMIT 1) AS dynamic_checklist_id,
        (SELECT r.id FROM inspection_rounds r
            WHERE r.milestone_id = m.id
            ORDER BY r.round_no DESC, r.id DESC LIMIT 1) AS dynamic_round_id,
        (SELECT r.status FROM inspection_rounds r
            WHERE r.milestone_id = m.id
            ORDER BY r.round_no DESC, r.id DESC LIMIT 1) AS dynamic_status
    FROM project_milestones m
    LEFT JOIN milestone_inspections i ON m.id = i.milestone_id
    WHERE m.project_id = $id 
    ORDER BY m.claim_date DESC";
$milestones = mysqli_query($conn, $milestones_sql);
?>

<?php
// คำนวณยอด PR ที่อนุมัติแล้วที่ผูกกับโครงการนี้
$pr_total_sql = "
    SELECT SUM(p.grand_total) as total 
    FROM project_documents pd
    JOIN pr p ON pd.doc_no = p.doc_no
    WHERE pd.project_id = $id AND pd.doc_type = 'pr' AND p.status = 'approved' AND p.deleted_at IS NULL
";
$pr_total_res = mysqli_query($conn, $pr_total_sql);
$pr_total_data = mysqli_fetch_assoc($pr_total_res);
$total_pr_approved = $pr_total_data['total'] ?? 0;

// คำนวณยอด PO ที่อนุมัติแล้วที่ผูกกับโครงการนี้
$po_total_sql = "
    SELECT SUM(p.grand_total) as total 
    FROM project_documents pd
    JOIN po p ON pd.doc_no = p.doc_no
    WHERE pd.project_id = $id AND pd.doc_type = 'po' AND p.status = 'approved' AND p.deleted_at IS NULL
";
$po_total_res = mysqli_query($conn, $po_total_sql);
$po_total_data = mysqli_fetch_assoc($po_total_res);
$total_po_approved = $po_total_data['total'] ?? 0;
?>

<div>
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">

        <div>
            <p class="text-slate-500 font-medium">
                จัดการงวดงานและเอกสารของ:
                <span class="text-indigo-600 font-bold"><?= htmlspecialchars($pj['project_name']) ?></span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 ">

        <div class="lg:col-span-4 space-y-6">

            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 space-y-4">
                <div class="bg-emerald-50 p-4 rounded-2xl border border-emerald-100 shadow-sm">
                    <p class="text-[12px] font-bold text-emerald-500 uppercase tracking-wider">มูลค่าสัญญาทั้งหมด (Base)
                    </p>
                    <p class="text-2xl font-black text-emerald-700">
                        <?= number_format($pj['contract_value'], 2) ?> <span class="text-sm">฿</span>
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-red-50 p-4 rounded-2xl border border-red-100">
                        <p class="text-[12px] font-bold text-red-500 uppercase tracking-wider">เบิกแล้วสะสม</p>
                        <p class="text-xl font-black text-red-600">
                            <?= number_format($total_paid_base, 2) ?>
                        </p>
                    </div>
                    <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100">
                        <p class="text-[12px] font-bold text-indigo-400 uppercase tracking-wider">คงเหลือเบิกได้</p>
                        <p class="text-xl font-black text-indigo-700">
                            <?= number_format($pj['contract_value'] - $total_paid_base, 2) ?>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 shadow-sm">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">VAT สะสมที่จ่ายแล้ว
                        </p>
                        <p class="text-lg font-black text-indigo-600">
                            + <?= number_format($total_paid_vat, 2) ?>
                        </p>
                    </div>

                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 shadow-sm">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">WHT สะสมที่หักแล้ว</p>
                        <p class="text-lg font-black text-rose-500">
                            - <?= number_format($total_paid_wht, 2) ?>
                        </p>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 shadow-sm">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">อื่นๆ สะสมที่หักแล้ว
                        </p>
                        <p class="text-lg font-black text-rose-500">
                            - <?= number_format($total_paid_other, 2) ?>
                        </p>
                    </div>
                </div>

                <div class="p-4 bg-slate-900 rounded-2xl border border-slate-800 flex justify-between items-center">
                    <div>
                        <span class="text-[12px] font-bold text-indigo-300 uppercase block">จ่ายจริงสะสม (Net
                            Paid)</span>
                        <span class="text-xs text-slate-400">จากเป้าหมาย
                            <?= number_format($pj['net_contract_value'], 2) ?></span>
                    </div>
                    <div class="text-right text-white">
                        <span class="text-2xl font-black"><?= number_format($total_paid_net, 2) ?></span>
                        <span class="text-sm text-indigo-300 ml-1">฿</span>
                    </div>
                </div>
            </div>

            <!-- เพิ่มสรุปงบประมาณและค่าใช้จ่าย -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 space-y-4">
                <h4 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-wallet text-indigo-500"></i> งบประมาณและค่าใช้จ่าย
                </h4>
                <div class="space-y-3">
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">ยอดขอซื้อ (PR Approved)</span>
                            <i class="fas fa-file-invoice-dollar text-indigo-400 text-xs"></i>
                        </div>
                        <p class="text-xl font-black text-slate-800"><?= number_format($total_pr_approved, 2) ?> <span class="text-[10px] italic">฿</span></p>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">ยอดสั่งซื้อ (PO Approved)</span>
                            <i class="fas fa-shopping-cart text-emerald-400 text-xs"></i>
                        </div>
                        <p class="text-xl font-black text-slate-800"><?= number_format($total_po_approved, 2) ?> <span class="text-[10px] italic">฿</span></p>
                    </div>
                </div>
                <div class="pt-2 border-t border-slate-100">
                    <div class="flex justify-between text-[11px] font-bold mb-1">
                        <span class="text-slate-400">กำไรเบื้องต้น (Estimated Profit)</span>
                        <span class="text-indigo-600"><?= number_format($pj['net_contract_value'] - $total_pr_approved, 2) ?> ฿</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                        <?php 
                        $profit_percent = $pj['net_contract_value'] > 0 ? (($pj['net_contract_value'] - $total_pr_approved) / $pj['net_contract_value']) * 100 : 0;
                        $profit_percent = max(0, min(100, $profit_percent));
                        ?>
                        <div class="bg-indigo-500 h-1.5 rounded-full" style="width: <?= $profit_percent ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl -sm border border-slate-100">
                <h4 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-link text-indigo-500"></i> เอกสารเชื่อมโยง
                </h4>
                <div class="space-y-3">
                    <?php if (mysqli_num_rows($docs) > 0): ?>
                        <?php while ($d = mysqli_fetch_assoc($docs)): ?>
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="uppercase font-black text-[9px] px-2 py-1 rounded bg-indigo-600 text-white"><?= $d['doc_type'] ?></span>
                                    <span class="text-sm font-bold text-slate-700"><?= $d['doc_no'] ?></span>
                                </div>
                                <?php $link = ($d['doc_type'] == 'po') ? 'view_po.php?no=' : (($d['doc_type'] == 'pr') ? 'view_pr.php?no=' : 'view_quotation.php?no='); ?>
                                <a href="<?= $link . $d['doc_no'] ?>" target="_blank"
                                    class="text-slate-400 hover:text-indigo-500"><i class="fas fa-external-link-alt"></i></a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-center py-4 text-slate-400 text-xs italic">ไม่มีเอกสารที่ผูกไว้</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="lg:col-span-8">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 min-h-[500px]">
                
                <!-- Tab Headers -->
                <div class="flex items-center gap-4 mb-6 border-b border-slate-100 pb-1">
                    <button onclick="switchTab('milestones')" id="tab-milestones" class="tab-btn active px-4 py-2 font-bold text-sm text-indigo-600 border-b-2 border-indigo-600 transition-all">
                        <i class="fas fa-list-ul mr-2"></i> งวดงาน (Milestones)
                    </button>
                </div>

                <!-- Tab: Milestones -->
                <div id="content-milestones" class="tab-content">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <h4 class="font-bold text-slate-800 flex items-center gap-2">
                            ประวัติการเบิกงวดงาน
                        </h4>
                        <div class="flex gap-2 w-full sm:w-auto">
                            <div id="bulkActions" class="hidden flex gap-2">
                                <button onclick="bulkPrint()"
                                    class="text-xs font-bold text-slate-600 bg-slate-100 px-4 py-2 rounded-xl hover:bg-slate-200 transition-all">
                                    <i class="fas fa-print mr-1"></i> พิมพ์ที่เลือก
                                </button>
                                <?php if (can_manage_projects()): ?>
                                <button onclick="bulkDelete()"
                                    class="text-xs font-bold text-white bg-rose-500 px-4 py-2 rounded-xl hover:bg-rose-600 transition-all">
                                    <i class="fas fa-trash-alt mr-1"></i> ลบที่เลือก
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php if (can_manage_projects()): ?>
                            <button onclick="location.href='add_milestone.php?project_id=<?= $id ?>'"
                                class="flex-1 sm:flex-none text-xs font-bold text-white bg-indigo-600 px-4 py-2 rounded-xl hover:bg-indigo-700 shadow-md transition-all">
                                <i class="fas fa-plus mr-1"></i> เพิ่มงวดเบิก
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <table id="milestoneTable" class="w-full">
                        <thead>
                            <tr class="text-[12px] text-slate-800 uppercase tracking-widest border-b border-slate-50">
                                <th class="px-4 py-3 text-left">
                                    <input type="checkbox" id="selectAll"
                                        class="rounded text-slate-800 focus:ring-indigo-500">
                                </th>
                                <th class="px-4 py-3 text-left">หลักฐาน</th>
                                <th class="px-4 py-3 text-left">งวดงาน</th>
                                <th class="px-4 py-3 text-left">วันที่เบิก</th>
                                <th class="px-4 py-3 text-center">สถานะ</th>
                                <th class="px-4 py-3 text-right">ยอดสุทธิ</th>
                                <th class="px-4 py-3 text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (mysqli_num_rows($milestones) > 0): ?>
                                <?php mysqli_data_seek($milestones, 0); ?>
                                <?php while ($m = mysqli_fetch_assoc($milestones)): ?>
                                    <tr class="group hover:bg-slate-50 transition-colors">
                                        <td class="px-4 py-4">
                                            <input type="checkbox" name="milestone_ids[]" value="<?= $m['id'] ?>"
                                                class="ms-check rounded text-indigo-600 focus:ring-indigo-500"
                                                data-status="<?= $m['status'] ?>">
                                        </td>
                                        <td class="px-4 py-4">
                                            <?php if (!empty($m['claim_attachment'])): ?>
                                                <?php
                                                $file_path = 'uploads/claims/' . $m['claim_attachment'];
                                                $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                                $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
                                                ?>
                                                <button
                                                    onclick="<?= $is_image ? "viewProofImage('$file_path')" : "window.open('$file_path', '_blank')" ?>"
                                                    class="w-10 h-10 flex items-center justify-center rounded-xl bg-indigo-50 text-indigo-500 border border-indigo-100 group-hover:bg-indigo-600 group-hover:text-white transition-all shadow-sm">
                                                    <i class="fas <?= $is_image ? 'fa-image' : 'fa-file-pdf' ?>"></i>
                                                </button>
                                            <?php else: ?>
                                                <div
                                                    class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-300 border border-dashed border-slate-200">
                                                    <i class="fas fa-eye-slash text-[12px]"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="font-black text-slate-700 uppercase text-sm truncate max-w-[250px]">
                                                <?= $m['milestone_name'] ?>
                                            </p>
                                            <?php if (!empty($m['work_start_date'])): ?>
                                                <p class="text-[10px] text-indigo-500 font-bold mt-0.5">
                                                    <i class="fas fa-calendar-alt mr-1"></i> เริ่ม: <?= date('d/m/Y', strtotime($m['work_start_date'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-800 font-bold">
                                            <?= date('d/m/Y', strtotime($m['claim_date'])) ?>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <span
                                                class="text-[12px] font-black px-2 py-1 rounded-full uppercase <?= $m['status'] == 'paid' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600' ?>">
                                                <?= $m['status'] == 'paid' ? 'ชำระเงินแล้ว' : 'ค้างชำระ' ?>
                                            </span>
                                            <?php if ($m['status'] == 'paid' && !empty($m['paid_at'])): ?>
                                                <p class="mt-1 text-[10px] font-bold text-slate-400">
                                                    โอน <?= date('d/m/Y', strtotime($m['paid_at'])) ?>
                                                    <?php if ($m['paid_amount'] !== null): ?> · <?= number_format((float)$m['paid_amount'], 2) ?> บาท<?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <p
                                                class="text-base font-black text-slate-800 group-hover:text-emerald-600 transition-colors">
                                                <?= number_format($m['net_amount'], 2) ?> <span class="text-[12px] italic">฿</span>
                                            </p>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <?php if (can_manage_projects()): ?>
                                                <button onclick="editMilestone(<?= $m['id'] ?>)"
                                                    class="text-[12px] bg-amber-400 text-white px-3 py-1.5 rounded-lg hover:bg-amber-500 shadow-sm transition-all"
                                                    title="แก้ไขข้อมูล">
                                                <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($m['status'] == 'pending'): ?>
                                                    <?php $inspectionPassed = (!empty($m['dynamic_round_id']) && ($m['dynamic_status'] ?? '') === 'completed') || (empty($m['dynamic_round_id']) && ($m['result_status'] ?? '') === 'pass'); ?>
                                                    <?php if ($inspectionPassed): ?>
                                                        <button onclick="updatePaymentStatus(<?= $m['id'] ?>, 'paid')"
                                                            class="text-[12px] font-bold bg-emerald-500 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-600 shadow-sm transition-all"
                                                            title="ยืนยันชำระเงิน">
                                                            <i class="fas fa-check-circle"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-[11px] font-bold text-slate-400 px-2 py-1.5" title="ต้องตรวจรับและอนุมัติครบก่อนจึงบันทึกการโอนได้">
                                                            <i class="fas fa-lock mr-1"></i>รอตรวจรับ
                                                        </span>
                                                    <?php endif; ?>

                                                    <button onclick="deleteMilestone(<?= $m['id'] ?>)"
                                                        class="text-[12px] bg-rose-500 text-white px-4 py-1.5 rounded-lg hover:bg-rose-600 shadow-sm transition-all"
                                                        title="ลบรายการ">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>

                                                <?php else: ?>
                                                    <div class="flex items-center gap-2 py-1.5">
                                                        <span
                                                            class="text-emerald-500 bg-emerald-50 px-3 py-1 rounded-lg text-[12px] font-black border border-emerald-100 uppercase tracking-tighter">
                                                            <i class="fas fa-check-double mr-1"></i> ชำระแล้ว
                                                        </span>
                                                        <button
                                                            onclick="refundRetention(<?= $m['id'] ?>, '<?= addslashes($m['deduction_note'] ?: 'เงินประกัน') ?>')"
                                                            class="text-[12px] font-bold bg-indigo-500 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-600 shadow-sm transition-all"
                                                            title="คืนเงินประกัน">
                                                            <i class="fas fa-undo-alt mr-1"></i> คืนเงินประกัน
                                                        </button>
                                                    </div>

                                                <?php endif; ?>
                                                <?php if (!empty($m['dynamic_round_id'])): ?>
                                                    <a href="add_inspection.php?project_id=<?= $id ?>&milestone_id=<?= $m['id'] ?>"
                                                        class="text-[12px] font-bold bg-emerald-500 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-600 shadow-sm transition-all flex items-center gap-1"
                                                        title="ตรวจ Checklist ก่อนดูเอกสาร">
                                                        <i class="fas fa-clipboard-check"></i> ตรวจ
                                                        <span class="ml-1 px-1.5 py-0.5 rounded-md bg-white/20 text-[10px] uppercase">
                                                            <?= $m['dynamic_status'] === 'completed' ? 'เสร็จสิ้น' : ($m['dynamic_status'] === 'correction_required' ? 'แก้งาน' : 'กำลังตรวจ') ?>
                                                        </span>
                                                    </a>
                                                <?php elseif ($m['inspection_id']): ?>
                                                    <a href="view_inspection.php?id=<?= $m['inspection_id'] ?>"
                                                        class="text-[12px] font-bold bg-emerald-500 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-600 shadow-sm transition-all flex items-center gap-1"
                                                        title="ดูผลการตรวจรับงาน">
                                                        <i class="fas fa-eye"></i> ดู
                                                        <span class="ml-1 px-1.5 py-0.5 rounded-md bg-white/20 text-[10px] uppercase">
                                                            <?= $m['result_status'] === 'pass' ? 'ผ่าน' : ($m['result_status'] === 'conditional_pass' ? 'เงื่อนไข' : 'ไม่ผ่าน') ?>
                                                        </span>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="add_inspection.php?project_id=<?= $id ?>&milestone_id=<?= $m['id'] ?>"
                                                        class="text-[12px] font-bold bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-black shadow-sm transition-all"
                                                        title="ตรวจงาน">
                                                        <i class="fas fa-clipboard-check mr-1"></i> ตรวจงาน
                                                    </a>
                                                <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if (empty($inspection_only_access)): ?><span class="text-slate-400 text-xs italic">View Only</span><?php endif; ?>
                                                    <?php if (!empty($m['dynamic_round_id'])): ?>
                                                        <a href="add_inspection.php?project_id=<?= $id ?>&milestone_id=<?= $m['id'] ?>"
                                                            class="text-[12px] font-bold bg-emerald-500 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-600 shadow-sm transition-all flex items-center gap-1"
                                                            title="ตรวจ Checklist ก่อนดูเอกสาร">
                                                            <i class="fas fa-clipboard-check"></i>
                                                        </a>
                                                    <?php elseif ($m['inspection_id']): ?>
                                                        <a href="view_inspection.php?id=<?= $m['inspection_id'] ?>"
                                                            class="text-[12px] font-bold bg-emerald-500 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-600 shadow-sm transition-all flex items-center gap-1"
                                                            title="ดูผลการตรวจรับงาน">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
<script>
    function switchTab(tab) {
        $('.tab-content').addClass('hidden');
        $(`#content-${tab}`).removeClass('hidden');
        
        $('.tab-btn').removeClass('active text-indigo-600 border-indigo-600').addClass('text-slate-400 border-transparent');
        $(`#tab-${tab}`).addClass('active text-indigo-600 border-indigo-600').removeClass('text-slate-400 border-transparent');
    }

    function refundRetention(id, currentNote) {
        Swal.fire({
            title: 'ยืนยันการคืนเงินประกัน?',
            text: "ระบบจะเซ็ตยอดหักอื่นๆ และค่าประกันเป็น 0",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ยืนยันคืนเงิน',
            cancelButtonText: 'ยกเลิก',
            heightAuto: false
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/update_refund_milestone.php',
                    type: 'POST',
                    dataType: 'json', // ระบุว่ารอรับ JSON
                    data: {
                        action: 'refund_retention',
                        id: id,
                        new_note: 'คืนเงินแล้ว: ' + currentNote
                    },
                    success: function (response) {
                        // เช็ค status ที่ส่งมาจาก PHP
                        if (response.status === 'success') {
                            Swal.fire({
                                title: 'สำเร็จ!',
                                text: response.message,
                                icon: 'success',
                                heightAuto: false
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({
                                title: 'เกิดข้อผิดพลาด',
                                text: response.message,
                                icon: 'error',
                                heightAuto: false
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        // ถ้าเข้าตรงนี้แสดงว่าไฟล์ PHP error หรือ path ผิด
                        console.error(xhr.responseText); // ดู error จริงใน Console (F12)
                        Swal.fire({
                            title: 'Error',
                            text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ หรือไฟล์มีปัญหา',
                            icon: 'error',
                            heightAuto: false
                        });
                    }
                });
            }
        })
    }
</script>
<script src="assets/js/detailproject.js"></script>
<?php include('footer.php'); ?>
