<?php
require_once 'config.php';
include 'header.php';

$customer_id = $_GET['customer_id'] ?? 0;

// 1. ดึงข้อมูลลูกค้า (ผู้ซื้อ)
$cust_query = mysqli_query($conn, "SELECT * FROM customers WHERE id = '" . mysqli_real_escape_string($conn, $customer_id) . "'");
$customer = mysqli_fetch_assoc($cust_query);

// 2. ดึงข้อมูลบริษัททั้งหมดจากตาราง suppliers (ผู้ขาย)
$suppliers_query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY id ASC");
$suppliers = [];
while ($s = mysqli_fetch_assoc($suppliers_query)) {
    $suppliers[] = $s;
}

if (!$customer) {
    echo "<script>alert('ไม่พบข้อมูลลูกค้า'); window.location.href='customers.php';</script>";
    exit;
}
?>
<form action="api/save_quotation.php" method="POST">
    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer_id) ?>">
    <div class="bg-slate-50 ">
        <div class="max-w-[1400px] mx-auto ">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white p-5 rounded-3xl border border-slate-200 ">

                        <div class="flex justify-between items-start mb-4">
                            <h3
                                class="text-xs font-black text-slate-400 flex items-center gap-2 uppercase tracking-[0.2em]">
                                <i class="fas fa-file-invoice text-indigo-500 text-base"></i> Issuer / ผู้ขาย
                            </h3>
                            <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                <img id="comp_logo_preview" src="uploads/default-logo.png"
                                    class="h-10 w-10 object-contain">
                            </div>
                        </div>
                        <select name="supplier_id" id="supplier_select" onchange="updateSupplierInfo()" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-2xl text-sm
    focus:border-indigo-500 focus:bg-white outline-none font-bold text-slate-700 mb-5 transition-all
    cursor-pointer">

                            <option value="0">ไม่ระบุ / อื่นๆ</option>

                            <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" data-info='<?= json_encode($sup, ENT_QUOTES) ?>'>
                                <?= htmlspecialchars($sup['company_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <div
                            class="p-5 bg-gradient-to-br from-indigo-50/50 to-slate-50 rounded-2xl border border-indigo-100/50 space-y-4">
                            <div>
                                <div id="comp_name" class="text-base font-black text-slate-800 leading-tight">
                                    กำลังโหลดข้อมูล...
                                </div>
                                <div class="flex items-center gap-2 mt-2">
                                    <span
                                        class="text-[10px] font-black bg-indigo-600 text-white px-2 py-0.5 rounded uppercase tracking-wider">Tax
                                        ID</span>
                                    <span id="comp_tax" class="text-xs font-mono font-bold text-slate-600">-</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-2">
                                <div class="flex items-center gap-3 text-xs">
                                    <i class="fas fa-phone-alt text-indigo-500 w-4"></i>
                                    <span id="comp_phone" class="font-bold text-slate-600">-</span>
                                </div>
                                <div class="flex items-center gap-3 text-xs">
                                    <i class="fas fa-envelope text-indigo-500 w-4"></i>
                                    <span id="comp_email" class="font-bold text-slate-600">-</span>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-indigo-100/50">
                                <div id="comp_addr"
                                    class="text-[11px] text-slate-500 leading-relaxed font-medium italic">-
                                </div>
                            </div>
                        </div>
                    </div>


                </div>

                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 ">
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 items-end">
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">วันที่</label>
                                <input type="date" name="doc_date" value="<?= date('Y-m-d') ?>"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">ยืนราคา
                                    (วัน)</label>
                                <input type="number" name="valid_until" value="30"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label
                                    class="text-[10px] font-bold text-slate-400 uppercase block mb-1">การชำระเงิน</label>
                                <select name="payment_term"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:border-indigo-500">
                                    <option value="30">เครดิต 30 วัน</option>
                                    <option value="cash">เงินสด</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">ภาษี (VAT
                                    %)</label>
                                <select name="vat_percent" id="vat_percent" onchange="calculateAll()"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:border-indigo-500">
                                    <option value="7" selected>7%</option>
                                    <option value="0">0%</option>
                                    <option value="10">10%</option>
                                    <option value="3">3%</option>
                                    <option value="5">5%</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">หัก ณ ที่จ่าย
                                    (%)</label>
                                <select name="wht_percent" id="wht_percent" onchange="calculateAll()"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:border-indigo-500">
                                    <option value="0" selected>0%</option>
                                    <option value="1">1%</option>
                                    <option value="3">3%</option>
                                    <option value="5">5%</option>
                                </select>
                            </div>
                            <div>
                                <button type="button" onclick="fillRandomData()"
                                    class="w-full px-4 py-2.5 bg-amber-500 text-white text-[11px] font-bold rounded-lg hover:bg-amber-600 transition-all flex items-center justify-center">
                                    <i class="fas fa-dice mr-2"></i> สุ่มข้อมูล
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 ">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="bg-indigo-500/20 p-1.5 rounded-lg">
                                <i class="fas fa-user-tie text-indigo-400 text-xs"></i>
                            </div>
                            <span class="text-white font-black text-base truncate"
                                title="<?= htmlspecialchars($customer['customer_name']) ?>">
                                <?= htmlspecialchars($customer['customer_name']) ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-[11px] mb-2">
                            <div class="text-slate-400"><span class="text-slate-600 font-bold uppercase mr-1">Tax
                                    ID:</span> <span
                                    class="text-indigo-300 font-mono"><?= $customer['tax_id'] ?: '-' ?></span></div>
                            <div class="text-slate-400"><span
                                    class="text-slate-600 font-bold uppercase mr-1">Contact:</span> <span
                                    class="text-slate-200"><?= htmlspecialchars($customer['contact_person'] ?: '-') ?></span>
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-400 mb-1 flex items-center gap-2">
                            <i class="fas fa-envelope text-[9px] text-slate-600 w-3"></i>
                            <span class="text-slate-300"><?= htmlspecialchars($customer['email'] ?: '-') ?></span>
                        </div>

                        <div
                            class="text-[11px] text-slate-400 mb-2 flex items-center gap-2 pb-2 border-b border-slate-800/50">
                            <i class="fas fa-phone text-[9px] text-slate-600 w-3"></i>
                            <span class="text-slate-300"><?= htmlspecialchars($customer['phone'] ?: '-') ?></span>
                        </div>

                        <div class="text-[11px] text-slate-500 truncate italic"
                            title="<?= htmlspecialchars($customer['address']) ?>">
                            <i class="fas fa-map-marker-alt text-[9px] mr-1"></i>
                            <?= str_replace(["\r", "\n"], ' ', htmlspecialchars($customer['address'])) ?>
                        </div>
                    </div>

                </div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200  overflow-hidden mt-4">
                <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <span class="text-sm font-black text-slate-700 uppercase"><i
                            class="fas fa-list mr-2 text-indigo-500"></i> Items List</span>


                    <button type="button" onclick="addItemRow()"
                        class="px-4 py-1.5 bg-indigo-600 text-white text-[11px] font-bold rounded-lg hover:bg-indigo-700 transition-all">
                        <i class="fas fa-plus mr-1"></i> เพิ่มรายการ
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse" id="itemsTable">
                        <thead
                            class="bg-slate-50 text-[10px] uppercase text-slate-400 font-black border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 w-12 text-center">#</th>
                                <th class="px-4 py-3">รายละเอียดสินค้า / บริการ</th>
                                <th class="px-4 py-3 w-24 text-center">จำนวน</th>
                                <th class="px-4 py-3 w-32 text-right">ราคา/หน่วย</th>
                                <th class="px-4 py-3 w-32 text-right">ส่วนลด (บาท)</th>
                                <th class="px-4 py-3 w-32 text-right">รวมเงิน</th>
                                <th class="px-4 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr class="item-row group">
                                <td class="px-4 py-3 text-center text-xs font-bold text-slate-400 row-number">1</td>
                                <td class="px-4 py-3">
                                    <textarea name="item_desc[]" placeholder="ระบุรายละเอียดสินค้า..." rows="1"
                                        oninput="autoResize(this)"
                                        class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-medium resize-none block overflow-hidden"><?= htmlspecialchars($item['item_desc'] ?? '') ?></textarea>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" name="item_qty[]" value="1" min="1" oninput="calculateAll()"
                                        class="w-full bg-slate-50 border-none rounded-md px-2 py-1 text-center text-sm font-bold focus:bg-indigo-50">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" name="item_price[]" value="0.00" step="0.01"
                                        oninput="calculateAll()"
                                        class="w-full bg-transparent border-none text-right text-sm font-mono font-bold focus:ring-0">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" name="item_discount[]" value="0.00" step="0.01"
                                        oninput="calculateAll()"
                                        class="w-full bg-amber-50 border-none rounded-md px-2 py-1 text-right text-sm font-mono font-bold text-amber-600 focus:bg-amber-100 outline-none">
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-mono font-bold text-slate-700 row-total">
                                    0.00</td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" onclick="removeRow(this)"
                                        class="text-slate-300 hover:text-red-500 transition-colors">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="p-6 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-start gap-6">
                    <div class="w-full md:flex-grow">
                        <label class="text-[10px] font-bold text-slate-400 uppercase block mb-2">หมายเหตุ</label>
                        <textarea name="notes" rows="3"
                            class="w-full p-3 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                            placeholder="เช่น เงื่อนไขการรับประกัน..."></textarea>
                    </div>
                    <div class="w-full md:w-72 space-y-2">
                        <div class="flex justify-between text-sm font-bold text-slate-500">
                            <span>รวมเป็นเงิน</span>
                            <span id="subtotal_display" class="text-slate-700">0.00</span>
                        </div>
                        <div class="flex justify-between text-sm font-bold text-slate-500">
                            <span>หัก ณ ที่จ่าย (<span id="wht_percent_label">0</span>%)</span>
                            <span>-<span id="wht_display">0.00</span></span>
                        </div>

                        <div class="flex justify-between text-sm font-bold text-slate-500">
                            <span>ภาษี (<span id="vat_percent_label">7</span>%)</span>
                            <span id="vat_display" class="text-slate-700">0.00</span>
                        </div>


                        <div
                            class="flex justify-between text-lg font-black text-indigo-600 pt-2 border-t border-slate-200">
                            <span>ยอดสุทธิ</span>
                            <span id="grandtotal_display">0.00</span>
                        </div>

                        <button type="submit"
                            class="w-full mt-4 py-3 bg-indigo-600 text-white font-black rounded-xl hover:bg-indigo-700 transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-save"></i> บันทึกและออกเอกสาร
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// 1. ฟังก์ชันปรับความสูง textarea อัตโนมัติ
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = (textarea.scrollHeight) + 'px';
}

