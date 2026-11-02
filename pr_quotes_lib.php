<?php
/**
 * ใบเทียบราคาใน PR (Price Comparison)
 *
 * - เทียบได้ 2–3 ร้าน (stores) ต่อ PR เลือกร้านเดียวทั้งใบ
 * - เป็นตัวเลือก แต่ถ้ายอดสุทธิ PR เกิน PR_QUOTE_REQUIRED_ABOVE บาท ต้องเทียบราคา
 * - ถ้าไม่เลือกร้านที่ราคาถูกที่สุด ต้องระบุเหตุผล
 * - ราคาของร้านที่เลือกจะถูกใช้เป็นราคาใน pr_items และ store_id ของ PR
 */

const PR_QUOTE_REQUIRED_ABOVE = 10000;
const PR_QUOTE_MIN_STORES = 2;
const PR_QUOTE_MAX_STORES = 4;
const PR_QUOTE_UPLOAD_DIR = __DIR__ . '/uploads/pr_quotes/';

// หัวแบบฟอร์ม (ตามแบบฟอร์มเปรียบเทียบราคาของบริษัท)
const PR_QUOTE_FORM_CODE = 'FO-PC-004 R 2';
const PR_QUOTE_FORM_REV = 'Rev : 14/03/60';
const PR_QUOTE_SIGNER_TITLES = [
    'ผู้จัดทำ' => 'เจ้าหน้าที่จัดซื้อ',
    'ผู้ตรวจสอบ' => 'ผู้จัดการฝ่ายบริหารสำนักงาน',
    'ผู้อนุมัติ' => 'ประธานเจ้าหน้าที่ฝ่ายการเงิน',
];

/** วิธีการเสนอราคาตามยอดซื้อ: ไม่เกิน 100,000 = ตกลงราคา, ไม่เกิน 2,000,000 = สอบราคา, เกินกว่านั้น = ประกวดราคา */
function pr_quote_method(float $amount): string
{
    if ($amount <= 100000) {
        return 'ตกลงราคา';
    }
    return $amount <= 2000000 ? 'สอบราคา' : 'ประกวดราคา';
}

function pr_quote_is_required(float $grand_total): bool
{
    return $grand_total > PR_QUOTE_REQUIRED_ABOVE + 0.001;
}

/**
 * คำนวณยอดของแต่ละร้านด้วยสูตรเดียวกับ PR: sum(qty * price - discount) แล้วคิด VAT/WHT
 */
function pr_quote_totals(array $prices, array $qtys, array $discounts, float $vat_percent, float $wht_percent): array
{
    $subtotal = 0.0;
    $lines = [];
    foreach ($prices as $key => $price) {
        $line = round(((float)($qtys[$key] ?? 0) * (float)$price) - (float)($discounts[$key] ?? 0), 2);
        $lines[$key] = $line;
        $subtotal += $line;
    }
    $vat = $subtotal * ($vat_percent / 100);
    $wht = $subtotal * ($wht_percent / 100);
    return [
        'lines' => $lines,
        'subtotal' => round($subtotal, 2),
        'vat' => round($vat, 2),
        'grand_total' => round(($subtotal + $vat) - $wht, 2),
    ];
}

/**
 * อ่านและตรวจข้อมูลใบเทียบราคาจาก POST (ไม่แตะ DB)
 *
 * @param array $line_keys key ของรายการสินค้าที่จะบันทึก (ตรงกับ item_desc[])
 * @return array{enabled: bool, quotes: array, selected: ?int, reason: string}
 * @throws DomainException เมื่อข้อมูลไม่ครบ
 */
