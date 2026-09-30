<?php
// ตรวจสอบชื่อไฟล์ config ของจารให้ดีว่าชื่ออะไร (เช่น db_connect.php หรือ config.php)
include 'config.php';
require_once __DIR__ . '/budget_projects_lib.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// ป้องกัน Error กรณีไม่มีค่าส่งมา
$action = $_GET['action'] ?? '';
$sup_id = intval($_GET['sup_id'] ?? 0);
$expense_cat_id = intval($_GET['expense_cat_id'] ?? 0);
$user_role = $_SESSION['role'] ?? '';

if ($sup_id <= 0 && $action !== 'get_stores_by_expense_cat' && $action !== 'get_users_by_sup' && $action !== 'get_budget_projects' && !($user_role === 'procure' && $action === 'get_expense_cats')) {
    echo json_encode([]);
    exit;
}

$data = [];
$role_filter = " AND (roles = '' OR roles IS NULL OR FIND_IN_SET('$user_role', roles))";

if ($action == 'get_expense_cats') {
    // ถ้าเป็นจัดซื้อ ให้แสดงทั้งหมด ไม่กรอง sup_id และ role
    if ($user_role === 'procure') {
        $sql = "SELECT id, name FROM expense_categories";
    } else {
        $sql = "SELECT id, name FROM expense_categories WHERE sup_id = $sup_id $role_filter";
    }
} elseif ($action == 'get_stores_by_expense_cat') {
    if ($expense_cat_id <= 0) { echo json_encode([]); exit; }
    $sql = "SELECT s.id, s.store_name
            FROM stores s
            INNER JOIN expense_category_stores ecs ON s.id = ecs.store_id
            WHERE ecs.expense_category_id = $expense_cat_id
            ORDER BY s.store_name ASC";
} elseif ($action == 'get_budget_types') {
    // total_spent = ยอดที่ใช้ไม่ได้แล้ว (กันให้โครงการ + PR นอกโครงการ) ดังนั้น current_total_budget − total_spent = ยอดที่ออก PR ได้
    $exclude_pr_id = (int)($_GET['exclude_pr_id'] ?? 0);
    $sql = "SELECT bt.id, bt.name,
                   " . BUDGET_TOTAL_SQL . " as current_total_budget,
                   (" . BUDGET_TOTAL_SQL . " - " . budget_free_sql(0, $exclude_pr_id) . ") as total_spent,
                   (SELECT COUNT(*) FROM budget_projects bp WHERE bp.budget_type_id = bt.id AND bp.status = 'approved') as project_count
            FROM budget_types bt WHERE bt.sup_id = $sup_id AND bt.status = 'approved'
            " . str_replace('roles', 'bt.roles', $role_filter);
} elseif ($action == 'get_budget_projects') {
    // โครงการที่อนุมัติแล้วในงบนี้ พร้อมยอดคงเหลือของโครงการ
    $budget_type_id = (int)($_GET['budget_type_id'] ?? 0);
    $exclude_pr_id = (int)($_GET['exclude_pr_id'] ?? 0);
    if ($budget_type_id <= 0) { echo json_encode([]); exit; }
    $sql = "SELECT bp.id, bp.project_no, bp.name, bp.amount,
                   " . budget_project_pr_used_sql($exclude_pr_id) . " as used
            FROM budget_projects bp
            WHERE bp.budget_type_id = $budget_type_id AND bp.status = 'approved'
            ORDER BY bp.project_no ASC, bp.name ASC";
} elseif ($action == 'get_objectives') {
    $sql = "SELECT id, name FROM pr_objectives WHERE sup_id = $sup_id $role_filter";
} elseif ($action == 'get_users_by_sup') {
    if ($sup_id > 0) {
        $sql = "SELECT id, name, phone FROM users WHERE sup_id = $sup_id ORDER BY name ASC";
    } else {
        $sql = "SELECT id, name, phone FROM users ORDER BY name ASC";
    }
}

if (!empty($sql)) {
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

echo json_encode($data);