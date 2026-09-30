<?php // Modal แสดงใบขออนุมัติโครงการ ใช้ร่วมกันระหว่าง my_budget.php และ pending_budget.php ?>
<div id="projectDetailModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-3xl max-h-[92vh] overflow-y-auto">
        <div class="flex justify-between items-center p-5 border-b border-slate-100 sticky top-0 bg-white">
            <h3 class="font-bold text-slate-800">ใบขออนุมัติโครงการ</h3>
            <button type="button" onclick="closeProjectDetail()" class="text-slate-400 hover:text-slate-700"><i class="fas fa-times"></i></button>
        </div>
        <div id="projectDetailBody" class="p-5 text-sm"></div>
        <div id="projectDetailActions" class="flex justify-end gap-2 px-5 pb-5"></div>
    </div>
</div>

<script>
function budgetProjectStatusBadge(item) {
    if (item.status === 'rejected') {
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 border border-red-200"><i class="fas fa-times-circle mr-1"></i> ไม่อนุมัติ</span>`;
    } else if (item.status === 'approved') {
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200"><i class="fas fa-check-circle mr-1"></i> อนุมัติแล้ว</span>`;
    } else if (item.approved_by_gmacc) {
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200"><i class="fas fa-user-check mr-1"></i> บัญชีอนุมัติแล้ว</span>`;
    } else if (item.approved_by_mgr) {
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200"><i class="fas fa-user-check mr-1"></i> MGR อนุมัติแล้ว</span>`;
    }
    return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-200"><i class="fas fa-clock mr-1"></i> รออนุมัติ</span>`;
}

