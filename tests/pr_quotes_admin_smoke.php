<?php
// ทดสอบการแก้ใบเทียบราคาโดย admin — ข้อมูลใน DB ถูก rollback เสมอ
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../pr_quotes_admin_lib.php';

function prqa_assert($cond, string $label): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $label);
    }
}

function prqa_expect_error(callable $fn, string $needle, string $label): void
{
    try {
        $fn();
    } catch (DomainException $e) {
        prqa_assert(strpos($e->getMessage(), $needle) !== false, $label . ' (got: ' . $e->getMessage() . ')');
        return;
    }
    throw new RuntimeException('FAIL: ' . $label . ' (no error)');
}

// สิทธิ์
$pending = ['status' => 'pending', 'deleted_at' => null];
prqa_assert(pr_quotes_admin_block_reason('admin', $pending, false) === null, 'admin can edit pending PR');
prqa_assert(pr_quotes_admin_block_reason('admin', ['status' => 'rejected'], false) === null, 'admin can edit rejected PR');
foreach (['procure', 'gmhok', 'gmacc', 'mgr', 'mgr2', 'viewer', ''] as $role) {
    prqa_assert(pr_quotes_admin_block_reason($role, $pending, false) !== null, "$role cannot edit");
}
prqa_assert(pr_quotes_admin_block_reason('admin', ['status' => 'approved'], false) !== null, 'approved PR locked');
prqa_assert(pr_quotes_admin_block_reason('admin', $pending, true) !== null, 'PR with PO locked');
prqa_assert(pr_quotes_admin_block_reason('admin', ['status' => 'pending', 'deleted_at' => '2026-01-01'], false) !== null, 'deleted PR locked');
prqa_assert(pr_quotes_admin_block_reason('admin', null, false) !== null, 'missing PR locked');

// ประเภทวงเงิน
prqa_assert(pr_quotes_admin_limit_type(10000) === 'low', 'limit low');
prqa_assert(pr_quotes_admin_limit_type(10000.01) === 'mid', 'limit mid');
prqa_assert(pr_quotes_admin_limit_type(99999.01) === 'high', 'limit high');

$conn->begin_transaction();
try {
    $conn->query("INSERT INTO pr (doc_no, status, store_id, subtotal, vat, vat_percent, wht_percent, wht_amount, grand_total, budget_limit_type, created_by)
        VALUES ('TEST-PRQA', 'pending', NULL, 1000, 70, 7, 3, 30, 1040, 'low', 1)");
    $pr_id = (int)$conn->insert_id;
    $stores = array_column($conn->query("SELECT id FROM stores ORDER BY id LIMIT 2")->fetch_all(MYSQLI_ASSOC), 'id');
    prqa_assert(count($stores) === 2, 'needs two stores in the database');

    $stmt = $conn->prepare("INSERT INTO pr_items (pr_id, item_desc, item_qty, item_unit, item_price, item_discount, item_total) VALUES (?, ?, ?, 'ชิ้น', ?, ?, ?)");
    foreach ([['ก', 2.0, 400.0, 0.0, 800.0], ['ข', 1.0, 300.0, 100.0, 200.0]] as [$desc, $qty, $price, $discount, $total]) {
        $stmt->bind_param('isdddd', $pr_id, $desc, $qty, $price, $discount, $total);
        $stmt->execute();
    }

    $post = [
        'item_desc' => ['แก้ชื่อ'], 'item_qty' => [999], 'item_discount' => [999], 'vat_percent' => '0', // ต้องถูกเมิน
        'quotes' => [['store_id' => $stores[0]], ['store_id' => $stores[1]]],
        'quote_price' => [['5000', '10000'], ['6000', '12000']],
        'quote_selected' => '0',
    ];

    prqa_expect_error(fn() => pr_quotes_admin_apply($conn, 'procure', $pr_id, $post), 'admin', 'non-admin rejected by apply');
    prqa_expect_error(fn() => pr_quotes_admin_apply($conn, 'admin', $pr_id, ['quotes' => []]), 'อย่างน้อย', 'quote_enabled is forced on');

    $result = pr_quotes_admin_apply($conn, 'admin', $pr_id, $post);
    $pr = $conn->query("SELECT * FROM pr WHERE id = $pr_id")->fetch_assoc();
    $rows = $conn->query("SELECT item_desc, item_qty, item_price, item_discount, item_total FROM pr_items WHERE pr_id = $pr_id ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    // 2*5000 + 1*10000 - 100 = 19900, VAT 1393, WHT 597 => 20696
    prqa_assert($rows[0]['item_desc'] === 'ก' && (float)$rows[0]['item_qty'] === 2.0, 'item desc/qty untouched');
    prqa_assert((float)$rows[1]['item_discount'] === 100.0, 'discount untouched');
    prqa_assert((float)$rows[0]['item_price'] === 5000.0 && (float)$rows[1]['item_total'] === 9900.0, 'item prices follow selected store');
    prqa_assert((int)$pr['store_id'] === (int)$stores[0], 'store follows selection');
    prqa_assert((float)$pr['subtotal'] === 19900.0 && (float)$pr['vat'] === 1393.0 && (float)$pr['wht_amount'] === 597.0, 'PR totals recalculated');
    prqa_assert((float)$pr['grand_total'] === 20696.0 && (float)$pr['vat_percent'] === 7.0, 'grand total uses stored VAT');
    prqa_assert($pr['budget_limit_type'] === 'mid', 'limit type follows new total');
    prqa_assert($result['new_total'] === 20696.0 && $result['store_changed'] === true, 'result summary');
    prqa_assert(count(pr_quotes_load($conn, $pr_id)) === 2, 'quotes saved');

    // มีงวดผ่อน: ยอดเปลี่ยนไม่ได้ แต่แก้ข้อมูลอื่นที่ยอดเท่าเดิมได้
    $conn->query("UPDATE pr SET installment_period = 3 WHERE id = $pr_id");
    $changed = $post;
    $changed['quote_price'][0] = ['4000', '10000'];
    prqa_expect_error(fn() => pr_quotes_admin_apply($conn, 'admin', $pr_id, $changed), 'ผ่อนชำระ', 'installment total locked');
    $sameTotal = $post;
    $sameTotal['quotes'][1]['quote_no'] = 'Q-2';
    pr_quotes_admin_apply($conn, 'admin', $pr_id, $sameTotal);
    prqa_assert(pr_quotes_load($conn, $pr_id)[1]['quote_no'] === 'Q-2', 'same total allowed with installments');
    $conn->query("UPDATE pr SET installment_period = 0 WHERE id = $pr_id");

    // อนุมัติครบแล้ว = ล็อก
    $conn->query("UPDATE pr SET status = 'approved' WHERE id = $pr_id");
    prqa_expect_error(fn() => pr_quotes_admin_apply($conn, 'admin', $pr_id, $post), 'แก้ไขใบเทียบราคาไม่ได้', 'approved PR locked by apply');
} finally {
    $conn->rollback();
}

echo "PR quotes admin: PASS\n";
