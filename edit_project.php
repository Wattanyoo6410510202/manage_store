<?php
require_once 'config.php';
include('header.php');

// 1. รับ ID และดึงข้อมูลเดิม
$pj_id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : 0;
$sql_pj = "SELECT * FROM projects WHERE id = '$pj_id'";
$res_pj = mysqli_query($conn, $sql_pj);
$pj = mysqli_fetch_assoc($res_pj);

if (!$pj) {
    echo "<div class='p-10 text-center text-red-500'>ไม่พบข้อมูลโครงการที่ต้องการแก้ไข</div>";
    exit;
}

// ดึงรายชื่อลูกค้า
$customers = mysqli_query($conn, "SELECT id, customer_name FROM customers ORDER BY customer_name ASC");

// ดึงเอกสารเชื่อมโยงเดิม
$sql_docs = "SELECT * FROM project_documents WHERE project_id = '$pj_id'";
$res_docs = mysqli_query($conn, $sql_docs);
?>

<div>
    <form action="api/update_project.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="project_id" value="<?= $pj['id'] ?>">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">แก้ไขโครงการ</h2>
                <p class="text-slate-500 text-sm">เลขที่อ้างอิง: <span
                        class="font-mono font-bold text-indigo-600"><?= $pj['project_no'] ?></span></p>
            </div>
            <div class="flex gap-3">
                <button type="submit"
                    class="bg-indigo-500 hover:bg-indigo-600 text-white px-8 py-2.5 rounded-xl font-bold  transition-all">
                    <i class="fas fa-sync-alt mr-2"></i> อัปเดตข้อมูล
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-info-circle text-indigo-500"></i> ข้อมูลงาน
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่องาน</label>
                            <input type="text" name="project_name" value="<?= htmlspecialchars($pj['project_name']) ?>"
                                required
                                class="w-full border border-slate-200 rounded-xl p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">เลขที่โครงการ</label>
                            <input type="text" name="project_no" value="<?= $pj['project_no'] ?>" readonly
                                class="w-full border border-slate-200 rounded-xl p-2.5 bg-slate-50 text-slate-500 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">ลูกค้า</label>
                            <select name="customer_id"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                                <?php while ($c = mysqli_fetch_assoc($customers)): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($c['id'] == $pj['customer_id']) ? 'selected' : '' ?>>
                                        <?= $c['customer_name'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">มูลค่างาน (Base)</label>
                            <input type="number" step="0.01" name="contract_value" id="contract_value"
                                value="<?= $pj['contract_value'] ?>" oninput="calculateNetValue()"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none font-bold text-indigo-600 bg-indigo-50/30">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">วันที่เริ่ม</label>
                                <input type="date" name="start_date" value="<?= $pj['start_date'] ?>"
                                    class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">วันที่สิ้นสุด</label>
                                <input type="date" name="end_date" value="<?= $pj['end_date'] ?>"
                                    class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-user-tie text-indigo-500"></i> ข้อมูลผู้รับจ้างและการชำระเงิน
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อผู้รับจ้าง / บริษัท</label>
                            <input type="text" name="contractor_name"
                                value="<?= htmlspecialchars($pj['contractor_name']) ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">ธนาคาร</label>
                            <input type="text" name="bank_name" value="<?= htmlspecialchars($pj['bank_name']) ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">เลขที่บัญชี</label>
                            <input type="text" name="bank_account_no"
                                value="<?= htmlspecialchars($pj['bank_account_no']) ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อบัญชี</label>
                            <input type="text" name="bank_account_name"
                                value="<?= htmlspecialchars($pj['bank_account_name']) ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-link text-indigo-500"></i> เอกสารเชื่อมโยง
                        </h3>
                        <button type="button" onclick="addDocRow()"
                            class="text-indigo-600 font-bold text-sm bg-indigo-50 px-4 py-2 rounded-lg hover:bg-indigo-100 transition-all">
                            <i class="fas fa-plus-circle mr-1"></i> เพิ่มเอกสาร
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <tbody id="docBody">
                                <?php if (mysqli_num_rows($res_docs) > 0): ?>
                                    <?php while ($d = mysqli_fetch_assoc($res_docs)): ?>
                                        <tr class="border-b border-slate-50">
                                            <td class="py-3 pr-2">
                                                <select name="doc_type[]"
                                                    class="w-full border border-slate-200 rounded-lg p-2 outline-none">
                                                    <option value="quotation" <?= $d['doc_type'] == 'quotation' ? 'selected' : '' ?>>Quotation</option>
                                                    <option value="pr" <?= $d['doc_type'] == 'pr' ? 'selected' : '' ?>>PR</option>
                                                    <option value="po" <?= $d['doc_type'] == 'po' ? 'selected' : '' ?>>PO</option>
                                                </select>
                                            </td>
                                            <td class="py-3 pr-2">
                                                <input type="text" name="doc_no[]" value="<?= htmlspecialchars($d['doc_no']) ?>"
                                                    required class="w-full border border-slate-200 rounded-lg p-2 outline-none"
                                                    placeholder="ระบุเลขที่เอกสาร...">
                                            </td>
                                            <td class="py-3 text-center">
                                                <button type="button" onclick="deleteDocRow(this)"
                                                    class="text-rose-400 hover:text-rose-600"><i
                                                        class="fas fa-times"></i></button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr class="border-b border-slate-50">
                                        <td class="py-3 pr-2">
                                            <select name="doc_type[]"
                                                class="w-full border border-slate-200 rounded-lg p-2 outline-none">
                                                <option value="quotation">Quotation</option>
                                                <option value="pr">PR</option>
                                                <option value="po">PO</option>
                                            </select>
                                        </td>
                                        <td class="py-3 pr-2">
                                            <input type="text" name="doc_no[]"
                                                class="w-full border border-slate-200 rounded-lg p-2 outline-none"
                                                placeholder="ระบุเลขที่เอกสาร...">
                                        </td>
                                        <td class="py-3 text-center">
                                            <button type="button" onclick="deleteDocRow(this)" class="text-rose-400"><i
                                                    class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-6 mt-6">
                    <label class="block text-sm font-bold text-slate-700 mb-1">หมายเหตุเพิ่มเติม (Remarks)</label>
                    <textarea name="project_remarks" rows="3"
                        class="w-full border border-slate-200 rounded-xl p-2.5 outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="ระบุเงื่อนไขพิเศษหรือบันทึกเพิ่มเติม..."><?= htmlspecialchars($pj['project_remarks'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-900 rounded-2xl p-6 text-white ">
                    <div class="flex justify-between items-center mb-4 border-b border-indigo-800 pb-2">
                        <h3 class="font-bold text-indigo-300">สรุปมูลค่างาน</h3>
                        <label class="inline-flex items-center cursor-pointer">
                            <span class="mr-2 text-[10px] font-bold text-slate-400">VAT 7%</span>
                            <input type="checkbox" id="vat_toggle" name="include_vat" value="yes"
                                onchange="calculateNetValue()" class="hidden peer" <?php
                                // เช็คว่ามีตัวแปร และค่ามากกว่า 0 จริงๆ
                                if (isset($pj['total_vat_amount']) && floatval($pj['total_vat_amount']) > 0) {
                                    echo 'checked';
                                }
                                ?>>

                            <div
                                class="w-9 h-5 bg-slate-700 rounded-full peer peer-checked:bg-indigo-600 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full">
                            </div>
                        </label>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span class="text-slate-400">มูลค่างาน:</span> <span
                                id="display_base">0.00</span></div>
                        <div id="vat_row" class="flex justify-between transition-all"><span class="text-slate-400">VAT
                                (7%):</span> <span id="display_vat">0.00</span></div>
                        <div class="flex justify-between border-t border-slate-800 pt-2"><span
                                class="font-bold">มูลค่าสุทธิ:</span> <span id="display_net"
                                class="font-bold text-lg text-indigo-400">0.00</span></div>
                    </div>

                    <input type="hidden" name="total_vat_amount" id="total_vat_amount"
                        value="<?= $pj['total_vat_amount'] ?>">
                    <input type="hidden" name="net_contract_value" id="net_contract_value"
                        value="<?= $pj['net_contract_value'] ?>">
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-paperclip text-indigo-500"></i> ไฟล์แนบ (เดิม:
                        <?= $pj['attachment_path'] ?: 'ไม่มี' ?>)
                    </h3>
                    <div id="file-container"
                        class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center hover:border-indigo-300 transition-all cursor-pointer relative"
                        onclick="document.getElementById('attachment').click()">
                        <div id="upload-placeholder" class="<?= $pj['attachment_path'] ? 'hidden' : '' ?>">
                            <i class="fas fa-file-pdf text-3xl text-slate-300 mb-2"></i>
                            <p class="text-[10px] text-slate-500 italic">คลิกเพื่อเปลี่ยนไฟล์สัญญาใหม่</p>
                        </div>
                        <input type="file" name="attachment" id="attachment" class="hidden" onchange="updateFileName()">
                        <div id="file-info" class="<?= $pj['attachment_path'] ? '' : 'hidden' ?>">
                            <i class="fas fa-check-circle text-2xl text-emerald-500 mb-2"></i>
                            <p id="file-name-display" class="text-xs text-slate-700 font-bold truncate">
                                <?= $pj['attachment_path'] ?>
                            </p>
                            <button type="button" onclick="resetFile(event)"
                                class="mt-3 text-[10px] bg-red-50 text-red-500 px-3 py-1 rounded-full hover:bg-red-100">เปลี่ยนไฟล์</button>
                        </div>
                    </div>
                </div>
                <div class="bg-amber-50 rounded-2xl p-5 border border-amber-100 mt-6">
                    <h4 class="font-bold text-amber-800 mb-2 text-sm flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i> คำแนะนำและข้อควรระวัง
                    </h4>
                    <ul class="text-[11px] text-amber-700 space-y-2 list-disc ml-4">
                        <li>
                            <strong>ตรวจสอบยอดภาษี:</strong> หากพบว่า <strong>"ยอดรวมสุทธิเพี้ยน"</strong>
                            ให้ตรวจสอบที่ปุ่ม Toggle VAT 7% ว่าเปิดหรือปิดอยู่ตามที่ต้องการหรือไม่
                            (ค่าเริ่มต้นจะถูกตั้งไว้ตามข้อมูลล่าสุดในระบบ)
                        </li>
                        <li>
                            <strong>ข้อมูลธนาคาร:</strong> ข้อมูลส่วนนี้จะถูกดึงไปแสดงในใบเบิกเงิน (Payment Requisition)
                            หากมีการเปลี่ยนบัญชีรับเงินของโครงการ ต้องมาอัปเดตที่หน้านี้เพื่อให้ยอดโอนถูกต้อง
                        </li>
                        <li>
                            <strong>การจัดการไฟล์:</strong> หากต้องการเปลี่ยนไฟล์แนบใหม่ จารสามารถกด
                            <strong>"เปลี่ยนไฟล์"</strong>
                            แล้วเลือกไฟล์ใหม่ทับได้ทันที ระบบจะใช้ไฟล์ล่าสุดในการอ้างอิงเสมอ
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // 1. ฟังก์ชันคำนวณ (ใช้ Logic จากหน้าสร้างงาน)
    function calculateNetValue() {
        const base = parseFloat(document.getElementById('contract_value').value) || 0;
        const isVatEnabled = document.getElementById('vat_toggle').checked;
        const vat = isVatEnabled ? base * 0.07 : 0;
        const net = base + vat;

        document.getElementById('total_vat_amount').value = vat.toFixed(2);
        document.getElementById('net_contract_value').value = net.toFixed(2);

        document.getElementById('display_base').innerText = base.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('display_vat').innerText = vat.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('display_net').innerText = net.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('vat_row').style.opacity = isVatEnabled ? '1' : '0.3';
    }

    // 2. จัดการหน้าจอเมื่อโหลดเสร็จ
    document.addEventListener('DOMContentLoaded', () => {
        calculateNetValue(); // คำนวณยอดทันทีที่เข้าหน้า
    });

    // 3. จัดการไฟล์ และ Doc Rows (ใช้ฟังก์ชันเดิมที่จารมีในหน้าสร้างงานได้เลย)
    function addDocRow() {
        const tbody = document.getElementById('docBody');
        const newRow = document.createElement('tr');
        newRow.className = "border-b border-slate-50";
        newRow.innerHTML = `
            <td class="py-3 pr-2">
                <select name="doc_type[]" class="w-full border border-slate-200 rounded-lg p-2 outline-none">
                    <option value="quotation">Quotation</option>
                    <option value="pr">PR</option>
                    <option value="po">PO</option>
                </select>
            </td>
            <td class="py-3 pr-2">
                <input type="text" name="doc_no[]" required class="w-full border border-slate-200 rounded-lg p-2 outline-none" placeholder="เลขที่เอกสาร...">
            </td>
            <td class="py-3 text-center">
                <button type="button" onclick="deleteDocRow(this)" class="text-rose-400 hover:text-rose-600"><i class="fas fa-times"></i></button>
            </td>
        `;
        tbody.appendChild(newRow);
    }

    function deleteDocRow(btn) { btn.closest('tr').remove(); }

    function updateFileName() {
        const fileInput = document.getElementById('attachment');
        if (fileInput.files && fileInput.files[0]) {
            document.getElementById('upload-placeholder').classList.add('hidden');
            document.getElementById('file-info').classList.remove('hidden');
            document.getElementById('file-name-display').innerText = fileInput.files[0].name;
        }
    }

    function resetFile(event) {
        event.stopPropagation();
        document.getElementById('attachment').value = '';
        document.getElementById('upload-placeholder').classList.remove('hidden');
        document.getElementById('file-info').classList.add('hidden');
    }
</script>