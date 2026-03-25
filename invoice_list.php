<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

// ใช้ Alias (AS) เพื่อให้ชื่อตัวแปรตอน fetch ออกมาเหมือนเดิมทุกประการ
$sql = "SELECT i.*, 
               i.invoice_no AS doc_no,          -- แปลงชื่อให้เป็น doc_no เหมือนเดิม
               i.sub_total AS subtotal,        -- แปลงชื่อให้เป็น subtotal เหมือนเดิม
               i.total_amount AS grand_total,  -- แปลงชื่อให้เป็น grand_total เหมือนเดิม
               c.customer_name, 
               s.company_name as supplier_name,
               u1.name as creator_name,
               u2.username as approver_name,
               (SELECT item_name FROM invoice_items 
                WHERE invoice_id = i.id 
                ORDER BY id ASC LIMIT 1) as first_item_desc
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN suppliers s ON i.supplier_id = s.id
        LEFT JOIN users u1 ON i.created_by = u1.id 
        LEFT JOIN users u2 ON i.approved_by = u2.id
        WHERE i.deleted_at IS NULL 
        ORDER BY i.updated_at DESC";

$result = mysqli_query($conn, $sql);

// ดึงรายชื่อ Supplier (คงเดิม)
$supplier_sql = "SELECT id, company_name FROM suppliers ORDER BY company_name ASC";
$supplier_res = mysqli_query($conn, $supplier_sql);
$suppliers = mysqli_fetch_all($supplier_res, MYSQLI_ASSOC);
?>
<!-- <style>
    /* บังคับให้ Body นิ่งสนิทแม้ SweetAlert จะทำงาน */
    body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) {
        overflow: unset !important;
        /* ป้องกันการซ่อน scrollbar แล้วหน้าดีด */
        padding-right: 0 !important;
    }

    /* ล็อค Sidebar ไม่ให้ขยับตามการดันของ Padding */
    aside,
    .sidebar,
    #sidebar-menu {
        left: 0 !important;
        transform: none !important;
    }

    /* ถ้าจารใช้ Tailwind ให้ล็อคความกว้างหน้าจอไว้ */
    .w-full {
        width: 100% !important;
    }
