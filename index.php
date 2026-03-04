<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');
?>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <button onclick="createDoc('quotation')"
        class="flex items-center justify-center gap-3 p-4 bg-indigo-600 text-white rounded-2xl shadow-sm hover:bg-indigo-700 hover:-translate-y-1 transition-all">
        <i class="fas fa-file-invoice text-xl"></i>
        <span class="font-bold">ใบเสนอราคา</span>
    </button>
    <button onclick="createDoc('pr')"
        class="flex items-center justify-center gap-3 p-4 bg-emerald-600 text-white rounded-2xl shadow-sm hover:bg-emerald-700 hover:-translate-y-1 transition-all">
        <i class="fas fa-file-contract text-xl"></i>
        <span class="font-bold">ใบขอซื้อ</span>
    </button>
    <button onclick="createDoc('po')"
        class="flex items-center justify-center gap-3 p-4 bg-blue-600 text-white rounded-2xl shadow-sm hover:bg-blue-700 hover:-translate-y-1 transition-all">
        <i class="fas fa-shopping-basket text-xl"></i>
        <span class="font-bold">ใบสั่งซื้อ</span>
    </button>
    <button onclick="createDoc('other')"
        class="flex items-center justify-center gap-3 p-4 bg-slate-700 text-white rounded-2xl shadow-sm hover:bg-slate-800 hover:-translate-y-1 transition-all">
        <i class="fas fa-folder-plus text-xl"></i>
        <span class="font-bold">ใบเบิกงวดงาน</span>
    </button>
</div>

<div class="flex flex-col xl:flex-row gap-6">
    <div class="w-full xl:w-[70%] order-2 xl:order-1">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-users text-indigo-500"></i> เลือกลูกค้าสำหรับทำรายการ
                </h2>
                <button id="btnDeleteSelected" onclick="deleteSelected()"
                    class="hidden px-4 py-2 bg-red-100 text-red-600 text-xs font-bold rounded-xl hover:bg-red-600 hover:text-white transition-all flex items-center gap-2">
                    <i class="fas fa-trash-alt"></i> ลบที่เลือก (<span id="selectedCount">0</span>)
                </button>
            </div>

            <div class="overflow-x-auto">
                <table id="customerTable" class="table table-hover w-full">
                    <thead class="bg-slate-50">
                        <tr class="text-slate-500 text-[11px] uppercase tracking-wider">
                            <th class="px-4 py-3 border-0 text-center w-10">
                                <input type="checkbox" id="selectAll" class="rounded border-slate-300">
                            </th>
                            <th class="px-4 py-3 border-0">ลูกค้า/บริษัท</th>
                            <th class="px-4 py-3 border-0">เลขภาษี</th>
                            <th class="px-4 py-3 border-0 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php
                        $query = mysqli_query($conn, "SELECT * FROM customers ORDER BY id DESC;");
                        while ($row = mysqli_fetch_assoc($query)):
                            $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors cursor-pointer customer-row"
                                data-id="<?= $row['id'] ?>">
                                <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                                    <input type="checkbox" name="customer_ids[]" value="<?= $row['id'] ?>"
                                        class="customer-checkbox rounded border-slate-300">
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-700 name-display">
                                        <?= htmlspecialchars($row['customer_name']) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-400 contact-display">
                                        <?= htmlspecialchars($row['contact_person'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-500 font-mono text-xs tax-display">
                                    <?= $row['tax_id'] ?: '-' ?>
                                </td>
                                <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                                    <div class="flex justify-center gap-1">
                                        <button onclick='editCustomer(<?= $json_data ?>)'
                                            class="w-8 h-8 flex items-center justify-center text-indigo-600 hover:bg-indigo-50 rounded-lg"><i
                                                class="fas fa-edit"></i></button>
                                        <button onclick="deleteCustomer(<?= $row['id'] ?>)"
                                            class="w-8 h-8 flex items-center justify-center text-red-500 hover:bg-red-50 rounded-lg"><i
                                                class="fas fa-trash-alt"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="w-full xl:w-[30%] order-1 xl:order-2">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden sticky top-6"
            id="customerFormSection">
            <div class="p-5 border-b border-slate-100 bg-slate-50/50">
                <h2 id="formTitle" class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-user-plus text-indigo-500"></i> เพิ่มลูกค้า
                </h2>
            </div>
            <form id="customerForm" class="p-5 space-y-4">
                <input type="hidden" name="customer_id" id="customer_id">
                <input type="hidden" name="action" id="formAction" value="add">

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1.5">ชื่อบริษัท/ลูกค้า <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="customer_name" id="customer_name" required
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">เลขภาษี</label>
                        <input type="text" name="tax_id" id="tax_id" maxlength="13"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">ผู้ติดต่อ</label>
                        <input type="text" name="contact_person" id="contact_person"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">เบอร์โทรศัพท์</label>
                        <input type="tel" name="phone" id="phone" placeholder="08x-xxx-xxxx"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">อีเมล</label>
                        <input type="email" name="email" id="email" placeholder="example@mail.com"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1.5">ที่อยู่</label>
                    <textarea name="address" id="address" rows="3"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm resize-none focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
                </div>

                <div class="pt-2 flex flex-col gap-2">
                    <button type="button" onclick="fillMockCustomer()"
                        class="w-full py-2 bg-amber-50 text-amber-600 border border-amber-200 text-xs font-bold rounded-xl hover:bg-amber-100 transition flex items-center justify-center gap-2 mb-2">
                        <i class="fas fa-magic"></i> สุ่มข้อมูลทดสอบ
                    </button>

                    <button type="submit"
                        class="w-full py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition shadow-md active:scale-[0.98]">
                        <i class="fas fa-save mr-2"></i> บันทึกข้อมูล
                    </button>

                    <button type="button" onclick="resetForm()" id="cancelBtn"
                        class="hidden w-full py-2.5 text-slate-500 font-bold hover:bg-slate-100 rounded-xl transition">
                        ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/mock-helper.js"></script>
<script src="assets/js/index.js"></script>
<style>
    /* ปรับแต่ง DataTables ให้เข้ากับ Tailwind */
    .dataTables_wrapper .dataTables_length select {
        padding-right: 2.5rem;
        border-radius: 0.5rem;
        border-color: #e2e8f0;
    }

    .dataTables_wrapper .dataTables_filter input {
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        padding: 0.5rem 1rem;
        outline: none;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        ring: 2px;
        ring-color: #4f46e5;
    }

    #customerTable_wrapper {
        padding: 0 !important;
    }

    .table-hover tbody tr:hover {
        background-color: #f8fafc !important;
    }

    /* ซ่อนวิทยุเดิมและเน้นการใช้งาน */
    input[type="radio"]:checked {
        background-color: #4f46e5;
        border-color: #4f46e5;
    }

    .dataTables_scrollBody {
        max-height: 500px !important;
        /* ปรับความสูงตามต้องการ */
        overflow-y: auto !important;
        scrollbar-width: thin;
        /* สำหรับ Firefox */
    }

    /* ปรับแต่ง Scrollbar สำหรับ Chrome, Edge, Safari */
    .dataTables_scrollBody::-webkit-scrollbar {
        width: 6px;
    }

    .dataTables_scrollBody::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    .dataTables_scrollBody::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    /* ล็อคหัวตารางให้ค้างไว้ด้านบน (Sticky Header) */
    #customerTable thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #f8fafc;
        /* พื้นหลังสีเทาอ่อนตามธีมเดิม */
        box-shadow: 0 1px 0 #e2e8f0;
        /* เส้นคั่นด้านล่าง */
    }
</style>

<?php include 'footer.php'; ?>