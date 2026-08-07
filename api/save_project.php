<?php
require_once '../config.php';
require_once '../project_no.php';
if (session_status() === PHP_SESSION_NONE)
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าพื้นฐานจาก Form (ดักจับ SQL Injection)
    $project_name = mysqli_real_escape_string($conn, $_POST['project_name']);
    $project_no_input = trim((string)($_POST['project_no'] ?? ''));
    if ($project_no_input === '') {
        $project_no_input = generate_unique_project_no($conn);
    } elseif (project_no_exists($conn, $project_no_input)) {
        http_response_code(409);
        echo 'เลขที่โครงการนี้มีอยู่แล้ว กรุณาใช้เลขที่โครงการอื่น';
        exit;
    }
    $project_no = mysqli_real_escape_string($conn, $project_no_input);
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

    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : "NULL";
    $check_work_url = mysqli_real_escape_string($conn, $_POST['check_work_url'] ?? '');
    $contract_no = mysqli_real_escape_string($conn, $_POST['contract_no'] ?? '');
    $contract_date = !empty($_POST['contract_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['contract_date']) . "'" : "NULL";
    $work_location = mysqli_real_escape_string($conn, $_POST['work_location'] ?? '');
    $payment_days_after_acceptance = max(0, intval($_POST['payment_days_after_acceptance'] ?? 30));
    $created_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : "NULL";

    // --- 2. ส่วนที่คำนวณ VAT (0=ใน, 1=นอก) และ WHT ---

    // เช็คว่าเปิดใช้งาน VAT หรือไม่ (Checkbox หลัก)
    $is_vat_enabled = (isset($_POST['include_vat']) && $_POST['include_vat'] === 'yes');

    /**
     * รับค่าสถานะ VAT: 0 = VAT ใน, 1 = VAT นอก
     * ใช้เทคนิคส่งค่าจาก Frontend (Hidden + Checkbox)
     */
    $has_vat = isset($_POST['vat_type_status']) ? intval($_POST['vat_type_status']) : 1;

    $actual_base = $contract_value;
    $total_vat_amount = 0;
    $total_wht_amount = 0;

    if ($is_vat_enabled) {
        if ($has_vat === 0) {
            // กรณี 0: VAT ใน (ยอดที่กรอกคือยอดสุทธิรวม VAT แล้ว)
            $actual_base = $contract_value / 1.07;
            $total_vat_amount = $contract_value - $actual_base;
        } else {
            // กรณี 1: VAT นอก (ยอดที่กรอกคือฐานเงินต้น)
            $actual_base = $contract_value;
            $total_vat_amount = $contract_value * 0.07;
        }
    } else {
        /**
         * หากไม่เปิดใช้ VAT เลย ให้บันทึกเป็นค่าที่สื่อว่าไม่มี VAT
         * จารสามารถเลือกบันทึกเป็น 0 หรือ NULL ก็ได้ (ในที่นี้ขอใช้ NULL ตามโครงสร้างเดิมจาร)
         */
        $has_vat = "NULL";
    }

    // คำนวณ หัก ณ ที่จ่าย (WHT) จากฐานเงินต้นก่อนภาษีเสมอ
    $is_wht_enabled = (isset($_POST['include_wht']) && $_POST['include_wht'] === 'yes');
    if ($is_wht_enabled) {
        $total_wht_amount = $actual_base * ($wht_percent / 100);
    }

    // คำนวณยอดสุทธิ (Net Value) ที่ต้องทำสัญญา/จ่ายจริง
    if ($is_vat_enabled && $has_vat === 0) {
        // VAT ใน: ยอดจ่ายจริงคือ [ยอดที่กรอก] - [หัก ณ ที่จ่าย]
        $net_contract_value = $contract_value - $total_wht_amount;
    } else {
        // VAT นอก หรือ ไม่มี VAT: ยอดจ่ายจริงคือ [ยอดที่กรอก] + [VAT] - [หัก ณ ที่จ่าย]
        $net_contract_value = $contract_value + $total_vat_amount - $total_wht_amount;
    }
    // ----------------------------------------------

    // 4. สร้างเลขที่โครงการอัตโนมัติ (ขยับขึ้นมาเพื่อให้ได้เลขก่อนอัปโหลดไฟล์)
    // 3. จัดการไฟล์แนบ (แยกเป็นโฟลเดอร์ตามเลขที่โครงการ)
    $target_dir = "../uploads/projects/" . $project_no . "/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

    function uploadProjectFile($input_name, $prefix, $dir) {
        if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == 0) {
            $file_ext = pathinfo($_FILES[$input_name]["name"], PATHINFO_EXTENSION);
            $new_name = $prefix . "_" . time() . "_" . rand(100, 999) . "." . $file_ext;
            if (move_uploaded_file($_FILES[$input_name]["tmp_name"], $dir . $new_name)) {
                return $new_name;
            }
        }
        return "";
    }

    $attachment_name = uploadProjectFile('attachment', 'PJ', $target_dir);
    $attachment_contract = uploadProjectFile('attachment_contract', 'CON', $target_dir);
    $attachment_boq = uploadProjectFile('attachment_boq', 'BOQ', $target_dir);

    // 5. บันทึกลงตารางหลัก
    $sql = "INSERT INTO projects (
                project_name, contractor_name, bank_name, bank_account_no, bank_account_name,
                project_no, customer_id, contract_value, total_vat_amount, total_wht_amount, 
                net_contract_value, has_vat, start_date, end_date, attachment_path, 
                attachment_contract, attachment_boq,
                retention_percent, supplier_id, 
                wht_percent, check_work_url, project_remarks, created_by, created_at, project_status,
                contract_no, contract_date, work_location, payment_days_after_acceptance
            ) VALUES (
                '$project_name', '$contractor_name', '$bank_name', '$bank_account_no', '$bank_account_name',
                '$project_no', '$customer_id', '$contract_value', '$total_vat_amount', '$total_wht_amount', 
                '$net_contract_value', $has_vat, '$start_date', '$end_date', '$attachment_name', 
                '$attachment_contract', '$attachment_boq',
                '$retention_percent', $supplier_id,
                '$wht_percent', '$check_work_url', '$project_remarks', $created_by, NOW(), 'on_hold',
                '$contract_no', $contract_date, '$work_location', '$payment_days_after_acceptance'
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

        $_SESSION['flash_msg'] = 'add_success';
        header("Location: ../projects.php");
        exit();
    } else {
        $insertError = mysqli_error($conn);
        if (mysqli_errno($conn) === 1062) {
            http_response_code(409);
            echo 'เลขที่โครงการนี้มีอยู่แล้ว กรุณาใช้เลขที่โครงการอื่น';
        } else {
            http_response_code(500);
            echo 'ไม่สามารถบันทึกโครงการได้: ' . $insertError;
        }
    }
}
?>
