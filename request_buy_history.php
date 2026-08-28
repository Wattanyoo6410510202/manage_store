<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

$sql = "SELECT 
            p.*, 
            p.reject_reason as reject_reason,
            s.company_name as supplier_name,
            st.store_name as store_name,
            IF(p.is_internal = 1, u1.name, c.customer_name) AS display_requester,
            u_creator.name AS creator_real_name, 
            u_creator.role AS creator_role,
            u2.name as approver_name,
            u_app0.name as approver_0_name,
            u_app1.name as approver_1_name,
            u_app2.name as approver_2_name,
            u_app3.name as approver_3_name,
            po.id as po_id,
            po.doc_no as po_doc_no,
            (SELECT item_desc FROM pr_items 
             WHERE pr_id = p.id 
             ORDER BY id ASC LIMIT 1) as first_item_desc
        FROM pr p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN stores st ON p.store_id = st.id
        LEFT JOIN customers c ON p.customer_id = c.id AND p.is_internal = 0
        LEFT JOIN users u1 ON p.created_by = u1.id AND p.is_internal = 1
        LEFT JOIN users u_creator ON p.created_by = u_creator.id
        LEFT JOIN users u2 ON p.approved_by = u2.id
        LEFT JOIN users u_app0 ON p.approved_by_0 = u_app0.id
        LEFT JOIN users u_app1 ON p.approved_by_1 = u_app1.id
        LEFT JOIN users u_app2 ON p.approved_by_2 = u_app2.id
        LEFT JOIN users u_app3 ON p.approved_by_3 = u_app3.id
        LEFT JOIN po ON po.reference_no = p.doc_no AND po.deleted_at IS NULL
        WHERE p.deleted_at IS NULL AND p.created_by = '{$_SESSION['user_id']}' AND p.is_internal = 1
        ORDER BY FIELD(p.status, 'pending', 'approved') ASC, p.created_at DESC";

$result = mysqli_query($conn, $sql);
$pr_list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $pr_list[] = $row;
}

$user_role_sup = $_SESSION['role'] ?? '';
$sup_id = $_SESSION['sup_id'] ?? 0;
$auto_filter_supplier = '';

$is_staff_or_acc = (strpos($user_role_sup, 'staff') === 0 || $user_role_sup === 'acc');

if ($is_staff_or_acc && !empty($sup_id) && $sup_id > 0) {
    $supplier_sql = "SELECT id, company_name FROM suppliers WHERE id = $sup_id ORDER BY company_name ASC";
    $sup_name_query = mysqli_query($conn, "SELECT company_name FROM suppliers WHERE id = $sup_id LIMIT 1");
    if ($sup_row = mysqli_fetch_assoc($sup_name_query)) {
        $auto_filter_supplier = $sup_row['company_name'];
    }
} else {
    $supplier_sql = "SELECT id, company_name FROM suppliers ORDER BY company_name ASC";
}
$supplier_res = mysqli_query($conn, $supplier_sql);
$suppliers = mysqli_fetch_all($supplier_res, MYSQLI_ASSOC);
?>

