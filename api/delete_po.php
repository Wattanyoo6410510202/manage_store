<?php
include '../config.php';

$response = ['status' => 'error', 'message' => 'Invalid request'];

// 1. ลบรายตัว (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // ดึงเลขที่เอกสาร PO มาแสดงก่อนลบ
    $get_doc = mysqli_query($conn, "SELECT doc_no FROM po WHERE id = '$id'");
    $doc_data = mysqli_fetch_assoc($get_doc);
    $doc_no = $doc_data['doc_no'] ?? 'N/A';

    // เริ่มการลบ (ลบรายการสินค้าใน po_items ก่อนเพื่อป้องกัน Foreign Key Error)
    mysqli_query($conn, "DELETE FROM po_items WHERE po_id = '$id'"); 
    $delete = mysqli_query($conn, "DELETE FROM po WHERE id = '$id'");

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
        // คลีนข้อมูล ID เพื่อความปลอดภัย
        $id_list = implode("','", array_map(function($id) use ($conn) {
            return mysqli_real_escape_string($conn, $id);
        }, $ids));

        // ลบรายการสินค้าในตาราง po_items และหัวเอกสารในตาราง po
        mysqli_query($conn, "DELETE FROM po_items WHERE po_id IN ('$id_list')");
        $bulk_delete = mysqli_query($conn, "DELETE FROM po WHERE id IN ('$id_list')");

        if ($bulk_delete) {
            $response = ['status' => 'success', 'count' => count($ids)];
        } else {
            $response['message'] = mysqli_error($conn);
        }
    }
}

echo json_encode($response);
mysqli_close($conn);