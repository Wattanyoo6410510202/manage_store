<?php
// บันทึกใบเทียบราคาจาก edit_pr_quotes.php (เฉพาะ admin)
require_once '../config.php';
require_once '../pr_quotes_admin_lib.php';
require_once '../pr_timeline_lib.php';
date_default_timezone_set('Asia/Bangkok');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$role = (string)($stmt->get_result()->fetch_row()[0] ?? ''); // ใช้ role ปัจจุบันจาก DB ไม่ใช่จาก session

if (pr_quotes_admin_block_reason($role, []) !== null) {
    http_response_code(403);
    pr_quotes_fail('เฉพาะผู้ดูแลระบบ (admin) เท่านั้นที่แก้ไขใบเทียบราคาได้');
}
if (!pr_quotes_admin_verify_csrf($_POST)) {
    http_response_code(403);
    pr_quotes_fail('คำขอหมดอายุ กรุณาเปิดหน้าแก้ไขใหม่แล้วลองอีกครั้ง');
}

$pr_id = (int)($_POST['pr_id'] ?? 0);
$conn->begin_transaction();
try {
    $result = pr_quotes_admin_apply($conn, $role, $pr_id, $_POST);
    $conn->commit();
} catch (DomainException $exception) {
    $conn->rollback();
    pr_quotes_fail($exception->getMessage());
} catch (Throwable $exception) {
    $conn->rollback();
    error_log('update_pr_quotes failed: ' . $exception->getMessage());
    pr_quotes_fail('บันทึกใบเทียบราคาไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
}

pr_quotes_cleanup_files($result['obsolete_files']);
$note = 'ยอดสุทธิ ' . number_format($result['old_total'], 2) . ' → ' . number_format($result['new_total'], 2) . ' บาท'
    . ($result['store_changed'] ? ' · เปลี่ยนร้านที่เลือก' : '')
    . ($result['po_doc_no'] !== null ? ' · ปรับราคาใน PO ' . $result['po_doc_no'] : '');
pr_log_activity($conn, $pr_id, 'quotes_edited', $user_id, $note);

$_SESSION['flash_msg'] = 'update_success';
header('Location: ../pending_approval.php?type=pr');
