<?php
ob_start(); // ป้องกันขยะหลุดออกไปนอก JSON
require_once '../config.php';
session_start();

// ล้าง output buffer ที่อาจมีช่องว่างหลุดมา
if (ob_get_length())
    ob_clean();
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];
$user_id = $_SESSION['user_id'] ?? 0; // เก็บ ID คนลบไว้ตรวจสอบ

// 1. ลบรายตัว (GET) - เปลี่ยนเป็น Soft Delete
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // ดึงเลขที่เอกสารมาแสดงใน Alert (เหมือนเดิม)
    $get_doc = mysqli_query($conn, "SELECT doc_no FROM pr WHERE id = '$id'");
    $doc_data = mysqli_fetch_assoc($get_doc);
    $doc_no = $doc_data['doc_no'] ?? 'N/A';

    // ใช้ UPDATE แทน DELETE เพื่อทำ Soft Delete
    // เราไม่ลบ pr_items เพื่อรักษา Data Integrity ไว้
    $sql = "UPDATE pr SET 
            deleted_at = NOW(), 
            deleted_by = '$user_id' 
            WHERE id = '$id'";

    $update = mysqli_query($conn, $sql);

    if ($update) {
        $response = ['status' => 'success', 'doc_no' => $doc_no, 'message' => 'ย้ายใบ PR ลงถังขยะเรียบร้อย'];
    } else {
        $response['message'] = mysqli_error($conn);
    }
}

// 2. ลบแบบกลุ่ม (POST Bulk Delete) - เปลี่ยนเป็น Soft Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $ids = json_decode($_POST['ids'], true);

    if (is_array($ids) && count($ids) > 0) {
        // ทำความสะอาด IDs ป้องกัน SQL Injection
        $clean_ids = array_map(function ($id) use ($conn) {
            return mysqli_real_escape_string($conn, $id);
        }, $ids);

        $id_list = implode("','", $clean_ids);

        // UPDATE พร้อมกันทั้งกลุ่ม
        $sql_bulk = "UPDATE pr SET 
                     deleted_at = NOW(), 
                     deleted_by = '$user_id' 
                     WHERE id IN ('$id_list')";

        $bulk_update = mysqli_query($conn, $sql_bulk);

        if ($bulk_update) {
            $response = ['status' => 'success', 'count' => count($ids), 'message' => 'ย้ายรายการที่เลือกไปยังถังขยะแล้ว'];
        } else {
            $response['message'] = mysqli_error($conn);
        }
    }
}

echo json_encode($response);
mysqli_close($conn);
exit;