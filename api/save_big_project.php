<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? '';

if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    mysqli_query($conn, "UPDATE big_projects SET deleted_at = NOW() WHERE id = $id");
    $_SESSION['flash_msg'] = 'delete_success';
    header("Location: ../big_projects.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : "NULL";
    $contract_value = floatval($_POST['contract_value'] ?? 0);
    $total_vat_amount = floatval($_POST['total_vat_amount'] ?? 0);
    $total_wht_amount = floatval($_POST['total_wht_amount'] ?? 0);
    $net_contract_value = floatval($_POST['net_contract_value'] ?? 0);
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'active');
    $start_date = !empty($_POST['start_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'" : "NULL";
    $end_date = !empty($_POST['end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'" : "NULL";
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
    $created_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : "NULL";
    $project_ids = $_POST['project_ids'] ?? [];

    if ($id > 0) {
        $sql = "UPDATE big_projects SET 
                name = '$name',
                description = '$description',
                customer_id = $customer_id,
                contract_value = '$contract_value',
                total_vat_amount = '$total_vat_amount',
                total_wht_amount = '$total_wht_amount',
                net_contract_value = '$net_contract_value',
                status = '$status',
                start_date = $start_date,
                end_date = $end_date,
                remarks = '$remarks'
                WHERE id = $id";
    } else {
        $sql = "INSERT INTO big_projects (name, description, customer_id, contract_value, total_vat_amount, total_wht_amount, net_contract_value, status, start_date, end_date, remarks, created_by, created_at)
                VALUES ('$name', '$description', $customer_id, '$contract_value', '$total_vat_amount', '$total_wht_amount', '$net_contract_value', '$status', $start_date, $end_date, '$remarks', $created_by, NOW())";
    }

    if (mysqli_query($conn, $sql)) {
        if ($id > 0) {
            $big_project_id = $id;
        } else {
            $big_project_id = mysqli_insert_id($conn);
        }

        if ($id > 0) {
            mysqli_query($conn, "DELETE FROM big_project_items WHERE big_project_id = $big_project_id");
        }

        if (!empty($project_ids)) {
            foreach ($project_ids as $pid) {
                $pid = intval($pid);
                if ($pid > 0) {
                    mysqli_query($conn, "INSERT IGNORE INTO big_project_items (big_project_id, project_id) VALUES ($big_project_id, $pid)");
                }
            }
        }

        $_SESSION['flash_msg'] = $id > 0 ? 'edit_success' : 'add_success';
        header("Location: ../big_projects.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
