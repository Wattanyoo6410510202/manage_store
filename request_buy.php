<?php
require_once 'config.php';
include 'header.php';

// 1. รับค่า customer_id (ถ้ามี)
$customer_id = $_GET['customer_id'] ?? null;

// 2. เตรียมตัวแปร $customer ไว้ล่วงหน้า
$customer = null;

if ($customer_id) {
    // --- กรณีที่ 1: มี ID ส่งมา (ผูกกับลูกค้า/โปรเจกต์) ---
    $clean_id = mysqli_real_escape_string($conn, $customer_id);
    $cust_query = mysqli_query($conn, "SELECT * FROM customers WHERE id = '$clean_id'");
    $customer = mysqli_fetch_assoc($cust_query);

    // ถ้าส่ง ID มาแต่หาไม่เจอใน DB ให้ดีดกลับ
    if (!$customer) {
        echo "<script>alert('ไม่พบข้อมูลลูกค้าในระบบ'); window.location.href='customers.php';</script>";
        exit;
    }
} else {
    // --- กรณีที่ 2: ไม่เลือกใครมา (ใช้ข้อมูลคน Login / ภายในองค์กร) ---
    // เรา "จำลอง" array ให้หน้าตาเหมือน table customers เพื่อให้ส่วนแสดงผลไม่พัง
    // --- กรณีที่ 2: ไม่เลือกใครมา (ใช้ข้อมูลคน Login / ภายในองค์กร) ---
    $customer = [
        'id' => 0,
        'customer_name' => $_SESSION['user_name'] ?? 'ผู้ใช้งานทั่วไป', // แก้ให้ตรงกับ login.php
        'name' => $_SESSION['user_name'] ?? 'ผู้ใช้งานทั่วไป', // ใส่เผื่อไว้ทั้ง 2 key กันพัง
        'tax_id' => '-',
        'address' => 'สั่งซื้อภายในองค์กร (สำนักงานใหญ่)',
        'phone' => '-', // ถ้าใน login.php ไม่ได้เก็บเบอร์โทรไว้ ให้ใส่ขีดไว้ก่อน
        'is_internal' => true
    ];
}

// 3. ดึงข้อมูลผู้ขาย (Suppliers) - เอาไว้เลือกตอนทำ PR ว่าจะซื้อจากเจ้าไหน
$suppliers_query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY id ASC");
$suppliers = [];
while ($s = mysqli_fetch_assoc($suppliers_query)) {
    $suppliers[] = $s;
}

// 4. ดึงข้อมูลเพื่อนร่วมงานในแผนก/บริษัทเดียวกัน (sup_id เดียวกัน)
$my_sup_id = $_SESSION['sup_id'] ?? 0;
$colleagues = [];
if ($my_sup_id > 0) {
    // ดึงทุกคนที่มี sup_id เดียวกัน
    $col_query = mysqli_query($conn, "SELECT id, name, phone FROM users WHERE sup_id = '$my_sup_id' ORDER BY name ASC");
    while ($col = mysqli_fetch_assoc($col_query)) {
        $colleagues[] = $col;
    }
} else {
    // ถ้าไม่มี sup_id (อาจเป็น admin หรือยังไม่ได้ตั้งค่า) ให้ดึงทุกคนมาให้เลือก หรืออย่างน้อยก็ตัวเอง
    $col_query = mysqli_query($conn, "SELECT id, name, phone FROM users ORDER BY name ASC");
    while ($col = mysqli_fetch_assoc($col_query)) {
        $colleagues[] = $col;
    }
}

// 5. ดึงข้อมูลร้านค้า (Stores)
$stores_query = mysqli_query($conn, "SELECT id, store_name FROM stores ORDER BY store_name ASC");
$stores = [];
while ($st = mysqli_fetch_assoc($stores_query)) {
    $stores[] = $st;
}
?>

<style>
    @media (max-width: 767px) {
        .overflow-x-auto { overflow: visible !important; }
        #itemsTable { width: 100%; }
        #itemsTable thead { display: none; }
        #itemsTable tbody { display: flex; flex-direction: column; gap: 16px; }
        #itemsTable tr.item-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 16px 12px;
            position: relative;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            gap: 8px;
            margin: 0;
        }
        #itemsTable td {
            display: flex;
            align-items: center;
            padding: 4px 0 !important;
            border: none !important;
            gap: 6px;
        }
        #itemsTable td::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 9px;
            color: #94a3b8;
            letter-spacing: 0.3px;
        }

        /* # - row badge top-right */
        #itemsTable td:nth-child(1) {
            position: absolute;
            top: -8px;
            right: 12px;
            padding: 0 !important;
            z-index: 1;
        }
        #itemsTable td:nth-child(1)::before { display: none; }
        #itemsTable td:nth-child(1) .row-number {
            background: #4f46e5;
            color: white;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 12px;
            border-radius: 20px;
            box-shadow: 0 2px 6px rgba(79,70,229,0.3);
        }

        /* delete top-left */
        #itemsTable td:last-child {
            position: absolute;
            top: 4px;
            left: 8px;
            padding: 0 !important;
        }
        #itemsTable td:last-child::before { display: none; }
        #itemsTable td:last-child button { font-size: 18px; color: #fca5a5; }

        /* description - full width */
        #itemsTable td:nth-child(2) {
            flex: 1 1 100%;
            order: -1;
            margin-bottom: 4px;
        }
        #itemsTable td:nth-child(2)::before { display: none; }
        #itemsTable td:nth-child(2) textarea {
            font-size: 14px !important;
            font-weight: 700;
            background: #f8fafc;
            border-radius: 10px !important;
            padding: 10px 12px !important;
            border: 1px solid #e2e8f0 !important;
            min-height: 44px;
        }

        /* qty (3) + unit (4) side by side */
        #itemsTable td:nth-child(3) { flex: 1; }
        #itemsTable td:nth-child(3) input {
            background: #f1f5f9 !important;
            border-radius: 10px !important;
            padding: 8px !important;
            font-size: 15px !important;
        }
        #itemsTable td:nth-child(4) { flex: 1; }
        #itemsTable td:nth-child(4) select {
            background: #f1f5f9 !important;
            border-radius: 10px !important;
            padding: 8px !important;
            font-size: 13px !important;
            border: none !important;
            text-align: center;
        }

        /* price (5) + discount (6) side by side */
        #itemsTable td:nth-child(5) { flex: 1; }
        #itemsTable td:nth-child(5) input {
            font-size: 14px !important;
            background: #f1f5f9 !important;
            border-radius: 10px !important;
            padding: 8px !important;
            text-align: center !important;
        }
        #itemsTable td:nth-child(6) { flex: 1; }
        #itemsTable td:nth-child(6) input {
            font-size: 14px !important;
            border-radius: 10px !important;
            padding: 8px !important;
            text-align: center !important;
        }

        /* total - full width, highlighted */
        #itemsTable td:nth-child(7) {
            flex: 1 1 100%;
            justify-content: space-between;
            background: #f8fafc;
            border-radius: 10px;
            padding: 8px 12px !important;
            margin-top: 4px;
            border: 1px solid #e2e8f0 !important;
        }
        #itemsTable td:nth-child(7)::before {
            font-size: 11px;
            color: #64748b;
        }
        #itemsTable td:nth-child(7) .row-total {
            font-size: 16px !important;
            color: #4f46e5;
        }
    }
