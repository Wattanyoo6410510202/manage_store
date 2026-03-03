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

$current_page = basename($_SERVER['PHP_SELF']);

// --- ตั้งค่า Logic การ Active ของเมนู ---

// กลุ่มรายการเอกสาร (เพื่อให้เมนูหลัก "รายการเอกสาร" กางออก)
$doc_list_pages = [
    'doc_list.php',
    'view_quotation.php',
    'edit_quotation.php',
    'add_quotation.php',
    'pr_list.php',
    'view_pr.php',
    'edit_pr.php',
    'add_pr.php',
    'po_list.php',
];
$is_list_active = in_array($current_page, $doc_list_pages);

// กลุ่มหน้าใบเสนอราคา
$quotation_group = ['doc_list.php', 'view_quotation.php', 'edit_quotation.php', 'add_quotation.php'];
$is_quotation_active = in_array($current_page, $quotation_group);

// กลุ่มหน้าใบขอซื้อ
$pr_group = ['pr_list.php', 'view_pr.php', 'edit_pr.php', 'add_pr.php'];
$is_pr_active = in_array($current_page, $pr_group);

$po_group = ['po_list.php', 'view_po.php', 'edit_po.php', 'po_settings.php'];
$is_po_active = in_array($current_page, $po_group);

// กลุ่มหน้าตั้งค่า
$doc_setup_pages = ['index.php', 'settings.php', 'user_settings.php', 'settings_api.php', 'quotation_settings.php', 'pr_settings.php'];
$is_setup_active = in_array($current_page, $doc_setup_pages);

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProSystem | Management</title>

    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css">

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js"></script>

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
        class="w-72 bg-slate-900 text-slate-300 flex flex-col shrink-0 h-full shadow-2xl md:shadow-none">
        <div class="p-6 bg-slate-950 flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="bg-indigo-600 p-2 rounded-lg text-white shadow-lg shadow-indigo-500/20">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <span class="text-xl font-extrabold text-white tracking-tight">ProSystem</span>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <nav id="sidebar-nav" class="flex-1 p-4 space-y-1 overflow-y-auto">
            <p class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-[2px] mb-2">Main Menu</p>

            <a href="index.php"
                class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo ($current_page == 'index.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'hover:bg-slate-800'; ?>">
                <i
                    class="fas fa-th-large w-5 <?php echo ($current_page == 'index.php') ? 'text-white' : 'text-indigo-400'; ?>"></i>
                <span class="font-medium">จัดการเอกสาร</span>
            </a>

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
                        <span
                            class="absolute -left-[2px] w-[2px] h-6 bg-indigo-500 transition-opacity <?php echo $is_quotation_active ? 'opacity-100' : 'opacity-0'; ?>"></span>
                        <i class="fas fa-file-invoice text-[12px]"></i>
                        <span class="text-sm font-medium">ใบเสนอราคา</span>
                    </a>

                    <a href="pr_list.php"
                        class="group relative flex items-center gap-3 py-2 px-4 transition-all duration-200 <?php echo $is_pr_active ? 'text-indigo-400 bg-indigo-500/5' : 'text-slate-500 hover:text-slate-200'; ?>">
                        <span
                            class="absolute -left-[2px] w-[2px] h-6 bg-indigo-500 transition-opacity <?php echo $is_pr_active ? 'opacity-100' : 'opacity-0'; ?>"></span>
                        <i class="fas fa-cart-plus text-[12px]"></i>
                        <span class="text-sm font-medium">ใบขอซื้อ</span>
                    </a>

                    <a href="po_list.php"
                        class="group relative flex items-center gap-3 py-2 px-4 transition-all duration-200 <?php echo $is_po_active ? 'text-indigo-400 bg-indigo-500/5' : 'text-slate-500 hover:text-slate-200'; ?>">
                        <span
                            class="absolute -left-[2px] w-[2px] h-6 bg-indigo-500 transition-opacity <?php echo $is_po_active ? 'opacity-100' : 'opacity-0'; ?>"></span>
                        <i class="fas fa-file-signature text-[12px]"></i>
                        <span class="text-sm font-medium">ใบสั่งซื้อ (PO)</span>
                    </a>

                </div>
            </div>


            <a href="compare.php"
                class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'compare.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'hover:bg-slate-800'; ?>">
                <i class="fas fa-exchange-alt w-5 text-indigo-400"></i>
                <span class="font-medium">เปรียบเทียบราคา</span>
            </a>

            <a href="inventory.php"
                class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'inventory.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'hover:bg-slate-800'; ?>">
                <i class="fas fa-boxes w-5 text-indigo-400"></i>
                <span class="font-medium">คลังสินค้า</span>
            </a>

            <div class="my-4 border-t border-slate-800/50"></div>
            <p class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-[2px] mb-2">Settings</p>

            <a href="settings.php"
                class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'settings.php' ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800'; ?>">
                <i class="fas fa-cog w-5 text-indigo-400"></i>
                <span class="font-medium">ตั้งค่าระบบ</span>
            </a>

            <a href="all_trash.php"
                class="flex items-center gap-3 p-3 rounded-xl transition-all <?php echo $current_page == 'all_trash.php' ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800'; ?>">
                <i class="fas fa-trash-alt w-5 text-indigo-400"></i>
                <span class="font-medium">ถังขยะ</span>
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
        </nav>

        <div class="p-4 bg-slate-950 border-t border-slate-800">
            <div class="flex items-center gap-3 mb-4 px-2">
                <div
                    class="w-10 h-10 bg-indigo-500 rounded-xl flex items-center justify-center text-sm font-bold text-white shadow-inner">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold truncate text-white">
                        <?php echo $_SESSION['username'] ?? 'Administrator'; ?>
                    </p>
                    <p class="text-[10px] text-green-500 uppercase flex items-center gap-1">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> Online
                    </p>
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
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()"
                    class="md:hidden w-10 h-10 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-lg">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-lg font-bold text-slate-800">
                    <?php
                    $titles = [
                        'index.php' => 'แดชบอร์ดจัดการเอกสาร',
                        'doc_list.php' => 'รายการใบเสนอราคา',
                        'pr_list.php' => 'รายการใบขอซื้อ',
                        'compare.php' => 'เปรียบเทียบราคาสินค้า',
                        'inventory.php' => 'ระบบจัดการคลังสินค้า',
                        'settings.php' => 'ตั้งค่าข้อมูลบริษัท',
                        'user_settings.php' => 'จัดการสิทธิ์ผู้ใช้งาน',
                        'settings_api.php' => 'ตั้งค่าการเชื่อมต่อ API',
                        'view_quotation.php' => 'รายละเอียดใบเสนอราคา',
                        'view_pr.php' => 'รายละเอียดใบขอซื้อ',
                        'edit_quotation.php' => 'แก้ไขใบเสนอราคา',
                        'quotation_settings.php' => 'ตั้งค่าใบเสนอราคา',
                        'pr_settings.php' => 'ตั้งค่าใบขอซื้อ',
                        'po_list.php' => 'รายการใบสั่งซื้อ (PO)',
                    ];
                    echo $titles[$current_page] ?? 'ProSystem Management';
                    ?>
                </h1>
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden md:block text-right">
                    <p class="text-[10px] text-slate-400 font-medium uppercase tracking-wider">ยินดีต้อนรับ</p>
                    <p class="text-sm font-bold text-slate-700"><?php echo $_SESSION['username'] ?? 'User'; ?></p>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-8 bg-slate-50">