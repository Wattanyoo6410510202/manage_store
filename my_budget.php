<?php
require 'config.php';
require_once __DIR__ . '/budget_projects_lib.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = (string)($_SESSION['role'] ?? '');

if (isset($_GET['action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    if (!isset($_SESSION['user'])) {
        echo json_encode(['status' => 'error', 'msg' => 'กรุณาเข้าสู่ระบบใหม่']);
        exit;
    }

    // ข้อมูลสำหรับฟอร์ม: งบที่อนุมัติแล้ว + โครงการที่อนุมัติแล้ว (ใช้อ้างอิงโครงการเพิ่มเติม)
    if ($_GET['action'] === 'form_data') {
        // ตอนแก้ไข ไม่นับยอดของโครงการที่กำลังแก้ในยอดที่กันไปแล้ว
        $editing_id = (int)($_GET['editing_id'] ?? 0);
        $allocated_sql = $editing_id > 0
            ? str_replace("bp.status <> 'rejected'", "bp.status <> 'rejected' AND bp.id <> $editing_id", BUDGET_PROJECT_ALLOCATED_SQL)
            : BUDGET_PROJECT_ALLOCATED_SQL;
        $sql = "SELECT bt.id, bt.name, bt.sup_id, s.company_name,
                " . BUDGET_TOTAL_SQL . " as total_budget,
                $allocated_sql as allocated,
                " . budget_general_pr_sql() . " as general_pr
                FROM budget_types bt
                JOIN suppliers s ON bt.sup_id = s.id
                WHERE bt.status = 'approved'
                ORDER BY s.company_name ASC, bt.name ASC";
        $budgets = mysqli_fetch_all(mysqli_query($conn, $sql), MYSQLI_ASSOC);
        foreach ($budgets as &$b) {
            $b['company_name'] = supplier_display_name($b['company_name']);
            $b['available'] = (float)$b['total_budget'] - (float)$b['allocated'] - (float)$b['general_pr'];
        }
        unset($b);

        $parents = mysqli_fetch_all(mysqli_query($conn,
            "SELECT bp.id, bp.project_no, bp.name, bp.budget_type_id, bp.department, bp.division, bp.responsible_name, s.company_name
             FROM budget_projects bp
             JOIN budget_types bt ON bp.budget_type_id = bt.id
             JOIN suppliers s ON bt.sup_id = s.id
             WHERE bp.status = 'approved' AND bp.project_type = 'new' AND bp.id <> $editing_id ORDER BY bp.created_at DESC"), MYSQLI_ASSOC);
        foreach ($parents as &$pr) {
            $pr['company_name'] = supplier_display_name($pr['company_name']);
        }
        unset($pr);

        // บริษัททั้งหมด (รายการเดียวกับหน้าตั้งค่าประเภทงบประมาณ)
        $companies = mysqli_fetch_all(mysqli_query($conn, "SELECT id, company_name FROM suppliers ORDER BY company_name ASC"), MYSQLI_ASSOC);
        foreach ($companies as &$c) {
            $c['company_name'] = supplier_display_name($c['company_name']);
        }
        unset($c);

        // ผู้ใช้ทั้งหมดสำหรับเลือกเป็นผู้ลงนาม
        $users = mysqli_fetch_all(mysqli_query($conn, "SELECT id, name, role FROM users ORDER BY name ASC"), MYSQLI_ASSOC);

        echo json_encode(['budgets' => $budgets, 'parents' => $parents, 'companies' => $companies, 'users' => $users]);
        exit;
    }

    // รายละเอียดโครงการ + รายการค่าใช้จ่าย (ใช้ทั้งหน้านี้และหน้ารออนุมัติ)
    if ($_GET['action'] === 'detail') {
        $project = budget_project_fetch($conn, (int)($_GET['id'] ?? 0));
        if (!$project) {
            echo json_encode(['status' => 'error', 'msg' => 'ไม่พบโครงการ']);
            exit;
        }
        $project['company_name'] = supplier_display_name($project['company_name']);
        $project['can_edit'] = budget_project_can_edit($project, $user_id, $user_role);
        $project['can_delete'] = budget_project_can_delete($project, $user_id, $user_role);
        echo json_encode(['status' => 'success', 'project' => $project]);
        exit;
    }

    if (in_array($_GET['action'], ['create', 'update'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = 0;
        if ($_GET['action'] === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $existing = budget_project_fetch($conn, $id);
            if (!$existing || !budget_project_can_edit($existing, $user_id, $user_role)) {
                echo json_encode(['status' => 'error', 'msg' => 'แก้ไขโครงการนี้ไม่ได้ (อนุมัติแล้ว หรือไม่มีสิทธิ์)']);
                exit;
            }
        }
        echo json_encode(budget_project_save($conn, $id, $_POST, $_FILES, $user_id));
        exit;
    }

    if ($_GET['action'] === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $existing = budget_project_fetch($conn, $id);
        if (!$existing || !budget_project_can_delete($existing, $user_id, $user_role)) {
            echo json_encode(['status' => 'error', 'msg' => 'ลบโครงการนี้ไม่ได้ (อนุมัติแล้ว หรือไม่มีสิทธิ์)']);
            exit;
        }
        echo json_encode(budget_project_delete($conn, $id));
        exit;
    }

    echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
    exit;
}

// โครงการที่ฉันสร้าง
$projects = mysqli_fetch_all(mysqli_query($conn,
    "SELECT bp.*, bt.name as budget_name, s.company_name,
            (SELECT COUNT(*) FROM budget_project_signers x WHERE x.project_id = bp.id AND x.signed_at IS NOT NULL) as signed_count,
            " . budget_project_pr_used_sql() . " as pr_used
     FROM budget_projects bp
     JOIN budget_types bt ON bp.budget_type_id = bt.id
     JOIN suppliers s ON bt.sup_id = s.id
     WHERE bp.created_by = $user_id
     ORDER BY bp.created_at DESC"), MYSQLI_ASSOC);

// ผู้ที่ถึงคิวเซ็นของแต่ละโครงการ (แสดงใต้สถานะ)
$next_signers = [];
foreach ($projects as $p) {
    $next = budget_project_next_signers($p, budget_project_signers($conn, (int)$p['id']));
    $next_signers[(int)$p['id']] = array_map(fn($s) => $s['user_name'] . ' (' . $s['label'] . ')', $next);
}

// โครงการที่ถึงคิวฉันเซ็น
$to_sign = mysqli_fetch_all(mysqli_query($conn,
    "SELECT p.id, p.project_no, p.name, p.amount, p.request_date, bt.name as budget_name, sup.company_name,
            GROUP_CONCAT(s.role_key) as role_keys, u.name as creator_name
     FROM budget_project_signers s
     JOIN budget_projects p ON p.id = s.project_id
     JOIN budget_types bt ON p.budget_type_id = bt.id
     JOIN suppliers sup ON bt.sup_id = sup.id
     LEFT JOIN users u ON p.created_by = u.id
     WHERE " . budget_project_my_turn_sql($user_id) . "
     GROUP BY p.id
     ORDER BY p.created_at ASC"), MYSQLI_ASSOC);

$sum_approved = 0;
$sum_pending = 0;
foreach ($projects as $p) {
    if ($p['status'] === 'approved') $sum_approved += (float)$p['amount'];
    if ($p['status'] === 'pending') $sum_pending += (float)$p['amount'];
}

$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM users WHERE id = $user_id"));

include('header.php');
?>

<div class="container p-0">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-slate-800">โครงการของฉัน</h2>
        <button type="button" onclick="openProjectModal()"
            class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-indigo-700 transition shadow-sm">
            <i class="fas fa-plus mr-1"></i> ขออนุมัติโครงการ
        </button>
    </div>

    <?php if ($to_sign): ?>
    <div class="bg-indigo-50 border border-indigo-200 rounded-3xl p-4 mb-4">
        <h3 class="font-bold text-indigo-800 mb-3"><i class="fas fa-signature mr-1"></i> รอคุณเซ็น (<?= count($to_sign) ?>)</h3>
        <div class="space-y-2">
            <?php foreach ($to_sign as $t): ?>
            <div class="bg-white rounded-2xl border border-indigo-100 p-3 flex flex-wrap items-center gap-3 text-sm">
                <div class="flex-1 min-w-[200px]">
                    <div class="font-bold text-slate-700"><?= htmlspecialchars(($t['project_no'] ? $t['project_no'] . ' · ' : '') . $t['name']) ?></div>
                    <div class="text-[11px] text-slate-400">
                        <?= htmlspecialchars(supplier_display_name($t['company_name'])) ?> · ผู้ขอ <?= htmlspecialchars($t['creator_name'] ?? '-') ?> · <?= htmlspecialchars($t['request_date']) ?>
                    </div>
                </div>
                <div class="text-[11px] font-bold text-indigo-600">
                    <?= htmlspecialchars(implode(', ', array_map(fn($r) => BUDGET_PROJECT_SIGNER_ROLES[$r]['label'] ?? $r, explode(',', $t['role_keys'])))) ?>
                </div>
                <div class="font-mono font-bold text-slate-700"><?= number_format((float)$t['amount'], 2) ?></div>
                <a href="budget_project_print.php?id=<?= (int)$t['id'] ?>" target="_blank"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-indigo-700"><i class="fas fa-pen-nib mr-1"></i>เปิดเอกสารเพื่อเซ็น</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500 font-bold">จำนวนโครงการ</p>
            <p class="text-lg font-bold text-slate-800"><?= count($projects) ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500 font-bold">ค่าใช้จ่ายที่อนุมัติแล้ว</p>
            <p class="text-lg font-bold text-emerald-600 font-mono"><?= number_format($sum_approved, 2) ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500 font-bold">ค่าใช้จ่ายที่รออนุมัติ</p>
            <p class="text-lg font-bold text-amber-600 font-mono"><?= number_format($sum_pending, 2) ?></p>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">เลขที่ / วันที่</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">ชื่อโครงการ</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">อ้างอิงงบประมาณ</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">ค่าใช้จ่าย</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">สถานะ</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php if (!$projects): ?>
                <tr><td colspan="6" class="p-12 text-center text-slate-400 font-medium">ยังไม่มีโครงการ กด "ขออนุมัติโครงการ" เพื่อเริ่มต้น</td></tr>
            <?php endif; ?>
            <?php foreach ($projects as $p): ?>
                <tr class="hover:bg-slate-50 transition text-sm">
                    <td class="p-4">
                        <div class="font-bold text-slate-700"><?= htmlspecialchars($p['project_no'] ?: '-') ?></div>
                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($p['request_date']) ?></div>
                    </td>
                    <td class="p-4">
                        <div class="font-bold text-slate-700"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="text-[10px] text-slate-400">
                            <?= $p['project_type'] === 'additional' ? 'โครงการเพิ่มเติม' : 'โครงการใหม่' ?>
                            <?php if ($p['department']): ?> · แผนก <?= htmlspecialchars($p['department']) ?><?php endif; ?>
                        </div>
                    </td>
                    <td class="p-4">
                        <div class="text-slate-600 font-bold"><?= htmlspecialchars($p['budget_name']) ?></div>
                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars(supplier_display_name($p['company_name'])) ?></div>
                    </td>
                    <td class="p-4 text-right">
                        <div class="font-mono font-bold text-indigo-600"><?= number_format((float)$p['amount'], 2) ?></div>
                        <?php if ($p['status'] === 'approved'): $remain = (float)$p['amount'] - (float)$p['pr_used']; ?>
                            <div class="text-[10px] text-slate-400">ใช้ไป (PR) <?= number_format((float)$p['pr_used'], 2) ?></div>
                            <div class="text-[10px] font-bold <?= $remain < 0 ? 'text-red-600' : 'text-emerald-600' ?>">คงเหลือ <?= number_format($remain, 2) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-center">
                        <div data-status-badge='<?= htmlspecialchars(json_encode([
                            'status' => $p['status'], 'approved_by_gmacc' => $p['approved_by_gmacc'], 'approved_by_mgr' => $p['approved_by_mgr'],
                        ]), ENT_QUOTES) ?>'></div>
                        <?php if (!empty($next_signers[(int)$p['id']])): ?>
                            <div class="text-[10px] text-slate-400 mt-1">รอเซ็น: <?= htmlspecialchars(implode(', ', $next_signers[(int)$p['id']])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <div class="flex justify-center gap-1">
                            <button type="button" onclick="viewProject(<?= (int)$p['id'] ?>, myProjectActionButtons)" title="รายละเอียด"
                                class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition"><i class="fas fa-eye text-xs"></i></button>
                            <a href="budget_project_print.php?id=<?= (int)$p['id'] ?>" target="_blank" title="พิมพ์"
                                class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition inline-flex items-center justify-center"><i class="fas fa-print text-xs"></i></a>
                            <?php if (budget_project_can_edit($p, $user_id, $user_role)): ?>
                            <button type="button" onclick="openProjectModal(<?= (int)$p['id'] ?>)" title="แก้ไข"
                                class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition"><i class="fas fa-pen text-xs"></i></button>
                            <?php endif; ?>
                            <?php if (budget_project_can_delete($p, $user_id, $user_role)): ?>
                            <button type="button" onclick="deleteProject(<?= (int)$p['id'] ?>, <?= htmlspecialchars(json_encode($p['name']), ENT_QUOTES) ?>)" title="ลบ"
                                class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition"><i class="fas fa-trash-alt text-xs"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: ใบขออนุมัติโครงการ -->
<div id="projectModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <form id="projectForm" class="bg-white rounded-3xl shadow-xl w-full max-w-3xl max-h-[92vh] overflow-y-auto" enctype="multipart/form-data">
        <div class="flex justify-between items-center p-5 border-b border-slate-100 sticky top-0 bg-white z-10">
            <h3 class="font-bold text-slate-800" id="projectModalTitle">ใบขออนุมัติโครงการ</h3>
            <button type="button" onclick="closeProjectModal()" class="text-slate-400 hover:text-slate-700"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5 space-y-4 text-sm">
            <input type="hidden" name="id" id="projectId" value="">
            <p id="editNotice" class="hidden text-xs bg-amber-50 text-amber-700 border border-amber-200 rounded-xl p-3">
                <i class="fas fa-info-circle mr-1"></i>เมื่อบันทึกการแก้ไข โครงการจะกลับไปเป็น "รออนุมัติ" ลายเซ็นเดิมจะถูกล้าง และต้องเซ็น/อนุมัติใหม่
            </p>
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 font-bold text-slate-700"><input type="radio" name="project_type" value="new" checked> โครงการใหม่</label>
                    <label class="flex items-center gap-2 font-bold text-slate-700"><input type="radio" name="project_type" value="additional"> โครงการเพิ่มเติม</label>
                </div>
                <label class="flex items-center gap-2 text-xs font-bold text-slate-600">วันที่
                    <input type="date" name="request_date" value="<?= date('Y-m-d') ?>" class="border border-slate-200 rounded-xl p-2">
                </label>
            </div>

            <div id="parentWrap" class="hidden">
                <label class="block text-xs font-bold text-slate-600 mb-1">โครงการเดิม <span class="text-red-500">*</span></label>
                <select name="parent_project_id" id="parentSelect" class="w-full border border-slate-200 rounded-xl p-2.5"></select>
            </div>

            <div id="budgetWrap" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">บริษัท <span class="text-red-500">*</span></label>
                    <select id="companySelect" class="w-full border border-slate-200 rounded-xl p-2.5">
                        <option value="">-- กำลังโหลด --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">อ้างอิงงบประมาณ <span class="text-red-500">*</span></label>
                    <select name="budget_type_id" id="budgetSelect" class="w-full border border-slate-200 rounded-xl p-2.5">
                        <option value="">-- เลือกบริษัทก่อน --</option>
                    </select>
                </div>
                <p id="companyHint" class="hidden sm:col-span-2 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-xl p-2 -mt-1">
                    บริษัทนี้ยังไม่มีงบประมาณที่อนุมัติแล้ว ต้องสร้างงบที่เมนู ตั้งค่า &gt; ตั้งค่าประเภทงบประมาณ และรออนุมัติก่อน
                </p>
            </div>
            <p id="budgetInfo" class="text-[11px] text-slate-500 -mt-2"></p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">แผนก</label>
                    <input type="text" name="department" maxlength="150" class="w-full border border-slate-200 rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">ฝ่าย</label>
                    <input type="text" name="division" maxlength="150" class="w-full border border-slate-200 rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">เลขที่โครงการ</label>
                    <input type="text" name="project_no" maxlength="50" placeholder="เช่น SS&F001" class="w-full border border-slate-200 rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">ผู้รับผิดชอบ</label>
                    <input type="text" name="responsible_name" maxlength="255" value="<?= htmlspecialchars($me['name'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl p-2.5">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">ชื่อโครงการ <span class="text-red-500">*</span></label>
                <input type="text" name="name" required maxlength="255" class="w-full border border-slate-200 rounded-xl p-2.5">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">วัตถุประสงค์</label>
                <textarea name="objectives" rows="2" placeholder="1 บรรทัดต่อ 1 ข้อ" class="w-full border border-slate-200 rounded-xl p-2.5"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">ระยะเวลาในการดำเนินการ (ประมาณ)</label>
                <div class="flex gap-2 w-60">
                    <input type="number" name="duration_value" min="1" class="w-24 border border-slate-200 rounded-xl p-2.5 text-right">
                    <select name="duration_unit" class="flex-1 border border-slate-200 rounded-xl p-2.5">
                        <option value="day">วัน</option>
                        <option value="month" selected>เดือน</option>
                        <option value="year">ปี</option>
                    </select>
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="text-xs font-bold text-slate-600">รายละเอียดของโครงการ <span class="text-red-500">*</span></label>
                    <button type="button" onclick="addItemRow()" class="text-[11px] text-indigo-600 font-bold hover:underline"><i class="fas fa-plus mr-1"></i>เพิ่มรายการ</button>
                </div>
                <table class="w-full border border-slate-200 rounded-xl overflow-hidden">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr><th class="p-2 w-12">ลำดับ</th><th class="p-2 text-left">รายละเอียด</th><th class="p-2 w-36 text-right">ราคา (บาท)</th><th class="w-8"></th></tr>
                    </thead>
                    <tbody id="itemRows"></tbody>
                    <tfoot class="bg-slate-50 font-bold">
                        <tr><td colspan="2" class="p-2 text-right text-slate-600">รวมค่าใช้จ่ายตลอดโครงการ</td><td class="p-2 text-right font-mono text-indigo-600" id="itemTotal">0.00</td><td></td></tr>
                    </tfoot>
                </table>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">ผลที่คาดว่าจะได้รับ</label>
                <textarea name="expected_results" rows="3" placeholder="1 บรรทัดต่อ 1 ข้อ" class="w-full border border-slate-200 rounded-xl p-2.5"></textarea>
            </div>

            <div class="border border-indigo-100 bg-indigo-50/40 rounded-2xl p-4">
                <div class="text-xs font-bold text-indigo-700 mb-1"><i class="fas fa-signature mr-1"></i>ผู้ลงนาม (เซ็นออนไลน์)</div>
                <p class="text-[11px] text-slate-500 mb-3">เซ็นตามลำดับ (1) → (2) → (3) → ผู้อนุมัติ → รับทราบ ฝ่ายบุคคล/ฝ่ายบัญชี · เว้นว่างช่องที่ไม่ต้องเซ็นได้ ยกเว้นผู้อนุมัติ</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach (BUDGET_PROJECT_SIGNER_ROLES as $role_key => $role_info): ?>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">
                            <?= in_array($role_key, ['responsible', 'checker', 'endorser'], true) ? '(' . $role_info['step'] . ') ' : '' ?><?= htmlspecialchars($role_info['label']) ?>
                            <?php if ($role_key === 'approver'): ?><span class="text-red-500">*</span><?php endif; ?>
                        </label>
                        <select name="signer[<?= $role_key ?>]" data-signer-role="<?= $role_key ?>" class="w-full border border-slate-200 rounded-xl p-2.5 bg-white"></select>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">ไฟล์แนบ (เช่น สแกนใบขออนุมัติที่เซ็นแล้ว)</label>
                <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" class="w-full">
                <p id="currentFile" class="hidden text-[11px] text-slate-500 mt-1"></p>
            </div>
        </div>
        <div class="flex justify-end gap-2 p-5 border-t border-slate-100 sticky bottom-0 bg-white">
            <button type="button" onclick="closeProjectModal()" class="px-4 py-2 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100">ยกเลิก</button>
            <button type="submit" id="projectSubmit" class="px-4 py-2 rounded-xl text-sm font-bold bg-indigo-600 text-white hover:bg-indigo-700">ส่งขออนุมัติ</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/budget_project_detail_modal.php'; ?>

<script>
let formData = {budgets: [], parents: []};
const fmt = n => Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
const escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

document.querySelectorAll('[data-status-badge]').forEach(td => {
    td.innerHTML = budgetProjectStatusBadge(JSON.parse(td.dataset.statusBadge));
});

// editId = 0 คือสร้างใหม่, มากกว่า 0 คือแก้ไขโครงการเดิม
async function openProjectModal(editId = 0) {
    const form = document.getElementById('projectForm');
    form.reset();
    document.getElementById('projectId').value = editId || '';
    document.getElementById('projectModalTitle').textContent = editId ? 'แก้ไขใบขออนุมัติโครงการ' : 'ใบขออนุมัติโครงการ';
    document.getElementById('projectSubmit').textContent = editId ? 'บันทึกการแก้ไข' : 'ส่งขออนุมัติ';
    document.getElementById('editNotice').classList.toggle('hidden', !editId);
    document.getElementById('currentFile').classList.add('hidden');
    document.getElementById('itemRows').innerHTML = '';
    if (!editId) for (let i = 0; i < 3; i++) addItemRow();
    recalcItems();
    toggleProjectType();

    const [data, detail] = await Promise.all([
        fetch('my_budget.php?action=form_data&editing_id=' + (editId || 0)).then(r => r.json()),
        editId ? fetch('my_budget.php?action=detail&id=' + editId).then(r => r.json()) : Promise.resolve(null),
    ]);
    if (detail && detail.status !== 'success') {
        Swal.fire({icon: 'error', title: 'ไม่สำเร็จ', text: detail.msg});
        return;
    }
    document.getElementById('projectModal').classList.remove('hidden');
    fillFormOptions(data);
    if (!editId) {
        // สร้างใหม่: เลือกบริษัทของผู้ใช้ไว้ก่อน (เปลี่ยนได้)
        const companySelect = document.getElementById('companySelect');
        companySelect.value = <?= json_encode((string)($_SESSION['sup_id'] ?? '')) ?>;
        if (!companySelect.value) companySelect.value = '';
        renderBudgetOptions();
        // ผู้สร้างเป็นผู้รับผิดชอบโครงการ (1) ไว้ก่อน
        document.querySelector('[data-signer-role="responsible"]').value = <?= json_encode((string)$user_id) ?>;
    }
    if (detail) fillProjectForm(detail.project);
}

function fillProjectForm(p) {
    const form = document.getElementById('projectForm');
    form.querySelector(`input[name="project_type"][value="${p.project_type}"]`).checked = true;
    toggleProjectType();
    ['request_date', 'department', 'division', 'project_no', 'name', 'responsible_name', 'objectives',
     'duration_value', 'duration_unit', 'expected_results'].forEach(f => { form[f].value = p[f] ?? ''; });
    if (p.project_type === 'additional') {
        form.parent_project_id.value = p.parent_project_id;
        form.parent_project_id.dispatchEvent(new Event('change'));
    } else {
        const budget = formData.budgets.find(b => String(b.id) === String(p.budget_type_id));
        document.getElementById('companySelect').value = budget ? budget.sup_id : '';
        renderBudgetOptions();
        form.budget_type_id.value = p.budget_type_id;
        showBudgetInfo(p.budget_type_id);
    }
    document.getElementById('itemRows').innerHTML = '';
    (p.items.length ? p.items : [{}]).forEach(it => {
        addItemRow();
        const tr = document.querySelector('#itemRows tr:last-child');
        tr.querySelector('[name="item_description[]"]').value = it.description ?? '';
        tr.querySelector('[name="item_amount[]"]').value = it.amount ?? '';
    });
    recalcItems();
    document.querySelectorAll('[data-signer-role]').forEach(sel => {
        const signer = (p.signers || {})[sel.dataset.signerRole];
        sel.value = signer ? signer.user_id : '';
    });
    const cur = document.getElementById('currentFile');
    if (p.file_path) {
        cur.innerHTML = `ไฟล์เดิม: <a href="${escapeHtml(p.file_path)}" target="_blank" class="text-indigo-600 hover:underline">เปิดไฟล์</a> (เลือกไฟล์ใหม่เพื่อแทนที่)`;
        cur.classList.remove('hidden');
    }
}

function deleteProject(id, name) {
    Swal.fire({
        icon: 'warning',
        title: 'ลบโครงการ?',
        text: name,
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#dc2626',
    }).then(result => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('id', id);
        fetch('my_budget.php?action=delete', {method: 'POST', body: fd})
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({icon: 'success', title: res.msg, timer: 1200, showConfirmButton: false}).then(() => location.reload());
                } else {
                    Swal.fire({icon: 'error', title: 'ไม่สำเร็จ', text: res.msg});
                }
            });
    });
}

// ปุ่มใน modal รายละเอียด (หน้าโครงการของฉัน)
function myProjectActionButtons(p) {
    let html = '';
    if (p.can_delete) html += `<button onclick="closeProjectDetail(); deleteProject(${p.id}, ${escapeHtml(JSON.stringify(p.name))})" class="px-4 py-2 rounded-xl text-sm font-bold bg-red-50 text-red-600 hover:bg-red-100"><i class="fas fa-trash-alt mr-1"></i>ลบ</button>`;
    if (p.can_edit) html += `<button onclick="closeProjectDetail(); openProjectModal(${p.id})" class="px-4 py-2 rounded-xl text-sm font-bold bg-amber-50 text-amber-600 hover:bg-amber-100"><i class="fas fa-pen mr-1"></i>แก้ไข</button>`;
    return html;
}

function fillFormOptions(data) {
    formData = data;
    const companySelect = document.getElementById('companySelect');
    companySelect.innerHTML = '<option value="">-- เลือกบริษัท --</option>' + data.companies.map(c => {
        const count = data.budgets.filter(b => String(b.sup_id) === String(c.id)).length;
        return `<option value="${c.id}">${escapeHtml(c.company_name)}${count ? '' : ' (ยังไม่มีงบ)'}</option>`;
    }).join('');
    renderBudgetOptions();
    document.querySelectorAll('[data-signer-role]').forEach(sel => {
        const empty = sel.dataset.signerRole === 'approver' ? '-- เลือกผู้อนุมัติ --' : '-- ไม่ต้องเซ็น --';
        sel.innerHTML = `<option value="">${empty}</option>` + data.users.map(u =>
            `<option value="${u.id}">${escapeHtml(u.name)} (${escapeHtml(u.role || '-')})</option>`).join('');
    });
    const parentSelect = document.getElementById('parentSelect');
    parentSelect.innerHTML = data.parents.length
        ? '<option value="">-- เลือกโครงการเดิม --</option>' + data.parents.map(p =>
            `<option value="${p.id}">${escapeHtml(p.project_no || '-')} — ${escapeHtml(p.name)} (${escapeHtml(p.company_name)})</option>`).join('')
        : '<option value="">ยังไม่มีโครงการที่อนุมัติแล้ว</option>';
}

// แสดงเฉพาะงบของบริษัทที่เลือก
function renderBudgetOptions() {
    const supId = document.getElementById('companySelect').value;
    const budgetSelect = document.getElementById('budgetSelect');
    const hint = document.getElementById('companyHint');
    showBudgetInfo(null);
    hint.classList.add('hidden');
    if (!supId) {
        budgetSelect.innerHTML = '<option value="">-- เลือกบริษัทก่อน --</option>';
        budgetSelect.disabled = true;
        return;
    }
    const budgets = formData.budgets.filter(b => String(b.sup_id) === String(supId));
    budgetSelect.disabled = !budgets.length;
    budgetSelect.innerHTML = budgets.length
        ? '<option value="">-- เลือกงบประมาณ --</option>' + budgets.map(b =>
            `<option value="${b.id}" ${b.available <= 0 ? 'disabled' : ''}>${escapeHtml(b.name)} (คงเหลือ ${fmt(b.available)})</option>`).join('')
        : '<option value="">บริษัทนี้ยังไม่มีงบประมาณที่อนุมัติแล้ว</option>';
    hint.classList.toggle('hidden', budgets.length > 0);
}
document.getElementById('companySelect').addEventListener('change', renderBudgetOptions);

function closeProjectModal() {
    document.getElementById('projectModal').classList.add('hidden');
}

function toggleProjectType() {
    const additional = document.querySelector('input[name="project_type"]:checked').value === 'additional';
    document.getElementById('parentWrap').classList.toggle('hidden', !additional);
    document.getElementById('budgetWrap').classList.toggle('hidden', additional);
    document.getElementById('budgetInfo').textContent = '';
}
document.querySelectorAll('input[name="project_type"]').forEach(r => r.addEventListener('change', toggleProjectType));

function showBudgetInfo(budgetId) {
    const b = formData.budgets.find(x => String(x.id) === String(budgetId));
    document.getElementById('budgetInfo').innerHTML = b
        ? `${escapeHtml(b.company_name)} · งบทั้งหมด ${fmt(b.total_budget)} · กันให้โครงการแล้ว ${fmt(b.allocated)} · PR นอกโครงการ ${fmt(b.general_pr)} · <b class="text-indigo-600">คงเหลือ ${fmt(b.available)}</b>`
        : '';
}
document.getElementById('budgetSelect').addEventListener('change', function () { showBudgetInfo(this.value); });

document.getElementById('parentSelect').addEventListener('change', function () {
    const p = formData.parents.find(x => String(x.id) === this.value);
    if (!p) { showBudgetInfo(null); return; }
    showBudgetInfo(p.budget_type_id);
    const form = document.getElementById('projectForm');
    ['department', 'division', 'responsible_name'].forEach(f => { if (!form[f].value && p[f]) form[f].value = p[f]; });
});

function addItemRow() {
    const tr = document.createElement('tr');
    tr.className = 'border-t border-slate-100';
    tr.innerHTML = `
        <td class="p-2 text-center text-slate-500 item-no"></td>
        <td class="p-1"><input type="text" name="item_description[]" maxlength="255" class="w-full border border-slate-200 rounded-lg p-2"></td>
        <td class="p-1"><input type="number" name="item_amount[]" min="0" step="0.01" class="w-full border border-slate-200 rounded-lg p-2 text-right font-mono" oninput="recalcItems()"></td>
        <td class="p-1 text-center"><button type="button" class="text-slate-300 hover:text-red-500" onclick="this.closest('tr').remove(); recalcItems();"><i class="fas fa-trash-alt"></i></button></td>`;
    document.getElementById('itemRows').appendChild(tr);
    recalcItems();
}

function recalcItems() {
    let total = 0;
    document.querySelectorAll('#itemRows tr').forEach((tr, i) => {
        tr.querySelector('.item-no').textContent = i + 1;
        total += parseFloat(tr.querySelector('input[name="item_amount[]"]').value) || 0;
    });
    document.getElementById('itemTotal').textContent = fmt(total);
}

document.getElementById('projectForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('projectSubmit');
    btn.disabled = true;
    const action = document.getElementById('projectId').value ? 'update' : 'create';
    fetch('my_budget.php?action=' + action, {method: 'POST', body: new FormData(this)})
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(() => location.reload());
            } else {
                Swal.fire({icon: 'error', title: 'ไม่สำเร็จ', text: res.msg});
            }
        })
        .catch(() => Swal.fire({icon: 'error', title: 'ไม่สำเร็จ', text: 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้'}))
        .finally(() => { btn.disabled = false; });
});
</script>

<?php include('footer.php'); ?>
