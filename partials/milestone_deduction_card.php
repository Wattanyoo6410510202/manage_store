<?php
$deductionChecked = !empty($deductionChecked);
$retentionPercentValue = isset($retentionPercentValue) ? (float)$retentionPercentValue : 0;
$deductionNoteValue = (string)($deductionNoteValue ?? '');
?>
<div class="h-full rounded-xl border border-slate-100 bg-slate-50 p-4 transition-opacity" id="deduction_card">
    <div class="flex items-center justify-between gap-3">
        <label for="use_deduction" class="text-[12px] font-bold uppercase text-slate-500 cursor-pointer">
            เงินประกัน / หักอื่นๆ
        </label>
        <input type="checkbox" id="use_deduction" onchange="calculateMoney()" <?= $deductionChecked ? 'checked' : '' ?>
            class="h-4 w-4 cursor-pointer rounded border-slate-300 text-amber-500 focus:ring-2 focus:ring-amber-400 focus:ring-offset-1">
    </div>

    <p class="mt-3 text-lg font-bold tabular-nums text-amber-600" id="deduction_total_display">- 0.00 ฿</p>

    <div class="mt-3 space-y-3 border-t border-slate-200 pt-3">
        <div class="flex items-center justify-between gap-3">
            <label for="retention_percent" class="text-xs font-bold text-slate-500">อัตราหัก</label>
            <div class="flex min-h-9 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2">
                <input type="number" id="retention_percent" name="retention_percent"
                    value="<?= htmlspecialchars((string)$retentionPercentValue, ENT_QUOTES, 'UTF-8') ?>"
                    oninput="calculateMoney()"
                    class="w-14 bg-transparent text-right text-sm font-bold tabular-nums text-slate-700 outline-none"
                    aria-label="เปอร์เซ็นต์เงินประกันหรือยอดหักอื่นๆ">
                <span class="text-xs font-bold text-slate-400">%</span>
            </div>
        </div>

        <label for="deduction_note" class="block text-xs font-bold text-slate-500">
            หมายเหตุการหัก
            <input type="text" id="deduction_note" name="deduction_note"
                value="<?= htmlspecialchars($deductionNoteValue, ENT_QUOTES, 'UTF-8') ?>"
                placeholder="เช่น เงินประกันผลงาน"
                class="mt-1.5 min-h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100 placeholder:text-slate-400">
        </label>
    </div>
</div>
