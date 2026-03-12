<?php
ob_start();
require_once '../config.php';
session_start();

// ล้าง output buffer กันขยะหลุด
if (ob_get_length())
    ob_clean();
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];
$user_id = $_SESSION['user_id'] ?? 0; // ID ผู้ใช้งานที่ทำการลบ

// 1. ลบรายตัว (GET) - เปลี่ยนเป็น UPDATE แทน DELETE
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // ดึงเลขที่เอกสาร PO มาแสดงก่อน (เพื่อส่งกลับไปให้ JS ทำ Alert)
    $get_doc = mysqli_query($conn, "SELECT doc_no FROM po WHERE id = '$id'");
    $doc_data = mysqli_fetch_assoc($get_doc);
    $doc_no = $doc_data['doc_no'] ?? 'N/A';

    // ทำ Soft Delete: อัปเดตวันที่ลบ และคนลบ
    // หมายเหตุ: ไม่ต้องลบ po_items เพื่อรักษาประวัติข้อมูล
    $sql = "UPDATE po SET 
            deleted_at = NOW(), 
            deleted_by = '$user_id' 
            WHERE id = '$id'";

    $update = mysqli_query($conn, $sql);

    if ($update) {
        $response = ['status' => 'success', 'doc_no' => $doc_no, 'message' => 'ย้ายใบสั่งซื้อลงถังขยะเรียบร้อย'];
    } else {
        $response['message'] = mysqli_error($conn);
    }
}

// 2. ลบแบบกลุ่ม (POST Bulk Delete) - เปลี่ยนเป็น UPDATE แบบยกชุด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $ids = json_decode($_POST['ids'], true);

    if (is_array($ids) && count($ids) > 0) {
        // คลีนข้อมูล ID เพื่อความปลอดภัย
        $clean_ids = array_map(function ($id) use ($conn) {
            return mysqli_real_escape_string($conn, $id);
        }, $ids);

        $id_list = implode("','", $clean_ids);

        // อัปเดตสถานะลบทั้งกลุ่ม
        $sql_bulk = "UPDATE po SET 
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