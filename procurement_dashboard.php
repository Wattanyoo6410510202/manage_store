<?php
require_once 'config.php';
include('header.php');

$today = date('Y-m-d');
$month_start = date('Y-m-01');
$year_start = date('Y-01-01');
$year = intval(date('Y'));
$month = intval(date('m'));

// ── Filters ─────────────────────────────────────────────────
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$sup_id = isset($_GET['sup_id']) ? intval($_GET['sup_id']) : 0;
$filter_year = isset($_GET['filter_year']) ? intval($_GET['filter_year']) : intval(date('Y'));
$filter_month = isset($_GET['filter_month']) ? intval($_GET['filter_month']) : intval(date('m'));
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
if ($filter_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) $filter_date = date('Y-m-d');

// Build date range from selected period + pickers
switch ($period) {
    case 'yearly':
        $date_start = "$filter_year-01-01";
        $date_where  = "(created_at >= '$date_start' AND created_at < '$filter_year-12-31' + INTERVAL 1 DAY)";
        $date_where_p  = "(p.created_at >= '$date_start' AND p.created_at < '$filter_year-12-31' + INTERVAL 1 DAY)";
        $date_where_po = "(po.created_at >= '$date_start' AND po.created_at < '$filter_year-12-31' + INTERVAL 1 DAY)";
        $date_where_r  = "(received_at >= '$date_start' AND received_at < '$filter_year-12-31' + INTERVAL 1 DAY)";
        $date_where_i  = "(created_at >= '$date_start' AND created_at < '$filter_year-12-31' + INTERVAL 1 DAY)";
        $date_where_rej = "(rejected_at >= '$date_start' AND rejected_at < '$filter_year-12-31' + INTERVAL 1 DAY)";
        $mtd  = $date_where;
        $mtd_p = $date_where_p;
        $mtd_po = $date_where_po;
        $mtd_r = $date_where_r;
        $mtd_i = $date_where_i;
        $period_label = 'รายปี';
        $period_label_en = 'Yearly';
        $period_subtitle = $filter_year + 543;
        break;
    case 'monthly':
        $date_start = "$filter_year-$filter_month-01";
        $month_end = date('Y-m-t', strtotime($date_start));
        $date_where  = "(created_at >= '$date_start' AND created_at < '$month_end' + INTERVAL 1 DAY)";
        $date_where_p  = "(p.created_at >= '$date_start' AND p.created_at < '$month_end' + INTERVAL 1 DAY)";
        $date_where_po = "(po.created_at >= '$date_start' AND po.created_at < '$month_end' + INTERVAL 1 DAY)";
        $date_where_r  = "(received_at >= '$date_start' AND received_at < '$month_end' + INTERVAL 1 DAY)";
        $date_where_i  = "(created_at >= '$date_start' AND created_at < '$month_end' + INTERVAL 1 DAY)";
        $date_where_rej = "(rejected_at >= '$date_start' AND rejected_at < '$month_end' + INTERVAL 1 DAY)";
        $mtd  = $date_where;
        $mtd_p = $date_where_p;
        $mtd_po = $date_where_po;
        $mtd_r = $date_where_r;
        $mtd_i = $date_where_i;
        $period_label = 'รายเดือน';
        $period_label_en = 'Monthly';
        $period_subtitle = date('m/Y', strtotime($date_start));
        break;
    default: // daily
        $date_start = $filter_date;
        $date_where  = "DATE(created_at) = '$filter_date'";
        $date_where_p  = "DATE(p.created_at) = '$filter_date'";
        $date_where_po = "DATE(po.created_at) = '$filter_date'";
        $date_where_r  = "DATE(received_at) = '$filter_date'";
        $date_where_i  = "DATE(created_at) = '$filter_date'";
        $date_where_rej = "DATE(rejected_at) = '$filter_date'";
        // MTD: month start to selected date
        $daily_month_start = date('Y-m-01', strtotime($filter_date));
        $mtd  = "(created_at >= '$daily_month_start' AND created_at < '$filter_date' + INTERVAL 1 DAY)";
        $mtd_p = "(p.created_at >= '$daily_month_start' AND p.created_at < '$filter_date' + INTERVAL 1 DAY)";
        $mtd_po = "(po.created_at >= '$daily_month_start' AND po.created_at < '$filter_date' + INTERVAL 1 DAY)";
        $mtd_r = "(received_at >= '$daily_month_start' AND received_at < '$filter_date' + INTERVAL 1 DAY)";
        $mtd_i = "(created_at >= '$daily_month_start' AND created_at < '$filter_date' + INTERVAL 1 DAY)";
        $period_label = 'รายวัน';
        $period_label_en = 'Daily';
        $period_subtitle = date('d/m/Y', strtotime($filter_date));
        break;
}

// Supplier filter SQL fragments
$sup_pr = $sup_id > 0 ? " AND supplier_id = $sup_id" : "";
$sup_po = $sup_id > 0 ? " AND supplier_id = $sup_id" : "";
$sup_p_pr = $sup_id > 0 ? " AND p.supplier_id = $sup_id" : "";
$sup_p_po = $sup_id > 0 ? " AND po.supplier_id = $sup_id" : "";

// Suppliers list for filter dropdown
$suppliers_list = mysqli_query($conn, "SELECT id, company_name FROM suppliers ORDER BY company_name ASC");

/* ── 1. Executive Summary ──────────────────────────────────── */
// PR
$pr_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM pr WHERE $date_where AND deleted_at IS NULL$sup_pr"));
$pr_mtd = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM pr WHERE $mtd AND deleted_at IS NULL$sup_pr"));
$pr_budget_mtd = 340; // mock budget target

// PO
$po_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM po WHERE $date_where AND deleted_at IS NULL$sup_po"));
$po_mtd = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM po WHERE $mtd AND deleted_at IS NULL$sup_po"));
$po_budget_mtd = 50000000;

// GR (received)
$gr_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM pr WHERE received_status IN ('received','partial') AND $date_where_r AND deleted_at IS NULL$sup_pr"));
$gr_mtd = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM pr WHERE received_status IN ('received','partial') AND $mtd_r AND deleted_at IS NULL$sup_pr"));

// Invoice
$inv_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM invoices WHERE $date_where_i AND deleted_at IS NULL"));
$inv_mtd = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM invoices WHERE $mtd_i AND deleted_at IS NULL"));