</style>
<form action="api/save_pr_new.php" method="POST" enctype="multipart/form-data" onsubmit="return validateBudget()">
    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer_id) ?>">

    <div class="bg-slate-50 ">
        <div class="container-fluid p-0">

            <div class="lg:col-span-1 mb-6">
                <div class="bg-white p-1.5 px-2 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="flex items-center gap-2">

                        <div
                            class="shrink-0 bg-slate-50 p-1 rounded-lg border border-slate-100 flex items-center justify-center w-9 h-9">
                            <img id="comp_logo_preview" src="" class="h-7 w-7 object-contain hidden">
                            <i id="comp_logo_icon" class="fas fa-building text-lg text-slate-400"></i>
                        </div>

                        <div class="w-48 shrink-0">
                            <select name="supplier_id" id="supplier_select" onchange="updateSupplierInfo()"
                                class="w-full px-2 py-1.5 bg-slate-50 border border-slate-100 rounded-lg text-[11px] focus:border-indigo-500 outline-none font-bold text-slate-700 cursor-pointer">
                                <option value="0">เลือกผู้ขาย...</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <?php
                                    // เช็คว่า ID นี้ตรงกับ sup_id ใน session ไหม
                                    $is_selected = (isset($_SESSION['sup_id']) && $_SESSION['sup_id'] == $sup['id']) ? 'selected' : '';
                                    ?>
                                    <option value="<?= $sup['id'] ?>" data-info='<?= json_encode($sup, ENT_QUOTES) ?>'
                                        <?= $is_selected ?>>
                                        <?= htmlspecialchars($sup['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex-1 min-w-0 bg-slate-50/50 rounded-lg border-l-2 border-indigo-500 px-2 py-0.5">
                            <div class="flex items-center gap-2 overflow-hidden">
                                <span id="comp_name"
                                    class="text-[12px] font-black text-slate-800 truncate leading-tight uppercase">รอเลือกข้อมูล...</span>
                                <span class="text-[8px] font-mono text-slate-400 shrink-0">ID: <span id="comp_tax"
                                        class="font-bold text-slate-600">-</span></span>
                            </div>

                            <div class="flex items-center gap-2 text-[9px] text-slate-800 truncate leading-tight">
                                <span class="shrink-0"><i
                                        class="fas fa-phone-alt text-[7px] text-indigo-400 mr-0.5"></i><span
                                        id="comp_phone" class="font-bold">-</span></span>
                                <span class="text-slate-300">|</span>
                                <span class="truncate"><i
                                        class="fas fa-envelope text-[7px] text-indigo-400 mr-0.5"></i><span
                                        id="comp_email">-</span></span>
                                <span class="text-slate-300">|</span>
                                <span class="truncate italic opacity-70"><i
                                        class="fas fa-map-marker-alt text-[7px] text-indigo-400 mr-0.5"></i><span
                                        id="comp_addr">-</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white p-6 rounded-3xl border border-slate-200 grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div>
                        <label
                            class="text-[12px] font-black text-slate-800 uppercase block mb-1">ความสำคัญ</label>
                        <select name="priority" id="priority_select" onchange="restrictDueDate()"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            <option value="น้อย">น้อย (Low)</option>
                            <option value="ปานกลาง" selected>ปานกลาง (Medium)</option>
                            <option value="เร่งด่วน">เร่งด่วน (Urgent) — 3 วัน</option>
                            <option value="เร่งสุดขีด">เร่งสุดขีด (Critical) — 2 วัน</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="text-[12px] font-black text-slate-800 uppercase block mb-1">วันที่ต้องการสินค้า</label>
                        <input type="date" name="due_date" id="due_date_input" value="<?= date('Y-m-d'); ?>"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ร้านค้า
                            (Store)</label>
                        <select name="store_id"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-emerald-500 transition-all cursor-pointer">
                            <option value="">-- ไม่ระบุ --</option>
                            <?php foreach ($stores as $st): ?>
                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['store_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">อ้างอิงเอกสาร
                            (Ref.)</label>
                        <input type="text" name="reference_no" placeholder="เช่น เลขที่ใบเสนอราคา"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
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
                            <option value="<?= $pm['id'] ?>" data-type="<?= $pm['type'] ?>">
                                <?= htmlspecialchars($pm['name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div id="installment_field" class="hidden">
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">จำนวนงวด</label>
                        <select name="installment_period" onchange="generateInstallmentSchedule()"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            <option value="">-- เลือกจำนวนงวด --</option>
                            <option value="2">2 งวด</option>
                            <option value="3">3 งวด</option>
                            <option value="4">4 งวด</option>
                            <option value="6">6 งวด</option>
                            <option value="8">8 งวด</option>
                            <option value="10">10 งวด</option>
                            <option value="12">12 งวด</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ระยะเวลาชำระ</label>
                        <select name="payment_term"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            <option value="cash">เงินสด / โอนจ่าย</option>
                            <option value="30">เครดิต 30 วัน</option>
                            <option value="60">เครดิต 60 วัน</option>
                            <option value="90">เครดิต 90 วัน</option>
                        </select>
                    </div>

                    <div class="col-span-1">
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ผู้ต้องการ /
                            แผนก</label>
                        <?php
                        $current_user_name = $_SESSION['user_name'] ?? '';
                        $safe_user_name = htmlspecialchars($current_user_name);
                        ?>
                        <select name="requested_by" onchange="updateContactTel(this)"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            <?php if ($current_user_name): ?>
                                <option value="<?= $safe_user_name ?>" selected><?= $safe_user_name ?></option>
                            <?php endif; ?>
                            <?php foreach ($colleagues as $col): ?>
                                <?php if (trim($col['name']) !== trim($current_user_name)): ?>
                                    <option value="<?= htmlspecialchars($col['name']) ?>" data-phone="<?= htmlspecialchars($col['phone'] ?? '') ?>">
                                        <?= htmlspecialchars($col['name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-span-1">
                        <label
                            class="text-[12px] font-black text-slate-800 uppercase block mb-1">เบอร์โทรผู้ติดต่อ</label>
                        <input type="text" name="contact_tel" placeholder="เบอร์โทรภายใน/มือถือ"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ภาษี (VAT
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
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">หัก ณ ที่จ่าย
                        </label>
                        <select name="wht_percent" id="wht_percent" onchange="calculateTotal()"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                            <option value="0">0% </option>
                            <option value="1">1% </option>
                            <option value="3">3% </option>
                            <option value="5">5% </option>
                        </select>
                    </div>
                </div>

                <!-- ===== กำหนดการผ่อนชำระ ===== -->
                <div id="installment_schedule_section" class="hidden bg-white rounded-3xl border border-slate-200 overflow-hidden">
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
                                        <th class="px-4 py-2.5 text-center w-16">งวดที่</th>
                                        <th class="px-4 py-2.5 text-center">วันครบกำหนด</th>
                                        <th class="px-4 py-2.5 text-right">ยอดชำระ</th>
                                        <th class="px-4 py-2.5 text-center w-24">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody id="installmentBody" class="divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                        <p id="installment_total_note" class="text-[11px] text-slate-400 mt-2 text-right hidden"></p>
                    </div>
                </div>

                <div class="lg:col-span-3 space-y-6">
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                        <!-- Card Header with Toggle -->
                        <?php
                        $user_role = $_SESSION['role'] ?? '';
                        $is_staff = (strpos($user_role, 'staff') === 0 || in_array($user_role, ['maid_shotel', 'tech_shotel', 'cater_shotel', 'acc', 'hr', 'staff_hr']));
                        ?>
                        <div onclick="toggleExpenseCard()" class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 cursor-pointer hover:bg-slate-100/80 transition-all">
                            <span class="text-xs font-black text-slate-700 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-file-invoice-dollar text-indigo-500"></i> รายละเอียดงบประมาณ (Expense & Budget)
                            </span>
                            <i id="expense_toggle_icon" class="fas fa-chevron-up text-slate-400 transition-transform duration-300 <?= $is_staff ? 'rotate-180' : '' ?>"></i>
                        </div>

                        <div id="expense_card_content" class="p-6 transition-all duration-300 overflow-hidden <?= $is_staff ? 'hidden' : '' ?>">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="space-y-4">
                                    <div>
                                        <label
                                            class="text-[12px] font-black text-slate-800 uppercase block mb-1">ประเภทค่าใช้จ่าย
                                            (Expense Cat.)</label>
                                        <select name="expense_cat_id" id="expense_cat_id"
                                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500 transition-all">
                                            <option value="">-- รอเลือกผู้ขาย --</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="text-[12px] font-black text-slate-800 uppercase block mb-1">ประเภทงบประมาณ
                                            (Budget Type) <span id="budget_amount_display" class="text-indigo-600 font-bold ml-2"></span></label>
                                        <select name="budget_type_id" id="budget_type_id" onchange="showBudgetAmount()"
                                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500 transition-all">
                                            <option value="">-- รอเลือกผู้ขาย --</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="text-[12px] font-black text-slate-800 uppercase block mb-1">วัตถุประสงค์
                                            (Objective)</label>
                                        <select name="objective_id" id="objective_id"
                                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500 transition-all">
                                            <option value="">-- รอเลือกผู้ขาย --</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-2">ประเภทวงเงิน</label>
                                        <div class="grid grid-cols-1 gap-2">
                                            <label class="flex items-center p-2 bg-slate-50 border border-slate-100 rounded-xl cursor-pointer hover:bg-white hover:border-indigo-200 transition-all">
                                                <input type="radio" name="budget_limit_type" value="low" checked class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                                <span class="ml-2 text-[11px] font-bold text-slate-700">น้อยกว่า 10,000</span>
                                            </label>
                                            <label class="flex items-center p-2 bg-slate-50 border border-slate-100 rounded-xl cursor-pointer hover:bg-white hover:border-indigo-200 transition-all">
                                                <input type="radio" name="budget_limit_type" value="mid" class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                                <span class="ml-2 text-[11px] font-bold text-slate-700">10,001 - 99,999</span>
                                            </label>
                                            <label class="flex items-center p-2 bg-slate-50 border border-slate-100 rounded-xl cursor-pointer hover:bg-white hover:border-indigo-200 transition-all">
                                                <input type="radio" name="budget_limit_type" value="high" class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                                <span class="ml-2 text-[11px] font-bold text-slate-700">มากกว่า 100,000</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ยอดงบ
                                        </label>
                                        <input type="number" name="budget_amount" step="0.01" placeholder="0.00" oninput="calculateTotal()"
                                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none text-right ">
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label
                                            class="text-[12px] font-black text-slate-800 uppercase block mb-1">รายละเอียดงบประมาณ</label>
                                        <textarea name="budget_details" rows="3" placeholder="ระบุรายละเอียดที่มาของงบ..."
                                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-medium outline-none focus:border-indigo-500 min-h-[60px]"></textarea>
                                    </div>
                                    <div>
                                        <label
                                            class="text-[12px] font-black text-slate-800 uppercase block mb-1">ความคาดหวัง
                                        </label>
                                        <textarea name="expectation" rows="2" placeholder="ผลที่คาดว่าจะได้รับ..."
                                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-medium outline-none focus:border-indigo-500 min-h-[60px]"></textarea>
                                    </div>
                                    <div>
                                        <label
                                            class="text-[12px] font-black text-slate-800 uppercase block mb-1">วิธีปฏิบัติ
                                        </label>
                                        <textarea name="practice_method" rows="2" placeholder="ขั้นตอนการทำงาน..."
                                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-medium outline-none focus:border-indigo-500 min-h-[60px]"></textarea>
                                    </div>
                                </div>
                <!-- <div class="bg-slate-900 rounded-xl overflow-hidden border border-slate-800">
                        <div
                            class="bg-slate-800/50 px-4 py-2 border-b border-slate-700 flex justify-between items-center">
                            <h3
                                class="text-indigo-400 text-[12px] font-black flex items-center gap-2 uppercase tracking-wider">
                                <i class="fas fa-map-marker-alt"></i> Ship To
                            </h3>
                            <span
                                class="px-2 py-0.5 bg-amber-500/10 text-amber-400 text-[9px] font-bold rounded-md border border-amber-500/20 uppercase">
                                <?= isset($customer['is_internal']) ? 'INTERNAL' : 'PROJECT' ?>
                            </span>
                        </div>

                        <div class="p-3 space-y-2">
                            <div class="flex justify-between items-start">
                                <div class="text-white font-black text-sm truncate max-w-[70%]">
                                    <?= htmlspecialchars($customer['customer_name'] ?? $customer['name'] ?? 'ไม่ระบุชื่อ') ?>
                                </div>
                                <div class="text-indigo-300 text-[9px] font-mono font-bold pt-1">
                                    ID: <?= htmlspecialchars($customer['tax_id'] ?? '-') ?>
                                </div>
                            </div>

                            <div
                                class="text-white text-[11px] leading-snug line-clamp-2 italic bg-slate-800/30 p-2 rounded-lg border border-slate-700/50">
                                <?= htmlspecialchars($customer['address'] ?? 'ไม่มีข้อมูลที่อยู่') ?>
                            </div>
                        </div>
                    </div> -->
            </div>
        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden mt-4 p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="relative">
                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1 ml-1">ไฟล์แนบ 1</label>
                    <div class="flex items-center gap-2">
                        <input type="file" name="attachment_1" id="file1"
                            class="w-full px-2 py-1.5 bg-slate-50 border border-slate-100 rounded-xl text-[11px] font-bold outline-none focus:border-indigo-500 transition-all 
file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[12px] file:font-bold file:bg-indigo-50 file:text-indigo-600">
                        <button type="button" onclick="clearFile('file1')"
                            class="p-2 hover:bg-red-50 text-slate-400 hover:text-red-500 transition-colors rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="relative">
                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1 ml-1">ไฟล์แนบ 2</label>
                    <div class="flex items-center gap-2">
                        <input type="file" name="attachment_2" id="file2"
                            class="w-full px-2 py-1.5 bg-slate-50 border border-slate-100 rounded-xl text-[11px] font-bold outline-none focus:border-indigo-500 transition-all 
file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[12px] file:font-bold file:bg-indigo-50 file:text-indigo-600">
                        <button type="button" onclick="clearFile('file2')"
                            class="p-2 hover:bg-red-50 text-slate-400 hover:text-red-500 transition-colors rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="relative">
                    <label class="text-[12px] font-black text-slate-800 uppercase block mb-1 ml-1">แนบสลิปชำระเงิน</label>
                    <div class="flex items-center gap-2">
                        <input type="file" name="payment_slip" id="payment_slip"
                            accept="image/*,.pdf"
                            class="w-full px-2 py-1.5 bg-slate-50 border border-slate-100 rounded-xl text-[11px] font-bold outline-none focus:border-emerald-500 transition-all 
file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[12px] file:font-bold file:bg-emerald-50 file:text-emerald-600">
                        <button type="button" onclick="clearFile('payment_slip')"
                            class="p-2 hover:bg-red-50 text-slate-400 hover:text-red-500 transition-colors rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden mt-4">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/30">
                <span class="text-xs font-black text-slate-700 uppercase tracking-widest"><i
                        class="fas fa-shopping-cart mr-2 text-indigo-500"></i> รายการที่ขอซื้อ</span>
                <button type="button" onclick="addItemRow()"
                    class="px-4 py-2 bg-indigo-600 text-white text-[11px] font-black rounded-xl hover:bg-indigo-700 transition-all ">
                    <i class="fas fa-plus mr-1"></i> เพิ่มรายการสินค้า
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="itemsTable">
                    <thead
                        class="bg-slate-50/80 text-[12px] uppercase text-slate-800 font-black border-b border-slate-200">
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
                        <tr class="item-row group">
                            <td class="px-6 py-4 text-center text-xs font-bold text-slate-800 row-number">1
                            </td>
                            <td class="px-2 py-4" data-label="รายละเอียด">
                                <textarea name="item_desc[]" placeholder="ระบุชื่อสินค้า / รหัสสินค้า..." rows="1"
                                    oninput="autoResize(this)"
                                    class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
                            </td>
                            <td class="px-2 py-4" data-label="จำนวน">
                                <input type="number" name="item_qty[]" value="1" min="0" step="0.01"
                                    oninput="calculateTotal()"
                                    class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black text-indigo-600 focus:bg-indigo-50">
                            </td>
                            <td class="px-2 py-4" data-label="หน่วย">
                                <select name="item_unit[]"
                                    class="w-full bg-transparent border-b border-slate-100 text-center text-xs font-bold outline-none focus:border-indigo-400 cursor-pointer">
                                    <option value="">- หน่วย -</option>
                                    <option value="ชิ้น">ชิ้น</option>
                                    <option value="ตัว">ตัว</option>
                                    <option value="อัน">อัน</option>
                                    <option value="ชุด">ชุด</option>
                                    <option value="กล่อง">กล่อง</option>
                                    <option value="แพ็ค">แพ็ค</option>
                                    <option value="ลัง">ลัง</option>
                                    <option value="ถุง">ถุง</option>
                                    <option value="ห่อ">ห่อ</option>
                                    <option value="ม้วน">ม้วน</option>
                                    <option value="แผ่น">แผ่น</option>
                                    <option value="เส้น">เส้น</option>
                                    <option value="เครื่อง">เครื่อง</option>
                                    <option value="คู่">คู่</option>
                                    <option value="กิโลกรัม">กิโลกรัม</option>
                                    <option value="เมตร">เมตร</option>
                                    <option value="ลิตร">ลิตร</option>
                                    <option value="ตัน">ตัน</option>
                                </select>
                            </td>
                            <td class="px-2 py-4" data-label="ราคา/หน่วย">
                                <input type="number" name="item_price[]" value="0.00" step="0.01"
                                    oninput="calculateTotal()"
                                    class="w-full bg-transparent border-none text-right text-sm font-mono font-black focus:ring-0">
                            </td>
                            <td class="px-2 py-4" data-label="ส่วนลด">
                                <input type="number" name="item_discount[]" value="0.00" step="0.01"
                                    oninput="calculateTotal()"
                                    class="w-full bg-amber-50/50 border-none rounded-lg px-2 py-2 text-right text-sm font-mono font-black text-amber-600 focus:bg-amber-50">
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-mono font-black text-slate-700 row-total" data-label="รวมเงิน">
                                0.00</td>
                            <td class="px-4 py-4 text-center">
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
                    <label class="text-[12px] font-bold text-slate-800 uppercase block mb-2">หมายเหตุ</label>
                    <textarea name="notes" rows="3"
                        class="w-full p-3 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-300 transition-all resize-y"
                        placeholder="ระบุหมายเหตุเพิ่มเติม (ถ้ามี)..."></textarea>
                </div>
                <div class="w-full md:w-80 space-y-2">
                    <div class="flex justify-between text-sm font-bold text-slate-800">
                        <span>รวม</span>
                        <span id="subtotal_display">0.00</span>
                    </div>
                    <div class="flex justify-between text-sm font-bold text-slate-800">
                        <span>หัก ณ ที่จ่าย (<span id="wht_percent_label">0</span>%)</span>
                        <span>- <span id="wht_display">0.00</span></span>
                    </div>

                    <div class="flex justify-between text-sm font-bold text-slate-800">
                        <span>ภาษี (<span id="vat_percent_label">7</span>%)</span>
                        <span id="vat_display">0.00</span>
                    </div>



                    <div class="flex justify-between text-lg font-black text-indigo-600 pt-2 border-t border-slate-200">
                        <span>ยอดสุทธิ</span>
                        <span id="grandtotal_display">0.00</span>
                    </div>

                    <?php if (!is_viewer()): ?>
                    <button type="submit"
                        class="w-full mt-4 py-3 bg-indigo-600 text-white font-black rounded-xl hover:bg-indigo-700  transition-all flex items-center justify-center gap-2 active:scale-95">
                        <i class="fas fa-save"></i>บันทึกและออกเอกสาร
                    </button>
                    <?php else: ?>
                    <div class="w-full mt-4 py-3 bg-slate-200 text-slate-500 font-black rounded-xl flex items-center justify-center gap-2 cursor-not-allowed">
                        <i class="fas fa-eye"></i>ดูได้อย่างเดียว (ไม่มีสิทธิ์บันทึก)
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>


<script>
    // 1. ฟังก์ชันเพิ่มแถวรายการใหม่
    function addItemRow() {
        const tbody = document.querySelector('#itemsTable tbody');

        // สร้าง Element แถวใหม่ (tr)
        const newRow = document.createElement('tr');
        newRow.className = "item-row group border-t border-slate-100 hover:bg-slate-50/50 transition-colors";

        // ไส้ในของแถว (เพิ่มช่องส่วนลด และ หน่วยสินค้า)
        newRow.innerHTML = `
        <td class="px-6 py-4 text-center text-xs font-bold text-slate-800 row-number"></td>
        <td class="px-2 py-4" data-label="รายละเอียด">
            <textarea name="item_desc[]" placeholder="ระบุชื่อสินค้า / รายละเอียด..."
                rows="1" oninput="autoResize(this)"
                class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
        </td>
        <td class="px-2 py-4" data-label="จำนวน">
            <input type="number" name="item_qty[]" value="1" min="0" step="0.01"
                oninput="calculateTotal()"
                class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black text-indigo-600 focus:bg-indigo-50 outline-none">
        </td>
        <td class="px-2 py-4" data-label="หน่วย">
            <select name="item_unit[]"
                class="w-full bg-transparent border-b border-slate-100 text-center text-xs font-bold outline-none focus:border-indigo-400 cursor-pointer">
                <option value="">- หน่วย -</option>
                <option value="ชิ้น">ชิ้น</option>
                <option value="ตัว">ตัว</option>
                <option value="อัน">อัน</option>
                <option value="ชุด">ชุด</option>
                <option value="กล่อง">กล่อง</option>
                <option value="แพ็ค">แพ็ค</option>
                <option value="ลัง">ลัง</option>
                <option value="ถุง">ถุง</option>
                <option value="ห่อ">ห่อ</option>
                <option value="ม้วน">ม้วน</option>
                <option value="แผ่น">แผ่น</option>
                <option value="เส้น">เส้น</option>
                <option value="เครื่อง">เครื่อง</option>
                <option value="คู่">คู่</option>
                <option value="กิโลกรัม">กิโลกรัม</option>
                <option value="เมตร">เมตร</option>
                <option value="ลิตร">ลิตร</option>
                <option value="ตัน">ตัน</option>
            </select>
        </td>
        <td class="px-2 py-4" data-label="ราคา/หน่วย">
            <input type="number" name="item_price[]" value="0.00" step="0.01"
                oninput="calculateTotal()"
                class="w-full bg-transparent border-none text-right text-sm font-mono font-black focus:ring-0 outline-none">
        </td>
        <td class="px-2 py-4" data-label="ส่วนลด">
            <input type="number" name="item_discount[]" value="0.00" step="0.01"
                oninput="calculateTotal()"
                class="w-full bg-amber-50/50 border-none rounded-lg px-2 py-2 text-right text-sm font-mono font-black text-amber-600 focus:bg-amber-100 outline-none">
        </td>
        <td class="px-6 py-4 text-right text-sm font-mono font-black text-slate-700 row-total" data-label="รวมเงิน">0.00</td>
        <td class="px-4 py-4 text-center">
            <button type="button" onclick="removeRow(this)"
                class="text-slate-200 hover:text-red-500 transition-colors">
                <i class="fas fa-times-circle"></i>
            </button>
        </td>
    `;

        // เพิ่มแถวลงใน Table
        tbody.appendChild(newRow);

        // สั่งรันเลขแถวใหม่
        updateRowNumbers();

        // ปรับขนาด Textarea อัตโนมัติสำหรับแถวใหม่
        autoResize(newRow.querySelector('textarea'));
    }

    // 2. ฟังก์ชันลบแถว
    function removeRow(btn) {
        const tbody = document.querySelector('#itemsTable tbody');
        // ป้องกันไม่ให้ลบจนหมด (เหลือไว้อย่างน้อย 1 แถว)
        if (tbody.rows.length > 1) {
            btn.closest('tr').remove();
            updateRowNumbers(); // รันเลขใหม่
            calculateTotal();   // คำนวณเงินใหม่
        } else {
            alert("ต้องมีอย่างน้อย 1 รายการครับจาร!");
        }
    }

    // 3. ฟังก์ชันรันเลขลำดับแถวใหม่ (1, 2, 3...)
    function updateRowNumbers() {
        document.querySelectorAll('.row-number').forEach((td, index) => {
            td.innerText = index + 1;
        });
    }

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

        // --- เพิ่มส่วนตรวจสอบงบประมาณ ---
        const budgetSelect = document.getElementById('budget_type_id');
        const budgetInput = document.querySelector('input[name="budget_amount"]');
        const submitBtn = document.querySelector('button[type="submit"]');
        
        // อัปเดตยอดงบให้อัตโนมัติตามยอดรวมสุทธิ (ถ้ามียอดรวมสุทธิ)
        if (grandtotal > 0) {
            budgetInput.value = grandtotal.toFixed(2);
        }

        // ลำดับความสำคัญ: 1. ค่าที่กรอกในช่องงบ 2. ค่าจาก dropdown
        let budget = parseFloat(budgetInput.value) || 0;
        
        if (budget === 0 && budgetSelect.options[budgetSelect.selectedIndex] && budgetSelect.options[budgetSelect.selectedIndex].dataset.amount) {
            budget = parseFloat(budgetSelect.options[budgetSelect.selectedIndex].dataset.amount);
            const spent = parseFloat(budgetSelect.options[budgetSelect.selectedIndex].dataset.spent || 0);
            budget = budget - spent;
        }

        if (budget > 0) {
            if (grandtotal > budget) {
                document.getElementById('grandtotal_display').classList.add('text-red-600');
                document.getElementById('grandtotal_display').classList.remove('text-indigo-600');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('bg-red-600', 'hover:bg-red-700');
                    submitBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                    submitBtn.innerText = 'งบประมาณไม่เพียงพอ';
                }
            } else {
                document.getElementById('grandtotal_display').classList.remove('text-red-600');
                document.getElementById('grandtotal_display').classList.add('text-indigo-600');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
                    submitBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
                    submitBtn.innerText = 'บันทึกและออกเอกสาร';
                }
            }
        } else {
            // กรณีไม่ได้กำหนดงบเลย ให้แสดงปกติ
            document.getElementById('grandtotal_display').classList.remove('text-red-600');
            document.getElementById('grandtotal_display').classList.add('text-indigo-600');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
                submitBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
                submitBtn.innerText = 'บันทึกและออกเอกสาร';
            }
        }

        // --- เลือกประเภทวงเงินอัตโนมัติตามยอดรวมสุทธิ ---
        const radios = document.querySelectorAll('input[name="budget_limit_type"]');
        radios.forEach(r => r.checked = false);
        if (grandtotal <= 10000) {
            document.querySelector('input[name="budget_limit_type"][value="low"]').checked = true;
        } else if (grandtotal <= 99999) {
            document.querySelector('input[name="budget_limit_type"][value="mid"]').checked = true;
        } else {
            document.querySelector('input[name="budget_limit_type"][value="high"]').checked = true;
        }
    }
    function updateSupplierInfo() {
        const select = document.getElementById('supplier_select');
        if (!select) return;

        const selectedOption = select.options[select.selectedIndex];
        const infoAttr = selectedOption ? selectedOption.getAttribute('data-info') : null;

        const logoImg = document.getElementById('comp_logo_preview');
        const logoIcon = document.getElementById('comp_logo_icon');

        // --- 1. กรณีกดย้อนกลับ หรือไม่ได้เลือกบริษัท (ค่าว่าง หรือเลือก "ไม่ระบุ") ---
        if (!infoAttr || select.value === "" || select.value === "0") {
            document.getElementById('comp_name').innerText = '-';
            document.getElementById('comp_tax').innerText = '-';
            document.getElementById('comp_phone').innerText = '-';
            document.getElementById('comp_email').innerText = '-';
            document.getElementById('comp_addr').innerText = '-';

            if (logoImg && logoIcon) {
                logoImg.src = '';
                logoImg.classList.add('hidden');
                logoIcon.classList.remove('hidden');
            }

            // รีเซ็ตช่อง "ผู้ต้องการ / แผนก" กลับเป็นค่าเดิมของผู้ Login (ใช้ sup_id ของ session)
            resetRequestedByDropdown(<?= intval($_SESSION['sup_id'] ?? 0) ?>);

            // เพิ่มการล้างค่า Dropdown ทั้ง 3 ตัวที่เพิ่มใหม่
            resetRelatedDropdowns();
            return;
        }

        // --- 2. กรณีเลือกบริษัทที่มีข้อมูล ---
        try {
            const data = JSON.parse(infoAttr);

            // อัปเดตข้อมูลตัวหนังสือ
            document.getElementById('comp_name').innerText = data.company_name || '-';
            document.getElementById('comp_tax').innerText = data.tax_id || '-';
            document.getElementById('comp_phone').innerText = data.phone || '-';
            document.getElementById('comp_email').innerText = data.email || '-';
            document.getElementById('comp_addr').innerText = data.address || '-';

            // จัดการเรื่องรูปภาพและไอคอน
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

            // ดึง user ทั้งหมดตาม sup_id ของผู้ขายที่เลือก มาใส่ใน dropdown "ผู้ต้องการ / แผนก"
            fetchUsersBySup(data.id);

            // *** ส่วนที่เพิ่มใหม่: ดึงข้อมูลงบประมาณ/ผังบัญชี ตามบริษัทที่เลือก (data.id) ***
            fetchRelatedData(data.id);

        } catch (e) {
            console.error("Error parsing supplier data:", e);
        }
    }

    let current_sup_id = 0;

    // ข้อมูลร้านค้าทั้งหมดสำหรับใช้ใน JS
    const ALL_STORES = <?= json_encode($stores) ?>;

    // ฟังก์ชันดึง user ตาม sup_id แล้วใส่ใน dropdown "ผู้ต้องการ / แผนก"
    function fetchUsersBySup(supId) {
        const requestedByContainer = document.querySelector('.col-span-1')?.querySelector('label[for="requested_by"]')?.parentElement 
            || document.querySelectorAll('.col-span-1')[0];
        const selects = document.querySelectorAll('select[name="requested_by"], input[name="requested_by"]');
        const requestedByEl = selects[0];
        if (!requestedByEl) return;

        const parent = requestedByEl.closest('.col-span-1');
        if (!parent) return;

        const myName = <?= json_encode($_SESSION['user_name'] ?? '') ?>;

        if (requestedByEl.tagName === 'SELECT') {
            requestedByEl.innerHTML = '<option value="">กำลังโหลด...</option>';
        } else {
            requestedByEl.value = 'กำลังโหลด...';
        }

        fetch(`get_pr_support_data.php?action=get_users_by_sup&sup_id=${supId}`)
            .then(res => res.json())
            .then(users => {
                if (!users || users.length === 0) {
                    const newInput = document.createElement('input');
                    newInput.type = 'text';
                    newInput.name = 'requested_by';
                    newInput.value = myName;
                    newInput.placeholder = 'ชื่อผู้ขอซื้อ / แผนก';
                    newInput.className = 'w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500';
                    parent.replaceChild(newInput, requestedByEl);
                } else {
                    let select = requestedByEl;
                    if (requestedByEl.tagName !== 'SELECT') {
                        select = document.createElement('select');
                        select.name = 'requested_by';
                        select.onchange = function() { updateContactTel(this); };
                        select.className = 'w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500';
                        parent.replaceChild(select, requestedByEl);
                    }
                    let html = '';
                    let foundSelf = false;
                    users.forEach((user) => {
                        if (user.name.trim() === myName.trim()) foundSelf = true;
                    });
                    users.forEach((user) => {
                        const isSelected = (myName.trim() === user.name.trim()) ? 'selected' : ((!foundSelf && user === users[0]) ? 'selected' : '');
                        html += `<option value="${user.name}" data-phone="${user.phone || ''}" ${isSelected}>${user.name}</option>`;
                    });
                    if (!foundSelf && myName) {
                        html = `<option value="${myName}" data-phone="" selected>${myName}</option>` + html;
                    }
                    select.innerHTML = html;
                    updateContactTel(select);
                }
            })
            .catch(err => {
                console.error('Error fetching users by sup_id:', err);
            });
    }

    // ฟังก์ชันรีเซ็ต dropdown "ผู้ต้องการ / แผนก" กลับเป็น user ของ sup_id ที่ระบุ
    function resetRequestedByDropdown(supId) {
        if (supId > 0) {
            fetchUsersBySup(supId);
        } else {
            // ถ้าไม่มี sup_id ให้ดึง user ทั้งหมด
            fetch(`get_pr_support_data.php?action=get_users_by_sup&sup_id=0`)
                .then(res => res.json())
                .then(users => {
                    const selects = document.querySelectorAll('select[name="requested_by"], input[name="requested_by"]');
                    const el = selects[0];
                    if (!el) return;
                    const parent = el.closest('.col-span-1');
                    if (!parent) return;

                    if (users.length === 0) {
                        if (el.tagName !== 'INPUT') {
                            const newInput = document.createElement('input');
                            newInput.type = 'text';
                            newInput.name = 'requested_by';
                            newInput.value = '<?= addslashes($_SESSION['user_name'] ?? '') ?>';
                            newInput.className = 'w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500';
                            parent.replaceChild(newInput, el);
                        }
                    } else {
                        let select = el;
                        if (el.tagName !== 'SELECT') {
                            select = document.createElement('select');
                            select.name = 'requested_by';
                            select.onchange = function() { updateContactTel(this); };
                            select.className = 'w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500';
                            parent.replaceChild(select, el);
                        }
                        let html = '';
                        users.forEach((user, index) => {
                            const isSelected = ('<?= addslashes($_SESSION['user_name'] ?? '') ?>' === user.name) ? 'selected' : (index === 0 ? 'selected' : '');
                            html += `<option value="${user.name}" data-phone="${user.phone || ''}" ${isSelected}>${user.name}</option>`;
                        });
                        select.innerHTML = html;
                        updateContactTel(select);
                    }
                });
        }
    }

    // ฟังก์ชันดึงข้อมูลจาก API ตาม id บริษัท
    function fetchRelatedData(sup_id) {
        current_sup_id = sup_id;
        const targets = [
            { id: 'expense_cat_id', action: 'get_expense_cats' },
            { id: 'budget_type_id', action: 'get_budget_types' },
            { id: 'objective_id', action: 'get_objectives' }
        ];

        targets.forEach(target => {
            const el = document.getElementById(target.id);
            if (!el) return;

            el.innerHTML = '<option value="">กำลังโหลด...</option>';

            fetch(`get_pr_support_data.php?action=${target.action}&sup_id=${sup_id}`)
                .then(response => response.json())
                .then(data => {
                    let html = '<option value="">-- เลือกรายการ --</option>';
                    data.forEach((item, index) => {
                        // ใช้ current_total_budget จาก Query ใหม่มาเก็บใน data-amount
                        const amountAttr = item.current_total_budget ? `data-amount="${item.current_total_budget}"` : 'data-amount="0"';
                        const spentAttr = item.total_spent ? `data-spent="${item.total_spent}"` : 'data-spent="0"';
                        // เลือกตัวแรกให้อัตโนมัติ
                        const selected = (index === 0) ? 'selected' : '';
                        html += `<option value="${item.id}" ${amountAttr} ${spentAttr} ${selected}>${item.name}</option>`;
                    });
                    el.innerHTML = html;
                    
                    // ถ้ามีข้อมูลและเป็น expense_cat_id ให้อัปเดตร้านค้า
                    if (target.id === 'expense_cat_id') {
                        updateStoresByExpenseCat();
                    }

                    // ถ้ามีข้อมูลและเป็น budget_type_id ให้เรียก showBudgetAmount เพื่อแสดงยอดเงินทันที
                    if (target.id === 'budget_type_id') {
                        if (data.length > 0) {
                            showBudgetAmount();
                        } else {
                            document.getElementById('budget_amount_display').innerText = '';
                        }
                    }
                })
                .catch(err => {
                    console.error(`Error fetching ${target.action}:`, err);
                    el.innerHTML = '<option value="">โหลดข้อมูลไม่สำเร็จ</option>';
                });
        });
    }

    // ฟังก์ชันแสดงยอดเงินงบประมาณ
    function showBudgetAmount() {
        const select = document.getElementById('budget_type_id');
        const display = document.getElementById('budget_amount_display');
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption && selectedOption.dataset.amount) {
            const budget = parseFloat(selectedOption.dataset.amount);
            const spent = parseFloat(selectedOption.dataset.spent || 0);
            const remaining = budget - spent;
            
            display.innerText = '(คงเหลือ: ' + remaining.toLocaleString(undefined, {minimumFractionDigits: 2}) + ')';
        } else {
            display.innerText = '';
        }
    }

    // ฟังก์ชันรีเซ็ต Dropdown เมื่อไม่ได้เลือกบริษัท
    function resetRelatedDropdowns() {
        const ids = ['expense_cat_id', 'budget_type_id', 'objective_id'];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = '<option value="">-- รอเลือกผู้ขาย --</option>';
        });
        resetStoreDropdown();
    }

    // ฟังก์ชันรีเซ็ตร้านค้ากลับเป็นค่าเริ่มต้น
    function resetStoreDropdown() {
        const storeSelect = document.querySelector('select[name="store_id"]');
        if (!storeSelect) return;
        let html = '<option value="">-- ไม่ระบุ --</option>';
        ALL_STORES.forEach(st => {
            html += `<option value="${st.id}">${st.name}</option>`;
        });
        storeSelect.innerHTML = html;
    }

    // ฟังก์ชันอัปเดตร้านค้าตาม Expense Category ที่เลือก
    function updateStoresByExpenseCat() {
        const catSelect = document.getElementById('expense_cat_id');
        const storeSelect = document.querySelector('select[name="store_id"]');
        if (!catSelect || !storeSelect) return;

        const expense_cat_id = catSelect.value;
        if (!expense_cat_id) {
            storeSelect.innerHTML = '<option value="">-- ไม่ระบุ --</option>';
            return;
        }

        fetch(`get_pr_support_data.php?action=get_stores_by_expense_cat&expense_cat_id=${expense_cat_id}`)
            .then(response => response.json())
            .then(stores => {
                if (!stores || stores.length === 0) {
                    storeSelect.innerHTML = '<option value="">-- ไม่ระบุ --</option>';
                    return;
                }
                let html = '<option value="">-- ไม่ระบุ --</option>';
                stores.forEach((st, index) => {
                    const selected = index === 0 ? 'selected' : '';
                    html += `<option value="${st.id}" ${selected}>${st.store_name}</option>`;
                });
                storeSelect.innerHTML = html;
            })
            .catch(() => {
                storeSelect.innerHTML = '<option value="">-- ไม่ระบุ --</option>';
            });
    }

    // 5. ฟังก์ชันปรับความสูง Textarea
    function autoResize(textarea) {
        if (!textarea) return;
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }

    // สั่งรันเมื่อโหลดหน้าเสร็จ
    document.addEventListener('DOMContentLoaded', () => {
        // ตรวจสอบว่าใน Select มีการเลือกค่าไว้ก่อนหน้าไหม (จาก Session)
        const supplierSelect = document.getElementById('supplier_select');
        if (supplierSelect && supplierSelect.value !== "0") {
            // เรียกฟังก์ชันเดิมของจารเพื่อ update UI ทันที
            updateSupplierInfo();
        }
        
        // อัปเดตเบอร์โทรตามผู้ที่เลือกไว้ (กรณีมี selected ไว้ตอนโหลด)
        const requestedBy = document.querySelector('select[name="requested_by"]');
        if (requestedBy) {
            updateContactTel(requestedBy);
        }

        calculateTotal();

        // ปรับความสูง textarea ทุกตัวที่มีอยู่ตอนเริ่มต้น
        document.querySelectorAll('textarea[name="item_desc[]"]').forEach(el => {
            autoResize(el);
        });

        // Event: เมื่อเปลี่ยน Expense Category ให้อัปเดตร้านค้า
        const expenseCatSelect = document.getElementById('expense_cat_id');
        if (expenseCatSelect) {
            expenseCatSelect.addEventListener('change', updateStoresByExpenseCat);
        }
    });

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
    // Utility: Debounce รอพิมพ์เสร็จก่อนค่อยทำงาน
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Debounce: รอพิมพ์เสร็จ 1 วินาที ค่อยเรียก API
    const detectUnit = debounce(async function(productName, unitSelect) {
        if (!productName || productName.length < 3) return;
        
        // เช็คว่าชื่อสินค้าเปลี่ยนจากครั้งที่แล้วหรือยัง
        const lastProduct = unitSelect.getAttribute('data-last-product');
        if (lastProduct === productName) return;
        
        // แสดงว่าระบบกำลังทำงาน
        unitSelect.classList.add('bg-amber-50');
        
        try {
            const response = await fetch('api/detect_unit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product: productName })
            });
            const data = await response.json();
            if (data && data.unit) {
                // ค้นหาว่ามี option นี้ใน dropdown หรือไม่
                let found = false;
                for (let i = 0; i < unitSelect.options.length; i++) {
                    if (unitSelect.options[i].value === data.unit) {
                        unitSelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                // ถ้าไม่เจอ ให้เพิ่ม option ใหม่เข้าไป
                if (!found) {
                    const opt = new Option(data.unit, data.unit, true, true);
                    unitSelect.add(opt);
                }
                unitSelect.setAttribute('data-last-product', productName);
            }
        } catch (e) {
            console.error('Unit detection error:', e);
        } finally {
            unitSelect.classList.remove('bg-amber-50');
        }
    }, 1000);

    // ตั้งค่า Event Delegation สำหรับ unit auto-detect
    document.addEventListener('DOMContentLoaded', () => {
        const tbody = document.querySelector('#itemsTable tbody');
        if (tbody) {
            tbody.addEventListener('input', function(e) {
                const textarea = e.target;
                if (textarea.matches('textarea[name="item_desc[]"]')) {
                    const row = textarea.closest('tr');
                    const unitSelect = row.querySelector('select[name="item_unit[]"]');
                    if (unitSelect) {
                        detectUnit(textarea.value, unitSelect);
                    }
                }
            });
        }
    });

    function togglePaymentFields() {
        const sel = document.getElementById('payment_method');
        const installField = document.getElementById('installment_field');
        const scheduleSection = document.getElementById('installment_schedule_section');
        if (!sel) return;
        const opt = sel.options[sel.selectedIndex];
        const type = opt ? opt.getAttribute('data-type') : '';
        if (type === 'installment') {
            installField.classList.remove('hidden');
            generateInstallmentSchedule();
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
                    <span class="px-2 py-1 bg-amber-50 text-amber-600 text-[10px] font-bold rounded-full"> pending</span>
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

    function clearFile(id) {
        const fileInput = document.getElementById(id);
        fileInput.value = ''; // ล้างค่าใน Input
    }

    function validateBudget() {
        const budgetSelect = document.getElementById('budget_type_id');
        const budgetInput = document.querySelector('input[name="budget_amount"]');
        const grandtotal = parseFloat(document.getElementById('grandtotal_display').innerText.replace(/,/g, '')) || 0;
        
        let budget = parseFloat(budgetInput.value) || 0;
        if (budget === 0 && budgetSelect.options[budgetSelect.selectedIndex] && budgetSelect.options[budgetSelect.selectedIndex].dataset.amount) {
            budget = parseFloat(budgetSelect.options[budgetSelect.selectedIndex].dataset.amount);
            const spent = parseFloat(budgetSelect.options[budgetSelect.selectedIndex].dataset.spent || 0);
            budget = budget - spent;
        }

        if (budget > 0 && grandtotal > budget) {
            alert('ยอดรวมสุทธิเกินงบประมาณที่กำหนด! ไม่สามารถบันทึกได้');
            return false;
        }
        return true;
    }

    function toggleExpenseCard() {
        const content = document.getElementById('expense_card_content');
        const icon = document.getElementById('expense_toggle_icon');
        
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.classList.remove('rotate-180');
        } else {
            content.classList.add('hidden');
            icon.classList.add('rotate-180');
        }
    }

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
            if (input.value < todayStr || input.value > input.max) {
                input.value = input.max;
            }
        } else if (priority === 'เร่งสุดขีด') {
            const max = new Date(today);
            max.setDate(max.getDate() + 2);
            const y = max.getFullYear();
            const m = String(max.getMonth() + 1).padStart(2, '0');
            const d = String(max.getDate()).padStart(2, '0');
            input.max = `${y}-${m}-${d}`;
            if (input.value < todayStr || input.value > input.max) {
                input.value = input.max;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        restrictDueDate();
        const reqSelect = document.querySelector('select[name="requested_by"]');
        if (reqSelect) updateContactTel(reqSelect);

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
<script src="assets/js/demo-data.js"></script>
<?php include 'footer.php'; ?>