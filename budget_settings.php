<?php
require 'config.php';

if (isset($_GET['action'])) {
    if (ob_get_length()) ob_clean(); // ล้าง buffer เพื่อให้ JSON สะอาด
    header('Content-Type: application/json');
    
    // API: ดึงข้อมูลทั้งหมด
    if ($_GET['action'] == 'fetch') {
        $sql = "SELECT bt.*, s.company_name,
                (bt.budget_amount + COALESCE((SELECT SUM(a.amount) FROM budget_adjustments a WHERE a.budget_type_id = bt.id AND a.status = 'approved'), 0)) as total_budget,
                (SELECT SUM(p.grand_total) FROM pr p WHERE p.budget_type_id = bt.id AND p.status = 'approved' AND p.deleted_at IS NULL) as total_spent,
                (SELECT SUM(a.amount) FROM budget_adjustments a WHERE a.budget_type_id = bt.id AND a.status = 'approved') as total_adjustment,
                (SELECT COUNT(*) FROM budget_adjustments a WHERE a.budget_type_id = bt.id AND a.status = 'pending') as pending_adjust_count,
                u_gmacc.name as gmacc_name, u_mgr.name as mgr_name
                FROM budget_types bt 
                JOIN suppliers s ON bt.sup_id = s.id 
                LEFT JOIN users u_gmacc ON bt.approved_by_gmacc = u_gmacc.id
                LEFT JOIN users u_mgr ON bt.approved_by_mgr = u_mgr.id
                ORDER BY s.company_name ASC, bt.name ASC";
        $result = mysqli_query($conn, $sql);
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        echo json_encode($data);
        exit;
    }

    // API: อนุมัติงบประมาณ
    if ($_GET['action'] == 'approve') {
        $id = intval($_POST['id'] ?? 0);
        $role = $_SESSION['role'] ?? '';
        $user_id = $_SESSION['user_id'] ?? 0;

        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid ID']);
            exit;
        }

        $update_sql = "";
        if ($role === 'gmacc') {
            $update_sql = "approved_by_gmacc = $user_id, approved_at_gmacc = NOW()";
        } elseif ($role === 'mgr') {
            $update_sql = "approved_by_mgr = $user_id, approved_at_mgr = NOW()";
        } elseif ($role === 'admin') {
             // Admin สามารถกดแทนได้
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
            // เช็คว่ากดครบหรือยังเพื่อเปลี่ยน status เป็น approved
            $check = mysqli_query($conn, "SELECT bt.*, s.company_name, s.line_token FROM budget_types bt JOIN suppliers s ON bt.sup_id = s.id WHERE bt.id = $id");
            $bt = mysqli_fetch_assoc($check);
            if ($bt['approved_by_gmacc'] && $bt['approved_by_mgr']) {
                mysqli_query($conn, "UPDATE budget_types SET status = 'approved' WHERE id = $id");
                
                // --- LINE NOTIFICATION (Budget Approved) ---
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
        exit;
    }

    // API: บันทึกข้อมูล (Save/Update)
    if ($_GET['action'] == 'save') {
        $id = $_POST['id'] ?? '';
        $sup_id = intval($_POST['sup_id'] ?? 0);
        $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $budget_amount = floatval($_POST['budget_amount'] ?? 0);
        $roles = isset($_POST['roles']) ? implode(',', $_POST['roles']) : '';

        if ($sup_id == 0 || empty($name)) {
            echo json_encode(['status' => 'error', 'msg' => 'กรุณากรอกข้อมูลให้ครบ']);
            exit;
        }

        $file_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $upload_dir = 'uploads/budgets/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $filename = 'budget_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                $file_path = $target;
            }
        }

        if (empty($id)) {
            // โหมดเพิ่มใหม่ - บังคับสถานะเป็น pending
            $sql = "INSERT INTO budget_types (sup_id, name, budget_amount, status, roles, is_active, file_path) 
                    VALUES ($sup_id, '$name', $budget_amount, 'pending', '$roles', 1, " . ($file_path ? "'$file_path'" : "NULL") . ")";
        } else {
            // โหมดแก้ไข
            $update_file = $file_path ? ", file_path = '$file_path'" : "";
            $sql = "UPDATE budget_types SET sup_id = $sup_id, name = '$name', budget_amount = $budget_amount, roles = '$roles' $update_file WHERE id = " . intval($id);
        }
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => mysqli_error($conn)]);
        }
        exit;
    }

    // API: ส่งออก Excel (CSV)
    if ($_GET['action'] == 'export_excel') {
        $sql = "SELECT s.company_name, bt.name, bt.budget_amount as initial_budget,
                (bt.budget_amount + COALESCE((SELECT SUM(a.amount) FROM budget_adjustments a WHERE a.budget_type_id = bt.id AND a.status = 'approved'), 0)) as total_budget,
                (SELECT SUM(p.grand_total) FROM pr p WHERE p.budget_type_id = bt.id AND p.status = 'approved' AND p.deleted_at IS NULL) as total_spent
                FROM budget_types bt 
                JOIN suppliers s ON bt.sup_id = s.id 
                ORDER BY s.company_name ASC, bt.name ASC";
        $result = mysqli_query($conn, $sql);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=budget_report_'.date('Ymd').'.csv');
        $output = fopen('php://output', 'w');
        
        // เพิ่ม BOM เพื่อให้ Excel อ่านภาษาไทยได้
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ["บริษัท", "ประเภทงบประมาณ", "งบตั้งต้น", "ยอดใช้ไป", "คงเหลือปัจจุบัน"]);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $balance = $row['total_budget'] - ($row['total_spent'] ?: 0);
            fputcsv($output, [
                $row['company_name'], $row['name'], $row['initial_budget'], 
                $row['total_spent'] ?: 0, $balance
            ]);
        }
        fclose($output);
        exit;
    }

    // API: ดึงประวัติการปรับงบ
    if ($_GET['action'] == 'fetch_history') {
        $budget_type_id = intval($_GET['budget_type_id'] ?? 0);
        $sql = "SELECT a.*, u.name as requester_name
                FROM budget_adjustments a
                LEFT JOIN users u ON a.created_by = u.id
                WHERE a.budget_type_id = $budget_type_id
                ORDER BY a.created_at DESC";
        $result = mysqli_query($conn, $sql);
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        echo json_encode($data);
        exit;
    }

    // API: ยกเลิก/ลบ pending adjustment
    if ($_GET['action'] == 'cancel_adjust') {
        $id = intval($_POST['id'] ?? 0);
        $sql = "DELETE FROM budget_adjustments WHERE id = $id AND status = 'pending'";
        if (mysqli_query($conn, $sql) && mysqli_affected_rows($conn) > 0) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'ไม่สามารถยกเลิกได้ หรือรายการนี้ไม่อยู่ในสถานะรออนุมัติ']);
        }
        exit;
    }

    // API: ปรับปรุงยอดงบประมาณ
    if ($_GET['action'] == 'adjust') {
        $budget_type_id = intval($_POST['budget_type_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');

        $file_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $upload_dir = 'uploads/budgets/adjustments/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $filename = 'adj_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                $file_path = $target;
            }
        }

        $user_id = intval($_SESSION['user_id'] ?? 0);
        if ($budget_type_id > 0 && $amount != 0) {
            $sql = "INSERT INTO budget_adjustments (budget_type_id, amount, adjustment_type, reason, status, created_by, file_path) 
                    VALUES ($budget_type_id, $amount, '" . ($amount >= 0 ? 'addition' : 'reduction') . "', '$reason', 'pending', $user_id, " . ($file_path ? "'$file_path'" : "NULL") . ")";
            if (mysqli_query($conn, $sql)) {
                echo json_encode(['status' => 'success', 'msg' => 'บันทึกคำขอปรับงบประมาณเรียบร้อย รอการอนุมัติ']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => mysqli_error($conn)]);
            }
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'ข้อมูลไม่ถูกต้อง']);
        }
        exit;
    }

    // API: ลบข้อมูล
    if ($_GET['action'] == 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $sql = "DELETE FROM budget_types WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => mysqli_error($conn)]);
        }
        exit;
    }
}

