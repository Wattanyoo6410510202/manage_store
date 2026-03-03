<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!$conn) { echo json_encode(['status' => 'error', 'message' => 'DB Connection Error']); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$response = ['status' => 'error', 'message' => 'Invalid Request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ''; 
    $table  = $_POST['table'] ?? '';  
    $ids    = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : [];
    
    $allowed_tables = ['pr', 'quotations', 'po'];
    if (!in_array($table, $allowed_tables)) {
        echo json_encode(['status' => 'error', 'message' => 'Table not allowed']); exit;
    }

    if (empty($ids)) { 
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID รายการ']); exit; 
    }

    // แก้ Scope $conn สำหรับ PHP ทุกเวอร์ชัน
    $clean_ids = array_map(function($id) use ($conn) {
        return mysqli_real_escape_string($conn, $id);
    }, $ids);
    
    $id_list = implode("','", $clean_ids);

    if ($action === 'restore') {
        $sql = "UPDATE $table SET deleted_at = NULL WHERE id IN ('$id_list')";
        $msg = "กู้คืนข้อมูลสำเร็จ";
    } 
    else if ($action === 'permanent_delete') {
        $item_table = ($table === 'quotations') ? 'quotation_items' : $table . "_items";
        $foreign_key = ($table === 'quotations') ? 'quotation_id' : $table . "_id";

        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "DELETE FROM $item_table WHERE $foreign_key IN ('$id_list')");
            mysqli_query($conn, "DELETE FROM $table WHERE id IN ('$id_list')");
            mysqli_commit($conn);
            $msg = "ลบข้อมูลถาวรเรียบร้อยแล้ว";
            $sql_success = true;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'ลบไม่สำเร็จ: ' . $e->getMessage()]); exit;
        }
    }

    $result = isset($sql) ? mysqli_query($conn, $sql) : ($sql_success ?? false);

    if ($result) {
        // ส่ง IDs กลับไปให้ JS ฝั่ง Client เพื่อสั่งลบแถวออกจากตาราง
        $response = [
            'status' => 'success', 
            'message' => $msg,
            'target_ids' => $ids, // ส่งคืน ID ที่ทำรายการสำเร็จ
            'table_type' => $table
        ];
    } else {
        $response['message'] = mysqli_error($conn);
    }
}

echo json_encode($response);
mysqli_close($conn);