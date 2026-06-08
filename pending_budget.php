<?php
require 'config.php';

if (isset($_GET['action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    // ===== FETCH: ดึงทั้งงบใหม่และปรับเพิ่ม =====
    if ($_GET['action'] == 'fetch') {
        // 1. งบใหม่ (pending budget_types)
        $sql_budgets = "SELECT bt.*, s.company_name,
                        (bt.budget_amount + COALESCE((SELECT SUM(a.amount) FROM budget_adjustments a WHERE a.budget_type_id = bt.id AND a.status = 'approved'), 0)) as total_budget,
                        (SELECT SUM(p.grand_total) FROM pr p WHERE p.budget_type_id = bt.id AND p.status = 'approved' AND p.deleted_at IS NULL) as total_spent,
                        u_gmacc.name as gmacc_name, u_mgr.name as mgr_name
                        FROM budget_types bt 
                        JOIN suppliers s ON bt.sup_id = s.id 
                        LEFT JOIN users u_gmacc ON bt.approved_by_gmacc = u_gmacc.id
                        LEFT JOIN users u_mgr ON bt.approved_by_mgr = u_mgr.id
                        WHERE bt.status = 'pending'
                        ORDER BY s.company_name ASC, bt.name ASC";
        $result = mysqli_query($conn, $sql_budgets);
        $budgets = mysqli_fetch_all($result, MYSQLI_ASSOC);

        // 2. ปรับเพิ่มงบ (pending adjustments)
        $sql_adjusts = "SELECT a.*, bt.name as budget_name, bt.budget_amount, s.company_name,
                        u.name as requester_name
                        FROM budget_adjustments a
                        JOIN budget_types bt ON a.budget_type_id = bt.id
                        JOIN suppliers s ON bt.sup_id = s.id
                        LEFT JOIN users u ON a.created_by = u.id
                        WHERE a.status = 'pending'
                        ORDER BY a.created_at DESC";
        $result2 = mysqli_query($conn, $sql_adjusts);
        $adjusts = mysqli_fetch_all($result2, MYSQLI_ASSOC);

        echo json_encode([
            'budgets' => $budgets,
            'adjusts' => $adjusts
        ]);
        exit;
    }

    // ===== APPROVE =====
    if ($_GET['action'] == 'approve') {
        $id = intval($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? 'budget'; // budget หรือ adjust
        $role = $_SESSION['role'] ?? '';
        $user_id = $_SESSION['user_id'] ?? 0;

        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid ID']);
            exit;
        }

        if ($type === 'adjust') {
            // === อนุมัติการปรับงบประมาณ ===
            $update_sql = "";
            if ($role === 'gmacc') {
                $update_sql = "approved_by_gmacc = $user_id, approved_at_gmacc = NOW()";
            } elseif ($role === 'mgr') {
                $update_sql = "approved_by_mgr = $user_id, approved_at_mgr = NOW()";
            } elseif ($role === 'admin') {
                $update_sql = "approved_by_gmacc = COALESCE(approved_by_gmacc, $user_id), 
                               approved_at_gmacc = COALESCE(approved_at_gmacc, NOW()),
                               approved_by_mgr = COALESCE(approved_by_mgr, $user_id),
                               approved_at_mgr = COALESCE(approved_at_mgr, NOW())";
            }

            if (empty($update_sql)) {
                echo json_encode(['status' => 'error', 'msg' => 'คุณไม่มีสิทธิ์อนุมัติ']);
                exit;
            }

            $sql = "UPDATE budget_adjustments SET $update_sql WHERE id = $id";
            if (mysqli_query($conn, $sql)) {
                $check = mysqli_query($conn, "SELECT * FROM budget_adjustments WHERE id = $id");
                $adj = mysqli_fetch_assoc($check);
                if ($adj['approved_by_gmacc'] && $adj['approved_by_mgr']) {
                    mysqli_query($conn, "UPDATE budget_adjustments SET status = 'approved' WHERE id = $id");
                }
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => mysqli_error($conn)]);
            }
        } else {
            // === อนุมัติงบประมาณใหม่ ===
            $update_sql = "";
            if ($role === 'gmacc') {
                $update_sql = "approved_by_gmacc = $user_id, approved_at_gmacc = NOW()";
            } elseif ($role === 'mgr') {
                $update_sql = "approved_by_mgr = $user_id, approved_at_mgr = NOW()";
            } elseif ($role === 'admin') {
                $update_sql = "approved_by_gmacc = COALESCE(approved_by_gmacc, $user_id), 
                               approved_at_gmacc = COALESCE(approved_at_gmacc, NOW()),
                               approved_by_mgr = COALESCE(approved_by_mgr, $user_id),
                               approved_at_mgr = COALESCE(approved_at_mgr, NOW())";
            }

            if (empty($update_sql)) {
                echo json_encode(['status' => 'error', 'msg' => 'คุณไม่มีสิทธิ์อนุมัติ']);
                exit;
            }

            $sql = "UPDATE budget_types SET $update_sql WHERE id = $id";
            if (mysqli_query($conn, $sql)) {
                $check = mysqli_query($conn, "SELECT bt.*, s.company_name, s.line_token FROM budget_types bt JOIN suppliers s ON bt.sup_id = s.id WHERE bt.id = $id");
                $bt = mysqli_fetch_assoc($check);
                if ($bt['approved_by_gmacc'] && $bt['approved_by_mgr']) {
                    mysqli_query($conn, "UPDATE budget_types SET status = 'approved' WHERE id = $id");
                    try {
                        require_once 'api/line_notify.php';
                        if (!empty($bt['line_token'])) {
                            $msg = "\n💰 อนุมัติงบประมาณใหม่\n";
                            $msg .= "ประเภทงบ: " . $bt['name'] . "\n";
                            $msg .= "บริษัท: " . $bt['company_name'] . "\n";
                            $msg .= "จำนวนเงิน: " . number_format($bt['budget_amount'], 2) . " บาท\n";
                            $msg .= "สถานะ: พร้อมใช้งานแล้ว";
                            sendLineNotify($msg, $bt['line_token']);
                        }
                    } catch (Exception $e) {}
                }
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => mysqli_error($conn)]);
            }
        }
        exit;
    }
}