<div class="w-full p-0">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4">
            <!-- Filter Section (Responsive) -->
            <div class="grid grid-cols-2 md:flex md:flex-wrap items-end gap-3 mb-4 w-full">
                <div class="relative col-span-2 md:col-span-1 md:min-w-[180px]">
                    <label
                        class="text-[10px] md:text-[12px] font-bold text-slate-800 uppercase mb-1 block ml-1">หน่วยงาน/บริษัท</label>
                    <select id="filterSupplier"
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                        <?php if (!$is_staff_or_acc || empty($auto_filter_supplier)): ?>
                            <option value="">ทั้งหมด (Show All)</option>
                        <?php endif; ?>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= htmlspecialchars($s['company_name']) ?>"
                                <?= ($auto_filter_supplier === $s['company_name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(supplier_display_name($s['company_name']), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="relative">
                    <label
                        class="text-[10px] md:text-[12px] font-bold text-slate-800 uppercase mb-1 block ml-1">ตั้งแต่วันที่</label>
                    <input type="date" id="minDate"
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                </div>
                <div class="relative">
                    <label
                        class="text-[10px] md:text-[12px] font-bold text-slate-800 uppercase mb-1 block ml-1">ถึงวันที่</label>
                    <input type="date" id="maxDate"
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                </div>
                <div class="flex gap-2 col-span-2 md:col-span-1">
                    <button onclick="filterToday()"
                        class="flex-1 md:flex-none border border-indigo-100 px-3 py-2 rounded-lg text-[11px] md:text-[12px] text-slate-700 hover:bg-indigo-50 transition-all flex items-center justify-center gap-1 whitespace-nowrap">
                        <i class="fas fa-calendar-day text-indigo-500"></i> วันนี้
                    </button>
                    <button onclick="resetFilter()"
                        class="flex-1 md:flex-none border border-slate-200 px-3 py-2 rounded-lg text-[11px] md:text-[12px] text-slate-800 hover:bg-slate-50 transition-colors whitespace-nowrap">
                        <i class="fas fa-undo mr-1"></i> ล้าง
                    </button>
                </div>
                <div class="relative col-span-1">
                    <label
                        class="text-[10px] md:text-[12px] font-bold text-slate-800 uppercase mb-1 block ml-1">สถานะ</label>
                        <select id="filterStatus"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                            <option value="">ทั้งหมด</option>
                            <option value="รอเบิก">รอเบิก</option>
                            <option value="เบิกแล้ว">เบิกแล้ว</option>
                        </select>
                </div>
                <div id="bulkActions"
                    class="hidden p-1.5 bg-red-50 border border-red-100 rounded-lg flex items-center gap-3 transition-all animate-fade-in col-span-1 justify-between md:justify-start">
                    <span class="text-[11px] font-bold text-red-700 ml-2">เลือก <span id="selectedCount"
                            class="underline">0</span></span>
                    <button onclick="bulkDeletePR()"
                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-md text-[12px] font-bold shadow-sm transition-all flex items-center gap-2">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>

            <!-- Search Bar for Mobile Cards -->
            <div class="md:hidden mb-4 relative">
                <input type="text" id="mobileSearch" placeholder="ค้นหาเลขที่เอกสาร หรือรายละเอียด..."
                    class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            </div>

            <!-- Desktop View (DataTables) -->
            <div class="hidden md:block overflow-x-auto">
                <table id="prTable" class="w-full display hover border-none">
                    <thead>
                        <tr class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-800">
                            <th class="w-8 text-center !pr-2"><input type="checkbox" id="selectAll"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"></th>
                            <th>เลขที่เอกสาร</th>
                            <th>ความสำคัญ</th>
                            <th>หน่วยงาน</th>
                            <th>ร้านค้า</th>
                            <th>รายละเอียด</th>
                            <th class="text-center">ไฟล์แนบ</th>
                            <th class="text-right">ยอดรวม</th>
                            <th>สถานะ</th>
                            <th>รับของ</th>
                            <th>วันที่</th>
                            <th>หัวหน้างาน</th>
                            <th>จัดซื้อ</th>
                            <th>SUP</th>
                            <th>CEO</th>
                            <th class="text-center w-24">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-600">
                        <?php foreach ($pr_list as $row): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-50"
                                data-id="<?= $row['id'] ?>">
                                <td class="text-center">
                                    <input type="checkbox"
                                        class="pr-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        value="<?= $row['id'] ?>">
                                </td>
                                <td class="font-bold text-slate-800"><?= $row['doc_no'] ?></td>
                                <td>
                                    <?php
                                    $prio = $row['priority'] ?? 'ปานกลาง';
                                    $p_config = [
                                        'น้อย' => ['bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'border' => 'border-slate-100'],
                                        'ปานกลาง' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-100'],
                                        'เร่งด่วน' => ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'border' => 'border-orange-100'],
                                        'เร่งสุดขีด' => ['bg' => 'bg-red-50', 'text' => 'text-red-600', 'border' => 'border-red-100'],
                                    ];
                                    $p_style = $p_config[$prio] ?? $p_config['ปานกลาง'];
                                    ?>
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $p_style['bg'] ?> <?= $p_style['border'] ?> <?= $p_style['text'] ?>">
                                        <?= $prio ?>
                                    </span>
                                </td>
                                <td data-search="<?= htmlspecialchars(trim(($row['supplier_name'] ?: '-') . ' ' . supplier_display_name($row['supplier_name'] ?: '-')), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="font-semibold text-slate-700 truncate max-w-[150px]">
                                        <?= htmlspecialchars(supplier_display_name($row['supplier_name'] ?: '-'), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-700 truncate max-w-[120px]">
                                        <?php if (!empty($row['store_name'])): ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded text-[11px] font-bold">
                                                <i class="fas fa-store-alt text-[9px]"></i>
                                                <?= htmlspecialchars($row['store_name']) ?>
                                            </span>
                                        <?php else:
                                            echo '-';
                                        endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-700 truncate max-w-[250px]"
                                        title="<?= htmlspecialchars($row['first_item_desc'] ?? '') ?>">
                                        <?= htmlspecialchars($row['first_item_desc'] ?: 'ไม่มีรายละเอียดสินค้า') ?>
                                    </div>
                                    <?php if (!empty($row['reject_reason'])): ?>
                                        <div class="mt-1.5 p-2 bg-red-50 border border-red-100 rounded-lg max-w-[250px]">
                                            <span
                                                class="text-[9px] font-bold text-red-500 uppercase flex items-center gap-1"><i
                                                    class="fas fa-times-circle text-[8px]"></i>เหตุผลที่ปฏิเสธ</span>
                                            <p class="text-[11px] text-red-700 mt-0.5 leading-snug"><?= htmlspecialchars($row['reject_reason']) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($row['attachment_1']) || !empty($row['attachment_2'])): ?>
                                        <div class="flex justify-center gap-1">
                                            <?php if (!empty($row['attachment_1'])): ?>
                                                <button
                                                    onclick="viewAttachment('uploads/pr/<?= htmlspecialchars($row['attachment_1']) ?>')"
                                                    class="w-7 h-7 flex items-center justify-center bg-red-50 text-red-500 rounded-md border border-red-100 hover:bg-red-100 transition-all"
                                                    title="ไฟล์แนบ 1"><i class="fas fa-file-pdf text-xs"></i></button>
                                            <?php endif; ?>
                                            <?php if (!empty($row['attachment_2'])): ?>
                                                <button
                                                    onclick="viewAttachment('uploads/pr/<?= htmlspecialchars($row['attachment_2']) ?>')"
                                                    class="w-7 h-7 flex items-center justify-center bg-red-50 text-red-500 rounded-md border border-red-100 hover:bg-red-100 transition-all"
                                                    title="ไฟล์แนบ 2"><i class="fas fa-file-pdf text-xs"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else:
                                        echo '-';
                                    endif; ?>
                                </td>
                                <td>
                                    <div class="flex flex-col items-end gap-1">
                                        <div class="flex items-center gap-1.5 opacity-80">
                                            <span
                                                class="text-[8px] font-bold text-slate-800 uppercase tracking-tighter">Subtotal</span>
                                            <i class="fas fa-calculator text-[9px] text-slate-300"></i>
                                            <span
                                                class="text-[12px] text-slate-500 font-mono font-medium"><?= number_format($row['subtotal'], 2) ?></span>
                                        </div>
                                        <div
                                            class="flex items-center gap-1.5 bg-slate-50 px-2 py-1 rounded-md border border-slate-100 shadow-sm">
                                            <span
                                                class="text-[9px] font-black text-indigo-500 uppercase tracking-widest">Net</span>
                                            <i class="fas fa-coins text-[12px] text-amber-500"></i>
                                            <span
                                                class="text-[14px] font-mono font-black text-slate-900 leading-none"><?= number_format($row['grand_total'], 2) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td data-order="<?= $row['status'] === 'approved' ? '2' : '1' ?>">
                                    <?php
                                    $status = $row['status'] ?: 'pending';
                                    $config = [
                                        'pending' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100', 'dot' => 'bg-amber-400', 'label' => 'รอเบิก'],
                                        'approved' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100', 'dot' => 'bg-emerald-400', 'label' => 'เบิกแล้ว'],
                                    ];
                                    $style = $config[$status] ?? $config['pending'];
                                    ?>
                                    <div
                                        class="status-badge inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border <?= $style['bg'] ?> <?= $style['border'] ?> <?= $style['text'] ?> shadow-sm">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $style['dot'] ?> animate-pulse"></span>
                                        <span
                                            class="text-[12px] font-bold uppercase tracking-wide"><?= $style['label'] ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $rs = $row['received_status'] ?? 'pending';
                                    $r_config = [
                                        'pending' => ['bg' => 'bg-slate-50', 'text' => 'text-slate-400', 'border' => 'border-slate-100', 'dot' => 'bg-slate-300', 'label' => 'รอรับ'],
                                        'received' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100', 'dot' => 'bg-emerald-400', 'label' => 'รับแล้ว'],
                                        'partial' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100', 'dot' => 'bg-amber-400', 'label' => 'รับบางส่วน'],
                                    ];
                                    $r_style = $r_config[$rs] ?? $r_config['pending'];
                                    ?>
                                    <div class="flex flex-col items-center gap-1">
                                        <div
                                            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border <?= $r_style['bg'] ?> <?= $r_style['border'] ?> <?= $r_style['text'] ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $r_style['dot'] ?>"></span>
                                            <span
                                                class="text-[10px] font-bold uppercase tracking-wide"><?= $r_style['label'] ?></span>
                                        </div>
                                        <?php if ($row['status'] === 'approved' && $rs !== 'received'): ?>
                                            <button onclick="openReceiveModal(<?= $row['id'] ?>)"
                                                class="mt-1 px-2 py-1 bg-emerald-500 hover:bg-emerald-600 text-white rounded-md text-[9px] font-bold transition-all flex items-center gap-1 shadow-sm">
                                                <i class="fas fa-check-double text-[8px]"></i> รับของ
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($rs === 'received' || $rs === 'partial'): ?>
                                            <a href="view_receiving.php?pr_id=<?= $row['id'] ?>"
                                                class="mt-1 px-2 py-1 bg-indigo-500 hover:bg-indigo-600 text-white rounded-md text-[9px] font-bold transition-all flex items-center gap-1 shadow-sm">
                                                <i class="fas fa-print text-[8px]"></i> พิมพ์ใบรับของ
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-order="<?= $row['created_at'] ?>">
                                    <div class="flex flex-col">
                                        <span class="text-[14px] font-bold text-slate-700 mt-0.5 flex items-center gap-1"><i
                                                class="fas fa-calendar-alt text-[9px]"></i><?= date('d/m/y', strtotime($row['created_at'])) ?></span>
                                        <?php if (!empty($row['updated_at'])): ?>
                                            <span class="text-[9px] text-slate-500 mt-0.5 flex items-center gap-1"><i
                                                    class="fas fa-history text-[8px]"></i><?= date('d/m/y', strtotime($row['updated_at'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($row['reject_reason'])): ?>
                                        <i class="fas fa-times text-red-500"></i>
                                        <?php if (!empty($row['approved_at_0'])): ?>
                                            <div class="text-[8px] text-slate-400 font-mono">
                                                <?= date('d/m/y', strtotime($row['approved_at_0'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif (!empty($row['approver_0_name'])): ?>
                                        <i class="fas fa-check text-emerald-500"></i>
                                        <div class="text-[8px] text-slate-400 font-mono">
                                            <?= date('d/m/y', strtotime($row['approved_at_0'])) ?>
                                        </div>
                                    <?php else:
                                        echo '-';
                                    endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($row['approver_name'])): ?>
                                        <i class="fas fa-check text-emerald-500"></i>
                                        <div class="text-[8px] text-slate-400 font-mono">
                                            <?= date('d/m/y', strtotime($row['approved_at'])) ?>
                                        </div>
                                    <?php else:
                                        echo '-';
                                    endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($row['approver_2_name'])): ?>
                                        <i class="fas fa-check text-emerald-500"></i>
                                        <div class="text-[8px] text-slate-400 font-mono">
                                            <?= date('d/m/y', strtotime($row['approved_at_2'])) ?>
                                        </div>
                                    <?php else:
                                        echo '-';
                                    endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($row['approver_3_name'])): ?><i
                                            class="fas fa-check text-emerald-500"></i><?php else:
                                        echo '-';
                                    endif; ?>
                                </td>
                                <td class="px-4">
                                    <div class="flex justify-center gap-1.5">
                                        <?php
                                        $can_edit = !is_viewer() && (empty($row['approved_by_0']) || $_SESSION['role'] === 'admin' || strpos($_SESSION['role'] ?? '', 'procure') === 0);
                                        $can_receive = ($row['status'] === 'approved' && $row['received_status'] !== 'received');
                                        ?>
                                        <?php if ($can_receive): ?>
                                            <button onclick="openReceiveModal(<?= $row['id'] ?>)"
                                                class="w-8 h-8 flex items-center justify-center bg-emerald-500 text-white rounded-lg border border-emerald-500 shadow-sm hover:bg-emerald-600 transition-all"
                                                title="รับของแล้ว">
                                                <i class="fas fa-check-double text-xs"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($rs === 'received' || $rs === 'partial'): ?>
                                            <a href="view_receiving.php?pr_id=<?= $row['id'] ?>"
                                                class="w-8 h-8 flex items-center justify-center bg-indigo-500 text-white rounded-lg border border-indigo-500 shadow-sm hover:bg-indigo-600 transition-all"
                                                title="พิมพ์ใบรับของ">
                                                <i class="fas fa-print text-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="view_pr_new.php?id=<?= $row['id'] ?>"
                                            class="w-8 h-8 flex items-center justify-center bg-white text-slate-800 rounded-lg border border-slate-200 shadow-sm"><i
                                                class="fas fa-eye text-xs"></i></a>

                                        <?php if ($can_edit): ?>
                                            <?php if ($row['status'] === 'pending' || (!empty($row['reject_reason']) && $row['allow_resubmit'] == 1)): ?>
                                                <a href="edit_pr_new.php?id=<?= $row['id'] ?>"
                                                    class="w-8 h-8 flex items-center justify-center bg-white text-slate-800 rounded-lg border border-slate-200 shadow-sm"><i
                                                        class="fas fa-edit text-xs"></i></a>
                                            <?php endif; ?>
                                            <button onclick="deletePR(<?= $row['id'] ?>)"
                                                class="w-8 h-8 flex items-center justify-center bg-white text-slate-800 rounded-lg border border-slate-200 shadow-sm"><i
                                                    class="fas fa-trash text-xs"></i></button>
                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View (Native Cards) -->
            <div id="mobileCardContainer" class="md:hidden grid grid-cols-1 gap-4">
                <!-- Cards will be rendered via JS -->
            </div>
            <div id="noDataMobile" class="hidden md:hidden py-12 text-center text-slate-400">
                <i class="fas fa-folder-open text-4xl mb-2 opacity-20"></i>
                <p class="text-sm font-medium">ไม่พบประวัติใบขอซื้อ</p>
            </div>
        </div>
    </div>
</div>

<script>
    const PR_DATA = <?= json_encode($pr_list, JSON_UNESCAPED_UNICODE) ?>;
    const AUTO_FILTER_SUPPLIER = <?= json_encode($auto_filter_supplier, JSON_UNESCAPED_UNICODE) ?>;
    const IS_VIEWER = <?= json_encode(is_viewer()) ?>;
    const USER_ROLE = <?= json_encode($_SESSION['role'] ?? '') ?>;

    let prTable;

    $(document).ready(function () {
        prTable = $('#prTable').DataTable({
            "pageLength": 10,
            "dom": '<"flex flex-col md:flex-row justify-between items-center gap-3 mb-4"lf>rt<"flex flex-col md:flex-row justify-between items-center gap-3 mt-4"ip>',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json" },
            "order": [[8, "asc"]],
            "columnDefs": [
                { "orderable": false, "targets": [0, 5, 6, 9, 11, 12, 13, 14, 15] },
                { "type": "html", "targets": [7, 9, 11, 12, 13, 14] }
            ],
            "drawCallback": function () { updateBulkUI(); }
        });

        if (AUTO_FILTER_SUPPLIER) {
            $('#filterSupplier').val(AUTO_FILTER_SUPPLIER);
            prTable.column(3).search(AUTO_FILTER_SUPPLIER).draw();
        }

        // Sync Filters
        $('#filterSupplier').on('change', function () {
            prTable.column(3).search(this.value).draw();
            renderMobileCards();
        });
        $('#filterStatus').on('change', function () {
            prTable.column(8).search(this.value).draw();
            renderMobileCards();
        });
        $('#minDate, #maxDate, #mobileSearch').on('input change', () => {
            prTable.draw();
            renderMobileCards();
        });

        // DataTables Custom Date Search
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            let min = $('#minDate').val();
            let max = $('#maxDate').val();
            let dateStr = data[10] || "";
            if (dateStr === "") return true;
            let match = dateStr.match(/(\d{2})\/(\d{2})\/(\d{2})/);
            if (!match) return true;
            let dateFormatted = `20${match[3]}-${match[2]}-${match[1]}`;
            if ((min === "" && max === "") || (min === "" && dateFormatted <= max) || (min <= dateFormatted && max === "") || (min <= dateFormatted && dateFormatted <= max)) return true;
            return false;
        });

        $('#selectAll').on('change', function () { $('.pr-checkbox').prop('checked', this.checked); updateBulkUI(); });
        $(document).on('change', '.pr-checkbox', updateBulkUI);

        // Initial Render Mobile
        renderMobileCards();
    });

    function renderMobileCards() {
        const container = $('#mobileCardContainer');
        const search = $('#mobileSearch').val().toLowerCase();
        const supplier = $('#filterSupplier').val();
        const statusVal = $('#filterStatus').val();
        const min = $('#minDate').val();
        const max = $('#maxDate').val();

        let filtered = PR_DATA.filter(item => {
            const textMatch = (item.doc_no.toLowerCase().includes(search) || (item.first_item_desc || '').toLowerCase().includes(search) || (item.supplier_name || '').toLowerCase().includes(search));
            if (!textMatch) return false;
            if (supplier && item.supplier_name !== supplier) return false;
            const status = item.status || 'pending';
            const mappedStatus = (status === 'pending' ? 'รอเบิก' : 'เบิกแล้ว');
            if (statusVal && mappedStatus !== statusVal) return false;
            if (min || max) {
                const dateVal = item.created_at.split(' ')[0];
                if (min && dateVal < min) return false;
                if (max && dateVal > max) return false;
            }
            return true;
        });

        filtered.sort((a, b) => {
            const order = { pending: 1, approved: 2 };
            return (order[a.status] || 1) - (order[b.status] || 1);
        });

        if (filtered.length === 0) {
            container.empty();
            $('#noDataMobile').removeClass('hidden');
            return;
        }

        $('#noDataMobile').addClass('hidden');
        let html = '';
        filtered.forEach(row => {
            const prio = row.priority || 'ปานกลาง';
            const p_config = {
                'น้อย': { bg: 'bg-slate-50', text: 'text-slate-600', border: 'border-slate-100' },
                'ปานกลาง': { bg: 'bg-blue-50', text: 'text-blue-600', border: 'border-blue-100' },
                'เร่งด่วน': { bg: 'bg-orange-50', text: 'text-orange-600', border: 'border-orange-100' },
                'เร่งสุดขีด': { bg: 'bg-red-50', text: 'text-red-600', border: 'border-red-100' },
            };
            const p_style = p_config[prio] || p_config['ปานกลาง'];

            const status = row.status || 'pending';
            const s_config = {
                'pending': { bg: 'bg-amber-50', text: 'text-amber-600', border: 'border-amber-100', dot: 'bg-amber-400', label: 'รอเบิก' },
                'approved': { bg: 'bg-emerald-50', text: 'text-emerald-600', border: 'border-emerald-100', dot: 'bg-emerald-400', label: 'เบิกแล้ว' },
            };
            const s_style = s_config[status] || s_config['pending'];

            const rs = row.received_status || 'pending';
            const r_config = {
                'pending': { bg: 'bg-slate-50', text: 'text-slate-400', border: 'border-slate-100', dot: 'bg-slate-300', label: 'รอรับ' },
                'received': { bg: 'bg-emerald-50', text: 'text-emerald-600', border: 'border-emerald-100', dot: 'bg-emerald-400', label: 'รับแล้ว' },
                'partial': { bg: 'bg-amber-50', text: 'text-amber-600', border: 'border-amber-100', dot: 'bg-amber-400', label: 'รับบางส่วน' },
            };
            const r_style = r_config[rs] || r_config['pending'];

            html += `
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden animate-fade-in" data-mobile-id="${row.id}">
                <div class="p-4 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" class="pr-checkbox w-5 h-5 rounded border-slate-300 text-indigo-600" value="${row.id}">
                        <span class="font-black text-slate-800">${row.doc_no}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="status-badge inline-flex items-center gap-1 px-2 py-0.5 rounded-full border ${r_style.bg} ${r_style.border} ${r_style.text} shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full ${r_style.dot}"></span>
                            <span class="text-[8px] font-bold uppercase tracking-widest">${r_style.label}</span>
                        </div>
                        <div class="status-badge inline-flex items-center gap-1.5 px-3 py-1 rounded-full border ${s_style.bg} ${s_style.border} ${s_style.text} shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full ${s_style.dot} animate-pulse"></span>
                            <span class="text-[10px] font-black uppercase tracking-widest">${s_style.label}</span>
                        </div>
                    </div>
                </div>
                <div class="p-4 space-y-4">
                    <div class="flex justify-between items-start">
                        <div class="space-y-1">
                            <span class="text-[9px] text-slate-400 uppercase font-black tracking-widest block">หน่วยงาน/บริษัท</span>
                            <span class="text-sm font-bold text-slate-700">${formatSupplierDisplayName(row.supplier_name || '-')}</span>
                        </div>
                        <span class="px-3 py-1 rounded-full text-[10px] font-black border ${p_style.bg} ${p_style.border} ${p_style.text} uppercase tracking-wider">${prio}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <span class="text-[9px] text-slate-400 uppercase font-black tracking-widest block mb-1">รายละเอียด</span>
                        <p class="text-xs text-slate-600 leading-relaxed line-clamp-2">${row.first_item_desc || 'ไม่มีรายละเอียดสินค้า'}</p>
                    </div>
                    ${row.reject_reason ? `
                        <div class="p-3 rounded-xl border border-red-100 bg-red-50">
                            <span class="text-[9px] text-red-500 uppercase font-black tracking-widest block mb-1"><i class="fas fa-times-circle mr-1"></i>เหตุผลที่ปฏิเสธ</span>
                            <p class="text-xs text-red-700 leading-relaxed">${row.reject_reason}</p>
                        </div>
                    ` : ''}
                    <div class="flex justify-between items-end">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-1.5 text-slate-400">
                                <i class="fas fa-calendar-alt text-[10px]"></i>
                                <span class="text-[10px]">${new Date(row.created_at).toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit', year: '2-digit' })}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] text-slate-400 uppercase font-black tracking-widest block mb-0.5">ยอดรวมสุทธิ</span>
                            <span class="text-lg font-black text-indigo-600">฿${parseFloat(row.grand_total).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-2 bg-slate-50 flex justify-between border-t border-slate-100">
                    <div class="flex items-center gap-4">
                         <div class="flex flex-col items-center gap-1">
                            <span class="text-[7px] text-slate-400 font-black uppercase">หัวหน้า</span>
                            ${row.reject_reason ? '<i class="fas fa-times-circle text-red-500 text-xs"></i>' : (row.approver_0_name ? '<i class="fas fa-check-circle text-emerald-500 text-xs"></i>' : '<i class="far fa-circle text-slate-300 text-xs"></i>')}
                         </div>
                         <div class="flex flex-col items-center gap-1">
                            <span class="text-[7px] text-slate-400 font-black uppercase">จัดซื้อ</span>
                            ${row.approver_name ? '<i class="fas fa-check-circle text-emerald-500 text-xs"></i>' : '<i class="far fa-circle text-slate-300 text-xs"></i>'}
                         </div>
                         <div class="flex flex-col items-center gap-1">
                            <span class="text-[7px] text-slate-400 font-black uppercase">SUP</span>
                            ${row.approver_2_name ? '<i class="fas fa-check-circle text-emerald-500 text-xs"></i>' : '<i class="far fa-circle text-slate-300 text-xs"></i>'}
                         </div>
                         <div class="flex flex-col items-center gap-1">
                            <span class="text-[7px] text-slate-400 font-black uppercase">CEO</span>
                            ${row.approver_3_name ? '<i class="fas fa-check-circle text-emerald-500 text-xs"></i>' : '<i class="far fa-circle text-slate-300 text-xs"></i>'}
                         </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="view_pr_new.php?id=${row.id}" class="w-9 h-9 flex items-center justify-center bg-indigo-600 text-white rounded-xl shadow-md shadow-indigo-200"><i class="fas fa-eye text-xs"></i></a>
                        ${row.status === 'approved' && row.received_status !== 'received' ? `
                            <button onclick="openReceiveModal(${row.id})" class="w-9 h-9 flex items-center justify-center bg-emerald-500 text-white rounded-xl border border-emerald-500 shadow-sm hover:bg-emerald-600 transition-all"><i class="fas fa-check-double text-xs"></i></button>
                        ` : ''}
                        ${row.received_status === 'received' || row.received_status === 'partial' ? `
                            <a href="view_receiving.php?pr_id=${row.id}" class="w-9 h-9 flex items-center justify-center bg-indigo-500 text-white rounded-xl border border-indigo-500 shadow-sm hover:bg-indigo-600 transition-all"><i class="fas fa-print text-xs"></i></a>
                        ` : ''}
                        ${!IS_VIEWER && (!row.approved_by_0 || USER_ROLE === 'admin' || USER_ROLE === 'procure') ? `
                            ${(row.status === 'pending' || (row.reject_reason && row.allow_resubmit == 1)) ? `<a href="edit_pr_new.php?id=${row.id}" class="w-9 h-9 flex items-center justify-center bg-white text-amber-500 rounded-xl border border-amber-100 shadow-sm"><i class="fas fa-edit text-xs"></i></a>` : ''}
                            <button onclick="deletePR(${row.id})" class="w-9 h-9 flex items-center justify-center bg-white text-red-500 rounded-xl border border-red-100 shadow-sm"><i class="fas fa-trash text-xs"></i></button>
                        ` : ''}
                    </div>
                </div>
            </div>`;
        });
        container.html(html);
    }

    function updateBulkUI() {
        const checkedCount = $('.pr-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#bulkActions').removeClass('hidden').addClass('flex');
            $('#selectedCount').text(checkedCount);
        } else {
            $('#bulkActions').addClass('hidden').removeClass('flex');
            $('#selectAll').prop('checked', false);
        }
    }

    function renderAlert(type, customMsg = '') {
        const configs = {
            'success': { msg: customMsg || "ทำรายการสำเร็จ", color: "#10b981", icon: "bi-check-lg" },
            'delete': { msg: customMsg || "ย้ายรายการไปที่ถังขยะเรียบร้อย", color: "#f43f5e", icon: "bi-trash3" },
            'error': { msg: customMsg || "เกิดข้อผิดพลาด", color: "#64748b", icon: "bi-x-circle" }
        };
        const config = configs[type] || configs['error'];
        const existingAlerts = $('.custom-alert').length;
        const topPosition = 25 + (existingAlerts * 85);
        const alertHtml = `<div class="custom-alert shadow-lg" style="color: ${config.color}; position: fixed; top: ${topPosition}px; right: 25px; z-index: 10000; width: 100%; max-width: 380px; display: flex; align-items: center; transition: all 0.5s; background: white; padding: 15px; border-radius: 12px; border-left: 5px solid ${config.color}"><div style="background-color: ${config.color}20; padding: 10px; border-radius: 10px; margin-right: 15px;"><i class="bi ${config.icon} fs-4"></i></div><div class="flex-grow-1"><div class="text-dark fw-bold mb-0" style="font-size: 0.95rem;">${config.msg}</div></div><button type="button" class="btn-close" onclick="$(this).closest('.custom-alert').remove()"></button></div>`;
        const $alert = $(alertHtml).hide().appendTo('body').fadeIn(300);
        setTimeout(() => $alert.fadeOut(300, function () { $(this).remove(); }), 4500);
    }

    function deletePR(id) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: `ย้ายใบขอซื้อนี้ไปยังถังขยะ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'ยืนยันการลบ',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true,
            heightAuto: false
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`api/delete_pr.php?id=${id}`).then(res => res.json()).then(res => {
                    if (res.status === 'success') {
                        location.reload();
                    } else renderAlert('error', res.message);
                });
            }
        });
    }

    function bulkDeletePR() {
        const ids = [];
        $('.pr-checkbox:checked').each(function () { ids.push($(this).val()); });
        if (ids.length === 0) return;

        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: `ย้ายใบขอซื้อที่เลือกทั้งหมด ${ids.length} รายการไปยังถังขยะ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'ยืนยันการลบ',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true,
            heightAuto: false
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'bulk_delete');
                fd.append('ids', JSON.stringify(ids));
                fetch('api/delete_pr.php', { method: 'POST', body: fd }).then(res => res.json()).then(res => {
                    if (res.status === 'success') {
                        location.reload();
                    } else renderAlert('error', res.message);
                });
            }
        });
    }

    function filterToday() {
        const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Bangkok' });
        $('#minDate').val(today); $('#maxDate').val(today);
        prTable.draw();
        renderMobileCards();
    }

    function resetFilter() {
        $('#filterSupplier').val(AUTO_FILTER_SUPPLIER || '');
        $('#filterStatus, #minDate, #maxDate, #mobileSearch').val('');
        prTable.column(3).search(AUTO_FILTER_SUPPLIER || '');
        prTable.column(8).search('');
        prTable.draw();
        renderMobileCards();
    }

    let currentReceivePRId = 0;

    function openReceiveModal(prId) {
        currentReceivePRId = prId;
        const btn = document.querySelector(`#receiveModalBtn`);
        if (btn) btn.disabled = false;

        document.getElementById('receiveModal').classList.remove('hidden');
        document.getElementById('receiveModal').style.display = 'flex';
        document.getElementById('receiveModalBody').innerHTML = `
            <tr><td colspan="7" class="text-center py-8 text-slate-400">
                <i class="fas fa-spinner fa-spin text-2xl"></i><br>กำลังโหลดข้อมูล...
            </td></tr>`;

        fetch(`api/get_receive_data.php?pr_id=${prId}`)
            .then(res => res.json())
            .then(res => {
                if (res.status !== 'success') {
                    document.getElementById('receiveModalBody').innerHTML = `
                        <tr><td colspan="7" class="text-center py-8 text-red-500">${res.message}</td></tr>`;
                    return;
                }

                const pr = res.pr;
                const po = res.po;
                const prItems = res.pr_items;
                const poItems = res.po_items;
                const hasPo = po && po.id;

                document.getElementById('receivePrDoc').textContent = pr.doc_no;
                document.getElementById('receiveSupplier').textContent = formatSupplierDisplayName(pr.supplier_name || '-');
                document.getElementById('receivePoDoc').textContent = hasPo ? po.doc_no : '-';

                let rows = '';
                const useItems = poItems.length > 0 ? poItems : prItems;

                useItems.forEach((item, idx) => {
                    const oqty = parseFloat(item.item_qty || item.ordered_qty || 0);
                    rows += `
                    <tr>
                        <td class="text-center text-slate-500">${idx + 1}</td>
                        <td class="text-slate-700">${item.item_desc || '-'}</td>
                        <td class="text-center font-semibold text-slate-700">${oqty.toLocaleString()}</td>
                        <td class="text-center text-slate-500">${item.item_unit || '-'}</td>
                        <td>
                            <input type="number" class="receive-qty-input w-20 px-2 py-1 border border-slate-200 rounded-lg text-center font-semibold text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                                value="${oqty}" min="0" step="0.01"
                                data-ordered="${oqty}"
                                oninput="calcReceiveDiff(this)">
                        </td>
                        <td>
                            <span class="receive-diff text-sm font-bold text-emerald-600">0</span>
                        </td>
                        <td>
                            <input type="text" class="receive-reason-input w-full px-2 py-1 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 hidden"
                                placeholder="ระบุเหตุผล..." maxlength="255">
                        </td>
                    </tr>`;
                });

                rows += `<input type="hidden" id="receivePoId" value="${hasPo ? po.id : 0}">`;

                document.getElementById('receiveModalBody').innerHTML = rows;

                if (!hasPo) {
                    document.getElementById('receivePoWarning').classList.remove('hidden');
                } else {
                    document.getElementById('receivePoWarning').classList.add('hidden');
                }
            })
            .catch(err => {
                document.getElementById('receiveModalBody').innerHTML = `
                    <tr><td colspan="6" class="text-center py-8 text-red-500">เกิดข้อผิดพลาด: ${err.message}</td></tr>`;
            });
    }

    function calcReceiveDiff(input) {
        const ordered = parseFloat(input.dataset.ordered) || 0;
        const received = parseFloat(input.value) || 0;
        const diff = received - ordered;
        const tr = input.closest('tr');
        const diffEl = tr.querySelector('.receive-diff');
        diffEl.textContent = diff.toFixed(2);
        const reasonInput = tr.querySelector('.receive-reason-input');
        if (diff < 0) {
            diffEl.className = 'receive-diff text-sm font-bold text-red-500';
            reasonInput.classList.remove('hidden');
        } else {
            diffEl.className = 'receive-diff text-sm font-bold ' + (diff > 0 ? 'text-amber-500' : 'text-emerald-600');
            reasonInput.classList.add('hidden');
            reasonInput.value = '';
        }
    }

    function closeReceiveModal() {
        document.getElementById('receiveModal').style.display = 'none';
        document.getElementById('receiveModal').classList.add('hidden');
    }

    function submitReceive() {
        const btn = document.getElementById('receiveModalBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

        const prId = currentReceivePRId;
        const poId = parseInt(document.getElementById('receivePoId')?.value || 0);
        const note = document.getElementById('receiveNote').value;
        const rows = document.querySelectorAll('#receiveModalBody tr');
        const items = [];

        rows.forEach(row => {
            const qtyInput = row.querySelector('.receive-qty-input');
            if (!qtyInput) return;
            const ordered = parseFloat(qtyInput.dataset.ordered) || 0;
            const received = parseFloat(qtyInput.value) || 0;
            const diff = received - ordered;
            const reasonInput = row.querySelector('.receive-reason-input');
            const cells = row.querySelectorAll('td');
            items.push({
                item_desc: cells.length > 1 ? cells[1].textContent.trim() : '',
                item_unit: cells.length > 3 ? cells[3].textContent.trim() : '',
                ordered_qty: ordered,
                received_qty: received,
                diff_qty: diff,
                reason: reasonInput ? reasonInput.value : ''
            });
        });

        const formData = new FormData();
        formData.append('pr_id', prId);
        formData.append('po_id', poId);
        formData.append('note', note);
        formData.append('items', JSON.stringify(items));

        fetch('api/receive_pr_detail.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    closeReceiveModal();
                    renderAlert('success', res.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    renderAlert('error', res.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-double"></i> ยืนยันรับของ';
                }
            })
            .catch(err => {
                renderAlert('error', 'เกิดข้อผิดพลาด: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-double"></i> ยืนยันรับของ';
            });
    }

    function viewAttachment(url) { window.open(url, '_blank'); }
</script>

<!-- Receive Modal -->
<div id="receiveModal" class="hidden"
    style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;"
    onclick="if(event.target===this)closeReceiveModal()">
    <div style="background: white; border-radius: 16px; width: 95%; max-width: 800px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3);"
        onclick="event.stopPropagation()">

        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #e2e8f0; background: linear-gradient(135deg, #f0fdf4, #ecfdf5); border-radius: 16px 16px 0 0;">
            <div>
                <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #065f46; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-boxes" style="color: #10b981;"></i> รับของ
                </h3>
                <p style="margin: 4px 0 0; font-size: 12px; color: #64748b;">
                    PR: <strong id="receivePrDoc" style="color: #0f172a;">-</strong>
                    | ผู้จำหน่าย: <strong id="receiveSupplier" style="color: #0f172a;">-</strong>
                </p>
            </div>
            <button onclick="closeReceiveModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8; padding: 4px;">&times;</button>
        </div>

        <!-- PO Reference -->
        <div style="padding: 12px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-file-invoice" style="color: #6366f1; font-size: 14px;"></i>
            <span style="font-size: 12px; color: #475569; font-weight: 600;">อ้างอิงใบสั่งซื้อ (PO):</span>
            <span id="receivePoDoc" style="font-size: 13px; font-weight: 700; color: #0f172a;">-</span>
            <span id="receivePoWarning" class="hidden" style="margin-left: 8px; font-size: 11px; color: #f59e0b; background: #fffbeb; padding: 2px 10px; border-radius: 20px; border: 1px solid #fde68a;">
                <i class="fas fa-exclamation-triangle"></i> ไม่พบใบ PO
            </span>
        </div>

        <!-- Items Table -->
        <div style="padding: 16px 24px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f1f5f9; border-radius: 8px;">
                        <th style="padding: 8px 12px; text-align: center; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">#</th>
                        <th style="padding: 8px 12px; text-align: left; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">รายการ</th>
                        <th style="padding: 8px 12px; text-align: center; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">จำนวนสั่ง</th>
                        <th style="padding: 8px 12px; text-align: center; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">หน่วย</th>
                        <th style="padding: 8px 12px; text-align: center; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">รับจำนวน</th>
                        <th style="padding: 8px 12px; text-align: center; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">ส่วนต่าง</th>
                        <th style="padding: 8px 12px; text-align: center; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">เหตุผล</th>
                    </tr>
                </thead>
                <tbody id="receiveModalBody">
                    <tr><td colspan="7" class="text-center py-8 text-slate-400">กำลังโหลด...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Note -->
        <div style="padding: 0 24px 16px;">
            <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">หมายเหตุ / เหตุผล (ถ้าไม่ครบ)</label>
            <textarea id="receiveNote" rows="2"
                style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 13px; outline: none; resize: vertical;"
                placeholder="กรอกหมายเหตุ หรือสาเหตุที่ของไม่ครบ..."></textarea>
        </div>

        <!-- Actions -->
        <div style="display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-radius: 0 0 16px 16px;">
            <button onclick="closeReceiveModal()"
                style="padding: 10px 20px; border: 1px solid #e2e8f0; border-radius: 10px; background: white; color: #64748b; font-size: 13px; font-weight: 600; cursor: pointer;">
                ยกเลิก
            </button>
            <button id="receiveModalBtn" onclick="submitReceive()"
                style="padding: 10px 24px; border: none; border-radius: 10px; background: #10b981; color: white; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-check-double"></i> ยืนยันรับของ
            </button>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
