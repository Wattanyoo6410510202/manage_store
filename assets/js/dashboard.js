(function () {
  const D = window.DASHBOARD_DATA;
  if (!D) return;

  const R = D.range;
  const COLORS = ['#6366f1', '#10b981', '#f59e0b', '#f43f5e', '#0ea5e9', '#8b5cf6', '#14b8a6', '#f97316', '#ec4899', '#64748b'];
  const STATUS_COLORS = { 'อนุมัติแล้ว': '#10b981', 'รออนุมัติ': '#f59e0b', 'ไม่อนุมัติ': '#f43f5e', 'ยกเลิก': '#94a3b8', 'ไม่ระบุ': '#cbd5e1' };
  const THAI_MONTHS = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

  // ── ตัวช่วยจัดรูปแบบ ─────────────────────────────────────
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const money = (v) => '฿' + Number(v || 0).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const num = (v, digits = 0) => Number(v || 0).toLocaleString('th-TH', { minimumFractionDigits: digits, maximumFractionDigits: digits });
  const compact = (v) => '฿' + Number(v || 0).toLocaleString('th-TH', { notation: 'compact', maximumFractionDigits: 1 });
  function thaiDate(value, withTime) {
    if (!value) return '-';
    const [d, t] = String(value).split(' ');
    const [y, m, day] = d.split('-').map(Number);
    if (!y || !m) return '-';
    const text = `${day} ${THAI_MONTHS[m - 1]} ${y + 543}`;
    return withTime && t ? `${text} ${t.slice(0, 5)}` : text;
  }
  const inPeriod = (row) => row.created_at >= R.start;
  const periodPr = D.prRows.filter(inPeriod);
  const periodPo = D.poRows.filter(inPeriod);
  const activePr = periodPr.filter((r) => r.status !== 'cancelled');
  const approvedPr = periodPr.filter((r) => r.status === 'approved');
  const activePo = periodPo.filter((r) => r.status !== 'cancelled');
  const sum = (rows, key = 'grand_total') => rows.reduce((total, r) => total + Number(r[key] || 0), 0);
  const label = (v) => (v === null || v === undefined || String(v).trim() === '' ? 'ไม่ระบุ' : String(v));
  const approvalDays = (r) => (r.approved_at ? Math.max(0, (new Date(r.approved_at.replace(' ', 'T')) - new Date(r.created_at.replace(' ', 'T'))) / 86400000) : null);

  // ── คอลัมน์มาตรฐาน (ใช้ทั้งหน้าต่างรายละเอียดและรายงาน) ────────
  const COL = {
    prDoc: { key: 'doc_no', label: 'เลขที่ PR', type: 'link', href: (r) => `view_pr_new.php?id=${r.id}` },
    poDoc: { key: 'doc_no', label: 'เลขที่ PO', type: 'link', href: (r) => `view_po.php?id=${r.id}` },
    express: { key: 'express_ref_code', label: 'Express REF.' },
    created: { key: 'created_at', label: 'วันที่สร้าง', type: 'date' },
    company: { key: 'company', label: 'บริษัท/หน่วยงาน' },
    vendor: { key: 'vendor', label: 'ผู้ขาย (Vendor)' },
    category: { key: 'category', label: 'หมวดค่าใช้จ่าย' },
    budget: { key: 'budget', label: 'ประเภทงบ' },
    item: { key: 'first_item', label: 'รายการแรก' },
    creator: { key: 'creator', label: 'ผู้สร้าง' },
    total: { key: 'grand_total', label: 'มูลค่า (บาท)', type: 'money' },
    prStatus: { key: 'status_label', label: 'สถานะ', type: 'badge' },
    received: { key: 'received_label', label: 'การรับของ' },
    wait: { key: 'wait_days', label: 'รอมาแล้ว (วัน)', type: 'num' },
  };
  const PR_COLUMNS = [COL.prDoc, COL.created, COL.company, COL.vendor, COL.category, COL.item, COL.total, COL.prStatus, COL.received];
  const PO_COLUMNS = [COL.poDoc, COL.express, COL.created, COL.company, COL.vendor, { key: 'reference_no', label: 'อ้างอิง PR' }, COL.total, COL.prStatus];

  function cellHtml(col, row) {
    const v = row[col.key];
    switch (col.type) {
      case 'money': return `<td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold tabular-nums text-slate-800">${money(v)}</td>`;
      case 'num': return `<td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums">${v === null || v === undefined ? '-' : num(v, row.digits ?? col.digits ?? 0)}</td>`;
      case 'date': return `<td class="whitespace-nowrap px-4 py-2.5 text-slate-600">${thaiDate(v)}</td>`;
      case 'link': return `<td class="whitespace-nowrap px-4 py-2.5"><a class="font-bold text-indigo-600 hover:underline" href="${esc(col.href(row))}" target="_blank" rel="noopener">${esc(v || '-')}</a></td>`;
      case 'badge': {
        const color = { 'อนุมัติแล้ว': 'bg-emerald-100 text-emerald-800', 'รออนุมัติ': 'bg-amber-100 text-amber-800', 'ไม่อนุมัติ': 'bg-rose-100 text-rose-800' }[v] || 'bg-slate-100 text-slate-600';
        return `<td class="px-4 py-2.5"><span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-bold ${color}">${esc(v || '-')}</span></td>`;
      }
      default: return `<td class="max-w-[280px] truncate px-4 py-2.5 text-slate-700" title="${esc(v)}">${esc(label(v))}</td>`;
    }
  }
  const headHtml = (columns) => `<tr>${columns.map((c) => `<th class="whitespace-nowrap px-4 py-3 ${['money', 'num'].includes(c.type) ? 'text-right' : 'text-left'}">${esc(c.label)}</th>`).join('')}</tr>`;
  const rowsHtml = (columns, rows) => rows.map((r) => `<tr class="hover:bg-slate-50">${columns.map((c) => cellHtml(c, r)).join('')}</tr>`).join('');

  // ── Excel ───────────────────────────────────────────────
  function sheetFor(title, columns, rows) {
    const aoa = [
      [title],
      ['ช่วงเวลา', R.label, '', 'บริษัท/หน่วยงาน', R.company],
      ['ข้อมูล ณ', D.generatedAt],
      [],
      columns.map((c) => c.label),
      ...rows.map((r) => columns.map((c) => {
        const v = r[c.key];
        if (c.type === 'money' || c.type === 'num') return v === null || v === undefined ? '' : Number(v);
        if (c.type === 'date') return thaiDate(v);
        return v === null || v === undefined ? '' : String(v);
      })),
    ];
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    ws['!cols'] = columns.map((c) => ({ wch: c.type === 'money' ? 16 : c.type === 'num' ? 12 : 22 }));
    columns.forEach((c, ci) => {
      if (c.type !== 'money') return;
      for (let ri = 5; ri < aoa.length; ri++) {
        const ref = XLSX.utils.encode_cell({ r: ri, c: ci });
        if (ws[ref] && typeof ws[ref].v === 'number') ws[ref].z = '#,##0.00';
      }
    });
    return ws;
  }
  const safeSheetName = (name) => name.replace(/[\\/?*[\]:]/g, ' ').slice(0, 31);
  const fileSuffix = () => `${R.start}_${R.period}`;
  function downloadSheets(filename, sheets) {
    if (typeof XLSX === 'undefined') {
      console.error('[dashboard] ไม่สามารถโหลดไลบรารี Excel ได้');
      return;
    }
    const wb = XLSX.utils.book_new();
    sheets.forEach((s) => XLSX.utils.book_append_sheet(wb, sheetFor(s.title, s.columns, s.rows), safeSheetName(s.sheet || s.title)));
    XLSX.writeFile(wb, filename);
  }

  // ── คำอธิบายแต่ละตัวเลข/กราฟ ───────────────────────────────
  const EXPLAIN = {
    pr: 'มูลค่ารวมของ <b>ใบขอซื้อ (PR)</b> ทุกใบที่สร้างในช่วงเวลาที่เลือก ไม่ว่าจะอนุมัติแล้วหรือยัง (ไม่นับใบที่ยกเลิก/ลบ) — บอกว่า "มีความต้องการซื้อเท่าไร"',
    po: 'มูลค่ารวมของ <b>ใบสั่งซื้อ (PO)</b> ที่ออกให้ผู้ขายในช่วงเวลาที่เลือก (ทั้งที่อนุมัติแล้วและรออนุมัติ) — บอกว่า "สั่งของจริงไปเท่าไร" PO ส่วนใหญ่สร้างจาก PR จึง<b>ไม่ควรนำยอด PR กับ PO มาบวกกัน</b>',
    approved: 'มูลค่ารวมของ PR ที่ <b>ผ่านการอนุมัติครบทุกขั้นแล้ว</b> ในช่วงเวลาที่เลือก — ใช้เป็นยอด "ค่าใช้จ่ายที่ได้รับอนุมัติ" และเป็นฐานของกราฟหมวดค่าใช้จ่าย/หน่วยงาน',
    approval_time: 'ระยะเวลาเฉลี่ยตั้งแต่ <b>สร้าง PR จนอนุมัติขั้นสุดท้าย</b> คิดจาก PR ที่สร้างในช่วงนี้และอนุมัติแล้ว ยิ่งน้อยยิ่งดี — ถ้าสูงขึ้นแปลว่าเอกสารค้างที่ผู้อนุมัติ',
    pending: 'เอกสารที่ <b>ยังรอผู้อนุมัติ ณ ตอนนี้</b> (นับทุกใบที่ค้าง ไม่ขึ้นกับช่วงเวลาที่เลือก) คอลัมน์ "รอใคร" บอกขั้นที่เอกสารค้างอยู่',
    receive: 'PR ที่ <b>อนุมัติแล้วแต่ยังรับของไม่ครบ ณ ตอนนี้</b> (ไม่ขึ้นกับช่วงเวลาที่เลือก) ถ้าเลยวันที่ต้องการสินค้าจะถูกนับเป็น "เลยกำหนด"',
    trend: 'แท่งสีม่วง = มูลค่า PR, แท่งสีเขียว = มูลค่า PO ที่สร้างในแต่ละวัน/เดือน (ไม่นับเอกสารที่ยกเลิก) ช่วยดูว่าช่วงไหนมีการซื้อมาก <b>คลิกที่แท่ง</b> เพื่อดูเอกสารของวันนั้น',
    status: 'สัดส่วนจำนวน PR ที่สร้างในช่วงนี้ แยกตามสถานะการอนุมัติ <b>คลิกที่ชิ้นกราฟ</b> เพื่อดูรายการ',
    category: 'มูลค่า PR <b>ที่อนุมัติแล้ว</b> แยกตามหมวดค่าใช้จ่ายที่ผู้ขอเลือกไว้ ช่วยดูว่าเงินไปลงที่หมวดไหนมากที่สุด',
    company: 'มูลค่า PR <b>ที่อนุมัติแล้ว</b> แยกตามบริษัท/หน่วยงานที่ขอซื้อ',
    vendor: 'มูลค่า PO แยกตาม <b>ผู้ขาย (Vendor)</b> 10 อันดับแรก ช่วยดูว่าซื้อกับใครมากที่สุด',
    budget: 'งบประมาณเป็น <b>วงเงินทั้งปี</b> (รวมรายการปรับงบที่อนุมัติแล้ว) จึงเทียบกับยอด PR ที่อนุมัติในปีเดียวกันเสมอ ไม่ว่าจะเลือกดูรายวันหรือรายเดือน สีเขียว = ใช้ไม่ถึง 75%, เหลือง = 75–90%, แดง = เกิน 90%',
    backlog: 'รวมเอกสารรออนุมัติ (PR/PO) และรอรับของ ณ ตอนนี้ เรียงจากเอกสารที่รอนานที่สุด เพื่อให้ตามงานได้ถูกจุด',
  };

  // ── หน้าต่างรายละเอียด ─────────────────────────────────────
  const modal = document.getElementById('detailModal');
  let detailExport = null;
  function openDetail(spec) {
    document.getElementById('detailTitle').textContent = spec.title;
    document.getElementById('detailSubtitle').textContent = spec.subtitle || `${R.label} · ${R.company}`;
    document.getElementById('detailExplain').innerHTML = spec.explain || '';
    document.getElementById('detailStats').innerHTML = (spec.stats || []).map(([k, v]) =>
      `<div class="rounded-2xl border border-slate-100 p-3"><p class="text-xs text-slate-500">${esc(k)}</p><p class="mt-0.5 text-lg font-black text-slate-900">${esc(v)}</p></div>`).join('');
    const wrap = document.getElementById('detailTableWrap');
    wrap.classList.toggle('hidden', !spec.columns);
    if (spec.columns) {
      document.getElementById('detailCount').textContent = `${num(spec.rows.length)} รายการ`;
      document.getElementById('detailHead').innerHTML = headHtml(spec.columns);
      document.getElementById('detailBody').innerHTML = spec.rows.length
        ? rowsHtml(spec.columns, spec.rows)
        : `<tr><td colspan="${spec.columns.length}" class="px-4 py-10 text-center text-slate-400">ไม่มีเอกสารในช่วงนี้</td></tr>`;
      detailExport = () => downloadSheets(`${spec.title}_${fileSuffix()}.xlsx`, [{ title: spec.title, sheet: 'รายละเอียด', columns: spec.columns, rows: spec.rows }]);
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.querySelector('[data-close-modal]').focus();
  }
  function closeDetail() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
  modal.addEventListener('click', (e) => { if (e.target === modal || e.target.closest('[data-close-modal]')) closeDetail(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeDetail(); });
  document.getElementById('detailExport').addEventListener('click', () => detailExport && detailExport());

  const s = D.summary;
  const PENDING_COLUMNS = [{ key: 'type', label: 'ประเภท' }, { key: 'doc_no', label: 'เลขที่', type: 'link', href: (r) => r.href }, COL.created, COL.company, { key: 'next_approver', label: 'รอใคร' }, COL.total, COL.wait];
  const RECEIVE_COLUMNS = [COL.prDoc, { key: 'approved_at', label: 'วันที่อนุมัติ', type: 'date' }, { key: 'due_date', label: 'ต้องการสินค้า', type: 'date' }, COL.company, COL.vendor, COL.total, COL.wait, { key: 'late_label', label: 'สถานะ' }];
  const pendingRows = () => [
    ...D.backlog.pending_pr.map((r) => ({ ...r, type: 'ใบขอซื้อ (PR)', href: `view_pr_new.php?id=${r.id}` })),
    ...D.backlog.pending_po.map((r) => ({ ...r, type: 'ใบสั่งซื้อ (PO)', next_approver: 'ผู้อนุมัติ PO', href: `view_po.php?id=${r.id}` })),
  ].sort((a, b) => b.wait_days - a.wait_days);
  const receiveRows = () => D.backlog.waiting_receive.map((r) => ({ ...r, late_label: Number(r.is_late) ? 'เลยกำหนด' : 'ปกติ' }));

  const DETAILS = {
    'kpi:pr': () => ({ title: 'ยอดขอซื้อ (PR)', explain: EXPLAIN.pr, columns: PR_COLUMNS, rows: activePr,
      stats: [['มูลค่ารวม', money(s.pr_value)], ['จำนวน', `${num(s.pr_count)} ใบ`], ['อนุมัติแล้ว', `${num(s.pr_approved_count)} ใบ`], ['รอ/ไม่อนุมัติ', `${num(s.pr_pending_count)} / ${num(s.pr_rejected_count)}`]] }),
    'kpi:po': () => ({ title: 'ยอดสั่งซื้อ (PO)', explain: EXPLAIN.po, columns: PO_COLUMNS, rows: activePo,
      stats: [['มูลค่ารวม', money(s.po_value)], ['จำนวน', `${num(s.po_count)} ใบ`], ['อนุมัติแล้ว', money(s.po_approved_value)], [`${R.prev_label}`, money(D.prev.po_value)]] }),
    'kpi:approved': () => ({ title: 'ขอซื้อที่อนุมัติแล้ว', explain: EXPLAIN.approved, columns: PR_COLUMNS, rows: approvedPr,
      stats: [['มูลค่า', money(s.pr_approved_value)], ['จำนวน', `${num(s.pr_approved_count)} ใบ`], ['อัตราอนุมัติ', s.pr_count ? `${num(s.pr_approved_count / s.pr_count * 100)}%` : '-'], [`${R.prev_label}`, money(D.prev.pr_approved_value)]] }),
    'kpi:approval_time': () => ({ title: 'เวลาอนุมัติเฉลี่ย', explain: EXPLAIN.approval_time,
      columns: [COL.prDoc, COL.created, { key: 'approved_at', label: 'อนุมัติเมื่อ', type: 'date' }, { key: 'days', label: 'ใช้เวลา (วัน)', type: 'num', digits: 1 }, COL.company, COL.total],
      rows: approvedPr.filter((r) => r.approved_at).map((r) => ({ ...r, days: approvalDays(r) })).sort((a, b) => b.days - a.days),
      stats: [['เฉลี่ย', s.approval_days_avg === null ? '-' : `${num(s.approval_days_avg, 1)} วัน`], [`${R.prev_label}`, D.prev.approval_days_avg === null ? '-' : `${num(D.prev.approval_days_avg, 1)} วัน`]] }),
    'kpi:pending': () => ({ title: 'เอกสารรออนุมัติ (ตอนนี้)', subtitle: `ณ ปัจจุบัน · ${R.company}`, explain: EXPLAIN.pending, columns: PENDING_COLUMNS, rows: pendingRows(),
      stats: [['PR รออนุมัติ', `${num(D.backlog.pending_pr.length)} ใบ`], ['PO รออนุมัติ', `${num(D.backlog.pending_po.length)} ใบ`], ['มูลค่ารวม', money(sum(pendingRows()))]] }),
    'kpi:receive': () => ({ title: 'รอรับของ (ตอนนี้)', subtitle: `ณ ปัจจุบัน · ${R.company}`, explain: EXPLAIN.receive, columns: RECEIVE_COLUMNS, rows: receiveRows(),
      stats: [['จำนวน', `${num(D.backlog.waiting_receive.length)} ใบ`], ['เลยกำหนด', `${num(receiveRows().filter((r) => r.late_label === 'เลยกำหนด').length)} ใบ`], ['มูลค่ารวม', money(sum(D.backlog.waiting_receive))]] }),
  };

  function groupDetail(kind, value) {
    const map = {
      status: { rows: activePr.filter((r) => label(r.status_label) === value), columns: PR_COLUMNS, title: `PR สถานะ: ${value}` },
      category: { rows: approvedPr.filter((r) => label(r.category) === value), columns: PR_COLUMNS, title: `หมวดค่าใช้จ่าย: ${value}` },
      company: { rows: approvedPr.filter((r) => label(r.company) === value), columns: PR_COLUMNS, title: `บริษัท/หน่วยงาน: ${value}` },
      vendor: { rows: activePo.filter((r) => label(r.vendor) === value), columns: PO_COLUMNS, title: `ผู้ขาย: ${value}` },
    }[kind];
    openDetail({ ...map, explain: EXPLAIN[kind], stats: [['มูลค่ารวม', money(sum(map.rows))], ['จำนวน', `${num(map.rows.length)} ใบ`]] });
  }

  function trendDetail(point) {
    const match = (r) => r.created_at.startsWith(point.key) && r.status !== 'cancelled';
    const rows = [
      ...D.prRows.filter(match).map((r) => ({ ...r, type: 'PR', href: `view_pr_new.php?id=${r.id}` })),
      ...D.poRows.filter(match).map((r) => ({ ...r, type: 'PO', href: `view_po.php?id=${r.id}` })),
    ];
    openDetail({
      title: `เอกสารวันที่/เดือน ${point.label}`, explain: EXPLAIN.trend,
      columns: [{ key: 'type', label: 'ประเภท' }, { key: 'doc_no', label: 'เลขที่', type: 'link', href: (r) => r.href }, COL.created, COL.company, COL.vendor, COL.total, COL.prStatus],
      rows, stats: [['มูลค่า PR', money(point.pr_value)], ['จำนวน PR', `${num(point.pr_count)} ใบ`], ['มูลค่า PO', money(point.po_value)], ['จำนวน PO', `${num(point.po_count)} ใบ`]],
    });
  }

  function budgetDetail(name) {
    const b = D.budgets.find((x) => x.name === name);
    if (!b) return;
    openDetail({
      title: `งบประมาณ: ${b.name}`, subtitle: `ปี ${R.year + 543} · ${R.company}`, explain: EXPLAIN.budget,
      stats: [['งบทั้งปี', money(b.total_budget)], ['ใช้ไปแล้ว', money(b.spent)], ['คงเหลือ', money(b.remaining)], ['ใช้ไป', b.used_pct === null ? '-' : `${num(b.used_pct, 1)}%`]],
    });
  }

  document.addEventListener('click', (e) => {
    const explain = e.target.closest('[data-explain]');
    if (explain) {
      e.stopPropagation();
      const key = explain.dataset.explain;
      openDetail({ title: explain.closest('.dash-card').querySelector('h2').textContent, explain: EXPLAIN[key] });
      return;
    }
    const trigger = e.target.closest('[data-detail]');
    if (!trigger) return;
    const key = trigger.dataset.detail;
    if (key.startsWith('budget:')) return budgetDetail(key.slice(7));
    if (DETAILS[key]) openDetail(DETAILS[key]());
  });
  document.addEventListener('keydown', (e) => {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-detail][tabindex]')) {
      e.preventDefault();
      e.target.click();
    }
  });

  // ── กราฟ ────────────────────────────────────────────────
  if (typeof Chart !== 'undefined') {
    Chart.defaults.font.family = "'Sarabun', sans-serif";
    Chart.defaults.color = '#64748b';
    const moneyTooltip = (ctx) => ` ${ctx.dataset.label || ctx.label}: ${money(ctx.parsed.y ?? ctx.parsed.x ?? ctx.parsed)}`;
    const empty = (id, text) => {
      const canvas = document.getElementById(id);
      canvas.parentElement.innerHTML = `<div class="flex h-full flex-col items-center justify-center text-slate-300"><i class="fas fa-chart-simple mb-2 text-3xl"></i><p class="text-sm font-semibold">${text}</p></div>`;
    };

    new Chart(document.getElementById('trendChart'), {
      type: 'bar',
      data: {
        labels: D.trend.map((p) => p.label),
        datasets: [
          { label: 'ขอซื้อ (PR)', data: D.trend.map((p) => p.pr_value), backgroundColor: '#6366f1', borderRadius: 6, maxBarThickness: 28 },
          { label: 'สั่งซื้อ (PO)', data: D.trend.map((p) => p.po_value), backgroundColor: '#10b981', borderRadius: 6, maxBarThickness: 28 },
        ],
      },
      options: {
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } },
          tooltip: { callbacks: { label: moneyTooltip, afterBody: (items) => { const p = D.trend[items[0].dataIndex]; return `PR ${p.pr_count} ใบ · PO ${p.po_count} ใบ`; } } },
        },
        scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: (v) => compact(v) }, grid: { color: '#f1f5f9' } } },
        onClick: (_, els) => { if (els.length) trendDetail(D.trend[els[0].index]); },
        onHover: (e, els) => { e.native.target.style.cursor = els.length ? 'pointer' : 'default'; },
      },
    });

    function doughnut(id, groups, kind, valueOf, colorOf) {
      if (!groups.length) return empty(id, 'ไม่มีข้อมูลในช่วงนี้');
      new Chart(document.getElementById(id), {
        type: 'doughnut',
        data: { labels: groups.map((g) => g.label), datasets: [{ data: groups.map(valueOf), backgroundColor: groups.map((g, i) => colorOf(g, i)), borderWidth: 2, borderColor: '#fff', hoverOffset: 8 }] },
        options: {
          maintainAspectRatio: false, cutout: '62%',
          plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12 } },
            tooltip: { callbacks: { label: (ctx) => { const g = groups[ctx.dataIndex]; return ` ${g.label}: ${num(g.count)} ใบ · ${money(g.value)}`; } } },
          },
          onClick: (_, els) => { if (els.length) groupDetail(kind, groups[els[0].index].label); },
          onHover: (e, els) => { e.native.target.style.cursor = els.length ? 'pointer' : 'default'; },
        },
      });
    }
    function hbar(id, groups, kind, color) {
      const top = groups.slice(0, 10);
      if (!top.length) return empty(id, 'ไม่มีข้อมูลในช่วงนี้');
      new Chart(document.getElementById(id), {
        type: 'bar',
        data: { labels: top.map((g) => (g.label.length > 26 ? g.label.slice(0, 26) + '…' : g.label)), datasets: [{ label: 'มูลค่า', data: top.map((g) => g.value), backgroundColor: color, borderRadius: 6, maxBarThickness: 22 }] },
        options: {
          indexAxis: 'y', maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { title: (items) => top[items[0].dataIndex].label, label: (ctx) => ` ${money(top[ctx.dataIndex].value)} · ${num(top[ctx.dataIndex].count)} ใบ` } } },
          scales: { x: { beginAtZero: true, ticks: { callback: (v) => compact(v) }, grid: { color: '#f1f5f9' } }, y: { grid: { display: false } } },
          onClick: (_, els) => { if (els.length) groupDetail(kind, top[els[0].index].label); },
          onHover: (e, els) => { e.native.target.style.cursor = els.length ? 'pointer' : 'default'; },
        },
      });
    }

    doughnut('statusChart', D.groups.status, 'status', (g) => g.count, (g) => STATUS_COLORS[g.label] || '#cbd5e1');
    doughnut('categoryChart', D.groups.category, 'category', (g) => g.value, (g, i) => COLORS[i % COLORS.length]);
    hbar('companyChart', D.groups.company, 'company', '#6366f1');
    hbar('vendorChart', D.groups.vendor, 'vendor', '#0ea5e9');
  } else {
    console.error('[dashboard] ไม่สามารถโหลด Chart.js ได้');
  }

  // ── งานค้างนานสุด ──────────────────────────────────────────
  const backlogTop = [
    ...pendingRows().map((r) => ({ ...r, tag: `รออนุมัติ · ${r.next_approver}`, tone: 'bg-amber-100 text-amber-800' })),
    ...receiveRows().map((r) => ({ ...r, type: 'ใบขอซื้อ (PR)', href: `view_pr_new.php?id=${r.id}`, tag: r.late_label === 'เลยกำหนด' ? 'รอรับของ · เลยกำหนด' : 'รอรับของ', tone: r.late_label === 'เลยกำหนด' ? 'bg-rose-100 text-rose-800' : 'bg-sky-100 text-sky-800' })),
  ].sort((a, b) => b.wait_days - a.wait_days).slice(0, 6);
  document.getElementById('backlogList').innerHTML = backlogTop.length
    ? backlogTop.map((r) => `
      <a href="${esc(r.href)}" target="_blank" rel="noopener" class="flex items-center gap-3 py-3 hover:bg-slate-50">
        <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl ${r.wait_days > 7 ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-600'}">
          <span class="text-base font-black leading-none">${num(r.wait_days)}</span><span class="text-[10px]">วัน</span>
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-bold text-slate-800">${esc(r.doc_no)} <span class="font-normal text-slate-400">· ${esc(r.company || '')}</span></p>
          <span class="mt-0.5 inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold ${r.tone}">${esc(r.tag)}</span>
        </div>
        <span class="shrink-0 text-sm font-bold tabular-nums text-slate-700">${money(r.grand_total)}</span>
      </a>`).join('')
    : '<p class="py-8 text-center text-sm text-emerald-600"><i class="fas fa-circle-check mr-1"></i>ไม่มีงานค้าง</p>';

  // ── รายงาน ──────────────────────────────────────────────
  const groupColumns = (first) => [{ key: 'label', label: first }, { key: 'count', label: 'จำนวนเอกสาร', type: 'num' }, { key: 'value', label: 'มูลค่า (บาท)', type: 'money' }, { key: 'share', label: 'สัดส่วน (%)', type: 'num', digits: 1 }];
  const withShare = (groups) => { const total = groups.reduce((t, g) => t + g.value, 0); return groups.map((g) => ({ ...g, share: total ? (g.value / total) * 100 : 0 })); };
  const itemSummary = () => {
    const map = new Map();
    D.items.forEach((i) => {
      const key = `${label(i.item_desc)}|${label(i.item_unit)}`;
      const row = map.get(key) || { item_desc: label(i.item_desc), item_unit: label(i.item_unit), qty: 0, amount: 0, docs: new Set() };
      row.qty += i.qty; row.amount += i.amount; row.docs.add(i.doc_no);
      map.set(key, row);
    });
    return [...map.values()].map((r) => ({ ...r, pr_count: r.docs.size, avg_price: r.qty ? r.amount / r.qty : 0 })).sort((a, b) => b.amount - a.amount);
  };
  const prev = D.prev;
  const change = (a, b) => (b ? ((a - b) / b) * 100 : null);

  const REPORTS = [
    { key: 'summary', icon: 'fa-gauge-high', title: 'สรุปภาพรวม', desc: 'ตัวเลขหลักของช่วงเวลาที่เลือก เทียบกับช่วงก่อนหน้า',
      columns: [{ key: 'metric', label: 'หัวข้อ' }, { key: 'current', label: R.label, type: 'num', digits: 2 }, { key: 'previous', label: R.prev_label, type: 'num', digits: 2 }, { key: 'change', label: 'เปลี่ยนแปลง (%)', type: 'num', digits: 1 }],
      rows: () => [
        ['มูลค่าขอซื้อ PR (บาท)', s.pr_value, prev.pr_value], ['จำนวน PR (ใบ)', s.pr_count, prev.pr_count],
        ['มูลค่า PR ที่อนุมัติ (บาท)', s.pr_approved_value, prev.pr_approved_value], ['จำนวน PR ที่อนุมัติ (ใบ)', s.pr_approved_count, prev.pr_approved_count],
        ['จำนวน PR ไม่อนุมัติ (ใบ)', s.pr_rejected_count, prev.pr_rejected_count],
        ['มูลค่าสั่งซื้อ PO (บาท)', s.po_value, prev.po_value], ['จำนวน PO (ใบ)', s.po_count, prev.po_count],
        ['เวลาอนุมัติเฉลี่ย (วัน)', s.approval_days_avg, prev.approval_days_avg],
      ].map(([metric, current, previous]) => ({ metric, current, previous, digits: metric.includes('(ใบ)') ? 0 : metric.includes('(วัน)') ? 1 : 2, change: current === null || previous === null ? null : change(current, previous) })) },
    { key: 'pr', icon: 'fa-file-signature', title: 'ทะเบียนใบขอซื้อ (PR)', desc: 'PR ทุกใบที่สร้างในช่วงเวลา (รวมทุกสถานะ)',
      columns: [COL.prDoc, COL.created, COL.company, COL.vendor, COL.category, COL.budget, COL.item, { key: 'item_count', label: 'จำนวนรายการ', type: 'num' }, COL.total, COL.prStatus, { key: 'approved_at', label: 'อนุมัติเมื่อ', type: 'date' }, COL.received, COL.creator], rows: () => periodPr },
    { key: 'po', icon: 'fa-cart-shopping', title: 'ทะเบียนใบสั่งซื้อ (PO)', desc: 'PO ทุกใบที่สร้างในช่วงเวลา',
      columns: [...PO_COLUMNS, { key: 'due_date', label: 'ต้องการสินค้า', type: 'date' }, COL.creator], rows: () => periodPo },
    { key: 'items', icon: 'fa-boxes-stacked', title: 'สรุปสินค้าที่ซื้อ', desc: 'รวมจำนวนและมูลค่าสินค้าจาก PR ที่อนุมัติแล้ว (ถ้า PR ไม่มีราคา ใช้ราคาจาก PO)',
      columns: [{ key: 'item_desc', label: 'สินค้า/บริการ' }, { key: 'item_unit', label: 'หน่วย' }, { key: 'qty', label: 'จำนวนรวม', type: 'num', digits: 2 }, { key: 'avg_price', label: 'ราคาเฉลี่ย/หน่วย', type: 'money' }, { key: 'amount', label: 'มูลค่ารวม (บาท)', type: 'money' }, { key: 'pr_count', label: 'จำนวน PR', type: 'num' }], rows: itemSummary },
    { key: 'item_lines', icon: 'fa-list', title: 'รายการสินค้าแยกตามเอกสาร', desc: 'ทุกบรรทัดสินค้าใน PR ที่อนุมัติแล้ว',
      columns: [{ key: 'doc_no', label: 'เลขที่ PR' }, COL.created, COL.company, COL.category, COL.vendor, { key: 'item_desc', label: 'สินค้า/บริการ' }, { key: 'qty', label: 'จำนวน', type: 'num', digits: 2 }, { key: 'item_unit', label: 'หน่วย' }, { key: 'price', label: 'ราคา/หน่วย', type: 'money' }, { key: 'amount', label: 'มูลค่า (บาท)', type: 'money' }], rows: () => D.items },
    { key: 'category', icon: 'fa-tags', title: 'สรุปตามหมวดค่าใช้จ่าย', desc: 'PR ที่อนุมัติแล้ว แยกตามหมวด', columns: groupColumns('หมวดค่าใช้จ่าย'), rows: () => withShare(D.groups.category) },
    { key: 'company', icon: 'fa-building', title: 'สรุปตามบริษัท/หน่วยงาน', desc: 'PR ที่อนุมัติแล้ว แยกตามหน่วยงานผู้ขอ', columns: groupColumns('บริษัท/หน่วยงาน'), rows: () => withShare(D.groups.company) },
    { key: 'vendor', icon: 'fa-store', title: 'สรุปตามผู้ขาย (Vendor)', desc: 'PO แยกตามผู้ขาย', columns: groupColumns('ผู้ขาย'), rows: () => withShare(D.groups.vendor) },
    { key: 'timeline', icon: 'fa-calendar-days', title: R.bucket === 'month' ? 'สรุปรายเดือน' : 'สรุปรายวัน', desc: 'ยอด PR/PO แยกตามช่วงเวลา (ไม่นับเอกสารยกเลิก)',
      columns: [{ key: 'label', label: R.bucket === 'month' ? 'เดือน' : 'วันที่' }, { key: 'pr_count', label: 'จำนวน PR', type: 'num' }, { key: 'pr_value', label: 'มูลค่า PR', type: 'money' }, { key: 'po_count', label: 'จำนวน PO', type: 'num' }, { key: 'po_value', label: 'มูลค่า PO', type: 'money' }],
      rows: () => D.trend.filter((p) => p.key >= R.start.slice(0, p.key.length)) },
    { key: 'budget', icon: 'fa-wallet', title: `การใช้งบประมาณ ปี ${R.year + 543}`, desc: 'งบทั้งปีเทียบยอด PR ที่อนุมัติในปีเดียวกัน',
      columns: [{ key: 'name', label: 'ประเภทงบ' }, { key: 'total_budget', label: 'งบทั้งปี', type: 'money' }, { key: 'spent', label: 'ใช้ไปแล้ว', type: 'money' }, { key: 'remaining', label: 'คงเหลือ', type: 'money' }, { key: 'used_pct', label: 'ใช้ไป (%)', type: 'num', digits: 1 }], rows: () => D.budgets },
    { key: 'pending', icon: 'fa-hourglass-half', title: 'เอกสารรออนุมัติ (ตอนนี้)', desc: 'ณ ปัจจุบัน ไม่ขึ้นกับช่วงเวลา', columns: PENDING_COLUMNS, rows: pendingRows },
    { key: 'receive', icon: 'fa-truck-ramp-box', title: 'รอรับของ (ตอนนี้)', desc: 'ณ ปัจจุบัน ไม่ขึ้นกับช่วงเวลา', columns: RECEIVE_COLUMNS, rows: receiveRows },
  ];

  let currentReport = REPORTS[0];
  const menu = document.getElementById('reportMenu');
  menu.innerHTML = REPORTS.map((r) => `
    <button type="button" data-report="${r.key}" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50">
      <i class="fas ${r.icon} w-4 text-center text-slate-400"></i><span>${esc(r.title)}</span>
    </button>`).join('');
  function renderReport() {
    const r = currentReport;
    menu.querySelectorAll('[data-report]').forEach((b) => {
      const active = b.dataset.report === r.key;
      b.classList.toggle('bg-indigo-50', active);
      b.classList.toggle('text-indigo-700', active);
    });
    document.getElementById('reportTitle').textContent = r.title;
    document.getElementById('reportDesc').textContent = r.desc;
    const query = document.getElementById('reportSearch').value.trim().toLowerCase();
    const all = r.rows();
    const rows = query ? all.filter((row) => r.columns.some((c) => String(c.type === 'date' ? thaiDate(row[c.key]) : row[c.key] ?? '').toLowerCase().includes(query))) : all;
    document.getElementById('reportHead').innerHTML = headHtml(r.columns);
    document.getElementById('reportBody').innerHTML = rows.length
      ? rowsHtml(r.columns, rows)
      : `<tr><td colspan="${r.columns.length}" class="px-4 py-12 text-center text-slate-400">ไม่มีข้อมูลในช่วงนี้</td></tr>`;
    const moneyCol = r.columns.find((c) => c.type === 'money');
    document.getElementById('reportFoot').innerHTML = `แสดง ${num(rows.length)} จาก ${num(all.length)} แถว` + (moneyCol && r.key !== 'summary' ? ` · รวม${esc(moneyCol.label)} <b class="text-slate-800">${money(sum(rows, moneyCol.key))}</b>` : '');
  }
  menu.addEventListener('click', (e) => {
    const b = e.target.closest('[data-report]');
    if (!b) return;
    currentReport = REPORTS.find((r) => r.key === b.dataset.report);
    document.getElementById('reportSearch').value = '';
    renderReport();
  });
  document.getElementById('reportSearch').addEventListener('input', renderReport);
  document.getElementById('exportReport').addEventListener('click', () => {
    downloadSheets(`${currentReport.title}_${fileSuffix()}.xlsx`, [{ title: currentReport.title, columns: currentReport.columns, rows: currentReport.rows() }]);
  });
  document.getElementById('exportAll').addEventListener('click', () => {
    downloadSheets(`รายงานจัดซื้อ_${fileSuffix()}.xlsx`, REPORTS.map((r) => ({ title: r.title, columns: r.columns, rows: r.rows() })));
  });
  renderReport();

  // ── แท็บ + ตัวกรองช่วงเวลา ─────────────────────────────────
  const form = document.getElementById('dashFilter');
  function showTab(tab) {
    document.querySelectorAll('[data-tab]').forEach((b) => b.setAttribute('aria-selected', b.dataset.tab === tab ? 'true' : 'false'));
    document.querySelectorAll('[data-panel]').forEach((p) => p.classList.toggle('hidden', p.dataset.panel !== tab));
    form.elements.tab.value = tab;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    history.replaceState(null, '', url);
  }
  document.querySelectorAll('[data-tab]').forEach((b) => b.addEventListener('click', () => showTab(b.dataset.tab)));

  function showPeriodFields(period) {
    form.elements.period.value = period;
    document.querySelectorAll('[data-period]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.period === period ? 'true' : 'false'));
    form.querySelectorAll('[data-show-for]').forEach((el) => {
      const visible = el.dataset.showFor.split(' ').includes(period);
      el.classList.toggle('hidden', !visible);
      el.querySelectorAll('input, select').forEach((input) => { input.disabled = !visible; });
    });
  }
  document.querySelectorAll('[data-period]').forEach((b) => b.addEventListener('click', () => showPeriodFields(b.dataset.period)));
  showPeriodFields(R.period);
})();
