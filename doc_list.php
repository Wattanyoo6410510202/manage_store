<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

$sql = "SELECT q.*, 
               c.customer_name, 
               s.company_name as supplier_name,
               u1.username as creator_name,  -- ดึงชื่อคนสร้าง
               u2.username as approver_name, -- ดึงชื่อคนอนุมัติ
               (SELECT item_desc FROM quotation_items 
                WHERE quotation_id = q.id 
                ORDER BY id ASC LIMIT 1) as first_item_desc
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN suppliers s ON q.supplier_id = s.id
        LEFT JOIN users u1 ON q.created_by = u1.id  -- Join หาคนสร้าง
        LEFT JOIN users u2 ON q.approved_by = u2.id -- Join หาคนอนุมัติ
        WHERE q.deleted_at IS NULL 
        ORDER BY q.updated_at DESC";

$result = mysqli_query($conn, $sql);

// ดึงรายชื่อ Supplier ทั้งหมดมาทำตัวกรอง
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
                            class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">กรองตามหน่วยงาน/บริษัท</label>
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
                            class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">ตั้งแต่วันที่</label>
                        <input type="date" id="minDate"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                    </div>

                    <div class="relative min-w-[150px]">
                        <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">ถึงวันที่</label>
                        <input type="date" id="maxDate"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                    </div>

                    <button onclick="filterToday()"
                        class="self-end mb-[2px] border border-indigo-100 px-3 py-2 rounded-lg text-[10px] text-slate-700 hover:bg-indigo-50 transition-all flex items-center gap-1">
                        <i class="fas fa-calendar-day text-indigo-500"></i> รายการวันนี้
                    </button>

                    <div class="relative min-w-[120px]">
                        <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">สถานะ</label>
                        <select id="filterStatus"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                            <option value="">ทั้งหมด</option>
                            <option value="รอ">รออนุมัติ (Pending)</option>
                            <option value="อนุมัติ">อนุมัติแล้ว (Approved)</option>
                        </select>
                    </div>

                    <button onclick="resetFilter()"
                        class="self-end mb-2.5 text-[10px] text-slate-400 hover:text-indigo-600 transition-colors">
                        <i class="fas fa-undo mr-1"></i> ล้างตัวกรอง
                    </button>

                    <div id="bulkActions"
                        class="hidden ml-auto self-end p-1.5 bg-red-50 border border-red-100 rounded-lg flex items-center gap-3 transition-all animate-fade-in">
                        <span class="text-[11px] font-bold text-red-700 ml-2">
                            เลือกอยู่ <span id="selectedCount" class="underline">0</span> รายการ
                        </span>
                        <button onclick="bulkDelete()"
                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-md text-[10px] font-bold shadow-sm transition-all flex items-center gap-2">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>

                </div>




                <table id="quoteTable" class="w-full display hover border-none">
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
                                    <input type="checkbox" name="quote_ids[]" value="<?= $row['id'] ?>"
                                        class="quote-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
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
                                                class="text-[8px] font-bold text-slate-400 uppercase tracking-tighter">Subtotal</span>
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
                                        <?php
                                        // ตรวจสอบทั้งสถานะ pending และสิทธิ์การใช้งาน (สมมติว่าตัวแปร session ชื่อ $_SESSION['role'])
                                        if ($row['status'] === 'pending' && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'gmhok')):
                                            ?>
                                            <button onclick="approveQuote(<?= $row['id'] ?>)"
                                                class="w-7 h-7 flex items-center justify-center bg-white text-emerald-500 rounded-md hover:bg-emerald-50 hover:text-emerald-600 border border-emerald-100 shadow-sm transition-all"
                                                title="Approve">
                                                <i class="fas fa-check-circle text-[10px]"></i>
                                            </button>
                                        <?php endif; ?>

                                        <a href="view_quotation.php?id=<?= $row['id'] ?>"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 shadow-sm transition-all"
                                            title="View">
                                            <i class="fas fa-eye text-[10px]"></i>
                                        </a>
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <a href="edit_quotation.php?id=<?= $row['id'] ?>"
                                                class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-amber-50 hover:text-amber-600 border border-slate-200 shadow-sm transition-all"
                                                title="Edit">
                                                <i class="fas fa-edit text-[10px]"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button onclick="deleteQuote(<?= $row['id'] ?>)"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-red-50 hover:text-red-600 border border-slate-200 shadow-sm transition-all"
                                            title="Delete">
                                            <i class="fas fa-trash text-[10px]"></i>
                                        </button>
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
    let quoteTable;

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
        quoteTable = $('#quoteTable').DataTable({
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
            quoteTable.draw(); // สั่งวาดตารางใหม่เพื่อรัน search function ข้างบน
        });

        // 4. กรอง Supplier (Column 3)
        $('#filterSupplier').on('change', function () {
            const val = $(this).val();
            quoteTable.column(3).search(val).draw();
        });

        $('#filterStatus').on('change', function () {
            const val = $(this).val();
            // ใช้ smart search เป็น false เพื่อให้ตรงตัวเป๊ะๆ หรือ search ธรรมดาก็ได้ครับ
            quoteTable.column(7).search(val).draw();
        });

        // 1. เมื่อติ๊กที่ Checkbox บนหัวตาราง (เลือกทั้งหมด)
        $('#selectAll').on('change', function () {
            const isChecked = $(this).is(':checked');
            // เลือกเฉพาะ checkbox ที่มองเห็นอยู่ในหน้าปัจจุบัน (filtered results)
            $('.quote-checkbox').prop('checked', isChecked);
            updateBulkUI();
        });

        // 2. เมื่อติ๊กที่ Checkbox รายตัว
        $(document).on('change', '.quote-checkbox', function () {
            updateBulkUI();

            // ถ้าติ๊กออกตัวเดียว ให้ Checkbox หัวตารางติ๊กออกด้วย
            if (!$(this).is(':checked')) {
                $('#selectAll').prop('checked', false);
            }

            // ถ้าติ๊กครบทุกตัว ให้ Checkbox หัวตารางติ๊กด้วย
            if ($('.quote-checkbox:checked').length === $('.quote-checkbox').length) {
                $('#selectAll').prop('checked', true);
            }
        });
    });

    /**
     * อัปเดต UI เมื่อมีการติ๊กเลือกรายการ
     */
    function updateBulkUI() {
        const checkedCount = $('.quote-checkbox:checked').length;
        const bulkBtn = $('#bulkActions');

        if (checkedCount > 0) {
            bulkBtn.removeClass('hidden').addClass('flex').fadeIn(200);
            $('#selectedCount').text(checkedCount);
        } else {
            bulkBtn.fadeOut(200, function () {
                $(this).addClass('hidden').removeClass('flex');
            });
            $('#selectAll').prop('checked', false);
        }
    }

    /**
     * ลบรายตัว (Soft Delete)
     */
    function deleteQuote(id) {
        if (!confirm('ยืนยันการย้ายใบเสนอราคานี้ไปยังถังขยะ?')) return;

        fetch(`api/delete_quotation.php?id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // หาแถวที่ต้องการลบใน DataTable
                    const targetRow = $(`input.quote-checkbox[value="${id}"]`).closest('tr');
                    quoteTable.row(targetRow).remove().draw(false);

                    renderAlert('delete', 'ย้ายรายการไปยังถังขยะเรียบร้อยแล้ว');
                    updateBulkUI();
                } else {
                    renderAlert('error', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                renderAlert('error', 'การเชื่อมต่อล้มเหลว');
            });
    }

    /**
     * ลบแบบกลุ่ม (Bulk Soft Delete)
     */
    function bulkDelete() {
        const ids = [];
        const checkedItems = $('.quote-checkbox:checked');

        checkedItems.each(function () {
            ids.push($(this).val());
        });

        if (ids.length === 0) return;
        if (!confirm(`⚠️ ยืนยันย้ายรายการที่เลือก ${ids.length} รายการไปยังถังขยะ?`)) return;

        const fd = new FormData();
        fd.append('action', 'bulk_delete');
        fd.append('ids', JSON.stringify(ids));

        fetch('api/delete_quotation.php', {
            method: 'POST',
            body: fd
        })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // ลบทุกแถวที่ถูกเลือกออกจาก DataTable UI
                    checkedItems.each(function () {
                        quoteTable.row($(this).closest('tr')).remove();
                    });

                    quoteTable.draw(false);
                    renderAlert('delete', `ย้าย ${ids.length} รายการไปยังถังขยะแล้ว`);
                    updateBulkUI();
                    $('#selectAll').prop('checked', false);
                } else {
                    renderAlert('error', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                renderAlert('error', 'ไม่สามารถดำเนินการลบแบบกลุ่มได้');
            });
    }

    /**
     * ระบบแจ้งเตือน Stackable Alert (คงเดิมตาม Logic เดิมของคุณที่เขียนไว้ดีแล้ว)
     */
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

    function approveQuote(id) {
        Swal.fire({
            title: 'ยืนยันการอนุมัติ?',
            text: "คุณกำลังจะอนุมัติใบเสนอราคาเลขที่ " + id,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ยืนยันอนุมัติ',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true,
            heightAuto: false
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`api/update_status.php?id=${id}&action=approved`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            renderAlert('success', 'อนุมัติใบเสนอราคาเรียบร้อยแล้ว');

                            // 1. เตรียม HTML ของ Badge (ดึงมาจาก $config ที่จารต้องการ)
                            const approvedBadge = `
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border bg-emerald-50 border-emerald-100 text-emerald-600 shadow-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span class="text-[10px] font-bold uppercase tracking-wide">อนุมัติ</span>
                            </div>`;

                            // 2. หาแถวที่กด
                            const targetRow = $(`button[onclick="approveQuote(${id})"]`).closest('tr');

                            // 3. อัปเดต Column สถานะ (ช่องที่ 8 คือ index 7) ด้วย HTML Badge
                            // .draw(false) เพื่อให้ตารางไม่อัปเดตหน้า (ค้างอยู่ที่หน้าเดิม)
                            quoteTable.cell(targetRow, 7).data(approvedBadge).draw(false);

                            // 4. ซ่อนปุ่มอนุมัติออก หรือล้างปุ่มในช่อง Action
                            // ใช้ .fadeOut() เพื่อความสวยงาม หรือ .html('') เพื่อล้างทิ้งทันที
                            targetRow.find('button[onclick*="approveQuote"]').fadeOut(400);

                        } else {
                            renderAlert('error', data.message || 'เกิดข้อผิดพลาด');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        renderAlert('error', 'การเชื่อมต่อล้มเหลว');
                    });
            }
        })
    }





    function filterToday() {
        // 1. ดึงวันที่ปัจจุบันของไทย (YYYY-MM-DD)
        const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Bangkok' });

        // 2. ใส่ค่าลงในช่อง Input
        $('#minDate').val(today);
        $('#maxDate').val(today);

        // 3. สั่งให้ตารางวาดใหม่ (เพื่อให้ Custom Search ทำงาน)
        if (typeof quoteTable !== 'undefined') {
            quoteTable.draw();
        } else {
            console.error("หาตัวแปร quoteTable ไม่เจอครับ");
        }
    }
    function resetFilter() {
        $('#filterSupplier').val('');
        $('#filterStatus').val(''); // ล้างค่าใน select status
        $('#minDate').val('');
        $('#maxDate').val('');

        // สั่งล้างการ search ทุกคอลัมน์แล้ววาดใหม่
        quoteTable.column(3).search('');
        quoteTable.column(7).search('');
        quoteTable.draw();
    }

</script>
<?php include 'footer.php'; ?>