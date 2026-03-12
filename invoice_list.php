<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

// 1. SQL ดึงข้อมูล - ตัด p.project_name และ i.project_id ออก (เพราะไม่มีในตาราง)
// เปลี่ยนไปใช้ s.company_name (Supplier) และแสดงเลขที่บิลแทน
$sql = "SELECT i.*, 
               s.company_name as supplier_name,
               u.username as creator_name,
               (SELECT item_name FROM invoice_items 
                WHERE invoice_id = i.id 
                ORDER BY id ASC LIMIT 1) as first_item_desc
        FROM invoices i
        LEFT JOIN suppliers s ON i.supplier_id = s.id
        LEFT JOIN users u ON i.created_by = u.id 
        WHERE i.deleted_at IS NULL 
        ORDER BY i.created_at DESC";

$result = mysqli_query($conn, $sql);

// ดึงรายชื่อ Supplier สำหรับตัวกรอง (เหมือนเดิม)
$supplier_sql = "SELECT id, company_name FROM suppliers ORDER BY company_name ASC";
$suppliers = mysqli_fetch_all(mysqli_query($conn, $supplier_sql), MYSQLI_ASSOC);
?>

<div class="w-full">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4">
            <div class="flex flex-wrap items-center gap-3 mb-6 w-full">
                <div class="relative min-w-[200px]">
                    <label
                        class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">กรองตามหน่วยงาน</label>
                    <select id="filterSupplier"
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg p-2 focus:ring-indigo-500 transition-all">
                        <option value="">ทั้งหมด (Show All)</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= htmlspecialchars($s['company_name']) ?>">
                                <?= htmlspecialchars($s['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="relative min-w-[150px]">
                    <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">ตั้งแต่วันที่</label>
                    <input type="date" id="minDate"
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg p-2 focus:ring-indigo-500">
                </div>

                <div class="relative min-w-[150px]">
                    <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">ถึงวันที่</label>
                    <input type="date" id="maxDate"
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg p-2 focus:ring-indigo-500">
                </div>

                <div class="relative min-w-[120px]">
                    <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block ml-1">สถานะชำระเงิน</label>
                    <select id="filterStatus"
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg p-2 focus:ring-indigo-500">
                        <option value="">ทั้งหมด</option>
                        <option value="PENDING">ค้างชำระ (Pending)</option>
                        <option value="PAID">ชำระแล้ว (Paid)</option>
                        <option value="OVERDUE">เกินกำหนด (Overdue)</option>
                    </select>
                </div>

                <button onclick="resetFilter()"
                    class="self-end mb-2.5 text-[10px] text-slate-400 hover:text-indigo-600 transition-colors">
                    <i class="fas fa-undo mr-1"></i> ล้างตัวกรอง
                </button>

                <div id="bulkActions"
                    class="hidden ml-auto self-end p-1.5 bg-red-50 border border-red-100 rounded-lg flex items-center gap-3 animate-fade-in">
                    <span class="text-[11px] font-bold text-red-700 ml-2">เลือกอยู่ <span id="selectedCount"
                            class="underline">0</span> รายการ</span>
                    <button onclick="bulkDelete()"
                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-md text-[10px] font-bold shadow-sm transition-all">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>

            <table id="invoiceTable" class="w-full display hover border-none">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="w-8 text-center !pr-2">
                            <input type="checkbox" id="selectAll"
                                class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        <th class="w-8 text-center">ID</th>
                        <th>เลขที่ใบแจ้งหนี้</th>
                        <th>หน่วยงาน / โครงการ</th>
                        <th>รายการล่าสุด</th>
                        <th class="text-right">ยอดสุทธิ</th>
                        <th>สถานะ</th>
                        <th>วันที่ออกบิล</th>
                        <th>ผู้บันทึก</th>
                        <th class="text-center w-28">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody class="text-slate-600">
                    <?php $i = 1;
                    while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-50">
                            <td class="text-center">
                                <input type="checkbox" name="invoice_ids[]" value="<?= $row['id'] ?>"
                                    class="invoice-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="text-center text-slate-300 font-mono text-[10px]"><?= $i++ ?></td>
                            <td class="font-bold text-slate-800"><?= $row['invoice_no'] ?></td>
                            <td>
                                <div class="font-semibold text-slate-700 truncate max-w-[180px]">
                                    <?= htmlspecialchars($row['supplier_name'] ?: '-') ?></div>
                            </td>
                            <td>
                                <div class="text-[11px] truncate max-w-[200px]"
                                    title="<?= htmlspecialchars($row['first_item_desc'] ?? '') ?>">
                                    <?= htmlspecialchars($row['first_item_desc'] ?: '-') ?>
                                </div>
                            </td>
                            <td>
                                <div class="flex flex-col items-end">
                                    <div
                                        class="flex items-center gap-1 bg-slate-50 px-2 py-1 rounded-md border border-slate-100 shadow-sm">
                                        <span
                                            class="text-[9px] font-black text-indigo-500 uppercase tracking-widest">Net</span>
                                        <span class="text-[14px] font-mono font-black text-slate-900 leading-none">
                                            <?= number_format($row['total_amount'], 2) ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                $status = strtoupper($row['status'] ?: 'pending');
                                $config = [
                                    'PENDING' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100', 'dot' => 'bg-amber-400'],
                                    'PAID' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100', 'dot' => 'bg-emerald-400'],
                                    'OVERDUE' => ['bg' => 'bg-red-50', 'text' => 'text-red-600', 'border' => 'border-red-100', 'dot' => 'bg-red-400']
                                ];
                                $style = $config[$status] ?? $config['PENDING'];
                                ?>
                                <div
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border <?= $style['bg'] ?> <?= $style['border'] ?> <?= $style['text'] ?> shadow-sm">
                                    <span
                                        class="w-1.5 h-1.5 rounded-full <?= $style['dot'] ?> <?= $status === 'PENDING' ? 'animate-pulse' : '' ?>"></span>
                                    <span class="text-[10px] font-bold uppercase tracking-wide"><?= $status ?></span>
                                </div>
                            </td>
                            <td data-order="<?= $row['invoice_date'] ?>">
                                <div class="flex flex-col">
                                    <span class="text-[13px] font-bold text-slate-700 flex items-center gap-1">
                                        <i class="fas fa-calendar-alt text-[9px] text-slate-300"></i>
                                        <?= date('d/m/Y', strtotime($row['invoice_date'])) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="text-[11px] font-medium"><?= htmlspecialchars($row['creator_name'] ?: '-') ?></td>
                            <td>
                                <div class="flex justify-center gap-1">
                                    <?php
                                    // ปุ่ม Approve: แสดงเฉพาะสถานะ pending และผู้มีสิทธิ์ (admin/gmhok)
                                    if ($row['status'] === 'pending' && (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'gmhok'))):
                                        ?>
                                        <button onclick="approveInvoice(<?= $row['id'] ?>)"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-emerald-500 rounded-md hover:bg-emerald-50 hover:text-emerald-600 border border-emerald-100 shadow-sm transition-all"
                                            title="อนุมัติรายการ">
                                            <i class="fas fa-check-circle text-[10px]"></i>
                                        </button>
                                    <?php endif; ?>

                                    <a href="view_invoice.php?id=<?= $row['id'] ?>"
                                        class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 shadow-sm transition-all"
                                        title="ดูรายละเอียด">
                                        <i class="fas fa-eye text-[10px]"></i>
                                    </a>

                                    <?php if ($row['status'] === 'pending'): ?>
                                        <a href="edit_invoice.php?id=<?= $row['id'] ?>"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-amber-50 hover:text-amber-600 border border-slate-200 shadow-sm transition-all"
                                            title="แก้ไข">
                                            <i class="fas fa-edit text-[10px]"></i>
                                        </a>
                                    <?php endif; ?>

                                    <button onclick="deleteInvoice(<?= $row['id'] ?>)"
                                        class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-red-50 hover:text-red-600 border border-slate-200 shadow-sm transition-all"
                                        title="ลบ">
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

<script>
    let invTable;

    function updateBulkUI() {
        const checkedCount = $('.invoice-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#bulkActions').removeClass('hidden').addClass('flex');
            $('#selectedCount').text(checkedCount);
        } else {
            $('#bulkActions').addClass('hidden').removeClass('flex');
        }
    }

    $(document).ready(function () {
        invTable = $('#invoiceTable').DataTable({
            "pageLength": 10,
            "dom": '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json" },
            "order": [[7, "desc"]], // เรียงตามวันที่ (Col 7)
            "columnDefs": [{ "orderable": false, "targets": [0, 9] }],
            "drawCallback": function () {
                updateBulkUI();
            }
        });

        // คัดกรองช่วงวันที่
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            const minStr = $('#minDate').val();
            const maxStr = $('#maxDate').val();
            if (!minStr && !maxStr) return true;

            const rowNode = settings.aoData[dataIndex].nTr;
            const dateRaw = $(rowNode).find('td:eq(7)').attr('data-order'); 
            
            if (!dateRaw) return false;

            const date = new Date(dateRaw);
            const min = minStr ? new Date(minStr) : null;
            const max = maxStr ? new Date(maxStr) : null;

            date.setHours(0, 0, 0, 0);
            if (min) min.setHours(0, 0, 0, 0);
            if (max) max.setHours(0, 0, 0, 0);

            if (min && date < min) return false;
            if (max && date > max) return false;
            return true;
        });

        $('#minDate, #maxDate').on('change', () => invTable.draw());

        // คัดกรองตาม Supplier (Col 3)
        $('#filterSupplier').on('change', function () {
            invTable.column(3).search($(this).val()).draw();
        });

        // คัดกรองตามสถานะ (Col 6)
        $('#filterStatus').on('change', function () {
            invTable.column(6).search($(this).val()).draw();
        });

        // ระบบ Checkbox เลือกทั้งหมด
        $('#selectAll').on('change', function () {
            $('.invoice-checkbox').prop('checked', $(this).is(':checked'));
            updateBulkUI();
        });

        $(document).on('change', '.invoice-checkbox', function () {
            updateBulkUI();
            if (!$(this).is(':checked')) $('#selectAll').prop('checked', false);
        });
    });

    // ฟังก์ชันอนุมัติ (Approve)
    function approveInvoice(id) {
        Swal.fire({
            title: 'ยืนยันการอนุมัติ?',
            text: "รายการนี้จะถูกบันทึกเข้าสู่ระบบอย่างเป็นทางการ",
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`api/approve_invoice.php?id=${id}`)
                    .then(res => res.json())
                    .then(res => {
                        if (res.status === 'success') {
                            Swal.fire('สำเร็จ!', 'อนุมัติรายการเรียบร้อย', 'success').then(() => location.reload());
                        }
                    });
            }
        });
    }

    // ฟังก์ชันลบ (Delete)
    function deleteInvoice(id) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: "ข้อมูลนี้จะหายไปจากระบบของบริษัท",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`api/delete_invoice.php?id=${id}`)
                    .then(res => res.json())
                    .then(res => {
                        if (res.status === 'success') {
                            Swal.fire('ลบแล้ว!', 'ข้อมูลถูกลบเรียบร้อย', 'success').then(() => location.reload());
                        }
                    });
            }
        });
    }
</script>

<?php include 'footer.php'; ?>