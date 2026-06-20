<?php
require_once '../config.php';

// --- 1. ลบร้านค้า ---
if (isset($_GET['delete_id'])) {
    $did = intval($_GET['delete_id']);
    if (mysqli_query($conn, "DELETE FROM stores WHERE id = $did")) {
        $_SESSION['flash_msg'] = 'deleted';
    } else {
        $_SESSION['flash_msg'] = 'error';
    }
    header("Location: ../store_settings.php");
    exit();
}

// --- 2. เพิ่มหรือแก้ไขร้านค้า ---
if (isset($_POST['save_store']) || isset($_POST['update_store'])) {
    $store_name     = mysqli_real_escape_string($conn, $_POST['store_name']);
    $phone          = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $address        = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person'] ?? '');
    $email          = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $line_id        = mysqli_real_escape_string($conn, $_POST['line_id'] ?? '');
    $notes          = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    $id = intval($_POST['store_id'] ?? 0);

    if (isset($_POST['update_store']) && $id > 0) {
        $sql = "UPDATE stores SET
                store_name='$store_name',
                phone='$phone',
                address='$address',
                contact_person='$contact_person',
                email='$email',
                line_id='$line_id',
                notes='$notes'
                WHERE id = $id";
    } else {
        $sql = "INSERT INTO stores (store_name, phone, address, contact_person, email, line_id, notes)
                VALUES ('$store_name', '$phone', '$address', '$contact_person', '$email', '$line_id', '$notes')";
    }

    if (mysqli_query($conn, $sql)) {
        $_SESSION['flash_msg'] = 'success';
    } else {
        $_SESSION['flash_msg'] = 'error';
    }
    header("Location: ../store_settings.php");
    exit();
}
