<?php
require_once __DIR__ . '/../pr_quotes_lib.php';

function prq_assert($cond, string $label): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $label);
    }
}

function prq_expect_error(callable $fn, string $needle, string $label): void
{
    try {
        $fn();
    } catch (DomainException $e) {
        prq_assert(strpos($e->getMessage(), $needle) !== false, $label . ' (got: ' . $e->getMessage() . ')');
        return;
    }
    throw new RuntimeException('FAIL: ' . $label . ' (no error)');
}

$keys = [0, 1];
$qtys = [2, 1];
$discounts = [0, 100];
$base = [
    'quote_enabled' => '1',
    'quotes' => [
        ['store_id' => '5', 'quote_no' => 'Q-A', 'delivery_days' => '3', 'payment_term' => 'เครดิต 30 วัน'],
        ['store_id' => '7', 'quote_no' => 'Q-B', 'delivery_days' => '', 'payment_term' => ''],
        ['store_id' => '', 'quote_no' => ''],
    ],
    'quote_price' => [
        ['1000', '6000'],   // 2*1000 + 6000 - 100 = 7900
        ['1100', '5000'],   // 2*1100 + 5000 - 100 = 7100 (ถูกสุด)
        ['', ''],
    ],
    'quote_selected' => '1',
];

// ปิดใช้ = ไม่ต้องตรวจอะไร
$off = pr_quotes_parse([], $keys, $qtys, $discounts, 7, 0);
prq_assert($off['enabled'] === false && pr_quotes_selected_prices($off) === null, 'disabled comparison');

// เลือกร้านที่ถูกสุด ไม่ต้องมีเหตุผล; คอลัมน์ว่างถูกข้าม
$ok = pr_quotes_parse($base, $keys, $qtys, $discounts, 7, 0);
prq_assert(count($ok['quotes']) === 2, 'empty slot ignored');
prq_assert($ok['quotes'][1]['totals']['subtotal'] == 7100.0, 'subtotal uses qty*price - discount');
prq_assert($ok['quotes'][1]['totals']['grand_total'] == 7597.0, 'grand total includes VAT');
prq_assert(pr_quotes_selected_store_id($ok) === 7, 'selected store id');
prq_assert(pr_quotes_selected_prices($ok) === [0 => 1100.0, 1 => 5000.0], 'selected prices');
prq_assert($ok['quotes'][1]['delivery_days'] === null, 'blank delivery days -> null');

// เลือกร้านที่แพงกว่า ต้องมีเหตุผล
$pricier = $base;
$pricier['quote_selected'] = '0';
prq_expect_error(fn() => pr_quotes_parse($pricier, $keys, $qtys, $discounts, 7, 0), 'ระบุเหตุผล', 'reason required');
$pricier['quote_selection_reason'] = 'ส่งของเร็วกว่า';
$withReason = pr_quotes_parse($pricier, $keys, $qtys, $discounts, 7, 0);
prq_assert($withReason['reason'] === 'ส่งของเร็วกว่า' && pr_quotes_selected_store_id($withReason) === 5, 'reason accepted');

// ร้านเดียว / ร้านซ้ำ / ราคาไม่ครบ / ไม่ได้เลือกร้าน
$one = $base;
$one['quotes'][1]['store_id'] = '';
prq_expect_error(fn() => pr_quotes_parse($one, $keys, $qtys, $discounts, 7, 0), 'อย่างน้อย', 'min stores');
$dup = $base;
$dup['quotes'][1]['store_id'] = '5';
prq_expect_error(fn() => pr_quotes_parse($dup, $keys, $qtys, $discounts, 7, 0), 'ซ้ำ', 'duplicate store');
$missing = $base;
$missing['quote_price'][0][1] = '';
prq_expect_error(fn() => pr_quotes_parse($missing, $keys, $qtys, $discounts, 7, 0), 'ให้ครบทุกรายการ', 'missing price');
$noSel = $base;
unset($noSel['quote_selected']);
prq_expect_error(fn() => pr_quotes_parse($noSel, $keys, $qtys, $discounts, 7, 0), 'เลือกร้าน', 'selection required');