function pr_quotes_parse(array $post, array $line_keys, array $qtys, array $discounts, float $vat_percent, float $wht_percent): array
{
    $result = ['enabled' => !empty($post['quote_enabled']), 'quotes' => [], 'selected' => null, 'reason' => ''];
    if (!$result['enabled']) {
        return $result;
    }

    $raw_quotes = is_array($post['quotes'] ?? null) ? $post['quotes'] : [];
    $raw_prices = is_array($post['quote_price'] ?? null) ? $post['quote_price'] : [];
    $selected_slot = isset($post['quote_selected']) && $post['quote_selected'] !== '' ? (int)$post['quote_selected'] : -1;

    $seen_stores = [];
    foreach ($raw_quotes as $slot => $q) {
        $slot = (int)$slot;
        if ($slot < 0 || $slot >= PR_QUOTE_MAX_STORES || !is_array($q)) {
            continue;
        }
        $store_id = (int)($q['store_id'] ?? 0);
        if ($store_id <= 0) {
            continue; // คอลัมน์ที่ไม่ได้เลือกร้าน = ไม่ใช้
        }
        $n = count($result['quotes']) + 1;
        if (isset($seen_stores[$store_id])) {
            throw new DomainException("ใบเทียบราคา: ร้านที่ {$n} ซ้ำกับร้านอื่น กรุณาเลือกร้านที่ต่างกัน");
        }
        $seen_stores[$store_id] = true;

        $prices = [];
        $slot_prices = is_array($raw_prices[$slot] ?? null) ? $raw_prices[$slot] : [];
        foreach ($line_keys as $key) {
            $price = (float)($slot_prices[$key] ?? 0);
            if ($price <= 0) {
                throw new DomainException("ใบเทียบราคา: กรุณาใส่ราคาของร้านที่ {$n} ให้ครบทุกรายการ");
            }
            $prices[$key] = round($price, 2);
        }

        $delivery = trim((string)($q['delivery_days'] ?? ''));
        $result['quotes'][] = [
            'slot' => $slot,
            'store_id' => $store_id,
            'quote_no' => mb_substr(trim((string)($q['quote_no'] ?? '')), 0, 100),
            'delivery_days' => $delivery === '' ? null : max(0, (int)$delivery),
            'payment_term' => mb_substr(trim((string)($q['payment_term'] ?? '')), 0, 100),
            'service_note' => mb_substr(trim((string)($q['service_note'] ?? '')), 0, 255),
            'existing_attachment' => basename((string)($q['existing_attachment'] ?? '')),
            'prices' => $prices,
            'totals' => pr_quote_totals($prices, $qtys, $discounts, $vat_percent, $wht_percent),
        ];
    }

    // ราคากลางต่อหน่วย (ไม่บังคับ)
    $raw_ref = is_array($post['quote_ref_price'] ?? null) ? $post['quote_ref_price'] : [];
    $result['ref_prices'] = [];
    foreach ($line_keys as $key) {
        $ref = trim((string)($raw_ref[$key] ?? ''));
        $result['ref_prices'][$key] = ($ref === '' || (float)$ref <= 0) ? null : round((float)$ref, 2);
    }

    if (count($result['quotes']) < PR_QUOTE_MIN_STORES) {
        throw new DomainException('ใบเทียบราคา: ต้องมีอย่างน้อย ' . PR_QUOTE_MIN_STORES . ' ร้าน');
    }

    $min_total = min(array_map(fn($q) => $q['totals']['grand_total'], $result['quotes']));
    foreach ($result['quotes'] as $i => $q) {
        if ($q['slot'] === $selected_slot) {
            $result['selected'] = $i;
        }
    }
    if ($result['selected'] === null) {
        throw new DomainException('ใบเทียบราคา: กรุณาเลือกร้านที่จะซื้อ');
    }

    $result['reason'] = trim((string)($post['quote_selection_reason'] ?? ''));
    $chosen_total = $result['quotes'][$result['selected']]['totals']['grand_total'];
    if ($chosen_total > $min_total + 0.005 && $result['reason'] === '') {
        throw new DomainException('ใบเทียบราคา: ร้านที่เลือกไม่ใช่ร้านที่ราคาถูกที่สุด กรุณาระบุเหตุผล');
    }
    if ($chosen_total <= $min_total + 0.005) {
        $result['reason'] = '';
    }

    return $result;
}

/** ราคาของร้านที่เลือก ใช้แทน item_price[] */
function pr_quotes_selected_prices(array $parsed): ?array
{
    if (!$parsed['enabled'] || $parsed['selected'] === null) {
        return null;
    }
    return $parsed['quotes'][$parsed['selected']]['prices'];
}

function pr_quotes_selected_store_id(array $parsed): ?int
{
    if (!$parsed['enabled'] || $parsed['selected'] === null) {
        return null;
    }
    return $parsed['quotes'][$parsed['selected']]['store_id'];
}

function pr_quote_upload_attachment(int $slot): ?string
{
    $f = $_FILES['quote_attachment'] ?? null;
    if (!$f || !isset($f['error'][$slot]) || $f['error'][$slot] !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo((string)$f['name'][$slot], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'xls', 'xlsx', 'doc', 'docx'], true)) {
        throw new DomainException('ใบเทียบราคา: ไฟล์ใบเสนอราคาต้องเป็น PDF, รูปภาพ, Excel หรือ Word');
    }
    if (!is_dir(PR_QUOTE_UPLOAD_DIR)) {
        mkdir(PR_QUOTE_UPLOAD_DIR, 0777, true);
    }
    $name = 'QT_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'][$slot], PR_QUOTE_UPLOAD_DIR . $name)) {
        return null;
    }
    return $name;
}

/**
 * บันทึกใบเทียบราคา (ลบของเดิมแล้วใส่ใหม่) — เรียกภายใน transaction ของ PR
 * คืนรายชื่อไฟล์แนบเดิมที่ไม่ถูกใช้แล้ว ให้ผู้เรียกลบด้วย pr_quotes_cleanup_files() หลัง commit
 */
