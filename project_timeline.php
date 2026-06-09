<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

$projects = mysqli_query($conn, "SELECT id, project_name, project_no FROM projects WHERE project_status = 'active' ORDER BY project_name ASC");
$has_projects = mysqli_num_rows($projects) > 0;
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Timeline โครงการ</h2>
            <p class="text-slate-500 text-sm">เลือกโครงการเพื่อดูกราฟการเงินและไทม์ไลน์กิจกรรม</p>
        </div>
        <div class="relative min-w-[300px]">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <select id="projectSelect" onchange="loadProject(this.value)"
                class="w-full bg-white border-slate-200 border rounded-xl pl-10 pr-4 py-3 text-sm font-semibold text-slate-700 shadow-sm focus:ring-2 focus:ring-indigo-500 transition-all outline-none appearance-none cursor-pointer">
                <option value="">-- เลือกโครงการ --</option>
                <?php while ($p = mysqli_fetch_assoc($projects)): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['project_no']) ?>)</option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <?php if (!$has_projects): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
            <i class="fas fa-folder-open text-5xl text-slate-300 mb-4"></i>
            <p class="text-lg font-bold text-slate-400">ไม่มีโครงการที่กำลังดำเนินการ</p>
        </div>
    <?php else: ?>
        <!-- Summary Bar -->
        <div id="summaryBar" class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
                <p class="text-xs text-slate-400 font-medium">งวดงานทั้งหมด</p>
                <p id="statTotalMs" class="text-xl font-black text-slate-700 mt-1">-</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
                <p class="text-xs text-slate-400 font-medium">ชำระแล้ว</p>
                <p id="statPaidMs" class="text-xl font-black text-emerald-600 mt-1">-</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
                <p class="text-xs text-slate-400 font-medium">คงค้าง (จำนวน)</p>
                <p id="statPendingMs" class="text-xl font-black text-amber-600 mt-1">-</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
                <p class="text-xs text-slate-400 font-medium">ยอดค้างชำระ</p>
                <p id="statPendingAmt" class="text-xl font-black text-rose-600 mt-1">-</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
                <p class="text-xs text-slate-400 font-medium">เลยกำหนด</p>
                <p id="statOverdue" class="text-xl font-black text-red-600 mt-1">-</p>
            </div>
        </div>

        <!-- Chart + Timeline -->
        <div id="dataArea" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h4 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-chart-bar text-indigo-500"></i> กราฟแสดงงวดงาน
                </h4>
                <div class="h-[350px] flex items-center justify-center" id="chartContainer">
                    <div class="text-center text-slate-300">
                        <i class="fas fa-hand-pointer text-4xl mb-2"></i>
                        <p class="text-sm font-bold">กรุณาเลือกโครงการ</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h4 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-indigo-500"></i> ปฏิทินกิจกรรม
                </h4>
                <div id="calendarNav" class="flex items-center justify-between mb-4">
                    <button onclick="prevMonth()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-500 transition-all"><i class="fas fa-chevron-left text-xs"></i></button>
                    <h5 id="calendarTitle" class="text-sm font-bold text-slate-700"></h5>
                    <button onclick="nextMonth()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-500 transition-all"><i class="fas fa-chevron-right text-xs"></i></button>
                </div>
                <div id="calendarGrid" class="min-h-[200px] flex items-center justify-center text-slate-300 text-sm">
                    <i class="fas fa-hand-pointer text-2xl mr-2"></i> กรุณาเลือกโครงการ
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let chartInstance = null;

function loadProject(id) {
    if (!id) return;
    document.getElementById('chartContainer').innerHTML = '<div class="text-center text-indigo-400"><i class="fas fa-spinner fa-spin text-3xl mb-2"></i><p class="text-sm font-bold">กำลังโหลด...</p></div>';
    document.getElementById('calendarGrid').innerHTML = '<div class="text-center text-indigo-400"><i class="fas fa-spinner fa-spin text-2xl mr-2"></i> กำลังโหลด...</div>';

    fetch('api/project_timeline_data.php?project_id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            updateSummary(data.stats);
            renderChart(data.milestones);
            renderTimeline(data.events);
        })
        .catch(e => {
            console.error(e);
            alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
        });
}

