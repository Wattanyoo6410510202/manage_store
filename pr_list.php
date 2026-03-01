<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

$sql = "SELECT p.*, c.customer_name, s.company_name as supplier_name 
        FROM pr p
        LEFT JOIN customers c ON p.customer_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        ORDER BY p.created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<div class="w-full px-4 py-2">
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
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-50">
                                <td class="text-center">
                                    <input type="checkbox" name="pr_ids[]" value="<?= $row['id'] ?>"
                                        class="pr-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                </td>
                                <td class="text-center text-slate-300 font-mono text-[10px]"><?= $i++ ?></td>
                                <td class="font-bold text-slate-800 italic"><?= $row['doc_no'] ?></td>
                                <td class="text-slate-500"><?= date('d/m/y', strtotime($row['doc_date'])) ?></td>
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
                                        <a href="view_pr.php?id=<?= $row['id'] ?>"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 shadow-sm transition-all">
                                            <i class="fas fa-eye text-[10px]"></i>
                                        </a>
                                        <a href="edit_pr.php?id=<?= $row['id'] ?>"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-amber-50 hover:text-amber-600 border border-slate-200 shadow-sm transition-all">
                                            <i class="fas fa-edit text-[10px]"></i>
                                        </a>
                                        <button onclick="deletePR(<?= $row['id'] ?>)"
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
                updateBulkUI();
            }
        });

        // 2. Select All
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

    function updateBulkUI() {
        const checkedCount = $('.pr-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#bulkActions').removeClass('hidden').addClass('flex').fadeIn(200);
            $('#selectedCount').text(checkedCount);
        } else {
            $('#bulkActions').fadeOut(200, function () {
                $(this).addClass('hidden').removeClass('flex');
            });
            $('#selectAll').prop('checked', false);
        }
    }

    function renderAlert(type, customMsg = '') {
        const configs = {
            'success': { msg: customMsg || "สำเร็จ", color: "#10b981", icon: "bi-check-lg" },
            'delete': { msg: customMsg || "ลบรายการเรียบร้อย", color: "#f43f5e", icon: "bi-trash3" },
            'error': { msg: customMsg || "เกิดข้อผิดพลาด", color: "#64748b", icon: "bi-x-circle" }
        };
        const config = configs[type] || configs['error'];
        const existingAlerts = $('.custom-alert').length;
        const topPosition = 25 + (existingAlerts * 85);

        const alertHtml = `
            <div class="custom-alert shadow-lg" style="color: ${config.color}; position: fixed; top: ${topPosition}px; right: 25px; z-index: 10000; width: 100%; max-width: 380px; display: flex; align-items: center; transition: all 0.5s ease; background: white; padding: 15px; border-radius: 12px; border-left: 5px solid ${config.color}">
                <div class="alert-icon-wrap" style="background-color: ${config.color}20; padding: 10px; border-radius: 10px; margin-right: 15px;">
                    <i class="bi ${config.icon} fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="text-dark fw-bold mb-0" style="font-size: 0.95rem;">${config.msg}</div>
                </div>
                <button type="button" class="btn-close" onclick="closeThisAlert(this)"></button>
            </div>`;

        const $alert = $(alertHtml).appendTo('body');
        setTimeout(() => { closeThisAlert($alert.find('.btn-close')); }, 4000);
    }

    function closeThisAlert(btn) {
        const $target = $(btn).closest('.custom-alert');
        $target.css('transform', 'translateX(120%)');
        setTimeout(() => $target.remove(), 600);
    }

    // ฟังก์ชันลบรายตัว
    function deletePR(id) {
        if (!confirm('⚠️ ยืนยันการลบใบขอซื้อ (PR) นี้?')) return;

        fetch(`api/delete_pr.php?id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // ลบแถวออกจาก DataTable
                    prTable.row($(`.pr-checkbox[value="${id}"]`).closest('tr')).remove().draw(false);
                    renderAlert('delete', 'ลบใบ PR เรียบร้อยแล้ว');
                } else {
                    renderAlert('error', res.message);
                }
            })
            .catch(err => renderAlert('error', 'การเชื่อมต่อผิดพลาด'));
    }

    // ฟังก์ชันลบแบบกลุ่ม
    function bulkDeletePR() {
        const ids = [];
        $('.pr-checkbox:checked').each(function () { ids.push($(this).val()); });

        if (ids.length === 0) return;
        if (!confirm(`⚠️ ยืนยันลบใบขอซื้อที่เลือกทั้งหมด ${ids.length} รายการ?`)) return;

        const fd = new FormData();
        fd.append('action', 'bulk_delete');
        fd.append('ids', JSON.stringify(ids));

        fetch('api/delete_pr.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    ids.forEach(id => {
                        prTable.row($(`.pr-checkbox[value="${id}"]`).closest('tr')).remove();
                    });
                    prTable.draw(false);
                    renderAlert('delete', `ลบรายการเรียบร้อยแล้ว`);
                    updateBulkUI();
                } else {
                    renderAlert('error', res.message);
                }
            })
            .catch(err => renderAlert('error', 'การเชื่อมต่อผิดพลาด'));
    }
</script>
<?php include 'footer.php'; ?>