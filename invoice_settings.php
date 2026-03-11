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

// สร้างเลขที่ใบแจ้งหนี้เบื้องต้น (เช่น INV202603-001)
$default_inv_no = "INV" . date('Ym') . "-001";
?>

<form action="api/save_invoice.php" method="POST">
    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer_id) ?>">
    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">
    
    <input type="hidden" name="sub_total" id="hidden_subtotal" value="0">
    <input type="hidden" name="vat_amount" id="hidden_vat_amount" value="0">
    <input type="hidden" name="wht_amount" id="hidden_wht_amount" value="0">
    <input type="hidden" name="total_amount" id="hidden_total_amount" value="0">

    <div class="bg-slate-50 ">
        <div class="max-w-[1400px] mx-auto p-4 md:p-6">
            
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
                                <div class="flex items-center gap-3 text-xs">
                                    <i class="fas fa-phone-alt text-indigo-500 w-4 text-center"></i>
                                    <span id="comp_phone" class="font-bold text-slate-600">-</span>
                                </div>
                                <div id="comp_addr" class="text-[11px] text-slate-500 leading-relaxed font-medium italic">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 rounded-3xl border border-slate-800 p-6 shadow-xl relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 text-slate-800 opacity-20 text-7xl rotate-12">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 class="text-xs font-black text-slate-500 mb-4 uppercase tracking-[0.2em] relative">Bill To / ผู้ซื้อ</h3>
                        <div class="relative z-10">
                            <div class="text-lg font-black text-white mb-2"><?= htmlspecialchars($customer['customer_name']) ?></div>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="text-slate-500 font-bold">Tax ID:</span>
                                    <span class="text-indigo-400 font-mono font-bold"><?= $customer['tax_id'] ?: '-' ?></span>
                                </div>
                                <div class="text-[11px] text-slate-400 leading-relaxed border-t border-slate-800 pt-3">
                                    <i class="fas fa-map-marker-alt mr-2 text-slate-600"></i>
                                    <?= htmlspecialchars($customer['address']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">วันที่ออกบิล</label>
                                <input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">ครบกำหนดชำระ</label>
                                <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">ภาษี (VAT %)</label>
                                <select name="vat_percent" id="vat_percent" onchange="calculateAll()"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:border-indigo-500 cursor-pointer">
                                    <option value="7" selected>7%</option>
                                    <option value="0">0% (Non-VAT)</option>
                                    <option value="3">3%</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">หัก ณ ที่จ่าย (%)</label>
                                <select name="wht_percent" id="wht_percent" onchange="calculateAll()"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:border-indigo-500 cursor-pointer">
                                    <option value="0" selected>ไม่หัก (0%)</option>
                                    <option value="1">ค่าขนส่ง (1%)</option>
                                    <option value="3">บริการ (3%)</option>
                                    <option value="5">ค่าเช่า (5%)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                            <span class="text-sm font-black text-slate-700 uppercase"><i class="fas fa-list mr-2 text-indigo-500"></i> รายการสินค้า / บริการ</span>
                            <button type="button" onclick="addItemRow()"
                                class="px-5 py-2 bg-indigo-600 text-white text-[11px] font-black rounded-xl hover:bg-indigo-700 transition-all shadow-md">
                                <i class="fas fa-plus mr-1"></i> เพิ่มรายการ
                            </button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse" id="itemsTable">
                                <thead class="bg-slate-50 text-[10px] uppercase text-slate-400 font-black border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3 w-12 text-center">#</th>
                                        <th class="px-4 py-3">รายละเอียดสินค้า</th>
                                        <th class="px-4 py-3 w-24 text-center">จำนวน</th>
                                        <th class="px-4 py-3 w-32 text-right">ราคา/หน่วย</th>
                                        <th class="px-4 py-3 w-32 text-right">รวมเงิน</th>
                                        <th class="px-4 py-3 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr class="item-row group">
                                        <td class="px-4 py-4 text-center text-xs font-bold text-slate-400 row-number">1</td>
                                        <td class="px-4 py-4">
                                            <textarea name="item_desc[]" placeholder="ระบุรายละเอียดสินค้า..." rows="1" oninput="autoResize(this)"
                                                class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
                                        </td>
                                        <td class="px-4 py-4">
                                            <input type="number" name="item_qty[]" value="1" min="1" oninput="calculateAll()"
                                                class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black focus:bg-indigo-50 transition-all">
                                        </td>
                                        <td class="px-4 py-4">
                                            <input type="number" name="item_price[]" value="0.00" step="0.01" oninput="calculateAll()"
                                                class="w-full bg-transparent border-none text-right text-sm font-mono font-black text-slate-700 focus:ring-0">
                                        </td>
                                        <td class="px-4 py-4 text-right text-sm font-mono font-black text-slate-700 row-total">0.00</td>
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
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-2 tracking-widest">บันทึกเพิ่มเติม</label>
                                <textarea name="remark" rows="3"
                                    class="w-full p-4 bg-white border border-slate-200 rounded-2xl text-sm outline-none focus:ring-2 focus:ring-indigo-500 resize-none shadow-inner"
                                    placeholder="เช่น รายละเอียดการโอนเงิน หรือ เงื่อนไขเพิ่มเติม..."></textarea>
                            </div>
                            
                            <div class="w-full md:w-80 space-y-3 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                                <div class="flex justify-between text-sm font-bold text-slate-500">
                                    <span>รวมเป็นเงิน (Sub-total)</span>
                                    <span id="subtotal_display" class="text-slate-800">0.00</span>
                                </div>
                                <div class="flex justify-between text-sm font-bold text-slate-400">
                                    <span>ภาษี (<span id="vat_percent_label">7</span>%)</span>
                                    <span id="vat_display">0.00</span>
                                </div>
                                <div class="flex justify-between text-sm font-bold text-red-400 border-b border-slate-50 pb-3">
                                    <span>หัก ณ ที่จ่าย (<span id="wht_percent_label">0</span>%)</span>
                                    <span>-<span id="wht_display">0.00</span></span>
                                </div>
                                <div class="flex justify-between text-xl font-black text-indigo-600 pt-1">
                                    <span>ยอดชำระสุทธิ</span>
                                    <span id="grandtotal_display">0.00</span>
                                </div>

                                <button type="submit"
                                    class="w-full mt-6 py-4 bg-indigo-600 text-white font-black rounded-2xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200 flex items-center justify-center gap-2 text-base uppercase tracking-wider">
                                    <i class="fas fa-check-circle"></i> บันทึกใบแจ้งหนี้
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>



<script>
// ฟังก์ชันเดิมที่ใช้สำหรับคำนวณและจัดการตาราง (ผมปรับให้เนียนขึ้น)
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = (textarea.scrollHeight) + 'px';
}

