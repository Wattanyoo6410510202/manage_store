<?php
/**
 * แบบฟอร์มเปรียบเทียบราคาสินค้า/บริการ (A4 แนวนอน) ตามแบบฟอร์ม FO-PC-004
 *
 * ใช้ใน view_pr_new.php / view_pr.php (แสดงบนจอเป็นแผ่นที่ 2) และ print_pr_quotes.php (หน้าพิมพ์)
 * ต้องมี $conn และ $data (แถวของ pr พร้อม my_company, creator_name ฯลฯ) ก่อน include
 * $prq_standalone = true เมื่อใช้ในหน้าพิมพ์
 */
require_once __DIR__ . '/pr_quotes_lib.php';
require_once __DIR__ . '/supplier_display.php';

$prq_quotes = pr_quotes_load($conn, (int)$data['id']);
if (!$prq_quotes) {
    return;
}
$prq_standalone = $prq_standalone ?? false;
$prq_refs = pr_quotes_load_ref_prices($conn, (int)$data['id']);

$prq_items = [];
$prq_stmt = $conn->prepare("SELECT item_desc, item_qty, item_unit FROM pr_items WHERE pr_id = ? ORDER BY id ASC");
$prq_pr_id = (int)$data['id'];
$prq_stmt->bind_param('i', $prq_pr_id);
$prq_stmt->execute();
$prq_res = $prq_stmt->get_result();
while ($row = $prq_res->fetch_assoc()) {
    $prq_items[] = $row;
}