// 2. ฟังก์ชันเพิ่มแถวในตาราง
function addItemRow() {
    const tbody = document.querySelector('#itemsTable tbody');
    const rowCount = tbody.rows.length + 1;
    const newRow = `
    <tr class="item-row group border-t border-slate-100">
        <td class="px-4 py-3 text-center text-xs font-bold text-slate-400 row-number">${rowCount}</td>
        <td class="px-4 py-3">
            <textarea name="item_desc[]" 
                      placeholder="ระบุรายละเอียด..." 
                      rows="1"
                      oninput="autoResize(this)"
                      class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-medium resize-none block overflow-hidden"></textarea>
        </td>
        <td class="px-4 py-3">
            <input type="number" name="item_qty[]" value="1" min="1" oninput="calculateAll()" 
                   class="w-full bg-slate-50 border-none rounded-md px-2 py-1 text-center text-sm font-bold focus:bg-indigo-50">
        </td>
        <td class="px-4 py-3">
            <input type="number" name="item_price[]" value="0.00" step="0.01" oninput="calculateAll()" 
                   class="w-full bg-transparent border-none text-right text-sm font-mono font-bold focus:ring-0">
        </td>
        <td class="px-4 py-3">
            <input type="number" name="item_discount[]" value="0.00" step="0.01" oninput="calculateAll()" 
                   class="w-full bg-amber-50 border-none rounded-md px-2 py-1 text-right text-sm font-mono font-bold text-amber-600 focus:bg-amber-100 outline-none">
        </td>
        <td class="px-4 py-3 text-right text-sm font-mono font-bold text-slate-700 row-total">0.00</td>
        <td class="px-4 py-3 text-center">
            <button type="button" onclick="removeRow(this)" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fas fa-times-circle"></i>
            </button>
        </td>
    </tr>`;
    tbody.insertAdjacentHTML('beforeend', newRow);
    calculateAll(); // คำนวณใหม่เมื่อเพิ่มแถว
}

