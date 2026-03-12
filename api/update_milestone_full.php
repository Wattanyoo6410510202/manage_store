<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าพื้นฐานและจัดการความปลอดภัย
    $id = intval($_POST['id']);
    $project_id = intval($_POST['project_id']);
    $milestone_name = mysqli_real_escape_string($conn, $_POST['milestone_name']);
    $amount = floatval($_POST['amount']);

    // รับค่าภาษี
    $vat_amount = floatval($_POST['vat_amount']);
    $wht_amount = floatval($_POST['wht_amount']);

    // --- ส่วนที่เพิ่มมาใหม่: เงินประกัน / หักอื่นๆ ---
    $retention_percent = floatval($_POST['retention_percent'] ?? 0);
    $retention_amount = floatval($_POST['retention_amount'] ?? 0);
    $other_deduction_amount = floatval($_POST['other_deduction_amount'] ?? 0);
    $deduction_note = mysqli_real_escape_string($conn, $_POST['deduction_note'] ?? '');

    // ยอดสุทธิและยอดคงเหลือจาก JS
    $net_amount = floatval($_POST['total_request_amount']);
    $total_request_amount = $net_amount; // ใช้ตัวเดียวกัน
    $remaining_balance = floatval($_POST['remaining_balance']);

    $claim_date = mysqli_real_escape_string($conn, $_POST['claim_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    // 2. จัดการเรื่องไฟล์แนบ (Attachment)
    $update_file_query = "";
    if (isset($_FILES['claim_attachment']) && $_FILES['claim_attachment']['error'] == 0) {
        $target_dir = "../uploads/claims/";

        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // ดึงชื่อไฟล์เก่ามาลบทิ้ง
        $old_file_res = mysqli_query($conn, "SELECT claim_attachment FROM project_milestones WHERE id = $id");
        $old_file_row = mysqli_fetch_assoc($old_file_res);
        if (!empty($old_file_row['claim_attachment'])) {
            @unlink($target_dir . $old_file_row['claim_attachment']);
        }

        $file_ext = pathinfo($_FILES["claim_attachment"]["name"], PATHINFO_EXTENSION);
        $new_file_name = "UPD_CLAIM_" . $id . "_" . time() . "." . $file_ext;

        if (move_uploaded_file($_FILES["claim_attachment"]["tmp_name"], $target_dir . $new_file_name)) {
            $update_file_query = ", claim_attachment = '$new_file_name'";
        }
    }

    // 3. ยิง SQL Update (เพิ่มฟิลด์เกี่ยวกับ Retention เข้าไป)
    $sql = "UPDATE project_milestones SET 
                milestone_name = '$milestone_name',
                amount = '$amount',
                retention_percent = '$retention_percent',
                retention_amount = '$retention_amount',
                other_deduction_amount = '$other_deduction_amount',
                deduction_note = '$deduction_note',
                vat_amount = '$vat_amount',
                wht_amount = '$wht_amount',
                net_amount = '$net_amount',
                total_request_amount = '$total_request_amount',
                remaining_balance = '$remaining_balance',
                claim_date = '$claim_date',
                status = '$status',
                remarks = '$remarks'
                $update_file_query
            WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        // อัปเดตสำเร็จ
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