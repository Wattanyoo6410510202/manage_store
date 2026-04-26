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
    
    // เพิ่มการรับค่า VAT Type (0 = VAT ใน, 1 = VAT นอก)
    $has_vat = isset($_POST['vat_type_status']) ? intval($_POST['vat_type_status']) : 1;
    // ถ้าไม่ได้ติ๊กเปิด VAT 7% เลย อาจจะเก็บเป็น NULL หรือ 1 ตามต้องการ
    // แต่ถ้าเปิด VAT สวิตช์ vat_type_status จะเป็นตัวบอกว่าเป็น 0 หรือ 1
    
    $start_date = !empty($_POST['start_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'" : "NULL";
    $end_date = !empty($_POST['end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'" : "NULL";

    $current_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $created_by_sql = "";

    if ($current_user_id > 0) {
        $check_sql = "SELECT created_by FROM projects WHERE id = '$project_id'";
        $check_res = mysqli_query($conn, $check_sql);
        $row = mysqli_fetch_assoc($check_res);

        if (is_null($row['created_by']) || $row['created_by'] == 0) {
            $created_by_sql = ", created_by = '$current_user_id'";
        }
    }

    $contractor_name = mysqli_real_escape_string($conn, $_POST['contractor_name']);
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $bank_account_no = mysqli_real_escape_string($conn, $_POST['bank_account_no']);
    $bank_account_name = mysqli_real_escape_string($conn, $_POST['bank_account_name']);
    $project_remarks = mysqli_real_escape_string($conn, $_POST['project_remarks']);
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);

    // 1. รับค่าพื้นฐาน
    $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);

    // ดึงเลขที่โครงการมาใช้สร้างชื่อโฟลเดอร์
    $pj_res = mysqli_query($conn, "SELECT project_no FROM projects WHERE id = '$project_id'");
    $pj_data = mysqli_fetch_assoc($pj_res);
    $project_no = $pj_data['project_no'];

    // 2. จัดการไฟล์แนบ
    $target_dir = "../uploads/projects/" . $project_no . "/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

    function updateProjectFile($input_name, $prefix, $dir, $project_id, $column, $conn) {
        if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == 0) {
            $file_ext = pathinfo($_FILES[$input_name]["name"], PATHINFO_EXTENSION);
            $new_name = $prefix . "_" . time() . "_" . rand(100, 999) . "." . $file_ext;

            if (move_uploaded_file($_FILES[$input_name]["tmp_name"], $dir . $new_name)) {
                // ลบไฟล์เก่า
                $old_file_res = mysqli_query($conn, "SELECT $column FROM projects WHERE id = '$project_id'");
                $old_file = mysqli_fetch_assoc($old_file_res);
                if ($old_file[$column] && file_exists($dir . $old_file[$column])) {
                    unlink($dir . $old_file[$column]);
                }
                return ", $column = '$new_name'";
            }
        }
        return "";
    }
    $file_sql = updateProjectFile('attachment', 'PJ', $target_dir, $project_id, 'attachment_path', $conn);
    $file_sql .= updateProjectFile('attachment_contract', 'CON', $target_dir, $project_id, 'attachment_contract', $conn);
    $file_sql .= updateProjectFile('attachment_boq', 'BOQ', $target_dir, $project_id, 'attachment_boq', $conn);

    // 3. UPDATE ข้อมูลหลัก (เพิ่มฟิลด์ has_vat ลงไปด้วย)
    $sql_update = "UPDATE projects SET 
                    project_name = '$project_name',
                    customer_id = '$customer_id',
                    contract_value = '$contract_value',
                    has_vat = '$has_vat', 
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