<?php
/**
 * ไฟล์นี้ต้องเริ่มด้วย <?php ในบรรทัดแรกสุด 
 * ห้ามมีช่องว่างหรือเว้นบรรทัดก่อนหน้าเด็ดขาด
 */

require_once '../config.php';
header('Content-Type: application/json');

// ป้องกันการพ่น Error เป็น HTML ออกมา (ให้ไปพ่นใน Log แทน)
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
$user_id = $_SESSION['user_id'] ?? 0;

// เตรียมตัวแปรคำตอบเริ่มต้น
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['id'])) {

    // 1. รับค่า IDs
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
    } else {
        $single_id = $_POST['id'] ?? $_GET['id'] ?? null;
        $ids = $single_id ? [$single_id] : [];
    }

    if (empty($ids)) {
        $response['message'] = 'ไม่พบรหัสรายการที่ต้องการ';
        echo json_encode($response);
        exit;
    }

    // 2. ทำความสะอาด IDs ป้องกัน SQL Injection
    $clean_ids = array_map(function ($id) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $id) . "'";
    }, $ids);
    $id_list = implode(',', $clean_ids);

    // 3. รันคำสั่ง SQL
    $sql = "UPDATE invoices SET 
            deleted_at = NOW(), 
            deleted_by = '$user_id',
            status = 'canceled' 
            WHERE id IN ($id_list)";

    if (mysqli_query($conn, $sql)) {
        $response = [
            'status' => 'deleted',
            'message' => 'ย้ายข้อมูลเรียบร้อยแล้ว'
        ];
    } else {
        $response['message'] = 'DB Error: ' . mysqli_error($conn);
    }

    // 4. ส่งคำตอบกลับเป็น JSON เพียงครั้งเดียวตอนท้าย
    echo json_encode($response);
    exit;
}