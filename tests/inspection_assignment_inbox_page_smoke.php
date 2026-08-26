<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user'] = 'smoke';
$_SESSION['user_id'] = 24;
$_SESSION['role'] = 'tech_shotel';
$_SERVER['PHP_SELF'] = '/pending_approval.php';
$_GET = ['type' => 'inspection'];

ob_start();
include __DIR__ . '/../pending_approval.php';
$html = ob_get_clean();

foreach (['id="inspectionAssignmentInbox"', 'inspection-assignment-card', 'งานตรวจรับที่ได้รับมอบหมาย', 'ขั้นตอนปัจจุบัน', 'ได้รับมอบหมายเป็น', 'id="inspection-task-nav"', 'pending_approval.php?type=inspection', 'data-pending-view="inspection"'] as $marker) {
    if (strpos($html, $marker) === false) {
        fwrite(STDERR, "Assigned inspection inbox is missing {$marker}\n");
        exit(1);
    }
}
if (strpos($html, 'id="prApprovalSection"') !== false) {
    fwrite(STDERR, "Inspection filter unexpectedly rendered the PR approval section\n");
    exit(1);
}
if (strpos($html, 'pending_approval.php?type=pr') !== false || strpos($html, 'id="pr-approval-nav"') !== false) {
    fwrite(STDERR, "Inspection-only user unexpectedly received PR approval navigation\n");
    exit(1);
}

$expectedActionable = inspection_pending_assignment_count($conn, 24);
if ($expectedActionable > 0 && strpos($html, 'งานตรวจรับ (' . $expectedActionable . ')') === false) {
    fwrite(STDERR, "Inspection navigation badge does not include actionable assignments\n");
    exit(1);
}

echo "inspection assignment inbox page smoke: PASS\n";