// 3. เริ่มส่วนแสดงผล HTML
include('header.php'); 
?>

<div class="container p-0">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-slate-800">ตั้งค่างบประมาณ</h2>
        <div class="flex gap-2">
            <a href="?action=export_excel" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-2xl transition shadow-lg text-sm font-semibold">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
            <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-2xl transition shadow-lg text-sm font-semibold">
                <i class="fas fa-plus mr-1"></i> เพิ่มประเภทงบ
            </button>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">บริษัท / Supplier</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">ชื่อประเภทงบประมาณ</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">งบตั้งต้น</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">คงเหลือปัจจุบัน</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">สิทธิ์ที่เห็นได้</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody id="budgetTableBody" class="divide-y divide-slate-50">
                </tbody>
        </table>
    </div>
</div>

<div id="budgetModal" class="fixed inset-0 bg-slate-900/60 hidden backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 id="modalTitle" class="font-bold text-slate-700 text-lg">เพิ่มรายการ</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form id="budgetForm" class="p-6 space-y-5">
            <input type="hidden" name="id" id="budget-id">
            
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">เลือกบริษัท <span class="text-red-500">*</span></label>
                <select name="sup_id" id="budget-sup-id" required class="w-full border-slate-200 rounded-xl p-3 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white">
                    <option value="">-- เลือกบริษัท --</option>
                    <?php
                    $sups = mysqli_query($conn, "SELECT id, company_name FROM suppliers ORDER BY company_name ASC");
                    while($s = mysqli_fetch_assoc($sups)) {
                        echo "<option value='{$s['id']}'>{$s['company_name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">ชื่อประเภทงบประมาณ <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="budget-name" required placeholder="เช่น งบประมาณปี 2567, งบซ่อมบำรุงประจำปี"
                       class="w-full border-slate-200 rounded-xl p-3 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">จำนวนเงินงบประมาณ <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" name="budget_amount" id="budget-amount" required placeholder="0.00"
                       class="w-full border-slate-200 rounded-xl p-3 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">กำหนดสิทธิ์ (Roles)</label>
                <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto p-3 border border-slate-200 rounded-xl bg-slate-50">
                    <?php
                    $roles_list = [
                        'staff' => 'Staff', 'admin' => 'Admin', 'gm' => 'GM', 'mgr' => 'Manager',
                        'mgr2' => 'Manager 2', 'acc' => 'Account', 'procure' => 'Procurement',
                        'fin' => 'Finance', 'viewer' => 'Viewer', 'gmhok' => 'GM HOK',
                        'gmhr' => 'GM HR', 'staff_hr' => 'Staff HR', 'gmacc' => 'GM ACC',
                        'gmshotel' => 'GM SHotel', 'gmmanonta' => 'GM Manonta',
                        'gmnijuni' => 'GM Nijuni', 'hok' => 'HOK', 'staff_shotel' => 'Staff SHotel',
                        'staff_manonta' => 'Staff Manonta', 'staff_nijuni' => 'Staff Nijuni'
                    ];
                    foreach($roles_list as $val => $label): ?>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="roles[]" value="<?= $val ?>" class="role-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs text-slate-600"><?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="text-[10px] text-slate-400 mt-1 italic">* หากไม่เลือกเลย จะถือว่าเห็นได้ทุกคน</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">ไฟล์แนบเอกสาร</label>
                <input type="file" name="attachment" id="budget-attachment"
                       class="w-full border-slate-200 rounded-xl p-2.5 text-xs border focus:ring-2 focus:ring-indigo-500 outline-none transition bg-slate-50">
                <p class="text-[10px] text-slate-400 mt-1 italic">* ถ้าแก้ไขและไม่เลือกไฟล์ใหม่ จะใช้ไฟล์เดิม</p>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeModal()" class="flex-1 px-4 py-3 border border-slate-200 text-slate-600 rounded-2xl hover:bg-slate-50 font-medium transition">ยกเลิก</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-2xl hover:bg-indigo-700 font-bold shadow-lg shadow-indigo-100 transition">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {
    fetchBudget();

    $('#budgetForm').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        $.ajax({
            url: '?action=save',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    closeModal();
                    fetchBudget();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + res.msg);
                }
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                alert('Server Error: ไม่สามารถบันทึกได้');
            }
        });
    });
});

