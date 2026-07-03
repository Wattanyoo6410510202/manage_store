<?php
ob_start(); // ป้องกันขยะหลุดออกไปนอก JSON
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ล้าง output buffer ที่อาจมีช่องว่างหลุดมา
if (ob_get_length())
    ob_clean();
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];
$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? '';

// 1. ลบรายตัว (GET) - เปลี่ยนเป็น Soft Delete
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // เช็คว่าหัวหน้างานอนุมัติแล้วหรือยัง (ยกเว้น admin)
    $check = mysqli_query($conn, "SELECT doc_no, approved_by_0 FROM pr WHERE id = '$id'");
    $pr_data = mysqli_fetch_assoc($check);
    if (!$pr_data) {
        $response['message'] = 'ไม่พบใบขอซื้อนี้';
        echo json_encode($response);
        exit;
    }
    if (!empty($pr_data['approved_by_0']) && $user_role !== 'admin' && strpos($user_role, 'procure') !== 0) {
        $response['message'] = 'ไม่สามารถลบได้ หัวหน้างานอนุมัติแล้ว';
        echo json_encode($response);
        exit;
    }

    $doc_no = $pr_data['doc_no'] ?? 'N/A';

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
        $clean_ids = array_map(function ($id) use ($conn) {
            return mysqli_real_escape_string($conn, $id);
        }, $ids);

        $id_list = implode("','", $clean_ids);

        // เช็คว่ามีรายการที่หัวหน้างานอนุมัติแล้วหรือไม่ (ยกเว้น admin)
        if ($user_role !== 'admin' && strpos($user_role, 'procure') !== 0) {
            $check = mysqli_query($conn, "SELECT id FROM pr WHERE id IN ('$id_list') AND approved_by_0 IS NOT NULL LIMIT 1");
            if (mysqli_num_rows($check) > 0) {
                $response['message'] = 'ไม่สามารถลบได้ มีรายการที่หัวหน้างานอนุมัติแล้ว';
                echo json_encode($response);
                exit;
            }
        }

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