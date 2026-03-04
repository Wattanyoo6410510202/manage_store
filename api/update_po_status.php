<?php
// ปิดการแสดง Error ออกทางหน้าจอ (ให้บันทึกลง log แทน) เพื่อไม่ให้ไปกวน JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config.php';
session_start();

// ล้าง Output Buffer เผื่อมี Space หรือ Bom หลุดมาจากไฟล์ config
if (ob_get_length())
    ob_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ']);
    exit;
}

$user_id = $_SESSION['user_id'];
$id = $_POST['id'] ?? '';
$action = $_POST['action'] ?? '';

if (empty($id) || $action !== 'approve') {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// ใช้งาน mysqli_real_escape_string เพื่อความปลอดภัย
$safe_id = mysqli_real_escape_string($conn, $id);

$sql = "UPDATE po SET 
        status = 'approved', 
        approved_by = '$user_id', 
        approved_at = NOW(),
        updated_at = NOW() 
        WHERE id = '$safe_id' 
        AND status = 'pending' 
        AND deleted_at IS NULL";

if (mysqli_query($conn, $sql)) {
    if (mysqli_affected_rows($conn) > 0) {
        $res_po = mysqli_query($conn, "SELECT doc_no FROM po WHERE id = '$safe_id'");
        $po_data = mysqli_fetch_assoc($res_po);

        echo json_encode([
            'status' => 'success',
            'message' => 'อนุมัติใบสั่งซื้อ ' . ($po_data['doc_no'] ?? '') . ' เรียบร้อยแล้ว'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'รายการนี้ถูกดำเนินการไปแล้ว']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'DB Error']);
}
exit;