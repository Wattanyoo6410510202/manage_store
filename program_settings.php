<?php
require 'config.php';
require_once __DIR__ . '/company_programs_lib.php';

// เก็บรหัสผ่านของผู้ใช้ทุกคน จึงให้เฉพาะ admin
$is_admin = isset($_SESSION['user']) && ($_SESSION['role'] ?? '') === 'admin';

if (isset($_GET['action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    if (!$is_admin) {
        echo json_encode(['status' => 'error', 'msg' => 'เฉพาะ admin เท่านั้น']);
        exit;
    }
    $action = $_GET['action'];

    if ($action === 'list') {
        $rows = mysqli_fetch_all(mysqli_query($conn,
            "SELECT p.*, (SELECT COUNT(*) FROM company_program_accounts a WHERE a.program_id = p.id) as user_count
             FROM company_programs p ORDER BY p.sort_order ASC, p.name ASC"), MYSQLI_ASSOC);
        echo json_encode($rows);
        exit;
    }

    if ($action === 'save_program' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = !empty($_POST['is_active']) ? 1 : 0;

        if ($name === '' || !company_program_valid_url($url)) {
            echo json_encode(['status' => 'error', 'msg' => 'กรุณากรอกชื่อโปรแกรม และลิงก์ที่ขึ้นต้นด้วย http:// หรือ https://']);
            exit;
        }
        try {
            $old = $id > 0 ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT image_path FROM company_programs WHERE id = $id")) : null;
            if ($id > 0 && !$old) throw new RuntimeException('ไม่พบโปรแกรม');
            $image_path = $old['image_path'] ?? null;
            $new_image = company_program_save_image($_FILES['image'] ?? []);
            if ($new_image || !empty($_POST['remove_image'])) {
                company_program_delete_image($image_path);
                $image_path = $new_image;
            }
            if ($id > 0) {
                $stmt = mysqli_prepare($conn, "UPDATE company_programs SET name = ?, description = ?, url = ?, image_path = ?, sort_order = ?, is_active = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'ssssiii', $name, $description, $url, $image_path, $sort_order, $is_active, $id);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO company_programs (name, description, url, image_path, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'ssssii', $name, $description, $url, $image_path, $sort_order, $is_active);
            }
            mysqli_stmt_execute($stmt);
            echo json_encode(['status' => 'success', 'id' => $id ?: mysqli_insert_id($conn)]);
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'msg' => $e instanceof RuntimeException ? $e->getMessage() : 'บันทึกไม่สำเร็จ']);
        }
        exit;
    }

    if ($action === 'delete_program' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image_path FROM company_programs WHERE id = $id"));
        mysqli_query($conn, "DELETE FROM company_programs WHERE id = $id"); // บัญชีลบตาม ON DELETE CASCADE
        company_program_delete_image($old['image_path'] ?? null);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // ผู้ใช้ + บัญชีของโปรแกรม (admin เห็นรหัสผ่านเพื่อแก้ไข)
    if ($action === 'accounts') {
        $program_id = (int)($_GET['program_id'] ?? 0);
        $accounts = mysqli_fetch_all(mysqli_query($conn,
            "SELECT a.user_id, a.login_username, a.login_password_enc, a.note FROM company_program_accounts a
             JOIN users u ON u.id = a.user_id WHERE a.program_id = $program_id ORDER BY u.name ASC"), MYSQLI_ASSOC);
        foreach ($accounts as &$a) {
            $a['login_password'] = company_program_decrypt($a['login_password_enc']);
            unset($a['login_password_enc']);
        }
        unset($a);
        $users = mysqli_fetch_all(mysqli_query($conn, "SELECT id, name, role FROM users ORDER BY name ASC"), MYSQLI_ASSOC);
        echo json_encode(['accounts' => $accounts, 'users' => $users]);
        exit;
    }

    // แทนที่รายการผู้ใช้ทั้งหมดของโปรแกรม (JSON: {program_id, rows:[{user_id, username, password, note}]})
    if ($action === 'save_accounts' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $program_id = (int)($body['program_id'] ?? 0);
        $rows = (array)($body['rows'] ?? []);
        if (!mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM company_programs WHERE id = $program_id"))) {
            echo json_encode(['status' => 'error', 'msg' => 'ไม่พบโปรแกรม']);
            exit;
        }
        $seen = [];
        foreach ($rows as $r) {
            $uid = (int)($r['user_id'] ?? 0);
            if ($uid <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'กรุณาเลือกผู้ใช้ให้ครบทุกแถว']);
                exit;
            }
            if (isset($seen[$uid])) {
                echo json_encode(['status' => 'error', 'msg' => 'มีผู้ใช้ซ้ำกันในรายการ']);
                exit;
            }
            $seen[$uid] = true;
        }
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "DELETE FROM company_program_accounts WHERE program_id = $program_id");
            $stmt = mysqli_prepare($conn, "INSERT INTO company_program_accounts (program_id, user_id, login_username, login_password_enc, note) VALUES (?, ?, ?, ?, ?)");
            foreach ($rows as $r) {
                $uid = (int)$r['user_id'];
                $username = trim((string)($r['username'] ?? '')) ?: null;
                $password = (string)($r['password'] ?? '');
                $password_enc = $password !== '' ? company_program_encrypt($password) : null;
                $note = trim((string)($r['note'] ?? '')) ?: null;
                mysqli_stmt_bind_param($stmt, 'iisss', $program_id, $uid, $username, $password_enc, $note);
                mysqli_stmt_execute($stmt);
            }
            mysqli_commit($conn);
            echo json_encode(['status' => 'success', 'msg' => 'บันทึกผู้ใช้ ' . count($rows) . ' คน']);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'msg' => 'บันทึกไม่สำเร็จ']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
    exit;
}

include('header.php');
if (!$is_admin) {
    echo "<script>alert('เฉพาะ admin เท่านั้นที่จัดการโปรแกรมในบริษัทได้'); window.location.href='settings.php';</script>";
    exit;
}
?>

<div class="container p-0">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800">โปรแกรมในบริษัท</h2>
            <p class="text-xs text-slate-500">โปรแกรมที่แสดงในหน้า E-Service · เลือกได้ว่าใครเห็นโปรแกรมไหน พร้อม username/password ของแต่ละคน</p>
        </div>
        <button type="button" onclick="openProgramModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-indigo-700 transition shadow-sm">
            <i class="fas fa-plus mr-1"></i> เพิ่มโปรแกรม
        </button>
    </div>

    <div id="programGrid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <div class="col-span-full p-12 text-center text-slate-400">กำลังโหลด...</div>
    </div>
</div>

<!-- Modal: เพิ่ม/แก้ไขโปรแกรม -->
<div id="programModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <form id="programForm" class="bg-white rounded-3xl shadow-xl w-full max-w-lg max-h-[92vh] overflow-y-auto" enctype="multipart/form-data">
        <div class="flex justify-between items-center p-5 border-b border-slate-100">
            <h3 class="font-bold text-slate-800" id="programModalTitle">เพิ่มโปรแกรม</h3>
            <button type="button" onclick="closeModal('programModal')" class="text-slate-400 hover:text-slate-700"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5 space-y-4 text-sm">
            <input type="hidden" name="id">
            <div class="flex gap-4 items-start">
                <label class="shrink-0 cursor-pointer">
                    <div id="imagePreview" class="w-20 h-20 rounded-2xl bg-slate-100 border border-dashed border-slate-300 flex items-center justify-center overflow-hidden text-slate-400">
                        <i class="fas fa-image text-2xl"></i>
                    </div>
                    <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" onchange="previewImage(this)">
                    <span class="block text-[10px] text-center text-indigo-600 font-bold mt-1">เลือกรูป</span>
                </label>
                <div class="flex-1 space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">ชื่อโปรแกรม <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required maxlength="150" class="w-full border border-slate-200 rounded-xl p-2.5">
                    </div>
                    <label class="flex items-center gap-2 text-xs text-slate-500 hidden" id="removeImageWrap">
                        <input type="checkbox" name="remove_image" value="1"> ลบรูปเดิม
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">ลิงก์เข้าโปรแกรม <span class="text-red-500">*</span></label>
                <input type="url" name="url" required maxlength="500" placeholder="https://..." class="w-full border border-slate-200 rounded-xl p-2.5">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">คำอธิบาย</label>
                <textarea name="description" rows="3" class="w-full border border-slate-200 rounded-xl p-2.5" placeholder="ใช้ทำอะไร ใครควรใช้"></textarea>
            </div>
            <div class="flex items-center gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">ลำดับการแสดง</label>
                    <input type="number" name="sort_order" value="0" class="w-24 border border-slate-200 rounded-xl p-2.5">
                </div>
                <label class="flex items-center gap-2 font-bold text-slate-700 mt-5">
                    <input type="checkbox" name="is_active" value="1" checked> เปิดใช้งาน
                </label>
            </div>
        </div>
        <div class="flex justify-end gap-2 p-5 border-t border-slate-100">
            <button type="button" onclick="closeModal('programModal')" class="px-4 py-2 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-bold bg-indigo-600 text-white hover:bg-indigo-700">บันทึก</button>
        </div>
    </form>
</div>

<!-- Modal: ผู้ใช้และบัญชีของโปรแกรม -->
<div id="accountsModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-4xl max-h-[92vh] flex flex-col">
        <div class="flex justify-between items-center p-5 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-slate-800">ผู้ใช้ที่เห็นโปรแกรม: <span id="accountsProgramName"></span></h3>
                <p class="text-[11px] text-slate-500">คนที่อยู่ในรายการนี้จะเห็นโปรแกรมในหน้า E-Service พร้อม username/password ของตัวเอง · รหัสผ่านถูกเข้ารหัสก่อนบันทึก</p>
            </div>
            <button type="button" onclick="closeModal('accountsModal')" class="text-slate-400 hover:text-slate-700"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5 overflow-y-auto flex-1">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-500 text-left">
                        <tr><th class="p-2">ผู้ใช้</th><th class="p-2">Username</th><th class="p-2">Password</th><th class="p-2">หมายเหตุ</th><th class="w-10"></th></tr>
                    </thead>
                    <tbody id="accountRows"></tbody>
                </table>
            </div>
            <p id="accountsEmpty" class="hidden text-center text-slate-400 py-6">ยังไม่มีผู้ใช้ กด "เพิ่มผู้ใช้"</p>
            <button type="button" onclick="addAccountRow()" class="mt-3 text-sm text-indigo-600 font-bold hover:underline"><i class="fas fa-user-plus mr-1"></i>เพิ่มผู้ใช้</button>
        </div>
        <div class="flex justify-end gap-2 p-5 border-t border-slate-100">
            <button type="button" onclick="closeModal('accountsModal')" class="px-4 py-2 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100">ยกเลิก</button>
            <button type="button" onclick="saveAccounts()" class="px-4 py-2 rounded-xl text-sm font-bold bg-indigo-600 text-white hover:bg-indigo-700">บันทึก</button>
        </div>
    </div>
</div>

<script>
let programs = [];
let users = [];
let currentProgramId = 0;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
const toast = (icon, title, text) => Swal.fire({icon, title, text, timer: icon === 'success' ? 1300 : undefined, showConfirmButton: icon !== 'success'});

function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function programImage(p, size = 'w-14 h-14') {
    return p.image_path
        ? `<img src="${esc(p.image_path)}" alt="" class="${size} rounded-2xl object-cover bg-slate-50 border border-slate-100">`
        : `<div class="${size} rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center text-xl font-black">${esc(((p.name || '').match(/[\p{L}\p{N}]/u) || ['?'])[0])}</div>`;
}

async function loadPrograms() {
    programs = await fetch('program_settings.php?action=list').then(r => r.json());
    const grid = document.getElementById('programGrid');
    if (!programs.length) {
        grid.innerHTML = '<div class="col-span-full p-12 text-center text-slate-400 bg-white rounded-3xl border border-slate-200">ยังไม่มีโปรแกรม กด "เพิ่มโปรแกรม"</div>';
        return;
    }
    grid.innerHTML = programs.map(p => `
        <div class="bg-white rounded-3xl border border-slate-200 p-4 flex flex-col gap-3 ${p.is_active == 1 ? '' : 'opacity-60'}">
            <div class="flex gap-3 items-start">
                ${programImage(p)}
                <div class="min-w-0 flex-1">
                    <div class="font-bold text-slate-800 truncate">${esc(p.name)} ${p.is_active == 1 ? '' : '<span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">ปิดอยู่</span>'}</div>
                    <a href="${esc(p.url)}" target="_blank" rel="noopener" class="text-[11px] text-indigo-600 hover:underline truncate block">${esc(p.url)}</a>
                    <div class="text-[11px] text-slate-400 mt-0.5"><i class="fas fa-users mr-1"></i>${p.user_count} คน</div>
                </div>
            </div>
            ${p.description ? `<p class="text-xs text-slate-500 line-clamp-2">${esc(p.description)}</p>` : ''}
            <div class="flex gap-2 mt-auto">
                <button type="button" onclick="openAccounts(${p.id})" class="flex-1 bg-indigo-50 text-indigo-700 rounded-xl py-2 text-xs font-bold hover:bg-indigo-100"><i class="fas fa-key mr-1"></i>ผู้ใช้ & รหัส</button>
                <button type="button" onclick="openProgramModal(${p.id})" class="w-9 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-100" title="แก้ไข"><i class="fas fa-pen text-xs"></i></button>
                <button type="button" onclick="deleteProgram(${p.id})" class="w-9 rounded-xl bg-red-50 text-red-600 hover:bg-red-100" title="ลบ"><i class="fas fa-trash-alt text-xs"></i></button>
            </div>
        </div>`).join('');
}

function previewImage(input) {
    const file = input.files[0];
    if (!file) return;
    document.getElementById('imagePreview').innerHTML = `<img src="${URL.createObjectURL(file)}" class="w-full h-full object-cover">`;
}

function openProgramModal(id = 0) {
    const form = document.getElementById('programForm');
    form.reset();
    const p = programs.find(x => x.id == id);
    document.getElementById('programModalTitle').textContent = p ? 'แก้ไขโปรแกรม' : 'เพิ่มโปรแกรม';
    form.id.value = p ? p.id : '';
    form.name.value = p ? p.name : '';
    form.url.value = p ? p.url : '';
    form.description.value = p ? (p.description || '') : '';
    form.sort_order.value = p ? p.sort_order : 0;
    form.is_active.checked = p ? p.is_active == 1 : true;
    document.getElementById('imagePreview').innerHTML = p && p.image_path
        ? `<img src="${esc(p.image_path)}" class="w-full h-full object-cover">`
        : '<i class="fas fa-image text-2xl"></i>';
    document.getElementById('removeImageWrap').classList.toggle('hidden', !(p && p.image_path));
    document.getElementById('programModal').classList.remove('hidden');
}

document.getElementById('programForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const res = await fetch('program_settings.php?action=save_program', {method: 'POST', body: new FormData(this)}).then(r => r.json());
    if (res.status !== 'success') return toast('error', 'ไม่สำเร็จ', res.msg);
    closeModal('programModal');
    toast('success', 'บันทึกโปรแกรมแล้ว');
    loadPrograms();
});

function deleteProgram(id) {
    const p = programs.find(x => x.id == id);
    Swal.fire({icon: 'warning', title: 'ลบโปรแกรม?', text: `${p.name} (ผู้ใช้ ${p.user_count} คน และรหัสที่บันทึกไว้จะถูกลบด้วย)`,
        showCancelButton: true, confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#dc2626'})
        .then(async r => {
            if (!r.isConfirmed) return;
            const fd = new FormData();
            fd.append('id', id);
            await fetch('program_settings.php?action=delete_program', {method: 'POST', body: fd});
            loadPrograms();
        });
}

async function openAccounts(id) {
    currentProgramId = id;
    document.getElementById('accountsProgramName').textContent = programs.find(x => x.id == id)?.name || '';
    const data = await fetch('program_settings.php?action=accounts&program_id=' + id).then(r => r.json());
    users = data.users;
    document.getElementById('accountRows').innerHTML = '';
    data.accounts.forEach(a => addAccountRow(a));
    toggleAccountsEmpty();
    document.getElementById('accountsModal').classList.remove('hidden');
}

function toggleAccountsEmpty() {
    document.getElementById('accountsEmpty').classList.toggle('hidden', document.querySelectorAll('#accountRows tr').length > 0);
}

function addAccountRow(a = {}) {
    const tr = document.createElement('tr');
    tr.className = 'border-t border-slate-100';
    tr.innerHTML = `
        <td class="p-1.5 min-w-[180px]">
            <select data-f="user_id" class="w-full border border-slate-200 rounded-lg p-2 bg-white">
                <option value="">-- เลือกผู้ใช้ --</option>
                ${users.map(u => `<option value="${u.id}" ${u.id == a.user_id ? 'selected' : ''}>${esc(u.name)} (${esc(u.role || '-')})</option>`).join('')}
            </select>
        </td>
        <td class="p-1.5 min-w-[140px]"><input data-f="username" value="${esc(a.login_username)}" autocomplete="off" class="w-full border border-slate-200 rounded-lg p-2"></td>
        <td class="p-1.5 min-w-[160px]">
            <div class="flex">
                <input data-f="password" type="password" value="${esc(a.login_password)}" autocomplete="new-password" class="w-full border border-slate-200 rounded-l-lg p-2">
                <button type="button" onclick="const i=this.previousElementSibling; i.type = i.type === 'password' ? 'text' : 'password'" class="px-2 border border-l-0 border-slate-200 rounded-r-lg text-slate-400 hover:text-slate-700" title="แสดง/ซ่อน"><i class="fas fa-eye text-xs"></i></button>
            </div>
        </td>
        <td class="p-1.5 min-w-[120px]"><input data-f="note" value="${esc(a.note)}" class="w-full border border-slate-200 rounded-lg p-2"></td>
        <td class="p-1.5 text-center"><button type="button" onclick="this.closest('tr').remove(); toggleAccountsEmpty();" class="text-slate-300 hover:text-red-500" title="เอาออก"><i class="fas fa-trash-alt"></i></button></td>`;
    document.getElementById('accountRows').appendChild(tr);
    toggleAccountsEmpty();
}

async function saveAccounts() {
    const rows = [...document.querySelectorAll('#accountRows tr')].map(tr => {
        const v = f => tr.querySelector(`[data-f="${f}"]`).value;
        return {user_id: v('user_id'), username: v('username'), password: v('password'), note: v('note')};
    });
    const res = await fetch('program_settings.php?action=save_accounts', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({program_id: currentProgramId, rows}),
    }).then(r => r.json());
    if (res.status !== 'success') return toast('error', 'ไม่สำเร็จ', res.msg);
    closeModal('accountsModal');
    toast('success', res.msg);
    loadPrograms();
}

loadPrograms();
</script>

<?php include 'footer.php'; ?>
