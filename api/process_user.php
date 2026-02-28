<?php
require_once '../config.php';
session_start();

// --- 1. เพิ่มผู้ใช้ใหม่ (Save) ---
if (isset($_POST['save_user'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // ✅ ตรวจสอบ Username ซ้ำ (เช็คทั้งตาราง)
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['flash_msg'] = 'duplicate'; // แจ้งเตือนว่าชื่อซ้ำ
    } else {
        $sql = "INSERT INTO users (name, username, password, role) VALUES ('$name', '$username', '$password', '$role')";
        $_SESSION['flash_msg'] = mysqli_query($conn, $sql) ? 'success' : 'error';
    }
    header("Location: ../user_settings.php");
    exit();
}

// --- 2. อัปเดตข้อมูล (Update) ---
if (isset($_POST['update_user'])) {
    $id = intval($_POST['user_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $role = $_POST['role'];

    // ✅ ตรวจสอบ Username ซ้ำ (ต้องไม่ซ้ำกับคนอื่น ยกเว้นตัวเอง)
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' AND id != $id");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['flash_msg'] = 'duplicate'; // ถ้าชื่อไปตรงกับคนอื่นที่ไม่ใช่ id ตัวเอง
    } else {
        // จัดการเรื่องรหัสผ่าน (ถ้ามีการกรอกใหม่)
        $pw_sql = "";
        if (!empty($_POST['password'])) {
            $new_pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pw_sql = ", password = '$new_pw'";
        }

        $sql = "UPDATE users SET name='$name', username='$username', role='$role' $pw_sql WHERE id = $id";
        $_SESSION['flash_msg'] = mysqli_query($conn, $sql) ? 'updated' : 'error';
    }
    header("Location: ../user_settings.php");
    exit();
}

// --- 3. ลบผู้ใช้ (Delete) ---
// (โค้ดเดิมของคุณดีอยู่แล้วครับ)
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);

    // ดึง ID ของคนที่กำลังใช้งานอยู่จาก Session ตรงๆ (ถ้าเราเก็บ $_SESSION['user_id'] ไว้)
    $my_id = $_SESSION['user_id'] ?? 0;

    if ($id == $my_id) {
        $_SESSION['flash_msg'] = 'cant_delete_self';
    } else {
        $_SESSION['flash_msg'] = mysqli_query($conn, "DELETE FROM users WHERE id = $id") ? 'deleted' : 'error';
    }
    header("Location: ../user_settings.php");
    exit();
}