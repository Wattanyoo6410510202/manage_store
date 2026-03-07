<?php
require_once 'config.php';
include 'header.php';

// รับค่า ID ของ PR ที่ต้องการแก้ไข
$pr_id = $_GET['id'] ?? 0;

// 1. ดึงข้อมูลหลักจากตาราง pr (ดึงออกมาทั้งหมดรวมถึง is_internal และ created_by)
$sql_pr = "SELECT * FROM pr WHERE id = '" . mysqli_real_escape_string($conn, $pr_id) . "'";
$res_pr = mysqli_query($conn, $sql_pr);
$pr_data = mysqli_fetch_assoc($res_pr);

if (!$pr_data) {
    echo "<script>alert('ไม่พบข้อมูลใบขอซื้อ'); window.location.href='pr_list.php';</script>";
    exit;
}

// --- จุดสำคัญ: จัดการข้อมูล "ผู้ขอซื้อ/ลูกค้า" ---
$customer = null;

if ($pr_data['is_internal'] == 1) {
    // กรณีที่ 1: เป็นงานภายใน (ดึงชื่อคนเปิดจากตาราง users)
    $u_id = mysqli_real_escape_string($conn, $pr_data['created_by']);
    $user_query = mysqli_query($conn, "SELECT name as customer_name, '-' as tax_id, 'ภายในองค์กร' as address FROM users WHERE id = '$u_id'");
    $customer = mysqli_fetch_assoc($user_query);

    // ถ้าหา User ไม่เจอ (เช่นโดนลบ) ให้ใส่ค่า Default ไว้กันหน้าเว็บพัง
    if (!$customer) {
        $customer = ['customer_name' => 'พนักงาน (ไม่ระบุ)', 'tax_id' => '-', 'address' => 'ภายในองค์กร'];
    }
    $customer['is_internal'] = true; // แปะ Flag ไว้ใช้ใน HTML
} else {
    // กรณีที่ 2: เป็นงานลูกค้า (ดึงจากตาราง customers ตามปกติ)
    $c_id = mysqli_real_escape_string($conn, $pr_data['customer_id']);
    $cust_query = mysqli_query($conn, "SELECT * FROM customers WHERE id = '$c_id'");
    $customer = mysqli_fetch_assoc($cust_query);

    if (!$customer) {
        echo "<script>alert('ไม่พบข้อมูลลูกค้าที่เชื่อมโยงกับใบขอซื้อนี้'); window.location.href='pr_list.php';</script>";
        exit;
    }
}

// 3. ดึงรายการสินค้าเดิม (Items)
$items_query = mysqli_query($conn, "SELECT * FROM pr_items WHERE pr_id = '" . mysqli_real_escape_string($conn, $pr_id) . "' ORDER BY id ASC");

// 4. ดึงข้อมูลผู้ขาย (Suppliers)
$suppliers_query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY id ASC");
$suppliers = [];
while ($s = mysqli_fetch_assoc($suppliers_query)) {
    $suppliers[] = $s;
}
?>