function viewProject(id, actionsHtml) {
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const money = n => Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const lines = s => esc(s).replace(/\n/g, '<br>') || '-';
    const units = {day: 'วัน', month: 'เดือน', year: 'ปี'};

    fetch('my_budget.php?action=detail&id=' + encodeURIComponent(id))
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') {
                Swal.fire({icon: 'error', title: 'ไม่สำเร็จ', text: res.msg});
                return;
            }
            const p = res.project;
            const row = (label, value) => `<div class="flex gap-3 py-1.5 border-b border-slate-100"><div class="w-36 shrink-0 text-slate-500">${label}</div><div class="flex-1 text-slate-800">${value}</div></div>`;
            const items = (p.items || []).map(it => `
                <tr class="border-t border-slate-100">
                    <td class="p-2 text-center">${esc(it.line_no)}</td>
                    <td class="p-2">${esc(it.description) || '-'}</td>
                    <td class="p-2 text-right font-mono">${money(it.amount)}</td>
                </tr>`).join('');

            document.getElementById('projectDetailBody').innerHTML = `
                <div class="flex flex-wrap justify-between items-start gap-2 mb-3">
                    <div>
                        <div class="font-bold text-slate-800">${esc(p.company_name)}</div>
                        <div class="text-xs text-slate-500">${p.project_type === 'additional' ? '☑ โครงการเพิ่มเติม' : '☑ โครงการใหม่'}
                            ${p.parent_project_no || p.parent_name ? ` (ต่อจาก ${esc(p.parent_project_no || '')} ${esc(p.parent_name || '')})` : ''}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-slate-500">วันที่ ${esc(p.request_date)}</div>
                        <div class="mt-1">${budgetProjectStatusBadge(p)}</div>
                    </div>
                </div>
                ${row('แผนก / ฝ่าย', `${esc(p.department) || '-'} / ${esc(p.division) || '-'}`)}
                ${row('เลขที่โครงการ', esc(p.project_no) || '-')}
                ${row('ชื่อโครงการ', `<b>${esc(p.name)}</b>`)}
                ${row('อ้างอิงงบประมาณ', esc(p.budget_name))}
                ${row('ผู้รับผิดชอบ', esc(p.responsible_name) || '-')}
                ${row('วัตถุประสงค์', lines(p.objectives))}
                ${row('ระยะเวลาดำเนินการ', p.duration_value ? `${esc(p.duration_value)} ${units[p.duration_unit] || ''}` : '-')}
                ${row('ค่าใช้จ่ายตลอดโครงการ', `<b class="text-indigo-600 font-mono">${money(p.amount)}</b> บาท`)}
                <table class="w-full border border-slate-200 mt-3 text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-2 w-14">ลำดับ</th><th class="p-2 text-left">รายละเอียดของโครงการ</th><th class="p-2 w-36 text-right">ราคา (บาท)</th></tr></thead>
                    <tbody>${items || '<tr><td colspan="3" class="p-3 text-center text-slate-400">-</td></tr>'}</tbody>
                    <tfoot class="bg-slate-50 font-bold"><tr><td colspan="2" class="p-2 text-right">รวมค่าใช้จ่าย</td><td class="p-2 text-right font-mono">${money(p.amount)}</td></tr></tfoot>
                </table>
                <div class="mt-3">${row('ผลที่คาดว่าจะได้รับ', lines(p.expected_results))}</div>
                ${row('ไฟล์แนบ', p.file_path ? `<a href="${esc(p.file_path)}" target="_blank" class="text-indigo-600 hover:underline"><i class="fas fa-paperclip mr-1"></i>เปิดไฟล์</a>` : '-')}
                ${p.status === 'approved' || (p.prs || []).length ? row('ใช้เงินโครงการ (PR)', `
                    <div>ใช้ไป <b class="font-mono">${money(p.pr_used)}</b> · คงเหลือ <b class="font-mono ${p.amount - p.pr_used < 0 ? 'text-red-600' : 'text-emerald-600'}">${money(p.amount - p.pr_used)}</b> บาท</div>
                    ${(p.prs || []).map(pr => `
                        <div class="flex flex-wrap gap-2 text-xs py-0.5">
                            <a href="view_pr_new.php?id=${encodeURIComponent(pr.id)}" target="_blank" class="text-indigo-600 font-bold hover:underline">${esc(pr.doc_no)}</a>
                            <span class="text-slate-400">${esc(pr.doc_date)} · ${esc(pr.creator_name) || '-'}</span>
                            <span class="font-mono">${money(pr.grand_total)}</span>
                            <span class="${pr.status === 'rejected' ? 'text-red-500 line-through' : (pr.status === 'approved' ? 'text-emerald-600' : 'text-amber-600')}">${esc(pr.status || 'pending')}</span>
                        </div>`).join('') || '<div class="text-xs text-slate-400">ยังไม่มี PR</div>'}`) : ''}
                ${row('ผู้ขออนุมัติ', `${esc(p.creator_name) || '-'} <span class="text-xs text-slate-400">${esc(p.created_at)}</span>`)}
                ${Object.keys(p.signers || {}).length ? row('ผู้ลงนาม', Object.values(p.signers).map(s => `
                    <div class="flex flex-wrap items-center gap-2 py-0.5">
                        <span class="w-40 text-slate-500">${esc(s.label)}</span>
                        <span class="font-bold">${esc(s.user_name) || '-'}</span>
                        ${s.signed_at
                            ? `<span class="text-[10px] font-bold ${s.decision === 'rejected' ? 'text-red-600' : 'text-emerald-600'}">✓ ${s.decision === 'rejected' ? 'ไม่อนุมัติ' : (s.decision === 'approved' ? 'อนุมัติ' : 'เซ็นแล้ว')} ${esc(s.signed_at)}</span>`
                            : '<span class="text-[10px] text-slate-400">ยังไม่เซ็น</span>'}
                    </div>`).join('')) : ''}
                ${row('บัญชีอนุมัติ', p.gmacc_name ? `${esc(p.gmacc_name)} <span class="text-xs text-slate-400">${esc(p.approved_at_gmacc)}</span>` : '-')}
                ${row('ผู้จัดการอนุมัติ', p.mgr_name ? `${esc(p.mgr_name)} <span class="text-xs text-slate-400">${esc(p.approved_at_mgr)}</span>` : '-')}
                ${p.status === 'rejected' ? row('ไม่อนุมัติโดย', `${esc(p.rejected_by_name) || '-'} <span class="text-xs text-slate-400">${esc(p.rejected_at)}</span><div class="text-red-600">${lines(p.reject_reason)}</div>`) : ''}
            `;
            const printBtn = `<a href="budget_project_print.php?id=${encodeURIComponent(p.id)}" target="_blank" class="px-4 py-2 rounded-xl text-sm font-bold bg-indigo-50 text-indigo-600 hover:bg-indigo-100 mr-auto"><i class="fas fa-print mr-1"></i>${Object.keys(p.signers || {}).length ? 'พิมพ์ / เซ็น' : 'พิมพ์'}</a>`;
            document.getElementById('projectDetailActions').innerHTML = printBtn + (typeof actionsHtml === 'function' ? actionsHtml(p) : '');
            document.getElementById('projectDetailModal').classList.remove('hidden');
        });
}

function closeProjectDetail() {
    document.getElementById('projectDetailModal').classList.add('hidden');
}
</script>
