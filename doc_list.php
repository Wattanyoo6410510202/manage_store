<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

$sql = "SELECT q.*, c.customer_name, s.company_name as supplier_name,
               (SELECT item_desc FROM quotation_items 
                WHERE quotation_id = q.id 
                ORDER BY id ASC LIMIT 1) as first_item_desc
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN suppliers s ON q.supplier_id = s.id
        WHERE q.deleted_at IS NULL 
        ORDER BY q.updated_at DESC";

$result = mysqli_query($conn, $sql);
?>
<style>
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
</style>
<div class="w-full ">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4">
            <div class="overflow-x-auto">
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
                                    <div class="font-semibold text-slate-700">
                                        <?= htmlspecialchars($row['supplier_name'] ?: '-') ?>
                                    </div>
                                </td>
                       <td>
                                   <div class="font-semibold text-slate-700 truncate max-w-[250px]" title="<?= htmlspecialchars($row['first_item_desc'] ?? '') ?>">
                                      <?= htmlspecialchars($row['first_item_desc'] ?: 'ไม่มีรายละเอียดสินค้า') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-700 truncate max-w-[250px]">
                                        <?= htmlspecialchars($row['customer_name'] ?: '-') ?>
                                    </div>
                                </td>
                               
                                <td >
    <div class="flex flex-col items-end gap-1">
    <div class="flex items-center gap-1.5 opacity-80">
        <span class="text-[8px] font-bold text-slate-400 uppercase tracking-tighter">Subtotal</span>
        <i class="fas fa-calculator text-[9px] text-slate-300"></i>
        <span class="text-[10px] text-slate-500 font-mono font-medium">
            <?= number_format($row['subtotal'], 2) ?>
        </span>
    </div>

    <div class="flex items-center gap-1.5 bg-slate-50 px-2 py-1 rounded-md border border-slate-100 shadow-sm">
        <span class="text-[9px] font-black text-indigo-500 uppercase tracking-widest">Net</span>
        <i class="fas fa-coins text-[10px] text-amber-500"></i>
        <span class="text-[14px] font-mono font-black text-slate-900 leading-none">
            <?= number_format($row['grand_total'], 2) ?>
        </span>
    </div>
</div>
</td>
                               <td >
    <?php
    // กำหนดสีตามสถานะ (จารปรับชื่อสถานะให้ตรงกับใน DB นะครับ)
    $status = $row['status'] ?: 'pending';
    
    $config = [
        'pending'  => ['bg' => 'bg-amber-50',  'text' => 'text-amber-600', 'border' => 'border-amber-100', 'dot' => 'bg-amber-400', 'label' => 'รอ'],
        'approved' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100', 'dot' => 'bg-emerald-400', 'label' => 'อนุมัติ'],
    ];

    $style = $config[$status] ?? $config['pending'];
    ?>

    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border <?= $style['bg'] ?> <?= $style['border'] ?> <?= $style['text'] ?> shadow-sm">
        <span class="w-1.5 h-1.5 rounded-full <?= $style['dot'] ?> animate-pulse"></span>
        
        <span class="text-[10px] font-bold uppercase tracking-wide">
            <?= $style['label'] ?>
        </span>
    </div>
</td>
                                <td class="p-4">
                                        <div class="flex flex-col">
                                                  <span class="text-[15px] font-bold text-slate-800 mt-0.5 flex items-center gap-1">
                                                     <i class="fas fa-calendar-alt text-[9px]"></i>
                                               สร้างเมื่อ: <?= date('d/m/y H:i', strtotime($row['created_at'])) ?>
                                           </span>
        
                                      <?php if (!empty($row['updated_at'])): ?>
                                           <span class="text-[10px] text-slate-600  mt-0.5 flex items-center gap-1">
                                                       <i class="fas fa-history text-[9px]"></i>
                                     ปรับปรุงเมื่อ: <?= date('d/m/y H:i', strtotime($row['updated_at'])) ?>
                                    </span>
                                         <?php else: ?>
                              <span class="text-[10px] text-slate-300  mt-0.5">
                                         No updates yet
                                                    </span>
                                     <?php endif; ?>
                               </div>
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

    $(document).ready(function () {
        // 1. Initialize DataTable
        quoteTable = $('#quoteTable').DataTable({
            "pageLength": 10,
            "dom": '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json" },
            "order": [[2, "desc"]], // เรียงตามวันที่ (Column 2)
            "columnDefs": [{ "orderable": false, "targets": [0, 9] }], // ปิด sorting checkbox และ action
            "drawCallback": function () {
                updateBulkUI();
            }
        });

        // 2. เลือกทั้งหมด (Select All) - รองรับ Pagination
        $(document).on('change', '#selectAll', function () {
            const isChecked = $(this).prop('checked');
            // เลือกเฉพาะ checkbox ที่มองเห็นในหน้านั้นๆ
            $('.quote-checkbox').prop('checked', isChecked);
            updateBulkUI();
        });

        // 3. เลือกรายตัว
        $(document).on('change', '.quote-checkbox', function () {
            updateBulkUI();
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

                            // --- จุดแก้ไข: ไม่อยากให้รีเฟรชทั้งหน้า ให้แก้ตรงนี้ ---

                            // 1. หา Row (แถว) ที่เราเพิ่งกดอนุมัติไป
                            const targetRow = $(`button[onclick="approveQuote(${id})"]`).closest('tr');

                            // 2. อัปเดต Column สถานะ (สมมติว่าเป็นคอลัมน์ที่ 8 เหมือนในโค้ดจาร)
                            // เราจะเปลี่ยนข้อความเป็น 'approved' และซ่อนปุ่มอนุมัติทิ้งไป
                            quoteTable.cell(targetRow, 7).data('approved').draw(false);

                            // 3. ซ่อนปุ่มอนุมัติออก เพื่อไม่ให้กดซ้ำ
                            $(`button[onclick="approveQuote(${id})"]`).fadeOut();

                            // จบครับจาร ไม่ต้องมี location.reload(); หน้าจอจะไม่ขาวแน่นอน
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
</script>
<?php include 'footer.php'; ?>