// 3. ฟังก์ชันลบแถว
function removeRow(btn) {
    const tbody = document.querySelector('#itemsTable tbody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
        reorderRows();
        calculateAll();
    } else {
        alert('อย่างน้อยต้องมี 1 รายการครับจาร');
    }
}

// 4. ฟังก์ชันจัดเลขลำดับใหม่
function reorderRows() {
    document.querySelectorAll('#itemsTable tbody tr').forEach((row, index) => {
        const rowNumCell = row.querySelector('.row-number');
        if (rowNumCell) rowNumCell.innerText = index + 1;
    });
}

// 5. ฟังก์ชันคำนวณเงิน (หัวใจหลัก) - แก้ไขเวอร์ชันคำนวณ WHT
function calculateAll() {
    let subtotal = 0;
    const vatSelect = document.getElementById('vat_percent');
    const whtSelect = document.getElementById('wht_percent');

    const vatPercent = parseFloat(vatSelect ? vatSelect.value : 0) || 0;
    const whtPercent = parseFloat(whtSelect ? whtSelect.value : 0) || 0;

    document.querySelectorAll('.item-row').forEach(row => {
        // ดึงค่าแบบดัก error และใช้ name selector ที่ยืดหยุ่นขึ้น
        const qty = parseFloat(row.querySelector('[name*="item_qty"]')?.value) || 0;
        const price = parseFloat(row.querySelector('[name*="item_price"]')?.value) || 0;
        const itemDiscount = parseFloat(row.querySelector('[name*="item_discount"]')?.value) || 0;

        const total = (qty * price) - itemDiscount;

        const rowTotalEl = row.querySelector('.row-total');
        if (rowTotalEl) {
            rowTotalEl.innerText = total.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        subtotal += total;
    });

    const vatAmount = subtotal * (vatPercent / 100);
    const whtAmount = subtotal * (whtPercent / 100);

    // 🔥 จุดสำคัญ: ยอดสุทธิ (Grand Total) ต้องลบ หัก ณ ที่จ่าย ออกด้วย
    const grandTotal = (subtotal + vatAmount) - whtAmount;

    const format = {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    };

    // อัปเดตตัวเลขหน้าจอ
    if (document.getElementById('subtotal_display')) document.getElementById('subtotal_display').innerText = subtotal
        .toLocaleString(undefined, format);
    if (document.getElementById('vat_display')) document.getElementById('vat_display').innerText = vatAmount
        .toLocaleString(undefined, format);
    if (document.getElementById('wht_display')) document.getElementById('wht_display').innerText = whtAmount
        .toLocaleString(undefined, format);
    if (document.getElementById('grandtotal_display')) document.getElementById('grandtotal_display').innerText =
        grandTotal.toLocaleString(undefined, format);

    // อัปเดต Label เปอร์เซ็นต์ (เพื่อให้เลขในวงเล็บเปลี่ยนตาม)
    if (document.getElementById('vat_percent_label')) document.getElementById('vat_percent_label').innerText =
        vatPercent;
    if (document.getElementById('wht_percent_label')) document.getElementById('wht_percent_label').innerText =
        whtPercent;
}
// 6. อัปเดตข้อมูลผู้ขาย
function updateSupplierInfo() {
    const select = document.getElementById('supplier_select');
    if (!select || !select.options[select.selectedIndex]) return;

    const selectedOption = select.options[select.selectedIndex];
    const infoAttr = selectedOption.getAttribute('data-info');
    if (!infoAttr) return;

    const data = JSON.parse(infoAttr);
    document.getElementById('comp_name').innerText = data.company_name || '-';
    document.getElementById('comp_tax').innerText = data.tax_id || '-';
    document.getElementById('comp_phone').innerText = data.phone || '-';
    document.getElementById('comp_email').innerText = data.email || '-';
    document.getElementById('comp_addr').innerText = data.address || '-';

    const logoImg = document.getElementById('comp_logo_preview');
    if (logoImg) logoImg.src = data.logo_path ? 'uploads/' + data.logo_path : 'uploads/default-logo.png';
}

// 7. ตั้งค่าเริ่มต้น
document.addEventListener('DOMContentLoaded', () => {
    // อัปเดตข้อมูลผู้ขายครั้งแรก
    if (document.getElementById('supplier_select')) {
        updateSupplierInfo();
    }

    // จัดการ Event สำหรับการคำนวณ (ใช้ Delegation เพื่อความชัวร์)
    document.addEventListener('input', (e) => {
        if (e.target.matches('[name="item_qty[]"], [name="item_price[]"], [name="item_discount[]"]')) {
            calculateAll();
        }
    });

    // จัดการเมื่อเปลี่ยนค่า VAT / WHT
    const vatSelect = document.getElementById('vat_percent');
    if (vatSelect) vatSelect.addEventListener('change', calculateAll);

    const whtSelect = document.getElementById('wht_percent');
    if (whtSelect) whtSelect.addEventListener('change', calculateAll);

    // ปรับ textarea ตั้งต้น
    document.querySelectorAll('textarea[name="item_desc[]"]').forEach(autoResize);

    // คำนวณครั้งแรก
    calculateAll();
});
</script>
<script src="assets/js/demo-data.js"></script>