include('header.php');
?>

<div class="container p-0">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-slate-800">รายการรออนุมัติงบประมาณ</h2>
    </div>

    <!-- Tab: งบใหม่ / ปรับเพิ่ม -->
    <div class="flex gap-1 bg-slate-100 p-1 rounded-xl mb-4 w-fit">
        <button onclick="switchTab('new')" id="tab-new" class="px-4 py-2 text-sm font-bold rounded-lg bg-white text-indigo-600 shadow-sm transition-all">งบประมาณใหม่</button>
        <button onclick="switchTab('adjust')" id="tab-adjust" class="px-4 py-2 text-sm font-bold rounded-lg text-slate-500 hover:text-slate-800 transition-all">เพิ่มงบเดิม</button>
    </div>

    <!-- ===== ตารางงบประมาณใหม่ ===== -->
    <div id="table-new" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">บริษัท</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">ชื่อประเภทงบประมาณ</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">งบตั้งต้น</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">คงเหลือปัจจุบัน</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">เหตุผล</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">สถานะ</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody id="budgetTableBody" class="divide-y divide-slate-50"></tbody>
        </table>
    </div>

    <!-- ===== ตารางเพิ่มงบเดิม ===== -->
    <div id="table-adjust" class="hidden bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">บริษัท</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">ชื่องบประมาณ</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">จำนวนเงิน</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">ผู้ขอ</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">เหตุผล</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">สถานะ</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody id="adjustTableBody" class="divide-y divide-slate-50"></tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
const USER_ROLE = '<?= $_SESSION['role'] ?? '' ?>';

$(document).ready(function() {
    fetchPending();
});

