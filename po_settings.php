<?php
require_once 'config.php';
include 'header.php';

$customer_id = $_GET['customer_id'] ?? 0;

// 1. ดึงข้อมูลลูกค้า
$cust_query = mysqli_query($conn, "SELECT * FROM customers WHERE id = '" . mysqli_real_escape_string($conn, $customer_id) . "'");
$customer = mysqli_fetch_assoc($cust_query);

if (!$customer) {
    echo "<script>alert('ไม่พบข้อมูลลูกค้า'); window.location.href='customers.php';</script>";
    exit;
}

// 2. ดึงข้อมูลผู้ขาย (Suppliers)
$suppliers_query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY id ASC");
$suppliers = [];
while ($s = mysqli_fetch_assoc($suppliers_query)) {
    $suppliers[] = $s;
}
?>

<form action="api/save_po.php" method="POST" id="poForm">
    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer_id) ?>">

    <div class="bg-slate-50 ">
        <div class="max-w-[1400px] mx-auto ">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white p-5 rounded-3xl border border-slate-200">
                        <div class="flex justify-between items-start mb-4">
                            <h3
                                class="text-xs font-black text-slate-400 flex items-center gap-2 uppercase tracking-[0.2em]">
                                <i class="fas fa-truck text-indigo-500 text-base"></i> Issuer / ผู้ขาย
                            </h3>
                            <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                <img id="comp_logo_preview" src="uploads/default-logo.png"
                                    class="h-10 w-10 object-contain">
                            </div>
                        </div>

                        <select name="supplier_id" id="supplier_select" onchange="updateSupplierInfo()"
                            class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-2xl text-sm focus:border-indigo-500 focus:bg-white outline-none font-bold text-slate-700 mb-5 transition-all cursor-pointer">
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"
                                    data-info='<?= json_encode($sup, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                                    <?= htmlspecialchars($sup['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div
                            class="p-5 bg-gradient-to-br from-indigo-50/50 to-slate-50 rounded-2xl border border-indigo-100/50 space-y-4">
                            <div>
                                <div id="comp_name" class="text-base font-black text-slate-800 leading-tight">
                                    กำลังโหลด...</div>
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
                                    class="text-[11px] text-slate-500 leading-relaxed font-medium italic">-</div>
                            </div>
                        </div>
                    </div>


                </div>

                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">วันที่ต้องการ</label>
                            <input type="date" name="due_date"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">เลขที่อ้างอิง</label>
                            <input type="text" name="reference_no"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">เงื่อนไขการชำระ</label>
                            <select name="payment_term"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                                <option value="cash">เงินสด</option>
                                <option value="30">30 วัน</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">ผู้ขอซื้อ</label>
                            <input type="text" name="requested_by"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">ภาษี (VAT
                                %)</label>
                            <select name="vat_percent" id="vat_percent" onchange="calculateTotal()"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                                <option value="7">7%</option>
                                <option value="0">0%</option>
                                <option value="10">10%</option>
                                <option value="5">5%</option>
                                <option value="3">3%</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">หัก ณ ที่จ่าย (WHT
                                %)</label>
                            <select name="wht_percent" id="wht_percent" onchange="calculateTotal()"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                                <option value="0">0% </option>
                                <option value="1">1% </option>
                                <option value="3">3% </option>
                                <option value="5">5% </option>
                            </select>
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
                                <th class="px-4 py-3">รายละเอียดสินค้า</th>
                                <th class="px-4 py-3 w-24 text-center">จำนวน</th>
                                <th class="px-4 py-3 w-24 text-center">หน่วย</th>
                                <th class="px-4 py-3 w-32 text-right">ราคา/หน่วย</th>
                                <th class="px-4 py-3 w-32 text-right">ส่วนลด (บาท)</th>
                                <th class="px-4 py-3 w-32 text-right">รวมเงิน</th>
                                <th class="px-4 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr class="item-row group">
                                <td class="px-4 py-3 text-center text-xs font-bold text-slate-400">1</td>
                                <td class="px-4 py-3">
                                    <textarea name="item_desc[]" placeholder="ระบุรายละเอียดสินค้า..." rows="1"
                                        oninput="autoResize(this)"
                                        class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-medium resize-none block overflow-hidden"><?= htmlspecialchars($item['item_desc'] ?? '') ?></textarea>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" name="item_qty[]" value="1" min="0" step="0.01"
                                        oninput="calculateTotal()"
                                        class="w-full bg-slate-50 border-none rounded-lg p-2 text-center text-sm font-black text-indigo-600 focus:bg-indigo-50 outline-none">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="item_unit[]" placeholder="หน่วย"
                                        class="w-full bg-transparent border-b border-slate-100 text-center text-sm font-bold outline-none focus:border-indigo-400">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" name="item_price[]" value="0.00" step="0.01"
                                        oninput="calculateTotal()"
                                        class="w-full bg-transparent border-none text-right text-sm font-mono font-black focus:ring-0">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" name="item_discount[]" value="0.00" step="0.01"
                                        oninput="calculateTotal()"
                                        class="w-full bg-amber-50/50 border-none rounded-lg p-2 text-right text-sm font-mono font-black text-amber-600 focus:bg-amber-100 outline-none">
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-mono font-black text-slate-700 row-total">
                                    0.00</td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" onclick="removeRow(this)"
                                        class="text-slate-200 hover:text-red-500 transition-colors">
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
                            class="w-full p-3 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-300 transition-all"
                            placeholder="ระบุหมายเหตุเพิ่มเติม (ถ้ามี)..."></textarea>
                    </div>
                    <div class="w-full md:w-80 space-y-2">
                        <div class="flex justify-between text-sm font-bold text-slate-500">
                            <span>รวม</span>
                            <span id="subtotal_display">0.00</span>
                        </div>
                        <div class="flex justify-between text-sm font-bold text-slate-500">
                            <span>หัก ณ ที่จ่าย (<span id="wht_percent_label">0</span>%)</span>
                            <span>- <span id="wht_display">0.00</span></span>
                        </div>

                        <div class="flex justify-between text-sm font-bold text-slate-500">
                            <span>ภาษี (<span id="vat_percent_label">7</span>%)</span>
                            <span id="vat_display">0.00</span>
                        </div>



                        <div
                            class="flex justify-between text-lg font-black text-indigo-600 pt-2 border-t border-slate-200">
                            <span>ยอดสุทธิ</span>
                            <span id="grandtotal_display">0.00</span>
                        </div>

                        <button type="submit"
                            class="w-full mt-4 py-3 bg-indigo-600 text-white font-black rounded-xl hover:bg-indigo-700  transition-all flex items-center justify-center gap-2 active:scale-95">
                            <i class="fas fa-save"></i>บันทึกและออกเอกสาร
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    // 1. เพิ่มแถวใหม่ (รองรับช่องส่วนลด)
    function addItemRow() {
        const tbody = document.querySelector('#itemsTable tbody');
        const rowCount = tbody.querySelectorAll('.item-row').length + 1;
        const newRow = `
        <tr class="item-row group border-t border-slate-100"> 
            <td class="px-4 py-3 text-center text-xs font-bold text-slate-400">${rowCount}</td>
            <td class="px-4 py-3">
                                    <textarea name="item_desc[]" placeholder="ระบุรายละเอียดสินค้า..." rows="1"
                                        oninput="autoResize(this)"
                                        class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-medium resize-none block overflow-hidden"><?= htmlspecialchars($item['item_desc'] ?? '') ?></textarea>
                                </td>
            <td class="px-4 py-3">
                <input type="number" name="item_qty[]" value="1" min="0" step="0.01" oninput="calculateTotal()" 
                    class="w-full bg-slate-50 border-none rounded-lg p-2 text-center text-sm font-black text-indigo-600 outline-none">
            </td>
            <td class="px-4 py-3">
                <input type="text" name="item_unit[]" placeholder="หน่วย" 
                    class="w-full bg-transparent border-b border-slate-100 text-center text-sm font-bold outline-none">
            </td>
            <td class="px-4 py-3">
                <input type="number" name="item_price[]" value="0.00" step="0.01" oninput="calculateTotal()" 
                    class="w-full bg-transparent border-none text-right text-sm font-mono font-black outline-none">
            </td>
            <td class="px-4 py-3">
                <input type="number" name="item_discount[]" value="0.00" step="0.01" oninput="calculateTotal()" 
                    class="w-full bg-amber-50/50 border-none rounded-lg p-2 text-right text-sm font-mono font-black text-amber-600 outline-none">
            </td>
            <td class="px-4 py-3 text-right text-sm font-mono font-black text-slate-700 row-total">0.00</td>
            <td class="px-4 py-3 text-center">
                <button type="button" onclick="removeRow(this)" class="text-slate-200 hover:text-red-500 transition-colors">
                    <i class="fas fa-times-circle"></i>
                </button>
            </td>
        </tr>`;
        tbody.insertAdjacentHTML('beforeend', newRow);
    }

    // 2. ลบแถวและรันเลขใหม่
    function removeRow(btn) {
        if (document.querySelectorAll('.item-row').length > 1) {
            btn.closest('tr').remove();
            reindexRows();
            calculateTotal();
        }
    }

    function reindexRows() {
        document.querySelectorAll('.row-number').forEach((td, index) => {
            td.innerText = index + 1;
        });
    }

    // 3. คำนวณยอด (รองรับ ส่วนลด + VAT + หัก ณ ที่จ่าย)
    function calculateTotal() {
        let subtotal = 0;

        // --- ส่วนของ VAT ---
        const vatSelect = document.querySelector('select[name="vat_percent"]');
        const vatPercent = vatSelect ? parseFloat(vatSelect.value) : 0;
        const vatLabel = document.getElementById('vat_percent_label');
        if (vatLabel) vatLabel.innerText = vatPercent;

        // --- ส่วนของ หัก ณ ที่จ่าย (WHT) ---
        const whtSelect = document.querySelector('select[name="wht_percent"]');
        const whtPercent = whtSelect ? parseFloat(whtSelect.value) : 0;
        const whtLabel = document.getElementById('wht_percent_label');
        if (whtLabel) whtLabel.innerText = whtPercent;

        // คำนวณแต่ละรายการสินค้า
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
            const price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
            const discount = parseFloat(row.querySelector('[name="item_discount[]"]').value) || 0;

            // สูตร: (จำนวน * ราคา) - ส่วนลด
            const total = (qty * price) - discount;

            row.querySelector('.row-total').innerText = total.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            subtotal += total;
        });

        // คำนวณภาษีมูลค่าเพิ่ม
        const vat = subtotal * (vatPercent / 100);

        // คำนวณหัก ณ ที่จ่าย (คำนวณจากยอด Subtotal ก่อน VAT ตามหลักสรรพากร)
        const wht = subtotal * (whtPercent / 100);

        // ยอดรวมสุทธิ = ยอดรวม + VAT - หัก ณ ที่จ่าย
        const grandtotal = (subtotal + vat) - wht;

        // อัปเดตการแสดงผลสรุปยอด
        document.getElementById('subtotal_display').innerText = subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('vat_display').innerText = vat.toLocaleString(undefined, { minimumFractionDigits: 2 });

        // อัปเดตการแสดงผล หัก ณ ที่จ่าย
        const whtDisplay = document.getElementById('wht_display');
        if (whtDisplay) {
            whtDisplay.innerText = wht.toLocaleString(undefined, { minimumFractionDigits: 2 });
        }

        document.getElementById('grandtotal_display').innerText = grandtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
    }

    // 4. ข้อมูล Supplier
    function updateSupplierInfo() {
        const select = document.getElementById('supplier_select');
        if (!select || !select.value) return;

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
        if (logoImg) {
            logoImg.src = data.logo_path ? 'uploads/' + data.logo_path : 'uploads/default-logo.png';
        }
    }

    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateSupplierInfo();
        calculateTotal();
    });

    // สั่งให้คำนวณความสูงใหม่ทุกครั้งที่เพิ่มแถว หรือโหลดหน้าเสร็จ
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('textarea[name="item_desc[]"]').forEach(el => {
            autoResize(el);
        });
    });
</script>
<?php include 'footer.php'; ?>