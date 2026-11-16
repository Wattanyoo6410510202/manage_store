<?php
// Modal ส่งลิงก์ให้อนุมัติ (ใน view_pr_new.php) — ต้องมี $data
?>
<style>
    #prsModal .prs-row { display: grid; grid-template-columns: 28px 1fr 1.3fr auto; gap: 8px; align-items: center; padding: 8px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; }
    #prsModal .prs-row.locked { background: #f0fdf4; border-color: #bbf7d0; }
    #prsModal .prs-no { width: 26px; height: 26px; border-radius: 99px; background: #eef2ff; color: #4338ca; font-weight: 700; font-size: 12px; display: grid; place-items: center; }
    #prsModal select { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 6px 8px; font-size: 13px; background: #fff; min-width: 0; }
    #prsModal .prs-act button { width: 28px; height: 28px; border-radius: 8px; color: #64748b; }
    #prsModal .prs-act button:hover:not(:disabled) { background: #f1f5f9; color: #0f172a; }
    #prsModal .prs-act button:disabled { opacity: .3; cursor: not-allowed; }
    #prsModal .badge { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px; white-space: nowrap; }
    @media (max-width: 640px) {
        #prsModal .prs-row { grid-template-columns: 28px 1fr auto; }
        #prsModal .prs-row .prs-user { grid-column: 2 / 4; }
    }
</style>

<div id="prsModal" class="no-print hidden fixed inset-0 z-[1000] bg-slate-900/50 flex items-end sm:items-center justify-center p-0 sm:p-4" onclick="if (event.target === this) prsClose()">
    <div class="bg-slate-50 w-full sm:max-w-2xl max-h-[94vh] flex flex-col rounded-t-2xl sm:rounded-2xl shadow-2xl">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 bg-white rounded-t-2xl">
            <div>
                <h3 class="font-bold text-slate-800 text-lg"><i class="fas fa-link text-emerald-600 mr-1"></i> ส่งลิงก์ให้อนุมัติ</h3>
                <p class="text-xs text-slate-500">ใบขอซื้อ <?= htmlspecialchars($data['doc_no']) ?> · ผู้รับลิงก์เปิดเซ็นได้ทันทีโดยไม่ต้อง login</p>
            </div>
            <button type="button" onclick="prsClose()" class="w-9 h-9 rounded-full hover:bg-slate-100 text-slate-500"><i class="fas fa-times"></i></button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4" id="prsBody">
            <div class="text-center text-slate-400 py-10"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>
        </div>

        <div class="px-5 py-3 border-t border-slate-200 bg-white rounded-b-2xl flex flex-wrap items-center gap-2 justify-between" id="prsFoot"></div>
    </div>
</div>

