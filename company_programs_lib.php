<?php
// โปรแกรมในบริษัท (หน้า E-Service) + บัญชีเข้าใช้ของแต่ละคน
// รหัสผ่านเข้ารหัสด้วย AES-256-GCM กุญแจอยู่ใน secrets/app_key.php (สร้างให้อัตโนมัติ ห้าม commit)

const COMPANY_PROGRAM_IMAGE_DIR = 'uploads/programs/';

/** กุญแจเข้ารหัส 32 ไบต์ — ไฟล์เป็น PHP ที่ return ค่า จึงไม่แสดงอะไรแม้มีคนเปิดผ่านเว็บ */
function company_program_key(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $dir = __DIR__ . '/secrets';
    $file = $dir . '/app_key.php';
    if (!is_file($file)) {
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        file_put_contents($dir . '/.htaccess', "Require all denied\n");
        file_put_contents($file, "<?php\n// กุญแจเข้ารหัสรหัสผ่านโปรแกรม ห้ามแก้/ลบ (ถ้าหาย รหัสที่บันทึกไว้จะถอดไม่ได้) และห้าม commit\nreturn '" . bin2hex(random_bytes(32)) . "';\n", LOCK_EX);
    }
    $hex = require $file;
    if (!is_string($hex) || strlen($hex) !== 64) {
        throw new RuntimeException('กุญแจเข้ารหัสไม่ถูกต้อง: secrets/app_key.php');
    }
    return $key = hex2bin($hex);
}

function company_program_encrypt(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', company_program_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function company_program_decrypt(?string $stored): string
{
    if ($stored === null || $stored === '' || strpos($stored, 'v1:') !== 0) return '';
    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false || strlen($raw) < 28) return '';
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', company_program_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}

/** อนุญาตเฉพาะลิงก์ http/https (กัน javascript: และลิงก์แปลก ๆ) */
function company_program_valid_url(string $url): bool
{
    return (bool)filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url);
}

/** โปรแกรมที่ผู้ใช้คนนี้เห็น (มีแถวบัญชีของตัวเอง) — ไม่ส่งรหัสผ่านออกไป */
function company_programs_for_user(mysqli $conn, int $user_id): array
{
    return mysqli_fetch_all(mysqli_query($conn,
        "SELECT p.id, p.name, p.description, p.url, p.image_path, a.login_username, a.note,
                (a.login_password_enc IS NOT NULL AND a.login_password_enc <> '') as has_password
         FROM company_programs p
         JOIN company_program_accounts a ON a.program_id = p.id AND a.user_id = $user_id
         WHERE p.is_active = 1
         ORDER BY p.sort_order ASC, p.name ASC"), MYSQLI_ASSOC);
}

/** บันทึกรูปโปรแกรม (jpg/png/webp/gif ไม่เกิน 2MB) — คืน path หรือ null */
function company_program_save_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 2 * 1024 * 1024) throw new RuntimeException('รูปต้องไม่เกิน 2MB');
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) throw new RuntimeException('รองรับเฉพาะรูป JPG, PNG, WEBP, GIF');
    $dir = __DIR__ . '/' . COMPANY_PROGRAM_IMAGE_DIR;
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $path = COMPANY_PROGRAM_IMAGE_DIR . 'program_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $path)) throw new RuntimeException('อัปโหลดรูปไม่สำเร็จ');
    return $path;
}

function company_program_delete_image(?string $path): void
{
    if ($path && strpos($path, COMPANY_PROGRAM_IMAGE_DIR) === 0 && is_file(__DIR__ . '/' . $path)) @unlink(__DIR__ . '/' . $path);
}
