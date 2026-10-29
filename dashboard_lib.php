<?php

const DASH_THAI_MONTHS = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
const DASH_THAI_MONTHS_SHORT = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

function dash_can_view(string $role): bool
{
    return in_array($role, ['acc', 'mgr', 'mgr2', 'procure', 'admin'], true);
}

/**
 * แปลงตัวกรองเป็นช่วงเวลา [start, end) พร้อมช่วงก่อนหน้า (ไว้เทียบ) และช่วงกราฟแนวโน้ม
 */
function dash_resolve_period(string $period, string $date, int $month, int $year, ?DateTimeImmutable $today = null): array
{
    $today = $today ?? new DateTimeImmutable('today');
    if (!in_array($period, ['daily', 'monthly', 'yearly'], true)) {
        $period = 'monthly';
    }
    if ($year < 2000 || $year > 2100) {
        $year = (int)$today->format('Y');
    }
    if ($month < 1 || $month > 12) {
        $month = (int)$today->format('n');
    }
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$day || $day->format('Y-m-d') !== $date) {
        $day = $today;
    }

    if ($period === 'daily') {
        $start = $day;
        $end = $start->modify('+1 day');
        $prevStart = $start->modify('-1 day');
        $trendStart = $start->modify('-13 days');
        $bucket = 'day';
        $label = 'วันที่ ' . dash_thai_date($start);
        $prevLabel = 'เมื่อวาน';
    } elseif ($period === 'monthly') {
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('+1 month');
        $prevStart = $start->modify('-1 month');
        $trendStart = $start;
        $bucket = 'day';
        $label = DASH_THAI_MONTHS[$month] . ' ' . ($year + 543);
        $prevLabel = 'เดือนก่อน';
    } else {
        $start = new DateTimeImmutable(sprintf('%04d-01-01', $year));
        $end = $start->modify('+1 year');
        $prevStart = $start->modify('-1 year');
        $trendStart = $start;
        $bucket = 'month';
        $label = 'ปี ' . ($year + 543);
        $prevLabel = 'ปีก่อน';
    }

    return [
        'period' => $period,
        'date' => $day->format('Y-m-d'),
        'month' => (int)$start->format('n'),
        'year' => (int)$start->format('Y'),
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'prev_start' => $prevStart->format('Y-m-d'),
        'prev_end' => $start->format('Y-m-d'),
        'trend_start' => $trendStart->format('Y-m-d'),
        'trend_end' => $end->format('Y-m-d'),
        'bucket' => $bucket,
        'label' => $label,
        'prev_label' => $prevLabel,
    ];
}

function dash_thai_date(DateTimeInterface $date): string
{
    return (int)$date->format('j') . ' ' . DASH_THAI_MONTHS_SHORT[(int)$date->format('n')] . ' ' . ((int)$date->format('Y') + 543);
}

/** รายการช่วงเวลาบนแกนกราฟ เพื่อให้วัน/เดือนที่ไม่มีข้อมูลแสดงเป็น 0 แทนการหายไป */
function dash_trend_buckets(array $range): array
{
    $buckets = [];
    $cursor = new DateTimeImmutable($range['trend_start']);
    $end = new DateTimeImmutable($range['trend_end']);
    while ($cursor < $end) {
        if ($range['bucket'] === 'month') {
            $buckets[$cursor->format('Y-m')] = DASH_THAI_MONTHS_SHORT[(int)$cursor->format('n')];
            $cursor = $cursor->modify('+1 month');
        } else {
            $buckets[$cursor->format('Y-m-d')] = $cursor->format('j') . ' ' . DASH_THAI_MONTHS_SHORT[(int)$cursor->format('n')];
            $cursor = $cursor->modify('+1 day');
        }
    }
    return $buckets;
}

function dash_percent_change(float $current, float $previous): ?float
{
    if (abs($previous) < 0.00001) {
        return null;
    }
    return round(($current - $previous) / $previous * 100, 1);
}

/** รวมยอดตาม key: คืน [[label, count, value], ...] เรียงมูลค่ามากไปน้อย */
function dash_group(array $rows, string $key, string $valueKey = 'grand_total', string $emptyLabel = 'ไม่ระบุ'): array
{
    $groups = [];
    foreach ($rows as $row) {
        $label = trim((string)($row[$key] ?? ''));
        $label = $label === '' ? $emptyLabel : $label;
        $groups[$label] ??= ['label' => $label, 'count' => 0, 'value' => 0.0];
        $groups[$label]['count']++;
        $groups[$label]['value'] += (float)($row[$valueKey] ?? 0);
    }
    usort($groups, fn ($a, $b) => $b['value'] <=> $a['value'] ?: $b['count'] <=> $a['count']);
    foreach ($groups as &$group) {
        $group['value'] = round($group['value'], 2);
    }
    return $groups;
}

