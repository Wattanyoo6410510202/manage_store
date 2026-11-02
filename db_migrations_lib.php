<?php
/**
 * อัปเดตฐานข้อมูล (migrations) จากหน้าตั้งค่า
 *
 * - ไฟล์ SQL ทุกไฟล์ต้อง "รันซ้ำได้" (CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS ...)
 * - ลำดับใน DB_MIGRATIONS สำคัญ (ไฟล์หลังอาจอ้างตารางของไฟล์ก่อน)
 * - เพิ่มไฟล์ใหม่: ต่อท้าย DB_MIGRATIONS เท่านั้น
 */

const DB_MIGRATIONS = [
    'add_budget_projects.sql' => 'ตารางโครงการงบประมาณ',
    'add_budget_project_signers.sql' => 'ผู้ลงนามโครงการงบประมาณ',
    'add_budget_project_sign_links.sql' => 'ลิงก์เซ็นเอกสารโครงการ',
    'add_pr_budget_project.sql' => 'ผูกใบขอซื้อกับโครงการ',
    'add_company_programs.sql' => 'โปรแกรม/บัญชีของบริษัท',
    'add_retention_return_fields.sql' => 'กำหนดคืนเงินประกันผลงาน',
    'sql/add_stock_min_max.sql' => 'Stock: ABC class / lead time',
    'sql/add_users_central_id.sql' => 'รหัสพนักงานกลาง (SSO)',
    'sql/add_pr_quotes.sql' => 'ใบเทียบราคาใน PR',
    'sql/add_pr_activity_log.sql' => 'Timeline ประวัติใบขอซื้อ',
];

function db_migrations_ensure_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `filename` varchar(190) NOT NULL,
        `checksum` char(40) NOT NULL,
        `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
        `applied_by` int(11) DEFAULT NULL,
        PRIMARY KEY (`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

/**
 * สถานะของทุกไฟล์: pending (ยังไม่เคยรัน), changed (ไฟล์ถูกแก้หลังรัน), applied, missing (ไม่พบไฟล์)
 */
function db_migrations_status(mysqli $conn): array
{
    db_migrations_ensure_table($conn);
    $applied = [];
    $res = $conn->query("SELECT filename, checksum, applied_at FROM schema_migrations");
    while ($row = $res->fetch_assoc()) {
        $applied[$row['filename']] = $row;
    }

    $list = [];
    foreach (DB_MIGRATIONS as $file => $label) {
        $path = __DIR__ . '/' . $file;
        $checksum = is_file($path) ? sha1_file($path) : null;
        $row = $applied[$file] ?? null;
        if ($checksum === null) {
            $state = 'missing';
        } elseif (!$row) {
            $state = 'pending';
        } elseif ($row['checksum'] !== $checksum) {
            $state = 'changed';
        } else {
            $state = 'applied';
        }
        $list[] = ['file' => $file, 'label' => $label, 'state' => $state, 'applied_at' => $row['applied_at'] ?? null];
    }
    return $list;
}

/** รันไฟล์ SQL ทั้งไฟล์ (หลายคำสั่ง) — คืน null ถ้าสำเร็จ หรือข้อความ error */
function db_migrations_run_file(mysqli $conn, string $path): ?string
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        return 'อ่านไฟล์ไม่ได้';
    }
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql); // ตัด BOM

    $prev = mysqli_report(MYSQLI_REPORT_OFF);
    try {
        if (!$conn->multi_query($sql)) {
            return $conn->error;
        }
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
            if ($conn->errno) {
                return $conn->error;
            }
        } while ($conn->more_results() && $conn->next_result());
        return $conn->errno ? $conn->error : null;
    } finally {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
}

/**
 * รันไฟล์ที่ยังไม่เคยรันหรือถูกแก้ไข ตามลำดับ (หยุดเมื่อเจอ error)
 * @param bool $all true = รันใหม่ทุกไฟล์ (ทุกไฟล์รันซ้ำได้อยู่แล้ว)
 */
function db_migrations_run(mysqli $conn, ?int $user_id, bool $all = false): array
{
    $results = [];
    foreach (db_migrations_status($conn) as $m) {
        if ($m['state'] === 'missing' || (!$all && $m['state'] === 'applied')) {
            continue;
        }
        $path = __DIR__ . '/' . $m['file'];
        $error = db_migrations_run_file($conn, $path);
        $results[] = ['file' => $m['file'], 'label' => $m['label'], 'ok' => $error === null, 'error' => $error];
        if ($error !== null) {
            break;
        }
        $checksum = sha1_file($path);
        $stmt = $conn->prepare("REPLACE INTO schema_migrations (filename, checksum, applied_at, applied_by) VALUES (?, ?, NOW(), ?)");
        $stmt->bind_param('ssi', $m['file'], $checksum, $user_id);
        $stmt->execute();
    }
    return $results;
}