$prq_min_total = min(array_map(fn($q) => (float)$q['grand_total'], $prq_quotes));
$prq_selected = null;
foreach ($prq_quotes as $q) {
    if ((int)$q['is_selected'] === 1) {
        $prq_selected = $q;
    }
}
$prq_not_cheapest = $prq_selected && (float)$prq_selected['grand_total'] > $prq_min_total + 0.005;
$prq_approved = ($data['status'] ?? '') === 'approved';
$prq_method = pr_quote_method((float)($data['grand_total'] ?? 0));
$prq_cols = array_pad($prq_quotes, PR_QUOTE_MAX_STORES, null); // แสดงครบ 4 ร้านตามแบบฟอร์ม
$prq_rows = max(5, count($prq_items));
$prq_company = !empty($data['my_company']) ? supplier_display_name($data['my_company']) : '';
$prq_logo = $logo_path ?? (!empty($data['logo_path']) ? 'uploads/' . $data['logo_path'] : '');
$prq_budget = (float)($data['budget_amount'] ?? 0);
$prq_h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$prq_money = fn($v) => number_format((float)$v, 2);
$prq_cb = fn(bool $on) => '<span class="prq-cb' . ($on ? ' on' : '') . '"></span>';
?>
<style>
    .prq-paper { background: #fff; width: 297mm; min-height: 210mm; margin: 24px auto; padding: 8mm 10mm; box-sizing: border-box;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / .1); color: #000; font-family: 'Sarabun', 'TH Sarabun New', sans-serif; font-size: 10.5px; }
    .prq-paper .prq-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .prq-paper .prq-brand { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 700; }
    .prq-paper .prq-brand img { width: 46px; height: 46px; object-fit: contain; }
    .prq-paper .prq-brand .box { width: 40px; height: 40px; background: #0f172a; color: #fff; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
    .prq-paper .prq-code { text-align: right; font-size: 10px; line-height: 1.6; }
    .prq-paper .prq-internal { display: inline-block; border: 1.5px solid #000; padding: 2px 10px; margin-top: 4px; font-size: 10px; }
    .prq-paper h2 { text-align: center; font-size: 15px; font-weight: 800; margin: 4px 0 10px; }
    .prq-paper .prq-method { margin-bottom: 4px; font-size: 11px; }
    .prq-paper .prq-method span.opt { margin-left: 14px; }
    .prq-paper .prq-cb { display: inline-block; width: 10px; height: 10px; border: 1.5px solid #000; vertical-align: -1px; margin-right: 3px; position: relative; }
    .prq-paper .prq-cb.on::after { content: ''; position: absolute; left: 2px; top: -2px; width: 4px; height: 8px; border: solid #000; border-width: 0 2px 2px 0; transform: rotate(45deg); }
    .prq-paper table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .prq-paper th, .prq-paper td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; word-wrap: break-word; }
    .prq-paper th { font-weight: 700; text-align: center; background: #fff; }
    .prq-paper td.num { text-align: right; white-space: nowrap; }
    .prq-paper td.c { text-align: center; }
    .prq-paper td.lbl { text-align: left; }
    .prq-paper .chosen { background: #eef2ff; }
    .prq-paper .best { font-weight: 800; }
    .prq-paper tr.items td { height: 22px; }
    .prq-paper .prq-note { margin-top: 6px; font-size: 10px; line-height: 1.7; }
    .prq-paper .prq-note u { font-weight: 700; }
    .prq-paper .prq-reason { margin-top: 6px; padding: 4px 8px; border: 1px solid #000; font-size: 10px; }
    .prq-paper .prq-signs { display: flex; justify-content: space-between; margin-top: 22px; font-size: 10.5px; text-align: center; }
    .prq-paper .prq-signs > div { width: 32%; line-height: 1.9; }
    @media print {
        .prq-paper { box-shadow: none; margin: 0; width: auto; min-height: 0; padding: 0; }
    }
</style>

<div class="prq-paper <?= $prq_standalone ? '' : 'no-print' ?>" id="prq-sheet">
    <div class="prq-top">
        <div class="prq-brand">
            <?php if ($prq_logo): ?>
                <img src="<?= $prq_h($prq_logo) ?>" alt="" onerror="this.remove()">
            <?php elseif ($prq_company !== ''): ?>
                <div class="box"><?= $prq_h(mb_substr($prq_company, 0, 1, 'UTF-8')) ?></div>
            <?php endif; ?>
            <span><?= $prq_h($prq_company) ?></span>
        </div>
        <div class="prq-code">
            <?= $prq_h(PR_QUOTE_FORM_CODE) ?><br>
            <?= $prq_h(PR_QUOTE_FORM_REV) ?><br>
            <span class="prq-internal">เอกสารใช้ภายใน (INTERNAL USE ONLY)</span>
        </div>
    </div>

    <h2>แบบฟอร์มเปรียบเทียบราคาสินค้า/บริการ</h2>

    <div class="prq-method">
        1) วิธีการเสนอราคา
        <?php foreach (['ตกลงราคา', 'สอบราคา', 'ประกวดราคา'] as $m): ?>
            <span class="opt"><?= $prq_cb($prq_method === $m) ?><?= $m ?></span>
        <?php endforeach; ?>
    </div>

    <table>
        <colgroup>
            <col style="width: 3.2%"><col style="width: 6.5%"><col style="width: 6.5%"><col style="width: 15%">
            <col style="width: 5.5%"><col style="width: 6.5%"><col style="width: 6%">
            <?php for ($i = 0; $i < PR_QUOTE_MAX_STORES * 2; $i++): ?><col><?php endfor; ?>
        </colgroup>
        <thead>
            <tr>
                <th rowspan="3">ลำดับ</th>
                <th rowspan="3">เลขที่<br>ใบขอซื้อ</th>
                <th rowspan="3">หน่วยงาน<br>ขอซื้อ</th>
                <th rowspan="3">รายการสินค้า/บริการ</th>
                <th rowspan="3">จำนวน<br>(หน่วย)</th>
                <th rowspan="3">งบประมาณ<br>ที่กำหนด</th>
                <th rowspan="3">ราคากลาง<br>ต่อหน่วย</th>
                <th colspan="<?= PR_QUOTE_MAX_STORES * 2 ?>">บริษัท/ห้าง/ร้าน และราคาที่เสนอ (บาท/หน่วย ไม่รวม VAT 7%)</th>
            </tr>
            <tr>
                <?php foreach ($prq_cols as $i => $q): ?>
                    <th colspan="2" class="<?= $q && (int)$q['is_selected'] === 1 ? 'chosen' : '' ?>">
                        <?= $q ? $prq_h($q['store_name'] ?: ('ร้าน #' . $q['store_id'])) : 'ชื่อผู้ขายรายที่ ' . ($i + 1) ?>
                    </th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach ($prq_cols as $q): ?>
                    <th class="<?= $q && (int)$q['is_selected'] === 1 ? 'chosen' : '' ?>">ราคาต่อหน่วย</th>
                    <th class="<?= $q && (int)$q['is_selected'] === 1 ? 'chosen' : '' ?>">ราคารวม</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < $prq_rows; $i++): $item = $prq_items[$i] ?? null; ?>
                <?php
                $line_prices = $item ? array_filter(array_map(fn($q) => $q ? ($q['prices'][$i]['unit_price'] ?? null) : null, $prq_cols), fn($p) => $p !== null) : [];
                $line_min = count($line_prices) > 1 ? min($line_prices) : null;
                ?>
                <tr class="items">
                    <td class="c"><?= $item ? $i + 1 : '' ?></td>
                    <?php if ($i === 0): ?>
                        <td class="c" rowspan="<?= $prq_rows ?>"><?= $prq_h($data['doc_no']) ?></td>
                        <td class="c" rowspan="<?= $prq_rows ?>"><?= $prq_h($prq_company) ?></td>
                    <?php endif; ?>
                    <td class="lbl"><?= $item ? nl2br($prq_h($item['item_desc'])) : '' ?></td>
                    <td class="c"><?= $item ? rtrim(rtrim(number_format((float)$item['item_qty'], 2), '0'), '.') . ' ' . $prq_h($item['item_unit']) : '' ?></td>
                    <?php if ($i === 0): ?>
                        <td class="num" rowspan="<?= $prq_rows ?>" style="vertical-align: middle;"><?= $prq_budget > 0 ? $prq_money($prq_budget) : '-' ?></td>
                    <?php endif; ?>
                    <td class="num"><?= ($item && isset($prq_refs[$i])) ? $prq_money($prq_refs[$i]) : '' ?></td>
                    <?php foreach ($prq_cols as $q): ?>
                        <?php $p = ($item && $q) ? ($q['prices'][$i] ?? null) : null; $chosen = $q && (int)$q['is_selected'] === 1 ? 'chosen' : ''; ?>
                        <td class="num <?= $chosen ?> <?= ($p && $line_min !== null && abs($p['unit_price'] - $line_min) < 0.005) ? 'best' : '' ?>"><?= $p ? $prq_money($p['unit_price']) : '' ?></td>
                        <td class="num <?= $chosen ?>"><?= $p ? $prq_money($p['line_total']) : '' ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endfor; ?>

            <tr>
                <td colspan="7" class="lbl" style="text-align:right;"><b>รวมเป็นเงิน (ไม่รวม VAT)</b></td>
                <?php foreach ($prq_cols as $q): ?>
                    <td colspan="2" class="num <?= $q && (int)$q['is_selected'] === 1 ? 'chosen' : '' ?>"><?= $q ? $prq_money($q['subtotal']) : '' ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td colspan="7" class="lbl">2) การส่งมอบ</td>
                <?php foreach ($prq_cols as $q): ?>
                    <td colspan="2" class="c"><?= ($q && $q['delivery_days'] !== null) ? 'ภายใน ' . (int)$q['delivery_days'] . ' วัน' : '' ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td colspan="7" class="lbl">3) เงื่อนไขชำระเงิน</td>
                <?php foreach ($prq_cols as $q): ?><td colspan="2" class="c"><?= $q ? $prq_h($q['payment_term']) : '' ?></td><?php endforeach; ?>
            </tr>
            <tr>
                <td colspan="7" class="lbl">4) การบริการ/การรับประกัน/อื่นๆ (ถ้ามี)</td>
                <?php foreach ($prq_cols as $q): ?><td colspan="2" class="c"><?= $q ? $prq_h($q['service_note'] ?? '') : '' ?></td><?php endforeach; ?>
            </tr>
            <tr>
                <td colspan="7" class="lbl">5) ความคิดเห็นของแผนกจัดซื้อ ราคารวมภาษีมูลค่าเพิ่มแล้ว</td>
                <?php foreach ($prq_cols as $q): ?>
                    <td colspan="2" class="c <?= $q && abs((float)$q['grand_total'] - $prq_min_total) < 0.005 ? 'best' : '' ?>">
                        <?= $prq_cb($q !== null && (int)$q['is_selected'] === 1) ?>
                        <?= $q ? $prq_money($q['grand_total']) : '' ?>
                    </td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td colspan="7" class="lbl">6) พิจารณาอนุมัติ</td>
                <?php foreach ($prq_cols as $q): ?>
                    <td colspan="2" class="c"><?= $prq_cb($prq_approved && $q !== null && (int)$q['is_selected'] === 1) ?></td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>

    <?php if ($prq_not_cheapest): ?>
        <div class="prq-reason">
            <b>เหตุผลที่เลือก <?= $prq_h($prq_selected['store_name']) ?></b> (ไม่ใช่ราคาต่ำสุด แพงกว่า <?= $prq_money((float)$prq_selected['grand_total'] - $prq_min_total) ?> บาท):
            <?= $prq_h($data['quote_selection_reason'] ?? '-') ?>
        </div>
    <?php endif; ?>

    <div class="prq-note">
        <u>หมายเหตุ</u><br>
        1. วิธีตกลงราคา &nbsp;คือ การซื้อ หรือการจ้างครั้งหนึ่ง ซึ่งมีราคาไม่เกิน 100,000 บาท<br>
        2. วิธีสอบราคา &nbsp;คือ การซื้อ หรือการจ้างครั้งหนึ่ง ซึ่งมีราคาเกิน 100,000 บาท แต่ไม่เกิน 2,000,000 บาท<br>
        3. วิธีประกวดราคา คือ การซื้อ หรือการจ้างครั้งหนึ่ง ซึ่งมีราคาเกิน 2,000,000 บาท
    </div>

    <div class="prq-signs">
        <?php $prq_i = 0; foreach (PR_QUOTE_SIGNER_TITLES as $role => $title): ?>
            <div>
                ลงชื่อ........................................................ <?= $role ?><br>
                ( <?= $prq_i === 0 && !empty($data['creator_name']) ? $prq_h($data['creator_name']) : '....................................................' ?> )<br>
                <?= $title ?>
            </div>
        <?php $prq_i++; endforeach; ?>
    </div>
</div>
