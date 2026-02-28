<?php
$conn = mysqli_connect("localhost", "root", "", "procurement_system");
if (!$conn) {
    die("Database Error");
}

// แก้ไขบรรทัดนี้: เช็คก่อนว่ามี session หรือยัง ถ้ายังไม่มีค่อย start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>