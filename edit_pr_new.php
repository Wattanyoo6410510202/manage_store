<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
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

$current_role = $_SESSION['role'] ?? '';
if (!empty($pr_data['approved_by_0']) && $current_role !== 'admin' && strpos($current_role, 'procure') !== 0) {
    echo "<script>alert('ไม่สามารถแก้ไขได้ หัวหน้างานอนุมัติแล้ว'); window.location.href='view_pr_new.php?id=$pr_id';</script>";
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

// 6. ดึงข้อมูลพนักงานทั้งหมด (ไม่จำกัด sup_id) เพื่อให้ dropdown แสดงค่าเก่าได้
$colleagues = [];
$col_query = mysqli_query($conn, "SELECT id, name, phone FROM users ORDER BY name ASC");
while ($col = mysqli_fetch_assoc($col_query)) {
    $colleagues[] = $col;
}

// 7. ดึงข้อมูลร้านค้า (Stores)
$stores_query = mysqli_query($conn, "SELECT id, store_name FROM stores ORDER BY store_name ASC");
$stores = [];
while ($st = mysqli_fetch_assoc($stores_query)) {
    $stores[] = $st;
}

// 8. ดึงกำหนดการผ่อนชำระ (ถ้ามี)
$installments = [];
if (!empty($pr_data['installment_period']) && $pr_data['installment_period'] > 0) {
    $ins_res = mysqli_query($conn, "SELECT * FROM installment_schedule WHERE pr_id = '$pr_id' ORDER BY installment_no ASC");
    while ($ins = mysqli_fetch_assoc($ins_res)) {
        $installments[] = $ins;
    }
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
                        <select name="priority" id="priority_select" onchange="restrictDueDate()"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            <option value="น้อย" <?= $pr_data['priority'] == 'น้อย' ? 'selected' : '' ?>>น้อย (Low)</option>
                            <option value="ปานกลาง" <?= $pr_data['priority'] == 'ปานกลาง' ? 'selected' : '' ?>>ปานกลาง (Medium)</option>
                            <option value="เร่งด่วน" <?= $pr_data['priority'] == 'เร่งด่วน' ? 'selected' : '' ?>>เร่งด่วน (Urgent) — 3 วัน</option>
                            <option value="เร่งสุดขีด" <?= $pr_data['priority'] == 'เร่งสุดขีด' ? 'selected' : '' ?>>เร่งสุดขีด (Critical) — 2 วัน</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">วันที่ต้องการสินค้า</label>
                        <input type="date" name="due_date" id="due_date_input" value="<?= $pr_data['due_date'] ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">อ้างอิงเอกสาร (Ref.)</label>
                        <input type="text" name="reference_no" value="<?= htmlspecialchars($pr_data['reference_no']) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">วิธีการชำระเงิน</label>
                        <select name="payment_method" id="payment_method" onchange="togglePaymentFields()"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            <option value="">-- เลือกวิธีชำระ --</option>
                            <?php
                            $pm_res = mysqli_query($conn, "SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC");
                            while ($pm = mysqli_fetch_assoc($pm_res)):
                            ?>
                            <option value="<?= $pm['id'] ?>" data-type="<?= $pm['type'] ?>"
                                <?= $pr_data['payment_method'] == $pm['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pm['name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div id="installment_field" class="<?= $pr_data['installment_period'] ? '' : 'hidden' ?>">
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">จำนวนงวด</label>
                        <select name="installment_period" onchange="generateInstallmentSchedule()"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            <option value="">-- เลือกจำนวนงวด --</option>
                            <?php foreach ([2,3,4,6,8,10,12] as $n): ?>
                            <option value="<?= $n ?>" <?= $pr_data['installment_period'] == $n ? 'selected' : '' ?>><?= $n ?> งวด</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ระยะเวลาชำระ</label>
                        <select name="payment_term" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            <option value="cash" <?= $pr_data['payment_term'] == 'cash' ? 'selected' : '' ?>>เงินสด / โอนจ่าย</option>
                            <option value="30" <?= $pr_data['payment_term'] == '30' ? 'selected' : '' ?>>เครดิต 30 วัน</option>
                            <option value="60" <?= $pr_data['payment_term'] == '60' ? 'selected' : '' ?>>เครดิต 60 วัน</option>
                            <option value="90" <?= $pr_data['payment_term'] == '90' ? 'selected' : '' ?>>เครดิต 90 วัน</option>
                        </select>
                    </div>
                    <div class="col-span-1">
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ผู้ต้องการ / แผนก</label>
                        <?php if (!empty($colleagues)): ?>
                            <select name="requested_by" onchange="updateContactTel(this)"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                                <?php foreach ($colleagues as $col): ?>
                                    <option value="<?= htmlspecialchars($col['name']) ?>" data-phone="<?= htmlspecialchars($col['phone'] ?? '') ?>"
                                        <?= ($pr_data['requested_by'] == $col['name']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($col['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="requested_by" value="<?= htmlspecialchars($pr_data['requested_by']) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                        <?php endif; ?>
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

                <!-- ===== กำหนดการผ่อนชำระ (แก้ไข) ===== -->
                <div id="installment_schedule_section" class="<?= empty($installments) ? 'hidden' : '' ?> bg-white rounded-3xl border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-100 bg-slate-50/50">
                        <span class="text-xs font-black text-slate-700 uppercase tracking-widest">
                            <i class="fas fa-calendar-alt text-indigo-500 mr-2"></i> กำหนดการผ่อนชำระ
                        </span>
                        <span class="text-[10px] text-slate-400 ml-2">(แก้ไขวันที่/ยอดได้)</span>
                    </div>
                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm" id="installmentTable">
                                <thead class="bg-slate-100 text-[10px] uppercase text-slate-600 font-black">
                                    <tr>
                                        <th class="px-4 py-2.5 text-center">งวดที่</th>
                                        <th class="px-4 py-2.5 text-center">วันครบกำหนด</th>
                                        <th class="px-4 py-2.5 text-right">ยอดชำระ</th>
                                        <th class="px-4 py-2.5 text-right">ชำระแล้ว</th>
                                        <th class="px-4 py-2.5 text-center">สถานะ</th>
                                        <th class="px-4 py-2.5 text-center">วันที่จ่าย</th>
                                        <th class="px-4 py-2.5 text-center">สลิป</th>
                                        <th class="px-4 py-2.5 text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="installmentBody" class="divide-y divide-slate-100">
                                    <?php foreach ($installments as $ins): 
                                        $ins_status_color = match($ins['status']) {
                                            'paid' => '#10b981',
                                            'partial' => '#f59e0b',
                                            'overdue' => '#ef4444',
                                            default => '#94a3b8'
                                        };
                                        $ins_status_label = match($ins['status']) {
                                            'paid' => 'ชำระแล้ว',
                                            'partial' => 'บางส่วน',
                                            'overdue' => 'เกินกำหนด',
                                            default => 'pending'
                                        };
                                    ?>
                                    <tr>
                                        <td class="px-4 py-2.5 text-center font-bold text-slate-700"><?= $ins['installment_no'] ?></td>
                                        <td class="px-4 py-2.5 text-center">
                                            <input type="date" name="installment_due_date[]" value="<?= $ins['due_date'] ?>"
                                                class="w-full bg-transparent border border-slate-200 rounded-lg px-2 py-1 text-center text-sm font-bold outline-none focus:border-indigo-500">
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <input type="number" name="installment_amount[]" value="<?= $ins['amount'] ?>" step="0.01"
                                                onchange="updateInstallmentTotal()"
                                                class="w-full bg-transparent border border-slate-200 rounded-lg px-2 py-1 text-right text-sm font-bold outline-none focus:border-indigo-500">
                                        </td>
                                        <td class="px-4 py-2.5 text-right" style="color: #10b981; font-weight: 600;"><?= number_format($ins['paid_amount'], 2) ?></td>
                                        <td class="px-4 py-2.5 text-center">
                                            <span style="display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; background: <?= $ins_status_color ?>15; color: <?= $ins_status_color ?>; border: 1px solid <?= $ins_status_color ?>30;">
                                                <?= $ins_status_label ?>
                                            </span>
                                            <input type="hidden" name="installment_status[]" value="<?= $ins['status'] ?>">
                                        </td>
                                        <td class="px-4 py-2.5 text-center" style="font-size: 11px; color: #64748b;">
                                            <?= !empty($ins['paid_at']) ? date('d/m/Y', strtotime($ins['paid_at'])) : '' ?>
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <?php if (!empty($ins['payment_slip'])): ?>
                                            <a href="uploads/payments/<?= htmlspecialchars($ins['payment_slip']) ?>" target="_blank" style="color: #2563eb; font-size: 18px;" title="ดูสลิป">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                            <?php else: ?>
                                            <span style="color: #cbd5e1;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <?php if ($ins['status'] === 'pending' || $ins['status'] === 'overdue'): ?>
                                            <button type="button" onclick='showPayModal(<?= json_encode(['id' => $ins['id'], 'no' => $ins['installment_no'], 'amount' => $ins['amount'], 'due_date' => $ins['due_date']]) ?>)'
                                                style="background: #6366f1; color: white; border: none; padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 700; cursor: pointer;">
                                                ชำระเงิน
                                            </button>
                                            <?php elseif ($ins['status'] === 'paid'): ?>
                                            <span style="color: #10b981; font-size: 11px; font-weight: 600;"><i class="fas fa-check-circle"></i> จ่ายแล้ว</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p id="installment_total_note" class="text-[11px] text-slate-400 mt-2 text-right <?= empty($installments) ? 'hidden' : '' ?>"></p>
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
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ร้านค้า (Store)</label>
                        <select name="store_id" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-emerald-500 transition-all">
                            <option value="">-- ไม่ระบุ --</option>
                            <?php foreach ($stores as $st): ?>
                                <option value="<?= $st['id'] ?>" <?= ($pr_data['store_id'] == $st['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($st['store_name']) ?>
                                </option>
                            <?php endforeach; ?>
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
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden mt-4 p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                <div class="relative">
                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1 ml-1">แนบสลิปชำระเงิน</label>
                    <div class="flex items-center gap-2" id="file_wrapper_slip">
                        <?php if (!empty($pr_data['payment_slip'])): ?>
                            <div id="existing_file_slip" class="flex items-center gap-2 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-100 w-full">
                                <a href="uploads/payments/<?= htmlspecialchars($pr_data['payment_slip']) ?>" target="_blank" class="text-emerald-700 text-[11px] font-bold truncate hover:underline flex-grow">
                                    <i class="fas fa-receipt mr-1"></i> <?= htmlspecialchars($pr_data['payment_slip']) ?>
                                </a>
                                <button type="button" onclick="removeExistingSlip()" class="text-red-500 hover:text-red-700 shrink-0">
                                    <i class="fas fa-times"></i>
                                </button>
                                <input type="hidden" name="delete_payment_slip" value="0" id="delete_payment_slip">
                            </div>
                            <input type="file" name="payment_slip" id="input_file_slip" class="hidden w-full px-2 py-1.5 bg-slate-50 border border-slate-100 rounded-xl text-[11px] font-bold outline-none file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[12px] file:font-bold file:bg-emerald-50 file:text-emerald-600" accept="image/*,.pdf">
                        <?php else: ?>
                            <input type="file" name="payment_slip" id="input_file_slip" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-100 rounded-xl text-[11px] font-bold outline-none file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[12px] file:font-bold file:bg-emerald-50 file:text-emerald-600" accept="image/*,.pdf">
                        <?php endif; ?>
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

        // --- เลือกประเภทวงเงินอัตโนมัติตามยอดรวมสุทธิ ---
        const radios = document.querySelectorAll('input[name="budget_limit_type"]');
        if (radios.length) {
            radios.forEach(r => r.checked = false);
            if (grandtotal <= 10000) {
                document.querySelector('input[name="budget_limit_type"][value="low"]').checked = true;
            } else if (grandtotal <= 99999) {
                document.querySelector('input[name="budget_limit_type"][value="mid"]').checked = true;
            } else {
                document.querySelector('input[name="budget_limit_type"][value="high"]').checked = true;
            }
        }
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

    // ฟังก์ชันอัปเดตเบอร์โทรอัตโนมัติเมื่อเลือกผู้ต้องการ
    function updateContactTel(select) {
        const selectedOption = select.options[select.selectedIndex];
        const phone = selectedOption ? selectedOption.getAttribute('data-phone') : '';
        const contactTel = document.querySelector('input[name="contact_tel"]');
        if (contactTel) {
            contactTel.value = phone || '';
        }
    }

    // === Unit Auto-Detect Functions ===
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    const detectUnit = debounce(async function(productName, unitInput) {
        if (!productName || productName.length < 3) return;
        // เช็คว่าชื่อสินค้าเปลี่ยนจากครั้งที่แล้วหรือยัง
        const lastProduct = unitInput.getAttribute('data-last-product');
        if (lastProduct === productName) return;
        unitInput.classList.add('bg-amber-50');
        try {
            const response = await fetch('api/detect_unit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product: productName })
            });
            const data = await response.json();
            if (data && data.unit) {
                unitInput.value = data.unit;
                unitInput.setAttribute('data-last-product', productName);
            }
        } catch (e) {
            console.error('Unit detection error:', e);
        } finally {
            unitInput.classList.remove('bg-amber-50');
        }
    }, 1000);

    function autoResize(textarea) {
        if (!textarea) return;
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }
    
    function togglePaymentFields() {
        const sel = document.getElementById('payment_method');
        const installField = document.getElementById('installment_field');
        const scheduleSection = document.getElementById('installment_schedule_section');
        if (!sel) return;
        const opt = sel.options[sel.selectedIndex];
        const type = opt ? opt.getAttribute('data-type') : '';
        if (type === 'installment') {
            installField.classList.remove('hidden');
            // ถ้ายังไม่มี schedule ให้ auto-generate
            const body = document.getElementById('installmentBody');
            if (body && body.children.length === 0) {
                generateInstallmentSchedule();
            } else if (scheduleSection) {
                scheduleSection.classList.remove('hidden');
            }
        } else {
            installField.classList.add('hidden');
            if (scheduleSection) scheduleSection.classList.add('hidden');
        }
    }

    function generateInstallmentSchedule() {
        const period = parseInt(document.querySelector('[name="installment_period"]').value) || 0;
        const grandTotalText = document.getElementById('grandtotal_display').innerText.replace(/,/g, '');
        const grandTotal = parseFloat(grandTotalText) || 0;
        const body = document.getElementById('installmentBody');
        const section = document.getElementById('installment_schedule_section');
        const note = document.getElementById('installment_total_note');

        if (period <= 0) {
            if (section) section.classList.add('hidden');
            return;
        }

        section.classList.remove('hidden');
        const perAmount = grandTotal > 0 ? Math.floor((grandTotal / period) * 100) / 100 : 0;
        let remaining = grandTotal > 0 ? grandTotal : 0;

        body.innerHTML = '';
        for (let i = 1; i <= period; i++) {
            const amount = grandTotal > 0 ? ((i === period) ? Math.round(remaining * 100) / 100 : perAmount) : 0;
            remaining -= amount;

            const dueDate = new Date();
            dueDate.setDate(dueDate.getDate() + 30 * i);

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-4 py-2.5 text-center font-bold text-slate-700">${i}</td>
                <td class="px-4 py-2.5 text-center">
                    <input type="date" name="installment_due_date[]" value="${dueDate.toISOString().split('T')[0]}"
                        class="w-full bg-transparent border border-slate-200 rounded-lg px-2 py-1 text-center text-sm font-bold outline-none focus:border-indigo-500">
                </td>
                <td class="px-4 py-2.5 text-right">
                    <input type="number" name="installment_amount[]" value="${amount.toFixed(2)}" step="0.01"
                        onchange="updateInstallmentTotal()"
                        class="w-full bg-transparent border border-slate-200 rounded-lg px-2 py-1 text-right text-sm font-bold outline-none focus:border-indigo-500">
                </td>
                <td class="px-4 py-2.5 text-center">
                    <span class="px-2 py-1 bg-amber-50 text-amber-600 text-[10px] font-bold rounded-full">pending</span>
                    <input type="hidden" name="installment_status[]" value="pending">
                </td>
            `;
            body.appendChild(tr);
        }

        note.classList.remove('hidden');
        note.innerText = 'ยอดรวม: ' + grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2}) + ' บาท';
    }

    function updateInstallmentTotal() {
        const inputs = document.querySelectorAll('[name="installment_amount[]"]');
        let total = 0;
        inputs.forEach(inp => { total += parseFloat(inp.value) || 0; });
        const note = document.getElementById('installment_total_note');
        if (note) {
            note.innerText = 'รวมทุกรวด: ' + total.toLocaleString(undefined, {minimumFractionDigits: 2}) + ' บาท';
        }
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

    function removeExistingSlip() {
        document.getElementById('existing_file_slip').style.display = 'none';
        const inputFile = document.getElementById('input_file_slip');
        if (inputFile) {
            inputFile.classList.remove('hidden');
        }
        document.getElementById('delete_payment_slip').value = '1';
    }

    // Event Delegation สำหรับ unit auto-detect
    document.addEventListener('DOMContentLoaded', () => {
        const tbody = document.querySelector('#itemsTable tbody');
        if (tbody) {
            tbody.addEventListener('input', function(e) {
                const textarea = e.target;
                if (textarea.matches('textarea[name="item_desc[]"]')) {
                    const row = textarea.closest('tr');
                    const unitInput = row.querySelector('input[name="item_unit[]"]');
                    if (unitInput) {
                        detectUnit(textarea.value, unitInput);
                    }
                }
            });
        }
    });

    function restrictDueDate() {
        const priority = document.getElementById('priority_select').value;
        const input = document.getElementById('due_date_input');
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const todayStr = `${yyyy}-${mm}-${dd}`;

        input.min = todayStr;
        input.removeAttribute('max');

        if (priority === 'เร่งด่วน') {
            const max = new Date(today);
            max.setDate(max.getDate() + 3);
            const y = max.getFullYear();
            const m = String(max.getMonth() + 1).padStart(2, '0');
            const d = String(max.getDate()).padStart(2, '0');
            input.max = `${y}-${m}-${d}`;
        } else if (priority === 'เร่งสุดขีด') {
            const max = new Date(today);
            max.setDate(max.getDate() + 2);
            const y = max.getFullYear();
            const m = String(max.getMonth() + 1).padStart(2, '0');
            const d = String(max.getDate()).padStart(2, '0');
            input.max = `${y}-${m}-${d}`;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        restrictDueDate();
        const supplierSelect = document.getElementById('supplier_select');
        if (supplierSelect && supplierSelect.value !== "0") { updateSupplierInfo(); }
        const requestedBy = document.querySelector('select[name="requested_by"]');
        if (requestedBy) {
            updateContactTel(requestedBy);
        }
        calculateTotal();
        document.querySelectorAll('textarea[name="item_desc[]"]').forEach(el => { autoResize(el); });

        const installPeriod = document.querySelector('[name="installment_period"]');
        if (installPeriod) {
            installPeriod.addEventListener('change', function() {
                const sel = document.getElementById('payment_method');
                const opt = sel ? sel.options[sel.selectedIndex] : null;
                if (opt && opt.getAttribute('data-type') === 'installment') {
                    generateInstallmentSchedule();
                }
            });
        }

        const origCalc = window.calculateTotal;
        if (typeof origCalc === 'function') {
            window._origCalculateTotal = origCalc;
            window.calculateTotal = function() {
                window._origCalculateTotal();
                const sel = document.getElementById('payment_method');
                const opt = sel ? sel.options[sel.selectedIndex] : null;
                if (opt && opt.getAttribute('data-type') === 'installment') {
                    const installPeriod = parseInt(document.querySelector('[name="installment_period"]')?.value || 0);
                    if (installPeriod > 0) generateInstallmentSchedule();
                }
            };
        }
    });
</script>

<!-- Pay Modal (no <form> to prevent accidental submission of the main form) -->
<div id="payModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <span class="text-sm font-black text-slate-700 uppercase tracking-wider">
                <i class="fas fa-credit-card text-indigo-500 mr-2"></i>ชำระเงิน <span id="payModalInstallmentNo"></span>
            </span>
            <button type="button" onclick="closePayModal()" class="text-slate-300 hover:text-slate-600 transition-colors text-xl leading-none">&times;</button>
        </div>
        <div class="p-5 space-y-4">
            <input type="hidden" id="pay_installment_id">
            <div class="bg-slate-50 rounded-2xl p-4 space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">ยอดชำระ</span><span id="payModalAmount" class="font-bold text-slate-800"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">ครบกำหนด</span><span id="payModalDueDate" class="font-bold text-slate-800"></span></div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">วันที่ชำระ</label>
                <input type="date" id="pay_date" required
                    class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">สลิปการชำระเงิน (ถ้ามี)</label>
                <input type="file" id="pay_slip" accept="image/*,.pdf"
                    class="w-full text-sm file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100">
            </div>
            <button type="button" onclick="submitPay()"
                class="w-full py-3 bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-black rounded-xl transition-colors"
                id="pay_submit_btn">
                ยืนยันการชำระเงิน
            </button>
        </div>
    </div>
</div>

<script>
    let currentInstallment = null;

    function showPayModal(ins) {
        currentInstallment = ins;
        document.getElementById('payModalInstallmentNo').textContent = 'งวดที่ ' + ins.no;
        document.getElementById('payModalAmount').textContent = parseFloat(ins.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('payModalDueDate').textContent = ins.due_date;
        document.getElementById('pay_installment_id').value = ins.id;
        document.getElementById('payModal').classList.remove('hidden');
    }

    function closePayModal() {
        document.getElementById('payModal').classList.add('hidden');
        currentInstallment = null;
    }

    async function submitPay() {
        const id = document.getElementById('pay_installment_id').value;
        const date = document.getElementById('pay_date').value;
        const slip = document.getElementById('pay_slip').files[0];

        if (!date) { alert('กรุณาเลือกวันที่ชำระ'); return; }

        const formData = new FormData();
        formData.append('id', id);
        formData.append('paid_at', date);
        if (slip) formData.append('payment_slip', slip);

        const btn = document.getElementById('pay_submit_btn');
        btn.disabled = true;
        btn.textContent = 'กำลังบันทึก...';
        try {
            const res = await fetch('api/pay_installment.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') {
                alert('บันทึกการชำระเงินเรียบร้อย');
                location.reload();
            } else {
                alert('เกิดข้อผิดพลาด: ' + (data.message || 'ไม่ทราบสาเหตุ'));
                btn.disabled = false;
                btn.textContent = 'ยืนยันการชำระเงิน';
            }
        } catch (err) {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            btn.disabled = false;
            btn.textContent = 'ยืนยันการชำระเงิน';
        }
    }
</script>

<?php include 'footer.php'; ?>
