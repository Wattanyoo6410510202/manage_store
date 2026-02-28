<?php
require_once '../config.php';
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'ไม่สามารถทำรายการได้'];

// รับค่าจากทั้ง GET (ลบรายตัว) และ POST (Bulk Delete)
$action = $_REQUEST['action'] ?? '';

if ($action === 'bulk_delete' && isset($_POST['ids'])) {
    $ids = json_decode($_POST['ids']);
    if (is_array($ids)) {
        $id_list = implode(',', array_map('intval', $ids));
        
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "DELETE FROM quotation_items WHERE quotation_id IN ($id_list)");
            mysqli_query($conn, "DELETE FROM quotations WHERE id IN ($id_list)");
            mysqli_commit($conn);
            $response = ['status' => 'success', 'message' => 'ลบรายการเรียบร้อยแล้ว'];
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $response = ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
} elseif (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "DELETE FROM quotation_items WHERE quotation_id = $id");
        mysqli_query($conn, "DELETE FROM quotations WHERE id = $id");
        mysqli_commit($conn);
        $response = ['status' => 'success', 'message' => 'ลบใบเสนอราคาเรียบร้อยแล้ว'];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

echo json_encode($response);