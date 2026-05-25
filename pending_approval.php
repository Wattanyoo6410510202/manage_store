<?php
require_once 'config.php';
include 'header.php';
include('assets/alert.php');

$sql = "SELECT 
            p.*, 
            s.company_name as supplier_name,
            IF(p.is_internal = 1, u1.name, c.customer_name) AS display_requester,
            u_creator.name AS creator_real_name, 
            u_creator.role AS creator_role,
            u_app0.name as approver_0_name,
            u2.name as approver_name,
            u_app1.name as approver_1_name,
            u_app2.name as approver_2_name,
            u_app3.name as approver_3_name,
            (SELECT item_desc FROM pr_items 
             WHERE pr_id = p.id 
             ORDER BY id ASC LIMIT 1) as first_item_desc
        FROM pr p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN customers c ON p.customer_id = c.id AND p.is_internal = 0
        LEFT JOIN users u1 ON p.created_by = u1.id AND p.is_internal = 1
        LEFT JOIN users u_creator ON p.created_by = u_creator.id
        LEFT JOIN users u_app0 ON p.approved_by_0 = u_app0.id
        LEFT JOIN users u2 ON p.approved_by = u2.id
        LEFT JOIN users u_app1 ON p.approved_by_1 = u_app1.id
        LEFT JOIN users u_app2 ON p.approved_by_2 = u_app2.id
        LEFT JOIN users u_app3 ON p.approved_by_3 = u_app3.id
        WHERE p.deleted_at IS NULL AND p.is_internal = 1
        ORDER BY p.created_at DESC";

$result = mysqli_query($conn, $sql);

$user_role_sup = $_SESSION['role'] ?? '';
$sup_id = $_SESSION['sup_id'] ?? 0;
$auto_filter_supplier = '';

$is_head_locked = ((strpos($user_role_sup, 'gm') === 0 && $user_role_sup !== 'gmacc') || $user_role_sup === 'hok');

