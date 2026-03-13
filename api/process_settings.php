<?php
require_once '../config.php';

// --- 1. กรณีการลบ (Delete) ---
if (isset($_GET['delete_id'])) {
    $did = intval($_GET['delete_id']);
    
    // ดึงชื่อไฟล์รูปทั้งหมดมาลบทิ้งก่อนลบ record
    $res = mysqli_query($conn, "SELECT logo_path, qr_code_path FROM suppliers WHERE id = $did");
    if ($data = mysqli_fetch_assoc($res)) {
        // ลบ Logo
        if ($data['logo_path'] != 'default-logo.png' && !empty($data['logo_path'])) {
            @unlink("../uploads/" . $data['logo_path']);
        }
        // ลบ QR Code
        if (!empty($data['qr_code_path'])) {
            @unlink("../uploads/" . $data['qr_code_path']);
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
    $bank_name    = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $bank_acc_name = mysqli_real_escape_string($conn, $_POST['bank_account_name']);
    $bank_acc_no   = mysqli_real_escape_string($conn, $_POST['bank_account_number']);
    
    $id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;

    // --- ส่วนที่ 1: จัดการไฟล์ Logo ---
    $logo_sql = ""; 
    $new_logo_name = "";
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $new_logo_name = "logo_" . time() . "_" . rand(100, 999) . "." . $ext;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], "../uploads/" . $new_logo_name)) {
            $logo_sql = ", logo_path = '$new_logo_name'";
            if ($id > 0) { // ลบรูปเก่าถ้าเป็นการ Update
                $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT logo_path FROM suppliers WHERE id = $id"));
                if ($old['logo_path'] != 'default-logo.png') @unlink("../uploads/" . $old['logo_path']);
            }
        }
    }

    // --- ส่วนที่ 2: จัดการไฟล์ QR Code (เพิ่มใหม่) ---
    $qr_sql = "";
    $new_qr_name = "";
    if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] == 0) {
        $ext = pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION);
        $new_qr_name = "qr_" . time() . "_" . rand(100, 999) . "." . $ext;
        
        if (move_uploaded_file($_FILES['qr_code']['tmp_name'], "../uploads/" . $new_qr_name)) {
            $qr_sql = ", qr_code_path = '$new_qr_name'";
            if ($id > 0) { // ลบ QR เก่าถ้าเป็นการ Update
                $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT qr_code_path FROM suppliers WHERE id = $id"));
                if (!empty($old['qr_code_path'])) @unlink("../uploads/" . $old['qr_code_path']);
            }
        }
    }

    if (isset($_POST['update_supplier'])) {
        // --- Mode: Update ---
        $sql = "UPDATE suppliers SET 
                company_name='$company_name', 
                tax_id='$tax_id', 
                contact_name='$contact_name', 
                phone='$phone', 
                address='$address', 
                email='$email',
                bank_name='$bank_name',
                bank_account_name='$bank_acc_name',
                bank_account_number='$bank_acc_no'
                $logo_sql 
                $qr_sql 
                WHERE id = $id";
    } else {
        // --- Mode: Insert ---
        $final_logo = !empty($new_logo_name) ? $new_logo_name : "default-logo.png";
        $final_qr   = !empty($new_qr_name) ? $new_qr_name : "";
        
        $sql = "INSERT INTO suppliers (
                    company_name, tax_id, contact_name, phone, address, 
                    logo_path, qr_code_path, email, bank_name, bank_account_name, bank_account_number
                ) VALUES (
                    '$company_name', '$tax_id', '$contact_name', '$phone', '$address', 
                    '$final_logo', '$final_qr', '$email', '$bank_name', '$bank_acc_name', '$bank_acc_no'
                )";
    }

    if (mysqli_query($conn, $sql)) {
        $_SESSION['flash_msg'] = 'success';
    } else {
        $_SESSION['flash_msg'] = 'error';
    }
    header("Location: ../settings.php");
    exit();
}