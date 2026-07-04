<?php
require_once '../config.php';

// เช็คก่อนว่ามี session หรือยัง เพื่อแก้ปัญหา Notice: session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. เพิ่มผู้ใช้ใหม่ (Save) ---
if (isset($_POST['save_user'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    // รับค่าจากฟอร์ม (ในฟอร์มจารใช้ name="supplier_id" ผมเลยขอรับชื่อนี้แต่เอาไปลงคอลัมน์ sup_id)
    $sup_id = intval($_POST['supplier_id'] ?? 0);
    $line_token = mysqli_real_escape_string($conn, $_POST['line_token'] ?? '');
    $line_user_id = mysqli_real_escape_string($conn, $_POST['line_user_id'] ?? '');

    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['flash_msg'] = 'duplicate';
    } else {
        $sql = "INSERT INTO users (name, phone, line_token, line_user_id, username, password, role, sup_id) VALUES ('$name', '$phone', '$line_token', '$line_user_id', '$username', '$password', '$role', $sup_id)";
        $_SESSION['flash_msg'] = mysqli_query($conn, $sql) ? 'success' : 'error';
    }
    header("Location: ../user_settings.php");
    exit();
}

// --- 2. อัปเดตข้อมูล (Update) ---
if (isset($_POST['update_user'])) {
    $id = intval($_POST['user_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $role = $_POST['role'];
    $sup_id = intval($_POST['supplier_id'] ?? 0);
    $line_token = mysqli_real_escape_string($conn, $_POST['line_token'] ?? '');
    $line_user_id = mysqli_real_escape_string($conn, $_POST['line_user_id'] ?? '');

    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' AND id != $id");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['flash_msg'] = 'duplicate';
    } else {
        $pw_sql = "";
        if (!empty($_POST['password'])) {
            $new_pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pw_sql = ", password = '$new_pw'";
        }

        $sql = "UPDATE users SET name='$name', phone='$phone', line_token='$line_token', line_user_id='$line_user_id', username='$username', role='$role', sup_id=$sup_id $pw_sql WHERE id = $id";
        $_SESSION['flash_msg'] = mysqli_query($conn, $sql) ? 'updated' : 'error';
    }
    header("Location: ../user_settings.php");
    exit();
}

// --- 3. ลบผู้ใช้ (Delete) ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $my_id = $_SESSION['user_id'] ?? 0;

    if ($id == $my_id) {
        $_SESSION['flash_msg'] = 'cant_delete_self';
    } else {
        $_SESSION['flash_msg'] = mysqli_query($conn, "DELETE FROM users WHERE id = $id") ? 'deleted' : 'error';
    }
    header("Location: ../user_settings.php");
    exit();
}