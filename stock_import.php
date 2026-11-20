<?php

require_once __DIR__ . '/stock_common.php';

$canImport = stock_can_manage((string)$stock_actor['role']) && stock_can_approve_reconciliation((string)$stock_actor['role']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $respond = static function (int $status, array $payload): void {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    };
    if (!$canImport) {
        $respond(403, ['error' => 'เฉพาะ GMHR หรือผู้ดูแลระบบเท่านั้นที่นำเข้ายอดนับได้']);
    }
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body) || !hash_equals($_SESSION['stock_csrf'] ?? '', (string)($body['csrf'] ?? ''))) {
        $respond(400, ['error' => 'คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่']);
    }
    $supId = (int)($body['sup_id'] ?? 0);
    $companyIds = array_map(static fn(array $company): int => (int)$company['id'], stock_companies($conn));
    if (!in_array($supId, $companyIds, true)) {
        $respond(422, ['error' => 'กรุณาเลือกบริษัท']);
    }
    $rows = $body['rows'] ?? $body['items'] ?? null;
    if (!is_array($rows) || !$rows || count($rows) > 2000) {
        $respond(422, ['error' => 'ไม่พบรายการในไฟล์ หรือมีมากเกิน 2,000 รายการ']);
    }
    $rows = array_values($rows);

    $action = (string)($body['action'] ?? '');
    if ($action === 'preview') {
        $respond(200, stock_import_match_rows($conn, $supId, $rows));
    }
    if ($action === 'commit') {
        $countDate = (string)($body['count_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $countDate)) {
            $respond(422, ['error' => 'กรุณาระบุวันที่นับ']);
        }
        $source = trim(mb_substr((string)($body['file_name'] ?? ''), 0, 120) . ' / ' . mb_substr((string)($body['sheet_name'] ?? ''), 0, 60), ' /');
        $note = trim((string)($body['note'] ?? ''));
        $reason = 'นำเข้ายอดนับมือ วันที่ ' . date('d/m/Y', strtotime($countDate)) . ($source !== '' ? ' จาก ' . $source : '') . ($note !== '' ? ' · ' . mb_substr($note, 0, 200) : '');
        try {
            $conn->begin_transaction();
            $result = stock_import_counts($conn, $supId, (int)$stock_actor['id'], $rows, $reason, !empty($body['apply_min']));
            $conn->commit();
            $respond(200, ['result' => $result]);
        } catch (Throwable $exception) {
            $conn->rollback();
            $respond(422, ['error' => stock_error_message($exception)]);
        }
    }
    $respond(400, ['error' => 'คำสั่งไม่ถูกต้อง']);
}

if (!$canImport) {
    http_response_code(403);
    die('เฉพาะ GMHR หรือผู้ดูแลระบบเท่านั้นที่นำเข้ายอดนับได้');
}

$companies = stock_companies($conn);
$selectedSupId = (int)($_GET['sup_id'] ?? 0);

include __DIR__ . '/header.php';
?>

