<?php
// usage: php pr_quotes_admin_page_smoke.php [list|edit] [role] — แต่ละหน้าต้องรันแยก process เพราะ header.php ประกาศฟังก์ชัน
if ($argc < 3) {
    $self = escapeshellarg(__FILE__);
    $php = escapeshellarg(PHP_BINARY);
    foreach ([['list', 'admin'], ['list', 'procure'], ['edit', 'admin']] as [$page, $role]) {
        passthru("$php $self $page $role", $code);
        if ($code !== 0) {
            exit($code);
        }
    }
    echo "PR quotes admin pages: PASS\n";
    exit(0);
}

[$_, $page, $role] = $argv;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

$user = $conn->query("SELECT id, username, role, sup_id FROM users WHERE role = '" . $conn->real_escape_string($role) . "' ORDER BY id LIMIT 1")->fetch_assoc();
if (!$user) {
    fwrite(STDERR, "needs one $role user\n");
    exit(1);
}
$_SESSION += ['user' => $user['username'], 'user_id' => (int)$user['id'], 'role' => $user['role'], 'sup_id' => $user['sup_id']];

$conn->begin_transaction();
$conn->query("INSERT INTO pr (doc_no, status, is_internal, subtotal, vat, vat_percent, wht_percent, grand_total, created_by)
    VALUES ('TEST-PRQA-PAGE', 'pending', 1, 100, 7, 7, 0, 107, {$_SESSION['user_id']})");
$pr_id = (int)$conn->insert_id;
$conn->query("INSERT INTO pr_items (pr_id, item_desc, item_qty, item_unit, item_price, item_discount, item_total) VALUES ($pr_id, 'รายการทดสอบ', 1, 'ชิ้น', 100, 0, 100)");

$link = 'edit_pr_quotes.php?id=' . $pr_id;
$_SERVER['REQUEST_METHOD'] = 'GET';
if ($page === 'list') {
    $_SERVER['PHP_SELF'] = '/pending_approval.php';
    $_GET = ['type' => 'pr'];
    ob_start();
    include __DIR__ . '/../pending_approval.php';
    $html = ob_get_clean();
    $ok = strpos($html, 'id="prApprovalSection"') !== false
        && (strpos($html, $link) !== false) === ($role === 'admin');
} else {
    $_SERVER['PHP_SELF'] = '/edit_pr_quotes.php';
    $_GET = ['id' => (string)$pr_id];
    ob_start();
    include __DIR__ . '/../edit_pr_quotes.php';
    $html = ob_get_clean();
    $ok = strpos($html, 'action="api/update_pr_quotes.php"') !== false
        && strpos($html, 'id="prQuotePanel"') !== false
        // pr_quotes.js เก็บราคาไว้ใน td สุดท้าย ยอดต้องอยู่ใน span ไม่เช่นนั้นราคาจะถูกเขียนทับ
        && strpos($html, '<span class="item-line-total">') !== false
        && strpos($html, 'id="prQuoteToggle" name="quote_enabled" value="1" class="w-5 h-5 rounded text-indigo-600"
                checked') !== false;
}
$conn->rollback();

if (!$ok) {
    fwrite(STDERR, "FAIL: $page as $role\n");
    exit(1);
}
echo "$page as $role: PASS\n";
