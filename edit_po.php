<?php
require_once 'config.php';
include 'header.php';

// รับค่า ID ของ PO ที่ต้องการแก้ไข
$po_id = $_GET['id'] ?? 0;

// 1. ดึงข้อมูลหลักจากตาราง po
$sql_po = "SELECT * FROM po WHERE id = '" . mysqli_real_escape_string($conn, $po_id) . "'";
$res_po = mysqli_query($conn, $sql_po);
$po_data = mysqli_fetch_assoc($res_po);

if (!$po_data) {
    echo "<script>alert('ไม่พบข้อมูลใบสั่งซื้อ'); window.location.href='po_list.php';</script>";
    exit;
}

$customer_id = $po_data['customer_id'];

// 2. ดึงข้อมูลลูกค้า
$cust_query = mysqli_query($conn, "SELECT * FROM customers WHERE id = '" . mysqli_real_escape_string($conn, $customer_id) . "'");
$customer = mysqli_fetch_assoc($cust_query);

// 3. ดึงรายการสินค้าเดิมจาก po_items
$items_query = mysqli_query($conn, "SELECT * FROM po_items WHERE po_id = '" . mysqli_real_escape_string($conn, $po_id) . "' ORDER BY id ASC");

// 4. ดึงข้อมูลผู้ขาย (Suppliers)
$suppliers_query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY id ASC");
$suppliers = [];
while ($s = mysqli_fetch_assoc($suppliers_query)) {
    $suppliers[] = $s;
}
?>

