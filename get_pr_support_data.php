<?php
// ตรวจสอบชื่อไฟล์ config ของจารให้ดีว่าชื่ออะไร (เช่น db_connect.php หรือ config.php)
include 'config.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// ป้องกัน Error กรณีไม่มีค่าส่งมา
$action = $_GET['action'] ?? '';
$sup_id = intval($_GET['sup_id'] ?? 0);
$expense_cat_id = intval($_GET['expense_cat_id'] ?? 0);
$user_role = $_SESSION['role'] ?? '';

if ($sup_id <= 0 && $action !== 'get_stores_by_expense_cat') {
    echo json_encode([]);
    exit;
}

$data = [];
$role_filter = " AND (roles = '' OR roles IS NULL OR FIND_IN_SET('$user_role', roles))";

if ($action == 'get_expense_cats') {
    $sql = "SELECT id, name FROM expense_categories WHERE sup_id = $sup_id $role_filter";
} elseif ($action == 'get_stores_by_expense_cat') {
    if ($expense_cat_id <= 0) { echo json_encode([]); exit; }
    $sql = "SELECT s.id, s.store_name
            FROM stores s
            INNER JOIN expense_category_stores ecs ON s.id = ecs.store_id
            WHERE ecs.expense_category_id = $expense_cat_id
            ORDER BY s.store_name ASC";
} elseif ($action == 'get_budget_types') {
    $sql = "SELECT b.id, b.name, 
                   (b.budget_amount + COALESCE((SELECT SUM(a.amount) FROM budget_adjustments a WHERE a.budget_type_id = b.id), 0)) as current_total_budget,
                   (SELECT SUM(p.grand_total) FROM pr p WHERE p.budget_type_id = b.id AND p.status = 'approved' AND p.deleted_at IS NULL) as total_spent
            FROM budget_types b WHERE b.sup_id = $sup_id AND b.status = 'approved' $role_filter";
} elseif ($action == 'get_objectives') {
    $sql = "SELECT id, name FROM pr_objectives WHERE sup_id = $sup_id $role_filter";
}

if (!empty($sql)) {
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

echo json_encode($data);