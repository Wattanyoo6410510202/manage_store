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
    <div class=" bg-slate-50 ">
        <div class="max-w-[1400px] mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white p-5 rounded-3xl border border-slate-200 ">

                    <div class="flex justify-between items-start mb-4">
                        <h3
                            class="text-xs font-black text-slate-400 flex items-center gap-2 uppercase tracking-[0.2em]">
                            <i class="fas fa-file-invoice text-indigo-500 text-base"></i> Issuer / ผู้ขาย
                        </h3>
                        <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                            <img id="comp_logo_preview" src="uploads/default-logo.png" class="h-10 w-10 object-contain">
                        </div>
                    </div>

                    <select name="supplier_id" id="supplier_select" onchange="updateSupplierInfo()" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-2xl text-sm
                        focus:border-indigo-500 focus:bg-white outline-none font-bold text-slate-700 mb-5 transition-all
                        cursor-pointer">
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
                            <div id="comp_addr" class="text-[11px] text-slate-500 leading-relaxed font-medium italic">-
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-900 rounded-2xl  overflow-hidden border border-slate-800">
                    <div class="bg-slate-800/50 px-5 py-3 border-b border-slate-700">
                        <h3 class="text-indigo-400 text-sm font-black flex items-center gap-2 uppercase tracking-wider">
                            <i class="fas fa-user-tie"></i> Client / ผู้ซื้อ
                        </h3>
                    </div>
                    <div class="p-5 space-y-5">
                        <div>
                            <label
                                class="text-[10px] uppercase font-bold text-slate-500 block mb-1 tracking-widest">Customer
                                Name</label>
                            <div class="text-white font-black text-lg">
                                <?= htmlspecialchars($customer['customer_name']) ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 bg-slate-800/50 rounded-xl border border-slate-700/50">
                                <label class="text-[9px] uppercase font-bold text-slate-500 block mb-1">Tax ID</label>
                                <div class="text-xs text-indigo-200 font-mono font-bold">
                                    <?= $customer['tax_id'] ?: '-' ?>
                                </div>
                            </div>
                            <div class="p-3 bg-slate-800/50 rounded-xl border border-slate-700/50">
                                <label class="text-[9px] uppercase font-bold text-slate-500 block mb-1">Contact
                                    Person</label>
                                <div class="text-xs text-white font-bold">
                                    <?= htmlspecialchars($customer['contact_person'] ?: '-') ?>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center gap-3 text-xs">
                                <div
                                    class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-slate-400">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-[9px] uppercase font-bold text-slate-500">Email</div>
                                    <div class="text-slate-200"><?= htmlspecialchars($customer['email'] ?: '-') ?></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <div
                                    class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-slate-400">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-[9px] uppercase font-bold text-slate-500">Phone</div>
                                    <div class="text-slate-200"><?= htmlspecialchars($customer['phone'] ?: '-') ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-800">
                            <label
                                class="text-[10px] uppercase font-bold text-slate-500 block mb-1 tracking-widest">Billing
                                Address</label>
                            <div class="text-[12px] text-slate-400 leading-relaxed font-medium">
                                <?= nl2br(htmlspecialchars($customer['address'])) ?>
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
                            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">การชำระเงิน</label>
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
                                <option value="0">0% (ไม่มีภาษี)</option>
                                <option value="10">10%</option>
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

                <div class="bg-white rounded-2xl border border-slate-200  overflow-hidden">
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
                                    <td class="px-4 py-3 text-center text-xs font-bold text-slate-400">1</td>
                                    <td class="px-4 py-3">
                                        <textarea name="item_desc[]" placeholder="ระบุรายละเอียดสินค้า..." rows="1"
                                            oninput="autoResize(this)"
                                            class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-medium resize-none block overflow-hidden"><?= htmlspecialchars($item['item_desc'] ?? '') ?></textarea>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="item_qty[]" value="1" min="1"
                                            oninput="calculateTotal()"
                                            class="w-full bg-slate-50 border-none rounded-md px-2 py-1 text-center text-sm font-bold focus:bg-indigo-50">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="item_price[]" value="0.00" step="0.01"
                                            oninput="calculateTotal()"
                                            class="w-full bg-transparent border-none text-right text-sm font-mono font-bold focus:ring-0">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="item_discount[]" value="0.00" step="0.01"
                                            oninput="calculateTotal()"
                                            class="w-full bg-amber-50 border-none rounded-md px-2 py-1 text-right text-sm font-mono font-bold text-amber-600 focus:bg-amber-100 outline-none">
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-sm font-mono font-bold text-slate-700 row-total">
                                        0.00</td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" onclick="removeRow(this)"
                                            class="text-slate-300 hover:text-red-500 transition-colors"><i
                                                class="fas fa-times-circle"></i></button>
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
                                class="w-full p-3 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                                placeholder="เช่น เงื่อนไขการรับประกัน..."></textarea>
                        </div>
                        <div class="w-full md:w-72 space-y-2">
                            <div class="flex justify-between text-sm font-bold text-slate-500">
                                <span>รวมเป็นเงิน</span>
                                <span id="subtotal_display">0.00</span>
                            </div>
                            <div class="flex justify-between text-sm font-bold text-slate-500">
                                <span>ภาษีมูลค่าเพิ่ม (<span id="vat_percent_label">7</span>%)</span>
                                <span id="vat_display">0.00</span>
                            </div>
                            <div
                                class="flex justify-between text-lg font-black text-indigo-600 pt-2 border-t border-slate-200">
                                <span>รวม</span>
                                <span id="grandtotal_display">0.00</span>
                            </div>
                            <button
                                class="w-full mt-4 py-3 bg-indigo-600 text-white font-black rounded-xl  hover:bg-indigo-700 transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-save"></i> บันทึกและออกเอกสาร
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    // ฟังก์ชันเพิ่มแถวในตาราง
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
            <input type="number" name="item_qty[]" value="1" min="1" oninput="calculateTotal()" 
                   class="w-full bg-slate-50 border-none rounded-md px-2 py-1 text-center text-sm font-bold focus:bg-indigo-50">
        </td>
        <td class="px-4 py-3">
            <input type="number" name="item_price[]" value="0.00" step="0.01" oninput="calculateTotal()" 
                   class="w-full bg-transparent border-none text-right text-sm font-mono font-bold focus:ring-0">
        </td>
        <td class="px-4 py-3">
            <input type="number" name="item_discount[]" value="0.00" step="0.01" oninput="calculateTotal()" 
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
    }

    // ลบแถว
    function removeRow(btn) {
        const tbody = document.querySelector('#itemsTable tbody');
        if (tbody.rows.length > 1) {
            btn.closest('tr').remove();
            reorderRows(); // รีรันเลขลำดับใหม่
            calculateTotal(); // คำนวณยอดใหม่
        } else {
            alert('อย่างน้อยต้องมี 1 รายการครับจาร');
        }
    }

    // ฟังก์ชันจัดเลขลำดับใหม่ (1, 2, 3...)
    function reorderRows() {
        document.querySelectorAll('#itemsTable tbody tr').forEach((row, index) => {
            const rowNumCell = row.querySelector('.row-number');
            if (rowNumCell) {
                rowNumCell.innerText = index + 1;
            }
        });
    }

    // คำนวณเงิน
    function calculateTotal() {
        let subtotal = 0;
        const vatPercent = parseFloat(document.getElementById('vat_percent').value) || 0;

        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
            const price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
            // ดึงค่าส่วนลดรายบรรทัดที่เพิ่มเข้าไป
            const itemDiscount = parseFloat(row.querySelector('[name="item_discount[]"]').value) || 0;

            // คำนวณ: (จำนวน * ราคา) - ส่วนลด
            const total = (qty * price) - itemDiscount;

            row.querySelector('.row-total').innerText = total.toLocaleString(undefined, { minimumFractionDigits: 2 });
            subtotal += total;
        });

        // คำนวณภาษีและยอดสุทธิจากยอดที่หักส่วนลดแล้ว
        const vat = subtotal * (vatPercent / 100);
        const grandtotal = subtotal + vat;

        document.getElementById('subtotal_display').innerText = subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('vat_display').innerText = vat.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('grandtotal_display').innerText = grandtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });

        // อัปเดตเปอร์เซ็นต์ VAT ในวงเล็บ (ถ้ามี)
        if (document.getElementById('vat_percent_label')) {
            document.getElementById('vat_percent_label').innerText = vatPercent;
        }
    }
    // อัปเดตข้อมูลผู้ขาย
    function updateSupplierInfo() {
        const select = document.getElementById('supplier_select');
        const selectedOption = select.options[select.selectedIndex];
        const data = JSON.parse(selectedOption.getAttribute('data-info'));

        document.getElementById('comp_name').innerText = data.company_name;
        document.getElementById('comp_tax').innerText = data.tax_id || '-';
        document.getElementById('comp_phone').innerText = data.phone || '-';
        document.getElementById('comp_email').innerText = data.email || '-'; // บรรทัดนี้ที่ขาดไป
        document.getElementById('comp_addr').innerText = data.address || '-';

        const logoImg = document.getElementById('comp_logo_preview');
        logoImg.src = data.logo_path ? 'uploads/' + data.logo_path : 'uploads/default-logo.png';
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateSupplierInfo();
        calculateTotal();
    });

    // ฟังก์ชันปรับความสูงอัตโนมัติ
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }

    // สั่งให้คำนวณความสูงใหม่ทุกครั้งที่เพิ่มแถว หรือโหลดหน้าเสร็จ
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('textarea[name="item_desc[]"]').forEach(el => {
            autoResize(el);
        });
    });
    function calculateAll() {
        // 1. ดึงค่าเปอร์เซ็นต์จาก Select ด้านบนที่จารเพิ่งเพิ่มไป
        let vatPercent = parseFloat($('#vat_percent').val()) || 0;

        // 2. อัปเดตตัวเลขในวงเล็บท้ายบิลให้ตรงกัน
        $('#vat_percent_label').text(vatPercent);

        // 3. คำนวณยอด (ตัวอย่างยอดรวมก่อนภาษี)
        let subtotal = 0;
        $('.row-total').each(function () {
            subtotal += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });

        // 4. คำนวณเงินภาษี
        let vatAmount = (subtotal * vatPercent) / 100;

        // 5. แสดงผลยอดเงินภาษี
        $('#vat_display').text(vatAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

        // 6. คำนวณยอดสุทธิ
        let grandTotal = subtotal + vatAmount;
        $('#grand_total_display').text(grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    }

    // ฟังก์ชันสำหรับผูกเหตุการณ์ (Event Listeners)
    function initCalculationListeners() {
        // 1. เมื่อมีการเลือกเปลี่ยน % VAT
        const vatSelect = document.getElementById('vat_percent');
        if (vatSelect) {
            vatSelect.addEventListener('change', calculateTotal);
        }

        // 2. เมื่อมีการพิมพ์ในช่อง จำนวน หรือ ราคา (ใช้ Delegation เพื่อรองรับการเพิ่มแถวใหม่)
        const itemList = document.getElementById('item-list'); // ใส่ ID ให้ tbody ของจาร
        if (itemList) {
            itemList.addEventListener('input', function (e) {
                if (e.target.matches('[name="item_qty[]"], [name="item_price[]"]')) {
                    calculateTotal();
                }
            });
        }
    }

    // เรียกใช้งานเมื่อโหลดหน้าเสร็จ
    document.addEventListener('DOMContentLoaded', initCalculationListeners);

</script>
<script src="assets/js/demo-data.js"></script>