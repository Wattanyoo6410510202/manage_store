<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

// 1. ปรับ SQL ให้ดึงจากตาราง invoices
$sql = "SELECT i.*, 
               p.project_name, 
               s.company_name as supplier_name,
               u.username as creator_name,
               (SELECT description FROM invoice_items 
                WHERE invoice_id = i.id 
                ORDER BY id ASC LIMIT 1) as first_item_desc
        FROM invoices i
        LEFT JOIN projects p ON i.project_id = p.id
        LEFT JOIN suppliers s ON i.supplier_id = s.id
        LEFT JOIN users u ON i.created_by = u.id 
        WHERE i.deleted_at IS NULL 
        ORDER BY i.created_at DESC";

$result = mysqli_query($conn, $sql);

// ดึงรายชื่อ Supplier สำหรับตัวกรอง
$supplier_sql = "SELECT id, company_name FROM suppliers ORDER BY company_name ASC";
$suppliers = mysqli_fetch_all(mysqli_query($conn, $supplier_sql), MYSQLI_ASSOC);
?>

<div class="w-full p-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4">
            <div class="flex flex-wrap items-center gap-3 mb-6 w-full">
                <div class="relative min-w-[200px]">
                    <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">กรองตามหน่วยงาน</label>
                    <select id="filterSupplier" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg p-2">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= htmlspecialchars($s['company_name']) ?>"><?= htmlspecialchars($s['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="relative min-w-[150px]">
                    <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">ตั้งแต่วันที่</label>
                    <input type="date" id="minDate" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg p-2">
                </div>

                <div class="relative min-w-[150px]">
                    <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">ถึงวันที่</label>
                    <input type="date" id="maxDate" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg p-2">
                </div>

                <div class="relative min-w-[120px]">
                    <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">สถานะชำระเงิน</label>
                    <select id="filterStatus" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg p-2">
                        <option value="">ทั้งหมด</option>
                        <option value="pending">ค้างชำระ (Pending)</option>
                        <option value="paid">ชำระแล้ว (Paid)</option>
                        <option value="overdue">เกินกำหนด (Overdue)</option>
                    </select>
                </div>

                <button onclick="resetFilter()" class="self-end mb-2 text-[10px] text-slate-400 hover:text-indigo-600">
                    <i class="fas fa-undo mr-1"></i> ล้างตัวกรอง
                </button>

                <div id="bulkActions" class="hidden ml-auto self-end p-1.5 bg-red-50 border border-red-100 rounded-lg flex items-center gap-3">
                    <span class="text-[11px] font-bold text-red-700 ml-2">เลือก <span id="selectedCount">0</span> รายการ</span>
                    <button onclick="bulkDelete()" class="bg-red-500 text-white px-3 py-1.5 rounded-md text-[10px] font-bold"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="invoiceTable" class="w-full hover border-none">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="w-8"><input type="checkbox" id="selectAll" class="rounded border-slate-300"></th>
                            <th>เลขที่ใบแจ้งหนี้</th>
                            <th>หน่วยงาน (Supplier)</th>
                            <th>โครงการ (Project)</th>
                            <th>รายการล่าสุด</th>
                            <th class="text-right">ยอดสุทธิ (Net)</th>
                            <th>สถานะ</th>
                            <th>วันที่ออกบิล</th>
                            <th>ผู้บันทึก</th>
                            <th class="text-center w-28">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-600">
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-50">
                            <td class="text-center"><input type="checkbox" class="inv-checkbox" value="<?= $row['id'] ?>"></td>
                            <td class="font-bold text-indigo-600"><?= $row['invoice_no'] ?></td>
                            <td class="font-semibold text-slate-700"><?= htmlspecialchars($row['supplier_name'] ?: '-') ?></td>
                            <td class="text-[11px]"><?= htmlspecialchars($row['project_name'] ?: '-') ?></td>
                            <td class="truncate max-w-[200px] text-[11px]"><?= htmlspecialchars($row['first_item_desc'] ?: '-') ?></td>
                            <td class="text-right font-mono font-bold text-slate-900">
                                <?= number_format($row['total_amount'], 2) ?>
                            </td>
                            <td>
                                <?php 
                                $status = $row['status'] ?: 'pending';
                                $badge = [
                                    'pending' => 'bg-amber-50 text-amber-600 border-amber-100',
                                    'paid'    => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                                    'overdue' => 'bg-red-50 text-red-600 border-red-100'
                                ];
                                ?>
                                <span class="px-2 py-0.5 rounded-full border text-[10px] font-bold <?= $badge[$status] ?>">
                                    <?= strtoupper($status) ?>
                                </span>
                            </td>
                            <td data-order="<?= $row['invoice_date'] ?>">
                                <div class="text-[11px] font-bold"><?= date('d/m/Y', strtotime($row['invoice_date'])) ?></div>
                            </td>
                            <td class="text-[11px]"><?= htmlspecialchars($row['creator_name'] ?: '-') ?></td>
                            <td>
                                <div class="flex justify-center gap-1">
                                    <a href="view_invoice.php?id=<?= $row['id'] ?>" class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md border hover:text-indigo-600" title="View"><i class="fas fa-eye text-[10px]"></i></a>
                                    <a href="print_invoice.php?id=<?= $row['id'] ?>" target="_blank" class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md border hover:text-emerald-600" title="Print PDF"><i class="fas fa-print text-[10px]"></i></a>
                                    <button onclick="deleteInvoice(<?= $row['id'] ?>)" class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md border hover:text-red-600" title="Delete"><i class="fas fa-trash text-[10px]"></i></button>
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

    $(document).ready(function () {
        invTable = $('#invoiceTable').DataTable({
            "pageLength": 10,
            "order": [[7, "desc"]], // เรียงตามวันที่ออกบิล
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json" },
            "columnDefs": [{ "orderable": false, "targets": [0, 9] }]
        });

        // กรองตาม Supplier
        $('#filterSupplier').on('change', function () {
            invTable.column(2).search($(this).val()).draw();
        });

        // กรองตามสถานะ
        $('#filterStatus').on('change', function () {
            invTable.column(6).search($(this).val()).draw();
        });

        // Custom Filter สำหรับวันที่ (ใช้ logic เดิมของจาร)
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            const minStr = $('#minDate').val();
            const maxStr = $('#maxDate').val();
            const dateRaw = settings.aoData[dataIndex].anCells[7].getAttribute('data-order');
            if (!minStr && !maxStr) return true;
            const rowDate = new Date(dateRaw);
            if (minStr && rowDate < new Date(minStr)) return false;
            if (maxStr && rowDate > new Date(maxStr)) return false;
            return true;
        });

        $('#minDate, #maxDate').on('change', () => invTable.draw());
    });

    function resetFilter() {
        $('.w-full select, .w-full input[type="date"]').val('');
        invTable.search('').columns().search('').draw();
    }

    function deleteInvoice(id) {
        if (!confirm('ยืนยันการลบใบแจ้งหนี้นี้?')) return;
        fetch(`api/delete_invoice.php?id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    location.reload(); // หรือจะใช้ logic ลบแถวแบบไม่รีเฟรชที่ผมเขียนให้คราวก่อนก็ได้ครับจาร
                }
            });
    }
</script>

<?php include 'footer.php'; ?>