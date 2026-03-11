<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

// ปรับ Query ให้ฉลาดขึ้น: 
// 1. ถ้า is_internal = 1 ให้ไปเอาชื่อจากตาราง users (u.name)
// 2. ถ้า is_internal = 0 ให้ไปเอาชื่อจากตาราง customers (c.customer_name)
$sql = "SELECT 
            p.*, 
            s.company_name as supplier_name,
            -- (ของเดิม) ดึงชื่อคนขอ/ลูกค้า --
            IF(p.is_internal = 1, u1.name, c.customer_name) AS display_requester,
            
            -- (เพิ่มใหม่) ดึงชื่อคนสร้างเอกสารจาก created_by ตรงๆ --
            u_creator.name AS creator_real_name, 

            u2.username as approver_name,
            (SELECT item_desc FROM pr_items 
             WHERE pr_id = p.id 
             ORDER BY id ASC LIMIT 1) as first_item_desc
        FROM pr p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN customers c ON p.customer_id = c.id AND p.is_internal = 0
        -- u1 (ของเดิม) สำหรับ logic display_requester
        LEFT JOIN users u1 ON p.created_by = u1.id AND p.is_internal = 1
        
        -- u_creator (เพิ่มใหม่) สำหรับดึงชื่อคนสร้าง ไม่ว่าจะเป็นเคสไหนก็ตาม --
        LEFT JOIN users u_creator ON p.created_by = u_creator.id
        
        -- u2 (ของเดิม) คนอนุมัติ
        LEFT JOIN users u2 ON p.approved_by = u2.id
        WHERE p.deleted_at IS NULL 
        ORDER BY p.created_at DESC";

$result = mysqli_query($conn, $sql);

