<?php
require_once 'config.php';
include 'header.php';

// รับ ID ของใบเสนอราคาที่ต้องการแก้ไข
$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : 0;

// 1. ดึงข้อมูลใบเสนอราคาหลักพร้อมข้อมูลลูกค้า
$sql_q = "SELECT q.*, c.customer_name, c.address as cust_address, c.tax_id as cust_tax, 
                 c.contact_person, c.phone as cust_phone, c.email as cust_email
          FROM quotations q 
          LEFT JOIN customers c ON q.customer_id = c.id 
          WHERE q.id = '$id' LIMIT 1";
$res_q = mysqli_query($conn, $sql_q);
$data = mysqli_fetch_assoc($res_q);

if (!$data) {
    echo "<script>alert('ไม่พบข้อมูลเอกสาร'); window.history.back();</script>";
    exit;
}

// 2. ดึงข้อมูลรายการสินค้า (Items)
$sql_items = "SELECT * FROM quotation_items WHERE quotation_id = '$id' ORDER BY id ASC";
$res_items = mysqli_query($conn, $sql_items);

// 3. ดึงข้อมูลบริษัทผู้ขาย (Suppliers) เพื่อให้เลือกเปลี่ยนได้ (ถ้ามี)
$suppliers_query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY id ASC");
$suppliers = [];
while ($s = mysqli_fetch_assoc($suppliers_query)) {
    $suppliers[] = $s;
}
?>

