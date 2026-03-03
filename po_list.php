<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

// แก้ไข Query ให้รองรับ Soft Delete และแสดงข้อมูลที่ถูกต้อง
$sql = "SELECT p.*, c.customer_name, s.company_name as supplier_name 
        FROM po p
        LEFT JOIN customers c ON p.customer_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        -- ดึงเฉพาะรายการที่ deleted_at เป็น NULL (ยังไม่ถูกลบ)
        WHERE p.deleted_at IS NULL 
        -- เรียงลำดับตามวันที่สร้างล่าสุด (หรือจะใช้ updated_at ก็ได้ถ้ามี)
        ORDER BY p.created_at DESC";

$result = mysqli_query($conn, $sql);
?>

<div class="w-full ">
    <div id="bulkActions"
        class="hidden mb-3 p-2 bg-indigo-50 border border-indigo-100 rounded-lg flex justify-between items-center transition-all">
        <span class="text-xs font-bold text-indigo-700 ml-2">
            เลือกอยู่ <span id="selectedCount">0</span> รายการ (PO)
        </span>
        <button onclick="bulkDeletePO()"
            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-md text-[10px] font-bold shadow-sm transition-all flex items-center gap-2">
            <i class="fas fa-trash-alt"></i> ลบ PO ที่เลือก
        </button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4">
            <div class="overflow-x-auto">
                <table id="poTable" class="w-full display hover border-none">
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
                            <th>SUPPLIER</th>
                            <th class="text-right">TOTAL AMOUNT</th>
                            <th class="text-center w-24">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-600">
                        <?php
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($result)):
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-50">
                                <td class="text-center">
                                    <input type="checkbox" name="po_ids[]" value="<?= $row['id'] ?>"
                                        class="po-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                </td>
                                <td class="text-center text-slate-300 font-mono text-[10px]"><?= $i++ ?></td>
                                <td class="font-bold text-slate-800 italic"><?= $row['doc_no'] ?></td>
                                <td class="text-slate-500"><?= date('d/m/y', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <div class="font-semibold text-slate-700">
                                        <?= htmlspecialchars($row['customer_name'] ?: '-') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-slate-500 font-medium">
                                        <?= htmlspecialchars($row['supplier_name'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="text-right font-mono font-bold text-slate-900">
                                    <?= number_format($row['grand_total'], 2) ?>
                                </td>
                                <td>
                                    <div class="flex justify-center gap-1">
                                        <a href="view_po.php?id=<?= $row['id'] ?>"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 shadow-sm transition-all">
                                            <i class="fas fa-eye text-[10px]"></i>
                                        </a>
                                        <a href="edit_po.php?id=<?= $row['id'] ?>"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-amber-50 hover:text-amber-600 border border-slate-200 shadow-sm transition-all">
                                            <i class="fas fa-edit text-[10px]"></i>
                                        </a>
                                        <button onclick="deletePO(<?= $row['id'] ?>)"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-red-50 hover:text-red-600 border border-slate-200 shadow-sm transition-all">
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
    let poTable;

    $(document).ready(function () {
        // 1. Initialize DataTable สำหรับ PO
        poTable = $('#poTable').DataTable({
            "pageLength": 10,
            "dom": '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json" },
            "order": [[1, "asc"]],
            "columnDefs": [{ "orderable": false, "targets": [0, 7] }],
            "drawCallback": function () {
                updateBulkUI();
            }
        });

        // 2. Select All
        $(document).on('change', '#selectAll', function () {
            const isChecked = $(this).prop('checked');
            $('.po-checkbox').prop('checked', isChecked);
            updateBulkUI();
        });

        // 3. Single Select
        $(document).on('change', '.po-checkbox', function () {
            updateBulkUI();
        });
    });

    function updateBulkUI() {
        const checkedCount = $('.po-checkbox:checked').length;
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

    function deletePO(id) {
        if (!confirm('⚠️ ยืนยันการย้ายใบสั่งซื้อ (PO) นี้ไปยังถังขยะ?')) return;
        fetch(`api/delete_po.php?id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    const rowElement = $(`.po-checkbox[value="${id}"]`).closest('tr');
                    poTable.row(rowElement).remove().draw(false);
                    renderAlert('delete', `ย้าย PO: ${res.doc_no} ลงถังขยะเรียบร้อยแล้ว`);
                    updateBulkUI();
                } else {
                    renderAlert('error', res.message);
                }
            })
            .catch(err => renderAlert('error', 'การเชื่อมต่อผิดพลาด'));
    }

    function bulkDeletePO() {
        const checkedItems = $('.po-checkbox:checked');
        const ids = [];
        checkedItems.each(function () { ids.push($(this).val()); });

        if (ids.length === 0) return;
        if (!confirm(`⚠️ ยืนยันย้ายใบสั่งซื้อที่เลือกทั้งหมด ${ids.length} รายการไปยังถังขยะ?`)) return;

        const fd = new FormData();
        fd.append('action', 'bulk_delete');
        fd.append('ids', JSON.stringify(ids));

        fetch('api/delete_po.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    checkedItems.each(function () {
                        poTable.row($(this).closest('tr')).remove();
                    });
                    poTable.draw(false);
                    renderAlert('delete', `ย้ายรายการที่เลือก ${ids.length} รายการลงถังขยะแล้ว`);
                    updateBulkUI();
                    $('#selectAll').prop('checked', false);
                } else {
                    renderAlert('error', res.message);
                }
            })
            .catch(err => renderAlert('error', 'ไม่สามารถดำเนินการได้'));
    }

    // --- ส่วนของระบบ Alert แยกออกมาข้างนอกให้ชัดเจน ---
    function renderAlert(type, customMsg = '') {
        const configs = {
            'success': { msg: customMsg || "ทำรายการสำเร็จ", color: "#10b981", icon: "bi-check-lg" },
            'delete': { msg: customMsg || "ย้ายรายการเรียบร้อย", color: "#f43f5e", icon: "bi-trash3" },
            'error': { msg: customMsg || "เกิดข้อผิดพลาด", color: "#64748b", icon: "bi-x-circle" }
        };
        const config = configs[type] || configs['error'];
        const existingAlerts = $('.custom-alert').length;
        const topPosition = 25 + (existingAlerts * 85);

        const alertHtml = `
        <div class="custom-alert shadow-lg" style="color: ${config.color}; position: fixed; top: ${topPosition}px; right: 25px; z-index: 10000; width: 100%; max-width: 380px; display: flex; align-items: center; transition: all 0.5s ease; background: white; padding: 15px; border-radius: 12px; border-left: 5px solid ${config.color}">
            <div style="background-color: ${config.color}20; padding: 10px; border-radius: 10px; margin-right: 15px; display: flex; align-items: center; justify-content: center;">
                <i class="bi ${config.icon}" style="font-size: 1.5rem;"></i>
            </div>
            <div style="flex-grow: 1;">
                <div style="color: #334155; font-weight: bold; font-size: 0.95rem;">${config.msg}</div>
            </div>
            <button type="button" onclick="closeThisAlert(this)" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-size:1.2rem;">&times;</button>
        </div>`;

        const $alert = $(alertHtml).hide().appendTo('body').fadeIn(300);
        const timer = setTimeout(() => { closeThisAlert($alert.find('button')); }, 4500);
        $alert.data('timer', timer);
    }

    function closeThisAlert(btn) {
        const $target = $(btn).closest('.custom-alert');
        const heightToRemove = 85;
        const currentTop = parseInt($target.css('top'));
        clearTimeout($target.data('timer'));

        $('.custom-alert').each(function () {
            const otherTop = parseInt($(this).css('top'));
            if (otherTop > currentTop) {
                $(this).animate({ top: (otherTop - heightToRemove) + 'px' }, 300);
            }
        });

        $target.fadeOut(300, function () { $(this).remove(); });
    }
</script>
<?php include 'footer.php'; ?>