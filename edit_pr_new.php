<?php
require_once 'config.php';
include 'header.php';

// 1. รับค่า ID ของ PR ที่ต้องการแก้ไข
$pr_id = intval($_GET['id'] ?? 0);

// 2. ดึงข้อมูลหลักจากตาราง pr
$sql_pr = "SELECT * FROM pr WHERE id = '" . mysqli_real_escape_string($conn, $pr_id) . "'";
$res_pr = mysqli_query($conn, $sql_pr);
$pr_data = mysqli_fetch_assoc($res_pr);

if (!$pr_data) {
    echo "<script>alert('ไม่พบข้อมูลใบขอซื้อ'); window.location.href='pr_list.php';</script>";
    exit;
}

// 3. จัดการข้อมูล "ผู้ขอซื้อ/ลูกค้า"
$customer = null;
if ($pr_data['is_internal'] == 1) {
    $u_id = mysqli_real_escape_string($conn, $pr_data['created_by']);
    $user_query = mysqli_query($conn, "SELECT name as customer_name, '-' as tax_id, 'ภายในองค์กร' as address FROM users WHERE id = '$u_id'");
    $customer = mysqli_fetch_assoc($user_query);
    if (!$customer) {
        $customer = ['customer_name' => 'พนักงาน (ไม่ระบุ)', 'tax_id' => '-', 'address' => 'ภายในองค์กร'];
    }
    $customer['is_internal'] = true;
} else {
    $c_id = mysqli_real_escape_string($conn, $pr_data['customer_id']);
    $cust_query = mysqli_query($conn, "SELECT * FROM customers WHERE id = '$c_id'");
    $customer = mysqli_fetch_assoc($cust_query);
}

// 4. ดึงรายการสินค้าเดิม
$items_query = mysqli_query($conn, "SELECT * FROM pr_items WHERE pr_id = '" . mysqli_real_escape_string($conn, $pr_id) . "' ORDER BY id ASC");

// 5. ดึงข้อมูลผู้ขาย
$suppliers_query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY id ASC");
$suppliers = [];
while ($s = mysqli_fetch_assoc($suppliers_query)) {
    $suppliers[] = $s;
}
?>