</style> -->
<div class="w-full ">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4">
            <div class="overflow-x-auto">
                <div class="flex flex-wrap items-center gap-3 mb-4 w-full">

                    <div class="relative min-w-[200px]">
                        <label
                            class="text-[10px] font-bold text-slate-800 uppercase mb-1 block ml-1">กรองตามหน่วยงาน/บริษัท</label>
                        <select id="filterSupplier"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                            <option value="">ทั้งหมด (Show All)</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= htmlspecialchars($s['company_name']) ?>">
                                    <?= htmlspecialchars($s['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="relative min-w-[150px]">
                        <label
                            class="text-[10px] font-bold text-slate-800 uppercase mb-1 block ml-1">ตั้งแต่วันที่</label>
                        <input type="date" id="minDate"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                    </div>

                    <div class="relative min-w-[150px]">
                        <label class="text-[10px] font-bold text-slate-800 uppercase mb-1 block ml-1">ถึงวันที่</label>
                        <input type="date" id="maxDate"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                    </div>

                    <button onclick="filterToday()"
                        class="self-end mb-[2px] border border-indigo-100 px-3 py-2 rounded-lg text-[10px] text-slate-700 hover:bg-indigo-50 transition-all flex items-center gap-1">
                        <i class="fas fa-calendar-day text-indigo-500"></i> รายการวันนี้
                    </button>

                    <div class="relative min-w-[120px]">
                        <label class="text-[10px] font-bold text-slate-800 uppercase mb-1 block ml-1">สถานะ</label>
                        <select id="filterStatus"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                            <option value="">ทั้งหมด</option>
                            <option value="รอ">รออนุมัติ (Pending)</option>
                            <option value="อนุมัติ">อนุมัติแล้ว (Approved)</option>
                            <option value="ชำระแล้ว">ชำระเงินแล้ว (Paid)</option>
                            <option value="ยกเลิก">ยกเลิก (Canceled)</option>
                        </select>
                    </div>

                    <button onclick="resetFilter()"
                        class="self-end mb-2.5 text-[10px] text-slate-800 hover:text-indigo-600 transition-colors">
                        <i class="fas fa-undo mr-1"></i> ล้างตัวกรอง
                    </button>

                    <div id="bulkActions"
                        class="hidden ml-auto self-end p-1.5 bg-slate-50 border border-slate-200 rounded-lg flex items-center gap-3 transition-all animate-fade-in shadow-sm">

                        <span class="text-[11px] font-bold text-slate-600 ml-2">
                            เลือกอยู่ <span id="selectedCount" class="text-indigo-600 underline">0</span> รายการ
                        </span>

                        <div class="flex items-center gap-2 border-l border-slate-200 pl-3">
                            <button onclick="bulkBilling()"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-md text-[10px] font-bold shadow-sm transition-all flex items-center gap-2">
                                <i class="fas fa-file-invoice-dollar"></i>
                                ทำใบวางบิล
                            </button>

                            <button onclick="bulkDelete()"
                                class="bg-white hover:bg-red-50 text-red-500 border border-red-100 px-3 py-1.5 rounded-md text-[10px] font-bold shadow-sm transition-all flex items-center gap-2">
                                <i class="fas fa-trash-alt"></i>
                                ลบรายการ
                            </button>
                        </div>
                    </div>

                </div>




                <table id="invTable" class="w-full display hover border-none">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="w-8 text-center !pr-2">
                                <input type="checkbox" id="selectAll"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            </th>
                            <th class="w-8 text-center">ID</th>
                            <th>เลขที่เอกสาร</th>
                            <th>หน่วยงาน</th>
                            <th>รายละเอียด</th>
                            <th>ลูกค้า</th>
                            <th class="text-right">ยอดรวม</th>
                            <th>สถานะ</th>
                            <th>วันที่</th>
                            <th>ผู้สร้าง</th>
                            <th>ผู้อนุมัติ</th>
                            <th class="text-center w-24">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-600">
                        <?php
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($result)):
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-50">
                                <td class="text-center">
                                    <input type="checkbox" name="invoice_ids[]" value="<?= $row['id'] ?>"
                                        data-customer="<?= $row['customer_id'] ?>"
                                        data-status="<?= strtolower($row['status']) ?>"
                                        class="invoice-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                </td>
                                <td class="text-center text-slate-300 font-mono text-[10px]"><?= $i++ ?></td>
                                <td class="font-bold text-slate-800 "><?= $row['doc_no'] ?></td>
                                <td>
                                    <div class="font-semibold text-slate-700 truncate max-w-[200px]">
                                        <?= htmlspecialchars($row['supplier_name'] ?: '-') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-700 truncate max-w-[250px]"
                                        title="<?= htmlspecialchars($row['first_item_desc'] ?? '') ?>">
                                        <?= htmlspecialchars($row['first_item_desc'] ?: 'ไม่มีรายละเอียดสินค้า') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-700 truncate max-w-[150px]">
                                        <?= htmlspecialchars($row['customer_name'] ?: '-') ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="flex flex-col items-end gap-1">
                                        <div class="flex items-center gap-1.5 opacity-80">
                                            <span
                                                class="text-[8px] font-bold text-slate-800 uppercase tracking-tighter">Subtotal</span>
                                            <i class="fas fa-calculator text-[9px] text-slate-300"></i>
                                            <span class="text-[10px] text-slate-500 font-mono font-medium">
                                                <?= number_format($row['subtotal'], 2) ?>
                                            </span>
                                        </div>

                                        <div
                                            class="flex items-center gap-1.5 bg-slate-50 px-2 py-1 rounded-md border border-slate-100 shadow-sm">
                                            <span
                                                class="text-[9px] font-black text-indigo-500 uppercase tracking-widest">Net</span>
                                            <i class="fas fa-coins text-[10px] text-amber-500"></i>
                                            <span class="text-[14px] font-mono font-black text-slate-900 leading-none">
                                                <?= number_format($row['grand_total'], 2) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    // กำหนดสีตามสถานะ (จารปรับชื่อสถานะให้ตรงกับใน DB นะครับ)
                                    $status = $row['status'] ?: 'pending';

                                    $config = [
                                        'pending' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100', 'dot' => 'bg-amber-400', 'label' => 'รอ'],
                                        'approved' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100', 'dot' => 'bg-emerald-400', 'label' => 'อนุมัติ'],
                                        'paid' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-100', 'dot' => 'bg-blue-400', 'label' => 'ชำระแล้ว'],
                                    ];

                                    $style = $config[$status] ?? $config['pending'];
                                    ?>

                                    <div
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border <?= $style['bg'] ?> <?= $style['border'] ?> <?= $style['text'] ?> shadow-sm">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $style['dot'] ?> animate-pulse"></span>

                                        <span class="text-[10px] font-bold uppercase tracking-wide">
                                            <?= $style['label'] ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4" data-order="<?= $row['created_at'] ?>">
                                    <div class="flex flex-col">
                                        <span class="text-[15px] font-bold text-slate-700 mt-0.5 flex items-center gap-1">
                                            <i class="fas fa-calendar-alt text-[9px]"></i>
                                            <?= date('d/m/y', strtotime($row['created_at'])) ?>
                                        </span>

                                        <?php if (!empty($row['updated_at'])): ?>
                                            <span class="text-[10px] text-slate-600 mt-0.5 flex items-center gap-1">
                                                <i class="fas fa-history text-[9px]"></i>
                                                <?= date('d/m/y', strtotime($row['updated_at'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] text-slate-300 mt-0.5">
                                                No updates yet
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="truncate max-w-[100px]"><?= htmlspecialchars($row['creator_name'] ?: '-') ?></td>
                                <td class="truncate max-w-[100px]"><?= htmlspecialchars($row['approver_name'] ?: '-') ?>
                                </td>
                                <td>
                                    <div class="flex justify-center gap-1">
                                        <?php if ($row['status'] === 'pending' && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'gmhok')): ?>
                                            <button onclick="approveInvoice(<?= $row['id'] ?>, '<?= $row['doc_no'] ?>')"
                                                class="w-7 h-7 flex items-center justify-center bg-white text-emerald-500 rounded-md hover:bg-emerald-50 hover:text-emerald-600 border border-emerald-100 shadow-sm transition-all"
                                                title="Approve Invoice">
                                                <i class="fas fa-check-circle text-[10px]"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($row['status'] === 'approved'): ?>
                                            <button onclick="payInvoice(<?= $row['id'] ?>, '<?= $row['doc_no'] ?>')"
                                                class="w-7 h-7 flex items-center justify-center bg-white text-blue-500 rounded-md hover:bg-blue-50 hover:text-blue-600 border border-blue-100 shadow-sm transition-all"
                                                title="Mark as Paid">
                                                <i class="fas fa-hand-holding-usd text-[10px]"></i>
                                            </button>
                                        <?php endif; ?>

                                        <a href="view_invoice.php?id=<?= $row['id'] ?>"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-slate-800 rounded-md hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 shadow-sm transition-all"
                                            title="View Invoice">
                                            <i class="fas fa-eye text-[10px]"></i>
                                        </a>

                                        <?php if ($row['status'] === 'pending'): ?>
                                            <a href="edit_invoice.php?id=<?= $row['id'] ?>"
                                                class="w-7 h-7 flex items-center justify-center bg-white text-slate-800 rounded-md hover:bg-amber-50 hover:text-amber-600 border border-slate-200 shadow-sm transition-all"
                                                title="Edit Invoice">
                                                <i class="fas fa-edit text-[10px]"></i>
                                            </a>
                                        <?php endif; ?>

                                        <div class="flex justify-center gap-1">
                                            <?php if ($row['status'] === 'paid'): ?>
                                                <a href="view_receipt.php?id=<?= $row['id'] ?>"
                                                    class="px-2 h-7 flex items-center justify-center gap-1.5 bg-white text-violet-500 rounded-md hover:bg-violet-50 hover:text-violet-600 border border-violet-100 shadow-sm transition-all"
                                                    title="ทำใบเสร็จ/กำกับภาษี" target="_blank">
                                                    <i class="fas fa-file-invoice-dollar text-[10px]"></i>
                                                    <span class="text-[10px] font-bold">ทำใบเสร็จ</span>
                                                </a>
                                            <?php endif; ?>

                                            <button onclick="deleteInvoice(<?= $row['id'] ?>)"
                                                class="w-7 h-7 flex items-center justify-center bg-white text-slate-800 rounded-md hover:bg-red-50 hover:text-red-600 border border-slate-200 shadow-sm transition-all"
                                                title="Delete Invoice">
                                                <i class="fas fa-trash text-[10px]"></i>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
    let invTable;

    // --- 1. ฟังก์ชันสนับสนุน (Helper Functions) อยู่นอกได้เลย ---
    function parseDate(dateStr) {
        if (!dateStr || dateStr.trim() === '-' || dateStr.trim() === '') return null;
        const cleanDate = dateStr.trim().split(/\s+/)[0];
        const parts = cleanDate.split('/');
        if (parts.length !== 3) return null;
        let year = parseInt(parts[2], 10);
        if (year < 100) year += 2000;
        return new Date(year, parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
    }

    $(document).ready(function () {
        // 1. Initialize DataTable ก่อน
        invTable = $('#invTable').DataTable({
            "pageLength": 10,
            "dom": '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json" },
            "order": [[8, "desc"]], // แก้ตรงนี้: ถ้าจะเรียงตามวันที่ ต้องเป็น Column 8 ครับ (เดิมจารใส่ 2)
            "columnDefs": [{ "orderable": false, "targets": [0, 11] }],
            "drawCallback": function () {
                updateBulkUI();
            }
        });

        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            const minStr = $('#minDate').val();
            const maxStr = $('#maxDate').val();

            if (!minStr && !maxStr) return true;

            const rowNode = settings.aoData[dataIndex].nTr;
            const dateRaw = $(rowNode).find('td:eq(8)').attr('data-order'); // ดึงจาก data-order ที่เราเพิ่มตะกี้

            let date;
            if (dateRaw) {
                date = new Date(dateRaw);
            } else {
                date = parseDate(data[8]);
            }

            if (!date || isNaN(date)) return false;

            // สร้าง Date Object สำหรับเปรียบเทียบ
            const min = minStr ? new Date(minStr) : null;
            const max = maxStr ? new Date(maxStr) : null;

            // Reset เวลาให้เป็น 00:00 เพื่อเทียบแค่วันที่ (ป้องกันเรื่องเวลาใน DB มาเกี่ยว)
            date.setHours(0, 0, 0, 0);
            if (min) min.setHours(0, 0, 0, 0);
            if (max) max.setHours(0, 0, 0, 0);

            if (min && date < min) return false;
            if (max && date > max) return false;

            return true;
        });

        // 3. Event Listener สำหรับวันที่
        $('#minDate, #maxDate').on('change', function () {
            invTable.draw(); // สั่งวาดตารางใหม่เพื่อรัน search function ข้างบน
        });

        // 4. กรอง Supplier (Column 3)
        $('#filterSupplier').on('change', function () {
            const val = $(this).val();
            invTable.column(3).search(val).draw();
        });

        $('#filterStatus').on('change', function () {
            const val = $(this).val();
            // ใช้ smart search เป็น false เพื่อให้ตรงตัวเป๊ะๆ หรือ search ธรรมดาก็ได้ครับ
            invTable.column(7).search(val).draw();
        });

        // 1. เมื่อติ๊กที่ Checkbox บนหัวตาราง (เลือกทั้งหมด)
        $('#selectAll').on('change', function () {
            const isChecked = $(this).is(':checked');
            // เลือกเฉพาะ checkbox ที่มองเห็นอยู่ในหน้าปัจจุบัน (filtered results)
            $('.invoice-checkbox').prop('checked', isChecked);
            updateBulkUI();
        });

        // 2. เมื่อติ๊กที่ Checkbox รายตัว
        $(document).on('change', '.invoice-checkbox', function () {
            updateBulkUI();

            // ถ้าติ๊กออกตัวเดียว ให้ Checkbox หัวตารางติ๊กออกด้วย
            if (!$(this).is(':checked')) {
                $('#selectAll').prop('checked', false);
            }

            // ถ้าติ๊กครบทุกตัว ให้ Checkbox หัวตารางติ๊กด้วย
            if ($('.invoice-checkbox:checked').length === $('.invoice-checkbox').length) {
                $('#selectAll').prop('checked', true);
            }
        });
    });

    function updateBulkUI() {
        const checkedCount = $('.invoice-checkbox:checked').length;
        const bulkDiv = $('#bulkActions');

        if (checkedCount > 0) {
            // โชว์แถบจัดการ และใส่เลขจำนวนที่เลือก
            bulkDiv.removeClass('hidden').addClass('flex');
            $('#selectedCount').text(checkedCount);
        } else {
            // ซ่อนถ้าไม่ได้เลือก
            bulkDiv.addClass('hidden').removeClass('flex');
        }
    }

    /**
     * ลบใบแจ้งหนี้ (Soft Delete)
     */
    /**
 * ลบใบแจ้งหนี้ (ตัวเดียว)
 */
    function deleteInvoice(id) {
        if (!confirm('ยืนยันการย้ายใบแจ้งหนี้นี้ไปยังถังขยะ?')) return;

        // แนะนำให้ใช้ POST เพื่อความปลอดภัยและตรงกับ Logic หลัก
        const fd = new FormData();
        fd.append('id', id);

        fetch('api/delete_invoice.php', {
            method: 'POST',
            body: fd
        })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'deleted') { // เช็คให้ตรงกับที่ PHP ส่งมา
                    const targetRow = $(`input.invoice-checkbox[value="${id}"]`).closest('tr');
                    invTable.row(targetRow).remove().draw(false);
                    renderAlert('delete', 'ย้ายใบแจ้งหนี้ไปยังถังขยะเรียบร้อยแล้ว');
                    updateBulkUI();
                } else {
                    renderAlert('error', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                renderAlert('error', 'การเชื่อมต่อล้มเหลว หรือไฟล์ API มีปัญหา');
            });
    }

    /**
     * ลบแบบกลุ่ม (Bulk Delete)
     */
    function bulkDelete() {
        const ids = [];
        const checkedItems = $('.invoice-checkbox:checked');
        checkedItems.each(function () { ids.push($(this).val()); });

        if (ids.length === 0) return;
        if (!confirm(`⚠️ ยืนยันย้ายใบแจ้งหนี้ที่เลือก ${ids.length} รายการไปยังถังขยะ?`)) return;

        const fd = new FormData();
        fd.append('action', 'bulk_delete');
        fd.append('ids', JSON.stringify(ids));

        fetch('api/delete_invoice.php', { // เปลี่ยน URL API
            method: 'POST',
            body: fd
        })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'deleted') {
                    checkedItems.each(function () {
                        invTable.row($(this).closest('tr')).remove();
                    });
                    invTable.draw(false);
                    renderAlert('delete', `ย้าย ${ids.length} ใบแจ้งหนี้ไปยังถังขยะแล้ว`);
                    updateBulkUI();
                    $('#selectAll').prop('checked', false);
                } else {
                    renderAlert('error', res.message);
                }
            });
    }

    function bulkBilling() {
        const ids = [];
        const customerIds = [];
        const statuses = [];
        const items = $('.invoice-checkbox:checked');

        if (items.length === 0) {
            alert('กรุณาเลือกใบแจ้งหนี้อย่างน้อย 1 รายการ');
            return;
        }

        items.each(function () {
            ids.push($(this).val());
            customerIds.push($(this).data('customer'));

            // ดึง status มาแล้วแปลงเป็นตัวเล็กทุกลำดับ
            let s = $(this).data('status') ? $(this).data('status').toString().toLowerCase() : '';
            statuses.push(s);
        });

        // --- 1. เช็คว่ามีใบที่จ่ายแล้ว (paid) หลุดมาไหม ---
        if (statuses.includes('paid')) {
            alert('⚠️ ไม่สามารถทำใบวางบิลได้: มีใบแจ้งหนี้ที่สถานะเป็น "ชำระเงินแล้ว (Paid)" รวมอยู่ด้วย');
            return;
        }

        // --- 2. เช็คว่ามีใบที่ถูกยกเลิก (canceled) ไหม (แถมให้จาร) ---
        if (statuses.includes('canceled')) {
            alert('⚠️ ไม่สามารถทำใบวางบิลได้: มีใบแจ้งหนี้ที่สถานะเป็น "ยกเลิก (Canceled)" รวมอยู่ด้วย');
            return;
        }

        // --- 3. เช็คว่าทุกลูกค้าเป็นคนเดียวกันไหม ---
        const firstCustomer = customerIds[0];
        const isSameCustomer = customerIds.every(id => id === firstCustomer);

        if (!isSameCustomer) {
            alert('⚠️ ไม่สามารถทำใบวางบิลได้: ใบแจ้งหนี้ที่เลือกมาจากลูกค้าคนละคนกัน');
            return;
        }

        // ผ่านทุกด่าน... วาร์ปได้!
        const selectedIds = ids.join(',');
        window.location.href = `view_billings.php?customer_id=${firstCustomer}&invoice_ids=${selectedIds}`;
    }

    // 1. ฟังก์ชันสำหรับ "อนุมัติ"
    function approveInvoice(id, doc_no) {
        Swal.fire({
            title: 'ยืนยันการอนุมัติ?',
            text: `เลขที่ใบขอซื้อ: ${doc_no}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'ยืนยันอนุมัติ',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true,
            heightAuto: false
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`api/update_invoice_status.php?id=${id}&action=approved`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            renderAlert('success', data.message);

                            // 1. Badge อนุมัติ (เขียว)
                            const approvedBadge = `
        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border bg-emerald-50 border-emerald-100 text-emerald-600 shadow-sm">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="text-[10px] font-bold uppercase tracking-wide">อนุมัติ</span>
        </div>`;

                            // 2. ปุ่มจ่ายเงิน (ดีไซน์เดิมของจารเป๊ะๆ)
                            const payButtonHtml = `
        <button onclick="payInvoice(${id}, '${doc_no}')" 
                class="w-7 h-7 flex items-center justify-center bg-white text-blue-500 rounded-md hover:bg-blue-50 hover:text-blue-600 border border-blue-100 shadow-sm transition-all"
                title="Mark as Paid">
            <i class="fas fa-hand-holding-usd text-[10px]"></i>
        </button>`;

                            // 3. หาแถวที่กด
                            let row = $(`button[onclick*="approveInvoice(${id}"]`).closest('tr');
                            if (row.length === 0) row = $(`button[onclick*="approveQuote(${id}"]`).closest('tr');
                            if (row.length === 0) row = $(`button[onclick*="approvePo(${id}"]`).closest('tr');

                            // 4. เปลี่ยนสถานะเป็น Badge เขียว
                            row.find('td:eq(7)').html(approvedBadge);

                            // 5. เปลี่ยน "เฉพาะปุ่มอนุมัติ" ให้เป็น "ปุ่มจ่ายเงิน"
                            // ปุ่มแก้ไขกับปุ่มลบจะยังอยู่เหมือนเดิม ไม่ขยับไปไหนครับ
                            row.find('button[onclick*="approve"]').replaceWith(payButtonHtml);
                        }
                    });
            }
        });
    }

    function payInvoice(id, doc_no) {
        Swal.fire({
            title: 'ยืนยันการชำระเงิน?',
            text: `เลขที่ใบแจ้งหนี้: ${doc_no}`,
            icon: 'info',
            html: `
            <div class="mt-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">แนบหลักฐานการชำระเงิน (ถ้ามี)</label>
                <input type="file" id="payment_file" class="swal2-input" accept="image/*,.pdf" style="font-size: 14px;">
            </div>
        `,
            showCancelButton: true,
            heightAuto: false,
            confirmButtonText: 'บันทึกการชำระเงิน',
            cancelButtonText: 'ยกเลิก',
            preConfirm: () => {
                const file = document.getElementById('payment_file').files[0];
                return { file: file };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('action', 'paid');
                if (result.value.file) {
                    formData.append('payment_proof', result.value.file);
                }

                // ใช้ POST แทน GET เพื่อส่งไฟล์
                fetch('api/update_invoice_status.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            renderAlert('success', 'บันทึกข้อมูลเรียบร้อยแล้ว');

                            // Logic การอัปเดตหน้าจอ (Badge)
                            const badgeHtml = `
                        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border bg-green-50 border-green-100 text-green-600 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                            <span class="text-[10px] font-bold uppercase tracking-wide">ชำระแล้ว</span>
                        </div>`;

                            let row = $(`button[onclick*="payInvoice(${id}"]`).closest('tr');
                            row.find('td:eq(7)').html(badgeHtml); // ปรับ index ให้ตรงกับตารางจาร
                            row.find(`button[onclick*="payInvoice(${id}"]`).remove();
                        } else {
                            renderAlert('error', data.message);
                        }
                    });
            }
        });
    }
    // ฟังก์ชัน Filter วันนี้ และ Reset
    function filterToday() {
        const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Bangkok' });
        $('#minDate').val(today);
        $('#maxDate').val(today);
        invTable.draw();
    }

    function resetFilter() {
        $('#filterSupplier, #filterStatus, #minDate, #maxDate').val('');
        invTable.column(3).search('');
        invTable.column(7).search('');
        invTable.draw();
    }

    function renderAlert(type, customMsg = '') {
        const configs = {
            'success': { msg: customMsg || "บันทึกข้อมูลเรียบร้อยแล้ว", color: "#10b981", icon: "bi-check-lg" },
            'delete': { msg: customMsg || "ย้ายรายการลงถังขยะแล้ว", color: "#f43f5e", icon: "bi-trash3" },
            'error': { msg: customMsg || "เกิดข้อผิดพลาด", color: "#64748b", icon: "bi-x-circle" }
        };

        const config = configs[type] || configs['error'];
        const existingAlerts = $('.custom-alert').length;
        const topPosition = 25 + (existingAlerts * 85);

        const alertHtml = `
            <div class="custom-alert shadow-lg bg-white p-3 rounded-3" 
                 style="color: ${config.color}; position: fixed; top: ${topPosition}px; right: 25px; z-index: 10000; width: 350px; display: flex; align-items: center; transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); border-left: 5px solid ${config.color};">
                <div class="alert-icon-wrap me-3" style="background-color: ${config.color}20; padding: 10px; border-radius: 12px; display: flex;">
                    <i class="bi ${config.icon} fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="text-dark fw-bold mb-0" style="font-size: 0.9rem;">${config.msg}</div>
                </div>
                <button type="button" class="btn-close ms-2" onclick="closeThisAlert(this)" style="font-size: 0.7rem;"></button>
            </div>`;

        const $alert = $(alertHtml).hide().appendTo('body').fadeIn(300);

        const autoClose = setTimeout(() => {
            closeThisAlert($alert.find('.btn-close'));
        }, 4000);

        $alert.data('timer', autoClose);
    }

    function closeThisAlert(btn) {
        const $target = $(btn).closest('.custom-alert');
        const heightToRemove = 85;
        const currentTop = parseInt($target.css('top'));

        clearTimeout($target.data('timer'));

        $('.custom-alert').each(function () {
            const otherTop = parseInt($(this).css('top'));
            if (otherTop > currentTop) {
                $(this).css('top', (otherTop - heightToRemove) + 'px');
            }
        });

        $target.fadeOut(300, function () { $(this).remove(); });
    }
</script>
<?php include 'footer.php'; ?>