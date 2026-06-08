<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$pid = intval($_GET['project_id'] ?? 0);
if ($pid <= 0) {
    echo json_encode(['error' => 'Invalid project ID']);
    exit;
}

$now = date('Y-m-d');

// Stats
$stats_sql = "SELECT
              (SELECT COUNT(*) FROM project_milestones WHERE project_id = p.id) as total_ms,
              (SELECT COUNT(*) FROM project_milestones WHERE project_id = p.id AND status = 'paid') as paid_ms,
              (SELECT COALESCE(SUM(total_request_amount), 0) FROM project_milestones WHERE project_id = p.id AND status = 'pending') as pending_amt,
              (SELECT COALESCE(SUM(total_request_amount), 0) FROM project_milestones WHERE project_id = p.id AND status = 'paid') as paid_amt,
              (SELECT COUNT(*) FROM project_milestones WHERE project_id = p.id AND status = 'pending' AND claim_date < '$now') as overdue_cnt
              FROM projects p WHERE p.id = $pid";
$r = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($r) ?: [];

// Milestones
$ms_sql = "SELECT id, milestone_name as ms_name, amount, total_request_amount as amount, status
           FROM project_milestones WHERE project_id = $pid ORDER BY id ASC";
$r = mysqli_query($conn, $ms_sql);
$milestones = mysqli_fetch_all($r, MYSQLI_ASSOC);

// Events
$events_sql = "
    (SELECT 'project_created' as type, p.created_at as date, p.project_name as title, 'เริ่มสร้างโครงการ' as detail
     FROM projects p WHERE p.id = $pid)
    UNION ALL
    (SELECT 'milestone_requested' as type, m.created_at as date, m.milestone_name as title, CONCAT('เรียกเก็บ: ', FORMAT(m.total_request_amount, 2), ' ฿') as detail
     FROM project_milestones m WHERE m.project_id = $pid)
    UNION ALL
    (SELECT 'milestone_inspected' as type, i.inspection_date as date, CONCAT('ตรวจรับ: ', m.milestone_name) as title, CONCAT('ผล: ', i.result_status) as detail
     FROM milestone_inspections i JOIN project_milestones m ON i.milestone_id = m.id WHERE m.project_id = $pid)
    UNION ALL
    (SELECT 'doc_linked' as type, pd.added_at as date, pd.doc_no as title, CONCAT('เชื่อมโยง: ', UPPER(pd.doc_type)) as detail
     FROM project_documents pd WHERE pd.project_id = $pid)
    ORDER BY date DESC";
$r = mysqli_query($conn, $events_sql);
$events = mysqli_fetch_all($r, MYSQLI_ASSOC);

echo json_encode([
    'stats' => $stats,
    'milestones' => $milestones,
    'events' => $events
], JSON_UNESCAPED_UNICODE);