<?php
/**
 * ส่วนใบเทียบราคาในฟอร์ม PR (request_buy.php / edit_pr_new.php)
 * ต้องวางไว้ภายใน <form> และหลังตาราง #itemsTable
 *
 * ตัวแปรที่ใช้:
 *   $pr_quote_stores   array ของร้าน [id, store_name]
 *   $pr_quote_existing array จาก pr_quotes_load() (ว่างได้)
 *   $pr_quote_reason   เหตุผลเดิม (string)
 */
require_once __DIR__ . '/pr_quotes_lib.php';

$pr_quote_existing = $pr_quote_existing ?? [];
$pr_quote_reason = $pr_quote_reason ?? '';
$pr_quote_config = [
    'requiredAbove' => PR_QUOTE_REQUIRED_ABOVE,
    'minStores' => PR_QUOTE_MIN_STORES,
    'maxStores' => PR_QUOTE_MAX_STORES,
    'stores' => array_map(fn($s) => ['id' => (int)$s['id'], 'name' => (string)$s['store_name']], $pr_quote_stores ?? []),
    'quotes' => array_map(fn($q) => [
        'store_id' => (int)$q['store_id'],
        'quote_no' => (string)($q['quote_no'] ?? ''),
        'delivery_days' => $q['delivery_days'] === null ? '' : (string)$q['delivery_days'],
        'payment_term' => (string)($q['payment_term'] ?? ''),
        'service_note' => (string)($q['service_note'] ?? ''),
        'attachment' => (string)($q['attachment'] ?? ''),
        'is_selected' => (int)$q['is_selected'] === 1,
        'prices' => array_map(fn($p) => $p['unit_price'], $q['prices']),
    ], $pr_quote_existing),
    'refPrices' => (object)($pr_quote_ref_prices ?? []),
];
$pr_quote_enabled = !empty($pr_quote_existing);
?>
<style>
    #prQuotePanel .prq-slot { border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px; background: #f8fafc; }
    #prQuotePanel .prq-slot.is-selected { border-color: #4f46e5; background: #eef2ff; }
    #prQuotePanel .prq-slot label { display: block; font-size: 11px !important; font-weight: 700; color: #334155; margin-bottom: 2px !important; }
    #prQuotePanel input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"]):not([type="file"]),
    #prQuotePanel select, #prQuotePanel textarea {
        width: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; outline: none; color: #0f172a;
    }
    #prQuotePanel textarea { padding: 8px 10px; font-size: 13px; }
    #prQuotePanel input:focus, #prQuotePanel select:focus, #prQuotePanel textarea:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, .12); }
    #prQuotePanel .prq-slot input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"]):not([type="file"]),
    #prQuotePanel .prq-slot select { min-height: 36px !important; padding: 6px 10px !important; font-size: 13px !important; }
    #prQuoteTable th, #prQuoteTable td { padding: 8px 10px; font-size: 13px; vertical-align: top; }
    #prQuoteTable thead th { background: #f8fafc; font-weight: 800; color: #1e293b; border-bottom: 1px solid #e2e8f0; }
    #prQuoteTable tbody tr { border-bottom: 1px solid #f1f5f9; }
    #prQuoteTable input.prq-price { width: 110px !important; min-height: 34px !important; padding: 4px 8px !important; text-align: right; font-family: ui-monospace, monospace; font-weight: 700; }
    #prQuoteTable td.prq-best { background: #ecfdf5; }
    #prQuoteTable td.prq-best input.prq-price { border-color: #10b981 !important; }
    #prQuoteTable .prq-col-selected { box-shadow: inset 0 0 0 9999px rgba(79, 70, 229, .05); }
    #prQuoteTable tfoot td { font-weight: 800; background: #f8fafc; }
    input.prq-locked { background: #eef2ff !important; color: #4338ca !important; cursor: not-allowed; }
</style>

<div id="prQuotePanel" class="bg-white rounded-3xl border border-slate-200 overflow-hidden mt-4"
    data-config='<?= htmlspecialchars(json_encode($pr_quote_config, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>
    <div class="p-5 border-b border-slate-100 flex flex-wrap justify-between items-center gap-3 bg-slate-50/30">
        <div>
            <span class="text-xs font-black text-slate-700 uppercase tracking-widest">
                <i class="fas fa-balance-scale mr-2 text-indigo-500"></i> ใบเทียบราคา
            </span>
            <p class="text-[12px] text-slate-500 mt-1">
                เทียบ <?= PR_QUOTE_MIN_STORES ?>–<?= PR_QUOTE_MAX_STORES ?> ร้าน ระบบจะใช้ราคาของร้านที่เลือกเป็นราคาในใบขอซื้อ
                <span id="prQuoteRequiredBadge" class="hidden ml-1 px-2 py-0.5 rounded-full bg-rose-50 text-rose-600 border border-rose-100 font-bold">
                    บังคับ: ยอดเกิน <?= number_format(PR_QUOTE_REQUIRED_ABOVE) ?> บาท
                </span>
            </p>
        </div>
        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" id="prQuoteToggle" name="quote_enabled" value="1" class="w-5 h-5 rounded text-indigo-600"
                <?= $pr_quote_enabled ? 'checked' : '' ?>>
            <span class="text-sm font-bold text-slate-700">เปิดใช้ใบเทียบราคา</span>
        </label>
    </div>

    <div id="prQuoteBody" class="p-5 space-y-4 <?= $pr_quote_enabled ? '' : 'hidden' ?>">
        <div id="prQuoteSlots" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
            <?php for ($slot = 0; $slot < PR_QUOTE_MAX_STORES; $slot++): ?>
                <div class="prq-slot space-y-2 hidden" data-slot="<?= $slot ?>">
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer !mb-0">
                            <input type="radio" name="quote_selected" value="<?= $slot ?>" class="prq-select w-4 h-4 text-indigo-600">
                            <span class="text-sm font-black text-slate-800">ร้านที่ <?= $slot + 1 ?></span>
                        </label>
                        <span class="prq-best-badge hidden text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100">ถูกที่สุด</span>
                        <button type="button" class="prq-remove text-slate-300 hover:text-red-500 text-sm" title="เอาร้านนี้ออก"><i class="fas fa-times-circle"></i></button>
                    </div>
                    <div>
                        <label>ร้านค้า <span class="text-rose-500">*</span></label>
                        <div class="flex gap-1">
                            <select name="quotes[<?= $slot ?>][store_id]" class="prq-store w-full"></select>
                            <button type="button" class="prq-add-store-btn shrink-0 px-2 rounded-lg bg-white border border-slate-200 text-indigo-600 text-xs font-bold" title="เพิ่มร้านใหม่">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="prq-new-store hidden mt-2 p-2 bg-white border border-indigo-100 rounded-lg space-y-1">
                            <input type="text" class="prq-new-name" placeholder="ชื่อร้านใหม่ *">
                            <input type="text" class="prq-new-phone" placeholder="เบอร์โทร (ถ้ามี)">
                            <input type="text" class="prq-new-contact" placeholder="ผู้ติดต่อ (ถ้ามี)">
                            <div class="flex gap-1 justify-end">
                                <button type="button" class="prq-new-cancel px-3 py-1 text-xs font-bold text-slate-500">ยกเลิก</button>
                                <button type="button" class="prq-new-save px-3 py-1 text-xs font-bold rounded-lg bg-indigo-600 text-white">บันทึกร้าน</button>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label>เลขที่ใบเสนอราคา</label>
                            <input type="text" name="quotes[<?= $slot ?>][quote_no]" class="prq-quote-no" maxlength="100">
                        </div>
                        <div>
                            <label>ส่งของ (วัน)</label>
                            <input type="number" min="0" name="quotes[<?= $slot ?>][delivery_days]" class="prq-delivery">
                        </div>
                    </div>
                    <div>
                        <label>เงื่อนไขชำระ / เครดิต</label>
                        <input type="text" name="quotes[<?= $slot ?>][payment_term]" class="prq-term" maxlength="100" placeholder="เช่น เงินสด, เครดิต 30 วัน">
                    </div>
                    <div>
                        <label>การบริการ / การรับประกัน / อื่นๆ</label>
                        <input type="text" name="quotes[<?= $slot ?>][service_note]" class="prq-service" maxlength="255" placeholder="เช่น รับประกัน 1 ปี, ติดตั้งฟรี">
                    </div>
                    <div>
                        <label>ไฟล์ใบเสนอราคา</label>
                        <input type="hidden" name="quotes[<?= $slot ?>][existing_attachment]" class="prq-existing" value="">
                        <div class="prq-existing-view hidden text-[12px] mb-1">
                            <a href="#" target="_blank" class="prq-existing-link text-indigo-600 font-bold underline"><i class="fas fa-paperclip mr-1"></i>ไฟล์เดิม</a>
                            <button type="button" class="prq-existing-clear ml-2 text-rose-500 font-bold">ลบ</button>
                        </div>
                        <input type="file" name="quote_attachment[<?= $slot ?>]" accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx,.doc,.docx"
                            class="w-full text-[11px] file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[12px] file:font-bold file:bg-indigo-50 file:text-indigo-600">
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <button type="button" id="prQuoteAddSlot" class="px-4 py-2 bg-white border border-indigo-200 text-indigo-600 text-[12px] font-black rounded-xl hover:bg-indigo-50">
            <i class="fas fa-plus mr-1"></i> เพิ่มร้านเทียบราคา
        </button>

        <div class="overflow-x-auto border border-slate-100 rounded-2xl">
            <table id="prQuoteTable" class="w-full text-left border-collapse"></table>
        </div>
        <p class="text-[12px] text-slate-500">
            ใส่ราคาต่อหน่วย (ไม่รวม VAT) ของแต่ละร้าน · "ราคากลาง" ไม่บังคับ · ส่วนลดในตารางรายการสินค้าจะถูกหักให้ทุกร้านเท่ากัน
        </p>

        <div id="prQuoteReasonWrap" class="hidden">
            <label class="text-[12px] font-black text-rose-600 block mb-1">
                เหตุผลที่ไม่เลือกร้านที่ราคาถูกที่สุด <span class="text-rose-500">*</span>
            </label>
            <textarea name="quote_selection_reason" id="prQuoteReason" rows="2"
                placeholder="เช่น ส่งของเร็วกว่า, ให้เครดิตนานกว่า, คุณภาพสินค้าดีกว่า"><?= htmlspecialchars($pr_quote_reason, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
</div>
<script src="assets/js/pr_quotes.js?v=<?= @filemtime(__DIR__ . '/assets/js/pr_quotes.js') ?>"></script>
