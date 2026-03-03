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
            IF(p.is_internal = 1, u.name, c.customer_name) AS display_requester 
        FROM pr p
        LEFT JOIN customers c ON p.customer_id = c.id AND p.is_internal = 0
        LEFT JOIN users u ON p.created_by = u.id AND p.is_internal = 1
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.deleted_at IS NULL 
        ORDER BY p.created_at DESC";

$result = mysqli_query($conn, $sql);
?>

<div class="w-full ">
    <div id="bulkActions"
        class="hidden mb-3 p-2 bg-indigo-50 border border-indigo-100 rounded-lg flex justify-between items-center transition-all">
        <span class="text-xs font-bold text-indigo-700 ml-2">
            เลือกอยู่ <span id="selectedCount">0</span> รายการ
        </span>
        <button onclick="bulkDeletePR()"
            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-md text-[10px] font-bold shadow-sm transition-all flex items-center gap-2">
            <i class="fas fa-trash-alt"></i> ลบรายการที่เลือก
        </button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4">
            <div class="overflow-x-auto">
                <table id="prTable" class="w-full display hover border-none">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="w-8 text-center !pr-2">
                                <input type="checkbox" id="selectAll"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            </th>
                            <th class="w-8 text-center">ID</th>
                            <th>DOC NO.</th>
                            <th>DATE</th>
                            <th>CLIENT</th>
                            <th>ISSUER</th>
                            <th class="text-right">TOTAL AMOUNT</th>
                            <th class="text-center w-24">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-600">
                        <?php
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($result)):
                            // เช็คว่าเป็นงานภายในหรือไม่
                            $isInternal = (isset($row['is_internal']) && $row['is_internal'] == 1);
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-100">
                                <td class="text-center px-4">
                                    <input type="checkbox" name="pr_ids[]" value="<?= $row['id'] ?>"
                                        class="pr-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                </td>
                                <td class="text-center text-slate-300 font-mono text-[10px]"><?= $i++ ?></td>

                                <td class="font-bold text-slate-800 italic px-2">
                                    <?= htmlspecialchars($row['doc_no']) ?>
                                </td>

                                <td class="text-slate-500 text-sm">
                                    <?= date('d/m/y', strtotime($row['doc_date'])) ?>
                                </td>

                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-slate-700 text-sm">
                                            <?= htmlspecialchars($row['display_requester'] ?: '-') ?>
                                        </span>
                                        <?php if ($isInternal): ?>
                                            <span class="text-[9px] font-bold text-amber-500 uppercase flex items-center gap-1">
                                                <i class="fas fa-user-circle"></i> Internal Staff
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[9px] font-bold text-blue-400 uppercase flex items-center gap-1">
                                                <i class="fas fa-handshake"></i> Customer Project
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="text-slate-500 font-medium text-sm">
                                        <?= htmlspecialchars($row['supplier_name'] ?: '-') ?>
                                    </div>
                                </td>

                                <td class="text-right font-mono font-bold text-slate-900 px-4">
                                    <?= number_format($row['grand_total'], 2) ?>
                                </td>

                                <td class="px-4">
                                    <div class="flex justify-center gap-1.5">
                                        <a href="view_pr.php?id=<?= $row['id'] ?>" title="ดูรายละเอียด"
                                            class="w-8 h-8 flex items-center justify-center bg-white text-slate-400 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 shadow-sm transition-all active:scale-90">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                        <a href="edit_pr.php?id=<?= $row['id'] ?>" title="แก้ไข"
                                            class="w-8 h-8 flex items-center justify-center bg-white text-slate-400 rounded-lg hover:bg-amber-50 hover:text-amber-600 border border-slate-200 shadow-sm transition-all active:scale-90">
                                            <i class="fas fa-edit text-xs"></i>
                                        </a>
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
        // 1. Initialize DataTable
        prTable = $('#prTable').DataTable({
            "pageLength": 10,
            "dom": '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json" },
            "order": [[1, "asc"]],
            "columnDefs": [{ "orderable": false, "targets": [0, 7] }],
            "drawCallback": function () {
                updateBulkUI(); // อัปเดต UI ปุ่ม Bulk ทันทีที่มีการวาดตารางใหม่
            }
        });

        // 2. Select All - รองรับการเลือกข้ามหน้าในระดับ UI
        $(document).on('change', '#selectAll', function () {
            const isChecked = $(this).prop('checked');
            $('.pr-checkbox').prop('checked', isChecked);
            updateBulkUI();
        });

        // 3. Single Select
        $(document).on('change', '.pr-checkbox', function () {
            updateBulkUI();
        });
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
            'success': { msg: customMsg || "ทำรายการสำเร็จ", color: "#10b981", icon: "bi-check-lg" },
            'delete': { msg: customMsg || "ย้ายรายการไปที่ถังขยะเรียบร้อย", color: "#f43f5e", icon: "bi-trash3" },
            'error': { msg: customMsg || "เกิดข้อผิดพลาด", color: "#64748b", icon: "bi-x-circle" }
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
        const timer = setTimeout(() => { closeThisAlert($alert.find('.btn-close')); }, 4500);
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

        $target.css({ 'transform': 'translateX(120%)', 'opacity': '0' });
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
        checkedItems.each(function () { ids.push($(this).val()); });

        if (ids.length === 0) return;
        if (!confirm(`⚠️ ยืนยันย้ายใบขอซื้อที่เลือกทั้งหมด ${ids.length} รายการไปยังถังขยะ?`)) return;

        const fd = new FormData();
        fd.append('action', 'bulk_delete');
        fd.append('ids', JSON.stringify(ids));

        fetch('api/delete_pr.php', { method: 'POST', body: fd })
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
</script>
<?php include 'footer.php'; ?>