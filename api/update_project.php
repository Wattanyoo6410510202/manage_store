<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าพื้นฐาน
    $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);
    $project_name = mysqli_real_escape_string($conn, $_POST['project_name']);
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $contract_value = floatval($_POST['contract_value']);
    $total_vat_amount = floatval($_POST['total_vat_amount']);
    $total_wht_amount = floatval($_POST['total_wht_amount'] ?? 0);
    $net_contract_value = floatval($_POST['net_contract_value']);
    $start_date = !empty($_POST['start_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'" : "NULL";
    $end_date = !empty($_POST['end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'" : "NULL";

    $current_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $created_by_sql = "";

    if ($current_user_id > 0) {
        // เช็คว่าของเดิมใน DB เป็น NULL หรือ 0 หรือไม่
        $check_sql = "SELECT created_by FROM projects WHERE id = '$project_id'";
        $check_res = mysqli_query($conn, $check_sql);
        $row = mysqli_fetch_assoc($check_res);

        if (is_null($row['created_by']) || $row['created_by'] == 0) {
            // ถ้าว่าง ให้เตรียม SQL เพื่อ Update ค่านี้เข้าไปด้วย
            $created_by_sql = ", created_by = '$current_user_id'";
        }
    }

    // ข้อมูลผู้รับจ้าง & ธนาคาร
    $contractor_name = mysqli_real_escape_string($conn, $_POST['contractor_name']);
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $bank_account_no = mysqli_real_escape_string($conn, $_POST['bank_account_no']);
    $bank_account_name = mysqli_real_escape_string($conn, $_POST['bank_account_name']);
    $project_remarks = mysqli_real_escape_string($conn, $_POST['project_remarks']);
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);

    // 2. จัดการไฟล์แนบ (ถ้ามีการอัปโหลดใหม่)
    $file_sql = "";
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $target_dir = "../uploads/projects/";
        if (!file_exists($target_dir))
            mkdir($target_dir, 0777, true);

        $file_ext = pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION);
        $new_file_name = "PJ_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_file_name;

        if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
            // ดึงชื่อไฟล์เก่ามาลบทิ้ง (เพื่อไม่ให้เปลืองที่ Server)
            $old_file_res = mysqli_query($conn, "SELECT attachment_path FROM projects WHERE id = '$project_id'");
            $old_file = mysqli_fetch_assoc($old_file_res);
            if ($old_file['attachment_path'] && file_exists($target_dir . $old_file['attachment_path'])) {
                unlink($target_dir . $old_file['attachment_path']);
            }
            $file_sql = ", attachment_path = '$new_file_name'";
        }
    }

    // 3. UPDATE ข้อมูลหลักในตาราง projects
    $sql_update = "UPDATE projects SET 
                    project_name = '$project_name',
                    customer_id = '$customer_id',
                    contract_value = '$contract_value',
                    total_vat_amount = '$total_vat_amount',
                    total_wht_amount = '$total_wht_amount',
                    net_contract_value = '$net_contract_value',
                    start_date = $start_date,
                    end_date = $end_date,
                    contractor_name = '$contractor_name',
                    bank_name = '$bank_name',
                    bank_account_no = '$bank_account_no',
                    bank_account_name = '$bank_account_name',
                    project_remarks = '$project_remarks',
                    supplier_id = '$supplier_id',
                    updated_at = NOW()
                    $file_sql
                    $created_by_sql
                  WHERE id = '$project_id'";

    if (mysqli_query($conn, $sql_update)) {

        // 4. จัดการเอกสารเชื่อมโยง (Delete & Re-insert)
        mysqli_query($conn, "DELETE FROM project_documents WHERE project_id = '$project_id'");

        if (isset($_POST['doc_no']) && is_array($_POST['doc_no'])) {
            foreach ($_POST['doc_no'] as $key => $doc_no) {
                if (!empty($doc_no)) {
                    $doc_type = mysqli_real_escape_string($conn, $_POST['doc_type'][$key]);
                    $doc_no_clean = mysqli_real_escape_string($conn, $doc_no);
                    mysqli_query($conn, "INSERT INTO project_documents (project_id, doc_type, doc_no) 
                                         VALUES ('$project_id', '$doc_type', '$doc_no_clean')");
                }
            }
        }

        $_SESSION['flash_msg'] = 'update_success';
        header("Location: ../projects.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>