function switchTab(tab) {
    $('#tab-new, #tab-adjust').removeClass('bg-white text-indigo-600 shadow-sm').addClass('text-slate-500');
    $('#table-new, #table-adjust').addClass('hidden');
    if (tab === 'new') {
        $('#tab-new').addClass('bg-white text-indigo-600 shadow-sm').removeClass('text-slate-500');
        $('#table-new').removeClass('hidden');
    } else {
        $('#tab-adjust').addClass('bg-white text-indigo-600 shadow-sm').removeClass('text-slate-500');
        $('#table-adjust').removeClass('hidden');
    }
}

function fetchPending() {
    $.get('?action=fetch', function(data) {
        // --- งบใหม่ ---
        let bHtml = '';
        const budgets = data.budgets || [];
        if (budgets.length === 0) {
            bHtml = '<tr><td colspan="7" class="p-12 text-center text-slate-400 font-medium">ไม่มีรายการงบประมาณใหม่ที่รออนุมัติ</td></tr>';
        } else {
            budgets.forEach(item => {
                const initial = parseFloat(item.budget_amount || 0);
                const total = parseFloat(item.total_budget || 0);
                const spent = parseFloat(item.total_spent || 0);
                const balance = total - spent;

                let approveBtn = '';
                if (USER_ROLE === 'gmacc' && !item.approved_by_gmacc) {
                    approveBtn += `<button onclick="approveBudget(${item.id}, 'budget')" class="text-[10px] bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition shadow-sm font-bold"><i class="fas fa-check-circle mr-1"></i>บัญชีอนุมัติ</button>`;
                }
                if (USER_ROLE === 'mgr' && !item.approved_by_mgr) {
                    approveBtn += `<button onclick="approveBudget(${item.id}, 'budget')" class="text-[10px] bg-emerald-600 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-700 transition shadow-sm font-bold"><i class="fas fa-check-circle mr-1"></i>MGR อนุมัติ</button>`;
                }
                if (USER_ROLE === 'admin') {
                    approveBtn += `<button onclick="approveBudget(${item.id}, 'budget')" class="text-[10px] bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-black transition shadow-sm font-bold"><i class="fas fa-check-circle mr-1"></i>Admin อนุมัติ</button>`;
                }

                let statusHtml = '';
                if (item.approved_by_gmacc && item.approved_by_mgr) {
                    statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200"><i class="fas fa-check-circle mr-1"></i> อนุมัติแล้ว</span>`;
                } else if (item.approved_by_gmacc) {
                    statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200"><i class="fas fa-user-check mr-1"></i> บัญชีอนุมัติแล้ว</span>`;
                } else if (item.approved_by_mgr) {
                    statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200"><i class="fas fa-user-check mr-1"></i> MGR อนุมัติแล้ว</span>`;
                } else {
                    statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-200"><i class="fas fa-clock mr-1"></i> รออนุมัติ</span>`;
                }

                const approvedInfo = [];
                if (item.gmacc_name) approvedInfo.push(`<span class="text-[9px] text-slate-400">บัญชี: ${item.gmacc_name}</span>`);
                if (item.mgr_name) approvedInfo.push(`<span class="text-[9px] text-slate-400">ผู้จัดการ: ${item.mgr_name}</span>`);

                bHtml += `
                <tr class="hover:bg-slate-50 transition text-sm">
                    <td class="p-4 font-bold text-slate-700">${item.company_name}</td>
                    <td class="p-4">
                        <div class="flex flex-col gap-1">
                            <span class="text-slate-600 font-bold">${item.name}</span>
                            <div class="flex gap-1">${approvedInfo.join('')}</div>
                        </div>
                    </td>
                    <td class="p-4 text-slate-600 text-right font-mono">${initial.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="p-4 text-right font-mono font-bold ${balance < 0 ? 'text-red-600' : 'text-indigo-600'}">
                        ${balance.toLocaleString(undefined, {minimumFractionDigits: 2})}
                    </td>
                    <td class="p-4 text-slate-400 text-[10px] max-w-[150px] truncate">-</td>
                    <td class="p-4 text-center">${statusHtml}</td>
                    <td class="p-4 text-center">${approveBtn || '<span class="text-[10px] text-slate-400 italic">ไม่มีสิทธิ์</span>'}</td>
                </tr>`;
            });
        }
        $('#budgetTableBody').html(bHtml);

        // --- ปรับเพิ่มงบ ---
        let aHtml = '';
        const adjusts = data.adjusts || [];
        if (adjusts.length === 0) {
            aHtml = '<tr><td colspan="7" class="p-12 text-center text-slate-400 font-medium">ไม่มีรายการปรับเพิ่มงบที่รออนุมัติ</td></tr>';
        } else {
            adjusts.forEach(item => {
                const amount = parseFloat(item.amount || 0);
                const isAddition = amount >= 0;

                let approveBtn = '';
                if (USER_ROLE === 'gmacc' && !item.approved_by_gmacc) {
                    approveBtn += `<button onclick="approveBudget(${item.id}, 'adjust')" class="text-[10px] bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition shadow-sm font-bold"><i class="fas fa-check-circle mr-1"></i>บัญชีอนุมัติ</button>`;
                }
                if (USER_ROLE === 'mgr' && !item.approved_by_mgr) {
                    approveBtn += `<button onclick="approveBudget(${item.id}, 'adjust')" class="text-[10px] bg-emerald-600 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-700 transition shadow-sm font-bold"><i class="fas fa-check-circle mr-1"></i>MGR อนุมัติ</button>`;
                }
                if (USER_ROLE === 'admin') {
                    approveBtn += `<button onclick="approveBudget(${item.id}, 'adjust')" class="text-[10px] bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-black transition shadow-sm font-bold"><i class="fas fa-check-circle mr-1"></i>Admin อนุมัติ</button>`;
                }

                let statusHtml = '';
                if (item.approved_by_gmacc && item.approved_by_mgr) {
                    statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200"><i class="fas fa-check-circle mr-1"></i> อนุมัติแล้ว</span>`;
                } else if (item.approved_by_gmacc) {
                    statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200"><i class="fas fa-user-check mr-1"></i> บัญชีอนุมัติแล้ว</span>`;
                } else if (item.approved_by_mgr) {
                    statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200"><i class="fas fa-user-check mr-1"></i> MGR อนุมัติแล้ว</span>`;
                } else {
                    statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-200"><i class="fas fa-clock mr-1"></i> รออนุมัติ</span>`;
                }

                aHtml += `
                <tr class="hover:bg-slate-50 transition text-sm">
                    <td class="p-4 font-bold text-slate-700">${item.company_name}</td>
                    <td class="p-4 font-bold text-slate-600">${item.budget_name}</td>
                    <td class="p-4 text-right font-mono font-bold ${isAddition ? 'text-emerald-600' : 'text-red-600'}">
                        ${isAddition ? '+' : ''}${amount.toLocaleString(undefined, {minimumFractionDigits: 2})}
                    </td>
                    <td class="p-4 text-slate-500">${item.requester_name || '-'}</td>
                    <td class="p-4 text-slate-500 text-[10px] max-w-[200px] truncate" title="${(item.reason || '').replace(/"/g, '&quot;')}">${item.reason || '-'}</td>
                    <td class="p-4 text-center">${statusHtml}</td>
                    <td class="p-4 text-center">${approveBtn || '<span class="text-[10px] text-slate-400 italic">ไม่มีสิทธิ์</span>'}</td>
                </tr>`;
            });
        }
        $('#adjustTableBody').html(aHtml);
    });
}

function approveBudget(id, type) {
    if (!confirm('ยืนยันการอนุมัติ?')) return;
    $.post('?action=approve', { id: id, type: type }, function(res) {
        if (res.status === 'success') {
            fetchPending();
        } else {
            alert(res.msg);
        }
    });
}
</script>

<?php include 'footer.php'; ?>
