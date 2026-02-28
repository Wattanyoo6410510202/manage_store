<?php
require_once 'config.php';
include 'header.php';

$customer_id = $_GET['customer_id'] ?? 0;

// 1. ดึงข้อมูลลูกค้า (ในบริบท PO คือผู้สั่งซื้อ/บริษัทเราเอง หรือสาขาปลายทาง)
$cust_query = mysqli_query($conn, "SELECT * FROM customers WHERE id = '" . mysqli_real_escape_string($conn, $customer_id) . "'");
$customer = mysqli_fetch_assoc($cust_query);

// 2. ดึงข้อมูลผู้ขาย (Suppliers)
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

<form action="api/save_purchase_order.php" method="POST">
    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer_id) ?>">

    <div class="bg-slate-50 min-h-screen pb-20">
        <div class="max-w-[1400px] mx-auto px-4 py-6">

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

                    <div class="bg-slate-900 rounded-3xl overflow-hidden border border-slate-800 shadow-xl">
                        <div class="bg-slate-800/50 px-6 py-4 border-b border-slate-700">
                            <h3
                                class="text-indigo-400 text-xs font-black flex items-center gap-2 uppercase tracking-wider">
                                <i class="fas fa-map-marker-alt"></i> Ship To / สถานที่จัดส่ง
                            </h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <div class="text-white font-black text-lg">
                                    <?= htmlspecialchars($customer['customer_name']) ?></div>
                                <div class="text-indigo-300 text-xs font-mono font-bold mt-1">Tax ID:
                                    <?= $customer['tax_id'] ?: '-' ?></div>
                            </div>
                            <div class="text-slate-400 text-xs leading-relaxed">
                                <?= nl2br(htmlspecialchars($customer['address'])) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    <div
                        class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">วันที่สั่งซื้อ</label>
                            <input type="date" name="doc_date" value="<?= date('Y-m-d') ?>"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">วันที่ต้องการสินค้า</label>
                            <input type="date" name="due_date"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500 text-indigo-600">
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">การชำระเงิน</label>
                            <select name="payment_term"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                                <option value="30">เครดิต 30 วัน</option>
                                <option value="60">เครดิต 60 วัน</option>
                                <option value="cash">เงินสด / โอนจ่าย</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">ส่งโดย</label>
                            <input type="text" name="shipping_method" placeholder="เช่น Kerry, รถบริษัท"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/30">
                            <span class="text-xs font-black text-slate-700 uppercase tracking-widest"><i
                                    class="fas fa-shopping-cart mr-2 text-indigo-500"></i> Order Items</span>
                            <button type="button" onclick="addItemRow()"
                                class="px-4 py-2 bg-indigo-600 text-white text-[11px] font-black rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200">
                                <i class="fas fa-plus mr-1"></i> เพิ่มรายการสินค้า
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse" id="itemsTable">
                                <thead
                                    class="bg-slate-50/80 text-[10px] uppercase text-slate-400 font-black border-b border-slate-200">
                                    <tr>
                                        <th class="px-6 py-4 w-12 text-center">#</th>
                                        <th class="px-2 py-4">รายละเอียดสินค้า</th>
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
                                        <td class="px-2 py-4">
                                            <textarea name="item_desc[]" placeholder="ระบุชื่อสินค้า / รหัสสินค้า..."
                                                rows="1" oninput="autoResize(this)"
                                                class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
                                        </td>
                                        <td class="px-2 py-4">
                                            <input type="number" name="item_qty[]" value="1" min="1"
                                                oninput="calculateTotal()"
                                                class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black text-indigo-600 focus:bg-indigo-50">
                                        </td>
                                        <td class="px-2 py-4">
                                            <input type="text" name="item_unit[]" placeholder="ชิ้น"
                                                class="w-full bg-transparent border-b border-slate-100 text-center text-xs font-bold outline-none focus:border-indigo-400">
                                        </td>
                                        <td class="px-2 py-4">
                                            <input type="number" name="item_price[]" value="0.00" step="0.01"
                                                oninput="calculateTotal()"
                                                class="w-full bg-transparent border-none text-right text-sm font-mono font-black focus:ring-0">
                                        </td>
                                        <td
                                            class="px-6 py-4 text-right text-sm font-mono font-black text-slate-700 row-total">
                                            0.00</td>
                                        <td class="px-4 py-4 text-center">
                                            <button type="button" onclick="removeRow(this)"
                                                class="text-slate-200 hover:text-red-500 transition-colors"><i
                                                    class="fas fa-trash-alt"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            class="p-8 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-start gap-10">
                            <div class="w-full md:flex-grow">
                                <label
                                    class="text-[10px] font-black text-slate-400 uppercase block mb-3 tracking-widest">ข้อความแจ้งผู้ขาย
                                    / หมายเหตุ</label>
                                <textarea name="notes" rows="4"
                                    class="w-full p-4 bg-white border border-slate-200 rounded-2xl text-sm outline-none focus:ring-2 focus:ring-indigo-500/20 resize-none shadow-inner"
                                    placeholder="เช่น กรุณาส่งสินค้าก่อนเวลา 16.00 น. ..."></textarea>
                            </div>

                            <div class="w-full md:w-80 space-y-3">
                                <div class="flex justify-between text-sm font-bold text-slate-500">
                                    <span>Sub Total</span>
                                    <span id="subtotal_display" class="font-mono">0.00</span>
                                </div>
                                <div class="flex justify-between text-sm font-bold text-slate-500">
                                    <span>VAT (7%)</span>
                                    <span id="vat_display" class="font-mono">0.00</span>
                                </div>
                                <div
                                    class="flex justify-between text-xl font-black text-slate-900 pt-4 border-t-2 border-dashed border-slate-200">
                                    <span class="uppercase tracking-tighter">Total Amount</span>
                                    <span id="grandtotal_display" class="text-indigo-600 font-mono">0.00</span>
                                </div>

                                <button type="submit"
                                    class="w-full mt-6 py-4 bg-slate-900 text-white font-black rounded-2xl hover:bg-indigo-600 transition-all flex items-center justify-center gap-3 shadow-xl shadow-slate-200">
                                    <i class="fas fa-check-circle text-lg"></i> ยืนยันสั่งซื้อสินค้า
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
    // JS เดิมของคุณทำงานได้ดีอยู่แล้ว ผมเพิ่มช่อง Unit เข้าไปใน addItemRow
    function addItemRow() {
        const tbody = document.querySelector('#itemsTable tbody');
        const rowCount = tbody.rows.length + 1;
        const newRow = `
        <tr class="item-row group border-t border-slate-50">
            <td class="px-6 py-4 text-center text-xs font-bold text-slate-300">${rowCount}</td>
            <td class="px-2 py-4">
                <textarea name="item_desc[]" placeholder="รายละเอียด..." rows="1" oninput="autoResize(this)" class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
            </td>
            <td class="px-2 py-4">
                <input type="number" name="item_qty[]" value="1" min="1" oninput="calculateTotal()" class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black text-indigo-600 focus:bg-indigo-50">
            </td>
            <td class="px-2 py-4">
                <input type="text" name="item_unit[]" placeholder="หน่วย" class="w-full bg-transparent border-b border-slate-100 text-center text-xs font-bold outline-none focus:border-indigo-400">
            </td>
            <td class="px-2 py-4">
                <input type="number" name="item_price[]" value="0.00" step="0.01" oninput="calculateTotal()" class="w-full bg-transparent border-none text-right text-sm font-mono font-black focus:ring-0">
            </td>
            <td class="px-6 py-4 text-right text-sm font-mono font-black text-slate-700 row-total">0.00</td>
            <td class="px-4 py-4 text-center">
                <button type="button" onclick="removeRow(this)" class="text-slate-200 hover:text-red-500 transition-colors"><i class="fas fa-trash-alt"></i></button>
            </td>
        </tr>`;
        tbody.insertAdjacentHTML('beforeend', newRow);
    }

    // ฟังก์ชันอื่นๆ (removeRow, calculateTotal, updateSupplierInfo, autoResize) 
    // ใช้ของเดิมที่คุณมีได้เลยครับ (ตรวจสอบชื่อ ID ให้ตรงกัน)

    function removeRow(btn) {
        const tbody = document.querySelector('#itemsTable tbody');
        if (tbody.rows.length > 1) {
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
        if (!selectedOption.getAttribute('data-info')) return;

        const data = JSON.parse(selectedOption.getAttribute('data-info'));

        document.getElementById('comp_name').innerText = data.company_name;
        document.getElementById('comp_tax').innerText = 'Tax ID: ' + (data.tax_id || '-');
        document.getElementById('comp_phone').innerText = data.phone || '-';
        document.getElementById('comp_email').innerText = data.email || '-';
        document.getElementById('comp_addr').innerText = data.address || '-';
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