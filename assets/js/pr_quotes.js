/**
 * ใบเทียบราคาในฟอร์ม PR
 * - ราคาของแต่ละร้านเก็บเป็น hidden input ในแต่ละแถวของ #itemsTable (quote_price[slot][])
 *   แถวถูกเพิ่ม/ลบ ราคาก็ไปด้วยกัน ลำดับจึงตรงกับ item_desc[] เสมอ
 * - ร้านที่เลือกจะถูกคัดลอกราคาไปที่ item_price[] และ store_id ของ PR
 */
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('prQuotePanel');
    const itemsTable = document.getElementById('itemsTable');
    if (!panel || !itemsTable) return;

    const form = panel.closest('form');
    const config = JSON.parse(panel.dataset.config || '{}');
    const MAX = config.maxStores || 3;
    const MIN = config.minStores || 2;
    const REQUIRED_ABOVE = config.requiredAbove || 10000;

    const toggle = document.getElementById('prQuoteToggle');
    const body = document.getElementById('prQuoteBody');
    const table = document.getElementById('prQuoteTable');
    const reasonWrap = document.getElementById('prQuoteReasonWrap');
    const reasonInput = document.getElementById('prQuoteReason');
    const requiredBadge = document.getElementById('prQuoteRequiredBadge');
    const addSlotBtn = document.getElementById('prQuoteAddSlot');
    const slotEls = Array.from(panel.querySelectorAll('.prq-slot'));

    let stores = (config.stores || []).slice();
    let busy = false;

    const fmt = (n) => (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (v) => parseFloat(v) || 0;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const rows = () => Array.from(itemsTable.querySelectorAll('tbody tr.item-row'));
    const isEnabled = () => toggle.checked;
    const activeSlots = () => slotEls.filter((el) => !el.classList.contains('hidden')).map((el) => Number(el.dataset.slot));
    const slotStoreId = (slot) => num(slotEls[slot].querySelector('.prq-store').value);
    const storeName = (id) => (stores.find((s) => s.id === Number(id)) || {}).name || '';

    function ensureRowInputs(row) {
        let holder = row.querySelector('.prq-hidden');
        if (!holder) {
            holder = document.createElement('span');
            holder.className = 'prq-hidden';
            holder.hidden = true;
            (row.lastElementChild || row).appendChild(holder);
        }
        if (!holder.querySelector('.prq-ref-hidden')) {
            const ref = document.createElement('input');
            ref.type = 'hidden';
            ref.name = 'quote_ref_price[]';
            ref.className = 'prq-ref-hidden';
            holder.appendChild(ref);
        }
        for (let slot = 0; slot < MAX; slot++) {
            if (!holder.querySelector(`[data-slot="${slot}"]`)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `quote_price[${slot}][]`;
                input.dataset.slot = slot;
                input.value = '';
                holder.appendChild(input);
            }
        }
        return holder;
    }
    const priceInput = (row, slot) => ensureRowInputs(row).querySelector(`[data-slot="${slot}"]`);
    const refInput = (row) => ensureRowInputs(row).querySelector('.prq-ref-hidden');

    function vatWht() {
        const vat = document.querySelector('select[name="vat_percent"]');
        const wht = document.querySelector('select[name="wht_percent"]');
        return { vat: vat ? num(vat.value) : 0, wht: wht ? num(wht.value) : 0 };
    }

    function rowInfo(row) {
        const desc = row.querySelector('[name="item_desc[]"]');
        const qty = row.querySelector('[name="item_qty[]"]');
        const unit = row.querySelector('[name="item_unit[]"]');
        const disc = row.querySelector('[name="item_discount[]"]');
        return {
            desc: desc ? desc.value.trim() : '',
            qty: qty ? num(qty.value) : 0,
            unit: unit ? unit.value : '',
            discount: disc ? num(disc.value) : 0,
        };
    }

    // ยอดรวมของแต่ละร้าน (สูตรเดียวกับ PR และ pr_quote_totals() ฝั่ง PHP)
    function slotTotals(slot) {
        const { vat, wht } = vatWht();
        let subtotal = 0;
        let complete = true;
        rows().forEach((row) => {
            const info = rowInfo(row);
            const price = num(priceInput(row, slot).value);
            if (price <= 0) complete = false;
            subtotal += info.qty * price - info.discount;
        });
        const grand = subtotal + subtotal * (vat / 100) - subtotal * (wht / 100);
        return { subtotal, grand, complete };
    }

    function selectedSlot() {
        const r = panel.querySelector('.prq-select:checked');
        return r ? Number(r.value) : null;
    }

    function cheapestSlot() {
        let best = null;
        activeSlots().forEach((slot) => {
            if (!slotStoreId(slot)) return;
            const t = slotTotals(slot);
            if (!t.complete) return;
            if (best === null || t.grand < best.grand - 0.005) best = { slot, grand: t.grand };
        });
        return best;
    }

    function fillStoreSelect(select, selectedId) {
        const current = selectedId !== undefined ? Number(selectedId) : num(select.value);
        select.innerHTML = '<option value="">-- เลือกร้าน --</option>' +
            stores.map((s) => `<option value="${s.id}" ${s.id === current ? 'selected' : ''}>${esc(s.name)}</option>`).join('');
    }

    function renderTable() {
        const slots = activeSlots();
        const sel = selectedSlot();
        const head = slots.map((slot) => {
            const name = storeName(slotStoreId(slot)) || `ร้านที่ ${slot + 1}`;
            return `<th class="text-right ${slot === sel ? 'prq-col-selected' : ''}">${esc(name)}</th>`;
        }).join('');

        const bodyRows = rows().map((row, i) => {
            const info = rowInfo(row);
            const prices = slots.map((slot) => num(priceInput(row, slot).value)).filter((p) => p > 0);
            const minPrice = prices.length > 1 ? Math.min(...prices) : null;
            const cells = slots.map((slot) => {
                const price = priceInput(row, slot).value;
                const line = info.qty * num(price) - info.discount;
                const best = minPrice !== null && num(price) > 0 && Math.abs(num(price) - minPrice) < 0.005;
                return `<td class="text-right ${best ? 'prq-best' : ''} ${slot === sel ? 'prq-col-selected' : ''}">
                    <input type="number" step="0.01" min="0" class="prq-price" data-row="${i}" data-slot="${slot}" value="${esc(price)}" placeholder="0.00">
                    <div class="text-[11px] text-slate-500 mt-1 prq-line" data-row="${i}" data-slot="${slot}">${num(price) > 0 ? fmt(line) : '-'}</div>
                </td>`;
            }).join('');
            return `<tr>
                <td class="text-center text-slate-500 font-bold">${i + 1}</td>
                <td class="font-bold text-slate-700" style="min-width:180px">${esc(info.desc) || '<span class="text-slate-400">(ยังไม่ระบุรายการ)</span>'}</td>
                <td class="text-center whitespace-nowrap">${fmt(info.qty)} ${esc(info.unit)}</td>
                <td class="text-right"><input type="number" step="0.01" min="0" class="prq-price prq-ref" data-row="${i}" value="${esc(refInput(row).value)}" placeholder="-"></td>
                ${cells}
            </tr>`;
        }).join('');

        const foot = (label, key) => `<tr><td></td><td colspan="3" class="text-right">${label}</td>${slots.map((slot) =>
            `<td class="text-right font-mono ${slot === sel ? 'prq-col-selected' : ''}" data-total="${key}" data-slot="${slot}"></td>`).join('')}</tr>`;

        table.innerHTML = `<thead><tr><th class="text-center w-10">#</th><th>รายการ</th><th class="text-center">จำนวน</th><th class="text-right">ราคากลาง/หน่วย</th>${head}</tr></thead>
            <tbody>${bodyRows}</tbody>
            <tfoot>${foot('รวม (หลังส่วนลด)', 'subtotal')}${foot('ยอดสุทธิ (รวม VAT / หัก ณ ที่จ่าย)', 'grand')}</tfoot>`;
        updateTotals();
    }

    // อัปเดตตัวเลข/ไฮไลต์ โดยไม่สร้างตารางใหม่ (ไม่ทำให้ช่องที่กำลังพิมพ์หลุดโฟกัส)
    function updateTotals() {
        const best = cheapestSlot();
        const sel = selectedSlot();
        activeSlots().forEach((slot) => {
            const t = slotTotals(slot);
            const sub = table.querySelector(`[data-total="subtotal"][data-slot="${slot}"]`);
            const grand = table.querySelector(`[data-total="grand"][data-slot="${slot}"]`);
            if (sub) sub.textContent = fmt(t.subtotal);
            if (grand) {
                grand.textContent = fmt(t.grand);
                grand.classList.toggle('text-emerald-600', !!best && best.slot === slot);
            }
            slotEls[slot].querySelector('.prq-best-badge').classList.toggle('hidden', !(best && best.slot === slot));
        });
        rows().forEach((row, i) => {
            const info = rowInfo(row);
            const prices = activeSlots().map((slot) => num(priceInput(row, slot).value)).filter((p) => p > 0);
            const minPrice = prices.length > 1 ? Math.min(...prices) : null;
            activeSlots().forEach((slot) => {
                const price = num(priceInput(row, slot).value);
                const line = table.querySelector(`.prq-line[data-row="${i}"][data-slot="${slot}"]`);
                if (line) line.textContent = price > 0 ? fmt(info.qty * price - info.discount) : '-';
                const cell = line ? line.closest('td') : null;
                if (cell) cell.classList.toggle('prq-best', minPrice !== null && price > 0 && Math.abs(price - minPrice) < 0.005);
            });
        });
        slotEls.forEach((el) => el.classList.toggle('is-selected', Number(el.dataset.slot) === sel));

        const needReason = isEnabled() && sel !== null && best && best.slot !== sel &&
            slotTotals(sel).complete && slotTotals(sel).grand > best.grand + 0.005;
        reasonWrap.classList.toggle('hidden', !needReason);
    }

    // คัดลอกราคาของร้านที่เลือกไปยังตารางรายการสินค้า และตั้งร้านค้าของ PR
    function applySelection() {
        const sel = selectedSlot();
        const mainPrices = rows().map((row) => row.querySelector('[name="item_price[]"]')).filter(Boolean);
        if (!isEnabled() || sel === null || activeSlots().indexOf(sel) === -1) {
            mainPrices.forEach((input) => { input.readOnly = false; input.classList.remove('prq-locked'); });
            return false;
        }
        let changed = false;
        rows().forEach((row) => {
            const main = row.querySelector('[name="item_price[]"]');
            if (!main) return;
            main.readOnly = true;
            main.classList.add('prq-locked');
            main.title = 'ราคามาจากใบเทียบราคา (แก้ที่ตารางเทียบราคา)';
            const price = num(priceInput(row, sel).value);
            if (Math.abs(num(main.value) - price) > 0.0001) {
                main.value = price.toFixed(2);
                changed = true;
            }
        });
        const storeSelect = form.querySelector('select[name="store_id"]');
        const storeId = slotStoreId(sel);
        if (storeSelect && storeId) {
            if (!storeSelect.querySelector(`option[value="${storeId}"]`)) {
                const opt = document.createElement('option');
                opt.value = storeId;
                opt.textContent = storeName(storeId);
                storeSelect.appendChild(opt);
            }
            if (storeSelect.value !== String(storeId)) storeSelect.value = String(storeId);
        }
        return changed;
    }

    function recalcMain() {
        if (typeof window.calculateTotal === 'function') window.calculateTotal();
    }

    function sync(full) {
        if (busy) return;
        busy = true;
        try {
            if (full) renderTable(); else updateTotals();
            if (applySelection()) recalcMain();
            updateRequired();
        } finally {
            busy = false;
        }
    }

    function mainGrandTotal() {
        const el = document.getElementById('grandtotal_display');
        return el ? num(el.innerText.replace(/,/g, '')) : 0;
    }

    function updateRequired() {
        const required = mainGrandTotal() > REQUIRED_ABOVE + 0.001;
        requiredBadge.classList.toggle('hidden', !required);
        if (required && !toggle.checked) {
            toggle.checked = true;
            setTimeout(onToggle, 0); // ออกจาก sync() ก่อน แล้วค่อยเปิดแผง
        }
    }

    function showSlot(slot, show) {
        const el = slotEls[slot];
        el.classList.toggle('hidden', !show);
        if (!show) {
            el.querySelector('.prq-store').value = '';
            el.querySelector('.prq-select').checked = false;
            el.querySelectorAll('input[type="text"], input[type="number"], input[type="file"]').forEach((i) => { i.value = ''; });
            setExisting(el, '');
            rows().forEach((row) => { priceInput(row, slot).value = ''; });
        }
        addSlotBtn.classList.toggle('hidden', activeSlots().length >= MAX);
    }

    function setExisting(el, file) {
        el.querySelector('.prq-existing').value = file || '';
        el.querySelector('.prq-existing-view').classList.toggle('hidden', !file);
        if (file) el.querySelector('.prq-existing-link').href = 'uploads/pr_quotes/' + encodeURIComponent(file);
    }

    function onToggle() {
        body.classList.toggle('hidden', !toggle.checked);
        if (toggle.checked) {
            for (let s = 0; s < MAX && activeSlots().length < MIN; s++) {
                if (slotEls[s].classList.contains('hidden')) showSlot(s, true);
            }
            if (selectedSlot() === null) {
                const first = slotEls[activeSlots()[0]];
                if (first) first.querySelector('.prq-select').checked = true;
            }
        }
        sync(true);
        if (!toggle.checked) recalcMain();
    }

    // ---------- events ----------
    toggle.addEventListener('change', () => {
        if (!toggle.checked && mainGrandTotal() > REQUIRED_ABOVE + 0.001) {
            toggle.checked = true;
            alert(`ยอดสุทธิเกิน ${REQUIRED_ABOVE.toLocaleString()} บาท ต้องทำใบเทียบราคา`);
            return;
        }
        onToggle();
    });

    addSlotBtn.addEventListener('click', () => {
        const free = slotEls.find((el) => el.classList.contains('hidden'));
        if (free) showSlot(Number(free.dataset.slot), true);
        sync(true);
    });

    slotEls.forEach((el) => {
        const slot = Number(el.dataset.slot);
        const select = el.querySelector('.prq-store');
        fillStoreSelect(select);
        select.addEventListener('change', () => sync(true));
        el.querySelector('.prq-select').addEventListener('change', () => sync(true));
        el.querySelector('.prq-remove').addEventListener('click', () => {
            if (activeSlots().length <= MIN) {
                alert(`ต้องมีอย่างน้อย ${MIN} ร้าน`);
                return;
            }
            const wasSelected = selectedSlot() === slot;
            showSlot(slot, false);
            if (wasSelected) {
                const first = slotEls[activeSlots()[0]];
                if (first) first.querySelector('.prq-select').checked = true;
            }
            sync(true);
        });
        el.querySelector('.prq-existing-clear').addEventListener('click', () => setExisting(el, ''));

        const newBox = el.querySelector('.prq-new-store');
        el.querySelector('.prq-add-store-btn').addEventListener('click', () => {
            newBox.classList.toggle('hidden');
            if (!newBox.classList.contains('hidden')) newBox.querySelector('.prq-new-name').focus();
        });
        el.querySelector('.prq-new-cancel').addEventListener('click', () => newBox.classList.add('hidden'));
        el.querySelector('.prq-new-save').addEventListener('click', async (ev) => {
            const btn = ev.currentTarget;
            const name = newBox.querySelector('.prq-new-name').value.trim();
            if (!name) { alert('กรุณาระบุชื่อร้าน'); return; }
            const fd = new FormData();
            fd.append('store_name', name);
            fd.append('phone', newBox.querySelector('.prq-new-phone').value.trim());
            fd.append('contact_person', newBox.querySelector('.prq-new-contact').value.trim());
            btn.disabled = true;
            try {
                const res = await fetch('api/quick_add_store.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status !== 'success') throw new Error(data.message || 'บันทึกไม่สำเร็จ');
                if (!stores.some((s) => s.id === data.id)) {
                    stores.push({ id: data.id, name: data.store_name });
                    stores.sort((a, b) => a.name.localeCompare(b.name, 'th'));
                }
                slotEls.forEach((other) => fillStoreSelect(other.querySelector('.prq-store')));
                select.value = String(data.id);
                newBox.classList.add('hidden');
                newBox.querySelectorAll('input').forEach((i) => { i.value = ''; });
                sync(true);
            } catch (err) {
                alert('เพิ่มร้านไม่สำเร็จ: ' + err.message);
            } finally {
                btn.disabled = false;
            }
        });
    });

    table.addEventListener('input', (ev) => {
        const input = ev.target.closest('.prq-price');
        if (!input) return;
        const row = rows()[Number(input.dataset.row)];
        if (input.classList.contains('prq-ref')) {
            if (row) refInput(row).value = input.value;
            return;
        }
        if (row) priceInput(row, Number(input.dataset.slot)).value = input.value;
        sync(false);
    });

    // แถวสินค้าถูกเพิ่ม/ลบ หรือแก้รายละเอียด → สร้างตารางเทียบใหม่
    new MutationObserver(() => {
        rows().forEach(ensureRowInputs);
        if (isEnabled()) sync(true);
    }).observe(itemsTable.querySelector('tbody'), { childList: true });
    itemsTable.addEventListener('change', () => { if (isEnabled()) sync(true); });
    ['vat_percent', 'wht_percent'].forEach((n) => {
        const el = document.querySelector(`select[name="${n}"]`);
        if (el) el.addEventListener('change', () => sync(true));
    });

    // คำนวณตารางใหม่ทุกครั้งที่ยอดใน PR เปลี่ยน (จำนวน/ส่วนลด)
    const baseCalc = window.calculateTotal;
    if (typeof baseCalc === 'function') {
        window.calculateTotal = function () {
            baseCalc.apply(this, arguments);
            if (!busy) sync(isEnabled());
        };
    }

    form.addEventListener('submit', (ev) => {
        const error = validate();
        if (error) {
            ev.preventDefault();
            ev.stopImmediatePropagation();
            alert(error);
        }
    }, true);

    function validate() {
        if (!isEnabled()) {
            return mainGrandTotal() > REQUIRED_ABOVE + 0.001
                ? `ยอดสุทธิเกิน ${REQUIRED_ABOVE.toLocaleString()} บาท ต้องทำใบเทียบราคาอย่างน้อย ${MIN} ร้าน` : null;
        }
        const slots = activeSlots().filter((slot) => slotStoreId(slot));
        if (slots.length < MIN) return `ใบเทียบราคา: กรุณาเลือกร้านอย่างน้อย ${MIN} ร้าน`;
        const ids = slots.map(slotStoreId);
        if (new Set(ids).size !== ids.length) return 'ใบเทียบราคา: มีร้านซ้ำกัน กรุณาเลือกร้านที่ต่างกัน';
        for (const slot of slots) {
            if (!slotTotals(slot).complete) return `ใบเทียบราคา: กรุณาใส่ราคาของ "${storeName(slotStoreId(slot))}" ให้ครบทุกรายการ`;
        }
        const sel = selectedSlot();
        if (sel === null || slots.indexOf(sel) === -1) return 'ใบเทียบราคา: กรุณาเลือกร้านที่จะซื้อ';
        const best = cheapestSlot();
        if (best && slotTotals(sel).grand > best.grand + 0.005 && !reasonInput.value.trim()) {
            reasonInput.focus();
            return 'ใบเทียบราคา: ร้านที่เลือกไม่ใช่ร้านที่ถูกที่สุด กรุณาระบุเหตุผล';
        }
        sync(true); // ให้ item_price ตรงกับร้านที่เลือกก่อนส่ง
        return null;
    }

    // ---------- init ----------
    rows().forEach(ensureRowInputs);
    (config.quotes || []).slice(0, MAX).forEach((q, slot) => {
        const el = slotEls[slot];
        showSlot(slot, true);
        fillStoreSelect(el.querySelector('.prq-store'), q.store_id);
        el.querySelector('.prq-quote-no').value = q.quote_no || '';
        el.querySelector('.prq-delivery').value = q.delivery_days || '';
        el.querySelector('.prq-term').value = q.payment_term || '';
        el.querySelector('.prq-service').value = q.service_note || '';
        el.querySelector('.prq-select').checked = !!q.is_selected;
        setExisting(el, q.attachment);
        const prices = q.prices || {};
        rows().forEach((row, i) => {
            if (prices[i] !== undefined) priceInput(row, slot).value = Number(prices[i]).toFixed(2);
        });
    });
    const refs = config.refPrices || {};
    rows().forEach((row, i) => {
        if (refs[i] !== undefined && refs[i] !== null) refInput(row).value = Number(refs[i]).toFixed(2);
    });
    addSlotBtn.classList.toggle('hidden', activeSlots().length >= MAX);
    if (toggle.checked) onToggle(); else updateRequired();
});
