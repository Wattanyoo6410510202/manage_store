<?php
require_once 'config.php';
include('header.php');

// ดึงรายชื่อลูกค้ามาใส่ใน Select
$customers = mysqli_query($conn, "SELECT id, customer_name FROM customers ORDER BY customer_name ASC");
?>

<div class="p-6">
    <form action="api/save_project.php" method="POST" enctype="multipart/form-data">
        <div class="flex justify-between items-center mb-6">

            <div>

                <h2 class="text-2xl font-bold text-slate-800">สร้างโครงการใหม่</h2>
                <p class="text-slate-500 text-sm">ระบุรายละเอียดโครงการและตั้งค่าการชำระเงิน</p>
            </div>
            <div class="flex gap-3">
                <a href="projects.php"
                    class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all">ยกเลิก</a>
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all">
                    <i class="fas fa-save mr-2"></i> บันทึกโครงการ
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-info-circle text-indigo-500"></i> ข้อมูลโครงการ
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อโครงการ <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="project_name" required
                                class="w-full border border-slate-200 rounded-xl p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none"
                                placeholder="เช่น งานพ่นผนัง TEXTURE">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">เลขที่โครงการ</label>
                            <input type="text" name="project_no"
                                class="w-full border border-slate-200 rounded-xl p-2.5 bg-slate-50"
                                placeholder="PJ-XXXX (เว้นว่างเพื่อ Auto)">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">ลูกค้า</label>
                            <select name="customer_id"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                                <option value="">-- เลือกบริษัทลูกค้า --</option>
                                <?php while ($c = mysqli_fetch_assoc($customers)): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['customer_name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">มูลค่าสัญญา (Base Amount)</label>
                            <input type="number" step="0.01" name="contract_value" id="contract_value"
                                oninput="calculateNetValue()"
                                class="w-full border border-slate-200 rounded-xl p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-indigo-600"
                                placeholder="0.00">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">วันที่เริ่ม</label>
                                <input type="date" name="start_date"
                                    class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">วันที่สิ้นสุด</label>
                                <input type="date" name="end_date"
                                    class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-user-tie text-indigo-500"></i> ข้อมูลผู้รับจ้างและการชำระเงิน
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อผู้รับจ้าง /
                                บริษัทผู้รับจ้าง</label>
                            <input type="text" name="contractor_name"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none"
                                placeholder="เช่น นายสุเทพ สายวารี">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">ธนาคาร</label>
                            <input type="text" name="bank_name"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none"
                                placeholder="เช่น กสิกรไทย">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">เลขที่บัญชี</label>
                            <input type="text" name="bank_account_no"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none"
                                placeholder="000-0-00000-0">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อบัญชี</label>
                            <input type="text" name="bank_account_name"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none"
                                placeholder="ชื่อ-นามสกุล เจ้าของบัญชี">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mt-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-link text-indigo-500"></i> เอกสารเชื่อมโยง (project_documents)
                        </h3>
                        <button type="button" onclick="addDocRow()"
                            class="text-emerald-600 hover:text-emerald-800 font-bold text-sm bg-emerald-50 px-4 py-2 rounded-lg transition-all">
                            <i class="fas fa-plus-circle mr-1"></i> เพิ่มเอกสาร
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-slate-500 text-sm border-b border-slate-100">
                                    <th class="pb-3" width="30%">ประเภทเอกสาร (doc_type)</th>
                                    <th class="pb-3" width="60%">เลขที่เอกสาร (doc_no)</th>
                                    <th class="pb-3 text-center" width="10%">ลบ</th>
                                </tr>
                            </thead>
                            <tbody id="docBody">
                                <tr class="border-b border-slate-50">
                                    <td class="py-3 pr-2">
                                        <select name="doc_type[]"
                                            class="w-full border border-slate-200 rounded-lg p-2 outline-none focus:ring-1 focus:ring-indigo-500">
                                            <option value="quotation">Quotation (ใบเสนอราคา)</option>
                                            <option value="pr">PR (ใบขอซื้อ)</option>
                                            <option value="po">PO (ใบสั่งซื้อ)</option>
                                        </select>
                                    </td>
                                    <td class="py-3 pr-2">
                                        <input type="text" name="doc_no[]" required
                                            class="w-full border border-slate-200 rounded-lg p-2 outline-none focus:ring-1 focus:ring-indigo-500"
                                            placeholder="ระบุเลขที่เอกสาร...">
                                    </td>
                                    <td class="py-3 text-center">
                                        <button type="button" onclick="deleteDocRow(this)"
                                            class="text-slate-300 hover:text-red-500 transition-all">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mt-6">
                    <label class="block text-sm font-bold text-slate-700 mb-1">หมายเหตุเพิ่มเติม (Remarks)</label>
                    <textarea name="project_remarks" rows="3"
                        class="w-full border border-slate-200 rounded-xl p-2.5 outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="ระบุเงื่อนไขพิเศษหรือบันทึกเพิ่มเติม..."></textarea>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                    <h3 class="font-bold mb-4 text-indigo-300 border-b border-indigo-800 pb-2">สรุปมูลค่าสัญญา</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-400">มูลค่างาน:</span>
                            <span id="display_base">0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">VAT (7%):</span>
                            <input type="hidden" name="total_vat_amount" id="total_vat_amount">
                            <span id="display_vat" class="text-emerald-400">0.00</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-800 pt-2">
                            <span class="font-bold">มูลค่าสุทธิ:</span>
                            <input type="hidden" name="net_contract_value" id="net_contract_value">
                            <span id="display_net" class="font-bold text-lg text-indigo-400">0.00</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-paperclip text-indigo-500"></i> ไฟล์แนบโครงการ
                    </h3>
                    <div class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center hover:border-indigo-300 transition-all cursor-pointer"
                        onclick="document.getElementById('attachment').click()">
                        <i class="fas fa-file-pdf text-3xl text-slate-300 mb-2"></i>
                        <p class="text-[10px] text-slate-500">สัญญา หรือ BOQ (PDF, JPG)</p>
                        <input type="file" name="attachment" id="attachment" class="hidden" onchange="updateFileName()">
                        <p id="file-name" class="mt-2 text-xs text-indigo-600 font-bold truncate"></p>
                    </div>
                </div>

                <div class="bg-amber-50 rounded-2xl p-5 border border-amber-100">
                    <h4 class="font-bold text-amber-800 mb-2 text-sm flex items-center gap-2">
                        <i class="fas fa-lightbulb"></i> คำแนะนำ
                    </h4>
                    <ul class="text-[11px] text-amber-700 space-y-2 list-disc ml-4">
                        <li>ระบบจะคำนวณ VAT 7% ให้อัตโนมัติจากมูลค่าสัญญา</li>
                        <li>ข้อมูลธนาคารจะถูกนำไปใช้ในใบเบิกเงิน (Payment Requisition)</li>
                    </ul>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function calculateNetValue() {
        const base = parseFloat(document.getElementById('contract_value').value) || 0;
        const vat = base * 0.07;
        const net = base + vat;

        // ใส่ค่าลงใน Input (Hidden) เพื่อส่งไป PHP
        document.getElementById('total_vat_amount').value = vat.toFixed(2);
        document.getElementById('net_contract_value').value = net.toFixed(2);

        // แสดงผลบน UI
        document.getElementById('display_base').innerText = base.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('display_vat').innerText = vat.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('display_net').innerText = net.toLocaleString(undefined, { minimumFractionDigits: 2 });
    }

    function updateFileName() {
        const file = document.getElementById('attachment').files[0];
        if (file) {
            document.getElementById('file-name').innerText = "✓ " + file.name;
        }
    }
    function addDocRow() {
        const tbody = document.getElementById('docBody');
        const newRow = document.createElement('tr');
        newRow.className = "border-b border-slate-50";
        newRow.innerHTML = `
        <td class="py-3 pr-2">
            <select name="doc_type[]" class="w-full border border-slate-200 rounded-lg p-2 outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="quotation">Quotation</option>
                <option value="pr">PR</option>
                <option value="po">PO</option>
            </select>
        </td>
        <td class="py-3 pr-2">
            <input type="text" name="doc_no[]" required class="w-full border border-slate-200 rounded-lg p-2 outline-none focus:ring-1 focus:ring-indigo-500" placeholder="ระบุเลขที่เอกสาร...">
        </td>
        <td class="py-3 text-center">
            <button type="button" onclick="deleteDocRow(this)" class="text-red-400 hover:text-red-600 transition-all">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    `;
        tbody.appendChild(newRow);
    }

    function deleteDocRow(btn) {
        btn.closest('tr').remove();
    }
</script>

<?php include('footer.php'); ?>