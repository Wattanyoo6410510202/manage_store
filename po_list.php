<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

$sql = "SELECT p.*, c.customer_name, s.company_name as supplier_name 
        FROM po p
        LEFT JOIN customers c ON p.customer_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        ORDER BY p.created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<div class="w-full px-4 py-2">
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
                                    <div class="font-semibold text-slate-700"><?= htmlspecialchars($row['customer_name'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <div class="text-slate-500 font-medium"><?= htmlspecialchars($row['supplier_name'] ?: '-') ?></div>
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

    // ฟังก์ชันลบรายตัว (PO)
    function deletePO(id) {
        if (!confirm('⚠️ ยืนยันการลบใบสั่งซื้อ (PO) นี้? ข้อมูลรายการสินค้าจะถูกลบออกด้วย')) return;

        fetch(`api/delete_po.php?id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // ลบแถวออกจาก DataTable
                    poTable.row($(`.po-checkbox[value="${id}"]`).closest('tr')).remove().draw(false);
                    renderAlert('delete', `ลบ PO: ${res.doc_no} เรียบร้อยแล้ว`);
                } else {
                    renderAlert('error', res.message);
                }
            })
            .catch(err => renderAlert('error', 'การเชื่อมต่อผิดพลาด'));
    }

    // ฟังก์ชันลบแบบกลุ่ม (PO)
    function bulkDeletePO() {
        const ids = [];
        $('.po-checkbox:checked').each(function () { ids.push($(this).val()); });

        if (ids.length === 0) return;
        if (!confirm(`⚠️ ยืนยันลบใบสั่งซื้อที่เลือกทั้งหมด ${ids.length} รายการ?`)) return;

        const fd = new FormData();
        fd.append('action', 'bulk_delete');
        fd.append('ids', JSON.stringify(ids));

        fetch('api/delete_po.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    ids.forEach(id => {
                        poTable.row($(`.po-checkbox[value="${id}"]`).closest('tr')).remove();
                    });
                    poTable.draw(false);
                    renderAlert('delete', `ลบสำเร็จ ${res.count} รายการ`);
                    updateBulkUI();
                } else {
                    renderAlert('error', res.message);
                }
            })
            .catch(err => renderAlert('error', 'การเชื่อมต่อผิดพลาด'));
    }
</script>
<?php include 'footer.php'; ?>