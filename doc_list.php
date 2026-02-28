<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

$sql = "SELECT q.*, c.customer_name, s.company_name as supplier_name 
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN suppliers s ON q.supplier_id = s.id
        ORDER BY q.created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<div class="w-full px-4 py-2">


    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4">
            <div class="overflow-x-auto">
                <table id="quoteTable" class="w-full display hover border-none">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="w-8 text-center !pr-2">
                                <input type="checkbox" id="selectAll"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            </th>
                            <div id="bulkActions"
                                class="hidden mb-3 p-2 bg-indigo-50 border border-indigo-100 rounded-lg flex justify-between items-center transition-all">
                                <span class="text-xs font-bold text-indigo-700 ml-2">
                                    เลือกอยู่ <span id="selectedCount">0</span> รายการ
                                </span>
                                <button onclick="bulkDelete()"
                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-md text-[10px] font-bold shadow-sm transition-all flex items-center gap-2">
                                    <i class="fas fa-trash-alt"></i> ลบรายการที่เลือก
                                </button>
                            </div>
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
                                    <input type="checkbox" name="quote_ids[]" value="<?= $row['id'] ?>"
                                        class="quote-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
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
                                        <a href="view_quotation.php?id=<?= $row['id'] ?>"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 shadow-sm transition-all"
                                            title="View">
                                            <i class="fas fa-eye text-[10px]"></i>
                                        </a>
                                        <a href="edit_quotation.php?id=<?= $row['id'] ?>"
                                            class="w-7 h-7 flex items-center justify-center bg-white text-slate-400 rounded-md hover:bg-amber-50 hover:text-amber-600 border border-slate-200 shadow-sm transition-all"
                                            title="Edit">
                                            <i class="fas fa-edit text-[10px]"></i>
                                        </a>
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

    $(document).ready(function () {
        // 1. Initialize DataTable
        quoteTable = $('#quoteTable').DataTable({
            "pageLength": 10,
            "dom": '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json" },
            "order": [[2, "desc"]],
            "columnDefs": [{ "orderable": false, "targets": [0, 7] }],
            "drawCallback": function () {
                updateBulkUI(); // อัปเดต UI เมื่อมีการเปลี่ยนหน้าหรือค้นหา
            }
        });

        // 2. เลือกทั้งหมด (Select All)
        $(document).on('change', '#selectAll', function () {
            const isChecked = $(this).prop('checked');
            $('.quote-checkbox').prop('checked', isChecked);
            updateBulkUI();
        });

        // 3. เลือกรายตัว
        $(document).on('change', '.quote-checkbox', function () {
            updateBulkUI();
        });
    });

    /**
     * ฟังก์ชันคุมการแสดงผลปุ่มลบกลุ่ม (Flash Actions)
     */
    function updateBulkUI() {
        const checkedCount = $('.quote-checkbox:checked').length;
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
    /**
  * ระบบแจ้งเตือน Flash Message แบบซ้อนต่อแถว (Stackable)
  */
    function renderAlert(type, customMsg = '') {
        const configs = {
            'success': { msg: customMsg || "บันทึกข้อมูลเรียบร้อยแล้ว", color: "#10b981", icon: "bi-check-lg" },
            'delete': { msg: customMsg || "ลบรายการเรียบร้อยแล้ว", color: "#f43f5e", icon: "bi-trash3" },
            'error': { msg: customMsg || "เกิดข้อผิดพลาดบางอย่าง", color: "#64748b", icon: "bi-x-circle" }
        };

        const config = configs[type] || configs['error'];

        // 1. คำนวณตำแหน่ง Top (ห่างจากขอบบน) ตามจำนวน Alert ที่มีอยู่แล้ว
        // สมมติว่าแต่ละอันสูงรวม Margin ประมาณ 85px
        const existingAlerts = $('.custom-alert').length;
        const topPosition = 25 + (existingAlerts * 85);

        const alertHtml = `
    <div class="custom-alert shadow-lg" 
         style="color: ${config.color}; position: fixed; top: ${topPosition}px; right: 25px; z-index: 10000; width: 100%; max-width: 380px; display: flex; align-items: center; transition: all 0.5s ease;">
        
        <div class="alert-icon-wrap" style="background-color: ${config.color}20;">
            <i class="bi ${config.icon} fs-4"></i>
        </div>
        
        <div class="flex-grow-1">
            <div class="text-dark fw-bold mb-0" style="font-size: 0.95rem;">${config.msg}</div>
        </div>
        
        <button type="button" class="btn-close" onclick="closeThisAlert(this)" 
                style="font-size: 0.7rem; opacity: 0.5;"></button>
        
        <style>
            .custom-alert::before {
                content: "";
                position: absolute;
                left: 0; top: 15%; height: 70%; width: 5px;
                background: currentColor;
                border-radius: 0 5px 5px 0;
            }
        </style>
    </div>`;

        const $alert = $(alertHtml).appendTo('body');

        // 2. ตั้งเวลาปิดอัตโนมัติ
        const autoClose = setTimeout(() => {
            closeThisAlert($alert.find('.btn-close'));
        }, 4000);

        $alert.data('timer', autoClose);
    }

    /**
     * ฟังก์ชันปิด Alert และขยับอันที่เหลือขึ้นไปแทนที่
     */
    function closeThisAlert(btn) {
        const $target = $(btn).closest('.custom-alert');
        const heightToRemove = 85; // ค่าเดียวกับที่ใช้คำนวณ top
        const currentTop = parseInt($target.css('top'));

        clearTimeout($target.data('timer'));

        // เลื่อนอันที่อยู่ข้างล่างขึ้นมาแทนที่
        $('.custom-alert').each(function () {
            const otherTop = parseInt($(this).css('top'));
            if (otherTop > currentTop) {
                $(this).css('top', (otherTop - heightToRemove) + 'px');
            }
        });

        // เลื่อนตัวเองออก
        $target.addClass('slide-out-right');
        setTimeout(() => $target.remove(), 600);
    }


    // ลบรายตัว
    function deleteQuote(id) {
        if (!confirm('ยืนยันการลบใบเสนอราคานี้?')) return;

        fetch(`api/delete_quotation.php?id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // ลบแถวออกจากตารางทันที (Single Page)
                    quoteTable.row($(`input[value="${id}"]`).closest('tr')).remove().draw(false);
                    renderAlert('delete');
                    updateBulkUI();
                } else {
                    renderAlert('error');
                }
            })
            .catch(err => renderAlert('error'));
    }

    // ลบแบบกลุ่ม
    function bulkDelete() {
        const ids = [];
        $('.quote-checkbox:checked').each(function () {
            ids.push($(this).val());
        });

        if (ids.length === 0) return;
        if (!confirm(`⚠️ ยืนยันลบใบเสนอราคาที่เลือกทั้งหมด ${ids.length} รายการ?`)) return;

        const fd = new FormData();
        fd.append('action', 'bulk_delete');
        fd.append('ids', JSON.stringify(ids));

        fetch('api/delete_quotation.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // ลบทุกแถวที่เลือก
                    ids.forEach(id => {
                        quoteTable.row($(`input[value="${id}"]`).closest('tr')).remove();
                    });
                    quoteTable.draw(false);
                    renderAlert('delete', res.message);
                    updateBulkUI();
                } else {
                    alert('Error: ' + res.message);
                }
            })
            .catch(err => renderAlert('error'));
    }
</script>
<?php include 'footer.php'; ?>