// Pending approval
$pending_approval = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM pr WHERE status = 'pending' AND approved_by IS NULL AND deleted_at IS NULL$sup_pr"));

// New suppliers this month
$new_supplier_mtd = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM suppliers WHERE created_at >= '$date_start'"));

/* ── 2. PR Section ────────────────────────────────────────── */
$pr_open = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM pr WHERE $date_where AND deleted_at IS NULL$sup_pr"));
$pr_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM pr WHERE status = 'approved' AND $date_where AND deleted_at IS NULL$sup_pr"));
$pr_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM pr WHERE status = 'pending' AND approved_by IS NULL AND deleted_at IS NULL$sup_pr"));
$pr_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM pr WHERE rejected_at IS NOT NULL AND $date_where_rej AND deleted_at IS NULL$sup_pr"));

/* ── 3. PO Section ────────────────────────────────────────── */
$po_open = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM po WHERE $date_where AND deleted_at IS NULL$sup_po"));
$po_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM po WHERE status = 'approved' AND deleted_at IS NULL$sup_po"));
$po_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM po WHERE status = 'pending' AND deleted_at IS NULL$sup_po"));
$po_cancelled = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM po WHERE status = 'cancelled' AND deleted_at IS NULL$sup_po"));

/* ── 4. GR Section ────────────────────────────────────────── */
$gr_full = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM pr WHERE received_status = 'received' AND deleted_at IS NULL$sup_pr"));
$gr_partial = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM pr WHERE received_status = 'partial' AND deleted_at IS NULL$sup_pr"));
$gr_late = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM pr WHERE due_date < CURDATE() AND received_status = 'pending' AND status = 'approved' AND deleted_at IS NULL$sup_pr"));
$gr_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM pr WHERE received_status IN ('received','partial') AND deleted_at IS NULL$sup_pr"));