<form action="api/update_pr_new.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="pr_id" value="<?= $pr_id ?>">
    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($pr_data['customer_id']) ?>">

    <div class="bg-slate-50 ">
        <div class="container-fluid p-0">
            <div class="lg:col-span-1 mb-6">
                <div class="bg-white p-1.5 px-2 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="flex items-center gap-2">
                        <div class="shrink-0 bg-slate-50 p-1 rounded-lg border border-slate-100 flex items-center justify-center w-9 h-9">
                            <img id="comp_logo_preview" src="" class="h-7 w-7 object-contain hidden">
                            <i id="comp_logo_icon" class="fas fa-building text-lg text-slate-400"></i>
                        </div>

                        <div class="w-48 shrink-0">
                            <select name="supplier_id" id="supplier_select" onchange="updateSupplierInfo()"
                                class="w-full px-2 py-1.5 bg-slate-50 border border-slate-100 rounded-lg text-[11px] focus:border-indigo-500 outline-none font-bold text-slate-700 cursor-pointer">
                                <option value="0">เลือกผู้ขาย...</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['id'] ?>" data-info='<?= json_encode($sup, ENT_QUOTES) ?>'
                                        <?= ($pr_data['supplier_id'] == $sup['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sup['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex-1 min-w-0 bg-slate-50/50 rounded-lg border-l-2 border-indigo-500 px-2 py-0.5">
                            <div class="flex items-center gap-2 overflow-hidden">
                                <span id="comp_name" class="text-[12px] font-black text-slate-800 truncate leading-tight uppercase">-</span>
                                <span class="text-[8px] font-mono text-slate-400 shrink-0">ID: <span id="comp_tax" class="font-bold text-slate-600">-</span></span>
                            </div>
                            <div class="flex items-center gap-2 text-[9px] text-slate-800 truncate leading-tight">
                                <span class="shrink-0"><i class="fas fa-phone-alt text-[7px] text-indigo-400 mr-0.5"></i><span id="comp_phone" class="font-bold">-</span></span>
                                <span class="text-slate-300">|</span>
                                <span class="truncate"><i class="fas fa-envelope text-[7px] text-indigo-400 mr-0.5"></i><span id="comp_email">-</span></span>
                                <span class="text-slate-300">|</span>
                                <span class="truncate italic opacity-70"><i class="fas fa-map-marker-alt text-[7px] text-indigo-400 mr-0.5"></i><span id="comp_addr">-</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white p-6 rounded-3xl border border-slate-200 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ความสำคัญ</label>
                        <select name="priority"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            <option value="น้อย" <?= $pr_data['priority'] == 'น้อย' ? 'selected' : '' ?>>น้อย (Low)</option>
                            <option value="ปานกลาง" <?= $pr_data['priority'] == 'ปานกลาง' ? 'selected' : '' ?>>ปานกลาง (Medium)</option>
                            <option value="เร่งด่วน" <?= $pr_data['priority'] == 'เร่งด่วน' ? 'selected' : '' ?>>เร่งด่วน (Urgent)</option>
                            <option value="วิกฤต" <?= $pr_data['priority'] == 'วิกฤต' ? 'selected' : '' ?>>วิกฤต (Critical)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">วันที่ต้องการสินค้า</label>
                        <input type="date" name="due_date" value="<?= $pr_data['due_date'] ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">อ้างอิงเอกสาร (Ref.)</label>
                        <input type="text" name="reference_no" value="<?= htmlspecialchars($pr_data['reference_no']) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">การชำระเงิน</label>
                        <select name="payment_term" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            <option value="cash" <?= $pr_data['payment_term'] == 'cash' ? 'selected' : '' ?>>เงินสด / โอนจ่าย</option>
                            <option value="30" <?= $pr_data['payment_term'] == '30' ? 'selected' : '' ?>>เครดิต 30 วัน</option>
                            <option value="60" <?= $pr_data['payment_term'] == '60' ? 'selected' : '' ?>>เครดิต 60 วัน</option>
                        </select>
                    </div>
                    <div class="col-span-1">
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ผู้ต้องการ / แผนก</label>
                        <input type="text" name="requested_by" value="<?= htmlspecialchars($pr_data['requested_by']) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                    </div>
                    <div class="col-span-1">
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">เบอร์โทรผู้ติดต่อ</label>
                        <input type="text" name="contact_tel" value="<?= htmlspecialchars($pr_data['contact_tel']) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ภาษี (VAT %)</label>
                        <select name="vat_percent" id="vat_percent" onchange="calculateTotal()" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                            <option value="7" <?= $pr_data['vat_percent'] == 7 ? 'selected' : '' ?>>7%</option>
                            <option value="0" <?= $pr_data['vat_percent'] == 0 ? 'selected' : '' ?>>0%</option>
                            <option value="10" <?= $pr_data['vat_percent'] == 10 ? 'selected' : '' ?>>10%</option>
                            <option value="5" <?= $pr_data['vat_percent'] == 5 ? 'selected' : '' ?>>5%</option>
                            <option value="3" <?= $pr_data['vat_percent'] == 3 ? 'selected' : '' ?>>3%</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">หัก ณ ที่จ่าย</label>
                        <select name="wht_percent" id="wht_percent" onchange="calculateTotal()" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                            <option value="0" <?= $pr_data['wht_percent'] == 0 ? 'selected' : '' ?>>0% </option>
                            <option value="1" <?= $pr_data['wht_percent'] == 1 ? 'selected' : '' ?>>1% </option>
                            <option value="3" <?= $pr_data['wht_percent'] == 3 ? 'selected' : '' ?>>3% </option>
                            <option value="5" <?= $pr_data['wht_percent'] == 5 ? 'selected' : '' ?>>5% </option>
                        </select>
                    </div>
                </div>

                <div class="lg:col-span-3 space-y-6">
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-4">
                                <div>
                                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ประเภทค่าใช้จ่าย (Expense Cat.)</label>
                                    <select name="expense_cat_id" id="expense_cat_id" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500 transition-all">
                                        <option value="">-- รอเลือกผู้ขาย --</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ประเภทงบประมาณ (Budget Type)</label>
                                    <select name="budget_type_id" id="budget_type_id" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500 transition-all">
                                        <option value="">-- รอเลือกผู้ขาย --</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">วัตถุประสงค์ (Objective)</label>
                                    <select name="objective_id" id="objective_id" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500 transition-all">
                                        <option value="">-- รอเลือกผู้ขาย --</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-2">ประเภทวงเงิน</label>
                                    <div class="grid grid-cols-1 gap-2">
                                        <label class="flex items-center p-2 bg-slate-50 border border-slate-100 rounded-xl cursor-pointer hover:bg-white hover:border-indigo-200 transition-all">
                                            <input type="radio" name="budget_limit_type" value="low" <?= (isset($pr_data['budget_limit_type']) && trim($pr_data['budget_limit_type']) == 'low') ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                            <span class="ml-2 text-[11px] font-bold text-slate-700">น้อยกว่า 10,000</span>
                                        </label>
                                        <label class="flex items-center p-2 bg-slate-50 border border-slate-100 rounded-xl cursor-pointer hover:bg-white hover:border-indigo-200 transition-all">
                                            <input type="radio" name="budget_limit_type" value="mid" <?= (isset($pr_data['budget_limit_type']) && trim($pr_data['budget_limit_type']) == 'mid') ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                            <span class="ml-2 text-[11px] font-bold text-slate-700">10,001 - 99,999</span>
                                        </label>
                                        <label class="flex items-center p-2 bg-slate-50 border border-slate-100 rounded-xl cursor-pointer hover:bg-white hover:border-indigo-200 transition-all">
                                            <input type="radio" name="budget_limit_type" value="high" <?= (isset($pr_data['budget_limit_type']) && trim($pr_data['budget_limit_type']) == 'high') ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                            <span class="ml-2 text-[11px] font-bold text-slate-700">มากกว่า 100,000</span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ยอดงบ</label>
                                    <input type="number" name="budget_amount" value="<?= $pr_data['budget_amount'] ?>" step="0.01" placeholder="0.00" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none text-right ">
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">รายละเอียดงบประมาณ</label>
                                    <textarea name="budget_details" rows="3" placeholder="ระบุรายละเอียดที่มาของงบ..." class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-medium outline-none focus:border-indigo-500 min-h-[60px]"><?= htmlspecialchars($pr_data['budget_details']) ?></textarea>
                                </div>
                                <div>
                                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ความคาดหวัง</label>
                                    <textarea name="expectation" rows="2" placeholder="ผลที่คาดว่าจะได้รับ..." class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-medium outline-none focus:border-indigo-500 min-h-[60px]"><?= htmlspecialchars($pr_data['expectation']) ?></textarea>
                                </div>
                                <div>
                                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">วิธีปฏิบัติ</label>
                                    <textarea name="practice_method" rows="2" placeholder="ขั้นตอนการทำงาน..." class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-medium outline-none focus:border-indigo-500 min-h-[60px]"><?= htmlspecialchars($pr_data['practice_method']) ?></textarea>
                                </div>
                            </div>
                            <div class="col-span-full grid grid-cols-1 md:grid-cols-2 gap-4 mt-2 pt-4 border-t border-slate-100">
                                <?php for ($i = 1; $i <= 2; $i++): 
                                    $fieldName = "attachment_$i";
                                    $existingFile = $pr_data[$fieldName] ?? '';
                                ?>
                                <div class="relative">
                                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1 ml-1">ไฟล์แนบ <?= $i ?></label>
                                    <div class="flex items-center gap-2" id="file_wrapper_<?= $i ?>">
                                        <?php if (!empty($existingFile)): ?>
                                            <div id="existing_file_<?= $i ?>" class="flex items-center gap-2 bg-indigo-50 px-3 py-1.5 rounded-xl border border-indigo-100 w-full">
                                                <a href="uploads/pr/<?= htmlspecialchars($existingFile) ?>" target="_blank" class="text-indigo-700 text-[11px] font-bold truncate hover:underline flex-grow">
                                                    <i class="fas fa-file-alt mr-1"></i> <?= htmlspecialchars($existingFile) ?>
                                                </a>
                                                <button type="button" onclick="removeExistingFile(<?= $i ?>)" class="text-red-500 hover:text-red-700 shrink-0">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <input type="hidden" name="delete_file_<?= $i ?>" value="0" id="delete_file_<?= $i ?>">
                                            </div>
                                            <input type="file" name="<?= $fieldName ?>" id="input_file_<?= $i ?>" class="hidden w-full px-2 py-1.5 bg-slate-50 border border-slate-100 rounded-xl text-[11px] font-bold outline-none file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[12px] file:font-bold file:bg-indigo-50 file:text-indigo-600">
                                        <?php else: ?>
                                            <input type="file" name="<?= $fieldName ?>" id="input_file_<?= $i ?>" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-100 rounded-xl text-[11px] font-bold outline-none file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[12px] file:font-bold file:bg-indigo-50 file:text-indigo-600">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden mt-4">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/30">
                <span class="text-xs font-black text-slate-700 uppercase tracking-widest"><i class="fas fa-shopping-cart mr-2 text-indigo-500"></i> รายการที่ขอซื้อ</span>
                <button type="button" onclick="addItemRow()" class="px-4 py-2 bg-indigo-600 text-white text-[11px] font-black rounded-xl hover:bg-indigo-700 transition-all ">เพิ่มรายการสินค้า</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="itemsTable">
                    <thead class="bg-slate-50/80 text-[12px] uppercase text-slate-800 font-black border-b border-slate-200">
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
                        <?php $i = 1; while($item = mysqli_fetch_assoc($items_query)): 
                            $total = ($item['item_qty'] * $item['item_price']) - $item['item_discount']; ?>
                            <tr class="item-row group">
                                <td class="px-6 py-4 text-center text-xs font-bold text-slate-800 row-number"><?= $i++ ?></td>
                                <td class="px-2 py-4"><textarea name="item_desc[]" rows="1" oninput="autoResize(this)" class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"><?= htmlspecialchars($item['item_desc']) ?></textarea></td>
                                <td class="px-2 py-4"><input type="number" name="item_qty[]" value="<?= $item['item_qty'] ?>" step="0.01" oninput="calculateTotal()" class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black text-indigo-600 focus:bg-indigo-50"></td>
                                <td class="px-2 py-4"><input type="text" name="item_unit[]" value="<?= htmlspecialchars($item['item_unit']) ?>" class="w-full bg-transparent border-b border-slate-100 text-center text-xs font-bold outline-none focus:border-indigo-400"></td>
                                <td class="px-2 py-4"><input type="number" name="item_price[]" value="<?= $item['item_price'] ?>" step="0.01" oninput="calculateTotal()" class="w-full bg-transparent border-none text-right text-sm font-mono font-black focus:ring-0"></td>
                                <td class="px-2 py-4"><input type="number" name="item_discount[]" value="<?= $item['item_discount'] ?>" step="0.01" oninput="calculateTotal()" class="w-full bg-amber-50/50 border-none rounded-lg px-2 py-2 text-right text-sm font-mono font-black text-amber-600 focus:bg-amber-50"></td>
                                <td class="px-6 py-4 text-right text-sm font-mono font-black text-slate-700 row-total"><?= number_format($total, 2) ?></td>
                                <td class="px-4 py-4 text-center"><button type="button" onclick="removeRow(this)" class="text-slate-200 hover:text-red-500 transition-colors"><i class="fas fa-times-circle"></i></button></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-6 bg-slate-50/50 border-t border-slate-100 flex justify-between items-start gap-6">
                <div class="w-full md:flex-grow">
                    <label class="text-[12px] font-bold text-slate-800 uppercase block mb-2">หมายเหตุ</label>
                    <textarea name="notes" rows="3" class="w-full p-3 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-300"><?= htmlspecialchars($pr_data['notes']) ?></textarea>
                </div>
                <div class="w-full md:w-80 space-y-2">
                    <div class="flex justify-between text-sm font-bold text-slate-800"><span>รวม</span><span id="subtotal_display">0.00</span></div>
                    <div class="flex justify-between text-sm font-bold text-slate-800"><span>หัก ณ ที่จ่าย (<span id="wht_percent_label"><?= $pr_data['wht_percent'] ?></span>%)</span><span>- <span id="wht_display">0.00</span></span></div>
                    <div class="flex justify-between text-sm font-bold text-slate-800"><span>ภาษี (<span id="vat_percent_label"><?= $pr_data['vat_percent'] ?></span>%)</span><span id="vat_display">0.00</span></div>
                    <div class="flex justify-between text-lg font-black text-indigo-600 pt-2 border-t border-slate-200"><span>ยอดสุทธิ</span><span id="grandtotal_display">0.00</span></div>
                    <button type="submit" class="w-full mt-4 py-3 bg-indigo-600 text-white font-black rounded-xl hover:bg-indigo-700 transition-all">บันทึกและแก้ไข</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    function addItemRow() {
        const tbody = document.querySelector('#itemsTable tbody');
        const newRow = document.createElement('tr');
        newRow.className = "item-row group border-t border-slate-100 hover:bg-slate-50/50 transition-colors";
        newRow.innerHTML = `
        <td class="px-6 py-4 text-center text-xs font-bold text-slate-800 row-number"></td>
        <td class="px-2 py-4">
            <textarea name="item_desc[]" placeholder="ระบุชื่อสินค้า / รายละเอียด..." rows="1" oninput="autoResize(this)" class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
        </td>
        <td class="px-2 py-4">
            <input type="number" name="item_qty[]" value="1" min="0" step="0.01" oninput="calculateTotal()" class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black text-indigo-600 focus:bg-indigo-50 outline-none">
        </td>
        <td class="px-2 py-4">
            <input type="text" name="item_unit[]" placeholder="ชิ้น/หน่วย" class="w-full bg-transparent border-b border-slate-100 text-center text-xs font-bold outline-none focus:border-indigo-400">
        </td>
        <td class="px-2 py-4">
            <input type="number" name="item_price[]" value="0.00" step="0.01" oninput="calculateTotal()" class="w-full bg-transparent border-none text-right text-sm font-mono font-black focus:ring-0 outline-none">
        </td>
        <td class="px-2 py-4">
            <input type="number" name="item_discount[]" value="0.00" step="0.01" oninput="calculateTotal()" class="w-full bg-amber-50/50 border-none rounded-lg px-2 py-2 text-right text-sm font-mono font-black text-amber-600 focus:bg-amber-100 outline-none">
        </td>
        <td class="px-6 py-4 text-right text-sm font-mono font-black text-slate-700 row-total">0.00</td>
        <td class="px-4 py-4 text-center">
            <button type="button" onclick="removeRow(this)" class="text-slate-200 hover:text-red-500 transition-colors"><i class="fas fa-times-circle"></i></button>
        </td>
    `;
        tbody.appendChild(newRow);
        updateRowNumbers();
        autoResize(newRow.querySelector('textarea'));
    }

    function removeRow(btn) {
        const tbody = document.querySelector('#itemsTable tbody');
        if (tbody.rows.length > 1) {
            btn.closest('tr').remove();
            updateRowNumbers();
            calculateTotal();
        } else {
            alert("ต้องมีอย่างน้อย 1 รายการครับจาร!");
        }
    }

    function updateRowNumbers() {
        document.querySelectorAll('.row-number').forEach((td, index) => { td.innerText = index + 1; });
    }

    function calculateTotal() {
        let subtotal = 0;
        const vatSelect = document.querySelector('select[name="vat_percent"]');
        const vatPercent = vatSelect ? parseFloat(vatSelect.value) : 0;
        const vatLabel = document.getElementById('vat_percent_label');
        if (vatLabel) vatLabel.innerText = vatPercent;

        const whtSelect = document.querySelector('select[name="wht_percent"]');
        const whtPercent = whtSelect ? parseFloat(whtSelect.value) : 0;
        const whtLabel = document.getElementById('wht_percent_label');
        if (whtLabel) whtLabel.innerText = whtPercent;

        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
            const price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
            const discount = parseFloat(row.querySelector('[name="item_discount[]"]').value) || 0;
            const total = (qty * price) - discount;
            row.querySelector('.row-total').innerText = total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            subtotal += total;
        });

        const vat = subtotal * (vatPercent / 100);
        const wht = subtotal * (whtPercent / 100);
        const grandtotal = (subtotal + vat) - wht;

        document.getElementById('subtotal_display').innerText = subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('vat_display').innerText = vat.toLocaleString(undefined, { minimumFractionDigits: 2 });
        const whtDisplay = document.getElementById('wht_display');
        if (whtDisplay) whtDisplay.innerText = wht.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('grandtotal_display').innerText = grandtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
    }

    function updateSupplierInfo() {
        const select = document.getElementById('supplier_select');
        if (!select) return;
        const selectedOption = select.options[select.selectedIndex];
        const infoAttr = selectedOption ? selectedOption.getAttribute('data-info') : null;
        const logoImg = document.getElementById('comp_logo_preview');
        const logoIcon = document.getElementById('comp_logo_icon');

        if (!infoAttr || select.value === "" || select.value === "0") {
            document.getElementById('comp_name').innerText = '-';
            document.getElementById('comp_tax').innerText = '-';
            document.getElementById('comp_phone').innerText = '-';
            document.getElementById('comp_email').innerText = '-';
            document.getElementById('comp_addr').innerText = '-';
            if (logoImg && logoIcon) { logoImg.src = ''; logoImg.classList.add('hidden'); logoIcon.classList.remove('hidden'); }
            resetRelatedDropdowns();
            return;
        }

        try {
            const data = JSON.parse(infoAttr);
            document.getElementById('comp_name').innerText = data.company_name || '-';
            document.getElementById('comp_tax').innerText = data.tax_id || '-';
            document.getElementById('comp_phone').innerText = data.phone || '-';
            document.getElementById('comp_email').innerText = data.email || '-';
            document.getElementById('comp_addr').innerText = data.address || '-';
            if (logoImg && logoIcon) {
                if (data.logo_path && data.logo_path.trim() !== '') {
                    logoImg.src = 'uploads/' + data.logo_path;
                    logoImg.classList.remove('hidden');
                    logoIcon.classList.add('hidden');
                } else {
                    logoImg.src = '';
                    logoImg.classList.add('hidden');
                    logoIcon.classList.remove('hidden');
                }
            }
            fetchRelatedData(data.id, '<?= $pr_data['expense_cat_id'] ?>', '<?= $pr_data['budget_type_id'] ?>', '<?= $pr_data['objective_id'] ?>');
        } catch (e) { console.error("Error parsing supplier data:", e); }
    }

    function fetchRelatedData(sup_id, selected_exp, selected_bud, selected_obj) {
        const targets = [
            { id: 'expense_cat_id', action: 'get_expense_cats', selected: selected_exp },
            { id: 'budget_type_id', action: 'get_budget_types', selected: selected_bud },
            { id: 'objective_id', action: 'get_objectives', selected: selected_obj }
        ];

        targets.forEach(target => {
            const el = document.getElementById(target.id);
            if (!el) return;
            el.innerHTML = '<option value="">กำลังโหลด...</option>';
            fetch(`get_pr_support_data.php?action=${target.action}&sup_id=${sup_id}`)
                .then(response => response.json())
                .then(data => {
                    let html = '<option value="">-- เลือกรายการ --</option>';
                    data.forEach(item => { 
                        const selected = (item.id == target.selected) ? 'selected' : '';
                        const amountAttr = item.current_total_budget ? `data-amount="${item.current_total_budget}"` : 'data-amount="0"';
                        const spentAttr = item.total_spent ? `data-spent="${item.total_spent}"` : 'data-spent="0"';
                        html += `<option value="${item.id}" ${selected} ${amountAttr} ${spentAttr}>${item.name}</option>`; 
                    });
                    el.innerHTML = html;
                })
                .catch(err => { console.error(`Error fetching ${target.action}:`, err); el.innerHTML = '<option value="">โหลดข้อมูลไม่สำเร็จ</option>'; });
        });
    }

    function resetRelatedDropdowns() {
        ['expense_cat_id', 'budget_type_id', 'objective_id'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = '<option value="">-- รอเลือกผู้ขาย --</option>';
        });
    }

    function autoResize(textarea) {
        if (!textarea) return;
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }
    
    function clearFile(id) { document.getElementById(id).value = ''; }
    
    function removeExistingFile(index) {
        // ซ่อนคอนเทนเนอร์ของไฟล์เดิม
        document.getElementById('existing_file_' + index).style.display = 'none';
        
        // แสดงช่อง input file
        const inputFile = document.getElementById('input_file_' + index);
        if (inputFile) {
            inputFile.classList.remove('hidden');
        }
        
        // ตั้งค่าตัวแปรเพื่อบอก API ให้ลบไฟล์
        document.getElementById('delete_file_' + index).value = '1';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const supplierSelect = document.getElementById('supplier_select');
        if (supplierSelect && supplierSelect.value !== "0") { updateSupplierInfo(); }
        calculateTotal();
        document.querySelectorAll('textarea[name="item_desc[]"]').forEach(el => { autoResize(el); });
    });
</script>
<?php include 'footer.php'; ?>
