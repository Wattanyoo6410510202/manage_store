<?php
require_once __DIR__ . '/line_notify.php';
require_once __DIR__ . '/line_messaging_api.php';

function notifyUserLine($user_id, $message) {
    global $conn;
    $stmt = $conn->prepare("SELECT line_user_id, line_token FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        // LINE OA (ส่งตาม userId) มี priority สูงกว่า
        if (!empty($row['line_user_id'])) {
            $sent = sendLinePushMessage($row['line_user_id'], $message);
            if ($sent) return true;
        }
        // Fallback: LINE Notify (ส่งตาม token)
        if (!empty($row['line_token'])) {
            return sendLineNotify($message, $row['line_token']);
        }
    }
    return false;
}

function notifyUsersLine($user_ids, $message) {
    $sent = 0;
    foreach ($user_ids as $uid) {
        if (notifyUserLine($uid, $message)) $sent++;
    }
    return $sent;
}

function notifyRoleLine($role, $message, $sup_id = null) {
    global $conn;
    $sql = "SELECT id FROM users WHERE role = ? AND (line_user_id IS NOT NULL AND line_user_id != '' OR line_token IS NOT NULL AND line_token != '')";
    $params = [$role];
    $types = "s";
    if ($sup_id) {
        $sql .= " AND sup_id = ?";
        $params[] = $sup_id;
        $types .= "i";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $ids = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ids[] = $row['id'];
    }
    return notifyUsersLine($ids, $message);
}

function notifyRoleGroupLine(array $roles, $message, $sup_id = null) {
    $sent = 0;
    foreach ($roles as $role) {
        $sent += notifyRoleLine($role, $message, $sup_id);
    }
    return $sent;
}

function getPRUrl($pr_id) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$protocol://$host/manage_store/view_pr_new.php?id=$pr_id";
}

// แจ้งเตือน LINE ให้ GM ทุกคนที่มี sup_id ตรงกับ supplier_id ที่ระบุ
function notifyGMsBySupId($message, $supplier_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM users WHERE role LIKE 'gm%' AND sup_id = ? AND (line_user_id IS NOT NULL AND line_user_id != '' OR line_token IS NOT NULL AND line_token != '')");
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $ids = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ids[] = $row['id'];
    }
    $stmt->close();
    return notifyUsersLine($ids, $message);
}
?>