/**
 * แสดง timeline ของใบขอซื้อแบบ step (ใช้ SweetAlert2)
 * เรียกใช้: showPRTimeline(prId)
 */
(function () {
    const COLORS = {
        indigo: ['#eef2ff', '#4f46e5'], amber: ['#fffbeb', '#d97706'], emerald: ['#ecfdf5', '#059669'],
        sky: ['#f0f9ff', '#0284c7'], red: ['#fef2f2', '#dc2626'], slate: ['#f1f5f9', '#64748b'],
    };
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmtDate = (s) => {
        if (!s) return '';
        const d = new Date(String(s).replace(' ', 'T'));
        return isNaN(d) ? s : d.toLocaleString('th-TH', { day: 'numeric', month: 'short', year: '2-digit', hour: '2-digit', minute: '2-digit' });
    };
    const gap = (a, b) => {
        const ms = new Date(String(b).replace(' ', 'T')) - new Date(String(a).replace(' ', 'T'));
        if (!(ms > 0)) return '';
        const h = Math.floor(ms / 3600000);
        if (h >= 24) return `+${Math.floor(h / 24)} วัน ${h % 24 ? (h % 24) + ' ชม.' : ''}`;
        if (h >= 1) return `+${h} ชม.`;
        return `+${Math.max(1, Math.round(ms / 60000))} นาที`;
    };

    const STYLE = `<style>
        .prtl { text-align: left; max-height: 62vh; overflow-y: auto; padding: 4px 6px 4px 2px; }
        .prtl-head { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; font-size: 12px; }
        .prtl-chip { padding: 2px 10px; border-radius: 99px; background: #f1f5f9; color: #334155; font-weight: 700; }
        .prtl-step { position: relative; display: flex; gap: 12px; padding-bottom: 16px; }
        .prtl-step:not(:last-child)::before { content: ''; position: absolute; left: 15px; top: 32px; bottom: 0; width: 2px; background: #e2e8f0; }
        .prtl-step.done:not(:last-child)::before { background: #cbd5e1; }
        .prtl-dot { flex: 0 0 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; border: 2px solid; z-index: 1; }
        .prtl-body { flex: 1; min-width: 0; padding-top: 4px; }
        .prtl-title { font-size: 14px; font-weight: 800; color: #0f172a; }
        .prtl-meta { font-size: 12px; color: #64748b; margin-top: 1px; }
        .prtl-gap { font-size: 11px; color: #94a3b8; margin-left: 6px; }
        .prtl-note { margin-top: 6px; font-size: 12.5px; padding: 6px 10px; border-radius: 8px; white-space: pre-wrap; word-break: break-word; }
        .prtl-step.waiting .prtl-dot { border-style: dashed; background: #fff; color: #94a3b8; border-color: #cbd5e1; }
        .prtl-step.waiting .prtl-title { color: #94a3b8; font-weight: 700; }
        .prtl-step.current .prtl-dot { background: #fffbeb; color: #d97706; border-color: #f59e0b; animation: prtlPulse 1.6s infinite; }
        .prtl-step.current .prtl-title { color: #b45309; }
        .prtl-step.blocked .prtl-dot { background: #fef2f2; color: #dc2626; border-color: #fca5a5; }
        .prtl-step.blocked .prtl-title { color: #b91c1c; }
        .prtl-legacy { font-size: 10px; color: #94a3b8; margin-left: 4px; }
        @keyframes prtlPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(245,158,11,.35); } 50% { box-shadow: 0 0 0 6px rgba(245,158,11,0); } }
    </style>`;

    function render(data) {
        const pr = data.pr;
        const statusText = { pending: 'รออนุมัติ', approved: 'อนุมัติแล้ว', rejected: 'ถูกปฏิเสธ' }[pr.status] || pr.status || '-';
        const limitText = { low: 'วงเงินต่ำ', mid: 'วงเงินกลาง', high: 'วงเงินสูง' }[String(pr.budget_limit_type || '').toLowerCase()] || '';
        let html = STYLE + '<div class="prtl"><div class="prtl-head">' +
            `<span class="prtl-chip">${esc(statusText)}</span>` +
            `<span class="prtl-chip">฿${Number(pr.grand_total).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>` +
            (limitText ? `<span class="prtl-chip">${limitText}</span>` : '') +
            (pr.po_doc_no ? `<span class="prtl-chip">${esc(pr.po_doc_no)}</span>` : '') +
            '</div>';

        let prev = null;
        data.events.forEach((e) => {
            const [bg, fg] = COLORS[e.color] || COLORS.slate;
            html += `<div class="prtl-step done">
                <div class="prtl-dot" style="background:${bg};color:${fg};border-color:${fg}"><i class="fas ${esc(e.icon)}"></i></div>
                <div class="prtl-body">
                    <div class="prtl-title">${esc(e.title)}</div>
                    <div class="prtl-meta">${esc(fmtDate(e.at))}${e.actor_name ? ' · ' + esc(e.actor_name) : ''}${prev ? `<span class="prtl-gap">${gap(prev, e.at)}</span>` : ''}${e.source === 'record' ? '<span class="prtl-legacy" title="ข้อมูลจากระบบเดิม (ก่อนมีการบันทึกประวัติ)">•</span>' : ''}</div>
                    ${e.note ? `<div class="prtl-note" style="background:${bg};color:${fg}">${esc(e.note)}</div>` : ''}
                </div>
            </div>`;
            prev = e.at;
        });

        data.upcoming.forEach((u) => {
            html += `<div class="prtl-step ${esc(u.state)}">
                <div class="prtl-dot"><i class="fas ${esc(u.icon)}"></i></div>
                <div class="prtl-body"><div class="prtl-title">${esc(u.title)}</div>
                ${u.state === 'current' ? '<div class="prtl-meta">กำลังรอดำเนินการ</div>' : ''}</div>
            </div>`;
        });

        if (!data.upcoming.length && pr.status === 'approved') {
            html += `<div class="prtl-step done"><div class="prtl-dot" style="background:#ecfdf5;color:#059669;border-color:#059669"><i class="fas fa-flag-checkered"></i></div>
                <div class="prtl-body"><div class="prtl-title">เสร็จสิ้นกระบวนการ</div></div></div>`;
        }
        return html + '</div>';
    }

    window.showPRTimeline = async function (id, docNo) {
        Swal.fire({ title: docNo ? `Timeline ${esc(docNo)}` : 'Timeline', html: 'กำลังโหลด...', showConfirmButton: false, heightAuto: false, didOpen: () => Swal.showLoading() });
        try {
            const res = await fetch(`api/pr_timeline.php?id=${encodeURIComponent(id)}`);
            const data = await res.json();
            if (data.status !== 'success') throw new Error(data.message || 'โหลดไม่สำเร็จ');
            Swal.fire({
                title: `<span style="font-size:20px">Timeline ${esc(data.pr.doc_no)}</span>`,
                html: render(data),
                width: 560,
                showConfirmButton: true,
                confirmButtonText: 'ปิด',
                showDenyButton: true,
                denyButtonText: 'เปิดใบขอซื้อ',
                denyButtonColor: '#4f46e5',
                heightAuto: false,
            }).then((r) => { if (r.isDenied) window.location.href = `view_pr_new.php?id=${data.pr.id}`; });
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'โหลด timeline ไม่สำเร็จ', text: err.message, heightAuto: false });
        }
    };
})();