<script>
(function () {
    const PR_ID = <?= (int)$data['id'] ?>;
    const API = 'api/pr_sign_setup.php';
    let S = null;          // ข้อมูลจาก API
    let rows = [];         // [{stage, user_id}] แถวที่แก้ได้ (เรียงตามลำดับเซ็น)
    let links = null;      // {signer_id: url} หลังบันทึก

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const body = () => document.getElementById('prsBody');
    const foot = () => document.getElementById('prsFoot');
    const stageLabel = k => k ? (S.stages.find(s => s.key === k)?.label || k) : S.ack_label;
    const lockedRows = () => S.signers.filter(s => s.locked);

    window.prsOpen = async function () {
        document.getElementById('prsModal').classList.remove('hidden');
        links = null;
        await load();
    };
    window.prsClose = function () {
        document.getElementById('prsModal').classList.add('hidden');
    };

    async function load() {
        body().innerHTML = '<div class="text-center text-slate-400 py-10"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>';
        foot().innerHTML = '';
        try {
            S = await fetch(API + '?id=' + PR_ID).then(r => r.json());
        } catch (e) {
            S = {status: 'error', msg: 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้'};
        }
        if (S.status !== 'success') {
            body().innerHTML = '<div class="text-center text-red-500 py-10">' + esc(S.msg) + '</div>';
            return;
        }
        const pending = S.signers.filter(s => !s.locked && !s.signed_at);
        if (pending.length) {
            renderStatus();
        } else {
            rows = S.defaults.map(d => ({...d}));
            renderEditor();
        }
    }

    // ===== สถานะลิงก์ที่ส่งไปแล้ว =====
    function badge(s) {
        if (s.signed_at && s.decision === 'rejected') return '<span class="badge bg-red-100 text-red-700">ไม่อนุมัติ</span>';
        if (s.signed_at) return '<span class="badge bg-emerald-100 text-emerald-700">เซ็นแล้ว</span>';
        if (s.done) return '<span class="badge bg-emerald-100 text-emerald-700">อนุมัติในระบบแล้ว</span>';
        if (s.is_current) return '<span class="badge bg-amber-100 text-amber-700">ถึงคิวเซ็น</span>';
        return '<span class="badge bg-slate-100 text-slate-500">รอคิว</span>';
    }

    function renderStatus() {
        let html = '<p class="text-sm text-slate-600">ส่งลิงก์ไปแล้ว — ลำดับการเซ็นตามด้านล่าง (คนถัดไปจะเซ็นได้เมื่อคนก่อนหน้าเซ็นแล้ว)</p><div class="space-y-2">';
        S.signers.forEach((s, i) => {
            const url = links && links[s.id];
            html += `<div class="bg-white border border-slate-200 rounded-xl p-3">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 shrink-0 rounded-full bg-indigo-50 text-indigo-700 font-bold text-xs grid place-items-center">${i + 1}</span>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-slate-800 truncate">${esc(s.user_name)}</div>
                        <div class="text-xs text-slate-500">${esc(s.label)}${s.signed_at ? ' · ' + esc(s.signed_at) : ''}${s.comment ? ' · “' + esc(s.comment) + '”' : ''}</div>
                    </div>
                    ${badge(s)}
                </div>
                ${!s.signed_at && !s.done ? `
                <div class="mt-2 flex flex-wrap gap-2 items-center">
                    ${url ? `<input readonly value="${esc(url)}" class="flex-1 min-w-[180px] text-xs border border-slate-200 rounded-lg px-2 py-1.5 bg-slate-50" onclick="this.select()">
                             <button type="button" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-slate-800 text-white" onclick="prsCopy(${s.id})"><i class="fas fa-copy"></i> คัดลอก</button>`
                          : `<button type="button" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-slate-800 text-white" onclick="prsNewLink(${s.id}, false)"><i class="fas fa-link"></i> สร้าง/คัดลอกลิงก์</button>`}
                    ${s.has_line ? `<button type="button" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-[#06C755] text-white" onclick="prsNewLink(${s.id}, true)"><i class="fab fa-line"></i> ส่ง LINE</button>` : ''}
                </div>` : ''}
            </div>`;
        });
        html += '</div>';
        if (!links) html += '<p class="text-xs text-slate-400"><i class="fas fa-info-circle"></i> ระบบเก็บลิงก์แบบเข้ารหัส — กด “สร้าง/คัดลอกลิงก์” จะได้ลิงก์ใหม่ และลิงก์เก่าของคนนั้นจะใช้ไม่ได้</p>';
        body().innerHTML = html;
        foot().innerHTML = `
            <button type="button" class="text-sm font-bold px-4 py-2 rounded-xl text-red-600 hover:bg-red-50" onclick="prsCancelAll()"><i class="fas fa-ban"></i> ยกเลิกลิงก์ทั้งหมด</button>
            <div class="flex gap-2">
                ${links ? '<button type="button" class="text-sm font-bold px-4 py-2 rounded-xl bg-slate-100 text-slate-700" onclick="prsCopyAll()"><i class="fas fa-copy"></i> คัดลอกทั้งหมด</button>' : ''}
                <button type="button" class="text-sm font-bold px-4 py-2 rounded-xl bg-indigo-600 text-white" onclick="prsEdit()"><i class="fas fa-user-pen"></i> แก้ไขผู้อนุมัติ / ลำดับ</button>
            </div>`;
    }

    window.prsEdit = function () {
        rows = S.signers.filter(s => !s.locked && !(s.signed_at)).map(s => ({stage: s.stage || '', user_id: +s.user_id}));
        if (!rows.length) rows = S.defaults.map(d => ({...d}));
        renderEditor();
    };

    // ===== เลือกผู้อนุมัติ + ลำดับ =====
    function stageOptions(cur) {
        const used = new Set(rows.map(r => r.stage).filter(Boolean).concat(lockedRows().map(s => s.stage).filter(Boolean)));
        let o = '';
        S.stages.forEach(st => {
            const dis = st.done || (used.has(st.key) && st.key !== cur);
            o += `<option value="${st.key}" ${st.key === cur ? 'selected' : ''} ${dis ? 'disabled' : ''}>อนุมัติขั้น: ${esc(st.label)}${st.done ? ' (อนุมัติแล้ว)' : ''}</option>`;
        });
        o += `<option value="" ${cur === '' ? 'selected' : ''}>${esc(S.ack_label)} (ไม่ผูกขั้นอนุมัติ)</option>`;
        return o;
    }

    function userOptions(stage, cur) {
        const list = S.candidates[stage] || [];
        const used = new Set(rows.map(r => +r.user_id).concat(lockedRows().map(s => +s.user_id)));
        let o = '<option value="0">— เลือกผู้ลงนาม —</option>';
        let groupOpen = null;
        list.forEach(u => {
            const grp = stage === '' ? null : ['ตาม role ของขั้นนี้', 'admin / gmhok', 'ผู้ใช้อื่นทั้งหมด'][u.rank];
            if (grp !== groupOpen) {
                if (groupOpen !== null) o += '</optgroup>';
                if (grp) o += `<optgroup label="${grp}">`;
                groupOpen = grp;
            }
            const dis = used.has(+u.id) && +u.id !== +cur;
            o += `<option value="${u.id}" ${+u.id === +cur ? 'selected' : ''} ${dis ? 'disabled' : ''}>${esc(u.name)} (${esc(u.role)})</option>`;
        });
        if (groupOpen) o += '</optgroup>';
        return o;
    }

    function renderEditor() {
        const locked = lockedRows();
        let html = `<p class="text-sm text-slate-600">ค่าเริ่มต้นเรียงตาม role ของขั้นอนุมัติที่ใบนี้ต้องผ่าน — เปลี่ยนคน เพิ่ม/ลบ หรือ <b>เลื่อนลำดับ</b> ได้ (เซ็นทีละคนตามลำดับ)</p><div class="space-y-2">`;
        locked.forEach((s, i) => {
            html += `<div class="prs-row locked"><span class="prs-no">${i + 1}</span>
                <div class="text-sm text-slate-700">${esc(s.label)}</div>
                <div class="prs-user text-sm font-semibold text-slate-800">${esc(s.user_name)}</div>
                <span class="badge bg-emerald-100 text-emerald-700">เซ็นแล้ว</span></div>`;
        });
        rows.forEach((r, i) => {
            html += `<div class="prs-row">
                <span class="prs-no">${locked.length + i + 1}</span>
                <select onchange="prsSetStage(${i}, this.value)">${stageOptions(r.stage)}</select>
                <select class="prs-user" onchange="prsSetUser(${i}, this.value)">${userOptions(r.stage, r.user_id)}</select>
                <div class="prs-act flex">
                    <button type="button" title="เลื่อนขึ้น" onclick="prsMove(${i}, -1)" ${i === 0 ? 'disabled' : ''}><i class="fas fa-arrow-up"></i></button>
                    <button type="button" title="เลื่อนลง" onclick="prsMove(${i}, 1)" ${i === rows.length - 1 ? 'disabled' : ''}><i class="fas fa-arrow-down"></i></button>
                    <button type="button" title="ลบ" onclick="prsRemove(${i})"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>`;
        });
        html += `</div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="text-sm font-bold px-3 py-2 rounded-xl border border-dashed border-indigo-300 text-indigo-600 hover:bg-indigo-50" onclick="prsAdd()"><i class="fas fa-plus"></i> เพิ่มผู้ลงนาม</button>
                <button type="button" class="text-sm px-3 py-2 rounded-xl text-slate-500 hover:bg-slate-100" onclick="prsReset()"><i class="fas fa-undo"></i> ค่าเริ่มต้นตาม role</button>
            </div>
            <label class="flex items-start gap-2 text-sm text-slate-700 bg-white border border-slate-200 rounded-xl p-3 cursor-pointer">
                <input type="checkbox" id="prsLine" class="mt-1" checked>
                <span>ส่งลิงก์ทาง LINE ให้ผู้ที่ถึงคิวอัตโนมัติ<br><span class="text-xs text-slate-400">คนแรกได้ทันที คนถัดไปได้เมื่อคนก่อนหน้าเซ็นแล้ว (เฉพาะผู้ที่ผูก LINE ไว้)</span></span>
            </label>`;
        body().innerHTML = html;
        foot().innerHTML = `
            <span class="text-xs text-slate-400">${S.signers.some(s => !s.locked) ? '<i class="fas fa-exclamation-triangle text-amber-500"></i> ลิงก์เดิมที่ยังไม่เซ็นจะใช้ไม่ได้' : ''}</span>
            <div class="flex gap-2">
                ${S.signers.some(s => !s.locked && !s.signed_at) ? '<button type="button" class="text-sm font-bold px-4 py-2 rounded-xl bg-slate-100 text-slate-700" onclick="prsBack()">ย้อนกลับ</button>' : ''}
                <button type="button" id="prsSave" class="text-sm font-bold px-5 py-2 rounded-xl bg-emerald-600 text-white" onclick="prsSave()"><i class="fas fa-paper-plane"></i> สร้างลิงก์</button>
            </div>`;
    }

    window.prsSetStage = (i, v) => {
        rows[i].stage = v;
        const ok = (S.candidates[v] || []).some(u => +u.id === +rows[i].user_id);
        if (!ok) {
            const used = new Set(rows.map(r => +r.user_id).concat(lockedRows().map(s => +s.user_id)));
            rows[i].user_id = ((S.candidates[v] || []).find(u => !used.has(+u.id) && (v === '' ? false : u.rank < 2)) || {id: 0}).id;
        }
        renderEditor();
    };
    window.prsSetUser = (i, v) => { rows[i].user_id = +v; renderEditor(); };
    window.prsMove = (i, d) => { const j = i + d; [rows[i], rows[j]] = [rows[j], rows[i]]; renderEditor(); };
    window.prsRemove = i => { rows.splice(i, 1); renderEditor(); };
    window.prsAdd = () => {
        const used = new Set(rows.map(r => r.stage).filter(Boolean).concat(lockedRows().map(s => s.stage).filter(Boolean)));
        const st = S.stages.find(s => !s.done && !used.has(s.key));
        rows.push({stage: st ? st.key : '', user_id: 0});
        prsSetStage(rows.length - 1, rows[rows.length - 1].stage);
    };
    window.prsReset = () => { rows = S.defaults.map(d => ({...d})); renderEditor(); };
    window.prsBack = () => renderStatus();

    async function post(params) {
        const fd = new FormData();
        Object.entries(params).forEach(([k, v]) => fd.append(k, v));
        try {
            return await fetch(API, {method: 'POST', body: fd}).then(r => r.json());
        } catch (e) {
            return {status: 'error', msg: 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้'};
        }
    }

    window.prsSave = async function () {
        if (!rows.length) return Swal.fire({icon: 'warning', title: 'กรุณาเพิ่มผู้ลงนามอย่างน้อย 1 คน'});
        if (rows.some(r => !+r.user_id)) return Swal.fire({icon: 'warning', title: 'กรุณาเลือกผู้ลงนามให้ครบทุกลำดับ'});
        const btn = document.getElementById('prsSave');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังสร้าง...';
        const res = await post({action: 'save', id: PR_ID, list: JSON.stringify(rows), notify_line: document.getElementById('prsLine').checked ? 1 : ''});
        if (res.status !== 'success') {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> สร้างลิงก์';
            return Swal.fire({icon: 'error', title: 'ไม่สำเร็จ', text: res.msg});
        }
        const got = res.links || {};
        await load();
        links = got;
        renderStatus();
    };

    window.prsNewLink = async function (signerId, sendLine) {
        const res = await post({action: 'link', signer_id: signerId, send_line: sendLine ? 1 : ''});
        if (res.status !== 'success') return Swal.fire({icon: 'error', title: 'ไม่สำเร็จ', text: res.msg});
        links = Object.assign(links || {}, {[signerId]: res.url});
        renderStatus();
        if (sendLine) {
            Swal.fire({icon: res.line_sent ? 'success' : 'warning', title: res.line_sent ? 'ส่ง LINE แล้ว' : 'ส่ง LINE ไม่สำเร็จ', text: res.line_sent ? '' : 'คัดลอกลิงก์ไปส่งเองได้', timer: res.line_sent ? 1500 : undefined, showConfirmButton: !res.line_sent});
        } else {
            prsCopy(signerId);
        }
    };

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        return Promise.resolve();
    }
    const toast = t => Swal.fire({toast: true, position: 'top', icon: 'success', title: t, timer: 1400, showConfirmButton: false});

    window.prsCopy = signerId => copyText(links[signerId]).then(() => toast('คัดลอกลิงก์แล้ว'));
    window.prsCopyAll = function () {
        const text = 'ใบขอซื้อ ' + <?= json_encode((string)$data['doc_no'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?> + ' — ลิงก์เซ็นอนุมัติ (ไม่ต้อง login)\n' + S.signers
            .filter(s => links[s.id])
            .map((s, i) => `${i + 1}. ${s.user_name} (${s.label})\n${links[s.id]}`).join('\n');
        copyText(text).then(() => toast('คัดลอกทั้งหมดแล้ว'));
    };

    window.prsCancelAll = async function () {
        const ok = await Swal.fire({icon: 'warning', title: 'ยกเลิกลิงก์ทั้งหมด?', text: 'ลิงก์ที่ยังไม่ได้เซ็นจะใช้ไม่ได้ทันที', showCancelButton: true, confirmButtonText: 'ยกเลิกลิงก์', cancelButtonText: 'ไม่', confirmButtonColor: '#dc2626'});
        if (!ok.isConfirmed) return;
        const res = await post({action: 'cancel', id: PR_ID});
        if (res.status !== 'success') return Swal.fire({icon: 'error', title: 'ไม่สำเร็จ', text: res.msg});
        links = null;
        await load();
    };
})();
</script>
