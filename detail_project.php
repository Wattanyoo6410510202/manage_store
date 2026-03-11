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
        SUM(net_amount) as total_net
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

// 4. ดึงงวดงาน (เรียงตามวันที่ล่าสุดขึ้นก่อน)
$milestones = mysqli_query($conn, "SELECT * FROM project_milestones WHERE project_id = $id ORDER BY claim_date DESC");
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
                    <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-wider">มูลค่าสัญญาทั้งหมด (Base)
                    </p>
                    <p class="text-2xl font-black text-emerald-700">
                        <?= number_format($pj['contract_value'], 2) ?> <span class="text-sm">฿</span>
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-red-50 p-4 rounded-2xl border border-red-100">
                        <p class="text-[10px] font-bold text-red-500 uppercase tracking-wider">เบิกแล้วสะสม</p>
                        <p class="text-xl font-black text-red-600">
                            <?= number_format($total_paid_base, 2) ?>
                        </p>
                    </div>
                    <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100">
                        <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider">คงเหลือเบิกได้</p>
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
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">อื่นๆ สะสมที่หักแล้ว</p>
                        <p class="text-lg font-black text-rose-500">
                            - <?= number_format($total_paid_other, 2) ?>
                        </p>
                    </div>
                </div>

                <div class="p-4 bg-slate-900 rounded-2xl border border-slate-800 flex justify-between items-center">
                    <div>
                        <span class="text-[10px] font-bold text-indigo-300 uppercase block">จ่ายจริงสะสม (Net
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
            <div class="bg-white p-6 rounded-3xl -sm border border-slate-100 min-h-[500px]">

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
                            <button onclick="bulkDelete()"
                                class="text-xs font-bold text-white bg-rose-500 px-4 py-2 rounded-xl hover:bg-rose-600 transition-all">
                                <i class="fas fa-trash-alt mr-1"></i> ลบที่เลือก
                            </button>
                        </div>
                        <button onclick="location.href='add_milestone.php?project_id=<?= $id ?>'"
                            class="flex-1 sm:flex-none text-xs font-bold text-white bg-indigo-600 px-4 py-2 rounded-xl hover:bg-indigo-700 -md transition-all">
                            <i class="fas fa-plus mr-1"></i> เพิ่มงวดเบิก
                        </button>
                    </div>
                </div>
                <table id="milestoneTable" class="w-full">
                    <thead>
                        <tr class="text-[10px] text-slate-400 uppercase tracking-widest border-b border-slate-50">
                            <th class="px-4 py-3 text-left">
                                <input type="checkbox" id="selectAll"
                                    class="rounded text-indigo-600 focus:ring-indigo-500">
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
                                                <i class="fas fa-eye-slash text-[10px]"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-black text-slate-700 uppercase text-sm truncate max-w-[250px]">
                                            <?= $m['milestone_name'] ?></p>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-slate-500 font-bold">
                                        <?= date('d/m/Y', strtotime($m['claim_date'])) ?>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span
                                            class="text-[10px] font-black px-2 py-1 rounded-full uppercase <?= $m['status'] == 'paid' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600' ?>">
                                            <?= $m['status'] == 'paid' ? 'ชำระเงินแล้ว' : 'ค้างชำระ' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <p
                                            class="text-base font-black text-slate-800 group-hover:text-emerald-600 transition-colors">
                                            <?= number_format($m['net_amount'], 2) ?> <span class="text-[10px] italic">฿</span>
                                        </p>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <?php if ($m['status'] == 'pending'): ?>
                                                <button onclick="updatePaymentStatus(<?= $m['id'] ?>, 'paid')"
                                                    class="text-[10px] font-bold bg-emerald-500 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-600 shadow-sm transition-all"
                                                    title="ยืนยันชำระเงิน">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>

                                                <button onclick="editMilestone(<?= $m['id'] ?>)"
                                                    class="text-[10px] bg-amber-400 text-white px-3 py-1.5 rounded-lg hover:bg-amber-500 shadow-sm transition-all"
                                                    title="แก้ไขข้อมูล">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <button onclick="deleteMilestone(<?= $m['id'] ?>)"
                                                    class="text-[10px] bg-rose-500 text-white px-4 py-1.5 rounded-lg hover:bg-rose-600 shadow-sm transition-all"
                                                    title="ลบรายการ">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>

                                            <?php else: ?>
                                                <div class="flex items-center gap-2 py-1.5">
                                                    <span
                                                        class="text-emerald-500 bg-emerald-50 px-3 py-1 rounded-lg text-[10px] font-black border border-emerald-100 uppercase tracking-tighter">
                                                        <i class="fas fa-check-double mr-1"></i> ชำระแล้ว
                                                    </span>
                                                </div>
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

<script src="assets/js/detailproject.js"></script>
<?php include('footer.php'); ?>