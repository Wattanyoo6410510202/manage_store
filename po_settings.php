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
        <div class="max-w-[1400px] mx-auto px-4 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white p-5 rounded-3xl border border-slate-200">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-xs font-black text-slate-400 flex items-center gap-2 uppercase tracking-[0.2em]">
                                <i class="fas fa-truck text-indigo-500 text-base"></i> Issuer / ผู้ขาย
                            </h3>
                            <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                <img id="comp_logo_preview" src="uploads/default-logo.png" class="h-10 w-10 object-contain">
                            </div>
                        </div>

                        <select name="supplier_id" id="supplier_select" onchange="updateSupplierInfo()" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-2xl text-sm focus:border-indigo-500 focus:bg-white outline-none font-bold text-slate-700 mb-5 transition-all cursor-pointer">
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>" data-info='<?= json_encode($sup, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                                    <?= htmlspecialchars($sup['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="p-5 bg-gradient-to-br from-indigo-50/50 to-slate-50 rounded-2xl border border-indigo-100/50 space-y-4">
                            <div>
                                <div id="comp_name" class="text-base font-black text-slate-800 leading-tight">กำลังโหลด...</div>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="text-[10px] font-black bg-indigo-600 text-white px-2 py-0.5 rounded uppercase tracking-wider">Tax ID</span>
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
                                <div id="comp_addr" class="text-[11px] text-slate-500 leading-relaxed font-medium italic">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 rounded-3xl overflow-hidden border border-slate-800">
                        <div class="bg-slate-800/50 px-6 py-4 border-b border-slate-700">
                            <h3 class="text-indigo-400 text-xs font-black flex items-center gap-2 uppercase tracking-wider">
                                <i class="fas fa-map-marker-alt"></i> Ship To / สถานที่จัดส่ง
                            </h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <div class="text-white font-black text-lg"><?= htmlspecialchars($customer['customer_name']) ?></div>
                                <div class="text-indigo-300 text-xs font-mono font-bold mt-1">Tax ID: <?= $customer['tax_id'] ?: '-' ?></div>
                            </div>
                            <div class="text-slate-400 text-xs leading-relaxed"><?= nl2br(htmlspecialchars($customer['address'])) ?></div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">วันที่ต้องการ</label>
                            <input type="date" name="due_date" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">เลขที่อ้างอิง</label>
                            <input type="text" name="reference_no" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">เงื่อนไขการชำระ</label>
                            <select name="payment_term" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                                <option value="cash">เงินสด</option>
                                <option value="30">30 วัน</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">ผู้ขอซื้อ</label>
                            <input type="text" name="requested_by" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden">
                        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/30">
                            <span class="text-xs font-black text-slate-700 uppercase tracking-widest"><i class="fas fa-shopping-cart mr-2 text-indigo-500"></i> Order Items</span>
                            <button type="button" onclick="addItemRow()" class="px-4 py-2 bg-indigo-600 text-white text-[11px] font-black rounded-xl hover:bg-indigo-700 transition-all"><i class="fas fa-plus mr-1"></i> เพิ่มรายการ</button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse" id="itemsTable">
                                <thead class="bg-slate-50/80 text-[10px] uppercase text-slate-400 font-black border-b border-slate-200">
                                    <tr>
                                        <th class="px-6 py-4 w-12 text-center">#</th>
                                        <th class="px-2 py-4">รายละเอียด</th>
                                        <th class="px-2 py-4 w-24 text-center">จำนวน</th>
                                        <th class="px-2 py-4 w-24 text-center">หน่วย</th>
                                        <th class="px-2 py-4 w-32 text-right">ราคา/หน่วย</th>
                                        <th class="px-6 py-4 w-32 text-right">รวมเงิน</th>
                                        <th class="px-4 py-4 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <tr class="item-row group">
                                        <td class="px-6 py-4 text-center text-xs font-bold text-slate-300">1</td>
                                        <td class="px-2 py-4"><textarea name="item_desc[]" oninput="autoResize(this)" class="w-full bg-transparent border-none outline-none text-sm font-bold resize-none"></textarea></td>
                                        <td class="px-2 py-4"><input type="number" name="item_qty[]" value="1" min="1" oninput="calculateTotal()" class="w-full bg-slate-50 border-none rounded-lg p-2 text-center text-sm font-bold"></td>
                                        <td class="px-2 py-4"><input type="text" name="item_unit[]" class="w-full bg-transparent text-center text-sm font-bold outline-none"></td>
                                        <td class="px-2 py-4"><input type="number" name="item_price[]" value="0.00" step="0.01" oninput="calculateTotal()" class="w-full bg-transparent text-right text-sm font-bold"></td>
                                        <td class="px-6 py-4 text-right text-sm font-bold text-slate-700 row-total">0.00</td>
                                        <td class="px-4 py-4 text-center"><button type="button" onclick="removeRow(this)" class="text-slate-200 hover:text-red-500"><i class="fas fa-times-circle"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="p-6 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-start gap-6">
                            <div class="w-full md:flex-grow">
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-2">หมายเหตุ</label>
                                <textarea name="notes" rows="3" class="w-full p-3 bg-white border border-slate-200 rounded-xl text-sm outline-none"></textarea>
                            </div>
                            <div class="w-full md:w-72 space-y-2">
                                <div class="flex justify-between text-sm font-bold text-slate-500"><span>รวมเป็นเงิน</span><span id="subtotal_display">0.00</span></div>
                                <div class="flex justify-between text-sm font-bold text-slate-500"><span>ภาษี (7%)</span><span id="vat_display">0.00</span></div>
                                <div class="flex justify-between text-lg font-black text-indigo-600 pt-2 border-t border-slate-200"><span>รวม</span><span id="grandtotal_display">0.00</span></div>
                                <button type="submit" class="w-full mt-4 py-3 bg-indigo-600 text-white font-black rounded-xl hover:bg-indigo-700">บันทึกและออกเอกสาร</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    function addItemRow() {
        const tbody = document.querySelector('#itemsTable tbody');
        const rowCount = tbody.rows.length + 1;
        const newRow = `
        <tr class="item-row group border-t border-slate-100">
            <td class="px-6 py-4 text-center text-xs font-bold text-slate-300">${rowCount}</td>
            <td class="px-2 py-4"><textarea name="item_desc[]" oninput="autoResize(this)" class="w-full bg-transparent border-none outline-none text-sm font-bold resize-none"></textarea></td>
            <td class="px-2 py-4"><input type="number" name="item_qty[]" value="1" min="1" oninput="calculateTotal()" class="w-full bg-slate-50 border-none rounded-lg p-2 text-center text-sm font-bold"></td>
            <td class="px-2 py-4"><input type="text" name="item_unit[]" class="w-full bg-transparent text-center text-sm font-bold outline-none"></td>
            <td class="px-2 py-4"><input type="number" name="item_price[]" value="0.00" step="0.01" oninput="calculateTotal()" class="w-full bg-transparent text-right text-sm font-bold"></td>
            <td class="px-6 py-4 text-right text-sm font-bold text-slate-700 row-total">0.00</td>
            <td class="px-4 py-4 text-center"><button type="button" onclick="removeRow(this)" class="text-slate-200 hover:text-red-500"><i class="fas fa-times-circle"></i></button></td>
        </tr>`;
        tbody.insertAdjacentHTML('beforeend', newRow);
    }

    function removeRow(btn) {
        if (document.querySelectorAll('.item-row').length > 1) {
            btn.closest('tr').remove();
            calculateTotal();
        }
    }

    function calculateTotal() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
            const price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
            const total = qty * price;
            row.querySelector('.row-total').innerText = total.toLocaleString(undefined, { minimumFractionDigits: 2 });
            subtotal += total;
        });

        const vat = subtotal * 0.07;
        const grandtotal = subtotal + vat;

        document.getElementById('subtotal_display').innerText = subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('vat_display').innerText = vat.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('grandtotal_display').innerText = grandtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
    }

    function updateSupplierInfo() {
        const select = document.getElementById('supplier_select');
        const selectedOption = select.options[select.selectedIndex];
        const data = JSON.parse(selectedOption.getAttribute('data-info'));

        document.getElementById('comp_name').innerText = data.company_name;
        document.getElementById('comp_tax').innerText = data.tax_id || '-';
        document.getElementById('comp_phone').innerText = data.phone || '-';
        document.getElementById('comp_email').innerText = data.email || '-';
        document.getElementById('comp_addr').innerText = data.address || '-';
        document.getElementById('comp_logo_preview').src = data.logo_path ? 'uploads/' + data.logo_path : 'uploads/default-logo.png';
    }

    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateSupplierInfo();
        calculateTotal();
    });
</script>