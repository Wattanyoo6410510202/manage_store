<?php
/**
 * Smart Login (SSO) จาก IT Control Center → ProSystem
 *
 * sso.php (ดาวน์โหลดจาก IT Control Center มีกุญแจลับ ห้าม commit) ตรวจ token แล้วเรียกฟังก์ชันนี้
 * ใน sso.php ให้แก้ itcc_sso_login() เป็น:
 *
 *   require_once __DIR__ . '/sso_login_lib.php';
 *   return prosystem_sso_login($username, $claims);
 *
 * และตั้ง const ITCC_SSO_REDIRECT = 'e_service.php';
 */

function prosystem_sso_login(string $username, array $claims): bool
{
    require __DIR__ . '/config.php'; // ต่อฐานข้อมูล ($conn) + session_start() แบบเดียวกับ login.php

    $centralId = trim((string)($claims['cid'] ?? ''));

    // 1) หาจาก ID กลางก่อน
    $user = null;
    if ($centralId !== '') {
        $st = mysqli_prepare($conn, 'SELECT * FROM users WHERE central_id = ? LIMIT 1');
        mysqli_stmt_bind_param($st, 's', $centralId);
        mysqli_stmt_execute($st);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: null;
    }

    // 2) ไม่เจอ → ใช้ username แล้วผูก ID กลางให้อัตโนมัติ (เฉพาะ user ที่ยังไม่เคยผูก)
    if (!$user) {
        $st = mysqli_prepare($conn, 'SELECT * FROM users WHERE username = ? AND central_id IS NULL LIMIT 1');
        mysqli_stmt_bind_param($st, 's', $username);
        mysqli_stmt_execute($st);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: null;
        if (!$user) {
            return false; // ไม่มีบัญชีใน ProSystem
        }
        if ($centralId !== '') {
            $st = mysqli_prepare($conn, 'UPDATE users SET central_id = ? WHERE id = ?');
            mysqli_stmt_bind_param($st, 'si', $centralId, $user['id']);
            mysqli_stmt_execute($st);
        }
    }

    // ตั้ง session ให้เหมือน login.php ทุก key
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user['username'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['sup_id'] = $user['sup_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['is_logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['login_via'] = 'itcc_sso';

    return true;
}
