<?php
// ห้ามมีบรรทัดว่างข้างบนนี้เด็ดขาด!
ob_start(); // ป้องกันพวก warning/notice แอบแสดงผล
require_once '../config.php';
session_start();

// ล้าง output buffer ที่อาจมีช่องว่างหรือ error หลุดมา
if (ob_get_length())
    ob_clean();

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'ไม่สามารถทำรายการได้'];
$action = $_REQUEST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

function softDelete($conn, $ids, $user_id)
{
    if (empty($ids))
        return false;
    $id_list = implode(',', array_map('intval', $ids));

    // ตรวจสอบชื่อตารางให้ดีว่าใช้ quotations หรือ po
    $sql = "UPDATE quotations SET 
            deleted_at = NOW(), 
            deleted_by = $user_id 
            WHERE id IN ($id_list)";

    return mysqli_query($conn, $sql);
}

if ($action === 'bulk_delete' && isset($_POST['ids'])) {
    $ids = json_decode($_POST['ids']);
    if (is_array($ids)) {
        if (softDelete($conn, $ids, $user_id)) {
            $response = ['status' => 'success', 'message' => 'ย้ายรายการที่เลือกไปยังถังขยะแล้ว'];
        } else {
            $response = ['status' => 'error', 'message' => mysqli_error($conn)];
        }
    }
} elseif (isset($_GET['id'])) {
    $id = [intval($_GET['id'])];
    if (softDelete($conn, $id, $user_id)) {
        $response = ['status' => 'success', 'message' => 'ย้ายใบเสนอราคาไปยังถังขยะแล้ว'];
    } else {
        $response = ['status' => 'error', 'message' => mysqli_error($conn)];
    }
}

echo json_encode($response);
exit; // จบการทำงานทันทีห้ามมีอะไรต่อท้าย