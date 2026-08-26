<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
$user = $conn->query("SELECT id, username, role, sup_id FROM users WHERE role = 'procure' ORDER BY id LIMIT 1")->fetch_assoc();
if (!$user) {
    fwrite(STDERR, "PR filter smoke needs one procurement user\n");
    exit(1);
}
$_SESSION['user'] = $user['username'];
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['role'] = $user['role'];
$_SESSION['sup_id'] = $user['sup_id'];
$_SERVER['PHP_SELF'] = '/pending_approval.php';
$_GET = ['type' => 'pr'];

ob_start();
include __DIR__ . '/../pending_approval.php';
$html = ob_get_clean();

if (strpos($html, 'id="prApprovalSection"') === false || strpos($html, 'data-pending-view="pr"') === false) {
    fwrite(STDERR, "PR filter did not render the PR approval section\n");
    exit(1);
}
if (strpos($html, 'id="inspectionAssignmentInbox"') !== false) {
    fwrite(STDERR, "PR filter unexpectedly rendered the inspection inbox\n");
    exit(1);
}

echo "pending approval PR filter smoke: PASS\n";