function pr_quotes_save(mysqli $conn, int $pr_id, array $parsed): array
{
    $old_files = [];
    $res = $conn->query("SELECT attachment FROM pr_quotes WHERE pr_id = $pr_id AND attachment IS NOT NULL AND attachment <> ''");
    while ($row = $res->fetch_assoc()) {
        $old_files[$row['attachment']] = true;
    }

    $conn->query("DELETE qi FROM pr_quote_items qi INNER JOIN pr_quotes q ON qi.quote_id = q.id WHERE q.pr_id = $pr_id");
    $conn->query("DELETE FROM pr_quotes WHERE pr_id = $pr_id");
    $conn->query("DELETE FROM pr_quote_lines WHERE pr_id = $pr_id");

    $reason = $parsed['enabled'] && $parsed['reason'] !== '' ? $parsed['reason'] : null;
    $stmt = $conn->prepare("UPDATE pr SET quote_selection_reason = ? WHERE id = ?");
    $stmt->bind_param('si', $reason, $pr_id);
    $stmt->execute();

    $keep_files = [];
    if ($parsed['enabled']) {
        $stmt_ref = $conn->prepare("INSERT INTO pr_quote_lines (pr_id, line_no, ref_price) VALUES (?, ?, ?)");
        $line_no = 0;
        foreach ($parsed['ref_prices'] ?? [] as $ref) {
            if ($ref !== null) {
                $stmt_ref->bind_param('iid', $pr_id, $line_no, $ref);
                $stmt_ref->execute();
            }
            $line_no++;
        }

        $stmt_q = $conn->prepare("INSERT INTO pr_quotes (pr_id, store_id, quote_no, delivery_days, payment_term, service_note, attachment, subtotal, vat, grand_total, is_selected, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_i = $conn->prepare("INSERT INTO pr_quote_items (quote_id, line_no, unit_price, line_total) VALUES (?, ?, ?, ?)");
        foreach ($parsed['quotes'] as $i => $q) {
            $attachment = pr_quote_upload_attachment($q['slot']);
            if ($attachment === null && $q['existing_attachment'] !== '' && isset($old_files[$q['existing_attachment']])) {
                $attachment = $q['existing_attachment'];
            }
            if ($attachment !== null) {
                $keep_files[$attachment] = true;
            }
            $is_selected = ($i === $parsed['selected']) ? 1 : 0;
            $stmt_q->bind_param(
                'iisisssdddii',
                $pr_id, $q['store_id'], $q['quote_no'], $q['delivery_days'], $q['payment_term'], $q['service_note'], $attachment,
                $q['totals']['subtotal'], $q['totals']['vat'], $q['totals']['grand_total'], $is_selected, $i
            );
            $stmt_q->execute();
            $quote_id = $conn->insert_id;

            $line_no = 0;
            foreach ($q['prices'] as $key => $price) {
                $line_total = $q['totals']['lines'][$key];
                $stmt_i->bind_param('iidd', $quote_id, $line_no, $price, $line_total);
                $stmt_i->execute();
                $line_no++;
            }
        }
    }

    return array_values(array_diff(array_keys($old_files), array_keys($keep_files)));
}

/** ลบไฟล์ใบเสนอราคาที่ไม่ใช้แล้ว — เรียกหลัง commit เท่านั้น */
function pr_quotes_cleanup_files(array $files): void
{
    foreach ($files as $file) {
        @unlink(PR_QUOTE_UPLOAD_DIR . basename($file));
    }
}

/** โหลดใบเทียบราคาของ PR พร้อมชื่อร้านและราคารายบรรทัด (เรียงตาม sort_order) */
function pr_quotes_load(mysqli $conn, int $pr_id): array
{
    $quotes = [];
    $stmt = $conn->prepare("SELECT q.*, s.store_name FROM pr_quotes q LEFT JOIN stores s ON q.store_id = s.id WHERE q.pr_id = ? ORDER BY q.sort_order ASC, q.id ASC");
    $stmt->bind_param('i', $pr_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['prices'] = [];
        $quotes[(int)$row['id']] = $row;
    }
    if ($quotes) {
        $ids = implode(',', array_keys($quotes));
        $res = $conn->query("SELECT quote_id, line_no, unit_price, line_total FROM pr_quote_items WHERE quote_id IN ($ids) ORDER BY line_no ASC");
        while ($row = $res->fetch_assoc()) {
            $quotes[(int)$row['quote_id']]['prices'][(int)$row['line_no']] = [
                'unit_price' => (float)$row['unit_price'],
                'line_total' => (float)$row['line_total'],
            ];
        }
    }
    return array_values($quotes);
}

/** ราคากลางต่อหน่วย [line_no => ref_price] */
function pr_quotes_load_ref_prices(mysqli $conn, int $pr_id): array
{
    $refs = [];
    $res = $conn->query("SELECT line_no, ref_price FROM pr_quote_lines WHERE pr_id = $pr_id");
    while ($row = $res->fetch_assoc()) {
        $refs[(int)$row['line_no']] = $row['ref_price'] === null ? null : (float)$row['ref_price'];
    }
    return $refs;
}

/** แปลงผลลัพธ์ DomainException เป็น alert แล้วย้อนกลับ (รูปแบบเดียวกับ API PR เดิม) */
function pr_quotes_fail(string $message): void
{
    echo "<script>alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . "); window.history.back();</script>";
    exit;
}