<div class="mx-auto max-w-screen-2xl space-y-6 p-4 md:p-8" data-stock-ui="procurement" id="stockImport">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold text-cyan-700"><a href="stock.php<?= $selectedSupId > 0 ? '?sup_id=' . $selectedSupId : '' ?>" class="hover:underline">คลังพัสดุ</a></p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">นำเข้ายอดนับ Stock จาก Excel</h1>
            <p class="mt-1 text-sm text-slate-500">อัปโหลดไฟล์ยอดนับมือ ตรวจการจับคู่และผลต่างก่อน แล้วจึงยืนยันปรับยอด</p>
        </div>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="block text-sm font-semibold text-slate-700">บริษัท/หน่วยงาน
                <select data-import="company" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    <option value="">เลือกบริษัท/หน่วยงาน</option>
                    <?php foreach ($companies as $company): ?>
                    <option value="<?= (int)$company['id'] ?>" <?= $selectedSupId === (int)$company['id'] ? 'selected' : '' ?>><?= stock_e(supplier_display_name($company['company_name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm font-semibold text-slate-700">ไฟล์ Excel
                <input type="file" accept=".xlsx,.xls" data-import="file" class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white text-sm font-normal file:mr-3 file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
            </label>
            <label class="block text-sm font-semibold text-slate-700">Sheet
                <select data-import="sheet" disabled class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400"><option>เลือกไฟล์ก่อน</option></select>
            </label>
            <label class="block text-sm font-semibold text-slate-700">วันที่นับ
                <input type="date" data-import="date" value="<?= date('Y-m-d') ?>" class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            </label>
        </div>
        <p class="mt-3 text-xs text-slate-500">ระบบอ่านคอลัมน์ <b>ItemCode, ชื่อสินค้า, หน่วยนับ, จำนวนคงเหลือ</b> (ถ้าว่างใช้ <b>สินค้าคงเหลือ</b>), <b>รหัสคลัง</b> เป็นหมวดหมู่ และ <b>MIN</b> · จับคู่กับพัสดุเดิมด้วยรหัส (SKU = ItemCode) ก่อน แล้วจึงใช้ชื่อที่ตรงกัน</p>
        <div data-import="error" class="mt-4 hidden rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>
    </section>

    <section data-import="preview" class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2 text-xs font-semibold" data-import="summary"></div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" data-import="only-changes" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">แสดงเฉพาะรายการที่จะเปลี่ยน</label>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="whitespace-nowrap bg-slate-50 text-xs font-semibold text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">รหัส / ชื่อในไฟล์</th>
                        <th class="px-4 py-3 text-right">ยอดนับ</th>
                        <th class="px-4 py-3 text-left">นำเข้าเป็น</th>
                        <th class="px-4 py-3 text-right">ยอดในระบบ</th>
                        <th class="px-4 py-3 text-right">ผลต่าง</th>
                        <th class="px-4 py-3 text-left">หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" data-import="rows"></tbody>
            </table>
        </div>
        <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 p-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label class="block text-sm font-semibold text-slate-700 sm:w-96">หมายเหตุเพิ่มเติม (ไม่บังคับ)
                    <input type="text" data-import="note" maxlength="200" placeholder="เช่น ตรวจนับโดยทีมจัดซื้อ" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-normal outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                </label>
                <label class="inline-flex items-center gap-2 pb-2.5 text-sm text-slate-700"><input type="checkbox" data-import="apply-min" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">ใช้ค่า MIN จากไฟล์</label>
            </div>
            <button type="button" data-import="commit" class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50">ยืนยันนำเข้ายอดนับ</button>
        </div>
    </section>

    <section data-import="done" class="hidden rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-emerald-900 shadow-sm">
        <h2 class="text-lg font-black"><i class="fas fa-circle-check mr-2"></i>นำเข้ายอดนับเรียบร้อย</h2>
        <p class="mt-2 text-sm" data-import="done-text"></p>
        <a data-import="done-link" class="mt-4 inline-flex rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">ไปที่ภาพรวม Stock</a>
    </section>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
(() => {
    const root = document.getElementById('stockImport');
    const $ = (name) => root.querySelector(`[data-import="${name}"]`);
    const csrf = <?= json_encode(stock_csrf_token()) ?>;
    const num = (v) => Number(v || 0).toLocaleString('th-TH', { maximumFractionDigits: 2 });
    const el = (tag, className, text) => { const n = document.createElement(tag); if (className) n.className = className; if (text !== undefined && text !== null) n.textContent = text; return n; };
    const norm = (v) => String(v ?? '').replace(/\s+/g, '').toLowerCase();
    let workbook = null, fileName = '', rows = [], products = [], productById = new Map();

    const showError = (message) => { const e = $('error'); e.textContent = message || ''; e.classList.toggle('hidden', !message); };
    const post = async (payload) => {
        const res = await fetch('stock_import.php', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ csrf, ...payload }) });
        let data = {};
        try { data = await res.json(); } catch (_) { /* non-JSON error */ }
        if (!res.ok) throw new Error(data.error || 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        return data;
    };

    const parseSheet = (sheet) => {
        const aoa = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: true, defval: null });
        const headerIndex = aoa.slice(0, 15).findIndex((r) => r.some((c) => String(c ?? '').trim() === 'ItemCode') || r.some((c) => String(c ?? '').trim() === 'ชื่อสินค้า'));
        if (headerIndex < 0) throw new Error('ไม่พบหัวตาราง (ItemCode / ชื่อสินค้า) ใน sheet นี้');
        const header = aoa[headerIndex].map((c) => String(c ?? '').trim());
        const col = (...names) => { for (const n of names) { const i = header.findIndex((h) => h.toUpperCase() === n.toUpperCase()); if (i >= 0) return i; } return -1; };
        const c = { code: col('ItemCode'), wh: col('รหัสคลัง'), name: col('ชื่อสินค้า'), unit: col('หน่วยนับ', 'หน่วย'), remain: col('จำนวนคงเหลือ'), carried: col('สินค้าคงเหลือ'), min: col('MIN') };
        if (c.name < 0 || (c.remain < 0 && c.carried < 0)) throw new Error('ไม่พบคอลัมน์ชื่อสินค้า หรือคอลัมน์จำนวนคงเหลือ');
        const cell = (r, i) => (i >= 0 ? r[i] : null);
        const asNumber = (v) => (typeof v === 'number' ? v : (typeof v === 'string' && v.trim() !== '' && !Number.isNaN(Number(v.replace(/,/g, ''))) ? Number(v.replace(/,/g, '')) : null));
        const out = [];
        aoa.slice(headerIndex + 1).forEach((r, offset) => {
            const name = String(cell(r, c.name) ?? '').trim();
            const code = String(cell(r, c.code) ?? '').trim();
            if (!name) return;
            let qty = asNumber(cell(r, c.remain));
            if (qty === null) qty = asNumber(cell(r, c.carried));
            const wh = String(cell(r, c.wh) ?? '').trim();
            out.push({
                line: headerIndex + offset + 2,
                code, name,
                unit: String(cell(r, c.unit) ?? '').trim(),
                category: wh.replace(/^\d+\s*-\s*/, '').replace(/\s+SS(&F)?$/i, '').trim(),
                quantity: qty,
                min: asNumber(cell(r, c.min)),
            });
        });
        if (!out.length) throw new Error('ไม่พบรายการสินค้าใน sheet นี้');
        return out;
    };

    const rowState = (row) => {
        const product = /^\d+$/.test(row.action) ? productById.get(Number(row.action)) : null;
        const warnings = [];
        if (row.quantity === null) warnings.push(['ไม่มียอดนับในไฟล์', 'text-slate-500']);
        if (product && row.unit && norm(product.unit) !== norm(row.unit)) warnings.push([`หน่วยไม่ตรง: ไฟล์ "${row.unit}" ระบบ "${product.unit}"`, 'text-rose-600']);
        if (product && row.matchBy === 'name' && String(row.action) === String(row.suggested)) warnings.push(['จับคู่จากชื่อ', 'text-slate-500']);
        if (product && row.matchBy === 'similar' && String(row.action) === String(row.suggested)) warnings.push([`ชื่อใกล้เคียง กรุณาตรวจสอบ: "${product.name}"`, 'text-amber-700']);
        if (row.action === 'create' && !row.unit) warnings.push(['ไม่มีหน่วยนับ', 'text-rose-600']);
        if (row.action === 'create' && row.code && products.some((p) => norm(p.sku) === norm(row.code))) warnings.push(['รหัสนี้มีในระบบแล้ว', 'text-rose-600']);
        const diff = product && row.quantity !== null ? Math.round((row.quantity - Number(product.quantity)) * 100) / 100 : (row.action === 'create' ? row.quantity : null);
        const changes = row.action === 'create' || (product && diff !== null && Math.abs(diff) > 0.00001);
        return { product, warnings, diff, changes };
    };

    const render = () => {
        const tbody = $('rows'); tbody.replaceChildren();
        const used = new Map();
        rows.forEach((r) => { if (/^\d+$/.test(r.action)) used.set(r.action, (used.get(r.action) || 0) + 1); });
        const counts = { match: 0, create: 0, skip: 0, change: 0, problems: 0 };
        const onlyChanges = $('only-changes').checked;
        rows.forEach((row, index) => {
            const s = rowState(row);
            if (row.action === 'skip') counts.skip++; else if (row.action === 'create') counts.create++; else counts.match++;
            if (s.changes) counts.change++;
            if (/^\d+$/.test(row.action) && used.get(row.action) > 1) s.warnings.push(['จับคู่ซ้ำกับแถวอื่น', 'text-rose-600']);
            const hardProblem = row.action !== 'skip' && (row.quantity === null || (row.action === 'create' && !row.unit) || s.warnings.some(([t]) => t === 'จับคู่ซ้ำกับแถวอื่น' || t === 'รหัสนี้มีในระบบแล้ว'));
            if (hardProblem) counts.problems++;
            if (onlyChanges && !s.changes && !hardProblem) return;
            const tr = el('tr', `align-top ${row.action === 'skip' ? 'bg-slate-50/60 text-slate-400' : ''} ${hardProblem ? 'bg-rose-50/60' : ''}`);
            const first = el('td', 'px-4 py-2.5');
            first.appendChild(el('div', 'font-semibold text-slate-900', row.name));
            first.appendChild(el('div', 'text-xs text-slate-500', [row.code, row.category, row.unit && `หน่วย ${row.unit}`, `แถว ${row.line}`].filter(Boolean).join(' · ')));
            tr.appendChild(first);
            tr.appendChild(el('td', 'whitespace-nowrap px-4 py-2.5 text-right font-bold tabular-nums', row.quantity === null ? '–' : num(row.quantity)));
            const actionTd = el('td', 'px-4 py-2.5');
            const select = el('select', 'w-64 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500');
            const opt = (value, label) => { const o = el('option', '', label); o.value = value; return o; };
            select.appendChild(opt('create', '＋ สร้างพัสดุใหม่'));
            select.appendChild(opt('skip', 'ข้าม (ไม่นำเข้า)'));
            const group = el('optgroup'); group.label = 'จับคู่กับพัสดุเดิม';
            products.forEach((p) => group.appendChild(opt(String(p.id), `${p.name} (${p.unit}) · ${num(p.quantity)}`)));
            select.appendChild(group);
            select.value = String(row.action);
            select.addEventListener('change', () => { rows[index].action = select.value; render(); });
            actionTd.appendChild(select);
            tr.appendChild(actionTd);
            tr.appendChild(el('td', 'whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-600', s.product ? `${num(s.product.quantity)} ${s.product.unit}` : (row.action === 'create' ? 'ใหม่' : '–')));
            const diffTd = el('td', 'whitespace-nowrap px-4 py-2.5 text-right font-bold tabular-nums');
            if (s.diff === null || row.action === 'skip') { diffTd.textContent = '–'; diffTd.classList.add('text-slate-300'); }
            else { diffTd.textContent = (s.diff > 0 ? '+' : '') + num(s.diff); diffTd.classList.add(s.diff > 0 ? 'text-emerald-600' : s.diff < 0 ? 'text-rose-600' : 'text-slate-400'); }
            tr.appendChild(diffTd);
            const noteTd = el('td', 'px-4 py-2.5 text-xs');
            s.warnings.forEach(([text, cls]) => noteTd.appendChild(el('div', cls, text)));
            tr.appendChild(noteTd);
            tbody.appendChild(tr);
        });
        const summary = $('summary'); summary.replaceChildren();
        [
            [`ทั้งหมด ${rows.length}`, 'bg-slate-100 text-slate-700'],
            [`จับคู่พัสดุเดิม ${counts.match}`, 'bg-indigo-50 text-indigo-700'],
            [`สร้างใหม่ ${counts.create}`, 'bg-emerald-50 text-emerald-700'],
            [`ข้าม ${counts.skip}`, 'bg-slate-100 text-slate-500'],
            [`จะเปลี่ยนยอด ${counts.change}`, 'bg-amber-50 text-amber-800'],
            ...(counts.problems ? [[`ต้องแก้ไข ${counts.problems}`, 'bg-rose-100 text-rose-700']] : []),
        ].forEach(([text, cls]) => summary.appendChild(el('span', `rounded-full px-3 py-1 ${cls}`, text)));
        $('commit').disabled = counts.problems > 0 || counts.match + counts.create === 0;
    };

    const runPreview = async () => {
        showError('');
        $('preview').classList.add('hidden');
        $('done').classList.add('hidden');
        const supId = $('company').value;
        if (!workbook || !$('sheet').value) return;
        if (!supId) { showError('กรุณาเลือกบริษัท/หน่วยงาน'); return; }
        try {
            const parsed = parseSheet(workbook.Sheets[$('sheet').value]);
            const data = await post({ action: 'preview', sup_id: Number(supId), rows: parsed.map(({ code, name }) => ({ code, name })) });
            products = data.products; productById = new Map(products.map((p) => [Number(p.id), p]));
            rows = parsed.map((row, i) => {
                const m = data.matches[i] || {};
                const suggested = m.product_id ? String(m.product_id) : null;
                return { ...row, suggested, matchBy: m.by, action: suggested ?? (row.quantity === null ? 'skip' : 'create') };
            });
            render();
            $('preview').classList.remove('hidden');
        } catch (error) {
            showError(error.message);
        }
    };

    $('file').addEventListener('change', async () => {
        showError('');
        const file = $('file').files[0];
        const sheetSelect = $('sheet');
        sheetSelect.replaceChildren(); sheetSelect.disabled = true; workbook = null;
        if (!file) return;
        if (typeof XLSX === 'undefined') { showError('ไม่สามารถโหลดไลบรารี Excel ได้ กรุณาตรวจสอบอินเทอร์เน็ต'); return; }
        try {
            workbook = XLSX.read(await file.arrayBuffer(), { type: 'array', cellDates: true });
            fileName = file.name;
            const placeholder = el('option', '', 'เลือก sheet'); placeholder.value = ''; sheetSelect.appendChild(placeholder);
            workbook.SheetNames.forEach((name) => { const o = el('option', '', name); o.value = name; sheetSelect.appendChild(o); });
            sheetSelect.disabled = false;
        } catch (error) {
            showError('อ่านไฟล์ไม่สำเร็จ: ' + error.message);
        }
    });
    $('sheet').addEventListener('change', runPreview);
    $('company').addEventListener('change', runPreview);
    $('only-changes').addEventListener('change', render);

    $('commit').addEventListener('click', async () => {
        const button = $('commit');
        const changing = rows.filter((r) => r.action !== 'skip');
        if (!window.confirm(`ยืนยันนำเข้ายอดนับ ${changing.length} รายการ? ระบบจะปรับยอด Stock ทันที`)) return;
        button.disabled = true; showError('');
        try {
            const items = rows.map((r) => {
                const product = /^\d+$/.test(r.action) ? productById.get(Number(r.action)) : null;
                return { action: r.action, code: r.code, name: r.name, unit: r.unit, category: r.category, quantity: r.quantity, min: r.min, expected: product ? Number(product.quantity) : null };
            });
            const supId = $('company').value;
            const data = await post({ action: 'commit', sup_id: Number(supId), items, count_date: $('date').value, file_name: fileName, sheet_name: $('sheet').value, note: $('note').value, apply_min: $('apply-min').checked });
            const r = data.result;
            $('preview').classList.add('hidden');
            $('done-text').textContent = `สร้างพัสดุใหม่ ${r.created} · ปรับยอด ${r.adjusted} · ยอดตรงอยู่แล้ว ${r.unchanged} · ข้าม ${r.skipped} รายการ`;
            $('done-link').href = `stock.php?sup_id=${encodeURIComponent(supId)}`;
            $('done').classList.remove('hidden');
            $('file').value = ''; $('sheet').replaceChildren(); $('sheet').disabled = true; workbook = null;
        } catch (error) {
            showError(error.message);
            button.disabled = false;
        }
    });
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
