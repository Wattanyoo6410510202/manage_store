<?php
require_once 'config.php';
include('header.php');

// ดึงรายชื่อลูกค้ามาใส่ใน Select
$customers = mysqli_query($conn, "SELECT id, customer_name FROM customers ORDER BY customer_name ASC");
?>

<div>
    <form action="api/save_project.php" method="POST" enctype="multipart/form-data">
        <div class="flex justify-between items-center mb-6">

            <div>

                <h2 class="text-2xl font-bold text-slate-800">สร้างงานใหม่</h2>
                <p class="text-slate-500 text-sm">ระบุรายละเอียดงานและตั้งค่าการชำระเงิน</p>
            </div>
            <div class="flex gap-3">
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-2.5 rounded-xl font-bold  transition-all">
                    <i class="fas fa-save mr-2"></i> บันทึกงาน
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl --sm border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-info-circle text-indigo-500"></i> ข้อมูลงาน
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่องาน <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="project_name" required
                                class="w-full border border-slate-200 rounded-xl p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none"
                                placeholder="เช่น งานพ่นผนัง ">
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
                            <label class="block text-sm font-bold text-slate-700 mb-1">

                                มูลค่างาน</label>
                            <input type="number" step="0.01" name="contract_value" id="contract_value"
                                oninput="calculateNetValue()"
                                class="w-full border border-slate-200 rounded-xl p-2.5  outline-none font-bold"
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

                <div class="bg-white rounded-2xl --sm border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-user-tie text-indigo-500"></i> ข้อมูลผู้รับจ้างและการชำระเงิน
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อผู้รับจ้าง /
                                บริษัทผู้รับจ้าง</label>
                            <input type="text" name="contractor_name"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none"
                                placeholder="เช่น นาย.... ....">
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

                <div class="bg-white rounded-2xl --sm border border-slate-200 p-6 mt-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-link text-indigo-500"></i> เอกสารเชื่อมโยง (project_documents)
                        </h3>
                        <button type="button" onclick="addDocRow()"
                            class="text-indigo-600 hover:text-indigo-800 font-bold text-sm bg-indigo-50 px-4 py-2 rounded-lg transition-all">
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
                <div class="bg-white rounded-2xl --sm border border-slate-200 p-6 mt-6">
                    <label class="block text-sm font-bold text-slate-700 mb-1">หมายเหตุเพิ่มเติม (Remarks)</label>
                    <textarea name="project_remarks" rows="3"
                        class="w-full border border-slate-200 rounded-xl p-2.5 outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="ระบุเงื่อนไขพิเศษหรือบันทึกเพิ่มเติม..."></textarea>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                    <div class="flex justify-between items-center mb-4 border-b border-indigo-800 pb-2 gap-4">
                        <h3 class="font-bold text-indigo-300">สรุปมูลค่างาน</h3>

                        <div class="flex gap-4">
                            <label class="inline-flex items-center cursor-pointer">
                                <span class="mr-2 text-[10px] font-bold text-slate-400 uppercase">คิดภาษี VAT
                                    (7%)</span>
                                <input type="checkbox" id="vat_toggle" name="include_vat" value="yes" checked
                                    class="hidden peer" onchange="calculateNetValue()">
                                <div
                                    class="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600 relative">
                                </div>
                            </label>

                            <label class="inline-flex items-center cursor-pointer">
                                <span class="mr-2 text-[10px] font-bold text-slate-400 uppercase">หัก ณ ที่จ่าย
                                    (3%)</span>
                                <input type="checkbox" id="wht_toggle" name="include_wht" value="yes" checked
                                    class="hidden peer" onchange="calculateNetValue()">
                                <div
                                    class="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-500 relative">
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-400">มูลค่างาน:</span>
                            <span id="display_base">0.00</span>
                        </div>
                        <div id="vat_row" class="flex justify-between transition-all duration-300">
                            <span class="text-slate-400">VAT (7%):</span>
                            <input type="hidden" name="total_vat_amount" id="total_vat_amount">
                            <span id="display_vat" class="text-indigo-400">+ 0.00</span>
                        </div>

                        <div id="wht_row" class="flex justify-between transition-all duration-300">
                            <span class="text-slate-400">หัก ณ ที่จ่าย (3%):</span>
                            <input type="hidden" name="total_wht_amount" id="total_wht_amount">
                            <span id="display_wht" class="text-rose-400">- 0.00</span>
                        </div>

                        <div class="flex justify-between border-t border-slate-800 pt-2">
                            <span class="font-bold text-slate-200">มูลค่าสุทธิ:</span>
                            <input type="hidden" name="net_contract_value" id="net_contract_value">
                            <div class="text-right">
                                <span id="display_net" class="font-bold text-lg text-indigo-400">0.00</span>
                                <p class="text-[10px] text-slate-500 font-normal">บาท (รวม VAT หัก WHT)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl --sm border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-paperclip text-indigo-500"></i> ไฟล์แนบงาน
                    </h3>
                    <div id="file-container"
                        class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center hover:border-indigo-300 transition-all cursor-pointer relative group"
                        onclick="document.getElementById('attachment').click()">

                        <div id="upload-placeholder">
                            <i
                                class="fas fa-file-pdf text-3xl text-slate-300 mb-2 group-hover:text-indigo-400 transition-colors"></i>
                            <p class="text-[10px] text-slate-500">สัญญา หรือ BOQ (PDF, JPG)</p>
                            <p class="text-[9px] text-indigo-400 mt-1 italic">คลิกเพื่อเลือกไฟล์ หรือเปลี่ยนไฟล์ใหม่</p>
                        </div>

                        <input type="file" name="attachment" id="attachment" class="hidden" onchange="updateFileName()">

                        <div id="file-info" class="hidden">
                            <i class="fas fa-check-circle text-2xl text-indigo-500 mb-2"></i>
                            <p id="file-name-display" class="text-xs text-slate-700 font-bold truncate px-4"></p>
                            <button type="button" onclick="resetFile(event)"
                                class="mt-3 text-[10px] bg-red-50 text-red-500 px-3 py-1 rounded-full hover:bg-red-100 transition-all">
                                <i class="fas fa-sync-alt mr-1"></i> เลือกไฟล์ใหม่
                            </button>
                        </div>
                    </div>
                </div>
                <div class="bg-amber-50 rounded-2xl p-5 border border-amber-100 mt-6">
                    <h4 class="font-bold text-amber-800 mb-2 text-sm flex items-center gap-2">
                        <i class="fas fa-lightbulb"></i> คำแนะนำและการใช้งาน
                    </h4>
                    <ul class="text-[11px] text-amber-700 space-y-2 list-disc ml-4">
                        <li>
                            <strong>การคำนวณภาษี:</strong> ระบบจะคำนวณ VAT 7% ให้อัตโนมัติ
                            จารสามารถเลือก <strong>"ปิด"</strong> การคิดภาษีได้ที่ปุ่ม Toggle ในส่วนสรุปมูลค่างาน
                        </li>
                        <li>
                            <strong>ข้อมูลธนาคาร:</strong> ข้อมูลนี้สำคัญมาก จะถูกนำไปใช้ในใบเบิกเงิน (Payment
                            Requisition) เพื่อโอนเงินให้ผู้รับจ้าง
                        </li>
                        <li>
                            <strong>เอกสารแนบ:</strong> แนะนำให้แนบไฟล์สัญญาหรือ BOQ ในรูปแบบ PDF
                            เพื่อใช้ตรวจสอบย้อนหลังได้ง่าย
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    /**
     * 1. ฟังก์ชันหลักสำหรับคำนวณยอดรวม (Items + VAT Toggle)
     * ใช้สำหรับหน้าที่มีตารางรายการสินค้า (Item Rows)
     */
    function calculateTotal() {
        let subtotal = 0;

        // วนลูปหาผลรวมจากทุกแถวในตารางรายการ
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
            const price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
            const discount = parseFloat(row.querySelector('[name="item_discount[]"]')?.value) || 0;

            // คำนวณรายบรรทัด
            const lineTotal = (qty * price) - discount;

            // ถ้ามี Element แสดงยอดรวมรายบรรทัด ให้ใส่ค่าลงไปด้วย
            const rowTotalDisplay = row.querySelector('.row-total');
            if (rowTotalDisplay) {
                rowTotalDisplay.innerText = lineTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
            }

            subtotal += lineTotal;
        });

        // ตรวจสอบสถานะปุ่ม Toggle VAT (ID: vat_toggle)
        const vatToggle = document.getElementById('vat_toggle');
        const isVatEnabled = vatToggle ? vatToggle.checked : true; // ถ้าไม่มีปุ่ม ให้ถือว่าคิด VAT ตามปกติ

        let vatAmount = 0;
        const vatRow = document.getElementById('vat_row');

        if (isVatEnabled) {
            vatAmount = subtotal * 0.07;
            if (vatRow) vatRow.style.opacity = '1';
        } else {
            vatAmount = 0;
            if (vatRow) vatRow.style.opacity = '0.3'; // จางลงเมื่อไม่คิดภาษี
        }

        const netTotal = subtotal + vatAmount;

        // อัปเดตการแสดงผลบน UI (ตัวเลขโชว์)
        if (document.getElementById('display_base'))
            document.getElementById('display_base').innerText = subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });

        if (document.getElementById('display_vat'))
            document.getElementById('display_vat').innerText = vatAmount.toLocaleString(undefined, { minimumFractionDigits: 2 });

        if (document.getElementById('display_net'))
            document.getElementById('display_net').innerText = netTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });

        // เก็บค่าเข้า Hidden Input เพื่อส่งไปที่ PHP
        if (document.getElementById('total_vat_amount'))
            document.getElementById('total_vat_amount').value = vatAmount.toFixed(2);

        if (document.getElementById('net_contract_value'))
            document.getElementById('net_contract_value').value = netTotal.toFixed(2);

        // กรณีหน้า PO ที่จารใช้ชื่อฟิลด์ต่างออกไปเล็กน้อย
        if (document.getElementById('subtotal_display'))
            document.getElementById('subtotal_display').innerText = subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
    }

    function calculateNetValue() {
        // 1. ดึงค่าจาก input ที่จารส่งมา (ID: contract_value)
        let contractInput = document.getElementById('contract_value');
        let baseValue = parseFloat(contractInput.value) || 0;

        // 2. เช็คสถานะ Toggle (เช็คว่า ID ตรงกับปุ่มสวิตช์ที่ทำไว้ไหม)
        let vatToggle = document.getElementById('vat_toggle');
        let whtToggle = document.getElementById('wht_toggle');

        let isVat = (vatToggle && vatToggle.checked);
        let isWht = (whtToggle && whtToggle.checked);

        // 3. คำนวณภาษี
        let vatAmount = isVat ? (baseValue * 0.07) : 0;
        let whtAmount = isWht ? (baseValue * 0.03) : 0;

        // 4. คำนวณยอดสุทธิ
        let netValue = baseValue + vatAmount - whtAmount;

        // 5. อัปเดตตัวเลขบนหน้าจอ (ต้องมี ID เหล่านี้ในส่วนแสดงผล)
        if (document.getElementById('display_base')) {
            document.getElementById('display_base').innerText = baseValue.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (document.getElementById('display_vat')) {
            document.getElementById('display_vat').innerText = "+ " + vatAmount.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (document.getElementById('display_wht')) {
            document.getElementById('display_wht').innerText = "- " + whtAmount.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (document.getElementById('display_net')) {
            document.getElementById('display_net').innerText = netValue.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // 6. เก็บค่าเข้า Hidden Inputs (สำหรับส่งลง Database)
        if (document.getElementById('total_vat_amount')) document.getElementById('total_vat_amount').value = vatAmount.toFixed(2);
        if (document.getElementById('total_wht_amount')) document.getElementById('total_wht_amount').value = whtAmount.toFixed(2);
        if (document.getElementById('net_contract_value')) document.getElementById('net_contract_value').value = netValue.toFixed(2);
    }

    // ป้องกันกรณีที่แก้เลขแล้วกด Toggle แล้วเลขไม่ขยับ
    document.addEventListener('change', function (e) {
        if (e.target.id === 'vat_toggle' || e.target.id === 'wht_toggle') {
            calculateNetValue();
        }
    });

    /**
     * 3. จัดการเอกสารแนบ (Show File Name)
     */
    function updateFileName() {
        const fileInput = document.getElementById('attachment');
        const fileNameDisplay = document.getElementById('file-name');
        if (fileInput && fileInput.files[0]) {
            fileNameDisplay.innerText = "✓ " + fileInput.files[0].name;
        }
    }

    /**
     * 4. จัดการแถวเอกสารอ้างอิง (Add/Delete Row)
     */
    function addDocRow() {
        const tbody = document.getElementById('docBody');
        if (!tbody) return;

        const newRow = document.createElement('tr');
        newRow.className = "border-b border-slate-50 group";
        newRow.innerHTML = `
            <td class="py-3 pr-2">
                <select name="doc_type[]" class="w-full border border-slate-200 rounded-xl p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    <option value="quotation">Quotation</option>
                    <option value="pr">PR</option>
                    <option value="po">PO</option>
                    <option value="other">อื่น ๆ</option>
                </select>
            </td>
            <td class="py-3 pr-2">
                <input type="text" name="doc_no[]" required 
                    class="w-full border border-slate-200 rounded-xl p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 transition-all" 
                    placeholder="ระบุเลขที่เอกสาร...">
            </td>
            <td class="py-3 text-center">
                <button type="button" onclick="deleteDocRow(this)" 
                    class="w-8 h-8 rounded-full flex items-center justify-center text-red-400 hover:bg-red-50 hover:text-red-600 transition-all">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        `;
        tbody.appendChild(newRow);
    }

    function deleteDocRow(btn) {
        const row = btn.closest('tr');
        if (row) row.remove();
    }

    // เรียกใช้ครั้งแรกเมื่อโหลดหน้า
    document.addEventListener('DOMContentLoaded', () => {
        calculateTotal();
    });

    function updateFileName() {
        const fileInput = document.getElementById('attachment');
        const placeholder = document.getElementById('upload-placeholder');
        const fileInfo = document.getElementById('file-info');
        const fileNameDisplay = document.getElementById('file-name-display');

        if (fileInput.files && fileInput.files[0]) {
            // เมื่อมีการเลือกไฟล์
            placeholder.classList.add('hidden');
            fileInfo.classList.remove('hidden');
            fileNameDisplay.innerText = fileInput.files[0].name;

            // เปลี่ยนสีเส้นขอบให้เป็นสีเขียวเพื่อบอกว่า OK
            document.getElementById('file-container').classList.replace('border-slate-200', 'border-indigo-200');
        }
    }

    // ฟังก์ชันสำหรับล้างค่าไฟล์เพื่อเลือกใหม่
    function resetFile(event) {
        event.stopPropagation(); // กันไม่ให้มันไปลั่นคำสั่ง click ของตัว container
        const fileInput = document.getElementById('attachment');
        const placeholder = document.getElementById('upload-placeholder');
        const fileInfo = document.getElementById('file-info');

        fileInput.value = ''; // ล้างค่าใน input file
        placeholder.classList.remove('hidden');
        fileInfo.classList.add('hidden');

        // คืนสีเส้นขอบ
        document.getElementById('file-container').classList.replace('border-indigo-200', 'border-slate-200');
    }
</script>
<?php include('footer.php'); ?>