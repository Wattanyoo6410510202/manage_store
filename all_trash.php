<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

$sql = "(SELECT t.id, t.doc_no COLLATE utf8mb4_general_ci as doc_no, t.created_at, t.deleted_at, t.deleted_by, u.name as deleted_by_name, 'pr' COLLATE utf8mb4_general_ci as type 
         FROM pr t 
         LEFT JOIN users u ON t.deleted_by = u.id 
         WHERE t.deleted_at IS NOT NULL)
        UNION 
        (SELECT t.id, t.doc_no COLLATE utf8mb4_general_ci as doc_no, t.created_at, t.deleted_at, t.deleted_by, u.name as deleted_by_name, 'quotations' COLLATE utf8mb4_general_ci as type 
         FROM quotations t 
         LEFT JOIN users u ON t.deleted_by = u.id 
         WHERE t.deleted_at IS NOT NULL)
        UNION 
        (SELECT t.id, t.doc_no COLLATE utf8mb4_general_ci as doc_no, t.created_at, t.deleted_at, t.deleted_by, u.name as deleted_by_name, 'po' COLLATE utf8mb4_general_ci as type 
         FROM po t 
         LEFT JOIN users u ON t.deleted_by = u.id 
         WHERE t.deleted_at IS NOT NULL)
        ORDER BY deleted_at DESC";

$result = mysqli_query($conn, $sql);
?>

<div class="w-full ">


    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">

            <div class="flex flex-wrap gap-2">
                <button onclick="filterType('all')"
                    class="filter-btn active px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold transition-all bg-white hover:bg-slate-50">ทั้งหมด</button>
                <button onclick="filterType('pr')"
                    class="filter-btn px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold transition-all bg-white hover:bg-slate-50">PR</button>
                <button onclick="filterType('quotations')"
                    class="filter-btn px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold transition-all bg-white hover:bg-slate-50">Quotation</button>
                <button onclick="filterType('po')"
                    class="filter-btn px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold transition-all bg-white hover:bg-slate-50">PO</button>
            </div>

            <div class="flex gap-2">
                <button onclick="bulkAction('restore')" id="bulkRestoreBtn"
                    class="hidden px-5 py-2.5 bg-emerald-500 text-white rounded-xl text-sm font-bold  transition-all active:scale-95">
                    <i class="fas fa-undo mr-2"></i> กู้คืน (<span class="selected-count">0</span>)
                </button>
                <button onclick="bulkAction('permanent_delete')" id="bulkDeleteBtn"
                    class="hidden px-5 py-2.5 bg-rose-500 text-white rounded-xl text-sm font-bold  transition-all active:scale-95">
                    <i class="fas fa-fire mr-2"></i> ลบถาวร (<span class="selected-count">0</span>)
                </button>
            </div>
        </div>

        <table id="trashDataTable" class="w-full display hover border-none">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="w-10 text-center"><input type="checkbox" id="selectAll"
                            class="w-4 h-4 rounded text-indigo-600"></th>
                    <th>ประเภท</th>
                    <th>เลขที่เอกสาร</th>
                    <th>วันที่ลบ</th>
                    <th>ลบโดย</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr id="row-<?= $row['type'] ?>-<?= $row['id'] ?>" class="trash-row">
                        <td class="text-center">
                            <input type="checkbox" class="trash-checkbox w-4 h-4 rounded" value="<?= $row['id'] ?>"
                                data-type="<?= $row['type'] ?>">
                        </td>
                        <td><span
                                class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase <?= $row['type'] == 'pr' ? 'bg-blue-100 text-blue-600' : ($row['type'] == 'po' ? 'bg-indigo-100 text-indigo-600' : 'bg-amber-100 text-amber-600') ?>"><?= $row['type'] ?></span>
                        </td>
                        <td class="font-bold font-mono tracking-tighter"><?= $row['doc_no'] ?></td>
                        <td data-order="<?= strtotime($row['deleted_at']) ?>">
                            <?= date('d/m/Y H:i', strtotime($row['deleted_at'])) ?>
                        </td>
                        <td><?= $row['deleted_by_name'] ?></td>
                        <td class="text-center">
                            <div class="flex justify-center gap-1.5">
                                <button onclick="singleAction('restore', '<?= $row['type'] ?>', <?= $row['id'] ?>)"
                                    class="w-8 h-8 bg-emerald-50 text-emerald-600 rounded-lg hover:bg-emerald-600 hover:text-white transition-all"><i
                                        class="fas fa-undo-alt text-xs"></i></button>
                                <button onclick="singleAction('permanent_delete', '<?= $row['type'] ?>', <?= $row['id'] ?>)"
                                    class="w-8 h-8 bg-rose-50 text-rose-600 rounded-lg hover:bg-rose-600 hover:text-white transition-all"><i
                                        class="fas fa-trash-alt text-xs"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    let table;
    $(document).ready(function () {
        table = $('#trashDataTable').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json" },
            "pageLength": 10,
            "columnDefs": [{ "orderable": false, "targets": [0, 4] }],
            "order": [[3, "desc"]]
        });

        $('#selectAll').on('click', function () {
            const rows = table.rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"]', rows).prop('checked', this.checked);
            updateUI();
        });

        $('#trashDataTable tbody').on('change', '.trash-checkbox', updateUI);
    });

    function updateUI() {
        const count = $('.trash-checkbox:checked').length;
        $('.selected-count').text(count);
        $('#bulkRestoreBtn, #bulkDeleteBtn').toggleClass('hidden', count === 0);
    }

    function filterType(type) {
        $('.filter-btn').removeClass('active');
        $(`button[onclick="filterType('${type}')"]`).addClass('active');
        table.column(1).search(type === 'all' ? '' : '^' + type + '$', true, false).draw();
    }

    function singleAction(action, type, id) {
        if (!confirm(action === 'restore' ? 'กู้คืนรายการนี้?' : 'ลบถาวรรายการนี้?')) return;
        executeAjax(action, type, [id]);
    }

    function bulkAction(action) {
        const selected = $('.trash-checkbox:checked');
        if (!confirm(`ยืนยันการทำรายการทั้งหมด ${selected.length} รายการ?`)) return;

        const groups = {};
        selected.each(function () {
            const t = $(this).data('type');
            if (!groups[t]) groups[t] = [];
            groups[t].push($(this).val());
        });

        Object.keys(groups).forEach(type => executeAjax(action, type, groups[type]));
    }

    // --- จุดหัวใจ: ทำงานเสร็จแล้วลบแถวทิ้งโดยไม่ Refresh ---
    function executeAjax(action, table_name, ids) {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('table', table_name);
        fd.append('ids', JSON.stringify(ids));

        fetch('api/universal_handler.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // ลบแถวออกจาก DataTable ทีละ ID
                    res.target_ids.forEach(id => {
                        const rowSelector = `#row-${res.table_type}-${id}`;

                        // 1. สั่ง DataTable ลบข้อมูลแถวนั้น
                        table.row($(rowSelector)).remove();
                    });

                    // 2. วาดตารางใหม่ (Smooth Draw)
                    table.draw(false);

                    // 3. แจ้งเตือนสวยๆ (ถ้ามีฟังก์ชัน renderAlert)
                    if (typeof renderAlert === 'function') {
                        renderAlert('success', res.message);
                    }

                    updateUI(); // รีเซ็ตปุ่ม Bulk
                } else {
                    alert('Error: ' + res.message);
                }
            })
            .catch(err => console.error('Fetch Error:', err));
    }
</script>

<?php include 'footer.php'; ?>