/* ── 5. Budget Control ────────────────────────────────────── */
$budget_query = mysqli_query($conn, "SELECT bt.id, bt.name, bt.budget_amount, st.store_name,
  (bt.budget_amount + COALESCE((SELECT SUM(amount) FROM budget_adjustments WHERE budget_type_id = bt.id AND status='approved'),0)) as total_budget,
  COALESCE((SELECT SUM(p.grand_total) FROM pr p WHERE p.budget_type_id = bt.id AND p.status IN ('approved') AND p.deleted_at IS NULL AND $mtd_p$sup_p_pr),0) as spent
  FROM budget_types bt
  LEFT JOIN expense_categories ec ON bt.name = ec.name
  LEFT JOIN stores st ON 1=1
  WHERE bt.status = 'approved'
  GROUP BY bt.id
  HAVING total_budget > 0
  ORDER BY spent DESC LIMIT 10");

/* ── 6. Top 10 Purchases Today ────────────────────────────── */
$top10 = mysqli_query($conn, "SELECT s.company_name,
  (SELECT item_desc FROM pr_items WHERE pr_id = p.id ORDER BY id ASC LIMIT 1) as item_desc,
  p.grand_total, st.store_name
  FROM pr p
  LEFT JOIN suppliers s ON p.supplier_id = s.id
  LEFT JOIN stores st ON p.store_id = st.id
  WHERE $date_where_p AND p.deleted_at IS NULL$sup_p_pr
  ORDER BY p.grand_total DESC LIMIT 10");

/* ── 7. Pending Approval ──────────────────────────────────── */
$pending_approvals = mysqli_query($conn, "SELECT p.id, p.doc_no, p.grand_total,
  DATEDIFF(CURDATE(), p.created_at) as wait_days,
  CASE
    WHEN p.approved_by_0 IS NULL THEN 'GM'
    WHEN p.approved_by IS NULL THEN 'Procure'
    WHEN p.approved_by_1 IS NULL THEN 'GMACC'
    WHEN p.approved_by_2 IS NULL THEN 'MGR'
    WHEN p.approved_by_3 IS NULL THEN 'MGR2'
    ELSE 'Completed'
  END as next_approver
  FROM pr p
  WHERE p.status = 'pending' AND p.deleted_at IS NULL$sup_p_pr
    AND (p.approved_by_0 IS NULL OR p.approved_by IS NULL OR p.approved_by_1 IS NULL OR p.approved_by_2 IS NULL OR p.approved_by_3 IS NULL)
  ORDER BY wait_days DESC LIMIT 10");

/* ── 8. Outstanding PO Aging ──────────────────────────────── */
$po_0_7 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM po WHERE DATEDIFF(CURDATE(), created_at) BETWEEN 0 AND 7 AND deleted_at IS NULL AND status != 'cancelled'$sup_po"));
$po_8_15 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM po WHERE DATEDIFF(CURDATE(), created_at) BETWEEN 8 AND 15 AND deleted_at IS NULL AND status != 'cancelled'$sup_po"));
$po_16_30 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM po WHERE DATEDIFF(CURDATE(), created_at) BETWEEN 16 AND 30 AND deleted_at IS NULL AND status != 'cancelled'$sup_po"));
$po_30p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM po WHERE DATEDIFF(CURDATE(), created_at) > 30 AND deleted_at IS NULL AND status != 'cancelled'$sup_po"));

/* ── 9. Charts Data ───────────────────────────────────────── */
// Daily PR/PO for current month (column chart)
$daily_chart = mysqli_query($conn, "SELECT d,
  SUM(CASE WHEN src='pr' THEN val ELSE 0 END) as pr_val,
  SUM(CASE WHEN src='po' THEN val ELSE 0 END) as po_val
  FROM (
    SELECT DATE(created_at) as d, SUM(grand_total) as val, 'pr' as src FROM pr WHERE $mtd AND deleted_at IS NULL AND status = 'approved'$sup_pr GROUP BY DATE(created_at)
    UNION ALL
    SELECT DATE(created_at) as d, SUM(grand_total) as val, 'po' as src FROM po WHERE $mtd AND deleted_at IS NULL AND status = 'approved'$sup_po GROUP BY DATE(created_at)
  ) t
  GROUP BY d ORDER BY d");

$chart_dates = []; $chart_pr_vals = []; $chart_po_vals = [];
while ($r = mysqli_fetch_assoc($daily_chart)) {
  $chart_dates[] = date('d/m', strtotime($r['d']));
  $chart_pr_vals[] = (float)$r['pr_val'];
  $chart_po_vals[] = (float)$r['po_val'];
}

// Spending by department (store) - donut
$dept_spend = mysqli_query($conn, "SELECT st.store_name, COALESCE(SUM(p.grand_total),0) as total
  FROM stores st
  LEFT JOIN pr p ON p.store_id = st.id AND p.status = 'approved' AND p.deleted_at IS NULL AND $mtd_p$sup_p_pr
  GROUP BY st.id HAVING total > 0 ORDER BY total DESC");
$dept_labels = []; $dept_vals = [];
while ($r = mysqli_fetch_assoc($dept_spend)) { $dept_labels[] = $r['store_name']; $dept_vals[] = (float)$r['total']; }

// Top 10 suppliers by value (bar chart)
$top_suppliers = mysqli_query($conn, "SELECT s.company_name, COALESCE(SUM(p.grand_total),0) as total
  FROM suppliers s
  LEFT JOIN pr p ON p.supplier_id = s.id AND p.status = 'approved' AND p.deleted_at IS NULL AND $mtd_p$sup_p_pr
  GROUP BY s.id HAVING total > 0 ORDER BY total DESC LIMIT 10");
$sup_labels = []; $sup_vals = [];
while ($r = mysqli_fetch_assoc($top_suppliers)) { $sup_labels[] = $r['company_name']; $sup_vals[] = (float)$r['total']; }

// Budget remaining (stacked bar) + table
$budget_query = mysqli_query($conn, "SELECT bt.name,
  (bt.budget_amount + COALESCE((SELECT SUM(amount) FROM budget_adjustments WHERE budget_type_id = bt.id AND status='approved'),0)) as total_budget,
  COALESCE((SELECT SUM(p.grand_total) FROM pr p WHERE p.budget_type_id = bt.id AND p.status = 'approved' AND p.deleted_at IS NULL AND $mtd_p$sup_p_pr),0) as spent
  FROM budget_types bt WHERE bt.status = 'approved' AND bt.budget_amount > 0
  ORDER BY spent DESC LIMIT 10");
$budget_rows = [];
while ($r = mysqli_fetch_assoc($budget_query)) { $budget_rows[] = $r; }
$bc_labels = []; $bc_spent = []; $bc_remain = [];
foreach ($budget_rows as $r) {
  $bc_labels[] = $r['name'];
  $bc_spent[] = (float)$r['spent'];
  $bc_remain[] = max(0, (float)$r['total_budget'] - (float)$r['spent']);
}

// PO aging (stacked column)
$po_aging = mysqli_query($conn, "SELECT
  SUM(CASE WHEN DATEDIFF(CURDATE(), created_at) BETWEEN 0 AND 7 THEN grand_total ELSE 0 END) as age_0_7,
  SUM(CASE WHEN DATEDIFF(CURDATE(), created_at) BETWEEN 8 AND 15 THEN grand_total ELSE 0 END) as age_8_15,
  SUM(CASE WHEN DATEDIFF(CURDATE(), created_at) BETWEEN 16 AND 30 THEN grand_total ELSE 0 END) as age_16_30,
  SUM(CASE WHEN DATEDIFF(CURDATE(), created_at) > 30 THEN grand_total ELSE 0 END) as age_30p
  FROM po WHERE deleted_at IS NULL AND status != 'cancelled'$sup_po");

/* ── Extra Charts Data ───────────────────────────────────── */
// Monthly trend (last 6 months)
$monthly_trend = mysqli_query($conn, "SELECT DATE_FORMAT(d, '%Y-%m') as ym,
  SUM(CASE WHEN src='pr' THEN val ELSE 0 END) as pr_val,
  SUM(CASE WHEN src='po' THEN val ELSE 0 END) as po_val
  FROM (
    SELECT DATE_FORMAT(created_at, '%Y-%m-01') as d, SUM(grand_total) as val, 'pr' as src FROM pr WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND deleted_at IS NULL AND status = 'approved'$sup_pr GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
    UNION ALL
    SELECT DATE_FORMAT(created_at, '%Y-%m-01') as d, SUM(grand_total) as val, 'po' as src FROM po WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND deleted_at IS NULL AND status = 'approved'$sup_po GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
  ) t GROUP BY ym ORDER BY ym");
$trend_labels = []; $trend_pr = []; $trend_po = [];
while ($r = mysqli_fetch_assoc($monthly_trend)) {
  $trend_labels[] = $r['ym'];
  $trend_pr[] = (float)$r['pr_val'];
  $trend_po[] = (float)$r['po_val'];
}

// PR status distribution
$pr_status_dist = mysqli_query($conn, "SELECT status, COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM pr WHERE deleted_at IS NULL$sup_pr GROUP BY status");
$prs_labels = []; $prs_vals = [];
while ($r = mysqli_fetch_assoc($pr_status_dist)) { $prs_labels[] = $r['status']; $prs_vals[] = (float)$r['cnt']; }

// PO status distribution
$po_status_dist = mysqli_query($conn, "SELECT status, COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as val FROM po WHERE deleted_at IS NULL$sup_po GROUP BY status");
$pos_labels = []; $pos_vals = [];
while ($r = mysqli_fetch_assoc($po_status_dist)) { $pos_labels[] = $r['status']; $pos_vals[] = (float)$r['cnt']; }

// GR status distribution
$gr_dist = mysqli_query($conn, "SELECT received_status as st, COUNT(*) as cnt FROM pr WHERE deleted_at IS NULL AND status = 'approved'$sup_pr GROUP BY received_status");
$gr_labels = []; $gr_vals = [];
while ($r = mysqli_fetch_assoc($gr_dist)) { $gr_labels[] = $r['st']; $gr_vals[] = (float)$r['cnt']; }

// Expense category breakdown
$exp_cat = mysqli_query($conn, "SELECT ec.name, COALESCE(SUM(p.grand_total),0) as total
  FROM expense_categories ec
  LEFT JOIN pr p ON p.expense_cat_id = ec.id AND p.status = 'approved' AND p.deleted_at IS NULL AND $mtd_p$sup_p_pr
  GROUP BY ec.id HAVING total > 0 ORDER BY total DESC LIMIT 8");
$exp_labels = []; $exp_vals = [];
while ($r = mysqli_fetch_assoc($exp_cat)) { $exp_labels[] = $r['name']; $exp_vals[] = (float)$r['total']; }

function fm($n) { return number_format($n ?? 0, 2); }
?>

<style>
  .kpi-card { transition: all 0.2s; }
  .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,0,0,0.08); }
  .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
  .status-green { background: #10b981; }
  .status-yellow { background: #f59e0b; }
  .status-red { background: #ef4444; }
  table.dash-table { width: 100%; border-collapse: collapse; }
  table.dash-table th { text-align: left; padding: 8px 12px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
  table.dash-table td { padding: 8px 12px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
  table.dash-table tr:hover td { background: #f8fafc; }
  .dash-section { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; }
  .dash-section-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; }
  .dash-section-header h3 { font-size: 14px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; }
  .dash-section-header .desc { font-size: 11px; color: #94a3b8; font-weight: 400; margin-left: auto; }
  .dash-section-body { padding: 16px 20px; }
  .chart-container { position: relative; height: 280px; width: 100%; }
</style>

<div class="max-w-[1440px] mx-auto px-4 py-6 space-y-6">

  <!-- Header -->
  <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight"><?= strtoupper($period_label_en) ?> PROCUREMENT DASHBOARD</h1>
      <p class="text-slate-500 text-sm">บริษัท xxxxxxxxx จำกัด — ประจำ<?= $period_label ?> <?= $period_subtitle ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="procurement.php" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 transition-all">
        <i class="fas fa-chart-pie mr-1"></i> Procurement Dashboard
      </a>
      <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all active:scale-95">
        <i class="fas fa-print mr-1"></i> พิมพ์
      </button>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="dash-section">
    <div class="dash-section-body">
      <form method="GET" class="flex flex-wrap items-end gap-4">
        <div>
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide block mb-1">ช่วงเวลา</label>
          <select name="period" onchange="this.form.submit()" class="px-3 py-2 border border-slate-200 rounded-lg text-sm font-medium bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
            <option value="daily" <?= $period == 'daily' ? 'selected' : '' ?>>รายวัน</option>
            <option value="monthly" <?= $period == 'monthly' ? 'selected' : '' ?>>รายเดือน</option>
            <option value="yearly" <?= $period == 'yearly' ? 'selected' : '' ?>>รายปี</option>
          </select>
        </div>

        <?php if ($period == 'yearly'): ?>
        <div>
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide block mb-1">ปี</label>
          <select name="filter_year" class="px-3 py-2 border border-slate-200 rounded-lg text-sm font-medium bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
            <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y + 543 ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <?php elseif ($period == 'monthly'): ?>
        <div>
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide block mb-1">เดือน</label>
          <select name="filter_month" class="px-3 py-2 border border-slate-200 rounded-lg text-sm font-medium bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $filter_month == $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide block mb-1">ปี</label>
          <select name="filter_year" class="px-3 py-2 border border-slate-200 rounded-lg text-sm font-medium bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
            <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y + 543 ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <?php else: ?>
        <div>
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide block mb-1">วันที่</label>
          <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>" class="px-3 py-2 border border-slate-200 rounded-lg text-sm font-medium bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
        </div>
        <?php endif; ?>

        <div>
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide block mb-1">บริษัท / Supplier</label>
          <select name="sup_id" class="px-3 py-2 border border-slate-200 rounded-lg text-sm font-medium bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
            <option value="0">ทั้งหมด</option>
            <?php mysqli_data_seek($suppliers_list, 0); while ($s = mysqli_fetch_assoc($suppliers_list)): ?>
            <option value="<?= $s['id'] ?>" <?= $sup_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['company_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 transition-all">
          <i class="fas fa-filter mr-1"></i> กรอง
        </button>
        <a href="procurement_dashboard.php" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm font-bold hover:bg-slate-200 transition-all">
          <i class="fas fa-times mr-1"></i> ล้าง
        </a>
      </form>
    </div>
  </div>

  <!-- ═══ 1. Executive Summary ═══ -->
  <div class="dash-section">
    <div class="dash-section-header">
      <i class="fas fa-tachometer-alt text-indigo-500 text-lg"></i>
      <h3>1. Executive Summary</h3>
      <span class="desc">ภาพรวม KPI <?= $period_label ?> เทียบกับเป้าหมาย</span>
    </div>
    <div class="dash-section-body">
      <div class="overflow-x-auto">
        <table class="dash-table">
          <thead>
            <tr>
              <th>KPI</th>
              <th class="text-right"><?= $period_label == 'รายวัน' ? 'Today' : ($period_label == 'รายเดือน' ? 'เดือนนี้' : 'ปีนี้') ?></th>
              <th class="text-right"><?= $period_label == 'รายวัน' ? 'MTD' : ($period_label == 'รายเดือน' ? 'เดือนนี้' : 'ปีนี้') ?></th>
              <th class="text-right">Budget</th>
              <th class="text-center" style="width:60px">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $kpis = [
              ['จำนวน PR', $pr_today['cnt'], $pr_mtd['cnt'], $pr_budget_mtd, $pr_mtd['cnt'] >= $pr_budget_mtd],
              ['จำนวน PO', $po_today['cnt'], $po_mtd['cnt'], 320, $po_mtd['cnt'] >= 320],
              ['มูลค่า PO', fm($po_today['val']), fm($po_mtd['val']), fm($po_budget_mtd), $po_mtd['val'] >= $po_budget_mtd],
              ['รับสินค้า (GR)', $gr_today['cnt'], $gr_mtd['cnt'], '-', true],
              ['Invoice รับเข้า', $inv_today['cnt'], $inv_mtd['cnt'], '-', true],
              ['ค้างอนุมัติ', $pending_approval['cnt'], '-', '0', $pending_approval['cnt'] == 0],
              ['Supplier ใหม่', '-', $new_supplier_mtd['cnt'], '-', true],
            ];
            foreach ($kpis as $k):
            ?>
            <tr>
              <td class="font-bold text-slate-700"><?= $k[0] ?></td>
              <td class="text-right font-mono"><?= $k[1] ?></td>
              <td class="text-right font-mono"><?= $k[2] ?></td>
              <td class="text-right font-mono text-slate-400"><?= $k[3] ?></td>
              <td class="text-center"><span class="status-dot <?= $k[4] ? 'status-green' : 'status-yellow' ?>"></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ═══ 2 + 3 + 4: PR / PO / GR + Daily Chart ═══ -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- PR -->
      <div class="dash-section">
        <div class="dash-section-header">
          <i class="fas fa-file-alt text-emerald-500 text-lg"></i>
          <h3>2. PR</h3>
          <span class="desc">คำขอซื้อ<?= $period_label ?>แยกตามสถานะ</span>
        </div>
        <div class="dash-section-body">
          <table class="dash-table">
            <thead><tr><th>รายการ</th><th class="text-right">จำนวน</th><th class="text-right">มูลค่า</th></tr></thead>
            <tbody>
              <tr><td class="font-bold"><?= $period_label == 'รายวัน' ? 'เปิดวันนี้' : ($period_label == 'รายเดือน' ? 'เปิดเดือนนี้' : 'เปิดปีนี้') ?></td><td class="text-right"><?= $pr_open['cnt'] ?></td><td class="text-right"><?= fm($pr_open['val']) ?></td></tr>
              <tr><td class="text-emerald-600">อนุมัติแล้ว</td><td class="text-right"><?= $pr_approved['cnt'] ?></td><td class="text-right"><?= fm($pr_approved['val']) ?></td></tr>
              <tr><td class="text-amber-600">รออนุมัติ</td><td class="text-right"><?= $pr_pending['cnt'] ?></td><td class="text-right"><?= fm($pr_pending['val']) ?></td></tr>
              <tr><td class="text-red-600">ปฏิเสธ</td><td class="text-right"><?= $pr_rejected['cnt'] ?></td><td class="text-right"><?= fm($pr_rejected['val']) ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- PO -->
      <div class="dash-section">
        <div class="dash-section-header">
          <i class="fas fa-shopping-cart text-blue-500 text-lg"></i>
          <h3>3. PO</h3>
          <span class="desc">ใบสั่งซื้อ<?= $period_label ?>แยกตามสถานะ</span>
        </div>
        <div class="dash-section-body">
          <table class="dash-table">
            <thead><tr><th>รายการ</th><th class="text-right">จำนวน</th><th class="text-right">มูลค่า</th></tr></thead>
            <tbody>
              <tr><td class="font-bold"><?= $period_label == 'รายวัน' ? 'เปิดวันนี้' : ($period_label == 'รายเดือน' ? 'เปิดเดือนนี้' : 'เปิดปีนี้') ?></td><td class="text-right"><?= $po_open['cnt'] ?></td><td class="text-right"><?= fm($po_open['val']) ?></td></tr>
              <tr><td class="text-emerald-600">ส่ง Supplier แล้ว</td><td class="text-right"><?= $po_approved['cnt'] ?></td><td class="text-right"><?= fm($po_approved['val']) ?></td></tr>
              <tr><td class="text-amber-600">รอ Confirm</td><td class="text-right"><?= $po_pending['cnt'] ?></td><td class="text-right"><?= fm($po_pending['val']) ?></td></tr>
              <tr><td class="text-red-600">Cancel</td><td class="text-right"><?= $po_cancelled['cnt'] ?></td><td class="text-right"><?= fm($po_cancelled['val']) ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- GR -->
      <div class="dash-section">
        <div class="dash-section-header">
          <i class="fas fa-boxes text-emerald-500 text-lg"></i>
          <h3>4. GR</h3>
          <span class="desc">สถานะการรับสินค้า</span>
        </div>
        <div class="dash-section-body">
          <table class="dash-table">
            <thead><tr><th>รายการ</th><th class="text-right">จำนวน</th></tr></thead>
            <tbody>
              <tr><td class="text-emerald-600 font-bold">ส่งมอบครบ</td><td class="text-right"><?= $gr_full['cnt'] ?></td></tr>
              <tr><td class="text-amber-600">บางส่วน</td><td class="text-right"><?= $gr_partial['cnt'] ?></td></tr>
              <tr><td class="text-red-600">ส่งล่าช้า</td><td class="text-right"><?= $gr_late['cnt'] ?></td></tr>
              <tr><td class="font-bold">รับสินค้าแล้ว</td><td class="text-right"><?= $gr_total['cnt'] ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <!-- Daily PR/PO Chart -->
  <div class="dash-section">
    <div class="dash-section-header">
      <i class="fas fa-chart-bar text-indigo-500 text-lg"></i>
      <h3>มูลค่า PR / PO <?= $period_label ?></h3>
      <span class="desc">ยอดรวม PR และ PO ที่อนุมัติแล้ว<?= $period_label == 'รายวัน' ? 'ในแต่ละวันของเดือนนี้' : ($period_label == 'รายเดือน' ? 'ในเดือนนี้' : 'ในปีนี้') ?></span>
      </div>
      <div class="dash-section-body">
        <div class="chart-container"><canvas id="dailyChart"></canvas></div>
      </div>
    </div>
  </div>

  <!-- ═══ 5. Budget Control + Chart ═══ -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 dash-section">
      <div class="dash-section-header">
        <i class="fas fa-wallet text-amber-500 text-lg"></i>
        <h3>5. Budget Control</h3>
        <span class="desc">งบประมาณรายแผนก เทียบกับยอดใช้จ่ายจริง (% การใช้)</span>
      </div>
      <div class="dash-section-body">
        <div class="overflow-x-auto">
          <table class="dash-table">
            <thead><tr><th>แผนก</th><th class="text-right">งบประมาณ</th><th class="text-right">ใช้แล้ว</th><th class="text-right">คงเหลือ</th><th class="text-right">% ใช้</th></tr></thead>
            <tbody>
              <?php $has_budget = false; foreach ($budget_rows as $b): $has_budget = true;
                $remain = $b['total_budget'] - $b['spent'];
                $pct = $b['total_budget'] > 0 ? ($b['spent'] / $b['total_budget']) * 100 : 0;
              ?>
              <tr>
                <td class="font-bold"><?= htmlspecialchars($b['name']) ?></td>
                <td class="text-right font-mono"><?= fm($b['total_budget']) ?></td>
                <td class="text-right font-mono"><?= fm($b['spent']) ?></td>
                <td class="text-right font-mono <?= $remain < 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= fm($remain) ?></td>
                <td class="text-right">
                  <div class="inline-flex items-center gap-2">
                    <div class="w-20 h-2 bg-slate-100 rounded-full overflow-hidden">
                      <div class="h-full rounded-full <?= $pct > 90 ? 'bg-red-500' : ($pct > 75 ? 'bg-amber-500' : 'bg-emerald-500') ?>" style="width: <?= min($pct, 100) ?>%"></div>
                    </div>
                    <span class="text-xs font-bold <?= $pct > 90 ? 'text-red-600' : ($pct > 75 ? 'text-amber-600' : 'text-emerald-600') ?>"><?= round($pct, 0) ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; if (!$has_budget): ?>
              <tr><td colspan="5" class="text-center text-slate-400 py-4">ไม่มีข้อมูลงบประมาณ</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <!-- Budget Chart -->
    <div class="dash-section">
      <div class="dash-section-header">
        <i class="fas fa-chart-bar text-rose-500 text-lg"></i>
        <h3>Budget Used vs Remaining</h3>
        <span class="desc">สัดส่วนงบประมาณที่ใช้ไปแล้วกับคงเหลือ</span>
      </div>
      <div class="dash-section-body">
        <div class="chart-container"><canvas id="budgetChart"></canvas></div>
      </div>
    </div>
  </div>

  <!-- ═══ 6. Top 10 Purchase Today + Supplier Chart ═══ -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 dash-section">
      <div class="dash-section-header">
        <i class="fas fa-trophy text-yellow-500 text-lg"></i>
      <h3>6. Top 10 Purchase <?= $period_label_en ?></h3>
      <span class="desc">รายการสั่งซื้อมูลค่าสูงสุด 10 อันดับ<?= $period_label ?></span>
      </div>
      <div class="dash-section-body">
        <div class="overflow-x-auto">
          <table class="dash-table">
            <thead><tr><th>#</th><th>Supplier</th><th>รายการ</th><th class="text-right">มูลค่า</th><th>แผนก</th></tr></thead>
            <tbody>
              <?php $i = 1; $has_top = false; mysqli_data_seek($top10, 0); while ($r = mysqli_fetch_assoc($top10)): $has_top = true; ?>
              <tr>
                <td class="text-slate-400"><?= $i++ ?></td>
                <td class="font-bold"><?= htmlspecialchars($r['company_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars(mb_substr($r['item_desc'] ?? '-', 0, 50)) ?></td>
                <td class="text-right font-mono"><?= fm($r['grand_total']) ?></td>
                <td><?= htmlspecialchars($r['store_name'] ?? '-') ?></td>
              </tr>
              <?php endwhile; if (!$has_top): ?>
              <tr><td colspan="5" class="text-center text-slate-400 py-4">ไม่มีรายการซื้อวันนี้</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <!-- Top Suppliers Chart + Dept Donut -->
    <div class="space-y-6">
      <div class="dash-section">
        <div class="dash-section-header">
          <i class="fas fa-chart-bar text-amber-500 text-lg"></i>
          <h3>Top 10 Supplier</h3>
          <span class="desc">จัดอันดับผู้ขายตามมูลค่าการซื้อ<?= $period_label ?></span>
        </div>
        <div class="dash-section-body">
          <div class="chart-container" style="height:200px"><canvas id="supChart"></canvas></div>
        </div>
      </div>
      <div class="dash-section">
        <div class="dash-section-header">
          <i class="fas fa-chart-pie text-emerald-500 text-lg"></i>
          <h3>สัดส่วนตามแผนก</h3>
          <span class="desc">สัดส่วนมูลค่าจัดซื้อแยกตามหน่วยงาน<?= $period_label ?></span>
        </div>
        <div class="dash-section-body">
          <div class="chart-container" style="height:200px"><canvas id="deptChart"></canvas></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ 7. Pending Approval ═══ -->
  <div class="dash-section">
    <div class="dash-section-header">
      <i class="fas fa-clock text-amber-500 text-lg"></i>
      <h3>7. Pending Approval</h3>
      <span class="desc">เอกสารรอการอนุมัติ เรียงตามวันที่ค้างนานที่สุด</span>
    </div>
    <div class="dash-section-body">
      <div class="overflow-x-auto">
        <table class="dash-table">
          <thead><tr><th>เอกสาร</th><th>ผู้อนุมัติ</th><th class="text-right">จำนวนเงิน</th><th class="text-center">ค้าง</th></tr></thead>
          <tbody>
            <?php $has_pending = false; while ($r = mysqli_fetch_assoc($pending_approvals)): $has_pending = true; ?>
            <tr>
              <td><a href="view_pr_new.php?id=<?= $r['id'] ?>" class="text-indigo-600 hover:underline font-bold"><?= htmlspecialchars($r['doc_no']) ?></a></td>
              <td><?= $r['next_approver'] ?></td>
              <td class="text-right font-mono"><?= fm($r['grand_total']) ?></td>
              <td class="text-center">
                <span class="<?= $r['wait_days'] > 3 ? 'text-red-600' : ($r['wait_days'] > 1 ? 'text-amber-600' : 'text-slate-500') ?> font-bold">
                  <?= $r['wait_days'] ?> วัน
                </span>
              </td>
            </tr>
            <?php endwhile; if (!$has_pending): ?>
            <tr><td colspan="4" class="text-center text-emerald-600 py-4"><i class="fas fa-check-circle mr-1"></i> ไม่มีรายการค้างอนุมัติ</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ═══ Monthly Trend ═══ -->
  <div class="dash-section">
    <div class="dash-section-header">
      <i class="fas fa-chart-line text-indigo-500 text-lg"></i>
      <h3>แนวโน้มมูลค่า PR / PO ย้อนหลัง 6 เดือน</h3>
          <span class="desc">เปรียบเทียบแนวโน้มการจัดซื้อย้อนหลัง 6 เดือน</span>
    </div>
    <div class="dash-section-body">
      <div class="chart-container"><canvas id="trendChart"></canvas></div>
    </div>
  </div>

  <!-- ═══ Status Distribution Charts ═══ -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
    <div class="dash-section">
      <div class="dash-section-header"><i class="fas fa-file-alt text-emerald-500"></i><h3>PR Status</h3><span class="desc" style="margin-left:auto">จำนวน PR แยกตามสถานะ</span></div>
      <div class="dash-section-body"><div class="chart-container" style="height:200px"><canvas id="prStatusChart"></canvas></div></div>
    </div>
    <div class="dash-section">
      <div class="dash-section-header"><i class="fas fa-shopping-cart text-blue-500"></i><h3>PO Status</h3><span class="desc" style="margin-left:auto">จำนวน PO แยกตามสถานะ</span></div>
      <div class="dash-section-body"><div class="chart-container" style="height:200px"><canvas id="poStatusChart"></canvas></div></div>
    </div>
    <div class="dash-section">
      <div class="dash-section-header"><i class="fas fa-boxes text-emerald-500"></i><h3>GR Status</h3><span class="desc" style="margin-left:auto">สถานะรับสินค้า</span></div>
      <div class="dash-section-body"><div class="chart-container" style="height:200px"><canvas id="grStatusChart"></canvas></div></div>
    </div>
    <div class="dash-section">
      <div class="dash-section-header"><i class="fas fa-tags text-purple-500"></i><h3>Expense Category</h3><span class="desc" style="margin-left:auto">มูลค่าจัดซื้อแยกตามประเภทค่าใช้จ่าย <?= $period_label ?></span></div>
      <div class="dash-section-body"><div class="chart-container" style="height:200px"><canvas id="expCatChart"></canvas></div></div>
    </div>
  </div>

  <!-- ═══ 10. Outstanding PO + Aging Chart ═══ -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 dash-section">
      <div class="dash-section-header">
        <i class="fas fa-clock text-blue-500 text-lg"></i>
        <h3>10. Outstanding PO</h3>
        <span class="desc">PO ที่ยังไม่ปิด แยกตามอายุคงค้าง</span>
      </div>
      <div class="dash-section-body">
        <div class="overflow-x-auto">
          <table class="dash-table">
            <thead><tr><th>อายุ PO</th><th class="text-right">จำนวน</th><th class="text-right">มูลค่า</th></tr></thead>
            <tbody>
              <tr><td class="font-bold text-emerald-600">0–7 วัน</td><td class="text-right"><?= $po_0_7['cnt'] ?></td><td class="text-right"><?= fm($po_0_7['val']) ?></td></tr>
              <tr><td class="font-bold text-amber-600">8–15 วัน</td><td class="text-right"><?= $po_8_15['cnt'] ?></td><td class="text-right"><?= fm($po_8_15['val']) ?></td></tr>
              <tr><td class="font-bold text-orange-600">16–30 วัน</td><td class="text-right"><?= $po_16_30['cnt'] ?></td><td class="text-right"><?= fm($po_16_30['val']) ?></td></tr>
              <tr><td class="font-bold text-red-600">&gt;30 วัน</td><td class="text-right"><?= $po_30p['cnt'] ?></td><td class="text-right"><?= fm($po_30p['val']) ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <!-- PO Aging Chart -->
    <div class="dash-section">
      <div class="dash-section-header">
        <i class="fas fa-chart-bar text-blue-500 text-lg"></i>
        <h3>PO Aging</h3>
        <span class="desc">มูลค่า PO แยกตามช่วงอายุ</span>
      </div>
      <div class="dash-section-body">
        <div class="chart-container"><canvas id="agingChart"></canvas></div>
      </div>
    </div>
  </div>

  <!-- ═══ 12. Compliance Dashboard ═══ -->
  <div class="dash-section">
    <div class="dash-section-header">
      <i class="fas fa-shield-alt text-purple-500 text-lg"></i>
      <h3>12. Compliance Dashboard</h3>
      <span class="desc">ตรวจสอบการปฏิบัติตามนโยบายจัดซื้อ</span>
    </div>
    <div class="dash-section-body">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <?php
        // PR without budget_type
        $no_budget = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM pr WHERE budget_type_id IS NULL AND deleted_at IS NULL AND status != 'cancelled'$sup_pr"));
        // Single quotation (no comparison possible - check if supplier only has 1 quote)
        $single_quote = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM pr p WHERE p.deleted_at IS NULL AND p.status != 'cancelled' AND (SELECT COUNT(*) FROM pr pp WHERE pp.supplier_id = p.supplier_id AND pp.deleted_at IS NULL) <= 1$sup_p_pr"));
        // PO without reference PR
        $po_no_ref = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM po WHERE (reference_no IS NULL OR reference_no = '') AND deleted_at IS NULL AND status != 'cancelled'$sup_po"));
        // Expired supplier documents (no document tracking, skip)
        // Receive before PO
        $receive_no_po = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM pr WHERE received_status != 'pending' AND (SELECT COUNT(*) FROM po WHERE reference_no LIKE CONCAT('%', pr.doc_no, '%')) = 0 AND deleted_at IS NULL$sup_pr"));

        $compliance_items = [
          ['PR ไม่มีงบประมาณ', $no_budget['cnt'] == 0, $no_budget['cnt']],
          ['PO ไม่มี PR อ้างอิง', $po_no_ref['cnt'] == 0, $po_no_ref['cnt']],
          ['รับของก่อนมี PO', $receive_no_po['cnt'] == 0, $receive_no_po['cnt']],
          ['PR ไม่มีใบเสนอราคา 3 ราย', true, null], // no data - assume OK
          ['แยกใบสั่งซื้อเพื่อหลบอำนาจอนุมัติ', false, null], // no data - flag manual
          ['PO ไม่มีสัญญา', true, null],
          ['Supplier หมดอายุเอกสาร', false, null],
          ['Invoice ไม่ตรง PO', true, null],
        ];
        foreach ($compliance_items as $ci):
          $icon = $ci[1] ? 'fa-check-circle text-emerald-500' : 'fa-exclamation-triangle text-red-500';
          $bg = $ci[1] ? 'bg-emerald-50' : 'bg-red-50';
          $label = $ci[1] ? '🟢 ปกติ' : '🔴 มีปัญหา';
          if ($ci[1] && $ci[2] === null) $label = '🟡 ไม่มีข้อมูล';
          if (!$ci[1] && $ci[2] === null) $label = '🟡 ต้องตรวจสอบ';
        ?>
        <div class="flex items-center justify-between p-3 <?= $bg ?> rounded-xl">
          <div class="flex items-center gap-2">
            <i class="fas <?= $icon ?>"></i>
            <span class="text-sm font-bold text-slate-700"><?= $ci[0] ?></span>
          </div>
          <span class="text-xs font-bold"><?= $label ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <p class="text-center text-[10px] text-slate-300 py-4">พิมพ์วันที่: <?= date('d/m/Y H:i') ?> | <?= $period_label_en ?> Procurement Dashboard</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const colors = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#3b82f6','#14b8a6','#f97316'];
const ctxColors = { green: 'rgba(16,185,129,0.8)', blue: 'rgba(99,102,241,0.8)', amber: 'rgba(245,158,11,0.8)', rose: 'rgba(244,63,94,0.8)' };
Chart.defaults.font.family = "'Sarabun','Prompt',sans-serif";

<?php if (!empty($chart_dates)): ?>
new Chart(document.getElementById('dailyChart'), {
  type: 'bar', data: {
    labels: <?= json_encode($chart_dates) ?>,
    datasets: [
      { label: 'PR', data: <?= json_encode($chart_pr_vals) ?>, backgroundColor: ctxColors.blue, borderRadius: 4 },
      { label: 'PO', data: <?= json_encode($chart_po_vals) ?>, backgroundColor: ctxColors.green, borderRadius: 4 }
    ]
  }, options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11, weight: 'bold' } } } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() } } }
  }
});
<?php endif; ?>

<?php if (!empty($dept_labels)): ?>
new Chart(document.getElementById('deptChart'), {
  type: 'doughnut', data: {
    labels: <?= json_encode($dept_labels) ?>,
    datasets: [{ data: <?= json_encode($dept_vals) ?>, backgroundColor: colors, borderWidth: 0, hoverOffset: 20 }]
  }, options: {
    responsive: true, maintainAspectRatio: false, cutout: '65%',
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' }, padding: 16 } } }
  }
});
<?php endif; ?>

<?php if (!empty($sup_labels)): ?>
new Chart(document.getElementById('supChart'), {
  type: 'bar', data: {
    labels: <?= json_encode(array_reverse($sup_labels)) ?>,
    datasets: [{ label: 'มูลค่า', data: <?= json_encode(array_reverse($sup_vals)) ?>, backgroundColor: colors.slice(0, <?= count($sup_labels) ?>).map(() => ctxColors.amber), borderRadius: 4 }]
  }, options: {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() } } }
  }
});
<?php endif; ?>

<?php if (!empty($bc_labels)): ?>
new Chart(document.getElementById('budgetChart'), {
  type: 'bar', data: {
    labels: <?= json_encode($bc_labels) ?>,
    datasets: [
      { label: 'ใช้แล้ว', data: <?= json_encode($bc_spent) ?>, backgroundColor: '#f59e0b', borderRadius: 4 },
      { label: 'คงเหลือ', data: <?= json_encode($bc_remain) ?>, backgroundColor: '#10b981', borderRadius: 4 }
    ]
  }, options: {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11, weight: 'bold' } } } },
    scales: { x: { stacked: true, ticks: { callback: v => v.toLocaleString() } }, y: { stacked: true } }
  }
});
<?php endif; ?>

<?php $pa = mysqli_fetch_assoc($po_aging); ?>
new Chart(document.getElementById('agingChart'), {
  type: 'bar', data: {
    labels: ['PO Aging'],
    datasets: [
      { label: '0–7 วัน (' + <?= $po_0_7['cnt'] ?> + ')', data: [<?= $po_0_7['val'] ?>], backgroundColor: '#10b981' },
      { label: '8–15 วัน (' + <?= $po_8_15['cnt'] ?> + ')', data: [<?= $po_8_15['val'] ?>], backgroundColor: '#f59e0b' },
      { label: '16–30 วัน (' + <?= $po_16_30['cnt'] ?> + ')', data: [<?= $po_16_30['val'] ?>], backgroundColor: '#f97316' },
      { label: '>30 วัน (' + <?= $po_30p['cnt'] ?> + ')', data: [<?= $po_30p['val'] ?>], backgroundColor: '#ef4444' }
    ]
  }, options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' }, padding: 12 } } },
    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { callback: v => v.toLocaleString() } } }
  }
});