<form action="api/update_po.php" method="POST">
    <input type="hidden" name="po_id" value="<?= $po_id ?>">
    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer_id) ?>">

    <div class="bg-slate-50">
        <div class="max-w-[1400px] mx-auto px-4 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white p-5 rounded-3xl border border-slate-200 ">
                        <div class="flex justify-between items-start mb-4">
                            <h3
                                class="text-xs font-black text-slate-400 flex items-center gap-2 uppercase tracking-[0.2em]">
                                <i class="fas fa-file-invoice text-indigo-500 text-base"></i> Supplier / ผู้ขาย
                            </h3>
                            <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                <img id="comp_logo_preview" src="assets/img/default-logo.png"
                                    class="h-10 w-10 object-contain">
                            </div>
                        </div>

                        <select name="supplier_id" id="supplier_select" onchange="updateSupplierInfo()"
                            class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-2xl text-sm focus:border-indigo-500 focus:bg-white outline-none font-bold text-slate-700 mb-5 transition-all">
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"
                                    data-info="<?= htmlspecialchars(json_encode($sup), ENT_QUOTES, 'UTF-8') ?>"
                                    <?= ($sup['id'] == $po_data['supplier_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sup['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div
                            class="p-5 bg-gradient-to-br from-indigo-50/50 to-slate-50 rounded-2xl border border-indigo-100/50 space-y-4">
                            <div>
                                <div id="comp_name" class="text-base font-black text-slate-800 leading-tight">
                                    โหลดข้อมูล...</div>
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

                    <div class="bg-slate-900 rounded-3xl overflow-hidden border border-slate-800 ">
                        <div class="bg-slate-800/50 px-6 py-4 border-b border-slate-700">
                            <h3
                                class="text-indigo-400 text-xs font-black flex items-center gap-2 uppercase tracking-wider">
                                <i class="fas fa-map-marker-alt"></i> Ship To / สถานที่จัดส่ง
                            </h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <div class="text-white font-black text-lg">
                                    <?= htmlspecialchars($customer['customer_name'] ?? 'ไม่พบข้อมูล') ?>
                                </div>
                                <div class="text-indigo-300 text-xs font-mono font-bold mt-1">Tax ID:
                                    <?= $customer['tax_id'] ?? '-' ?>
                                </div>
                            </div>
                            <div class="text-slate-400 text-xs leading-relaxed">
                                <?= nl2br(htmlspecialchars($customer['address'] ?? '-')) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="col-span-1">
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">เลขที่เอกสาร
                                PO</label>
                            <input type="text" value="<?= $po_data['doc_no'] ?>"
                                class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl text-sm font-bold outline-none text-slate-500"
                                readonly>
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">วันที่สั่งซื้อ</label>
                            <input type="date" name="po_date"
                                value="<?= date('Y-m-d', strtotime($po_data['created_at'])) ?>"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">อ้างอิง
                                (Ref.)</label>
                            <input type="text" name="reference_no"
                                value="<?= htmlspecialchars($po_data['reference_no']) ?>"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">การชำระเงิน</label>
                            <select name="payment_term"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                                <option value="30" <?= $po_data['payment_term'] == '30' ? 'selected' : '' ?>>เครดิต 30 วัน
                                </option>
                                <option value="60" <?= $po_data['payment_term'] == '60' ? 'selected' : '' ?>>เครดิต 60 วัน
                                </option>
                                <option value="cash" <?= $po_data['payment_term'] == 'cash' ? 'selected' : '' ?>>เงินสด /
                                    โอนจ่าย</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">ภาษี (VAT
                                %)</label>
                            <select name="vat_percent" id="vat_percent" onchange="calculateTotal()"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                                <option value="7" <?= $po_data['vat_percent'] == 7 ? 'selected' : '' ?>>7%</option>
                                <option value="0" <?= $po_data['vat_percent'] == 0 ? 'selected' : '' ?>>0%</option>
                                <option value="10" <?= $po_data['vat_percent'] == 10 ? 'selected' : '' ?>>10%</option>
                            </select>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden">
                        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/30">
                            <span class="text-xs font-black text-slate-700 uppercase tracking-widest"><i
                                    class="fas fa-shopping-cart mr-2 text-indigo-500"></i> PO Items</span>
                            <button type="button" onclick="addItemRow()"
                                class="px-4 py-2 bg-indigo-600 text-white text-[11px] font-black rounded-xl hover:bg-indigo-700 transition-all  ">
                                <i class="fas fa-plus mr-1"></i> เพิ่มรายการ
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
                                        <th class="px-2 py-4 w-32 text-right">ส่วนลด (บาท)</th>
                                        <th class="px-6 py-4 w-32 text-right">รวมเงิน</th>
                                        <th class="px-4 py-4 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php
                                    $count = 1;
                                    // อย่าลืมเช็คชื่อคอลัมน์ในตาราง pr_items ของจารนะครับ
                                    while ($item = mysqli_fetch_assoc($items_query)):
                                        // คำนวณราคารายบรรทัดเบื้องต้น: (ราคา * จำนวน) - ส่วนลด
                                        $row_total = ($item['item_qty'] * $item['item_price']) - ($item['item_discount'] ?? 0);
                                        ?>
                                        <tr class="item-row group">
                                            <td class="px-6 py-4 text-center text-xs font-bold text-slate-300 row-number">
                                                <?= $count++ ?>
                                            </td>
                                            <td class="px-2 py-4">
                                                <textarea name="item_desc[]" rows="1" oninput="autoResize(this)"
                                                    class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"><?= htmlspecialchars($item['item_desc']) ?></textarea>
                                            </td>
                                            <td class="px-2 py-4">
                                                <input type="number" name="item_qty[]" value="<?= $item['item_qty'] ?>"
                                                    min="0" step="0.01" oninput="calculateTotal()"
                                                    class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black text-indigo-600 focus:bg-indigo-50">
                                            </td>
                                            <td class="px-2 py-4">
                                                <input type="text" name="item_unit[]"
                                                    value="<?= htmlspecialchars($item['item_unit']) ?>"
                                                    class="w-full bg-transparent border-b border-slate-100 text-center text-xs font-bold outline-none focus:border-indigo-400">
                                            </td>
                                            <td class="px-2 py-4">
                                                <input type="number" name="item_price[]"
                                                    value="<?= number_format($item['item_price'], 2, '.', '') ?>"
                                                    step="0.01" oninput="calculateTotal()"
                                                    class="w-full bg-transparent border-none text-right text-sm font-mono font-black focus:ring-0">
                                            </td>
                                            <td class="px-2 py-4">
                                                <input type="number" name="item_discount[]"
                                                    value="<?= number_format($item['item_discount'] ?? 0, 2, '.', '') ?>"
                                                    step="0.01" oninput="calculateTotal()"
                                                    class="w-full bg-amber-50/50 border-none rounded-lg px-2 py-2 text-right text-sm font-mono font-black text-amber-600 focus:bg-amber-100 outline-none">
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm font-mono font-black text-slate-700 row-total">
                                                <?= number_format($row_total, 2) ?>
                                            </td>
                                            <td class="px-4 py-4 text-center">
                                                <button type="button" onclick="removeRow(this)"
                                                    class="text-slate-200 hover:text-red-500 transition-colors">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <div
                            class="p-6 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-start gap-6">
                            <div class="w-full md:flex-grow">
                                <label
                                    class="text-[10px] font-bold text-slate-400 uppercase block mb-2">หมายเหตุการสั่งซื้อ</label>
                                <textarea name="notes" rows="3"
                                    class="w-full p-3 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-500 resize-none"><?= htmlspecialchars($po_data['notes']) ?></textarea>
                            </div>
                            <div class="w-full md:w-72 space-y-2">
                                <div class="flex justify-between text-sm font-bold text-slate-500">
                                    <span>Subtotal</span>
                                    <span id="subtotal_display">0.00</span>
                                </div>
                                <div class="flex justify-between text-sm font-bold text-slate-500">
                                    <span>VAT (<span
                                            id="vat_percent_label"><?= number_format($po_data['vat_percent'] ?? 7, 0) ?></span>%)</span>

                                    <div class="flex items-center gap-1">
                                        <span
                                            id="vat_display"><?= number_format($po_data['vat_amount'] ?? 0, 2) ?></span>
                                        <span class="text-[10px] text-slate-400">THB</span>
                                    </div>
                                </div>
                                <div
                                    class="flex justify-between text-lg font-black text-indigo-600 pt-2 border-t border-slate-200">
                                    <span>Grand Total</span>
                                    <span id="grandtotal_display">0.00</span>
                                </div>
                                <button type="submit"
                                    class="w-full mt-4 py-3 bg-indigo-600 text-white font-black rounded-xl hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 ">
                                    <i class="fas fa-save"></i> อัปเดตข้อมูล PO
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
    // 1. เพิ่มแถวรายการสินค้า
    function addItemRow() {
        const tbody = document.querySelector('#itemsTable tbody');
        const rowCount = tbody.rows.length + 1;
        const newRow = `
        <tr class="item-row group border-t border-slate-100">
            <td class="px-6 py-4 text-center text-xs font-bold text-slate-300">${rowCount}</td>
            <td class="px-2 py-4">
                <textarea name="item_desc[]" placeholder="ระบุรายละเอียด..." rows="1" oninput="autoResize(this)" class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
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
            <td class="px-2 py-4">
                <input type="number" name="item_discount[]" value="0.00" step="0.01" oninput="calculateTotal()" class="w-full bg-transparent border-none text-right text-sm font-mono text-red-500 focus:ring-0" placeholder="0.00">
            </td>
            <td class="px-6 py-4 text-right text-sm font-mono font-black text-slate-700 row-total">0.00</td>
            <td class="px-4 py-4 text-center">
                <button type="button" onclick="removeRow(this)" class="text-slate-200 hover:text-red-500 transition-colors">
                    <i class="fas fa-times-circle"></i>
                </button>
            </td>
        </tr>`;
        tbody.insertAdjacentHTML('beforeend', newRow);
        calculateTotal();
    }

    // 2. ลบแถวรายการ
    function removeRow(btn) {
        const tbody = document.querySelector('#itemsTable tbody');
        if (tbody.rows.length > 1) {
            btn.closest('tr').remove();
            reindexRows();
            calculateTotal();
        }
    }

    // 3. รันเลขลำดับใหม่
    function reindexRows() {
        document.querySelectorAll('.item-row').forEach((row, index) => {
            row.querySelector('td:first-child').innerText = index + 1;
        });
    }

    function calculateTotal() {
        let subtotal = 0;

        // 1. ดึงค่า % VAT จาก Select box มาใช้ (ถ้าไม่มีให้ default เป็น 7)
        const vatSelect = document.getElementById('vat_percent');
        const vatRate = vatSelect ? (parseFloat(vatSelect.value) / 100) : 0.07;

        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
            const price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
            const discount = parseFloat(row.querySelector('[name="item_discount[]"]')?.value) || 0;

            const total = (qty * price) - discount;
            row.querySelector('.row-total').innerText = total.toLocaleString(undefined, { minimumFractionDigits: 2 });
            subtotal += total;
        });

        // 2. คำนวณ VAT ตาม Rate ที่ดึงมาได้จริง
        const vat = subtotal * vatRate;
        const grandtotal = subtotal + vat;

        // 3. อัปเดต Label แสดงผล % (เช่น VAT (0%))
        if (document.getElementById('vat_percent_label')) {
            document.getElementById('vat_percent_label').innerText = (vatRate * 100).toFixed(0);
        }

        // อัปเดตค่าที่หน้าจอ
        if (document.getElementById('subtotal_display')) document.getElementById('subtotal_display').innerText = subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        if (document.getElementById('vat_display')) document.getElementById('vat_display').innerText = vat.toLocaleString(undefined, { minimumFractionDigits: 2 });
        if (document.getElementById('grandtotal_display')) document.getElementById('grandtotal_display').innerText = grandtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
    }
    // 5. จัดการข้อมูล Supplier
    function updateSupplierInfo() {
        try {
            const select = document.getElementById('supplier_select');
            if (!select || !select.value) return;

            const selectedOption = select.options[select.selectedIndex];
            const dataAttr = selectedOption.getAttribute('data-info');
            if (!dataAttr) return;

            const data = JSON.parse(dataAttr);
            document.getElementById('comp_name').innerText = data.company_name || '-';
            document.getElementById('comp_tax').innerText = data.tax_id || '-';
            document.getElementById('comp_phone').innerText = data.phone || '-';
            document.getElementById('comp_email').innerText = data.email || '-';
            document.getElementById('comp_addr').innerText = data.address || '-';

            const logoImg = document.getElementById('comp_logo_preview');
            if (logoImg) logoImg.src = data.logo_path ? 'uploads/' + data.logo_path : 'assets/img/default-logo.png';
        } catch (e) {
            console.error("Error parsing supplier info:", e);
        }
    }

    // 6. ขยาย Textarea อัตโนมัติ
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }

    // เมื่อหน้าโหลดเสร็จ
    document.addEventListener('DOMContentLoaded', () => {
        updateSupplierInfo();
        calculateTotal();

        // สั่งขยาย textarea ทุกตัวที่อาจจะมีข้อมูลอยู่แล้ว
        setTimeout(() => {
            document.querySelectorAll('textarea[name="item_desc[]"]').forEach(el => autoResize(el));
        }, 100);
    });
</script>
<?php include 'footer.php'; ?>