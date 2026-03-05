<?php
require_once 'config.php';
include('header.php');

// รับ ID ของ Milestone ที่จะแก้ไข
$id = intval($_GET['id']);

// 1. ดึงข้อมูล Milestone เดิม
$sql_m = "SELECT * FROM project_milestones WHERE id = $id";
$res_m = mysqli_query($conn, $sql_m);
$m_data = mysqli_fetch_assoc($res_m);

if (!$m_data) {
    echo "<script>alert('ไม่พบข้อมูลรายการนี้'); window.location.href='projects.php';</script>";
    exit;
}

$project_id = $m_data['project_id'];

// 2. ดึงข้อมูลโปรเจกต์
$sql_pj = "SELECT * FROM projects WHERE id = $project_id";
$res_pj = mysqli_query($conn, $sql_pj);
$pj = mysqli_fetch_assoc($res_pj);

// 3. คำนวณหายอดรวมที่เบิกไปแล้ว (ไม่นับงวดปัจจุบันที่กำลังแก้ เพื่อเอามาคำนวณ balance ใหม่)
$sql_collected = "SELECT SUM(amount) as total FROM project_milestones WHERE project_id = $project_id AND id != $id";
$collected = mysqli_fetch_assoc(mysqli_query($conn, $sql_collected))['total'] ?: 0;
?>

