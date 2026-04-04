<?php
// เรียกไฟล์ config ของจาร (ที่มี $conn = mysqli_connect...)
require_once '../config.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $new_note = isset($_POST['new_note']) ? $_POST['new_note'] : 'คืนเงินประกัน';

    try {
        // 1. ดึงข้อมูลเดิมมาคำนวณยอดใหม่ (ใช้ mysqli)
        $query = "SELECT amount, vat_amount, wht_amount FROM project_milestones WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        if ($row) {
            // สูตรคำนวณใหม่: คืนทุกอย่างเป็นยอดเต็ม
            $new_total_request = $row['amount']; 
            $new_net_amount = $new_total_request + $row['vat_amount'] - $row['wht_amount'];

            // 2. อัปเดตล้างค่า Retention และ Other Deduction เป็น 0
            $update_query = "UPDATE project_milestones SET 
                retention_percent = 0, 
                retention_amount = 0, 
                other_deduction_amount = 0, 
                deduction_note = ?, 
                total_request_amount = ?, 
                net_amount = ? 
                WHERE id = ?";
            
            $update_stmt = mysqli_prepare($conn, $update_query);
            // "sd d i" คือ string, double, double, integer ตามลำดับฟิลด์
            mysqli_stmt_bind_param($update_stmt, "sddi", 
                $new_note, 
                $new_total_request, 
                $new_net_amount, 
                $id
            );
            
            if (mysqli_stmt_execute($update_stmt)) {
                echo json_encode(['status' => 'success', 'message' => 'คืนเงินประกันและปรับยอดเรียบร้อยแล้ว']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถอัปเดตข้อมูลได้: ' . mysqli_error($conn)]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลรายการนี้ในระบบ']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
}