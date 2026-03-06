<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าจาก Form
    $project_name = mysqli_real_escape_string($conn, $_POST['project_name']);
    $project_no = mysqli_real_escape_string($conn, $_POST['project_no']);
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $contract_value = floatval($_POST['contract_value']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $project_remarks = mysqli_real_escape_string($conn, $_POST['project_remarks']);

    $contractor_name = mysqli_real_escape_string($conn, $_POST['contractor_name']);
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $bank_account_no = mysqli_real_escape_string($conn, $_POST['bank_account_no']);
    $bank_account_name = mysqli_real_escape_string($conn, $_POST['bank_account_name']);
    $retention_percent = floatval($_POST['retention_percent'] ?? 0);
    $wht_percent = floatval($_POST['wht_percent'] ?? 3);

    // --- ส่วนที่แก้ไขให้ครบ: คำนวณ VAT และ WHT ---

    // เช็ค Toggle VAT 7%
    $include_vat = isset($_POST['include_vat']) && $_POST['include_vat'] === 'yes';
    $total_vat_amount = $include_vat ? ($contract_value * 0.07) : 0;

    // เช็ค Toggle WHT (หัก ณ ที่จ่าย) - เพิ่มส่วนนี้
    $include_wht = isset($_POST['include_wht']) && $_POST['include_wht'] === 'yes';
    $total_wht_amount = $include_wht ? ($contract_value * ($wht_percent / 100)) : 0;

    // ยอดสุทธิ = มูลค่างาน + VAT - WHT
    $net_contract_value = $contract_value + $total_vat_amount - $total_wht_amount;

    // ----------------------------------------------

    // 3. จัดการไฟล์แนบ
    $attachment_name = "";
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $target_dir = "../uploads/projects/";
        if (!file_exists($target_dir))
            mkdir($target_dir, 0777, true);
        $file_ext = pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION);
        $attachment_name = "PJ_" . time() . "." . $file_ext;
        move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_dir . $attachment_name);
    }

    // 4. สร้างเลขที่โครงการอัตโนมัติ
    if (empty($project_no)) {
        $year = (date('Y') + 543) % 100;
        $project_no = "PJ-" . $year . "-" . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    }

    // 5. บันทึกลงตารางหลัก (เพิ่ม total_wht_amount เข้าไปใน SQL)
    $sql = "INSERT INTO projects (
                project_name, contractor_name, bank_name, bank_account_no, bank_account_name,
                project_no, customer_id, contract_value, total_vat_amount, total_wht_amount, 
                net_contract_value, start_date, end_date, attachment_path, retention_percent, 
                wht_percent, project_remarks, created_at
            ) VALUES (
                '$project_name', '$contractor_name', '$bank_name', '$bank_account_no', '$bank_account_name',
                '$project_no', '$customer_id', '$contract_value', '$total_vat_amount', '$total_wht_amount', 
                '$net_contract_value', '$start_date', '$end_date', '$attachment_name', '$retention_percent', 
                '$wht_percent', '$project_remarks', NOW()
            )";

    if (mysqli_query($conn, $sql)) {
        $project_id = mysqli_insert_id($conn);

        // 6. บันทึกเอกสารเชื่อมโยง
        if (!empty($_POST['doc_no'])) {
            foreach ($_POST['doc_no'] as $key => $val) {
                $doc_type = mysqli_real_escape_string($conn, $_POST['doc_type'][$key]);
                $doc_no = mysqli_real_escape_string($conn, $val);
                if (!empty($doc_no)) {
                    mysqli_query($conn, "INSERT INTO project_documents (project_id, doc_type, doc_no) 
                                        VALUES ('$project_id', '$doc_type', '$doc_no')");
                }
            }
        }

        // 7. บันทึกงวดงาน
        if (!empty($_POST['ms_name'])) {
            foreach ($_POST['ms_name'] as $key => $val) {
                $ms_name = mysqli_real_escape_string($conn, $val);
                $ms_amount = floatval($_POST['ms_amount'][$key]);
                $ms_no = $key + 1;
                if (!empty($ms_name)) {
                    mysqli_query($conn, "INSERT INTO project_milestones (project_id, milestone_no, milestone_name, amount) 
                                        VALUES ('$project_id', '$ms_no', '$ms_name', '$ms_amount')");
                }
            }
        }

        header("Location: ../projects.php");
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>