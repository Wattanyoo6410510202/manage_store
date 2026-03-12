<?php
require_once 'config.php';
include 'header.php';

// รับ ID ลูกค้า หรือ โปรเจกต์ เพื่อดึงข้อมูลเบื้องต้น
$customer_id = $_GET['customer_id'] ?? 0;
$project_id = $_GET['project_id'] ?? 0;

// 1. ดึงข้อมูลลูกค้า (ผู้ซื้อ)
$cust_query = mysqli_query($conn, "SELECT * FROM customers WHERE id = '" . mysqli_real_escape_string($conn, $customer_id) . "'");
$customer = mysqli_fetch_assoc($cust_query);

// 2. ดึงข้อมูลบริษัทเรา (ผู้ขาย/ผู้ประเมิน) จากตาราง suppliers
$suppliers_query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY id ASC");
$suppliers = [];
while ($s = mysqli_fetch_assoc($suppliers_query)) {
    $suppliers[] = $s;
}

if (!$customer) {
    echo "<script>alert('ไม่พบข้อมูลลูกค้า'); window.location.href='customers.php';</script>";
    exit;
}

// สร้างเลขที่ใบแจ้งหนี้เบื้องต้น
$default_inv_no = "INV" . date('Ym') . "-001";
?>

<form action="api/save_invoice.php" method="POST" id="invoiceForm">
    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer_id) ?>">
    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">
    
    <input type="hidden" name="sub_total" id="hidden_subtotal" value="0.00">
    <input type="hidden" name="vat_amount" id="hidden_vat_amount" value="0.00">
    <input type="hidden" name="wht_amount" id="hidden_wht_amount" value="0.00">
    <input type="hidden" name="total_amount" id="hidden_total_amount" value="0.00">

    <div class="bg-slate-50 min-h-screen pb-20">
        <div class="max-w-[1400px] mx-auto p-4 md:p-6">
            
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <div>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight">สร้างใบแจ้งหนี้ใหม่</h2>
                    <p class="text-slate-500 text-sm font-bold">New Invoice Creation</p>
                </div>
                <div class="flex gap-3">
                    <a href="invoices.php" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-2xl font-bold text-sm hover:bg-slate-50 transition-all">ยกเลิก</a>
                    <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white rounded-2xl font-black text-sm hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">บันทึกเอกสาร</button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-xs font-black text-slate-400 flex items-center gap-2 uppercase tracking-[0.2em]">
                                <i class="fas fa-building text-indigo-500 text-base"></i> Issuer / ผู้ขาย
                            </h3>
                            <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                <img id="comp_logo_preview" src="uploads/default-logo.png" class="h-10 w-10 object-contain">
                            </div>
                        </div>

                        <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">เลือกบริษัทผู้ออกบิล</label>
                        <select name="supplier_id" id="supplier_select" onchange="updateSupplierInfo()"
                            class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-2xl text-sm focus:border-indigo-500 focus:bg-white outline-none font-bold text-slate-700 mb-5 transition-all cursor-pointer">
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>" data-info='<?= json_encode($sup, ENT_QUOTES) ?>'>
                                    <?= htmlspecialchars($sup['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="p-5 bg-gradient-to-br from-indigo-50/50 to-slate-50 rounded-2xl border border-indigo-100/50 space-y-4">
                            <div>
                                <div id="comp_name" class="text-base font-black text-slate-800 leading-tight">-</div>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="text-[10px] font-black bg-indigo-600 text-white px-2 py-0.5 rounded uppercase tracking-wider">Tax ID</span>
                                    <span id="comp_tax" class="text-xs font-mono font-bold text-slate-600">-</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-2 border-t border-indigo-100/50 pt-3">
                                <div class="flex items-center gap-3 text-xs text-slate-600 font-bold">
                                    <i class="fas fa-phone-alt text-indigo-500 w-4 text-center"></i>
                                    <span id="comp_phone">-</span>
                                </div>
                                <div id="comp_addr" class="text-[11px] text-slate-500 leading-relaxed font-medium italic">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 items-end">
                            <div class="col-span-2 md:col-span-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">เลขที่ใบแจ้งหนี้</label>
                                <input type="text" name="invoice_no" value="<?= $default_inv_no ?>"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-indigo-600 outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">วันที่ออกบิล</label>
                                <input type="date" name="invoice_date" id="invoice_date" value="<?= date('Y-m-d') ?>"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">วันครบกำหนด</label>
                                <input type="date" name="due_date" id="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:border-indigo-500 text-red-500">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">สถานะ</label>
                                <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                                    <option value="pending" selected>รอชำระ (Pending)</option>
                                    <option value="paid">ชำระแล้ว (Paid)</option>
                                    <option value="canceled">ยกเลิก (Canceled)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t border-slate-100">
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">ภาษี (VAT %)</label>
                                <select name="vat_percent" id="vat_percent" onchange="calculateAll()"
                                    class="w-full px-3 py-2 bg-indigo-50 border border-indigo-100 rounded-xl text-sm font-black text-indigo-700 outline-none focus:border-indigo-500 cursor-pointer">
                                    <option value="7" selected>7% (Standard)</option>
                                    <option value="0">0% (Non-VAT)</option>
                                    <option value="10">10%</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">หัก ณ ที่จ่าย (WHT %)</label>
                                <select name="wht_percent" id="wht_percent" onchange="calculateAll()"
                                    class="w-full px-3 py-2 bg-rose-50 border border-rose-100 rounded-xl text-sm font-black text-rose-700 outline-none focus:border-indigo-500 cursor-pointer">
                                    <option value="0" selected>ไม่หัก (0%)</option>
                                    <option value="1">ค่าขนส่ง (1%)</option>
                                    <option value="3">บริการ/จ้างทำของ (3%)</option>
                                    <option value="5">ค่าเช่า/โฆษณา (5%)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 rounded-3xl border border-slate-800 p-6 relative overflow-hidden shadow-xl">
                        <div class="absolute -right-4 -top-4 text-slate-800 opacity-20 text-7xl rotate-12">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 class="text-xs font-black text-slate-500 mb-4 uppercase tracking-[0.2em] relative">Bill To / ผู้ซื้อ</h3>
                        <div class="relative z-10">
                            <div class="text-lg font-black text-white mb-2"><?= htmlspecialchars($customer['customer_name']) ?></div>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="text-slate-500 font-bold">Tax ID:</span>
                                    <span class="text-indigo-400 font-mono font-bold"><?= $customer['tax_id'] ?: 'ไม่ระบุ' ?></span>
                                </div>
                                <div class="text-[11px] text-slate-400 leading-relaxed border-t border-slate-800 pt-3">
                                    <i class="fas fa-map-marker-alt mr-2 text-slate-600"></i>
                                    <?= htmlspecialchars($customer['address']) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden mt-6 shadow-sm">
                <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <span class="text-sm font-black text-slate-700 uppercase">
                        <i class="fas fa-list mr-2 text-indigo-500"></i> รายการสินค้าและบริการ
                    </span>
                    <button type="button" onclick="addItemRow()"
                        class="px-5 py-2 bg-indigo-600 text-white text-[11px] font-black rounded-xl hover:bg-indigo-700 transition-all">
                        <i class="fas fa-plus mr-1"></i> เพิ่มแถวรายการ
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse" id="itemsTable">
                        <thead class="bg-slate-50 text-[10px] uppercase text-slate-400 font-black border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 w-12 text-center">#</th>
                                <th class="px-4 py-3">รายละเอียด (Description)</th>
                                <th class="px-4 py-3 w-24 text-center">จำนวน</th>
                                <th class="px-4 py-3 w-28 text-center">หน่วยนับ</th>
                                <th class="px-4 py-3 w-32 text-right">ราคา/หน่วย</th>
                                <th class="px-4 py-3 w-32 text-right">รวมเงิน</th>
                                <th class="px-4 py-3 w-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr class="item-row group">
                                <td class="px-4 py-4 text-center text-xs font-bold text-slate-400 row-number">1</td>
                                <td class="px-4 py-4">
                                    <textarea name="item_name[]" placeholder="ระบุชื่อรายการ..." rows="1" oninput="autoResize(this)"
                                        class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
                                </td>
                                <td class="px-4 py-4">
                                    <input type="number" name="qty[]" value="1.00" step="0.01" oninput="calculateAll()"
                                        class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black focus:bg-indigo-50 transition-all">
                                </td>
                                <td class="px-4 py-4">
                                    <input type="text" name="unit_name[]" placeholder="ชิ้น"
                                        class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-bold text-slate-600 focus:bg-indigo-50 transition-all">
                                </td>
                                <td class="px-4 py-4">
                                    <input type="number" name="price_per_unit[]" value="0.00" step="0.01" oninput="calculateAll()"
                                        class="w-full bg-transparent border-none text-right text-sm font-mono font-black text-slate-700 focus:ring-0">
                                </td>
                                <td class="px-4 py-4 text-right text-sm font-mono font-black text-slate-800 row-total">0.00</td>
                                <td class="px-4 py-4 text-center">
                                    <button type="button" onclick="removeRow(this)" class="text-slate-200 hover:text-red-500 transition-colors">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-8 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-start gap-8">
                    <div class="w-full md:flex-grow">
                        <label class="text-[10px] font-bold text-slate-400 uppercase block mb-2 tracking-widest">หมายเหตุ (Remark)</label>
                        <textarea name="remark" rows="3"
                            class="w-full p-4 bg-white border border-slate-200 rounded-2xl text-sm outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                            placeholder="เงื่อนไขการชำระเงิน หรือ ข้อมูลธนาคาร..."></textarea>
                    </div>

                    <div class="w-full md:w-80 space-y-3">
                        <div class="flex justify-between text-sm font-bold text-slate-500">
                            <span>รวมเงิน (Sub-total)</span>
                            <span id="subtotal_display" class="text-slate-800">0.00</span>
                        </div>
                        <div class="flex justify-between text-sm font-bold text-slate-400">
                            <span>ภาษีมูลค่าเพิ่ม (<span id="vat_percent_label">7</span>%)</span>
                            <span id="vat_display">0.00</span>
                        </div>
                        <div class="flex justify-between text-sm font-bold text-rose-400">
                            <span>หัก ณ ที่จ่าย (<span id="wht_percent_label">0</span>%)</span>
                            <span>- <span id="wht_display">0.00</span></span>
                        </div>
                        <div class="flex justify-between text-xl font-black text-indigo-600 pt-2 border-t border-slate-200 mt-2">
                            <span>ยอดสุทธิ</span>
                            <span id="grandtotal_display">0.00</span>
                        </div>

                        <button type="submit"
                            class="w-full mt-6 py-4 bg-indigo-600 text-white font-black rounded-2xl hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 text-base uppercase tracking-wider shadow-lg shadow-indigo-100">
                            <i class="fas fa-save"></i> บันทึกใบแจ้งหนี้
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    // ปรับความสูง Textarea อัตโนมัติ
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }

    // เพิ่มแถวใหม่
    function addItemRow() {
        const tbody = document.querySelector('#itemsTable tbody');
        const rowCount = tbody.querySelectorAll('.item-row').length + 1;
        const newRow = `
        <tr class="item-row group border-t border-slate-50">
            <td class="px-4 py-4 text-center text-xs font-bold text-slate-400 row-number">${rowCount}</td>
            <td class="px-4 py-4">
                <textarea name="item_name[]" placeholder="ระบุชื่อรายการ..." rows="1" oninput="autoResize(this)"
                    class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
            </td>
            <td class="px-4 py-4">
                <input type="number" name="qty[]" value="1.00" step="0.01" oninput="calculateAll()" 
                    class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black focus:bg-indigo-50 transition-all">
            </td>
            <td class="px-4 py-4">
                <input type="text" name="unit_name[]" placeholder="ชิ้น" 
                    class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-bold text-slate-600 focus:bg-indigo-50 transition-all">
            </td>
            <td class="px-4 py-4">
                <input type="number" name="price_per_unit[]" value="0.00" step="0.01" oninput="calculateAll()" 
                    class="w-full bg-transparent border-none text-right text-sm font-mono font-black text-slate-700 focus:ring-0">
            </td>
            <td class="px-4 py-4 text-right text-sm font-mono font-black text-slate-800 row-total">0.00</td>
            <td class="px-4 py-4 text-center">
                <button type="button" onclick="removeRow(this)" class="text-slate-200 hover:text-red-500 transition-colors">
                    <i class="fas fa-times-circle"></i>
                </button>
            </td>
        </tr>`;
        tbody.insertAdjacentHTML('beforeend', newRow);
    }

    // ลบแถว
    function removeRow(btn) {
        const tbody = document.querySelector('#itemsTable tbody');
        if (tbody.rows.length > 1) {
            btn.closest('tr').remove();
            reorderRows();
            calculateAll();
        }
    }

    // จัดลำดับตัวเลขหน้าแถวใหม่
    function reorderRows() {
        document.querySelectorAll('.row-number').forEach((td, index) => {
            td.innerText = index + 1;
        });
    }

    // คำนวณเงินทั้งหมด
    function calculateAll() {
        let subtotal = 0;
        const vatPercent = parseFloat(document.getElementById('vat_percent').value) || 0;
        const whtPercent = parseFloat(document.getElementById('wht_percent').value) || 0;

        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('[name="qty[]"]').value) || 0;
            const price = parseFloat(row.querySelector('[name="price_per_unit[]"]').value) || 0;
            const total = qty * price;

            row.querySelector('.row-total').innerText = total.toLocaleString(undefined, { minimumFractionDigits: 2 });
            subtotal += total;
        });

        const vatAmount = subtotal * (vatPercent / 100);
        const whtAmount = subtotal * (whtPercent / 100);
        const grandTotal = (subtotal + vatAmount) - whtAmount;

        // อัปเดตตัวเลขโชว์หน้าจอ
        document.getElementById('subtotal_display').innerText = subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('vat_display').innerText = vatAmount.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('wht_display').innerText = whtAmount.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('grandtotal_display').innerText = grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });

        document.getElementById('vat_percent_label').innerText = vatPercent;
        document.getElementById('wht_percent_label').innerText = whtPercent;

        // ใส่ค่าลง Hidden Input เพื่อส่ง $_POST
        document.getElementById('hidden_subtotal').value = subtotal.toFixed(2);
        document.getElementById('hidden_vat_amount').value = vatAmount.toFixed(2);
        document.getElementById('hidden_wht_amount').value = whtAmount.toFixed(2);
        document.getElementById('hidden_total_amount').value = grandTotal.toFixed(2);
    }

    // อัปเดตข้อมูลผู้ขายตามที่เลือก
    function updateSupplierInfo() {
        const select = document.getElementById('supplier_select');
        if(!select.options[select.selectedIndex]) return;
        
        const data = JSON.parse(select.options[select.selectedIndex].getAttribute('data-info'));

        document.getElementById('comp_name').innerText = data.company_name;
        document.getElementById('comp_tax').innerText = data.tax_id || '-';
        document.getElementById('comp_phone').innerText = data.phone || '-';
        document.getElementById('comp_addr').innerText = data.address || '-';
        document.getElementById('comp_logo_preview').src = data.logo_path ? 'uploads/' + data.logo_path : 'uploads/default-logo.png';
    }

    // ทำงานตอนโหลดหน้า
    document.addEventListener('DOMContentLoaded', () => {
        updateSupplierInfo();
        calculateAll();
    });
</script>

<?php include 'footer.php'; ?>