if ($is_head_locked && !empty($sup_id) && $sup_id > 0) {
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
$isAdminOrProcure = ($_SESSION['role'] === 'admin' || strpos($_SESSION['role'], 'procure') === 0);
?>

<div class="w-full p-0">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4">
            <div class="overflow-x-auto">
                <style>
                    .no-scrollbar::-webkit-scrollbar { display: none; }
                    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
                </style>
                <div class="flex flex-nowrap md:flex-wrap items-center gap-3 mb-4 w-full overflow-x-auto no-scrollbar pb-1">
                    <div class="relative min-w-[180px] flex-shrink-0">
                        <label
                            class="text-[10px] md:text-[12px] font-bold text-slate-800 uppercase mb-1 block ml-1">หน่วยงาน/บริษัท</label>
                        <select id="filterSupplier"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                            <?php if (!$is_head_locked || empty($auto_filter_supplier)): ?>
                                <option value="">ทั้งหมด (Show All)</option>
                            <?php endif; ?>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= htmlspecialchars($s['company_name']) ?>" <?= ($auto_filter_supplier === $s['company_name']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="relative min-w-[130px] flex-shrink-0">
                        <label
                            class="text-[10px] md:text-[12px] font-bold text-slate-800 uppercase mb-1 block ml-1">ตั้งแต่วันที่</label>
                        <input type="date" id="minDate"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                    </div>
                    <div class="relative min-w-[130px] flex-shrink-0">
                        <label class="text-[10px] md:text-[12px] font-bold text-slate-800 uppercase mb-1 block ml-1">ถึงวันที่</label>
                        <input type="date" id="maxDate"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                    </div>
                    <div class="flex-shrink-0 self-end mb-[2px]">
                        <button onclick="filterToday()"
                            class="border border-indigo-100 px-3 py-2 rounded-lg text-[11px] md:text-[12px] text-slate-700 hover:bg-indigo-50 transition-all flex items-center gap-1 whitespace-nowrap">
                            <i class="fas fa-calendar-day text-indigo-500"></i> วันนี้
                        </button>
                    </div>
                    <div class="relative min-w-[110px] flex-shrink-0">
                        <label class="text-[10px] md:text-[12px] font-bold text-slate-800 uppercase mb-1 block ml-1">สถานะ</label>
                        <select id="filterStatus"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-all">
                            <option value="">ทั้งหมด</option>
                            <option value="รอ" selected>รออนุมัติ</option>
                            <option value="อนุมัติ">อนุมัติแล้ว</option>
                        </select>
                    </div>
                    <div class="flex-shrink-0 self-end mb-2.5">
                        <button onclick="resetFilter()"
                            class="text-[11px] md:text-[12px] text-slate-800 hover:text-indigo-600 transition-colors whitespace-nowrap">
                            <i class="fas fa-undo mr-1"></i> ล้าง
                        </button>
                    </div>
                    <div id="bulkActions"
                        class="hidden ml-auto self-end p-1.5 bg-red-50 border border-red-100 rounded-lg flex items-center gap-3 transition-all animate-fade-in flex-shrink-0">
                        <span class="text-[11px] font-bold text-red-700 ml-2">เลือกอยู่ <span id="selectedCount"
                                class="underline">0</span></span>
                        <button onclick="bulkDeletePR()"
                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-md text-[12px] font-bold shadow-sm transition-all flex items-center gap-2">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>

                <table id="prTable" class="w-full display hover border-none">
                    <thead>
                        <tr class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-800">
                            <th class="w-8 text-center !pr-2 hidden md:table-cell"><input type="checkbox" id="selectAll"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"></th>
                            <th class="table-cell md:hidden">รายการขอซื้อ (Mobile View)</th>
                            <th class="hidden md:table-cell">เลขที่เอกสาร</th>
                            <th class="hidden md:table-cell">ความสำคัญ</th>
                            <th class="hidden md:table-cell">หน่วยงาน</th>
                            <th class="hidden md:table-cell">รายละเอียด</th>
                            <th class="text-center hidden md:table-cell">ไฟล์แนบ</th>
                            <th class="text-right hidden md:table-cell">ยอดรวม</th>
                            <th class="hidden md:table-cell">สถานะจัดซื้อ</th>
                            <th class="hidden md:table-cell">วันที่</th>
                            <th class="hidden md:table-cell">ผู้สร้าง</th>
                            <th class="hidden md:table-cell">สถานะหัวหน้างาน</th>
                            <th class="hidden md:table-cell">หัวหน้า</th>
                            <th class="hidden md:table-cell">จัดซื้อ</th>
                            <th class="hidden md:table-cell">SUP</th>
                            <th class="hidden md:table-cell">CEO</th>
                            <th class="text-center w-24 hidden md:table-cell">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-600">
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-50"
                                data-id="<?= $row['id'] ?>">
                                <td class="text-center hidden md:table-cell">
                                    <input type="checkbox"
                                        class="pr-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        value="<?= $row['id'] ?>">
                                </td>
                                
                                <!-- Mobile Card View -->
                                <td class="table-cell md:hidden p-2">
                                    <?php
                                    $prio = $row['priority'] ?? 'ปานกลาง';
                                    $p_config = [
                                        'น้อย' => ['bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'border' => 'border-slate-100'],
                                        'ปานกลาง' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-100'],
                                        'เร่งด่วน' => ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'border' => 'border-orange-100'],
                                        'วิกฤต' => ['bg' => 'bg-red-50', 'text' => 'text-red-600', 'border' => 'border-red-100'],
                                    ];
                                    $p_style = $p_config[$prio] ?? $p_config['ปานกลาง'];

                                    $status = $row['status'] ?: 'pending';
                                    $status_config = [
                                        'pending' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100', 'dot' => 'bg-amber-400', 'label' => 'รอ'],
                                        'approved' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100', 'dot' => 'bg-emerald-400', 'label' => 'อนุมัติ'],
                                    ];
                                    $s_style = $status_config[$status] ?? $status_config['pending'];

                                    $can_approve = false;
                                    $role = $_SESSION['role'] ?? '';
                                    $user_id = $_SESSION['user_id'] ?? 0;
                                    $already_approved = (
                                        $row['approved_by_0'] == $user_id ||
                                        $row['approved_by'] == $user_id ||
                                        $row['approved_by_1'] == $user_id ||
                                        $row['approved_by_2'] == $user_id ||
                                        $row['approved_by_3'] == $user_id
                                    );
                                    if ($row['status'] === 'pending' && !$already_approved && !is_viewer()) {
                                        $dept_map = [
                                            'hok' => 'gmhok', 'hr' => 'gmhr', 'staff_shotel' => 'gmshotel',
                                            'staff_manonta' => 'gmmanonta', 'staff_nijuni' => 'gmnijuni', 'acc' => 'gmacc'
                                        ];
                                        $target_head_role = $dept_map[$row['creator_role']] ?? '';
                                        if (empty($row['approved_by_0'])) {
                                            if ($role === $target_head_role || ($user_id == $row['created_by'] && in_array($role, array_values($dept_map)))) $can_approve = true;
                                        } elseif ($role === 'procure' && empty($row['approved_by'])) $can_approve = true;
                                        elseif ($role === 'gmacc' && empty($row['approved_by_1'])) $can_approve = true;
                                        elseif ($role === 'mgr' && empty($row['approved_by_2'])) $can_approve = true;
                                        elseif ($role === 'mgr2' && empty($row['approved_by_3'])) $can_approve = true;
                                        elseif (in_array($role, ['admin', 'gmhok'])) $can_approve = true;
                                    }
                                    ?>
                                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-2">
                                        <div class="p-3 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox" class="pr-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600" value="<?= $row['id'] ?>">
                                                <span class="font-bold text-slate-800 text-sm"><?= $row['doc_no'] ?></span>
                                            </div>
                                            <div class="status-badge inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border <?= $s_style['bg'] ?> <?= $s_style['border'] ?> <?= $s_style['text'] ?> shadow-sm">
                                                <span class="w-1.5 h-1.5 rounded-full <?= $s_style['dot'] ?> animate-pulse"></span>
                                                <span class="text-[10px] font-bold uppercase tracking-wide"><?= $s_style['label'] ?></span>
                                            </div>
                                        </div>
                                        <div class="p-3 space-y-2">
                                            <div class="flex justify-between items-start">
                                                <div class="flex flex-col">
                                                    <span class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">หน่วยงาน/บริษัท</span>
                                                    <span class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($row['supplier_name'] ?: '-') ?></span>
                                                </div>
                                                <div class="text-right">
                                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold border <?= $p_style['bg'] ?> <?= $p_style['border'] ?> <?= $p_style['text'] ?>">
                                                        <?= $prio ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">รายละเอียด</span>
                                                <p class="text-[11px] text-slate-600 line-clamp-2"><?= htmlspecialchars($row['first_item_desc'] ?: 'ไม่มีรายละเอียดสินค้า') ?></p>
                                            </div>
                                            <div class="flex justify-between items-end pt-1">
                                                <div class="flex items-center gap-2">
                                                    <div class="flex flex-col">
                                                        <span class="text-[10px] text-slate-500 leading-tight"><i class="fas fa-user text-[8px] mr-1"></i><?= htmlspecialchars($row['creator_real_name']) ?></span>
                                                        <span class="text-[9px] text-slate-400"><i class="fas fa-calendar-alt text-[8px] mr-1"></i><?= date('d/m/y', strtotime($row['created_at'])) ?></span>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <span class="text-[9px] text-slate-400 uppercase font-bold tracking-wider block">ยอดรวมสุทธิ</span>
                                                    <span class="text-sm font-black text-indigo-600">฿<?= number_format($row['grand_total'], 2) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="px-3 py-1.5 bg-slate-50 flex justify-around border-t border-slate-100">
                                             <div class="flex flex-col items-center gap-0.5">
                                                <span class="text-[7px] text-slate-400 uppercase font-bold">หัวหน้า</span>
                                                <?= !empty($row['approver_0_name']) ? '<i class="fas fa-check-circle text-emerald-500 text-[10px]"></i>' : '<i class="far fa-circle text-slate-300 text-[10px]"></i>' ?>
                                             </div>
                                             <div class="flex flex-col items-center gap-0.5">
                                                <span class="text-[7px] text-slate-400 uppercase font-bold">จัดซื้อ</span>
                                                <?= !empty($row['approver_name']) ? '<i class="fas fa-check-circle text-emerald-500 text-[10px]"></i>' : '<i class="far fa-circle text-slate-300 text-[10px]"></i>' ?>
                                             </div>
                                             <div class="flex flex-col items-center gap-0.5">
                                                <span class="text-[7px] text-slate-400 uppercase font-bold">SUP</span>
                                                <?= !empty($row['approver_1_name']) ? '<i class="fas fa-check-circle text-emerald-500 text-[10px]"></i>' : '<i class="far fa-circle text-slate-300 text-[10px]"></i>' ?>
                                             </div>
                                             <div class="flex flex-col items-center gap-0.5">
                                                <span class="text-[7px] text-slate-400 uppercase font-bold">CEO</span>
                                                <?= !empty($row['approver_2_name']) ? '<i class="fas fa-check-circle text-emerald-500 text-[10px]"></i>' : '<i class="far fa-circle text-slate-300 text-[10px]"></i>' ?>
                                             </div>
                                        </div>
                                        <div class="p-2 bg-white border-t border-slate-100 flex gap-2">
                                            <?php if ($can_approve): ?>
                                                <button onclick="approvePR(<?= $row['id'] ?>, '<?= $row['doc_no'] ?>')"
                                                    class="flex-1 py-1.5 bg-emerald-500 text-white rounded-lg font-bold text-[10px] shadow-sm hover:bg-emerald-600 transition-all flex items-center justify-center gap-1">
                                                    <i class="fas fa-check-circle"></i> อนุมัติ
                                                </button>
                                            <?php endif; ?>
                                            <a href="view_pr_new.php?id=<?= $row['id'] ?>" 
                                                class="flex-1 py-1.5 bg-slate-100 text-slate-700 rounded-lg font-bold text-[10px] hover:bg-slate-200 transition-all flex items-center justify-center gap-1">
                                                <i class="fas fa-eye"></i> ดูข้อมูล
                                            </a>
                                            <?php if (!is_viewer() && $row['status'] === 'pending'): ?>
                                                <a href="edit_pr_new.php?id=<?= $row['id'] ?>" 
                                                    class="p-1.5 bg-amber-50 text-amber-600 border border-amber-100 rounded-lg hover:bg-amber-100 transition-all">
                                                    <i class="fas fa-edit text-[10px]"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td class="font-bold text-slate-800 hidden md:table-cell"><?= $row['doc_no'] ?></td>
                                <td class="hidden md:table-cell">
                                    <?php
                                    $prio = $row['priority'] ?? 'ปานกลาง';
                                    $p_config = [
                                        'น้อย' => ['bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'border' => 'border-slate-100'],
                                        'ปานกลาง' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-100'],
                                        'เร่งด่วน' => ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'border' => 'border-orange-100'],
                                        'วิกฤต' => ['bg' => 'bg-red-50', 'text' => 'text-red-600', 'border' => 'border-red-100'],
                                    ];
                                    $p_style = $p_config[$prio] ?? $p_config['ปานกลาง'];
                                    ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $p_style['bg'] ?> <?= $p_style['border'] ?> <?= $p_style['text'] ?>">
                                        <?= $prio ?>
                                    </span>
                                </td>
                                <td class="hidden md:table-cell">
                                    <div class="font-semibold text-slate-700 truncate max-w-[150px]">
                                        <?= htmlspecialchars($row['supplier_name'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="hidden md:table-cell">
                                    <div class="font-semibold text-slate-700 truncate max-w-[250px]"
                                        title="<?= htmlspecialchars($row['first_item_desc'] ?? '') ?>">
                                        <?= htmlspecialchars($row['first_item_desc'] ?: 'ไม่มีรายละเอียดสินค้า') ?>
                                    </div>
                                </td>
                                <td class="text-center hidden md:table-cell">
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
                                <td class="hidden md:table-cell">
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
                                <td class="hidden md:table-cell">
                                    <?php
                                    $status = $row['status'] ?: 'pending';
                                    $config = [
                                        'pending' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100', 'dot' => 'bg-amber-400', 'label' => 'รอ'],
                                        'approved' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100', 'dot' => 'bg-emerald-400', 'label' => 'อนุมัติ'],
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
                                <td data-order="<?= $row['created_at'] ?>" class="hidden md:table-cell">
                                    <div class="flex flex-col">
                                        <span class="text-[14px] font-bold text-slate-700 mt-0.5 flex items-center gap-1"><i
                                                class="fas fa-calendar-alt text-[9px]"></i><?= date('d/m/y', strtotime($row['created_at'])) ?></span>
                                        <?php if (!empty($row['updated_at'])): ?>
                                            <span class="text-[9px] text-slate-500 mt-0.5 flex items-center gap-1"><i
                                                    class="fas fa-history text-[8px]"></i><?= date('d/m/y', strtotime($row['updated_at'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="truncate max-w-[100px] text-[11px] font-medium text-slate-600 hidden md:table-cell">
                                    <?= htmlspecialchars($row['creator_real_name'] ?: '-') ?>
                                </td>
                                <td class="text-center hidden md:table-cell">
                                    <?php if (!empty($row['approved_by_0'])): ?>
                                        <span class="text-emerald-600 text-[10px] font-bold"><i class="fas fa-check-circle"></i> อนุมัติ</span>
                                    <?php else: ?>
                                        <span class="text-amber-500 text-[10px] font-bold"><i class="fas fa-clock"></i> รออนุมัติ</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center approver-cell-head hidden md:table-cell">
                                    <?php if (!empty($row['approver_0_name'])): ?>
                                        <i class="fas fa-check text-emerald-500"></i>
                                        <div class="text-[8px] text-slate-400 font-mono">
                                            <?= date('d/m/y', strtotime($row['approved_at_0'])) ?>
                                        </div>
                                    <?php else:
                                        echo '-';
                                    endif; ?>
                                </td>
                                <td class="text-center approver-cell-0 hidden md:table-cell">
                                    <?php if (!empty($row['approver_name'])): ?>
                                        <i class="fas fa-check text-emerald-500"></i>
                                        <div class="text-[8px] text-slate-400 font-mono">
                                            <?= date('d/m/y', strtotime($row['approved_at'])) ?>
                                        </div>
                                    <?php else:
                                        echo '-';
                                    endif; ?>
                                </td>
                                <td class="text-center approver-cell-1 hidden md:table-cell">
                                    <?php if (!empty($row['approver_1_name'])): ?>
                                        <i class="fas fa-check text-emerald-500"></i>
                                        <div class="text-[8px] text-slate-400 font-mono">
                                            <?= date('d/m/y', strtotime($row['approved_at_1'])) ?>
                                        </div>
                                    <?php else:
                                        echo '-';
                                    endif; ?>
                                </td>
                                <td class="text-center approver-cell-2 hidden md:table-cell">
                                    <?php if (!empty($row['approver_2_name'])): ?>
                                        <i class="fas fa-check text-emerald-500"></i>
                                        <div class="text-[8px] text-slate-400 font-mono">
                                            <?= date('d/m/y', strtotime($row['approved_at_2'])) ?>
                                        </div>
                                    <?php else:
                                        echo '-';
                                    endif; ?>
                                </td>

                                <td class="px-4 hidden md:table-cell">
                                    <div class="flex justify-center gap-1.5">
                                        <?php if ($can_approve): ?>
                                            <button onclick="approvePR(<?= $row['id'] ?>, '<?= $row['doc_no'] ?>')"
                                                title="อนุมัติ PR"
                                                class="btn-approve-pr w-8 h-8 flex items-center justify-center bg-white text-emerald-500 rounded-lg hover:bg-emerald-50 hover:text-emerald-600 border border-emerald-100 shadow-sm transition-all active:scale-95"><i
                                                    class="fas fa-check-circle text-xs"></i></button>
                                        <?php endif; ?>
                                        <a href="view_pr_new.php?id=<?= $row['id'] ?>" title="ดูรายละเอียด"
                                            class="w-8 h-8 flex items-center justify-center bg-white text-slate-800 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 shadow-sm transition-all active:scale-90"><i
                                                class="fas fa-eye text-xs"></i></a>
                                        <?php if (!is_viewer()): ?>
                                            <?php if ($row['status'] === 'pending'): ?>
                                                <a href="edit_pr_new.php?id=<?= $row['id'] ?>" title="แก้ไข"
                                                    class="w-8 h-8 flex items-center justify-center bg-white text-slate-800 rounded-lg hover:bg-amber-50 hover:text-amber-600 border border-slate-200 shadow-sm transition-all active:scale-90"><i
                                                        class="fas fa-edit text-xs"></i></a>
                                            <?php endif; ?>
                                        <?php endif; ?>
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
    const AUTO_FILTER_SUPPLIER = <?= json_encode($auto_filter_supplier, JSON_UNESCAPED_UNICODE) ?>;

    $(document).ready(function () {
        // Table columns indices (Updated for Mobile Card column at index 1):
        // 0: Checkbox (Desktop), 1: Mobile Card, 2: เลขที่, 3: ความสำคัญ, 4: หน่วยงาน, 5: รายละเอียด, 6: ไฟล์แนบ, 7: ยอดรวม, 8: สถานะ, 9: วันที่, 10: ผู้สร้าง, 11: สถานะหัวหน้า, 12: หัวหน้า, 13: จัดซื้อ, 14: SUP, 15: CEO, 16: ดำเนินการ
        const columns = [
            { "orderable": false, "targets": [0, 1, 6, 11, 12, 13, 14, 15, 16] },
            { "type": "html", "targets": [8, 11, 12, 13, 14, 15] }
        ];

        prTable = $('#prTable').DataTable({
            "pageLength": 10,
            "dom": '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json" },
            "order": [[9, "desc"]],
            "columnDefs": columns,
            "drawCallback": function () { updateBulkUI(); }
        });

        // ตั้งค่าการกรองเริ่มต้นเป็น "รอ"
        prTable.column(8).search('รอ').draw();

        // Auto-filter ตาม Supplier สำหรับ GM roles ที่มี sup_id
        if (AUTO_FILTER_SUPPLIER) {
            $('#filterSupplier').val(AUTO_FILTER_SUPPLIER);
            prTable.column(4).search(AUTO_FILTER_SUPPLIER).draw();
        }

        $('#filterSupplier').on('change', function () { prTable.column(4).search(this.value).draw(); });
        $('#filterStatus').on('change', function () { prTable.column(8).search(this.value).draw(); });

        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            let min = $('#minDate').val();
            let max = $('#maxDate').val();
            let dateIdx = 9;
            let dateStr = data[dateIdx] || "";
            if (dateStr === "") return true;
            let match = dateStr.match(/(\d{2})\/(\d{2})\/(\d{2})/);
            if (!match) return true;
            let dateFormatted = `20${match[3]}-${match[2]}-${match[1]}`;
            if ((min === "" && max === "") || (min === "" && dateFormatted <= max) || (min <= dateFormatted && max === "") || (min <= dateFormatted && dateFormatted <= max)) return true;
            return false;
        });

        $('#minDate, #maxDate').on('change', () => prTable.draw());
        $('#selectAll').on('change', function () { $('.pr-checkbox').prop('checked', this.checked); updateBulkUI(); });
        $(document).on('change', '.pr-checkbox', updateBulkUI);
    });

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

    /**
     * ลบ PR แบบกลุ่ม (Bulk Soft Delete)
     */
    function bulkDeletePR() {
        const checkedItems = $('.pr-checkbox:checked');
        const ids = [];
        checkedItems.each(function () {
            ids.push($(this).val());
        });

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

                fetch('api/delete_pr.php', {
                    method: 'POST',
                    body: fd
                })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        checkedItems.each(function () {
                            prTable.row($(this).closest('tr')).remove();
                        });

                        prTable.draw(false);
                        renderAlert('delete', `ย้าย ${ids.length} รายการลงถังขยะแล้ว`);
                        updateBulkUI();
                        $('#selectAll').prop('checked', false);
                        if (typeof updatePendingBadge === 'function') updatePendingBadge();
                    } else {
                        renderAlert('error', res.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    renderAlert('error', 'การเชื่อมต่อผิดพลาด');
                });
            }
        });
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

    function approvePR(id, prNo) {
        Swal.fire({
            title: 'ยืนยันการอนุมัติ?',
            text: `ใบขอซื้อเลขที่ ${prNo}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'ยืนยันอนุมัติ',
            reverseButtons: true,
            heightAuto: false
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`api/update_pr_status_new.php?id=${id}&action=approved`).then(res => res.json()).then(data => {
                    if (data.status === 'success') {
                        renderAlert('success', 'อนุมัติเรียบร้อย');
                        if (typeof updatePendingBadge === 'function') updatePendingBadge();

                        const row = $(`.pr-checkbox[value="${id}"]`).closest('tr');
                        const colMap = { 'approved_by_0': 12, 'approved_by': 13, 'approved_by_1': 14, 'approved_by_2': 15 };

                        if (data.column && colMap[data.column] !== undefined) {
                            let content = '<i class="fas fa-check text-emerald-500"></i>';
                            if (data.column !== 'approved_by_2') content += `<div class="text-[8px] text-slate-400 font-mono">${data.approved_date}</div>`;
                            prTable.cell(row, colMap[data.column]).data(content).draw(false);
                        }
                        // Update status column (index 8)
                        if (data.full_approved) {
                            prTable.cell(row, 8).data('<div class="status-badge inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border bg-emerald-50 border-emerald-100 text-emerald-600 shadow-sm"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span><span class="text-[12px] font-bold uppercase tracking-wide">อนุมัติ</span></div>').draw(false);
                        }
                        
                        // Optional: Refresh page or row to update mobile card icons too
                        // location.reload(); 
                    } else Swal.fire({ title: 'แจ้งเตือน', text: data.message, icon: 'warning', heightAuto: false });
                });
            }
        });
    }

    function filterToday() {
        const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Bangkok' });
        $('#minDate').val(today); $('#maxDate').val(today);
        prTable.draw();
    }
    function resetFilter() {
        if (AUTO_FILTER_SUPPLIER) {
            $('#filterSupplier').val(AUTO_FILTER_SUPPLIER);
            $('#filterStatus').val('');
            $('#minDate').val(''); $('#maxDate').val('');
            prTable.column(4).search(AUTO_FILTER_SUPPLIER);
            prTable.column(8).search('');
            prTable.draw();
        } else {
            $('#filterSupplier').val('');
            $('#filterStatus').val('');
            $('#minDate').val(''); $('#maxDate').val('');
            prTable.column(4).search('');
            prTable.column(8).search('');
            prTable.draw();
        }
    }
    function viewAttachment(url) { window.open(url, '_blank'); }
</script>
<?php include 'footer.php'; ?>