// ดึงรายชื่อ Supplier ทั้งหมดมาทำตัวกรอง
$supplier_sql = "SELECT id, company_name FROM suppliers ORDER BY company_name ASC";
$supplier_res = mysqli_query($conn, $supplier_sql);
$suppliers = mysqli_fetch_all($supplier_res, MYSQLI_ASSOC);
?>

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
                        <button onclick="bulkDeletePR()"
                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-md text-[10px] font-bold shadow-sm transition-all flex items-center gap-2">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>

                </div>

                <table id="prTable" class="w-full display hover border-none">
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
                                    <input type="checkbox" name="pr_ids[]" value="<?= $row['id'] ?>"
                                        class="pr-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                </td>
                                <td class="text-center text-slate-300 font-mono text-[10px]"><?= $i++ ?></td>
                                <td class="font-bold text-slate-800 "><?= $row['doc_no'] ?></td>
                                <td>
                                    <div class="font-semibold text-slate-700 truncate max-w-[200px]">
                                        <?= htmlspecialchars($row['supplier_name'] ?: '-') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-700 truncate max-w-[220px]"
                                        title="<?= htmlspecialchars($row['first_item_desc'] ?? '') ?>">
                                        <?= htmlspecialchars($row['first_item_desc'] ?: 'ไม่มีรายละเอียดสินค้า') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-700 truncate max-w-[150px]">
                                        <?= htmlspecialchars($row['display_requester'] ?: '-') ?>
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
                                <td class="truncate max-w-[150px]"><?= htmlspecialchars($row['creator_real_name'] ?: '-') ?>
                                </td>
                                <td class="truncate max-w-[150px]"><?= htmlspecialchars($row['approver_name'] ?: '-') ?>
                                </td>
                                <td class="px-4">
                                    <div class="flex justify-center gap-1.5">
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <button onclick="approvePR(<?= $row['id'] ?>, '<?= $row['doc_no'] ?>')"
                                                title="อนุมัติ PR"
                                                class="w-8 h-8 flex items-center justify-center bg-white text-emerald-500 rounded-lg hover:bg-emerald-50 hover:text-emerald-600 border border-emerald-100 shadow-sm transition-all active:scale-95">
                                                <i class="fas fa-check-circle text-xs"></i>
                                            </button>
                                        <?php endif; ?>

                                        <a href="view_pr.php?id=<?= $row['id'] ?>" title="ดูรายละเอียด"
                                            class="w-8 h-8 flex items-center justify-center bg-white text-slate-400 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 shadow-sm transition-all active:scale-90">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>

                                        <?php if ($row['status'] === 'pending'): ?>
                                            <a href="edit_pr.php?id=<?= $row['id'] ?>" title="แก้ไข"
                                                class="w-8 h-8 flex items-center justify-center bg-white text-slate-400 rounded-lg hover:bg-amber-50 hover:text-amber-600 border border-slate-200 shadow-sm transition-all active:scale-90">
                                                <i class="fas fa-edit text-xs"></i>
                                            </a>
                                        <?php endif; ?>

                                        <button onclick="deletePR(<?= $row['id'] ?>)" title="ลบ"
                                            class="w-8 h-8 flex items-center justify-center bg-white text-slate-400 rounded-lg hover:bg-red-50 hover:text-red-600 border border-slate-200 shadow-sm transition-all active:scale-90">
                                            <i class="fas fa-trash text-xs"></i>
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
    let prTable;
    $(document).ready(function () {
        // 1. Init DataTable
        prTable = $('#prTable').DataTable({
            "pageLength": 10,
            "dom": '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json" },
            "order": [[8, "desc"]], // เรียงตามวันที่ล่าสุด
            "columnDefs": [{ "orderable": false, "targets": [0, 9] }],
            "drawCallback": function () { updateBulkUI(); }
        });

        // 2. Custom Filters
        $('#filterSupplier').on('change', function () { prTable.column(3).search(this.value).draw(); });
        $('#filterStatus').on('change', function () { prTable.column(7).search(this.value).draw(); });

        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            let min = $('#minDate').val(); // ค่าจะเป็น YYYY-MM-DD
            let max = $('#maxDate').val(); // ค่าจะเป็น YYYY-MM-DD

            // ดึงค่าจากคอลัมน์ที่ 8 (วันที่)
            let dateStr = data[8] || "";
            if (dateStr === "") return true;

            // ตัดเอาเฉพาะวันที่ (เผื่อมี icon หรือช่องว่าง) และแยกส่วน d/m/y
            // หมายเหตุ: ต้องระวังเรื่องเลขปี 2 หลัก (YY) กับ 4 หลัก (YYYY)
            let match = dateStr.match(/(\d{2})\/(\d{2})\/(\d{2})/);
            if (!match) return true;

            let day = match[1];
            let month = match[2];
            let year = "20" + match[3]; // เติม 20 ข้างหน้าเพื่อให้เป็น ค.ศ. 4 หลัก (2024)

            let dateFormatted = `${year}-${month}-${day}`; // กลายเป็น YYYY-MM-DD

            if ((min === "" && max === "") ||
                (min === "" && dateFormatted <= max) ||
                (min <= dateFormatted && max === "") ||
                (min <= dateFormatted && dateFormatted <= max)) {
                return true;
            }
            return false;
        });

        $('#minDate, #maxDate').on('change', () => prTable.draw());

        // 3. Selection
        $('#selectAll').on('change', function () {
            $('.pr-checkbox').prop('checked', this.checked);
            updateBulkUI();
        });

        $(document).on('change', '.pr-checkbox', updateBulkUI);
    });

    /**
     * ควบคุมการแสดงผลปุ่มลบแบบกลุ่ม (Bulk Actions)
     */
    function updateBulkUI() {
        const checkedCount = $('.pr-checkbox:checked').length;
        const bulkElement = $('#bulkActions');

        if (checkedCount > 0) {
            bulkElement.removeClass('hidden').addClass('flex').fadeIn(200);
            $('#selectedCount').text(checkedCount);
        } else {
            bulkElement.fadeOut(200, function () {
                $(this).addClass('hidden').removeClass('flex');
            });
            $('#selectAll').prop('checked', false);
        }
    }

    /**
     * ระบบแจ้งเตือน Stackable Alert
     */
    function renderAlert(type, customMsg = '') {
        const configs = {
            'success': {
                msg: customMsg || "ทำรายการสำเร็จ",
                color: "#10b981",
                icon: "bi-check-lg"
            },
            'delete': {
                msg: customMsg || "ย้ายรายการไปที่ถังขยะเรียบร้อย",
                color: "#f43f5e",
                icon: "bi-trash3"
            },
            'error': {
                msg: customMsg || "เกิดข้อผิดพลาด",
                color: "#64748b",
                icon: "bi-x-circle"
            }
        };
        const config = configs[type] || configs['error'];

        // คำนวณตำแหน่งทับซ้อน
        const existingAlerts = $('.custom-alert').length;
        const topPosition = 25 + (existingAlerts * 85);

        const alertHtml = `
            <div class="custom-alert shadow-lg" style="color: ${config.color}; position: fixed; top: ${topPosition}px; right: 25px; z-index: 10000; width: 100%; max-width: 380px; display: flex; align-items: center; transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55); background: white; padding: 15px; border-radius: 12px; border-left: 5px solid ${config.color}">
                <div class="alert-icon-wrap" style="background-color: ${config.color}20; padding: 10px; border-radius: 10px; margin-right: 15px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi ${config.icon} fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="text-dark fw-bold mb-0" style="font-size: 0.95rem;">${config.msg}</div>
                </div>
                <button type="button" class="btn-close" onclick="closeThisAlert(this)" style="font-size: 0.8rem;"></button>
            </div>`;

        const $alert = $(alertHtml).hide().appendTo('body').fadeIn(300);

        // ตั้งเวลาปิดอัตโนมัติ
        const timer = setTimeout(() => {
            closeThisAlert($alert.find('.btn-close'));
        }, 4500);
        $alert.data('timer', timer);
    }

    function closeThisAlert(btn) {
        const $target = $(btn).closest('.custom-alert');
        const heightToRemove = 85;
        const currentTop = parseInt($target.css('top'));

        clearTimeout($target.data('timer'));

        // ขยับ Alert อันอื่นขึ้น
        $('.custom-alert').each(function () {
            const otherTop = parseInt($(this).css('top'));
            if (otherTop > currentTop) {
                $(this).css('top', (otherTop - heightToRemove) + 'px');
            }
        });

        $target.css({
            'transform': 'translateX(120%)',
            'opacity': '0'
        });
        setTimeout(() => $target.remove(), 600);
    }

    /**
     * ลบ PR รายตัว (Soft Delete)
     */
    function deletePR(id) {
        if (!confirm('⚠️ ยืนยันการย้ายใบขอซื้อ (PR) นี้ไปยังถังขยะ?')) return;

        fetch(`api/delete_pr.php?id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // ลบแถวออกจาก DataTable โดยอ้างอิงจาก IDCheckbox
                    const row = $(`.pr-checkbox[value="${id}"]`).closest('tr');
                    prTable.row(row).remove().draw(false);

                    renderAlert('delete', `ย้าย PR เลขที่ ${res.doc_no || ''} ไปที่ถังขยะแล้ว`);
                    updateBulkUI();
                } else {
                    renderAlert('error', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                renderAlert('error', 'การเชื่อมต่อผิดพลาด');
            });
    }

    /**
     * ลบ PR แบบกลุ่ม (Bulk Soft Delete)
     */
    function bulkDeletePR() {
        const checkedItems = $('.pr-checkbox:checked');
        const ids = [];
        checkedItems.each(function () {
            ids.push($(this).val());
        });

        if (ids.length === 0) return;
        if (!confirm(`⚠️ ยืนยันย้ายใบขอซื้อที่เลือกทั้งหมด ${ids.length} รายการไปยังถังขยะ?`)) return;

        const fd = new FormData();
        fd.append('action', 'bulk_delete');
        fd.append('ids', JSON.stringify(ids));

        fetch('api/delete_pr.php', {
            method: 'POST',
            body: fd
        })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // ลบทุกแถวที่เลือกออกแบบรวดเดียว
                    checkedItems.each(function () {
                        prTable.row($(this).closest('tr')).remove();
                    });

                    prTable.draw(false);
                    renderAlert('delete', `ย้าย ${ids.length} รายการลงถังขยะแล้ว`);
                    updateBulkUI();
                    $('#selectAll').prop('checked', false);
                } else {
                    renderAlert('error', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                renderAlert('error', 'การเชื่อมต่อผิดพลาด');
            });
    }

    function approvePR(id, prNo) {
        Swal.fire({
            title: 'ยืนยันการอนุมัติ?',
            text: `คุณกำลังจะอนุมัติใบขอซื้อเลขที่ ${prNo}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            cancelButtonText: 'ยกเลิก',
            confirmButtonText: 'ยืนยันอนุมัติ',
            reverseButtons: true,

            heightAuto: false // กันหน้าดีด
        }).then((result) => {
            if (result.isConfirmed) {
                // ส่งไปที่ API ของ PR (จารอย่าลืมสร้างไฟล์นี้แยกจากของ Quotation นะ)
                fetch(`api/update_pr_status.php?id=${id}&action=approved`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            renderAlert('success', 'อนุมัติใบขอซื้อเรียบร้อยแล้ว');

                            // 1. หาแถว (Row) ที่เราเพิ่งกด
                            const targetRow = $(`.pr-checkbox[value="${id}"]`).closest('tr');

                            // 2. อัปเดต Column "STATUS" (ในตารางของจารคือคอลัมน์ที่ 7 นับจาก 0)
                            // เราจะเปลี่ยนตัวหนังสือเป็น 'approved'
                            prTable.cell(targetRow, 7).data('approved').draw(false);

                            // 3. ซ่อนปุ่มอนุมัติและปุ่มแก้ไขทิ้ง (เพราะอนุมัติแล้วไม่ควรแก้)
                            targetRow.find('button[onclick^="approvePR"]').fadeOut();
                            targetRow.find('a[href^="edit_pr.php"]').fadeOut();

                            // --- ลบ location.reload() ทิ้งไปเลยครับจาร ---
                        } else {
                            Swal.fire('ผิดพลาด', data.message, 'error');
                        }
                    })
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
        if (typeof prTable !== 'undefined') {
            prTable.draw();
        } else {
            console.error("หาตัวแปร prTable ไม่เจอครับ");
        }
    }
    function resetFilter() {
        $('#filterSupplier').val('');
        $('#filterStatus').val(''); // ล้างค่าใน select status
        $('#minDate').val('');
        $('#maxDate').val('');

        // สั่งล้างการ search ทุกคอลัมน์แล้ววาดใหม่
        prTable.column(3).search('');
        prTable.column(7).search('');
        prTable.draw();
    }
</script>
<?php include 'footer.php'; ?>