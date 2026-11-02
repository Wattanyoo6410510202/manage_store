<?php
require_once __DIR__ . '/../pr_timeline_lib.php';

function tl_assert($cond, string $label): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $label);
    }
}

// ขั้นอนุมัติตามวงเงิน ต้องตรงกับ api/update_pr_status_new.php
tl_assert(pr_required_stages('low') === ['approved_by_0', 'approved_by'], 'low stages');
tl_assert(pr_required_stages('mid') === ['approved_by_0', 'approved_by', 'approved_by_2'], 'mid stages');
tl_assert(pr_required_stages('high') === ['approved_by_0', 'approved_by', 'approved_by_3'], 'high stages');

// ทุก API ที่เปลี่ยนสถานะ PR ต้องบันทึก log
$hooks = [
    'api/save_pr_new.php' => "'created'",
    'api/update_pr_new.php' => "'resubmitted' : 'edited'",
    'api/update_pr_status_new.php' => "'approved'",
    'api/reject_pr.php' => "'rejected'",
    'api/receive_pr.php' => "'received'",
    'api/receive_pr_detail.php' => "'received'",
];
foreach ($hooks as $file => $needle) {
    $src = file_get_contents(__DIR__ . '/../' . $file);
    tl_assert(strpos($src, 'pr_log_activity(') !== false && strpos($src, $needle) !== false, "$file logs $needle");
}
$statusApi = file_get_contents(__DIR__ . '/../api/update_pr_status_new.php');
tl_assert(strpos($statusApi, "'fully_approved'") !== false && strpos($statusApi, "'po_created'") !== false, 'full approval + PO logged');

$conn = @mysqli_connect('localhost', 'root', '', 'procurement_system');
if (!$conn) {
    echo "PR timeline DB flow: SKIPPED (no database)\nPR timeline: PASS\n";
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();
try {
    $uid = (int)$conn->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetch_row()[0];
    $conn->query("INSERT INTO pr (doc_no, status, budget_limit_type, created_by, created_at) VALUES ('TEST-TL', 'pending', 'low', $uid, '2026-01-01 09:00:00')");
    $pr_id = (int)$conn->insert_id;

    // log สร้าง + ปฏิเสธ (เวลาเดียวกับคอลัมน์เดิม → ต้องไม่ซ้ำ)
    $conn->query("INSERT INTO pr_activity_log (pr_id, action, actor_id, created_at) VALUES ($pr_id, 'created', $uid, '2026-01-01 09:00:05')");
    $conn->query("UPDATE pr SET status='rejected', reject_reason='ขาดใบเสนอราคา', rejected_by=$uid, rejected_at='2026-01-01 10:00:00', allow_resubmit=1 WHERE id=$pr_id");
    $conn->query("INSERT INTO pr_activity_log (pr_id, action, stage, actor_id, note, created_at) VALUES ($pr_id, 'rejected', 'approved_by_0', $uid, 'ขาดใบเสนอราคา', '2026-01-01 10:00:00')");

    $t = pr_timeline_build($conn, $pr_id);
    tl_assert(count($t['events']) === 2, 'no duplicate between log and record (' . count($t['events']) . ')');
    tl_assert($t['upcoming'][0]['state'] === 'blocked', 'rejected PR waits for resubmit');

    // ส่งใหม่ → หัวหน้าอนุมัติ (ระบบเดิมล้างเหตุผลปฏิเสธ) → ประวัติปฏิเสธต้องยังอยู่ใน timeline
    $conn->query("UPDATE pr SET allow_resubmit=3 WHERE id=$pr_id");
    $conn->query("INSERT INTO pr_activity_log (pr_id, action, actor_id, created_at) VALUES ($pr_id, 'resubmitted', $uid, '2026-01-02 09:00:00')");
    $conn->query("UPDATE pr SET approved_by_0=$uid, approved_at_0='2026-01-02 11:00:00', reject_reason=NULL, rejected_by=NULL, rejected_at=NULL, allow_resubmit=0, status='pending' WHERE id=$pr_id");
    $conn->query("INSERT INTO pr_activity_log (pr_id, action, stage, actor_id, created_at) VALUES ($pr_id, 'approved', 'approved_by_0', $uid, '2026-01-02 11:00:01')");

    $t = pr_timeline_build($conn, $pr_id);
    $actions = array_column($t['events'], 'action');
    tl_assert($actions === ['created', 'rejected', 'resubmitted', 'approved'], 'history kept after re-approval: ' . implode(',', $actions));
    tl_assert($t['events'][1]['title'] === 'ปฏิเสธโดยหัวหน้างาน' && $t['events'][1]['note'] === 'ขาดใบเสนอราคา', 'rejection title + reason');
    tl_assert($t['upcoming'][0]['title'] === 'รออนุมัติ: จัดซื้อ' && $t['upcoming'][0]['state'] === 'current', 'next step is procurement');

    // PR เก่าไม่มี log → สร้างจากคอลัมน์เดิม
    $conn->query("DELETE FROM pr_activity_log WHERE pr_id=$pr_id");
    $t = pr_timeline_build($conn, $pr_id);
    tl_assert(array_column($t['events'], 'source') === ['record', 'record'], 'legacy events derived from record');

    tl_assert(pr_timeline_build($conn, 999999999) === null, 'missing PR returns null');
    echo "PR timeline DB flow: PASS\n";
} finally {
    $conn->rollback();
}
echo "PR timeline: PASS\n";
