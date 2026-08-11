<?php

if (!function_exists('project_user_can_manage')) {
    function project_user_can_manage(int $userId, string $role): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return in_array($role, ['admin', 'gm', 'gmhok', 'hok', 'procure', 'mgr', 'mgr2'], true);
    }
}

if (!function_exists('project_require_management_access')) {
    function project_require_management_access(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $role = (string)($_SESSION['role'] ?? '');
        if (project_user_can_manage($userId, $role)) {
            return;
        }

        $statusCode = $userId > 0 ? 403 : 401;
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => $statusCode === 401 ? 'กรุณาเข้าสู่ระบบ' : 'คุณไม่มีสิทธิ์จัดการโครงการ',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
