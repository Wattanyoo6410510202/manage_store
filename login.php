<?php
include "config.php";

// เริ่ม session หากยังไม่ได้เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $u = mysqli_real_escape_string($conn, $_POST['username']);
    $p_input = $_POST['password']; 

    // 1. ค้นหา User จาก username อย่างเดียวเพื่อดึงรหัสผ่านที่เก็บไว้มาเช็ค
    $sql = "SELECT * FROM users WHERE username = '$u' LIMIT 1";
    $q = mysqli_query($conn, $sql);

    if (mysqli_num_rows($q) > 0) {
        $user_data = mysqli_fetch_assoc($q);
        $stored_password = $user_data['password'];

        // 2. ตรวจสอบรหัสผ่าน (รองรับ MD5, Password Hash และแบบตัวอักษรตรงๆ สำหรับ admin 1234)
        $is_valid = false;
        
        if ($p_input === $stored_password) {
            // เช็คแบบตัวอักษรตรงๆ (สำหรับ id 4 ที่เก็บเป็น 1234)
            $is_valid = true;
        } elseif (md5($p_input) === $stored_password) {
            // เช็คแบบ MD5 (สำหรับ id 1)
            $is_valid = true;
        } elseif (password_verify($p_input, $stored_password)) {
            // เช็คแบบ Password Hash (สำหรับ id 3)
            $is_valid = true;
        }

        if ($is_valid) {
            // --- เก็บแบบที่คุณให้จำ (สำหรับระบบใหม่) ---
            $_SESSION['user_id']   = $user_data['id'];
            $_SESSION['user']      = $user_data['username']; // <--- index.php เช็คตัวนี้ในบรรทัดที่ 4
            $_SESSION['user_name'] = $user_data['name'];
            $_SESSION['role']      = $user_data['role'];
            
            // --- เก็บเพิ่มเพื่อให้ index.php แสดงผลได้ (สำหรับระบบเดิม) ---
            $_SESSION['username']  = $user_data['username']; // <--- index.php ใช้ตัวนี้โชว์ชื่อที่เมนู
            $_SESSION['is_logged_in'] = true;
            $_SESSION['login_time'] = time();

            header("Location: index.php");
            exit;
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบชื่อผู้ใช้งานนี้ในระบบ";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - ProSystem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1a1a1a;
            --accent-color: #6366f1; /* เปลี่ยนจากสีทองเป็นสี Indigo ตาม UI เดิมของคุณให้ดูทันสมัย */
            --accent-light: #818cf8;
        }

        body {
            background: radial-gradient(circle at center, #2c2c2c 0%, #121212 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', 'Sarabun', sans-serif;
            margin: 0;
            color: #fff;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background: rgba(26, 26, 26, 0.9);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
        }

        .login-logo {
            font-size: 2.5rem;
            color: var(--accent-color);
            text-align: center;
            margin-bottom: 10px;
        }

        .login-title {
            text-align: center;
            font-weight: 600;
            letter-spacing: 1px;
            margin-bottom: 30px;
            color: #fff;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff !important;
            padding: 14px 15px;
            border-radius: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
        }

        .btn-login {
            background: linear-gradient(45deg, var(--accent-color), var(--accent-light));
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 14px;
            border-radius: 15px;
            margin-top: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
            color: #fff;
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            padding: 12px;
            border-radius: 12px;
            text-align: center;
            font-size: 0.9rem;
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-logo">
        <i class="bi bi-shield-lock-fill"></i>
    </div>
    <h4 class="login-title">เข้าสู่ระบบจัดซื้อ</h4>

    <?php if(isset($error)): ?>
        <div class="error-msg">
            <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label small text-white-50 ms-2">ชื่อผู้ใช้งาน</label>
            <input name="username" class="form-control" placeholder="Username" required autofocus>
        </div>
        <div class="mb-4">
            <label class="form-label small text-white-50 ms-2">รหัสผ่าน</label>
            <input type="password" name="password" class="form-control" placeholder="Password" required>
        </div>
        <button type="submit" class="btn btn-login w-100">
            Sign In <i class="bi bi-arrow-right-short ms-1"></i>
        </button>
    </form>

    <div class="mt-4 text-center opacity-40 small text-uppercase tracking-widest">
        ProSystem v1.0
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>