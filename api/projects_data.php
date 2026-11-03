<?php
/**
 * API ส่งข้อมูลหมวดโครงการ/ก่อสร้างออกไปให้เว็บอื่น (อ่านอย่างเดียว) — ยืนยันตัวตนดู external_api_auth.php
 *
 * GET api/projects_data.php?year=2026
 *   year : ปีของกราฟการจ่ายเงินรายเดือน (ค่าเริ่มต้น ปีปัจจุบัน) — ข้อมูลอื่นเป็นสถานะ ณ ปัจจุบัน
 * คืน: projects (โครงการ + สรุปงวด), milestones (ทุกงวด), inspections (รอบตรวจรับ), companies
 */
declare(strict_types=1);

require_once __DIR__ . '/external_api_auth.php';
ext_api_guard();

try {
    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/supplier_display.php';
    require_once dirname(__DIR__) . '/inspection_workflow.php';
    $conn->set_charset('utf8mb4');

    $year = (int)($_GET['year'] ?? 0);
    if ($year < 2000 || $year > 2100) {
        $year = (int)date('Y');
    }
    $today = date('Y-m-d');
    $all = fn (string $sql) => $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    $f = fn ($v) => $v === null ? null : (float)$v;

    $companies = array_map(fn ($c) => ['id' => (int)$c['id'], 'name' => supplier_display_name((string)$c['company_name'])],
        $all('SELECT id, company_name FROM suppliers ORDER BY company_name'));
    $coName = array_column($companies, 'name', 'id');

    // งวดงานทั้งหมด
    $milestones = array_map(fn ($m) => [
        'id' => (int)$m['id'], 'project_id' => (int)$m['project_id'], 'no' => $m['milestone_no'], 'name' => $m['milestone_name'],
        'work_start_date' => $m['work_start_date'], 'claim_date' => $m['claim_date'],
        'amount' => $f($m['amount']), 'net_amount' => $f($m['net_amount']), 'total_request_amount' => $f($m['total_request_amount']),
        'vat_amount' => $f($m['vat_amount']), 'wht_amount' => $f($m['wht_amount']), 'other_deduction_amount' => $f($m['other_deduction_amount']),
        'retention_amount' => $f($m['retention_amount']), 'retention_return_due_date' => $m['retention_return_due_date'],
        'retention_returned_at' => $m['retention_returned_at'],
        'status' => $m['status'] ?: 'pending', 'paid_at' => $m['paid_at'], 'paid_amount' => $f($m['paid_amount']),
        'overdue_days' => ($m['status'] !== 'paid' && $m['claim_date'] && $m['claim_date'] < $today) ? (int)((strtotime($today) - strtotime($m['claim_date'])) / 86400) : 0,
        'inspection_status' => $m['inspection_status'], 'inspection_label' => $m['inspection_status'] ? inspection_status_label($m['inspection_status']) : null,
    ], $all("SELECT m.*, (SELECT r.status FROM inspection_rounds r WHERE r.milestone_id = m.id ORDER BY r.round_no DESC, r.id DESC LIMIT 1) inspection_status
             FROM project_milestones m ORDER BY m.project_id, m.id"));
    $byProject = [];
    foreach ($milestones as $m) {
        $byProject[$m['project_id']][] = $m;
    }

    // โครงการ + สรุปจากงวดงาน
    $projects = [];
    foreach ($all("SELECT p.id, p.project_no, p.contract_no, p.contract_date, p.project_name, p.contractor_name, p.work_location, p.project_remarks,
                          p.supplier_id, c.customer_name customer, p.contract_value, p.net_contract_value, p.total_vat_amount, p.total_wht_amount,
                          p.retention_percent, p.start_date, p.end_date, p.project_status, p.created_at
                   FROM projects p LEFT JOIN customers c ON c.id = p.customer_id ORDER BY p.created_at DESC") as $p) {
        $ms = $byProject[(int)$p['id']] ?? [];
        $paid = array_filter($ms, fn ($m) => $m['status'] === 'paid');
        $pending = array_filter($ms, fn ($m) => $m['status'] !== 'paid');
        $next = null;
        foreach ($pending as $m) {
            if ($m['claim_date'] && (!$next || $m['claim_date'] < $next['claim_date'])) $next = $m;
        }
        $sid = (int)$p['supplier_id'];
        $projects[] = [
            'id' => (int)$p['id'], 'project_no' => $p['project_no'], 'contract_no' => $p['contract_no'], 'contract_date' => $p['contract_date'],
            'name' => $p['project_name'], 'contractor' => $p['contractor_name'], 'customer' => $p['customer'], 'location' => $p['work_location'],
            'remarks' => $p['project_remarks'], 'company_id' => $sid ?: null, 'company' => $coName[$sid] ?? 'ไม่ระบุบริษัท',
            'contract_value' => $f($p['contract_value']), 'net_contract_value' => $f($p['net_contract_value']),
            'retention_percent' => $f($p['retention_percent']), 'start_date' => $p['start_date'], 'end_date' => $p['end_date'],
            'status' => $p['project_status'] ?: 'active', 'created_at' => $p['created_at'],
            'milestone_count' => count($ms), 'paid_count' => count($paid),
            'paid_amount' => round(array_sum(array_map(fn ($m) => $m['paid_amount'] ?? $m['net_amount'] ?? 0, $paid)), 2),
            'pending_amount' => round(array_sum(array_map(fn ($m) => $m['net_amount'] ?? 0, $pending)), 2),
            'overdue_count' => count(array_filter($pending, fn ($m) => $m['overdue_days'] > 0)),
            'retention_held' => round(array_sum(array_map(fn ($m) => $m['retention_returned_at'] ? 0 : ($m['retention_amount'] ?? 0), $ms)), 2),
            'next_claim' => $next ? ['id' => $next['id'], 'no' => $next['no'], 'name' => $next['name'], 'claim_date' => $next['claim_date'], 'net_amount' => $next['net_amount']] : null,
            'overdue_end' => ($p['project_status'] === 'active' && $p['end_date'] && $p['end_date'] < $today),
        ];
    }

    // รอบตรวจรับงาน
    $inspections = array_map(fn ($r) => [
        'id' => (int)$r['id'], 'project_id' => (int)$r['project_id'], 'milestone_id' => (int)$r['milestone_id'], 'round_no' => (int)$r['round_no'],
        'status' => $r['status'], 'status_label' => inspection_status_label((string)$r['status']),
        'inspection_date' => $r['inspection_date'], 'created_at' => $r['created_at'], 'finalized_at' => $r['finalized_at'],
    ], $all('SELECT id, project_id, milestone_id, round_no, status, inspection_date, created_at, finalized_at FROM inspection_rounds ORDER BY created_at DESC'));

    ext_api_out(200, ['ok' => true, 'data' => [
        'year' => $year, 'today' => $today,
        'projects' => $projects, 'milestones' => $milestones, 'inspections' => $inspections, 'companies' => $companies,
        'generatedAt' => date('d/m/Y H:i'),
    ]]);
} catch (Throwable $e) {
    error_log('[projects-api] ' . get_class($e) . ': ' . $e->getMessage());
    ext_api_fail($e);
}