<?php if (!empty($trend_labels)): ?>
new Chart(document.getElementById('trendChart'), {
  type: 'line', data: {
    labels: <?= json_encode($trend_labels) ?>,
    datasets: [
      { label: 'PR', data: <?= json_encode($trend_pr) ?>, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.3, pointRadius: 4 },
      { label: 'PO', data: <?= json_encode($trend_po) ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, tension: 0.3, pointRadius: 4 }
    ]
  }, options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11, weight: 'bold' } } } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() } } }
  }
});
<?php endif; ?>

<?php if (!empty($prs_labels)): ?>
new Chart(document.getElementById('prStatusChart'), {
  type: 'doughnut', data: {
    labels: <?= json_encode($prs_labels) ?>,
    datasets: [{ data: <?= json_encode($prs_vals) ?>, backgroundColor: ['#10b981','#f59e0b','#ef4444','#94a3b8'], borderWidth: 0 }]
  }, options: {
    responsive: true, maintainAspectRatio: false, cutout: '60%',
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 9, weight: 'bold' }, padding: 8 } } }
  }
});
<?php endif; ?>

<?php if (!empty($pos_labels)): ?>
new Chart(document.getElementById('poStatusChart'), {
  type: 'doughnut', data: {
    labels: <?= json_encode($pos_labels) ?>,
    datasets: [{ data: <?= json_encode($pos_vals) ?>, backgroundColor: ['#3b82f6','#f59e0b','#ef4444','#94a3b8'], borderWidth: 0 }]
  }, options: {
    responsive: true, maintainAspectRatio: false, cutout: '60%',
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 9, weight: 'bold' }, padding: 8 } } }
  }
});
<?php endif; ?>

<?php if (!empty($gr_labels)): ?>
new Chart(document.getElementById('grStatusChart'), {
  type: 'doughnut', data: {
    labels: <?= json_encode($gr_labels) ?>,
    datasets: [{ data: <?= json_encode($gr_vals) ?>, backgroundColor: ['#10b981','#f59e0b','#94a3b8'], borderWidth: 0 }]
  }, options: {
    responsive: true, maintainAspectRatio: false, cutout: '60%',
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 9, weight: 'bold' }, padding: 8 } } }
  }
});
<?php endif; ?>

<?php if (!empty($exp_labels)): ?>
new Chart(document.getElementById('expCatChart'), {
  type: 'bar', data: {
    labels: <?= json_encode($exp_labels) ?>,
    datasets: [{ label: 'มูลค่า', data: <?= json_encode($exp_vals) ?>, backgroundColor: ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#3b82f6'], borderRadius: 4 }]
  }, options: {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() } } }
  }
});
<?php endif; ?>
</script>

<?php include('footer.php'); ?>