function updateSummary(s) {
    if (!s) return;
    document.getElementById('statTotalMs').textContent = s.total_ms || 0;
    document.getElementById('statPaidMs').textContent = s.paid_ms || 0;
    document.getElementById('statPendingMs').textContent = (s.total_ms || 0) - (s.paid_ms || 0);
    document.getElementById('statPendingAmt').textContent = fmt(s.pending_amt || 0);
    document.getElementById('statOverdue').textContent = s.overdue_cnt || 0;
}

function renderChart(ms) {
    const container = document.getElementById('chartContainer');
    if (chartInstance) { chartInstance.destroy(); chartInstance = null; }

    if (!ms || ms.length === 0) {
        container.innerHTML = '<div class="text-center text-slate-300"><i class="fas fa-chart-bar text-4xl mb-2"></i><p class="text-sm font-bold">ไม่มีข้อมูลงวดงาน</p></div>';
        return;
    }

    container.innerHTML = '<canvas id="milestoneChart"></canvas>';
    const ctx = document.getElementById('milestoneChart').getContext('2d');

    const labels = ms.map(m => m.ms_name);
    const paidData = ms.map(m => m.status === 'paid' ? parseFloat(m.amount) : 0);
    const pendingData = ms.map(m => m.status === 'pending' ? parseFloat(m.amount) : 0);

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'ชำระแล้ว', data: paidData, backgroundColor: 'rgba(16,185,129,0.8)', borderColor: 'rgba(16,185,129,1)', borderWidth: 1, borderRadius: 6, barPercentage: 0.6 },
                { label: 'คงค้าง', data: pendingData, backgroundColor: 'rgba(245,158,11,0.8)', borderColor: 'rgba(245,158,11,1)', borderWidth: 1, borderRadius: 6, barPercentage: 0.6 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11, weight: 'bold' }, padding: 15, usePointStyle: true } },
                tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + fmt(ctx.raw) + ' ฿' } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { font: { size: 10 }, callback: v => fmt(v) } },
                x: { ticks: { font: { size: 10 }, maxRotation: 30 } }
            }
        }
    });
}

let calendarEvents = [];
let calViewDate = new Date();

function renderTimeline(events) {
    calendarEvents = events || [];
    calViewDate = new Date();
    if (calendarEvents.length > 0) {
        const dates = calendarEvents.map(e => e.date).filter(Boolean).sort();
        if (dates.length > 0) calViewDate = new Date(dates[0].substring(0, 10) + 'T00:00:00');
    }
    renderCalendar();
}