<div>
    <form action="api/update_milestone_full.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <input type="hidden" name="vat_amount" id="vat_amount_val" value="<?= $m_data['vat_amount'] ?>">
        <input type="hidden" name="wht_amount" id="wht_amount_val" value="<?= $m_data['wht_amount'] ?>">
        <input type="hidden" name="total_request_amount" id="total_request_amount_val"
            value="<?= $m_data['total_request_amount'] ?>">
        <input type="hidden" name="remaining_balance" id="remaining_balance_val"
            value="<?= $m_data['remaining_balance'] ?>">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">แก้ไขใบเบิกงวดงาน</h2>
                <p class="text-slate-500 text-sm">โครงการ: <span
                        class="text-indigo-600 font-bold"><?= $pj['project_no'] ?> - <?= $pj['project_name'] ?></span>
                </p>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="history.back()"
                    class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all">ยกเลิก</button>
                <button type="submit"
                    class="bg-amber-500 hover:bg-amber-600 text-white px-8 py-2.5 rounded-xl font-bold shadow-lg shadow-amber-100 transition-all">
                    <i class="fas fa-save mr-2"></i> บันทึกการแก้ไข
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-edit text-amber-500"></i> รายละเอียดที่ต้องการแก้ไข
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">งวดที่ / รายละเอียดงาน <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="milestone_name" required
                                value="<?= htmlspecialchars($m_data['milestone_name']) ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1 text-indigo-600">ยอดเงินตั้งเบิก
                                (ก่อนภาษี)</label>
                            <div class="relative">
                                <input type="number" step="0.01" name="amount" id="amount" oninput="calculateMoney()"
                                    required value="<?= $m_data['amount'] ?>"
                                    class="w-full border-2 border-indigo-100 rounded-xl p-2.5 pl-8 focus:border-indigo-500 focus:ring-0 outline-none font-bold text-lg text-indigo-700">
                                <span class="absolute left-3 top-3 text-slate-400">฿</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">วันที่เรียกเก็บ</label>
                            <input type="date" name="claim_date" value="<?= $m_data['claim_date'] ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">หมายเหตุ (เพิ่มเติม)</label>
                            <textarea name="remarks" rows="2"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars($m_data['remarks']) ?></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-slate-100">
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <div class="flex justify-between items-center mb-1">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">VAT 7%</label>
                                <input type="checkbox" id="use_vat" <?= $m_data['vat_amount'] > 0 ? 'checked' : '' ?>
                                    onchange="calculateMoney()" class="rounded text-indigo-500">
                            </div>
                            <p class="text-lg font-bold text-slate-700" id="vat_display">0.00 ฿</p>
                        </div>

                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <div class="flex justify-between items-center mb-1">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">หัก ณ ที่จ่าย 3%</label>
                                <input type="checkbox" id="use_wht" <?= $m_data['wht_amount'] > 0 ? 'checked' : '' ?>
                                    onchange="calculateMoney()" class="rounded text-rose-500">
                            </div>
                            <p class="text-lg font-bold text-rose-500" id="wht_display">0.00 ฿</p>
                        </div>

                        <div class="p-4 bg-indigo-600 rounded-xl text-white">
                            <label class="text-[10px] font-bold opacity-80 uppercase mb-1 block">ยอดจ่ายสุทธิ</label>
                            <p class="text-xl font-black" id="total_request_display">0.00 ฿</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl -sm border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-university text-blue-500"></i> บัญชีธนาคารสำหรับโอนเงิน
                    </h3>
                    <div class="flex items-center gap-4 p-4 bg-blue-50 border border-blue-100 rounded-2xl">
                        <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center -sm">
                            <i class="fas fa-wallet text-blue-500 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-700"><?= $pj['bank_name'] ?>: <span
                                    class="text-blue-700 font-black"><?= $pj['bank_account_no'] ?></span></p>
                            <p class="text-[11px] text-slate-500">ชื่อบัญชี: <?= $pj['bank_account_name'] ?></p>
                        </div>
                    </div>
                </div>
            </div>



            <div class="space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <label class="block text-sm font-bold text-slate-700 mb-2">สถานะการจ่ายเงิน</label>
                    <select name="status"
                        class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-indigo-500 font-bold text-slate-700">
                        <option value="pending" <?= $m_data['status'] == 'pending' ? 'selected' : '' ?>>⏳ รอชำระเงิน
                            (Pending)</option>
                        <option value="paid" <?= $m_data['status'] == 'paid' ? 'selected' : '' ?>>✅ ชำระเงินแล้ว (Paid)
                        </option>
                    </select>
                </div>

                <div class="bg-slate-900 rounded-3xl p-6 text-white shadow-xl">
                    <h4 class="font-bold mb-4 flex items-center gap-2 text-indigo-400">
                        <i class="fas fa-calculator"></i> ประมาณการงบใหม่
                    </h4>
                    <div class="space-y-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-400">มูลค่ารวมโครงการ:</span>
                            <span class="font-bold"><?= number_format($pj['contract_value'], 2) ?></span>
                        </div>
                        <div class="flex justify-between border-t border-slate-800 pt-2">
                            <span class="text-slate-400">เบิกจากงวดอื่นไปแล้ว:</span>
                            <span class="text-white"><?= number_format($collected, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-amber-400 font-bold">
                            <span>งวดนี้ (แก้ไขเป็น):</span>
                            <span id="current_claim_display">0.00</span>
                        </div>
                        <div
                            class="flex justify-between font-black text-lg pt-2 mt-2 border-t border-dashed border-slate-700">
                            <span>คงเหลือสุทธิ:</span>
                            <span id="remaining_balance_display" class="text-indigo-400">0.00</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h3 class="text-sm font-bold text-slate-800 mb-3">หลักฐานการเบิก (อัปโหลดใหม่เพื่อเปลี่ยน)</h3>
                    <?php if (!empty($m_data['claim_attachment'])): ?>
                        <div
                            class="mb-3 p-2 bg-slate-50 rounded-lg border flex items-center gap-2 text-[10px] text-slate-500">
                            <i class="fas fa-paperclip"></i> ไฟล์เดิม: <?= $m_data['claim_attachment'] ?>
                        </div>
                    <?php endif; ?>
                    <div
                        class="relative border-2 border-dashed border-slate-200 rounded-2xl p-4 text-center hover:border-indigo-400 cursor-pointer">
                        <input type="file" name="claim_attachment" class="absolute inset-0 opacity-0 cursor-pointer"
                            onchange="document.getElementById('file-label').innerText = this.files[0].name">
                        <p id="file-label" class="text-[10px] text-slate-400 truncate">คลิกเพื่อเปลี่ยนไฟล์หลักฐาน</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function calculateMoney() {
        let amount = parseFloat(document.getElementById('amount').value) || 0;
        let contractValue = <?= $pj['contract_value'] ?>;
        let collectedOther = <?= $collected ?>; // ยอดจากงวดอื่นๆ

        let vat = document.getElementById('use_vat').checked ? (amount * 0.07) : 0;
        let wht = document.getElementById('use_wht').checked ? (amount * 0.03) : 0;

        // totalRequest คือ ยอดรับสุทธิ (Net Amount)
        let totalRequest = (amount + vat) - wht;

        // remaining คำนวณจาก (มูลค่ารวม - (งวดอื่น + งวดนี้))
        let remaining = contractValue - (collectedOther + amount);

        document.getElementById('vat_display').innerText = vat.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' ฿';
        document.getElementById('wht_display').innerText = '- ' + wht.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' ฿';
        document.getElementById('total_request_display').innerText = totalRequest.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' ฿';
        document.getElementById('current_claim_display').innerText = amount.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('remaining_balance_display').innerText = remaining.toLocaleString(undefined, { minimumFractionDigits: 2 });

        // เก็บค่าใส่ Hidden Input เพื่อส่งไป Save
        document.getElementById('vat_amount_val').value = vat.toFixed(2);
        document.getElementById('wht_amount_val').value = wht.toFixed(2);
        document.getElementById('total_request_amount_val').value = totalRequest.toFixed(2);
        document.getElementById('remaining_balance_val').value = remaining.toFixed(2);
    }
    // รันครั้งแรกตอนโหลดหน้า
    calculateMoney();
</script>

<?php include('footer.php'); ?>