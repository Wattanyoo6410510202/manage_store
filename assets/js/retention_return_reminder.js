(function (root, factory) {
    const api = factory();
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
    if (root) {
        root.retentionReturnDaysRemaining = api.retentionReturnDaysRemaining;
        root.retentionReturnLabel = api.retentionReturnLabel;
        root.showRetentionReturnReminder = api.showRetentionReturnReminder;

        const show = function () {
            api.showRetentionReturnReminder(
                root.retentionReturnAlerts || [],
                undefined,
                root.Swal
            );
        };
        if (root.document) {
            if (root.document.readyState === 'loading') {
                root.document.addEventListener('DOMContentLoaded', show);
            } else {
                show();
            }
        }
    }
}(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : null), function () {
    function dateToUtc(value) {
        if (value instanceof Date) {
            return Date.UTC(value.getFullYear(), value.getMonth(), value.getDate());
        }
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return NaN;
        return Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    function isoDate(value) {
        if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
        const date = value instanceof Date ? value : new Date();
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function retentionReturnDaysRemaining(dueDate, today) {
        const due = dateToUtc(dueDate);
        const current = dateToUtc(today || new Date());
        if (!Number.isFinite(due) || !Number.isFinite(current)) return null;
        return Math.round((due - current) / 86400000);
    }

    function retentionReturnLabel(daysRemaining) {
        if (daysRemaining === null || daysRemaining === undefined) return 'ไม่ระบุวันคืนเงินประกัน';
        if (daysRemaining > 0) return `เหลืออีก ${daysRemaining} วัน`;
        if (daysRemaining === 0) return 'ครบกำหนดวันนี้';
        return `เกินกำหนด ${Math.abs(daysRemaining)} วัน`;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, function (character) {
            return {
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[character];
        });
    }

    function showRetentionReturnReminder(items, storage, swal, today) {
        if (!Array.isArray(items) || items.length === 0) return false;

        const currentDate = isoDate(today || new Date());
        const key = `retention_return_reminder_${currentDate}`;
        let store = storage;
        if (!store && typeof window !== 'undefined') {
            try { store = window.localStorage; } catch (error) { store = null; }
        }
        try {
            if (store && store.getItem(key) === 'shown') return false;
        } catch (error) {
            store = null;
        }

        const dialog = swal || (typeof window !== 'undefined' ? window.Swal : null);
        if (!dialog || typeof dialog.fire !== 'function') return false;

        const html = items.map(function (item) {
            const days = item.daysRemaining === undefined || item.daysRemaining === null
                ? retentionReturnDaysRemaining(item.dueDate, currentDate)
                : Number(item.daysRemaining);
            return `<div class="text-left border-b border-slate-100 py-2 last:border-b-0">
                <div class="font-bold">${escapeHtml(item.projectName)}</div>
                <div class="text-sm">งวด: ${escapeHtml(item.milestoneName)}</div>
                <div class="text-sm">คืนเงินประกัน: <b>${escapeHtml(item.dueDate)}</b> · ${escapeHtml(retentionReturnLabel(days))}</div>
            </div>`;
        }).join('');

        dialog.fire({
            title: 'แจ้งเตือนคืนเงินประกัน',
            html,
            icon: 'warning',
            confirmButtonText: 'รับทราบ',
            heightAuto: false,
            width: 520,
        });
        try {
            if (store) store.setItem(key, 'shown');
        } catch (error) {
            // A blocked localStorage should not prevent the reminder from showing.
        }
        return true;
    }

    return {
        retentionReturnDaysRemaining,
        retentionReturnLabel,
        showRetentionReturnReminder,
    };
}));
