<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id = intval($_POST['project_id']);
    $milestone_name = mysqli_real_escape_string($conn, $_POST['milestone_name']);
    $claim_date = $_POST['claim_date'];
    $status = isset($_POST['status']) ? mysqli_real_escape_string($conn, $_POST['status']) : 'pending';
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    $amount = floatval($_POST['amount']);
    $vat_amount = floatval($_POST['vat_amount']);
    $wht_amount = floatval($_POST['wht_amount']);
    $total_request_amount = floatval($_POST['total_request_amount']);
    $remaining_balance = floatval($_POST['remaining_balance']);

    $attachment_name = "";
    if (isset($_FILES['claim_attachment']) && $_FILES['claim_attachment']['error'] == 0) {
        $target_dir = "../uploads/claims/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        $file_ext = pathinfo($_FILES["claim_attachment"]["name"], PATHINFO_EXTENSION);
        $attachment_name = "CLAIM_" . $project_id . "_" . time() . "." . $file_ext;
        move_uploaded_file($_FILES["claim_attachment"]["tmp_name"], $target_dir . $attachment_name);
    }

    $sql = "INSERT INTO project_milestones (
                project_id, milestone_name, amount, 
                vat_percent, vat_amount, wht_percent, wht_amount, 
                total_request_amount, remaining_balance, 
                claim_date, status, claim_attachment, remarks, created_at
            ) VALUES (
                '$project_id', '$milestone_name', '$amount', 
                '7.00', '$vat_amount', '3.00', '$wht_amount', 
                '$total_request_amount', '$remaining_balance', 
                '$claim_date', '$status', '$attachment_name', '$remarks', NOW()
            )";

    if (mysqli_query($conn, $sql)) {
        header("Location: ../view_project.php?id=$project_id&save=success");
    } else {
        echo "SQL Error: " . mysqli_error($conn);
    }
}