function addItemRow() {
    const tbody = document.querySelector('#itemsTable tbody');
    const rowCount = tbody.rows.length + 1;
    const newRow = `
    <tr class="item-row group border-t border-slate-50">
        <td class="px-4 py-4 text-center text-xs font-bold text-slate-400 row-number">${rowCount}</td>
        <td class="px-4 py-4">
            <textarea name="item_desc[]" placeholder="ระบุรายละเอียด..." rows="1" oninput="autoResize(this)"
                class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
        </td>
        <td class="px-4 py-4">
            <input type="number" name="item_qty[]" value="1" min="1" oninput="calculateAll()" 
                class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black focus:bg-indigo-50">
        </td>
        <td class="px-4 py-4">
            <input type="number" name="item_price[]" value="0.00" step="0.01" oninput="calculateAll()" 
                class="w-full bg-transparent border-none text-right text-sm font-mono font-black text-slate-700">
        </td>
        <td class="px-4 py-4 text-right text-sm font-mono font-black text-slate-700 row-total">0.00</td>
        <td class="px-4 py-4 text-center">
            <button type="button" onclick="removeRow(this)" class="text-slate-200 hover:text-red-500 transition-colors">
                <i class="fas fa-times-circle"></i>
            </button>
        </td>
    </tr>`;
    tbody.insertAdjacentHTML('beforeend', newRow);
    calculateAll();
}

function removeRow(btn) {
    const tbody = document.querySelector('#itemsTable tbody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
        reorderRows();
        calculateAll();
    }
}

function reorderRows() {
    document.querySelectorAll('#itemsTable tbody tr').forEach((row, index) => {
        row.querySelector('.row-number').innerText = index + 1;
    });
}

function calculateAll() {
    let subtotal = 0;
    const vatPercent = parseFloat(document.getElementById('vat_percent').value) || 0;
    const whtPercent = parseFloat(document.getElementById('wht_percent').value) || 0;

    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
        const price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
        const total = qty * price;
        
        row.querySelector('.row-total').innerText = total.toLocaleString(undefined, {minimumFractionDigits: 2});
        subtotal += total;
    });

    const vatAmount = subtotal * (vatPercent / 100);
    const whtAmount = subtotal * (whtPercent / 100);
    const grandTotal = (subtotal + vatAmount) - whtAmount;

    // อัปเดตการแสดงผล
    document.getElementById('subtotal_display').innerText = subtotal.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('vat_display').innerText = vatAmount.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('wht_display').innerText = whtAmount.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('grandtotal_display').innerText = grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2});
    
    document.getElementById('vat_percent_label').innerText = vatPercent;
    document.getElementById('wht_percent_label').innerText = whtPercent;

    // อัปเดตค่าใน Hidden Input สำหรับส่งไปบันทึก
    document.getElementById('hidden_subtotal').value = subtotal.toFixed(2);
    document.getElementById('hidden_vat_amount').value = vatAmount.toFixed(2);
    document.getElementById('hidden_wht_amount').value = whtAmount.toFixed(2);
    document.getElementById('hidden_total_amount').value = grandTotal.toFixed(2);
}

function updateSupplierInfo() {
    const select = document.getElementById('supplier_select');
    const data = JSON.parse(select.options[select.selectedIndex].getAttribute('data-info'));
    
    document.getElementById('comp_name').innerText = data.company_name;
    document.getElementById('comp_tax').innerText = data.tax_id;
    document.getElementById('comp_phone').innerText = data.phone;
    document.getElementById('comp_addr').innerText = data.address;
    document.getElementById('comp_logo_preview').src = data.logo_path ? 'uploads/' + data.logo_path : 'uploads/default-logo.png';
}

document.addEventListener('DOMContentLoaded', () => {
    updateSupplierInfo();
    calculateAll();
});
</script>