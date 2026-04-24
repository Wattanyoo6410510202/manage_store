<?php
// ตรวจสอบชื่อไฟล์ config ของจารให้ดีว่าชื่ออะไร (เช่น db_connect.php หรือ config.php)
include 'config.php'; 

header('Content-Type: application/json');

// ป้องกัน Error กรณีไม่มีค่าส่งมา
$action = $_GET['action'] ?? '';
$sup_id = intval($_GET['sup_id'] ?? 0);

if ($sup_id <= 0) {
    echo json_encode([]);
    exit;
}

$data = [];
if ($action == 'get_expense_cats') {
    $sql = "SELECT id, name FROM expense_categories WHERE sup_id = $sup_id";
} elseif ($action == 'get_budget_types') {
    $sql = "SELECT id, name FROM budget_types WHERE sup_id = $sup_id";
} elseif ($action == 'get_objectives') {
    $sql = "SELECT id, name FROM pr_objectives WHERE sup_id = $sup_id";
}

if (!empty($sql)) {
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

echo json_encode($data);