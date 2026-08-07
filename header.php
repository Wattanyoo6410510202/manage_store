<?php
// เริ่ม Session หากยังไม่ได้เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. เช็ค Login (ใช้ JS แทน header เพื่อกัน Error "Headers already sent")
if (!isset($_SESSION['user'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$user_role = $_SESSION['role'] ?? 'viewer'; // ดึงค่าจาก Session

if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/inspection_workflow.php';
$inspection_only_access = inspection_user_has_assignment($conn, (int)($_SESSION['user_id'] ?? 0));

$permissions = [
    // 1. Admin: ทำได้ทุกอย่างในระบบ
    'admin' => ['dashboard', 'docs', 'projects', 'compare', 'inventory', 'setup', 'trash'],

    // 2. GM (General Manager): ดูและจัดการได้เกือบหมด ยกเว้นการตั้งค่าระบบ/API/ลบขยะ
    'gm' => ['dashboard', 'docs', 'projects', 'compare', 'inventory'],

    // 3. GMHOK: สิทธิ์ระดับบริหารเฉพาะส่วน (เน้นดูงานและเอกสาร)
    'gmhok' => ['dashboard', 'docs', 'projects', 'compare', 'procure'],

    // 4. HOK: สิทธิ์ระดับหัวหน้าส่วนงาน
    'hok' => ['dashboard', 'docs', 'projects', 'compare'],

    // 5. Staff: พนักงานปฏิบัติการ (เอา projects และ docs ออกตามสั่ง)
    'staff' => ['dashboard', 'compare', 'inventory'],
    'maid_shotel' => ['dashboard', 'compare'],
    'tech_shotel' => ['dashboard', 'compare'],
    'cater_shotel' => ['dashboard', 'compare'],

    // 6. Viewer: ดูได้ทุกอย่าง (ยกเว้นตั้งค่า) แต่จะไปคุมที่ปุ่มห้าม เพิ่ม/แก้ไข/ลบ
    'viewer' => ['dashboard', 'docs', 'projects', 'compare', 'inventory', 'trash'],
    'procure' => ['dashboard', 'docs', 'projects', 'compare', 'setup', 'trash'],
    
    // 7. ฝ่ายบัญชี/บริหาร
    'acc' => ['dashboard', 'compare'],
    'mgr' => ['dashboard', 'docs', 'projects', 'compare', 'inventory', 'trash'],
    'mgr2' => ['dashboard', 'docs', 'projects', 'compare', 'inventory', 'trash'],
    
    'fin' => ['dashboard', 'compare'],

    // 8. ฝ่ายขาย/การตลาด
    'gm_sale' => ['dashboard', 'compare'],
    'sale' => ['dashboard', 'compare'],
    'marketing' => ['dashboard', 'compare']
];

// ฟังก์ชันเช็คสิทธิ์สำหรับใช้ใน Side Bar และปุ่มต่างๆ
function can($module)
{
    global $user_role, $permissions, $inspection_only_access;
    if ($module === 'projects' && !empty($inspection_only_access)) {
        return true;
    }
    return in_array($module, $permissions[$user_role] ?? []);
}

// ฟังก์ชันช่วยเช็คว่าเป็น Viewer หรือไม่ (เพื่อซ่อนปุ่ม)
function is_viewer() {
    global $user_role, $inspection_only_access;
    return $user_role === 'viewer' || !empty($inspection_only_access);
}
// ==========================================
// ==========================================

$current_page = basename($_SERVER['PHP_SELF']);

// 1. กลุ่ม "รายการเอกสาร" (เพื่อให้เมนูหลักกางออก)
$doc_list_pages = [
    'doc_list.php',
    'view_quotation.php',
    'edit_quotation.php',
    'add_quotation.php',
    'quotation_settings.php',
    'pr_list.php',
    'view_pr.php',
    'edit_pr.php',
    'add_pr.php',
    'pr_settings.php',
    'po_list.php',
    'view_po.php',
    'edit_po.php',
    'add_po.php',
    'po_settings.php',
    'invoice_list.php',
    'view_invoice.php',
    'edit_invoice.php',
    'add_invoice.php',
    'invoice_settings.php'

];
$is_list_active = in_array($current_page, $doc_list_pages);

// 2. แยกกลุ่มย่อย เพื่อทำแถบสีไฮไลท์ที่เมนูย่อย
$is_quotation_active = in_array($current_page, ['doc_list.php', 'view_quotation.php', 'edit_quotation.php', 'add_quotation.php', 'quotation_settings.php']);
$is_pr_active = in_array($current_page, ['pr_list.php', 'view_pr.php', 'edit_pr.php', 'add_pr.php', 'pr_settings.php']);
$is_po_active = in_array($current_page, ['po_list.php', 'view_po.php', 'edit_po.php', 'add_po.php', 'po_settings.php']);
$is_invoice_active = in_array($current_page, ['invoice_list.php', 'view_invoice.php', 'edit_invoice.php', 'add_invoice.php', 'invoice_settings.php']);

// 3. กลุ่ม "ขอซื้อ"
$is_req_buy_group = in_array($current_page, ['request_buy.php', 'request_buy_history.php', 'view_pr_new.php', 'edit_pr_new.php']);

// 4. กลุ่ม "ก่อสร้าง"
$is_construction_group = in_array($current_page, ['projects.php', 'add_project.php', 'edit_project.php', 'detail_project.php', 'view_milstones.php', 'add_milestone.php', 'edit_milestone.php', 'upcoming_payments.php', 'project_timeline.php', 'big_projects.php', 'add_big_project.php', 'detail_big_project.php']);

// An assigned inspector may browse only the project list and the project that
// contains their assigned checklist. All project administration routes remain
// blocked even though the user can reach the inspection workspace.
if ($inspection_only_access && $is_construction_group) {
    $inspection_project_id = (int)($_GET['id'] ?? 0);
    $inspection_milestone_ids = preg_split('/\D+/', (string)($_GET['ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    $inspection_route_allowed = $current_page === 'projects.php'
        || ($current_page === 'detail_project.php'
            && inspection_user_assigned_to_project($conn, $inspection_project_id, (int)($_SESSION['user_id'] ?? 0)))
        || ($current_page === 'view_milstones.php'
            && inspection_user_assigned_to_milestones($conn, $inspection_milestone_ids, (int)($_SESSION['user_id'] ?? 0)));
    if (!$inspection_route_allowed) {
        echo "<script>window.location.href='e_service.php';</script>";
        exit;
    }
}

// 5. กลุ่ม "ตั้งค่า"
$is_setup_active = in_array($current_page, ['settings.php', 'store_settings.php', 'user_settings.php', 'settings_api.php', 'expense_settings.php', 'budget_settings.php', 'objective_settings.php']);

// ==========================================
// [เพิ่มใหม่] บล็อกการเข้าหน้าทางตรง (URL Security)
// ==========================================
if ($is_setup_active && !can('setup')) {
    echo "<script>alert('เฉพาะ Admin หรือ จัดซื้อ เท่านั้นที่เข้าถึงส่วนการตั้งค่าได้'); window.location.href='index.php';</script>";
    exit;
}
if ($is_construction_group && !can('projects')) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหมวดก่อสร้าง'); window.location.href='e_service.php';</script>";
    exit;
}
if (($is_list_active || $current_page == 'index.php') && !can('docs')) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการเอกสาร'); window.location.href='e_service.php';</script>";
    exit;
}
if ($current_page == 'all_trash.php' && !can('trash')) {
    echo "<script>window.location.href='e_service.php';</script>";
    exit;
}
// Check if user is Admin, Viewer, or any type of GM (e.g., gm, gmhok, gmhr, gmacc, etc.)
$is_gm = (strpos($user_role, 'gm') === 0);
$allowed_roles = ['admin', 'procure', 'mgr', 'mgr2', 'viewer'];

if ($current_page == 'pending_approval.php') {
    if (strpos($user_role, 'staff') === 0 || $user_role === 'acc' || (!$is_gm && !in_array($user_role, $allowed_roles))) {
        echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้ได้'); window.location.href='e_service.php';</script>";
        exit;
    }
}

if ($current_page == 'pending_budget.php') {
    if ($user_role === 'hok' || strpos($user_role, 'staff') === 0 || $user_role === 'acc' || $user_role === 'viewer') {
        echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้ได้'); window.location.href='e_service.php';</script>";
        exit;
    }
}
// ==========================================
// [เพิ่มใหม่] จัดกลุ่มหมวดหมู่ใหญ่
// ==========================================
$cat_main = ['e_service.php', 'request_buy.php', 'request_buy_history.php', 'procurement.php', 'procurement_dashboard.php', 'pending_approval.php', 'view_pr_new.php', 'edit_pr_new.php', 'pending_budget.php', 'budget_settings.php'];
$cat_settings = ['settings.php', 'store_settings.php', 'user_settings.php', 'settings_api.php', 'all_trash.php', 'expense_settings.php', 'budget_settings.php', 'objective_settings.php'];
$cat_construction = ['projects.php', 'add_project.php', 'edit_project.php', 'detail_project.php', 'view_milstones.php', 'add_milestone.php', 'edit_milestone.php', 'upcoming_payments.php', 'project_timeline.php', 'big_projects.php', 'add_big_project.php', 'detail_big_project.php'];
// อื่นๆ คือ cat_system

$active_cat = 'system'; 
if (in_array($current_page, $cat_main)) $active_cat = 'main';
if (in_array($current_page, $cat_settings)) $active_cat = 'settings';
if (in_array($current_page, $cat_construction)) $active_cat = 'construction';

// ดึงจำนวนรายการที่รออนุมัติเฉพาะส่วนของ Role และ User ตัวเอง
$pending_count = 0;
require_once 'config.php';
$user_role_for_count = $_SESSION['role'] ?? '';
$user_id_for_count = $_SESSION['user_id'] ?? 0;

$pending_sql = "SELECT COUNT(p.id) as total FROM pr p 
                LEFT JOIN users u ON p.created_by = u.id 
                WHERE p.deleted_at IS NULL AND p.status = 'pending'";

// ตรรกะ: นับเฉพาะรายการที่ User มีสิทธิ์อนุมัติในขั้นนั้นๆ และเขายังไม่ได้อนุมัติ
if ($user_role_for_count === 'procure') {
    $pending_sql .= " AND p.approved_by IS NULL";
} elseif ($user_role_for_count === 'gmacc' || $user_role_for_count === 'acc') {
    $pending_sql .= " AND p.approved_by_1 IS NULL";
} elseif ($user_role_for_count === 'mgr') {
    $pending_sql .= " AND p.approved_by_2 IS NULL";
} elseif ($user_role_for_count === 'mgr2') {
    $pending_sql .= " AND p.approved_by_3 IS NULL";
} elseif (strpos($user_role_for_count, 'gm') === 0 && $user_role_for_count !== 'gmacc') {
    // GM นับ Level 0 ของ PR ที่ผู้สร้างมี sup_id ตรงกับตัวเอง
    $user_sup_id = $_SESSION['sup_id'] ?? 0;
    if ($user_sup_id > 0) {
        $pending_sql .= " AND u.sup_id = $user_sup_id AND p.approved_by_0 IS NULL";
    } else {
        $pending_sql .= " AND 1=0";
    }
} elseif (in_array($user_role_for_count, ['admin', 'gmhok'])) {
    // Admin/GMHOK นับรวมทุกรายการที่ยังไม่จบ (รวมทุกสถานะ Null)
    $pending_sql .= " AND (p.approved_by_0 IS NULL OR p.approved_by IS NULL OR p.approved_by_1 IS NULL OR p.approved_by_2 IS NULL OR p.approved_by_3 IS NULL)";
} else {
    $pending_sql .= " AND 1=0";
}

$pending_res = mysqli_query($conn, $pending_sql);
if ($pending_res) {
    $pending_row = mysqli_fetch_assoc($pending_res);
    $pending_count = $pending_row['total'] ?? 0;
}

// --- ดึงจำนวนรายการรออนุมัติงบประมาณ ---
$pending_budget_count = 0;
$budget_approval_roles = ['gmacc', 'mgr', 'mgr2', 'admin'];
if (in_array($user_role_for_count, $budget_approval_roles)) {
    $budget_count_sql = "SELECT COUNT(*) as total FROM budget_types WHERE status = 'pending'";
    $budget_res = mysqli_query($conn, $budget_count_sql);
    if ($budget_res) {
        $budget_row = mysqli_fetch_assoc($budget_res);
        $pending_budget_count += $budget_row['total'] ?? 0;
    }
    $adjust_count_sql = "SELECT COUNT(*) as total FROM budget_adjustments WHERE status = 'pending'";
    $adjust_res = mysqli_query($conn, $adjust_count_sql);
    if ($adjust_res) {
        $adjust_row = mysqli_fetch_assoc($adjust_res);
        $pending_budget_count += $adjust_row['total'] ?? 0;
    }
}

// --- ดึงชื่อบริษัท (Supplier Name) ถ้ามี sup_id ---
$company_name = '';
if (!empty($_SESSION['sup_id'])) {
    $sup_id = intval($_SESSION['sup_id']);
    $sup_res = mysqli_query($conn, "SELECT company_name FROM suppliers WHERE id = $sup_id");
    if ($sup_res && $sup_row = mysqli_fetch_assoc($sup_res)) {
        $company_name = $sup_row['company_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProSystem | Management</title>

    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="shopping-cart.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css">

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js"></script>

    <!-- Driver.js for Tutorial -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css"/>
    <script src="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.js.iife.js"></script>

    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }

        #sidebar {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #sidebar-nav::-webkit-scrollbar {
            width: 4px;
        }

        #sidebar-nav::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            #sidebar {
                position: fixed;
                left: -100%;
                z-index: 100;
            }

            #sidebar.open {
                left: 0;
            }
        }

        .submenu-active {
            color: #818cf8 !important;
            background: rgba(79, 70, 229, 0.1);
        }
    </style>
</head>

<body class="bg-slate-100 flex h-screen overflow-hidden">

    <div id="sidebar-overlay"
        class="fixed inset-0 bg-black/50 z-[90] hidden opacity-0 transition-opacity duration-300 md:hidden"
        onclick="toggleSidebar()"></div>

    <aside id="sidebar"
        class="w-60 bg-slate-900 text-slate-300 flex flex-col shrink-0 h-full shadow-2xl md:shadow-none">
        <div class="p-6 bg-slate-950 flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="bg-white p-2 rounded-lg shadow-lg shadow-indigo-500/20">
                    <img src="shopping-cart.png" alt="ไอคอนไง" class="h-6 w-6 text-white" />
                </div>
                <span class="text-xl font-extrabold text-white tracking-tight">ProSystem</span>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <nav id="sidebar-nav" class="flex-1 p-4 space-y-1 overflow-y-auto">

            <?php if ($active_cat == 'main'): ?>
                <p class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-[2px] mb-2">Main Menu</p>
                <a href="e_service.php"
                    class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo ($current_page == 'e_service.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'hover:bg-slate-800'; ?>">
                    <i class="fas fa-bolt w-5 <?php echo ($current_page == 'e_service.php') ? 'text-white' : 'text-indigo-400'; ?>"></i>
                    <span class="font-medium">E-Service</span>
                </a>

                <div class="space-y-1">
                    <button onclick="toggleSubmenu('req-buy-submenu')"
                        class="flex items-center justify-between w-full p-3 rounded-xl transition-all <?php echo $is_req_buy_group ? 'bg-slate-800 text-white' : 'hover:bg-slate-800'; ?>">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-shopping-basket w-5 text-emerald-400"></i>
                            <span class="font-medium">ขอซื้อ</span>
                        </div>
                        <i id="arrow-req-buy-submenu"
                            class="fas fa-chevron-down text-[10px] transition-transform <?php echo $is_req_buy_group ? 'rotate-180' : ''; ?>"></i>
                    </button>

                    <div id="req-buy-submenu"
                        class="<?php echo $is_req_buy_group ? '' : 'hidden'; ?> ml-6 mt-1 border-l-2 border-slate-800 space-y-1">
                        <a href="request_buy.php"
                            class="group relative flex items-center gap-3 py-2 px-4 transition-all duration-200 <?php echo $current_page == 'request_buy.php' ? 'text-emerald-400 bg-emerald-500/5' : 'text-slate-500 hover:text-slate-200'; ?>">
                            <span class="absolute -left-[2px] w-[2px] h-6 bg-emerald-500 transition-opacity <?php echo $current_page == 'request_buy.php' ? 'opacity-100' : 'opacity-0'; ?>"></span>
                            <i class="fas fa-plus-circle text-[10px]"></i>
                            <span class="text-sm font-medium">สร้างใบขอซื้อ</span>
                        </a>
                        <a href="request_buy_history.php"
                            class="group relative flex items-center gap-3 py-2 px-4 transition-all duration-200 <?php echo $current_page == 'request_buy_history.php' || $current_page == 'view_pr_new.php' || $current_page == 'edit_pr_new.php' ? 'text-emerald-400 bg-emerald-500/5' : 'text-slate-500 hover:text-slate-200'; ?>">
                            <span class="absolute -left-[2px] w-[2px] h-6 bg-emerald-500 transition-opacity <?php echo $current_page == 'request_buy_history.php' || $current_page == 'view_pr_new.php' || $current_page == 'edit_pr_new.php' ? 'opacity-100' : 'opacity-0'; ?>"></span>
                            <i class="fas fa-history text-[10px]"></i>
                            <span class="text-sm font-medium">ประวัติขอซื้อ</span>
                        </a>
                    </div>
                </div>

                <?php if (strpos($user_role, 'staff') !== 0 && !in_array($user_role, ['acc', 'maid_shotel', 'tech_shotel', 'cater_shotel', 'sale', 'marketing'])): ?>
                <a href="pending_approval.php"
                    class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo ($current_page == 'pending_approval.php') ? 'bg-rose-600 text-white shadow-lg shadow-rose-600/20' : 'hover:bg-slate-800'; ?>">
                    <i class="fas fa-clipboard-check w-5 <?php echo ($current_page == 'pending_approval.php') ? 'text-white' : 'text-rose-400'; ?>"></i>
                    <span class="font-medium">รายการรออนุมัติ <?php echo ($pending_count > 0) ? "($pending_count)" : ""; ?></span>
                </a>
                <?php endif; ?>

                <?php if (in_array($user_role, ['admin', 'gmacc', 'mgr', 'mgr2'])): ?>
                <a href="pending_budget.php"
                    class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo ($current_page == 'pending_budget.php') ? 'bg-amber-600 text-white shadow-lg shadow-amber-600/20' : 'hover:bg-slate-800'; ?>">
                    <i class="fas fa-coins w-5 <?php echo ($current_page == 'pending_budget.php') ? 'text-white' : 'text-amber-400'; ?>"></i>
                    <span class="font-medium">รายการรออนุมัติงบประมาณ <?php echo ($pending_budget_count > 0) ? "($pending_budget_count)" : ""; ?></span>
                </a>
                <?php endif; ?>

                <?php if (in_array($user_role, ['acc', 'mgr', 'mgr2', 'procure', 'admin'])): ?>
                <div class="my-4 border-t border-slate-800/50"></div>
                <p class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-[2px] mb-2">แผนก</p>
                <a href="procurement.php"
                    class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo ($current_page == 'procurement.php') ? 'bg-amber-600 text-white shadow-lg shadow-amber-600/20' : 'hover:bg-slate-800'; ?>">
                    <i class="fas fa-truck-loading w-5 <?php echo ($current_page == 'procurement.php') ? 'text-white' : 'text-amber-400'; ?>"></i>
                    <span class="font-medium">จัดซื้อ</span>
                </a>
                <a href="procurement_dashboard.php"
                    class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo ($current_page == 'procurement_dashboard.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'hover:bg-slate-800'; ?>">
                    <i class="fas fa-chart-line w-5 <?php echo ($current_page == 'procurement_dashboard.php') ? 'text-white' : 'text-indigo-400'; ?>"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($active_cat == 'system'): ?>
                <p class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-[2px] mb-2">Management System</p>
                <a href="index.php"
                    class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo ($current_page == 'index.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'hover:bg-slate-800'; ?>">
                    <i class="fas fa-th-large w-5 <?php echo ($current_page == 'index.php') ? 'text-white' : 'text-indigo-400'; ?>"></i>
                    <span class="font-medium">จัดการเอกสาร</span>
                </a>

                <?php if (can('docs')): ?>
                    <div class="space-y-1">
                        <button onclick="toggleSubmenu('doc-submenu')"
                            class="flex items-center justify-between w-full p-3 rounded-xl transition-all <?php echo $is_list_active ? 'bg-slate-800 text-white' : 'hover:bg-slate-800'; ?>">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-list w-5 text-indigo-400"></i>
                                <span class="font-medium">รายการเอกสาร</span>
                            </div>
                            <i id="arrow-doc-submenu"
                                class="fas fa-chevron-down text-[10px] transition-transform <?php echo $is_list_active ? 'rotate-180' : ''; ?>"></i>
                        </button>

                        <div id="doc-submenu"
                            class="<?php echo $is_list_active ? '' : 'hidden'; ?> ml-6 mt-1 border-l-2 border-slate-800 space-y-1">
                            <a href="doc_list.php"
                                class="group relative flex items-center gap-3 py-2 px-4 transition-all duration-200 <?php echo $is_quotation_active ? 'text-indigo-400 bg-indigo-500/5' : 'text-slate-500 hover:text-slate-200'; ?>">
                                <span class="absolute -left-[2px] w-[2px] h-6 bg-indigo-500 transition-opacity <?php echo $is_quotation_active ? 'opacity-100' : 'opacity-0'; ?>"></span>
                                <i class="fas fa-file-invoice text-[10px]"></i>
                                <span class="text-sm font-medium">ใบเสนอราคา</span>
                            </a>
                            <a href="pr_list.php"
                                class="group relative flex items-center gap-3 py-2 px-4 transition-all duration-200 <?php echo $is_pr_active ? 'text-indigo-400 bg-indigo-500/5' : 'text-slate-500 hover:text-slate-200'; ?>">
                                <span class="absolute -left-[2px] w-[2px] h-6 bg-indigo-500 transition-opacity <?php echo $is_pr_active ? 'opacity-100' : 'opacity-0'; ?>"></span>
                                <i class="fas fa-cart-plus text-[10px]"></i>
                                <span class="text-sm font-medium">ใบขอซื้อ</span>
                            </a>
                            <a href="po_list.php"
                                class="group relative flex items-center gap-3 py-2 px-4 transition-all duration-200 <?php echo $is_po_active ? 'text-indigo-400 bg-indigo-500/5' : 'text-slate-500 hover:text-slate-200'; ?>">
                                <span class="absolute -left-[2px] w-[2px] h-6 bg-indigo-500 transition-opacity <?php echo $is_po_active ? 'opacity-100' : 'opacity-0'; ?>"></span>
                                <i class="fas fa-file-signature text-[10px]"></i>
                                <span class="text-sm font-medium">ใบสั่งซื้อ (PO)</span>
                            </a>
                            <a href="invoice_list.php"
                                class="group relative flex items-center gap-3 py-2 px-4 transition-all duration-200 <?php echo $is_invoice_active ? 'text-indigo-400 bg-indigo-500/5' : 'text-slate-500 hover:text-slate-200'; ?>">
                                <span class="absolute -left-[2px] w-[2px] h-6 bg-indigo-500 transition-opacity <?php echo $is_invoice_active ? 'opacity-100' : 'opacity-0'; ?>"></span>
                                <i class="fas fa-file-invoice text-[10px]"></i>
                                <span class="text-sm font-medium">ใบแจ้งหนี้</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (can('compare')): ?>
                    <a href="compare.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'compare.php' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-exchange-alt w-5 text-indigo-400"></i>
                        <span class="font-medium">เปรียบเทียบราคา</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($active_cat == 'construction'): ?>
                <p class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-[2px] mb-2">Construction</p>
                <?php if (can('projects')): ?>
                    <?php if (empty($inspection_only_access)): ?>
                     <a href="big_projects.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo in_array($current_page, ['big_projects.php', 'add_big_project.php', 'detail_big_project.php']) ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-diagram-project w-5 <?php echo in_array($current_page, ['big_projects.php', 'add_big_project.php', 'detail_big_project.php']) ? 'text-white' : 'text-indigo-400'; ?>"></i>
                        <span class="font-medium">โปรเจคใหญ่</span>
                    </a>
                    <?php endif; ?>
                    <a href="projects.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo ($current_page == 'projects.php' || $current_page == 'add_project.php' || $current_page == 'edit_project.php' || $current_page == 'detail_project.php' || $current_page == 'view_milstones.php' || $current_page == 'add_milestone.php' || $current_page == 'edit_milestone.php') ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-tasks w-5 text-indigo-400"></i>
                        <span class="font-medium">จัดการงวดงาน</span>
                    </a>
                    <?php if (empty($inspection_only_access)): ?>
                    <a href="upcoming_payments.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'upcoming_payments.php' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-credit-card w-5 <?php echo $current_page == 'upcoming_payments.php' ? 'text-white' : 'text-indigo-400'; ?>"></i>
                        <span class="font-medium">ยอดค้างชำระ</span>
                    </a>
                    <a href="project_timeline.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'project_timeline.php' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-clock-rotate-left w-5 <?php echo $current_page == 'project_timeline.php' ? 'text-white' : 'text-indigo-400'; ?>"></i>
                        <span class="font-medium">Timeline โครงการ</span>
                    </a>
                   
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($active_cat == 'settings'): ?>
                <p class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-[2px] mb-2">Settings</p>
                <?php if (can('setup')): ?>
                    <a href="settings.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'settings.php' ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-cog w-5 text-indigo-400"></i>
                        <span class="font-medium">ตั้งค่าระบบ</span>
                    </a>
                    <a href="store_settings.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'store_settings.php' ? 'bg-emerald-600 text-white' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-store-alt w-5 text-emerald-400"></i>
                        <span class="font-medium">ร้านค้า</span>
                    </a>
                    <a href="user_settings.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'user_settings.php' ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-user-shield w-5 text-indigo-400"></i>
                        <span class="font-medium">ตั้งค่าผู้ใช้งาน</span>
                    </a>
                    <a href="settings_api.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'settings_api.php' ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-code w-5 text-indigo-400"></i>
                        <span class="font-medium">ตั้งค่า API</span>
                    </a>
                    <a href="expense_settings.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'expense_settings.php' ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-file-invoice-dollar w-5 text-indigo-400"></i>
                        <span class="font-medium">ตั้งค่าหมวดค่าใช้จ่าย</span>
                    </a>
                    <a href="budget_settings.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'budget_settings.php' ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-coins w-5 text-indigo-400"></i>
                        <span class="font-medium">ตั้งค่าประเภทงบประมาณ</span>
                    </a>
                     <a href="objective_settings.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'objective_settings.php' ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-bullseye w-5 text-indigo-400"></i>
                        <span class="font-medium">ตั้งค่าวัตถุประสงค์</span>
                    </a>
                <?php endif; ?>

                <?php if (can('trash')): ?>
                    <a href="all_trash.php"
                        class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'all_trash.php' ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800'; ?>">
                        <i class="fas fa-trash-alt w-5 text-indigo-400"></i>
                        <span class="font-medium">ถังขยะ</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="p-4 bg-slate-950 border-t border-slate-800">
            <div class="flex items-center gap-3 mb-4 px-2">
                <div
                    class="w-10 h-10 bg-indigo-500 rounded-xl flex items-center justify-center text-sm font-bold text-white shadow-inner uppercase">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold truncate text-white">
                        <?php echo $_SESSION['username'] ?? 'Administrator'; ?>
                    </p>
                    <p class="text-[10px] text-indigo-400 uppercase font-bold"><?php echo $user_role; ?></p>
                </div>
            </div>
            <a href="logout.php"
                class="flex items-center justify-center gap-2 w-full py-2.5 bg-red-500/10 text-red-500 rounded-xl text-sm font-bold hover:bg-red-500 hover:text-white transition-all">
                <i class="fas fa-power-off text-xs"></i> ออกจากระบบ
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-4 md:px-8 shrink-0">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <button onclick="toggleSidebar()"
                    class="md:hidden w-10 h-10 shrink-0 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-lg">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                <!-- Mobile Category Dropdown -->
                <div class="md:hidden flex-1 min-w-0 max-w-[200px]">
                    <select onchange="window.location.href=this.value" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs rounded-lg block p-2 focus:ring-indigo-500 focus:border-indigo-500 font-bold outline-none">
                        <option value="e_service.php" <?php echo $active_cat == 'main' ? 'selected' : ''; ?>>🏠 หน้าหลัก</option>
                        
                        <?php if ($user_role !== 'staff' && (can('docs') || $user_role == 'admin')): ?>
                        <option value="index.php" <?php echo $active_cat == 'system' ? 'selected' : ''; ?>>📄 จัดการเอกสาร</option>
                        <?php endif; ?>

                        <?php if (can('projects')): ?>
                        <option value="projects.php" <?php echo $active_cat == 'construction' ? 'selected' : ''; ?>>🔨 หมวดก่อสร้าง</option>
                        <?php endif; ?>
                        
                        <?php if ($user_role !== 'staff' && can('setup')): ?>
                        <option value="settings.php" <?php echo $active_cat == 'settings' ? 'selected' : ''; ?>>⚙️ ตั้งค่า</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Desktop Category Tabs -->
                <div class="hidden md:flex h-16 items-center shrink-0">
                    <a id="nav-home" href="e_service.php" class="h-full flex items-center px-8 text-sm font-bold border-r border-slate-200 transition-all <?php echo $active_cat == 'main' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'; ?>">
                        <i class="fas fa-home mr-2"></i>หน้าหลัก
                    </a>
                    
                    <?php if ($user_role !== 'staff' && (can('docs') || $user_role == 'admin')): ?>
                    <a id="nav-docs" href="index.php" class="h-full flex items-center px-8 text-sm font-bold border-r border-slate-200 transition-all <?php echo $active_cat == 'system' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'; ?>">
                        <i class="fas fa-th-large mr-2"></i>จัดการเอกสาร
                    </a>
                    <?php endif; ?>

                    <?php if (can('projects')): ?>
                    <a id="nav-construction" href="projects.php" class="h-full flex items-center px-8 text-sm font-bold border-r border-slate-200 transition-all <?php echo $active_cat == 'construction' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'; ?>">
                        <i class="fas fa-hammer mr-2"></i>หมวดก่อสร้าง
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($user_role !== 'staff' && can('setup')): ?>
                    <a id="nav-settings" href="settings.php" class="h-full flex items-center px-8 text-sm font-bold border-r border-slate-200 transition-all <?php echo $active_cat == 'settings' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'; ?>">
                        <i class="fas fa-cog mr-2"></i>ตั้งค่า
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button id="btn-tutorial" onclick="startTutorial()" class="flex items-center gap-2 px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition-all border border-indigo-100">
                    <i class="fas fa-magic"></i>
                    <span class="text-xs font-bold">สอนหน่อย</span>
                </button>
                <div class="hidden md:block text-right">
                    <div class="flex items-center justify-end gap-2 mb-0.5">
                        <span class="text-[10px] bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded font-black uppercase">
                            <?= $_SESSION['role'] ?? 'Guest'; ?>
                        </span>
                        <p class="text-sm font-black text-slate-700">
                            <?= !empty($company_name) ? $company_name : ($_SESSION['user_name'] ?? 'Guest User'); ?>
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 text-[10px] text-slate-400 font-medium">
                        <?php if (!empty($company_name)): ?>
                            <span class="font-bold text-slate-600"><?= $_SESSION['user_name'] ?? ''; ?></span>
                            <span class="text-slate-300">|</span>
                        <?php endif; ?>
                        <span>@<?= $_SESSION['user'] ?? '-'; ?></span>
                        <span class="text-slate-300">|</span>
                        <span>UID: <span class="text-slate-500 font-bold"><?= $_SESSION['user_id'] ?? '-'; ?></span></span>
                        
                        <?php if (!empty($_SESSION['sup_id'])): ?>
                            <span class="text-slate-300">|</span>
                            <span class="text-emerald-500 font-bold">COMP ID: <?= $_SESSION['sup_id']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50">
