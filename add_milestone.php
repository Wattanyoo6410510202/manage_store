<?php
require_once 'config.php';
include('header.php');

$project_id = intval($_GET['project_id']);

// 1. ดึงข้อมูลโปรเจกต์
$sql_pj = "SELECT * FROM projects WHERE id = $project_id";
$res_pj = mysqli_query($conn, $sql_pj);
$pj = mysqli_fetch_assoc($res_pj);

if (!$pj) {
    echo "<script>alert('ไม่พบข้อมูลโครงการ'); window.location.href='projects.php';</script>";
    exit;
}

// 2. นับงวดงานถัดไป
$sql_count = "SELECT COUNT(*) as total FROM project_milestones WHERE project_id = $project_id";
$count = mysqli_fetch_assoc(mysqli_query($conn, $sql_count))['total'] + 1;

// 3. คำนวณหายอดรวมที่เบิกไปแล้ว (Gross Amount)
$collected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM project_milestones WHERE project_id = $project_id"))['total'] ?: 0;
?>

<div>
    <form action="api/save_milestone.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <input type="hidden" name="vat_amount" id="vat_amount_val">
        <input type="hidden" name="wht_amount" id="wht_amount_val">
        <input type="hidden" name="total_request_amount" id="total_request_amount_val">
        <input type="hidden" name="remaining_balance" id="remaining_balance_val">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">บันทึกใบเบิกงวดงาน</h2>
                <p class="text-slate-500 text-sm">โครงการ: <span
                        class="text-indigo-600 font-bold"><?= $pj['project_no'] ?> - <?= $pj['project_name'] ?></span>
                </p>
            </div>
            <div class="flex gap-3">
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-2.5 rounded-xl font-bold -lg -indigo-200 transition-all">
                    <i class="fas fa-check-circle mr-2"></i> บันทึกข้อมูล
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl -sm border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-file-invoice-dollar text-indigo-500"></i> รายละเอียดใบแจ้งหนี้
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">งวดที่ / รายละเอียดงาน <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="milestone_name" required value="งวดที่ <?= $count ?>: "
                                class="w-full border border-slate-200 rounded-xl p-2.5 ">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1 ">ยอดเงินตั้งเบิก
                                (ก่อนภาษี)</label>
                            <div class="relative">
                                <input type="number" step="0.01" name="amount" id="amount" oninput="calculateMoney()"
                                    required class="w-full border-2  rounded-xl p-2.5 pl-8 " placeholder="0.00">
                                <span class="absolute left-3 top-3 text-slate-400">฿</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">วันที่เรียกเก็บ</label>
                            <input type="date" name="claim_date" value="<?= date('Y-m-d') ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none ">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">หมายเหตุ (เพิ่มเติม)</label>
                            <textarea name="remarks" rows="2"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none "
                                placeholder="ระบุรายละเอียดเพิ่มเติม เช่น หักค่าของ, จ่ายล่วงหน้า..."></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-slate-100">
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <div class="flex justify-between items-center mb-1">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">VAT 7%</label>
                                <input type="checkbox" id="use_vat" checked onchange="calculateMoney()"
                                    class="rounded text-indigo-500">
                            </div>
                            <p class="text-lg font-bold text-slate-700" id="vat_display">0.00 ฿</p>
                        </div>

                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <div class="flex justify-between items-center mb-1">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">หัก ณ ที่จ่าย 3%</label>
                                <input type="checkbox" id="use_wht" checked onchange="calculateMoney()"
                                    class="rounded text-rose-500">
                            </div>
                            <p class="text-lg font-bold text-rose-500" id="wht_display">0.00 ฿</p>
                        </div>

                        <div class="p-4 bg-indigo-600 rounded-xl -md text-white">
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
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <label class="block text-sm font-bold text-slate-700 mb-4">สถานะการจ่ายเงิน</label>

                    <div class="grid grid-cols-2 gap-4">
                        <label class="cursor-pointer group">
                            <input type="radio" name="status" value="pending" class="peer hidden" checked>
                            <div
                                class="flex flex-col items-center justify-center py-4 rounded-2xl border-2 border-slate-100 bg-slate-50 text-slate-300 transition-all 
                    peer-checked:border-amber-400 peer-checked:bg-amber-50 peer-checked:text-amber-500 shadow-sm group-hover:bg-white">
                                <i class="fas fa-hourglass-half text-2xl mb-2"></i>
                                <span class="text-[11px] font-black uppercase tracking-wider">รอชำระ</span>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="status" value="paid" class="peer hidden">
                            <div
                                class="flex flex-col items-center justify-center py-4 rounded-2xl border-2 border-slate-100 bg-slate-50 text-slate-300 transition-all 
                    peer-checked:border-emerald-400 peer-checked:bg-emerald-50 peer-checked:text-emerald-500 shadow-sm group-hover:bg-white">
                                <i class="fas fa-check-circle text-2xl mb-2"></i>
                                <span class="text-[11px] font-black uppercase tracking-wider">จ่ายแล้ว</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="bg-slate-900 rounded-3xl p-6 text-white -xl">
                    <h4 class="font-bold mb-4 flex items-center gap-2 text-indigo-400">
                        <i class="fas fa-calculator"></i> สถานะงบประมาณ
                    </h4>
                    <div class="space-y-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-400">มูลค่ารวม:</span>
                            <span class="font-bold"><?= number_format($pj['contract_value'], 2) ?></span>
                        </div>
                        <div class="flex justify-between border-t border-slate-800 pt-2">
                            <span class="text-slate-400">เบิกไปแล้ว:</span>
                            <span class="text-white"><?= number_format($collected, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-indigo-400 font-bold">
                            <span>งวดนี้:</span>
                            <span id="current_claim_display">0.00</span>
                        </div>
                        <div
                            class="flex justify-between font-black text-lg pt-2 mt-2 border-t border-dashed border-slate-700">
                            <span>คงเหลือสุทธิ:</span>
                            <span id="remaining_balance_display" class="text-indigo-400">0.00</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl -sm border border-slate-200 p-6">
                    <h3 class="text-sm font-bold text-slate-800 mb-3">หลักฐานการเบิก (สลิป/ใบแจ้งหนี้)</h3>
                    <div
                        class="relative border-2 border-dashed border-slate-200 rounded-2xl p-4 text-center hover:border-indigo-400 cursor-pointer">
                        <input type="file" name="claim_attachment" class="absolute inset-0 opacity-0 cursor-pointer"
                            onchange="document.getElementById('file-label').innerText = this.files[0].name">
                        <p id="file-label" class="text-[10px] text-slate-400 truncate">คลิกเพื่อเลือกไฟล์</p>
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
        let collectedBefore = <?= $collected ?>;

        let vat = document.getElementById('use_vat').checked ? (amount * 0.07) : 0;
        let wht = document.getElementById('use_wht').checked ? (amount * 0.03) : 0;
        let totalRequest = (amount + vat) - wht;
        let remaining = contractValue - (collectedBefore + amount);

        document.getElementById('vat_display').innerText = vat.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' ฿';
        document.getElementById('wht_display').innerText = '- ' + wht.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' ฿';
        document.getElementById('total_request_display').innerText = totalRequest.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' ฿';
        document.getElementById('current_claim_display').innerText = amount.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('remaining_balance_display').innerText = remaining.toLocaleString(undefined, { minimumFractionDigits: 2 });

        document.getElementById('vat_amount_val').value = vat.toFixed(2);
        document.getElementById('wht_amount_val').value = wht.toFixed(2);
        document.getElementById('total_request_amount_val').value = totalRequest.toFixed(2);
        document.getElementById('remaining_balance_val').value = remaining.toFixed(2);
    }
    calculateMoney();
</script>

<?php include('footer.php'); ?>