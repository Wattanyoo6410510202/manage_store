<?php
/**
 * ใช้ร่วมกันสำหรับ API ที่ส่งข้อมูลออกไปให้เว็บอื่น (อ่านอย่างเดียว)
 * ยืนยันตัวตนด้วย header:  X-API-Key: <key>   หรือ   Authorization: Bearer <key>
 *
 * กุญแจอ่านจาก env DASHBOARD_API_KEY หรือ secrets/dashboard_api.php (สร้างให้อัตโนมัติครั้งแรก ห้าม commit)
 * secrets/dashboard_api.php ใส่ 'allowed_origins' ได้ หากต้องเรียกจาก JavaScript บนเบราว์เซอร์ของเว็บอื่น (CORS)
 */
declare(strict_types=1);

function ext_api_out(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function ext_api_config(): array
{
    $dir = dirname(__DIR__) . '/secrets';
    $file = $dir . '/dashboard_api.php';
    if (!is_file($file)) {
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        if (!is_file($dir . '/.htaccess')) {
            file_put_contents($dir . '/.htaccess', "Require all denied\n");
        }
        file_put_contents($file, "<?php\n// กุญแจ API ส่งข้อมูลออก ห้าม commit — เปลี่ยน key ได้ (เว็บที่เรียกต้องเปลี่ยนตาม)\n"
            . "return [\n    'key' => '" . bin2hex(random_bytes(24)) . "',\n"
            . "    // เว็บที่อนุญาตให้เรียกจากเบราว์เซอร์ เช่น 'https://report.example.com' (ถ้าเรียกจากฝั่ง server ไม่ต้องใส่)\n"
            . "    'allowed_origins' => [],\n];\n", LOCK_EX);
    }
    $cfg = require $file;
    $cfg = is_array($cfg) ? $cfg : [];
    $envKey = getenv('DASHBOARD_API_KEY');
    if ($envKey) {
        $cfg['key'] = $envKey;
    }
    return $cfg;
}

/** ตั้ง header, จัดการ CORS/OPTIONS, บังคับ GET และตรวจกุญแจ — ไม่ผ่านจะตอบกลับแล้วจบทันที */
function ext_api_guard(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    $cfg = ext_api_config();

    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '' && in_array($origin, (array)($cfg['allowed_origins'] ?? []), true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: X-API-Key, Authorization');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    if ($method !== 'GET') {
        ext_api_out(405, ['ok' => false, 'error' => 'ใช้ GET เท่านั้น']);
    }

    $key = (string)($cfg['key'] ?? '');
    $given = (string)($_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($given === '') {
        $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($auth === '' && function_exists('getallheaders')) {
            $auth = (string)(array_change_key_case(getallheaders())['authorization'] ?? '');
        }
        if (stripos($auth, 'Bearer ') === 0) {
            $given = trim(substr($auth, 7));
        }
    }
    if (strlen($key) < 16) {
        ext_api_out(503, ['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่ากุญแจ API']);
    }
    if ($given === '' || !hash_equals($key, $given)) {
        ext_api_out(401, ['ok' => false, 'error' => 'API key ไม่ถูกต้อง']);
    }
}
