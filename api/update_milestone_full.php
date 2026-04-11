<?php
require_once '../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าพื้นฐานและจัดการความปลอดภัย
    $id = intval($_POST['id']);
    $project_id = intval($_POST['project_id']);
    $milestone_name = mysqli_real_escape_string($conn, $_POST['milestone_name']);
    $amount = floatval($_POST['amount']);

    // รับค่าภาษี
    $vat_amount = floatval($_POST['vat_amount'] ?? 0);
    $wht_amount = floatval($_POST['wht_amount'] ?? 0);
    
    // --- รับค่าประเภท VAT (0=ใน, 1=นอก) ---
    $has_vat = isset($_POST['has_vat']) ? intval($_POST['has_vat']) : 1;

    // --- ส่วนที่เกี่ยวกับเงินประกัน / หักอื่นๆ ---
    $retention_percent = floatval($_POST['retention_percent'] ?? 0);
    $retention_amount = floatval($_POST['retention_amount'] ?? 0);
    $other_deduction_amount = floatval($_POST['other_deduction_amount'] ?? 0);
    $deduction_note = mysqli_real_escape_string($conn, $_POST['deduction_note'] ?? '');

    // ยอดสุทธิและยอดคงเหลือจาก JS
    $total_request_amount = floatval($_POST['total_request_amount']);
    $net_amount = $total_request_amount; 
    $remaining_balance = floatval($_POST['remaining_balance']);

    $claim_date = mysqli_real_escape_string($conn, $_POST['claim_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    // กำหนด % เพื่อลง Database (ถ้ามียอดเงินแปลว่ามีการใช้ภาษานั้นๆ)
    $vat_percent = ($vat_amount > 0) ? 7.00 : 0.00;
    $wht_percent = ($wht_amount > 0) ? 3.00 : 0.00;

    // 2. จัดการเรื่องไฟล์แนบ (Attachment)
    $update_file_query = "";
    if (isset($_FILES['claim_attachment']) && $_FILES['claim_attachment']['error'] == 0) {
        $target_dir = "../uploads/claims/";

        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // ดึงชื่อไฟล์เก่ามาลบทิ้งเพื่อประหยัดพื้นที่ Server
        $old_file_res = mysqli_query($conn, "SELECT claim_attachment FROM project_milestones WHERE id = $id");
        $old_file_row = mysqli_fetch_assoc($old_file_res);
        if ($old_file_row && !empty($old_file_row['claim_attachment'])) {
            $old_file_path = $target_dir . $old_file_row['claim_attachment'];
            if (file_exists($old_file_path)) {
                @unlink($old_file_path);
            }
        }

        $file_ext = pathinfo($_FILES["claim_attachment"]["name"], PATHINFO_EXTENSION);
        $new_file_name = "UPD_CLAIM_" . $id . "_" . time() . "." . $file_ext;

        if (move_uploaded_file($_FILES["claim_attachment"]["tmp_name"], $target_dir . $new_file_name)) {
            $update_file_query = ", claim_attachment = '$new_file_name'";
        }
    }

    // 3. ยิง SQL Update
    $sql = "UPDATE project_milestones SET 
                milestone_name = '$milestone_name',
                amount = '$amount',
                retention_percent = '$retention_percent',
                retention_amount = '$retention_amount',
                other_deduction_amount = '$other_deduction_amount',
                deduction_note = '$deduction_note',
                vat_percent = '$vat_percent',
                vat_amount = '$vat_amount',
                wht_percent = '$wht_percent',
                wht_amount = '$wht_amount',
                has_vat = '$has_vat',
                net_amount = '$net_amount',
                total_request_amount = '$total_request_amount',
                remaining_balance = '$remaining_balance',
                claim_date = '$claim_date',
                status = '$status',
                remarks = '$remarks'
                $update_file_query
            WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['flash_msg'] = 'update_success';
        header("Location: ../detail_project.php?id=$project_id");
        exit();
    } else {
        echo "Database Error: " . mysqli_error($conn);
    }
} else {
    echo "Invalid Request Method";
}
?>