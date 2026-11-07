<?php
/**
 * Timeline ของใบขอซื้อ (PR)
 *
 * - pr_log_activity(): บันทึกทุกการดำเนินการลง pr_activity_log (ตั้งแต่มีฟีเจอร์นี้)
 * - pr_timeline_build(): รวม log + ข้อมูลเดิมในตาราง pr/po/receiving_reports
 *   (สำหรับ PR เก่าที่ยังไม่มี log) และคำนวณขั้นตอนที่ยังรออยู่ตามประเภทวงเงิน
 */

const PR_STAGE_LABELS = [
    'approved_by_0' => 'หัวหน้างาน',
    'approved_by' => 'จัดซื้อ',
    'approved_by_1' => 'GM ฝ่ายบัญชี',
    'approved_by_2' => 'ผู้จัดการ (SUP)',
    'approved_by_3' => 'ผู้บริหาร (CEO)',
];

const PR_STAGE_TIME_COLS = [
    'approved_by_0' => 'approved_at_0',
    'approved_by' => 'approved_at',
    'approved_by_1' => 'approved_at_1',
    'approved_by_2' => 'approved_at_2',
    'approved_by_3' => 'approved_at_3',
];

/** บันทึกการดำเนินการ — ไม่ให้ error ของ log ทำให้งานหลักล้ม */
function pr_log_activity(mysqli $conn, int $pr_id, string $action, ?int $actor_id = null, ?string $note = null, ?string $stage = null): void
{
    try {
        $stmt = $conn->prepare("INSERT INTO pr_activity_log (pr_id, action, stage, actor_id, note) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('issis', $pr_id, $action, $stage, $actor_id, $note);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('pr_log_activity failed: ' . $e->getMessage());
    }
}

/** ขั้นอนุมัติที่ต้องผ่านตามประเภทวงเงิน (ตรงกับเงื่อนไขใน api/update_pr_status_new.php) */
function pr_required_stages(string $limit_type): array
{
    switch (strtolower(trim($limit_type))) {
        case 'low':
            return ['approved_by_0', 'approved_by'];
        case 'mid':
            return ['approved_by_0', 'approved_by', 'approved_by_2'];
        case 'high':
            return ['approved_by_0', 'approved_by', 'approved_by_3'];
        default:
            return array_keys(PR_STAGE_LABELS);
    }
}

function pr_timeline_action_meta(string $action, ?string $stage): array
{
    $stage_label = PR_STAGE_LABELS[$stage] ?? 'ผู้อนุมัติ';
    $map = [
        'created' => ['สร้างใบขอซื้อ', 'fa-file-circle-plus', 'indigo'],
        'edited' => ['แก้ไขใบขอซื้อ', 'fa-pen', 'amber'],
        'resubmitted' => ['แก้ไขและส่งใหม่', 'fa-rotate-right', 'amber'],
        'quotes_edited' => ['แก้ไขใบเทียบราคา', 'fa-scale-balanced', 'amber'],
        'approved' => ['อนุมัติโดย' . $stage_label, 'fa-check', 'emerald'],
        'fully_approved' => ['อนุมัติครบทุกขั้นตอน', 'fa-circle-check', 'emerald'],
        'po_created' => ['ออกใบสั่งซื้อ (PO)', 'fa-file-invoice', 'sky'],
        'rejected' => ['ปฏิเสธโดย' . ($stage ? $stage_label : 'ผู้อนุมัติ'), 'fa-xmark', 'red'],
        'received' => ['รับของครบแล้ว', 'fa-box-open', 'emerald'],
        'partial_received' => ['รับของบางส่วน', 'fa-box', 'amber'],
        'installment_paid' => ['ชำระเงินงวด', 'fa-money-bill-wave', 'sky'],
    ];
    [$title, $icon, $color] = $map[$action] ?? [$action, 'fa-circle', 'slate'];
    return ['title' => $title, 'icon' => $icon, 'color' => $color];
}

/**
 * สร้าง timeline ของ PR
 * @return array{pr: array, events: array, upcoming: array}|null
 */
function pr_timeline_build(mysqli $conn, int $pr_id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM pr WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param('i', $pr_id);
    $stmt->execute();
    $pr = $stmt->get_result()->fetch_assoc();
    if (!$pr) {
        return null;
    }

    $events = [];
    $logged = []; // action|stage => [timestamps]

    $res = $conn->query("SELECT * FROM pr_activity_log WHERE pr_id = $pr_id ORDER BY created_at ASC, id ASC");
    while ($row = $res->fetch_assoc()) {
        $events[] = [
            'action' => $row['action'], 'stage' => $row['stage'], 'actor_id' => $row['actor_id'],
            'note' => $row['note'], 'at' => $row['created_at'], 'source' => 'log',
        ];
        $logged[$row['action'] . '|' . ($row['stage'] ?? '')][] = strtotime($row['created_at']);
    }

    // เพิ่มเหตุการณ์จากข้อมูลเดิม ถ้ายังไม่มีใน log (กันซ้ำด้วยเวลาใกล้เคียง 2 นาที)
    $add_derived = function (string $action, ?string $stage, ?int $actor, ?string $at, ?string $note = null) use (&$events, $logged) {
        if (empty($at)) {
            return;
        }
        $key = $action . '|' . ($stage ?? '');
        foreach ($logged[$key] ?? [] as $ts) {
            if (abs($ts - strtotime($at)) <= 120) {
                return;
            }
        }
        $events[] = ['action' => $action, 'stage' => $stage, 'actor_id' => $actor, 'note' => $note, 'at' => $at, 'source' => 'record'];
    };

    $add_derived('created', null, (int)$pr['created_by'] ?: null, $pr['created_at']);
    foreach (PR_STAGE_TIME_COLS as $stage => $time_col) {
        if (!empty($pr[$stage])) {
            $add_derived('approved', $stage, (int)$pr[$stage], $pr[$time_col] ?: null);
        }
    }
    if (trim((string)$pr['reject_reason']) !== '') {
        // ยังไม่ผ่านหัวหน้า = หัวหน้าเป็นคนปฏิเสธ (ตรงกับที่ api/reject_pr.php บันทึก)
        $add_derived('rejected', empty($pr['approved_by_0']) ? 'approved_by_0' : null, (int)$pr['rejected_by'] ?: null, $pr['rejected_at'], $pr['reject_reason']);
    }

    $po = null;
    $stmt = $conn->prepare("SELECT id, doc_no, created_by, created_at FROM po WHERE reference_no = ? AND deleted_at IS NULL ORDER BY id ASC LIMIT 1");
    $stmt->bind_param('s', $pr['doc_no']);
    $stmt->execute();
    $po = $stmt->get_result()->fetch_assoc();
    if ($po) {
        $add_derived('po_created', null, (int)$po['created_by'] ?: null, $po['created_at'], 'เลขที่ ' . $po['doc_no']);
    }

    $reports = $conn->query("SELECT received_by, note, created_at FROM receiving_reports WHERE pr_id = $pr_id ORDER BY created_at ASC")->fetch_all(MYSQLI_ASSOC);
    $has_report = $reports !== [];
    foreach ($reports as $i => $row) {
        $is_last_full = $i === count($reports) - 1 && $pr['received_status'] === 'received';
        $add_derived($is_last_full ? 'received' : 'partial_received', null, (int)$row['received_by'] ?: null, $row['created_at'], $row['note'] ?: null);
    }
    if (!$has_report && in_array($pr['received_status'], ['received', 'partial'], true)) {
        $add_derived($pr['received_status'] === 'received' ? 'received' : 'partial_received', null, (int)$pr['received_by'] ?: null, $pr['received_at']);
    }

    $res = $conn->query("SELECT installment_no, paid_amount, amount, paid_at FROM installment_schedule WHERE pr_id = $pr_id AND paid_at IS NOT NULL ORDER BY paid_at ASC");
    while ($row = $res->fetch_assoc()) {
        $amount = (float)($row['paid_amount'] ?: $row['amount']);
        $add_derived('installment_paid', null, null, $row['paid_at'], 'งวดที่ ' . $row['installment_no'] . ' · ' . number_format($amount, 2) . ' บาท');
    }

    usort($events, fn($a, $b) => strcmp($a['at'], $b['at']));

    // ชื่อผู้ดำเนินการ
    $ids = array_values(array_unique(array_filter(array_map(fn($e) => (int)$e['actor_id'], $events))));
    $names = [];
    if ($ids) {
        $res = $conn->query("SELECT id, name, role FROM users WHERE id IN (" . implode(',', $ids) . ")");
        while ($u = $res->fetch_assoc()) {
            $names[(int)$u['id']] = $u['name'];
        }
    }
    foreach ($events as &$e) {
        $e += pr_timeline_action_meta($e['action'], $e['stage']);
        $e['actor_name'] = $names[(int)$e['actor_id']] ?? null;
    }
    unset($e);

    // ขั้นตอนที่ยังรออยู่
    $upcoming = [];
    $is_rejected = $pr['status'] === 'rejected' && (int)$pr['allow_resubmit'] !== 3;
    if ($is_rejected) {
        $upcoming[] = [
            'title' => (int)$pr['allow_resubmit'] === 1 ? 'รอผู้ขอซื้อแก้ไขและส่งใหม่' : 'ใบขอซื้อถูกปฏิเสธ (ไม่อนุญาตให้ส่งใหม่)',
            'icon' => 'fa-hourglass-half', 'state' => 'blocked',
        ];
    } elseif ($pr['status'] !== 'approved') {
        $first = true;
        foreach (pr_required_stages((string)$pr['budget_limit_type']) as $stage) {
            if (empty($pr[$stage])) {
                $upcoming[] = ['title' => 'รออนุมัติ: ' . PR_STAGE_LABELS[$stage], 'icon' => 'fa-user-check', 'state' => $first ? 'current' : 'waiting'];
                $first = false;
            }
        }
        $upcoming[] = ['title' => 'ออกใบสั่งซื้อ (PO)', 'icon' => 'fa-file-invoice', 'state' => 'waiting'];
        $upcoming[] = ['title' => 'รับของ', 'icon' => 'fa-box-open', 'state' => 'waiting'];
    } elseif ($pr['received_status'] !== 'received') {
        $upcoming[] = ['title' => $pr['received_status'] === 'partial' ? 'รอรับของส่วนที่เหลือ' : 'รอรับของ', 'icon' => 'fa-box-open', 'state' => 'current'];
    }

    return [
        'pr' => [
            'id' => (int)$pr['id'], 'doc_no' => $pr['doc_no'], 'status' => $pr['status'],
            'received_status' => $pr['received_status'], 'grand_total' => (float)$pr['grand_total'],
            'budget_limit_type' => $pr['budget_limit_type'], 'po_doc_no' => $po['doc_no'] ?? null,
        ],
        'events' => $events,
        'upcoming' => $upcoming,
    ];
}
