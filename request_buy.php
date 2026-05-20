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

// ไม่ต้องมี if (!$customer) exit; แล้ว เพราะเราจำลองค่าไว้ให้แล้วด้านบนครับจาร
?>

<form action="api/save_pr_new.php" method="POST" enctype="multipart/form-data">
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
                <div class="bg-white p-6 rounded-3xl border border-slate-200 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label
                            class="text-[12px] font-black text-slate-800 uppercase block mb-1">วันที่ต้องการสินค้า</label>
                        <input type="date" name="due_date" value="<?= date('Y-m-d'); ?>"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none">
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">อ้างอิงเอกสาร
                            (Ref.)</label>
                        <input type="text" name="reference_no" placeholder="เช่น เลขที่ใบเสนอราคา"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">การชำระเงิน</label>
                        <select name="payment_term"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
                            <option value="cash">เงินสด / โอนจ่าย</option>
                            <option value="30">เครดิต 30 วัน</option>
                            <option value="60">เครดิต 60 วัน</option>
                        </select>
                    </div>

                    <div class="col-span-1">
                        <label class="text-[12px] font-black text-slate-800 uppercase block mb-1">ผู้ต้องการ /
                            แผนก</label>
                        <input type="text" name="requested_by" placeholder="ชื่อผู้ขอซื้อ / แผนก"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-xl text-sm font-bold outline-none focus:border-indigo-500">
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

                <div class="lg:col-span-3 space-y-6">
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
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
                                    <input type="number" name="budget_amount" step="0.01" placeholder="0.00"
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
                            <div
                                class="col-span-full grid grid-cols-1 md:grid-cols-2 gap-4 mt-2 pt-4 border-t border-slate-100">
                                <div class="relative">
                                    <label
                                        class="text-[12px] font-black text-slate-800 uppercase block mb-1 ml-1">ไฟล์แนบ
                                        1</label>
                                    <div class="flex items-center gap-2">
                                        <input type="file" name="attachment_1" id="file1"
                                            class="w-full px-2 py-1.5 bg-slate-50 border border-slate-100 rounded-xl text-[11px] font-bold outline-none focus:border-indigo-500 transition-all 
                file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[12px] file:font-bold file:bg-indigo-50 file:text-indigo-600">
                                        <button type="button" onclick="clearFile('file1')"
                                            class="p-2 hover:bg-red-50 text-slate-400 hover:text-red-500 transition-colors rounded-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="relative">
                                    <label
                                        class="text-[12px] font-black text-slate-800 uppercase block mb-1 ml-1">ไฟล์แนบ
                                        2</label>
                                    <div class="flex items-center gap-2">
                                        <input type="file" name="attachment_2" id="file2"
                                            class="w-full px-2 py-1.5 bg-slate-50 border border-slate-100 rounded-xl text-[11px] font-bold outline-none focus:border-indigo-500 transition-all 
                file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[12px] file:font-bold file:bg-indigo-50 file:text-indigo-600">
                                        <button type="button" onclick="clearFile('file2')"
                                            class="p-2 hover:bg-red-50 text-slate-400 hover:text-red-500 transition-colors rounded-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
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
                            <td class="px-2 py-4">
                                <textarea name="item_desc[]" placeholder="ระบุชื่อสินค้า / รหัสสินค้า..." rows="1"
                                    oninput="autoResize(this)"
                                    class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
                            </td>
                            <td class="px-2 py-4">
                                <input type="number" name="item_qty[]" value="1" min="0" step="0.01"
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
                            <td class="px-2 py-4">
                                <input type="number" name="item_discount[]" value="0.00" step="0.01"
                                    oninput="calculateTotal()"
                                    class="w-full bg-amber-50/50 border-none rounded-lg px-2 py-2 text-right text-sm font-mono font-black text-amber-600 focus:bg-amber-50">
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-mono font-black text-slate-700 row-total">
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
        <td class="px-2 py-4">
            <textarea name="item_desc[]" placeholder="ระบุชื่อสินค้า / รายละเอียด..."
                rows="1" oninput="autoResize(this)"
                class="w-full bg-transparent border-none focus:ring-0 outline-none text-sm text-slate-700 font-bold resize-none block overflow-hidden"></textarea>
        </td>
        <td class="px-2 py-4">
            <input type="number" name="item_qty[]" value="1" min="0" step="0.01"
                oninput="calculateTotal()"
                class="w-full bg-slate-50 border-none rounded-lg px-2 py-2 text-center text-sm font-black text-indigo-600 focus:bg-indigo-50 outline-none">
        </td>
        <td class="px-2 py-4">
            <input type="text" name="item_unit[]" placeholder="ชิ้น/หน่วย"
                class="w-full bg-transparent border-b border-slate-100 text-center text-xs font-bold outline-none focus:border-indigo-400">
        </td>
        <td class="px-2 py-4">
            <input type="number" name="item_price[]" value="0.00" step="0.01"
                oninput="calculateTotal()"
                class="w-full bg-transparent border-none text-right text-sm font-mono font-black focus:ring-0 outline-none">
        </td>
        <td class="px-2 py-4">
            <input type="number" name="item_discount[]" value="0.00" step="0.01"
                oninput="calculateTotal()"
                class="w-full bg-amber-50/50 border-none rounded-lg px-2 py-2 text-right text-sm font-mono font-black text-amber-600 focus:bg-amber-100 outline-none">
        </td>
        <td class="px-6 py-4 text-right text-sm font-mono font-black text-slate-700 row-total">0.00</td>
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

            // *** ส่วนที่เพิ่มใหม่: ดึงข้อมูลงบประมาณ/ผังบัญชี ตามบริษัทที่เลือก (data.id) ***
            fetchRelatedData(data.id);

        } catch (e) {
            console.error("Error parsing supplier data:", e);
        }
    }

    // ฟังก์ชันดึงข้อมูลจาก API ตาม id บริษัท
    function fetchRelatedData(sup_id) {
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
                    data.forEach(item => {
                        // ใช้ current_total_budget จาก Query ใหม่มาเก็บใน data-amount
                        const amountAttr = item.current_total_budget ? `data-amount="${item.current_total_budget}"` : 'data-amount="0"';
                        const spentAttr = item.total_spent ? `data-spent="${item.total_spent}"` : 'data-spent="0"';
                        html += `<option value="${item.id}" ${amountAttr} ${spentAttr}>${item.name}</option>`;
                    });
                    el.innerHTML = html;
                    
                    // ถ้าเป็น budget_type_id ให้เคลียร์ตัวแสดงผลยอดเงินด้วย
                    if (target.id === 'budget_type_id') {
                        document.getElementById('budget_amount_display').innerText = '';
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
        calculateTotal();

        // ปรับความสูง textarea ทุกตัวที่มีอยู่ตอนเริ่มต้น
        document.querySelectorAll('textarea[name="item_desc[]"]').forEach(el => {
            autoResize(el);
        });
    });

    function clearFile(id) {
        const fileInput = document.getElementById(id);
        fileInput.value = ''; // ล้างค่าใน Input
    }
</script>
<script src="assets/js/demo-data.js"></script>
<?php include 'footer.php'; ?>