// ร้านที่ 4, บริการ/รับประกัน, ราคากลาง
$four = $base;
$four['quotes'][3] = ['store_id' => '9', 'service_note' => 'รับประกัน 1 ปี'];
$four['quote_price'][3] = ['1050', '5100'];
$four['quote_ref_price'] = ['990', ''];
$fourParsed = pr_quotes_parse($four, $keys, $qtys, $discounts, 7, 0);
prq_assert(count($fourParsed['quotes']) === 3 && $fourParsed['quotes'][2]['service_note'] === 'รับประกัน 1 ปี', '4th slot + service note');
prq_assert($fourParsed['ref_prices'] === [0 => 990.0, 1 => null], 'reference prices optional');
$five = $four;
$five['quotes'][4] = ['store_id' => '10'];
$five['quote_price'][4] = ['1', '1'];
prq_assert(count(pr_quotes_parse($five, $keys, $qtys, $discounts, 7, 0)['quotes']) === 3, 'slot beyond max ignored');

// วิธีการเสนอราคาตามยอด
prq_assert(pr_quote_method(100000) === 'ตกลงราคา', 'method <= 100k');
prq_assert(pr_quote_method(100000.01) === 'สอบราคา', 'method > 100k');
prq_assert(pr_quote_method(2000000.01) === 'ประกวดราคา', 'method > 2M');

// เกณฑ์บังคับ: เกิน 10,000 เท่านั้น
prq_assert(!pr_quote_is_required(10000.00), '10,000 exactly is optional');
prq_assert(pr_quote_is_required(10000.01), 'above 10,000 is required');

// API ต้องใช้ lib นี้
foreach (['api/save_pr_new.php', 'api/update_pr_new.php'] as $file) {
    $src = file_get_contents(__DIR__ . '/../' . $file);
    foreach (['pr_quotes_parse(', 'pr_quote_is_required(', 'pr_quotes_save('] as $needle) {
        prq_assert(strpos($src, $needle) !== false, "$file uses $needle");
    }
}

// DB round-trip (rollback เสมอ) — ข้ามถ้าต่อ DB ไม่ได้
$conn = @mysqli_connect('localhost', 'root', '', 'procurement_system');
if ($conn) {
    mysqli_set_charset($conn, 'utf8mb4');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();
    try {
        $conn->query("INSERT INTO pr (doc_no, status) VALUES ('TEST-PRQ', 'pending')");
        $pr_id = (int)$conn->insert_id;
        pr_quotes_save($conn, $pr_id, $withReason);
        $loaded = pr_quotes_load($conn, $pr_id);
        prq_assert(count($loaded) === 2, 'db: 2 quotes saved');
        prq_assert((int)$loaded[0]['is_selected'] === 1 && (int)$loaded[0]['store_id'] === 5, 'db: selected flag');
        prq_assert(abs($loaded[1]['prices'][1]['unit_price'] - 5000) < 0.001, 'db: line prices');
        $reason = $conn->query("SELECT quote_selection_reason FROM pr WHERE id = $pr_id")->fetch_row()[0];
        prq_assert($reason === 'ส่งของเร็วกว่า', 'db: reason stored');

        pr_quotes_save($conn, $pr_id, $fourParsed);
        $loaded4 = pr_quotes_load($conn, $pr_id);
        prq_assert(count($loaded4) === 3 && $loaded4[2]['service_note'] === 'รับประกัน 1 ปี', 'db: service note');
        prq_assert(pr_quotes_load_ref_prices($conn, $pr_id) === [0 => 990.0], 'db: reference prices');

        // บันทึกซ้ำแบบปิดใช้ = ลบใบเทียบราคาออก
        pr_quotes_save($conn, $pr_id, $off);
        prq_assert(pr_quotes_load($conn, $pr_id) === [], 'db: disabled clears quotes');
        prq_assert(pr_quotes_load_ref_prices($conn, $pr_id) === [], 'db: disabled clears reference prices');
        echo "PR quotes DB round-trip: PASS\n";
    } finally {
        $conn->rollback();
    }
} else {
    echo "PR quotes DB round-trip: SKIPPED (no database)\n";
}

echo "PR quotes: PASS\n";