function dash_pr_status_label(string $status): string
{
    return ['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ', 'cancelled' => 'ยกเลิก'][$status] ?? 'ไม่ระบุ';
}

function dash_po_status_label(string $status): string
{
    return ['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'cancelled' => 'ยกเลิก'][$status] ?? 'ไม่ระบุ';
}

function dash_received_label(string $status): string
{
    return ['pending' => 'ยังไม่รับของ', 'partial' => 'รับบางส่วน', 'received' => 'รับของแล้ว'][$status] ?? 'ไม่ระบุ';
}

/** สรุปตัวเลขหลักจากรายการ PR/PO ของช่วงเวลา */
function dash_summarize(array $prRows, array $poRows): array
{
    $summary = [
        'pr_count' => 0, 'pr_value' => 0.0,
        'pr_approved_count' => 0, 'pr_approved_value' => 0.0,
        'pr_pending_count' => 0, 'pr_rejected_count' => 0,
        'po_count' => 0, 'po_value' => 0.0,
        'po_approved_count' => 0, 'po_approved_value' => 0.0,
        'approval_days_avg' => null,
    ];
    $approvalHours = [];
    foreach ($prRows as $row) {
        if (($row['status'] ?? '') === 'cancelled') {
            continue;
        }
        $summary['pr_count']++;
        $summary['pr_value'] += (float)$row['grand_total'];
        if ($row['status'] === 'approved') {
            $summary['pr_approved_count']++;
            $summary['pr_approved_value'] += (float)$row['grand_total'];
            if (!empty($row['approved_at'])) {
                $approvalHours[] = max(0, (strtotime($row['approved_at']) - strtotime($row['created_at'])) / 3600);
            }
        } elseif ($row['status'] === 'pending') {
            $summary['pr_pending_count']++;
        } elseif ($row['status'] === 'rejected') {
            $summary['pr_rejected_count']++;
        }
    }
    foreach ($poRows as $row) {
        if (($row['status'] ?? '') === 'cancelled') {
            continue;
        }
        $summary['po_count']++;
        $summary['po_value'] += (float)$row['grand_total'];
        if ($row['status'] === 'approved') {
            $summary['po_approved_count']++;
            $summary['po_approved_value'] += (float)$row['grand_total'];
        }
    }
    if ($approvalHours) {
        $summary['approval_days_avg'] = round(array_sum($approvalHours) / count($approvalHours) / 24, 1);
    }
    foreach (['pr_value', 'pr_approved_value', 'po_value', 'po_approved_value'] as $key) {
        $summary[$key] = round($summary[$key], 2);
    }
    return $summary;
}

function dash_fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** เงื่อนไขช่วงเวลา + บริษัท ใช้ร่วมทุก query (alias ตารางต้องเป็น t) */
function dash_scope(string $start, string $end, int $supId, string $dateColumn = 't.created_at'): array
{
    $sql = " AND {$dateColumn} >= ? AND {$dateColumn} < ?";
    $types = 'ss';
    $params = [$start, $end];
    if ($supId > 0) {
        $sql .= ' AND t.supplier_id = ?';
        $types .= 'i';
        $params[] = $supId;
    }
    return [$sql, $types, $params];
}

function dash_fetch_pr_rows(mysqli $conn, string $start, string $end, int $supId): array
{
    [$scope, $types, $params] = dash_scope($start, $end, $supId);
    $rows = dash_fetch_all($conn,
        "SELECT t.id, t.doc_no, t.created_at, t.approved_at, t.rejected_at, t.reject_reason,
                COALESCE(t.status, '') status, COALESCE(t.received_status, 'pending') received_status,
                COALESCE(t.grand_total, 0) grand_total, t.requested_by,
                s.company_name company, c.customer_name vendor, ec.name category, bt.name budget, u.name creator,
                (SELECT item_desc FROM pr_items WHERE pr_id = t.id ORDER BY id LIMIT 1) first_item,
                (SELECT COUNT(*) FROM pr_items WHERE pr_id = t.id) item_count
         FROM pr t
         LEFT JOIN suppliers s ON s.id = t.supplier_id
         LEFT JOIN customers c ON c.id = t.customer_id
         LEFT JOIN expense_categories ec ON ec.id = t.expense_cat_id
         LEFT JOIN budget_types bt ON bt.id = t.budget_type_id
         LEFT JOIN users u ON u.id = t.created_by
         WHERE t.deleted_at IS NULL{$scope}
         ORDER BY t.created_at DESC",
        $types, $params);
    foreach ($rows as &$row) {
        $row['company'] = supplier_display_name((string)$row['company']);
        $row['status_label'] = dash_pr_status_label($row['status']);
        $row['received_label'] = dash_received_label($row['received_status']);
        $row['grand_total'] = (float)$row['grand_total'];
    }
    return $rows;
}

function dash_fetch_po_rows(mysqli $conn, string $start, string $end, int $supId): array
{
    [$scope, $types, $params] = dash_scope($start, $end, $supId);
    $rows = dash_fetch_all($conn,
        "SELECT t.id, t.doc_no, t.express_ref_code, t.reference_no, t.created_at, t.approved_at, t.due_date,
                COALESCE(t.status, '') status, COALESCE(t.grand_total, 0) grand_total,
                s.company_name company, c.customer_name vendor, u.name creator
         FROM po t
         LEFT JOIN suppliers s ON s.id = t.supplier_id
         LEFT JOIN customers c ON c.id = t.customer_id
         LEFT JOIN users u ON u.id = t.created_by
         WHERE t.deleted_at IS NULL{$scope}
         ORDER BY t.created_at DESC",
        $types, $params);
    foreach ($rows as &$row) {
        $row['company'] = supplier_display_name((string)$row['company']);
        $row['status_label'] = dash_po_status_label($row['status']);
        $row['grand_total'] = (float)$row['grand_total'];
    }
    return $rows;
}

/** รายการสินค้าจาก PR ที่อนุมัติแล้ว — ถ้า PR ไม่มีราคา ใช้ราคาจาก PO ที่อ้างอิง PR นั้น */
function dash_fetch_item_rows(mysqli $conn, string $start, string $end, int $supId): array
{
    [$scope, $types, $params] = dash_scope($start, $end, $supId);
    $rows = dash_fetch_all($conn,
        "SELECT t.doc_no, t.created_at, s.company_name company, ec.name category, c.customer_name vendor,
                i.item_desc, i.item_unit, COALESCE(i.item_qty, 0) qty,
                COALESCE(NULLIF(i.item_price, 0),
                    (SELECT poi.item_price FROM po_items poi INNER JOIN po ON po.id = poi.po_id
                     WHERE po.reference_no = t.doc_no AND po.deleted_at IS NULL AND poi.item_desc = i.item_desc LIMIT 1), 0) price
         FROM pr_items i
         INNER JOIN pr t ON t.id = i.pr_id
         LEFT JOIN suppliers s ON s.id = t.supplier_id
         LEFT JOIN customers c ON c.id = t.customer_id
         LEFT JOIN expense_categories ec ON ec.id = t.expense_cat_id
         WHERE t.deleted_at IS NULL AND t.status = 'approved'{$scope}
         ORDER BY t.created_at DESC, i.id",
        $types, $params);
    foreach ($rows as &$row) {
        $row['company'] = supplier_display_name((string)$row['company']);
        $row['qty'] = (float)$row['qty'];
        $row['price'] = (float)$row['price'];
        $row['amount'] = round($row['qty'] * $row['price'], 2);
    }
    return $rows;
}

function dash_fetch_trend(mysqli $conn, array $range, int $supId): array
{
    $format = $range['bucket'] === 'month' ? '%Y-%m' : '%Y-%m-%d';
    $series = [];
    foreach (['pr', 'po'] as $table) {
        [$scope, $types, $params] = dash_scope($range['trend_start'], $range['trend_end'], $supId);
        $rows = dash_fetch_all($conn,
            "SELECT DATE_FORMAT(t.created_at, '{$format}') bucket, COUNT(*) cnt, COALESCE(SUM(t.grand_total), 0) val
             FROM {$table} t
             WHERE t.deleted_at IS NULL AND COALESCE(t.status, '') <> 'cancelled'{$scope}
             GROUP BY bucket",
            $types, $params);
        foreach ($rows as $row) {
            $series[$table][$row['bucket']] = ['count' => (int)$row['cnt'], 'value' => (float)$row['val']];
        }
    }
    $trend = [];
    foreach (dash_trend_buckets($range) as $key => $label) {
        $trend[] = [
            'key' => $key,
            'label' => $label,
            'pr_count' => $series['pr'][$key]['count'] ?? 0,
            'pr_value' => $series['pr'][$key]['value'] ?? 0.0,
            'po_count' => $series['po'][$key]['count'] ?? 0,
            'po_value' => $series['po'][$key]['value'] ?? 0.0,
        ];
    }
    return $trend;
}

/** งานค้าง ณ ปัจจุบัน (ไม่ขึ้นกับช่วงเวลาที่เลือก) */
function dash_fetch_backlog(mysqli $conn, int $supId): array
{
    $supSql = $supId > 0 ? ' AND t.supplier_id = ?' : '';
    $types = $supId > 0 ? 'i' : '';
    $params = $supId > 0 ? [$supId] : [];

    $pendingPr = dash_fetch_all($conn,
        "SELECT t.id, t.doc_no, t.created_at, COALESCE(t.grand_total, 0) grand_total, s.company_name company, u.name creator,
                DATEDIFF(CURDATE(), t.created_at) wait_days,
                CASE
                    WHEN t.approved_by_0 IS NULL THEN 'GM'
                    WHEN t.approved_by IS NULL THEN 'จัดซื้อ'
                    WHEN t.approved_by_1 IS NULL THEN 'GM บัญชี'
                    WHEN t.approved_by_2 IS NULL THEN 'MGR'
                    ELSE 'MGR2'
                END next_approver
         FROM pr t
         LEFT JOIN suppliers s ON s.id = t.supplier_id
         LEFT JOIN users u ON u.id = t.created_by
         WHERE t.deleted_at IS NULL AND t.status = 'pending'{$supSql}
         ORDER BY t.created_at ASC",
        $types, $params);

    $pendingPo = dash_fetch_all($conn,
        "SELECT t.id, t.doc_no, t.express_ref_code, t.created_at, COALESCE(t.grand_total, 0) grand_total,
                s.company_name company, c.customer_name vendor, DATEDIFF(CURDATE(), t.created_at) wait_days
         FROM po t
         LEFT JOIN suppliers s ON s.id = t.supplier_id
         LEFT JOIN customers c ON c.id = t.customer_id
         WHERE t.deleted_at IS NULL AND t.status = 'pending'{$supSql}
         ORDER BY t.created_at ASC",
        $types, $params);

    $waitingReceive = dash_fetch_all($conn,
        "SELECT t.id, t.doc_no, t.created_at, t.approved_at, t.due_date, COALESCE(t.grand_total, 0) grand_total,
                s.company_name company, c.customer_name vendor,
                DATEDIFF(CURDATE(), COALESCE(t.approved_at, t.created_at)) wait_days,
                (t.due_date IS NOT NULL AND t.due_date <> '0000-00-00' AND t.due_date < CURDATE()) is_late
         FROM pr t
         LEFT JOIN suppliers s ON s.id = t.supplier_id
         LEFT JOIN customers c ON c.id = t.customer_id
         WHERE t.deleted_at IS NULL AND t.status = 'approved' AND COALESCE(t.received_status, 'pending') <> 'received'{$supSql}
         ORDER BY wait_days DESC",
        $types, $params);

    foreach ([&$pendingPr, &$pendingPo, &$waitingReceive] as &$list) {
        foreach ($list as &$row) {
            $row['company'] = supplier_display_name((string)$row['company']);
            $row['grand_total'] = (float)$row['grand_total'];
            $row['wait_days'] = (int)$row['wait_days'];
        }
    }
    return ['pending_pr' => $pendingPr, 'pending_po' => $pendingPo, 'waiting_receive' => $waitingReceive];
}

/** งบประมาณทั้งปี เทียบยอด PR ที่อนุมัติในปีเดียวกัน (งบเป็นรายปี จึงไม่เทียบกับยอดรายวัน/รายเดือน) */
function dash_fetch_budgets(mysqli $conn, int $year, int $supId): array
{
    $start = sprintf('%04d-01-01', $year);
    $end = sprintf('%04d-01-01', $year + 1);
    $supSql = $supId > 0 ? ' AND p.supplier_id = ?' : '';
    $types = 'ss' . ($supId > 0 ? 'i' : '');
    $params = $supId > 0 ? [$start, $end, $supId] : [$start, $end];
    $rows = dash_fetch_all($conn,
        "SELECT bt.id, bt.name,
                bt.budget_amount + COALESCE((SELECT SUM(amount) FROM budget_adjustments WHERE budget_type_id = bt.id AND status = 'approved'), 0) total_budget,
                COALESCE(spent.total, 0) spent
         FROM budget_types bt
         LEFT JOIN (
             SELECT p.budget_type_id, SUM(p.grand_total) total
             FROM pr p
             WHERE p.deleted_at IS NULL AND p.status = 'approved' AND p.created_at >= ? AND p.created_at < ?{$supSql}
             GROUP BY p.budget_type_id
         ) spent ON spent.budget_type_id = bt.id
         WHERE bt.status = 'approved'
         ORDER BY spent DESC",
        $types, $params);
    foreach ($rows as &$row) {
        $row['total_budget'] = (float)$row['total_budget'];
        $row['spent'] = (float)$row['spent'];
        $row['remaining'] = round($row['total_budget'] - $row['spent'], 2);
        $row['used_pct'] = $row['total_budget'] > 0 ? round($row['spent'] / $row['total_budget'] * 100, 1) : null;
    }
    return $rows;
}
