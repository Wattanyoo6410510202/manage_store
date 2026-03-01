<?php
include '../config.php';

$response = ['status' => 'error', 'message' => 'Invalid request'];

// 1. ลบรายตัว (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // ดึงเลขที่เอกสารมาแสดงใน Alert ก่อนลบ
    $get_doc = mysqli_query($conn, "SELECT doc_no FROM pr WHERE id = '$id'");
    $doc_data = mysqli_fetch_assoc($get_doc);
    $doc_no = $doc_data['doc_no'] ?? 'N/A';

    // ลบข้อมูล (แบบ Cascade: ลบรายการสินค้าในใบนั้นด้วย)
    // หมายเหตุ: หากฐานข้อมูลไม่ได้ตั้ง ON DELETE CASCADE ให้รันลบ pr_items ก่อน
    mysqli_query($conn, "DELETE FROM pr_items WHERE pr_id = '$id'"); 
    $delete = mysqli_query($conn, "DELETE FROM pr WHERE id = '$id'");

    if ($delete) {
        $response = ['status' => 'success', 'doc_no' => $doc_no];
    } else {
        $response['message'] = mysqli_error($conn);
    }
}

// 2. ลบแบบกลุ่ม (POST Bulk Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $ids = json_decode($_POST['ids'], true);
    
    if (is_array($ids) && count($ids) > 0) {
        $id_list = implode("','", array_map(function($id) use ($conn) {
            return mysqli_real_escape_string($conn, $id);
        }, $ids));

        // ลบรายการสินค้าและหัวเอกสาร
        mysqli_query($conn, "DELETE FROM pr_items WHERE pr_id IN ('$id_list')");
        $bulk_delete = mysqli_query($conn, "DELETE FROM pr WHERE id IN ('$id_list')");

        if ($bulk_delete) {
            $response = ['status' => 'success', 'count' => count($ids)];
        } else {
            $response['message'] = mysqli_error($conn);
        }
    }
}

echo json_encode($response);
mysqli_close($conn);