<?php
require_once '../config.php';

// --- 1. กรณีการลบ (Delete) ---
if (isset($_GET['delete_id'])) {
    $did = intval($_GET['delete_id']);
    $res = mysqli_query($conn, "SELECT logo_path FROM suppliers WHERE id = $did");
    if ($data = mysqli_fetch_assoc($res)) {
        if ($data['logo_path'] != 'default-logo.png') {
            $file_path = "../uploads/" . $data['logo_path'];
            if (file_exists($file_path)) @unlink($file_path);
        }
    }
    if (mysqli_query($conn, "DELETE FROM suppliers WHERE id = $did")) {
        $_SESSION['flash_msg'] = 'deleted';
    } else {
        $_SESSION['flash_msg'] = 'error';
    }
    header("Location: ../settings.php");
    exit();
}

// --- 2. กรณีการบันทึกใหม่ หรือ แก้ไข (Save/Update) ---
if (isset($_POST['save_supplier']) || isset($_POST['update_supplier'])) {
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $tax_id       = mysqli_real_escape_string($conn, $_POST['tax_id']);
    $contact_name = mysqli_real_escape_string($conn, $_POST['contact_name']);
    $phone        = mysqli_real_escape_string($conn, $_POST['phone']);
    $address      = mysqli_real_escape_string($conn, $_POST['address']);
    $email        = mysqli_real_escape_string($conn, $_POST['email']);
    
    // จัดการรูปภาพ
    $logo_sql = ""; 
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $new_name = "logo_" . time() . "_" . rand(1000, 9999) . "." . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], "../uploads/" . $new_name)) {
            $logo_sql = ", logo_path = '$new_name'";
        }
    }

    if (isset($_POST['update_supplier'])) {
        // Mode: Update
        $id = intval($_POST['supplier_id']);
        $sql = "UPDATE suppliers SET company_name='$company_name', tax_id='$tax_id', contact_name='$contact_name', phone='$phone', address='$address', email='$email' $logo_sql WHERE id = $id";
    } else {
        // Mode: Insert
        $final_logo = !empty($new_name) ? $new_name : "default-logo.png";
        $sql = "INSERT INTO suppliers (company_name, tax_id, contact_name, phone, address, logo_path, email) 
                VALUES ('$company_name', '$tax_id', '$contact_name', '$phone', '$address', '$final_logo', '$email')";
    }

    if (mysqli_query($conn, $sql)) {
        $_SESSION['flash_msg'] = 'success';
    } else {
        $_SESSION['flash_msg'] = 'error';
    }
    header("Location: ../settings.php");
    exit();
}