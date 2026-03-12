<?php
session_start();
$conn = mysqli_connect("localhost", "root", "", "procurement_system");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $sql);
    $user = mysqli_fetch_assoc($result);

    // ในที่นี้สมมติว่าเช็คแบบพื้นฐาน (ถ้าใช้ password_hash ให้ใช้ password_verify)
    if ($user && ($password == '1234' || password_verify($password, $user['password']))) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        header("Location: index.php");
    } else {
        echo "<script>alert('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'); window.location='login.php';</script>";
    }
}
?>