const USER_ROLE = '<?= $_SESSION['role'] ?? '' ?>';

function fetchBudget() {
    $.get('?action=fetch', function(data) {
        let html = '';
        if(!data || data.length === 0) {
            html = '<tr><td colspan="7" class="p-12 text-center text-slate-400 font-medium">ไม่พบข้อมูลประเภทงบประมาณ</td></tr>';
        } else {
            data.forEach(item => {
                const initial = parseFloat(item.budget_amount || 0);
                const total = parseFloat(item.total_budget || 0);
                const spent = parseFloat(item.total_spent || 0);
                const balance = total - spent;
                let rolesDisplay = item.roles ? item.roles.split(',').map(r => `<span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] mr-1">${r}</span>`).join('') : '<span class="text-slate-400 italic text-[10px]">ทั้งหมด</span>';

                // จัดการสถานะการอนุมัติ
                let statusBadge = '';
                let approveBtns = '';

                if (item.status === 'approved') {
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">
                        <i class="fas fa-check-circle mr-1"></i> อนุมัติแล้ว
                    </span>`;
                } else if (item.status === 'rejected') {
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 border border-red-200">
                        <i class="fas fa-times-circle mr-1"></i> ปฏิเสธ
                    </span>`;
                } else {
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-200">
                        <i class="fas fa-clock mr-1"></i> รออนุมัติ
                    </span>`;
                    
                    // ปุ่มอนุมัติสำหรับ GMACC และ MGR
                    if (USER_ROLE === 'gmacc' && !item.approved_by_gmacc) {
                        approveBtns += `<button onclick="event.stopPropagation(); approveBudget(${item.id}, 'GMACC')" class="text-[10px] bg-indigo-600 text-white px-2 py-1 rounded hover:bg-indigo-700 transition">บัญชีอนุมัติ</button>`;
                    }
                    if (USER_ROLE === 'mgr' && !item.approved_by_mgr) {
                        approveBtns += `<button onclick="event.stopPropagation(); approveBudget(${item.id}, 'MGR')" class="text-[10px] bg-emerald-600 text-white px-2 py-1 rounded hover:bg-emerald-700 transition">MGR อนุมัติ</button>`;
                    }
                    if (USER_ROLE === 'admin') {
                        approveBtns += `<button onclick="event.stopPropagation(); approveBudget(${item.id}, 'Admin')" class="text-[10px] bg-slate-800 text-white px-2 py-1 rounded hover:bg-black transition">Admin อนุมัติ</button>`;
                    }
                }

                const pendingCount = parseInt(item.pending_adjust_count || 0);
                const pendingBadge = pendingCount > 0 ? `<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-700 border border-amber-200 ml-1"><i class="fas fa-clock mr-0.5"></i>${pendingCount}</span>` : '';

                const clickableId = `budget-${item.id}`;
                const fileHtml = item.file_path ? `<a href="${item.file_path}" target="_blank" class="text-[9px] text-indigo-500 hover:underline"><i class="fas fa-paperclip mr-0.5"></i>ดูไฟล์แนบ</a>` : '';
                
                html += `
            <tr class="hover:bg-slate-50 transition text-sm cursor-pointer" onclick="toggleSubRows(${item.id}, this)">
            <td class="p-4 font-bold text-slate-700">${item.company_name}</td>
            <td class="p-4">
                <div class="flex flex-col gap-1">
                    <span class="text-slate-600 font-bold"><i class="fas fa-chevron-right text-[8px] mr-1.5 text-slate-300 transition-transform" id="icon-${item.id}"></i>${item.name}${pendingBadge}</span>
                    <div class="flex gap-2 items-center">
                        ${statusBadge}
                        <div class="flex gap-1">${approveBtns}</div>
                        ${fileHtml}
                    </div>
                    ${item.gmacc_name ? `<span class="text-[9px] text-slate-400">บัญชี: ${item.gmacc_name}</span>` : ''}
                    ${item.mgr_name ? `<span class="text-[9px] text-slate-400">ผู้จัดการ: ${item.mgr_name}</span>` : ''}
                </div>
            </td>
            <td class="p-4 text-slate-600 text-right font-mono">${initial.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            <td class="p-4 text-right font-mono font-bold ${balance < 0 ? 'text-red-600' : 'text-indigo-600'}">
                ${balance.toLocaleString(undefined, {minimumFractionDigits: 2})}
            </td>
            <td class="p-4">${rolesDisplay}</td>
            <td class="p-4 text-center space-x-1 flex items-center justify-center">
                <button onclick="event.stopPropagation(); showHistory(${item.id}, &quot;${item.name.replace(/"/g, '&quot;')}&quot;)" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-600 hover:text-white transition shadow-sm" title="ประวัติ">
                    <i class="fas fa-history text-xs"></i>
                </button>
                <button onclick="event.stopPropagation(); openAdjustModal(${item.id}, &quot;${item.name.replace(/"/g, '&quot;')}&quot;)" class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition shadow-sm" title="ปรับปรุงยอด">
                    <i class="fas fa-plus-circle text-xs"></i>
                </button>
                <button onclick='event.stopPropagation(); editBudget(${JSON.stringify(item)})' class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white transition shadow-sm" title="แก้ไข">
                    <i class="fas fa-edit text-xs"></i>
                </button>
                <button onclick="event.stopPropagation(); deleteBudget(${item.id})" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition shadow-sm" title="ลบ">
                    <i class="fas fa-trash-alt text-xs"></i>
                </button>
            </td>
            </tr>`;
            });        }
        $('#budgetTableBody').html(html);
    });
}

let expandedBudgetId = null;

function toggleSubRows(id, row) {
    const icon = document.getElementById('icon-' + id);
    const $row = $(row);

    if (expandedBudgetId === id) {
        // ปิด
        $row.nextAll('tr.sub-row-' + id).remove();
        icon.style.transform = 'rotate(0deg)';
        expandedBudgetId = null;
        return;
    }

    // ปิดอันเก่า
    if (expandedBudgetId) {
        const oldIcon = document.getElementById('icon-' + expandedBudgetId);
        if (oldIcon) oldIcon.style.transform = 'rotate(0deg)';
        $('tr.sub-row-' + expandedBudgetId).remove();
    }

    icon.style.transform = 'rotate(90deg)';
    expandedBudgetId = id;

    // โหลดประวัติการปรับงบทั้งหมด
    $.get('?action=fetch_history&budget_type_id=' + id, function(data) {
        if (data.length === 0) {
            // ถ้าไม่มีประวัติเลย ให้แสดงว่าไม่มี
            let subHtml = `
                <tr class="sub-row-${id} bg-slate-50 border-b border-slate-100">
                    <td colspan="6" class="p-4 text-center text-slate-400 text-xs italic">
                        ไม่มีประวัติการปรับปรุงยอดงบประมาณ
                    </td>
                </tr>`;
            $row.after(subHtml);
            return;
        }

        let subHtml = '';
        data.forEach(item => {
            const amount = parseFloat(item.amount || 0);
            const isAddition = amount >= 0;

            let statusBadge = '';
            if (item.status === 'approved') {
                statusBadge = `<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200"><i class="fas fa-check-circle mr-0.5"></i> อนุมัติ</span>`;
            } else if (item.status === 'rejected') {
                statusBadge = `<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-red-100 text-red-700 border border-red-200"><i class="fas fa-times-circle mr-0.5"></i> ปฏิเสธ</span>`;
            } else {
                statusBadge = `<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-700 border border-amber-200"><i class="fas fa-clock mr-0.5"></i> รออนุมัติ</span>`;
            }

            const cancelBtn = item.status === 'pending' 
                ? `<button onclick="event.stopPropagation(); cancelAdjust(${item.id})" class="text-[9px] bg-rose-50 text-rose-500 px-2 py-0.5 rounded hover:bg-rose-500 hover:text-white transition"><i class="fas fa-times mr-0.5"></i>ยกเลิก</button>`
                : '';
            
            const fileHtml = item.file_path ? `<a href="${item.file_path}" target="_blank" class="text-[9px] text-indigo-500 hover:underline"><i class="fas fa-paperclip mr-0.5"></i>ดูไฟล์แนบ</a>` : '';

            subHtml += `
                <tr class="sub-row-${id} ${item.status === 'pending' ? 'bg-amber-50/40' : 'bg-slate-50/50'} border-b border-slate-100">
                    <td colspan="6" class="p-0">
                        <div class="flex items-center gap-4 px-10 py-2.5 text-xs">
                            <span class="text-slate-400 font-medium w-[80px] shrink-0">วันที่:</span>
                            <span class="text-slate-500 w-[120px] shrink-0">${item.created_at}</span>
                            <span class="text-slate-400 font-medium w-[60px] shrink-0">ผู้ขอ:</span>
                            <span class="text-slate-600 font-bold w-[120px]">${item.requester_name || '-'}</span>
                            <span class="text-slate-400 font-medium w-[60px] shrink-0">จำนวน:</span>
                            <span class="font-mono font-bold w-[120px] ${isAddition ? 'text-emerald-600' : 'text-red-600'}">${isAddition ? '+' : ''}${amount.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                            <span class="text-slate-400 font-medium w-[50px] shrink-0">เหตุผล:</span>
                            <span class="text-slate-500 flex-1 truncate" title="${(item.reason || '').replace(/"/g, '&quot;')}">${item.reason || '-'}</span>
                            <span class="text-slate-400 font-medium w-[50px] shrink-0">สถานะ:</span>
                            <span class="w-[80px]">${statusBadge}</span>
                            <span class="w-[80px]">${fileHtml}</span>
                            <div class="w-[60px] flex justify-end">${cancelBtn}</div>
                        </div>
                    </td>
                </tr>`;
        });

        $row.after(subHtml);
    });
}

function approveBudget(id, type) {
    if (confirm(`ยืนยันการอนุมัติงบประมาณในส่วนของ ${type}?`)) {
        $.post('?action=approve', { id: id }, function(res) {
            if (res.status === 'success') {
                fetchBudget();
            } else {
                alert(res.msg);
            }
        });
    }
}


let currentHistoryBudgetId = 0;

function showHistory(id, name) {
    currentHistoryBudgetId = id;
    $.get('?action=fetch_history&budget_type_id=' + id, function(data) {
        let html = '';
        if(data.length === 0) {
            html = '<tr><td colspan="6" class="p-4 text-center text-slate-400 text-xs">ไม่พบประวัติ</td></tr>';
        } else {
            data.forEach(item => {
                const color = item.amount >= 0 ? 'text-emerald-600' : 'text-red-600';

                let statusBadge = '';
                if (item.status === 'approved') {
                    statusBadge = `<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200"><i class="fas fa-check-circle mr-0.5"></i> อนุมัติ</span>`;
                } else if (item.status === 'rejected') {
                    statusBadge = `<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-red-100 text-red-700 border border-red-200"><i class="fas fa-times-circle mr-0.5"></i> ปฏิเสธ</span>`;
                } else {
                    statusBadge = `<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-700 border border-amber-200"><i class="fas fa-clock mr-0.5"></i> รออนุมัติ</span>`;
                }

                const cancelBtn = item.status === 'pending'
                    ? `<button onclick="cancelAdjust(${item.id})" class="text-[9px] bg-rose-50 text-rose-500 px-2 py-0.5 rounded hover:bg-rose-500 hover:text-white transition"><i class="fas fa-times mr-0.5"></i>ยกเลิก</button>`
                    : '';
                const fileHtml = item.file_path ? `<a href="${item.file_path}" target="_blank" class="text-indigo-500 hover:text-indigo-700"><i class="fas fa-paperclip"></i></a>` : '';

                html += `
                    <tr class="text-xs border-b">
                        <td class="p-2 text-slate-500">${item.created_at}</td>
                        <td class="p-2 font-bold text-slate-600">${item.requester_name || '-'}</td>
                        <td class="p-2 ${color} font-bold text-right">${parseFloat(item.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                        <td class="p-2 text-slate-600 max-w-[150px] truncate" title="${(item.reason || '').replace(/"/g, '&quot;')}">${item.reason || '-'}</td>
                        <td class="p-2 text-center">${statusBadge}</td>
                        <td class="p-2 text-center flex items-center justify-center gap-2">
                            ${fileHtml}
                            ${cancelBtn}
                        </td>
                    </tr>
                `;
            });
        }
        $('#history-table-body').html(html);
        $('#history-budget-name').text(name);
        $('#historyModal').removeClass('hidden');
    });
}

function openModal() {
    $('#budgetForm')[0].reset();
    $('.role-checkbox').prop('checked', false);
    $('#budget-id').val('');
    $('#modalTitle').text('เพิ่มประเภทงบประมาณ');
    $('#budgetModal').removeClass('hidden');
}

function closeModal() {
    $('#budgetModal').addClass('hidden');
}

function editBudget(item) {
    $('#modalTitle').text('แก้ไขประเภทงบประมาณ');
    $('#budget-id').val(item.id);
    $('#budget-sup-id').val(item.sup_id);
    $('#budget-name').val(item.name);
    $('#budget-amount').val(item.budget_amount);

    $('.role-checkbox').prop('checked', false);
    if(item.roles) {
        let roles = item.roles.split(',');
        roles.forEach(r => {
            $(`.role-checkbox[value="${r}"]`).prop('checked', true);
        });
    }

    $('#budgetModal').removeClass('hidden');
}

function deleteBudget(id) {
    if(confirm('ยืนยันการลบงบประมาณประเภทนี้?')) {
        $.post('?action=delete', { id: id }, function(res) {
            if(res.status === 'success') {
                fetchBudget();
            } else {
                alert(res.msg);
            }
        });
    }
}

function cancelAdjust(id) {
    if(!confirm('ยกเลิกรายการปรับงบประมาณนี้?')) return;
    $.post('?action=cancel_adjust', { id: id }, function(res) {
        if(res.status === 'success') {
            fetchBudget();
            $('#historyModal').addClass('hidden');
            if (currentHistoryBudgetId > 0) {
                showHistory(currentHistoryBudgetId, $('#history-budget-name').text());
            }
            // รีเฟรช sub-row ถ้ากำลังเปิดอยู่
            if (expandedBudgetId) {
                $('tr.sub-row-' + expandedBudgetId).remove();
                const oldIcon = document.getElementById('icon-' + expandedBudgetId);
                if (oldIcon) oldIcon.style.transform = 'rotate(0deg)';
                const bid = expandedBudgetId;
                const oldRow = document.querySelector(`tr[onclick*="toggleSubRows(${bid}"]`);
                expandedBudgetId = null;
                if (oldRow) toggleSubRows(bid, oldRow);
            }
        } else {
            alert(res.msg);
        }
    });
}
</script>

<div id="historyModal" class="fixed inset-0 bg-slate-900/60 hidden backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-bold text-slate-700 text-lg">ประวัติการปรับงบ</h3>
            <button onclick="$('#historyModal').addClass('hidden')" class="text-slate-400 hover:text-slate-600 transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-6">
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-sm font-bold text-slate-600 mb-4" id="history-budget-name"></div>
            <div class="max-h-64 overflow-y-auto">
                <table class="w-full text-left">
                    <thead class="text-[10px] uppercase text-slate-400 sticky top-0 bg-white">
                        <tr><th class="p-2">วันที่</th><th class="p-2">ผู้ขอ</th><th class="p-2 text-right">จำนวน</th><th class="p-2">เหตุผล</th><th class="p-2 text-center">สถานะ</th><th class="p-2 text-center">จัดการ</th></tr>
                    </thead>
                    <tbody id="history-table-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="adjustModal" class="fixed inset-0 bg-slate-900/60 hidden backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-bold text-slate-700 text-lg">ปรับปรุงยอดงบประมาณ</h3>
            <button onclick="$('#adjustModal').addClass('hidden')" class="text-slate-400 hover:text-slate-600 transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form id="adjustForm" class="p-6 space-y-4">
            <input type="hidden" name="budget_type_id" id="adjust-budget-id">
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-sm font-bold text-slate-600" id="adjust-budget-name"></div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">ยอดเงิน (+/-) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" name="amount" required class="w-full border-slate-200 rounded-xl p-3 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">เหตุผล</label>
                <input type="text" name="reason" class="w-full border-slate-200 rounded-xl p-3 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">ไฟล์แนบ</label>
                <input type="file" name="attachment" class="w-full border-slate-200 rounded-xl p-2.5 text-xs border focus:ring-2 focus:ring-indigo-500 outline-none transition bg-slate-50">
            </div>
            <button type="submit" class="w-full py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition">บันทึกยอดปรับปรุง</button>
        </form>
    </div>
</div>

<script>
function openAdjustModal(id, name) {
    $('#adjustForm')[0].reset();
    $('#adjust-budget-id').val(id);
    $('#adjust-budget-name').text(name);
    $('#adjustModal').removeClass('hidden');
}

$('#adjustForm').on('submit', function(e) {
    e.preventDefault();
    let formData = new FormData(this);
    $.ajax({
        url: '?action=adjust',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res) {
            if(res.status === 'success') {
                $('#adjustModal').addClass('hidden');
                fetchBudget();
            } else {
                alert(res.msg);
            }
        },
        error: function(xhr) {
            console.error(xhr.responseText);
            alert('Server Error: ไม่สามารถบันทึกได้');
        }
    });
});
</script>

<?php include 'footer.php'; ?>