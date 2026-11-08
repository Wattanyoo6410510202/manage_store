<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once 'config.php';
include 'header.php';
require_once 'pr_quotes_admin_lib.php';

$pr_id = (int)($_GET['id'] ?? 0);
$actor_role = (string)$approval_actor_context['role'];

$stmt = $conn->prepare("SELECT p.*, s.company_name AS supplier_name, st.store_name, u.name AS creator_name
    FROM pr p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN stores st ON p.store_id = st.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = ?");
$stmt->bind_param('i', $pr_id);
$stmt->execute();
$pr = $stmt->get_result()->fetch_assoc() ?: null;

$block = pr_quotes_admin_block_reason($actor_role, $pr);
if ($block !== null) {
    echo "<script>alert(" . json_encode($block, JSON_UNESCAPED_UNICODE) . "); window.location.href='pending_approval.php?type=pr';</script>";
    exit;
}

$stmt = $conn->prepare("SELECT item_desc, item_qty, item_unit, item_price, item_discount, item_total FROM pr_items WHERE pr_id = ? ORDER BY id ASC");
$stmt->bind_param('i', $pr_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pr_quote_stores = mysqli_query($conn, "SELECT id, store_name FROM stores ORDER BY store_name ASC")->fetch_all(MYSQLI_ASSOC);
$pr_quote_existing = pr_quotes_load($conn, $pr_id);
$pr_quote_ref_prices = pr_quotes_load_ref_prices($conn, $pr_id);
$pr_quote_reason = (string)($pr['quote_selection_reason'] ?? '');
$pr_quote_force_enabled = true;

$has_approvals = !empty($pr['approved_by_0']) || !empty($pr['approved_by']) || !empty($pr['approved_by_1'])
    || !empty($pr['approved_by_2']) || !empty($pr['approved_by_3']);
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$money = fn($v) => number_format((float)$v, 2);
?>

<form action="api/update_pr_quotes.php" method="POST" enctype="multipart/form-data" class="space-y-4">
    <input type="hidden" name="pr_id" value="<?= $pr_id ?>">
    <input type="hidden" name="csrf" value="<?= $h(pr_quotes_admin_csrf_token()) ?>">
    <select name="vat_percent" disabled hidden><option value="<?= (float)$pr['vat_percent'] ?>" selected></option></select>
    <select name="wht_percent" disabled hidden><option value="<?= (float)$pr['wht_percent'] ?>" selected></option></select>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="pending_approval.php?type=pr" class="text-xs font-bold text-slate-500 hover:text-slate-800"><i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>กลับรายการรออนุมัติ</a>
            <h1 class="mt-1 text-lg font-black text-slate-900">แก้ไขใบเทียบราคา · <?= $h($pr['doc_no']) ?></h1>
        </div>
        <a href="view_pr_new.php?id=<?= $pr_id ?>" class="text-xs font-bold text-indigo-600 hover:underline">ดูใบขอซื้อ</a>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        <p class="font-bold"><i class="fas fa-circle-info mr-1" aria-hidden="true"></i>แก้ได้เฉพาะใบเทียบราคา ราคาและร้านค้าของใบขอซื้อจะเปลี่ยนตามร้านที่เลือก</p>
        <?php if ($pr['status'] === 'approved'): ?>
            <p class="mt-1">ใบนี้อนุมัติครบแล้ว การอนุมัติเดิมจะยังอยู่ และถ้าราคาเปลี่ยน ราคาในใบสั่งซื้อ (PO) ของใบนี้จะเปลี่ยนตามทันที</p>
        <?php elseif ($has_approvals): ?>
            <p class="mt-1">ใบนี้ผ่านการอนุมัติบางขั้นแล้ว การอนุมัติเดิมจะยังอยู่ แม้ยอดหรือร้านจะเปลี่ยน</p>
        <?php endif; ?>
    </div>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white">
        <div class="grid grid-cols-1 gap-3 border-b border-slate-100 bg-slate-50/30 p-5 text-sm sm:grid-cols-3">
            <div><span class="block text-[11px] font-bold text-slate-500">หน่วยงาน/บริษัท</span><span class="font-bold text-slate-800"><?= $h(supplier_display_name($pr['supplier_name'] ?: '-')) ?></span></div>
            <div><span class="block text-[11px] font-bold text-slate-500">ผู้สร้าง</span><span class="font-bold text-slate-800"><?= $h($pr['creator_name'] ?: '-') ?></span></div>
            <div><span class="block text-[11px] font-bold text-slate-500">ร้านค้าปัจจุบัน</span><span class="font-bold text-slate-800"><?= $h($pr['store_name'] ?: '-') ?></span></div>
        </div>
        <div class="overflow-x-auto">
            <table id="itemsTable" class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-700">
                    <tr>
                        <th class="px-4 py-2 w-10 text-center">#</th>
                        <th class="px-4 py-2">รายการ</th>
                        <th class="px-4 py-2 text-center">จำนวน</th>
                        <th class="px-4 py-2 text-right">ส่วนลด</th>
                        <th class="px-4 py-2 text-right">ราคาต่อหน่วย</th>
                        <th class="px-4 py-2 text-right">รวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $item): ?>
                        <tr class="item-row border-t border-slate-100">
                            <td class="px-4 py-2 text-center text-slate-500"><?= $i + 1 ?></td>
                            <td class="px-4 py-2 font-bold text-slate-700">
                                <?= nl2br($h($item['item_desc'])) ?>
                                <input type="hidden" name="item_desc[]" value="<?= $h($item['item_desc']) ?>">
                            </td>
                            <td class="px-4 py-2 text-center whitespace-nowrap">
                                <?= rtrim(rtrim(number_format((float)$item['item_qty'], 2), '0'), '.') ?> <?= $h($item['item_unit']) ?>
                                <input type="hidden" name="item_qty[]" value="<?= (float)$item['item_qty'] ?>">
                                <input type="hidden" name="item_unit[]" value="<?= $h($item['item_unit']) ?>">
                            </td>
                            <td class="px-4 py-2 text-right font-mono">
                                <?= $money($item['item_discount']) ?>
                                <input type="hidden" name="item_discount[]" value="<?= (float)$item['item_discount'] ?>">
                            </td>
                            <td class="px-4 py-2 text-right">
                                <input type="number" name="item_price[]" value="<?= number_format((float)$item['item_price'], 2, '.', '') ?>" readonly
                                    class="w-28 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-right font-mono text-sm" aria-label="ราคาต่อหน่วยของรายการที่ <?= $i + 1 ?>">
                            </td>
                            <?php // pr_quotes.js เก็บ hidden input ราคาไว้ใน td สุดท้าย จึงต้องแยกยอดไว้ใน span ?>
                            <td class="px-4 py-2 text-right font-mono font-bold"><span class="item-line-total"><?= $money($item['item_total']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="ml-auto max-w-sm space-y-1 border-t border-slate-100 p-5 text-sm font-bold text-slate-800">
            <div class="flex justify-between"><span>รวม (หลังส่วนลด)</span><span id="subtotal_display"><?= $money($pr['subtotal']) ?></span></div>
            <div class="flex justify-between"><span>ภาษี (<?= (float)$pr['vat_percent'] ?>%)</span><span id="vat_display"><?= $money($pr['vat']) ?></span></div>
            <div class="flex justify-between"><span>หัก ณ ที่จ่าย (<?= (float)$pr['wht_percent'] ?>%)</span><span>- <span id="wht_display"><?= $money($pr['wht_amount']) ?></span></span></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-lg font-black text-indigo-600"><span>ยอดสุทธิ</span><span id="grandtotal_display"><?= $money($pr['grand_total']) ?></span></div>
        </div>
    </section>

    <?php include 'pr_quotes_panel.php'; ?>

    <div class="flex justify-end gap-2 pb-6">
        <a href="pending_approval.php?type=pr" class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-700 hover:bg-slate-50">ยกเลิก</a>
        <button type="submit" class="inline-flex min-h-11 items-center rounded-xl bg-indigo-600 px-5 text-sm font-black text-white hover:bg-indigo-700">บันทึกใบเทียบราคา</button>
    </div>
</form>

<script>
    // อัปเดตยอดที่แสดงตามร้านที่เลือก (ฝั่ง server คำนวณใหม่จาก DB เสมอ)
    function calculateTotal() {
        const money = (n) => n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        let subtotal = 0;
        document.querySelectorAll('#itemsTable tr.item-row').forEach((row) => {
            const value = (name) => parseFloat(row.querySelector(`[name="${name}[]"]`).value) || 0;
            const line = value('item_qty') * value('item_price') - value('item_discount');
            row.querySelector('.item-line-total').textContent = money(line);
            subtotal += line;
        });
        const vat = subtotal * <?= (float)$pr['vat_percent'] ?> / 100;
        const wht = subtotal * <?= (float)$pr['wht_percent'] ?> / 100;
        document.getElementById('subtotal_display').textContent = money(subtotal);
        document.getElementById('vat_display').textContent = money(vat);
        document.getElementById('wht_display').textContent = money(wht);
        document.getElementById('grandtotal_display').textContent = money(subtotal + vat - wht);
    }
</script>
<?php include 'footer.php'; ?>