<form action="api/update_quotation.php" method="POST">
    <input type="hidden" name="quotation_id" value="<?= $id ?>">
    <input type="hidden" name="customer_id" value="<?= $data['customer_id'] ?>">

    <div class="bg-slate-50 min-h-screen py-8">
        <div class="max-w-[1400px] mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6 px-4">

            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <h3
                            class="text-xs font-black text-slate-400 flex items-center gap-2 uppercase tracking-[0.2em]">
                            <i class="fas fa-file-invoice text-indigo-500 text-base"></i> Issuer / ผู้ขาย
                        </h3>
                        <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                            <img id="comp_logo_preview" src="uploads/default-logo.png" class="h-10 w-10 object-contain">
                        </div>
                    </div>

                    <select name="supplier_id" id="supplier_select" onchange="updateSupplierInfo()"
                        class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-2xl text-sm focus:border-indigo-500 focus:bg-white outline-none font-bold text-slate-700 mb-5 transition-all cursor-pointer">
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" data-info='<?= json_encode($sup, ENT_QUOTES) ?>'
                                <?= ($sup['id'] == $data['supplier_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sup['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div
                        class="p-5 bg-gradient-to-br from-indigo-50/50 to-slate-50 rounded-2xl border border-indigo-100/50 space-y-4">
                        <div>
                            <div id="comp_name" class="text-base font-black text-slate-800 leading-tight">กำลังโหลด...
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <span
                                    class="text-[10px] font-black bg-indigo-600 text-white px-2 py-0.5 rounded uppercase tracking-wider">Tax
                                    ID</span>
                                <span id="comp_tax" class="text-xs font-mono font-bold text-slate-600">-</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-2 text-xs">
                            <div class="flex items-center gap-3"><i
                                    class="fas fa-phone-alt text-indigo-500 w-4"></i><span id="comp_phone"
                                    class="font-bold text-slate-600">-</span></div>
                            <div class="flex items-center gap-3"><i
                                    class="fas fa-envelope text-indigo-500 w-4"></i><span id="comp_email"
                                    class="font-bold text-slate-600">-</span></div>
                        </div>
                        <div class="pt-3 border-t border-indigo-100/50">
                            <div id="comp_addr" class="text-[11px] text-slate-500 leading-relaxed font-medium italic">-
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-900 rounded-2xl overflow-hidden border border-slate-800 shadow-xl">
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
                            <div class="text-white font-black text-lg"><?= htmlspecialchars($data['customer_name']) ?>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 bg-slate-800/50 rounded-xl border border-slate-700/50">
                                <label class="text-[9px] uppercase font-bold text-slate-500 block mb-1">Tax ID</label>
                                <div class="text-xs text-indigo-200 font-mono font-bold"><?= $data['cust_tax'] ?: '-' ?>
                                </div>
                            </div>
                            <div class="p-3 bg-slate-800/50 rounded-xl border border-slate-700/50">
                                <label class="text-[9px] uppercase font-bold text-slate-500 block mb-1">Contact
                                    Person</label>
                                <div class="text-xs text-white font-bold">
                                    <?= htmlspecialchars($data['contact_person'] ?: '-') ?>
                                </div>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-slate-800">
                            <label
                                class="text-[10px] uppercase font-bold text-slate-500 block mb-1 tracking-widest">Address</label>
                            <div class="text-[12px] text-slate-400 leading-relaxed font-medium">
                                <?= nl2br(htmlspecialchars($data['cust_address'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label
                                class="text-[10px] font-bold text-slate-400 uppercase block mb-1">เลขที่เอกสาร</label>
                            <input type="text" name="doc_no" value="<?= htmlspecialchars($data['doc_no']) ?>" readonly
                                class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-lg text-sm font-bold outline-none text-slate-500 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">วันที่</label>
                            <input type="date" name="doc_date" value="<?= $data['doc_date'] ?>"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">ยืนราคา
                                (วัน)</label>
                            <input type="number" name="valid_until" value="<?= $data['valid_until'] ?? 30 ?>"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">การชำระเงิน</label>
                            <select name="payment_term"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:border-indigo-500">
                                <option value="30" <?= ($data['payment_term'] == '30') ? 'selected' : '' ?>>เครดิต 30 วัน
                                </option>
                                <option value="cash" <?= ($data['payment_term'] == 'cash') ? 'selected' : '' ?>>เงินสด
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
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
                                    <th class="px-4 py-3 w-32 text-right">รวมเงิน</th>
                                    <th class="px-4 py-3 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php
                                $count = 1;
                                while ($item = mysqli_fetch_assoc($res_items)):
                                    ?>
                                    <tr class="item-row group">
                                        <td class="px-4 py-3 text-center text-xs font-bold text-slate-400"><?= $count++ ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <textarea name="item_desc[]" rows="1" oninput="autoResize(this)"
                                                placeholder="ระบุรายละเอียดสินค้า..."
                                                class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-medium resize-none block overflow-hidden"><?= htmlspecialchars($item['item_desc']) ?></textarea>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="item_qty[]" value="<?= $item['item_qty'] ?>" min="1"
                                                step="0.01" oninput="calculateTotal()"
                                                class="w-full bg-slate-50 border-none rounded-md px-2 py-1 text-center text-sm font-bold focus:bg-indigo-50">
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="item_price[]" value="<?= $item['item_price'] ?>"
                                                step="0.01" oninput="calculateTotal()"
                                                class="w-full bg-transparent border-none text-right text-sm font-mono font-bold focus:ring-0">
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-sm font-mono font-bold text-slate-700 row-total">
                                            <?= number_format($item['item_total'], 2) ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button type="button" onclick="removeRow(this)"
                                                class="text-slate-300 hover:text-red-500 transition-colors">
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
                            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-2">หมายเหตุ</label>
                            <textarea name="notes" rows="3"
                                class="w-full p-3 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-500 resize-none"><?= htmlspecialchars($data['notes']) ?></textarea>
                        </div>
                        <div class="w-full md:w-72 space-y-2">
                            <div class="flex justify-between text-sm font-bold text-slate-500">
                                <span>รวมเป็นเงิน</span>
                                <span id="subtotal_display"><?= number_format($data['subtotal'], 2) ?></span>
                            </div>
                            <div class="flex justify-between text-sm font-bold text-slate-500">
                                <span>ภาษีมูลค่าเพิ่ม (7%)</span>
                                <span id="vat_display"><?= number_format($data['vat'], 2) ?></span>
                            </div>
                            <div
                                class="flex justify-between text-lg font-black text-indigo-600 pt-2 border-t border-slate-200">
                                <span>รวม</span>
                                <span id="grandtotal_display"><?= number_format($data['grand_total'], 2) ?></span>
                            </div>
                            <button
                                class="w-full mt-4 py-3 bg-indigo-600 text-white font-black rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-check-circle"></i> อัปเดตข้อมูลเอกสาร
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    // ใช้ฟังก์ชันเดิมที่คุณมี แต่ปรับปรุงนิดหน่อย
    function addItemRow() {
        const tbody = document.querySelector('#itemsTable tbody');
        const rowCount = tbody.rows.length + 1;
        const newRow = `
        <tr class="item-row group border-t border-slate-100">
            <td class="px-4 py-3 text-center text-xs font-bold text-slate-400">${rowCount}</td>
            <td class="px-4 py-3">
                <textarea name="item_desc[]" 
                          rows="1" 
                          oninput="autoResize(this)" 
                          placeholder="ระบุรายละเอียด..." 
                          class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-medium resize-none block overflow-hidden"></textarea>
            </td>
            <td class="px-4 py-3">
                <input type="number" name="item_qty[]" value="1" min="1" oninput="calculateTotal()" class="w-full bg-slate-50 border-none rounded-md px-2 py-1 text-center text-sm font-bold focus:bg-indigo-50">
            </td>
            <td class="px-4 py-3">
                <input type="number" name="item_price[]" value="0.00" step="0.01" oninput="calculateTotal()" class="w-full bg-transparent border-none text-right text-sm font-mono font-bold focus:ring-0">
            </td>
            <td class="px-4 py-3 text-right text-sm font-mono font-bold text-slate-700 row-total">0.00</td>
            <td class="px-4 py-3 text-center">
                <button type="button" onclick="removeRow(this)" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fas fa-times-circle"></i></button>
            </td>
        </tr>`;
        tbody.insertAdjacentHTML('beforeend', newRow);
    }

    function removeRow(btn) {
        const tbody = document.querySelector('#itemsTable tbody');
        if (tbody.rows.length > 1) {
            btn.closest('tr').remove();
            calculateTotal();
            // เรียงเลขแถวใหม่
            Array.from(tbody.rows).forEach((row, index) => {
                row.cells[0].innerText = index + 1;
            });
        }
    }

    function calculateTotal() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
            const price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
            const total = qty * price;
            row.querySelector('.row-total').innerText = total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            subtotal += total;
        });

        const vat = subtotal * 0.07;
        const grandtotal = subtotal + vat;

        document.getElementById('subtotal_display').innerText = subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('vat_display').innerText = vat.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('grandtotal_display').innerText = grandtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateSupplierInfo() {
        const select = document.getElementById('supplier_select');
        const selectedOption = select.options[select.selectedIndex];
        if (!selectedOption.getAttribute('data-info')) return;

        const data = JSON.parse(selectedOption.getAttribute('data-info'));

        document.getElementById('comp_name').innerText = data.company_name;
        document.getElementById('comp_tax').innerText = data.tax_id || '-';
        document.getElementById('comp_phone').innerText = data.phone || '-';
        document.getElementById('comp_email').innerText = data.email || '-';
        document.getElementById('comp_addr').innerText = data.address || '-';

        const logoImg = document.getElementById('comp_logo_preview');
        logoImg.src = data.logo_path ? 'uploads/' + data.logo_path : 'uploads/default-logo.png';
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateSupplierInfo();
        calculateTotal();
    });

    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }

    // สั่งให้รันทันทีที่โหลดหน้าเสร็จ เพื่อให้รายการที่มีข้อมูลเดิมอยู่แล้วขยายความสูงให้พอดี
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('textarea[name="item_desc[]"]').forEach(textarea => {
            autoResize(textarea);
        });
    });
</script>

<?php include 'footer.php'; ?>