function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    const title = document.getElementById('calendarTitle');
    if (!calendarEvents || calendarEvents.length === 0) {
        grid.innerHTML = '<div class="text-center text-slate-300 py-8"><i class="fas fa-calendar-alt text-3xl mb-2"></i><p class="text-sm font-bold">ไม่มีกิจกรรม</p></div>';
        title.textContent = '';
        return;
    }

    const year = calViewDate.getFullYear();
    const month = calViewDate.getMonth();
    const monthNames = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    title.textContent = monthNames[month] + ' ' + year;

    // Group events by date
    const byDate = {};
    calendarEvents.forEach(ev => {
        if (!ev.date) return;
        const d = ev.date.substring(0, 10);
        if (!byDate[d]) byDate[d] = [];
        byDate[d].push(ev);
    });

    // Calendar grid
    const firstDay = new Date(year, month, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const dayNames = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];

    let html = '<table class="w-full border-collapse"><thead><tr>';
    dayNames.forEach(d => {
        html += `<th class="text-[10px] font-bold text-slate-400 pb-2 text-center w-[14.28%]">${d}</th>`;
    });
    html += '</tr></thead><tbody>';

    let day = 1;
    for (let row = 0; row < 6; row++) {
        if (day > daysInMonth) break;
        html += '<tr>';
        for (let col = 0; col < 7; col++) {
            if ((row === 0 && col < firstDay) || day > daysInMonth) {
                html += '<td class="p-1"></td>';
            } else {
                const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
                const evs = byDate[dateStr] || [];
                const isToday = dateStr === new Date().toISOString().substring(0, 10);
                const hasEvents = evs.length > 0;

                let dotHtml = '';
                if (hasEvents) {
                    const colors = { project_created: 'bg-indigo-500', milestone_requested: 'bg-amber-500', milestone_inspected: 'bg-emerald-500', doc_linked: 'bg-blue-500' };
                    const typeCounts = {};
                    evs.forEach(e => { typeCounts[e.type] = (typeCounts[e.type] || 0) + 1; });
                    Object.keys(typeCounts).slice(0, 4).forEach(t => {
                        dotHtml += `<span class="inline-block w-1.5 h-1.5 rounded-full ${colors[t] || 'bg-slate-300'} mr-0.5"></span>`;
                    });
                    if (evs.length > 4) dotHtml += `<span class="text-[8px] text-slate-400 font-bold ml-0.5">+${evs.length - 4}</span>`;
                }

                html += `<td class="p-1 align-top ${isToday ? 'bg-indigo-50/50 rounded-lg' : ''} ${hasEvents ? 'cursor-pointer' : ''}" onclick="${hasEvents ? 'showDayEvents(\'' + dateStr + '\')' : ''}">
                    <div class="text-center">
                        <span class="text-xs font-bold ${isToday ? 'text-indigo-600' : hasEvents ? 'text-slate-700' : 'text-slate-300'}">${day}</span>
                        ${dotHtml ? `<div class="flex items-center justify-center mt-0.5 gap-0.5">${dotHtml}</div>` : ''}
                    </div>
                </td>`;
                day++;
            }
        }
        html += '</tr>';
    }
    html += '</tbody></table>';

    // Legend + day detail
    html += `<div id="dayDetail" class="mt-4 pt-3 border-t border-slate-100 min-h-[60px]">
        <p class="text-xs text-slate-400 text-center">คลิกวันที่ที่มีจุดเพื่อดูรายละเอียด</p>
    </div>`;

    grid.innerHTML = html;
}

function showDayEvents(dateStr) {
    const evs = (calendarEvents || []).filter(e => e.date && e.date.substring(0, 10) === dateStr);
    if (evs.length === 0) return;

    const container = document.getElementById('dayDetail');
    const d = new Date(dateStr + 'T00:00:00');
    const dateLabel = d.toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    let html = `<p class="text-xs font-bold text-indigo-600 mb-2 flex items-center gap-1.5">
        <i class="fas fa-calendar-check"></i> ${dateLabel} (${evs.length} กิจกรรม)
    </p><div class="space-y-1.5 max-h-[180px] overflow-y-auto">`;

    evs.forEach(ev => {
        let icon = 'fa-circle', color = 'bg-slate-300';
        switch(ev.type) {
            case 'project_created': icon = 'fa-flag-checkered'; color = 'bg-indigo-500'; break;
            case 'milestone_requested': icon = 'fa-file-invoice-dollar'; color = 'bg-amber-500'; break;
            case 'milestone_inspected': icon = 'fa-clipboard-check'; color = ev.detail && ev.detail.includes('pass') ? 'bg-emerald-500' : 'bg-rose-500'; break;
            case 'doc_linked': icon = 'fa-link'; color = 'bg-blue-500'; break;
        }
        html += `<div class="flex items-start gap-2 p-2 rounded-lg hover:bg-slate-50 transition-colors">
            <span class="w-5 h-5 rounded-full ${color} text-white flex items-center justify-center shrink-0 mt-0.5"><i class="fas ${icon} text-[7px]"></i></span>
            <div><p class="text-xs font-bold text-slate-700">${esc(ev.title)}</p><p class="text-[10px] text-slate-500">${esc(ev.detail)}</p></div>
        </div>`;
    });

    html += '</div>';
    container.innerHTML = html;
}

function prevMonth() {
    calViewDate.setMonth(calViewDate.getMonth() - 1);
    renderCalendar();
}
function nextMonth() {
    calViewDate.setMonth(calViewDate.getMonth() + 1);
    renderCalendar();
}

function fmt(n) { return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('projectSelect');
    if (sel && sel.options.length > 1) {
        sel.value = sel.options[1].value;
        loadProject(sel.value);
    }
});
</script>

<?php include('footer.php'); ?>