<form action="api/update_pr.php" method="POST">
    <input type="hidden" name="pr_id" value="<?= $pr_id ?>">
    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($pr_data['customer_id']) ?>">

    <div class="bg-slate-50">
        <div class="max-w-[1400px] mx-auto ">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white p-5 rounded-3xl border border-slate-200">
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

                        <select name="supplier_id" id="supplier_select" onchange="updateSupplierInfo()"
                            class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-2xl text-sm focus:border-indigo-500 focus:bg-white outline-none font-bold text-slate-700 mb-5 transition-all cursor-pointer">
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>" data-info='<?= json_encode($sup, ENT_QUOTES) ?>'
                                    <?= ($sup['id'] == $pr_data['supplier_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sup['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div
                            class="p-5 bg-gradient-to-br from-indigo-50/50 to-slate-50 rounded-2xl border border-indigo-100/50 space-y-4">
                            <div>
                                <div id="comp_name" class="text-base font-black text-slate-800 leading-tight">
                                    กำลังโหลดข้อมูล...</div>
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

                    

                    <input type="hidden" name="customer_id" value="<?= $pr_data['customer_id'] ?>">
                </div>

                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">เลขที่เอกสาร</label>
                            <input type="text" value="<?= $pr_data['doc_no'] ?>"
                                class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl text-sm font-bold outline-none text-slate-500"
                                readonly>
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">วันที่ต้องการสินค้า</label>
                            <input type="date" name="due_date" value="<?= $pr_data['due_date'] ?>"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">อ้างอิงเอกสาร
                                (Ref.)</label>
                            <input type="text" name="reference_no"
                                value="<?= htmlspecialchars($pr_data['reference_no']) ?>"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">การชำระเงิน</label>
                            <select name="payment_term"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                                <option value="30" <?= $pr_data['payment_term'] == '30' ? 'selected' : '' ?>>เครดิต 30 วัน
                                </option>
                                <option value="60" <?= $pr_data['payment_term'] == '60' ? 'selected' : '' ?>>เครดิต 60 วัน
                                </option>
                                <option value="cash" <?= $pr_data['payment_term'] == 'cash' ? 'selected' : '' ?>>เงินสด /
                                    โอนจ่าย</option>
                            </select>
                        </div>
                        <div class="col-span-1">
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">ผู้ต้องการ /
                                แผนก</label>
                            <input type="text" name="requested_by"
                                value="<?= htmlspecialchars($pr_data['requested_by']) ?>"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                        </div>
                        <div class="col-span-1">
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase block mb-1">เบอร์โทรผู้ติดต่อ</label>
                            <input type="text" name="contact_tel"
                                value="<?= htmlspecialchars($pr_data['contact_tel']) ?>"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">ภาษี (VAT
                                %)</label>
                            <select name="vat_percent" id="vat_percent" onchange="calculateTotal()"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                                <option value="7" <?= $pr_data['vat_percent'] == 7 ? 'selected' : '' ?>>7%</option>
                                <option value="0" <?= $pr_data['vat_percent'] == 0 ? 'selected' : '' ?>>0%</option>
                                <option value="10" <?= $pr_data['vat_percent'] == 10 ? 'selected' : '' ?>>10%</option>
                                <option value="5" <?= $pr_data['vat_percent'] == 5 ? 'selected' : '' ?>>5%</option>
                                <option value="3" <?= $pr_data['vat_percent'] == 3 ? 'selected' : '' ?>>3%</option>
                            </select>
                        </div>
                         <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-1">หัก ณ ที่จ่าย</label>
                            <select name="wht_percent" id="wht_percent" onchange="calculateTotal()"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                                <option value="1" <?= $pr_data['wht_percent'] == 1 ? 'selected' : '' ?>>1%</option>
                                <option value="0" <?= $pr_data['wht_percent'] == 0 ? 'selected' : '' ?>>0%</option>
                                <option value="3" <?= $pr_data['wht_percent'] == 3 ? 'selected' : '' ?>>3%</option>
                                <option value="5" <?= $pr_data['wht_percent'] == 5 ? 'selected' : '' ?>>5%</option>
                            </select>
                        </div>
                    </div>
                    <div class="bg-slate-900 rounded-3xl border border-slate-800 p-6 space-y-3">
    <div class="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-indigo-400">
        <span><i class="fas fa-map-marker-alt mr-2"></i>Ship To</span>
        <span class="px-2 py-0.5 rounded-full border border-current opacity-70"><?= ($customer['is_internal'] ?? 0) ? 'Internal' : 'Customer' ?></span>
    </div>
    <div class="text-white font-black text-lg truncate"><?= htmlspecialchars($customer['customer_name'] ?? 'N/A') ?></div>
    <div class="text-indigo-300 text-[10px] font-mono opacity-60 italic leading-none"><?= ($customer['is_internal'] ?? 0) ? 'No Tax ID Required' : 'Tax ID: '.htmlspecialchars($customer['tax_id'] ?? '-') ?></div>
    <div class="text-slate-400 text-xs leading-relaxed bg-slate-800/30 p-3 rounded-xl border border-slate-700/50 italic line-clamp-2"><?= nl2br(htmlspecialchars($customer['address'] ?? '-')) ?></div>
</div>
                </div>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden mt-4">
                        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/30">
                            <span class="text-xs font-black text-slate-700 uppercase tracking-widest"><i
                                    class="fas fa-shopping-cart mr-2 text-indigo-500"></i> Order Items</span>
                            <button type="button" onclick="addItemRow()"
                                class="px-4 py-2 bg-indigo-600 text-white text-[11px] font-black rounded-xl hover:bg-indigo-700 transition-all ">
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

                        <div class="p-6 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-start gap-6">
                            <div class="w-full md:flex-grow">
                                <label
                                    class="text-[10px] font-bold text-slate-400 uppercase block mb-2">หมายเหตุ</label>
                                <textarea name="notes" rows="3"
                                    class="w-full p-3 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-500 resize-none"><?= htmlspecialchars($pr_data['notes']) ?></textarea>
                            </div>
                            <div class="w-full md:w-72 space-y-2">
                                <div class="flex justify-between text-sm font-bold text-slate-500">
                                    <span>รวมเป็นเงิน</span>
                                    <span id="subtotal_display">0.00</span>
                                </div>
                                <div class="flex justify-between text-sm font-bold text-slate-500">
                                    <span>หัก ณ ที่จ่าย </span>
                                    <span id="wht_display">0.00</span></span>
                                </div>
                                <div class="flex justify-between text-sm font-bold text-slate-500">
                                    <span>ภาษี</span>
                                    <span id="vat_display">0.00</span>
                                </div>
                                <div
                                    class="flex justify-between text-lg font-black text-indigo-600 pt-2 border-t border-slate-200">
                                    <span>รวมทั้งหมด</span>
                                    <span id="grandtotal_display">0.00</span>
                                </div>
                                <button
                                    class="w-full mt-4 py-3 bg-indigo-600 text-white font-black rounded-xl hover:bg-indigo-700 transition-all flex items-center justify-center gap-2">
                                    <i class="fas fa-save"></i> บันทึกการแก้ไข
                                </button>
                            </div>
                        </div>
                    </div>
        </div>
        
    </div>
</form>

<script>
    // 1. ฟังก์ชันเพิ่มแถวใหม่ (ปรับให้มีช่องส่วนลด)
    function addItemRow() {
        const tbody = document.querySelector('#itemsTable tbody');
        const rowCount = tbody.rows.length + 1;
        const newRow = `
        <tr class="item-row group border-t border-slate-100">
            <td class="px-6 py-4 text-center text-xs font-bold text-slate-300 row-number">${rowCount}</td>
            <td class="px-2 py-4">
                <textarea name="item_desc[]" rows="1" oninput="autoResize(this)" 
                    class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden" placeholder="ระบุรายละเอียด..."></textarea>
            </td>
            <td class="px-2 py-4">
                <input type="number" name="item_qty[]" value="1" min="0" step="0.01" oninput="calculateTotal()" 
                    class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black text-indigo-600 focus:bg-indigo-50">
            </td>
            <td class="px-2 py-4">
                <input type="text" name="item_unit[]" placeholder="หน่วย" 
                    class="w-full bg-transparent border-b border-slate-100 text-center text-xs font-bold outline-none focus:border-indigo-400">
            </td>
            <td class="px-2 py-4">
                <input type="number" name="item_price[]" value="0.00" step="0.01" oninput="calculateTotal()" 
                    class="w-full bg-transparent border-none text-right text-sm font-mono font-black focus:ring-0">
            </td>
            <td class="px-2 py-4">
                <input type="number" name="item_discount[]" value="0.00" step="0.01" oninput="calculateTotal()" 
                    class="w-full bg-amber-50/50 border-none rounded-lg px-2 py-2 text-right text-sm font-mono font-black text-amber-600 focus:bg-amber-100 outline-none">
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

    // 2. ลบแถวและรันเลขใหม่
    function removeRow(btn) {
        const tbody = document.querySelector('#itemsTable tbody');
        if (tbody.rows.length > 1) {
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

    function calculateTotal() {
    let subtotal = 0;
    // ดึงค่า % จาก Select
    const vatPercent = parseFloat(document.getElementById('vat_percent')?.value) || 0;
    const whtPercent = parseFloat(document.getElementById('wht_percent')?.value) || 0;

    // 1. คำนวณยอดรวมสินค้าทุกแถว
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
        const price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
        const discount = parseFloat(row.querySelector('[name="item_discount[]"]').value) || 0;
        
        const rowTotal = (qty * price) - discount;
        row.querySelector('.row-total').innerText = rowTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        subtotal += rowTotal;
    });

    // 2. คำนวณยอดภาษีต่างๆ
    const vatAmount = subtotal * (vatPercent / 100);
    const whtAmount = subtotal * (whtPercent / 100); // คำนวณยอดหัก ณ ที่จ่ายจาก Subtotal
    
    // 3. ยอดรวมบิล (ราคาสินค้า + VAT)
    const grandTotal = subtotal + vatAmount; 
    
    // 4. ยอดจ่ายจริง (Net Total) = ยอดรวมบิล - ยอดหัก ณ ที่จ่าย
    const netTotal = grandTotal - whtAmount; 

    // ฟังก์ชันช่วย Format ตัวเลข
    const format = (num) => num.toLocaleString(undefined, { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
    });
    
    // 5. แสดงผลลงหน้าจอ
    document.getElementById('subtotal_display').innerText = format(subtotal);
    document.getElementById('vat_display').innerText      = format(vatAmount);
    document.getElementById('wht_display').innerText      = format(whtAmount);
    
    // *** จุดที่จารต้องการ: ให้ช่องรวมทั้งหมดโชว์ยอดที่หัก WHT แล้ว ***
    document.getElementById('grandtotal_display').innerText = format(netTotal); 
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
        if (!textarea) return;
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }

    // 5. Initial Load
    document.addEventListener('DOMContentLoaded', () => {
        // อัปเดตข้อมูลผู้ขายตอนเริ่ม (ถ้ามีค่าอยู่แล้ว)
        if (document.getElementById('supplier_select')) {
            updateSupplierInfo();
        }

        // คำนวณยอดทั้งหมดทันทีที่โหลดหน้า (สำคัญมากสำหรับหน้า Edit)
        calculateTotal();

        // ปรับขนาด Textarea ชื่อสินค้าทั้งหมดตามข้อมูลที่โหลดมาจาก DB
        document.querySelectorAll('textarea[name="item_desc[]"]').forEach(el => autoResize(el));
    });
</script>
<?php include 'footer.php'; ?>