<?php
require_once 'config.php';
include('header.php');
require_once __DIR__ . '/company_programs_lib.php';

// โปรแกรมในบริษัทที่ผู้ใช้คนนี้เห็น (admin กำหนดใน ตั้งค่า > โปรแกรมในบริษัท)
$my_programs = [];
try {
    $my_programs = company_programs_for_user($conn, (int)($_SESSION['user_id'] ?? 0));
} catch (mysqli_sql_exception $e) {
    // ยังไม่ได้รัน add_company_programs.sql
}
?>

<style>
    .program-card { transition: transform .2s, box-shadow .2s; }
    .program-card:hover { transform: translateY(-3px); box-shadow: 0 16px 40px -18px rgba(15,23,42,.3); }
    .prog-btn { width: 30px; height: 30px; border-radius: 8px; color: #64748b; flex-shrink: 0; }
    .prog-btn:hover { background: #e2e8f0; color: #334155; }
</style>

<div id="my-programs" class="min-h-full flex flex-col">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h2 class="text-2xl font-black text-slate-800">โปรแกรมของฉัน</h2>
            <p class="text-sm text-slate-400 mt-1">กดเปิดโปรแกรม แล้วคัดลอก username / password ไปกรอกได้เลย</p>
        </div>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <a href="program_settings.php" class="text-sm font-bold text-indigo-600 hover:underline"><i class="fas fa-cog mr-1"></i>จัดการโปรแกรม</a>
        <?php endif; ?>
    </div>

    <?php if (!$my_programs): ?>
    <div class="bg-white rounded-3xl border border-slate-200 p-12 text-center">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-2xl mb-4"><i class="fas fa-th"></i></div>
        <h3 class="font-bold text-slate-700">ยังไม่มีโปรแกรมสำหรับคุณ</h3>
        <p class="text-sm text-slate-400 mt-1">ติดต่อ admin เพื่อเพิ่มโปรแกรมและบัญชีเข้าใช้งานของคุณ</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($my_programs as $prog): ?>
        <div class="program-card bg-white rounded-3xl border border-slate-200 p-5 flex flex-col gap-4 shadow-sm">
            <div class="flex gap-4 items-start">
                <?php if ($prog['image_path']): ?>
                    <img src="<?= htmlspecialchars($prog['image_path']) ?>" alt="" class="w-14 h-14 rounded-2xl object-cover bg-slate-50 border border-slate-100 shrink-0">
                <?php else: ?>
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center text-xl font-black shrink-0"><?= htmlspecialchars(preg_match('/[\p{L}\p{N}]/u', $prog['name'], $m) ? $m[0] : '?') ?></div>
                <?php endif; ?>
                <div class="min-w-0">
                    <h3 class="font-black text-slate-800 text-lg leading-tight"><?= htmlspecialchars($prog['name']) ?></h3>
                    <?php if ($prog['description']): ?>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed"><?= nl2br(htmlspecialchars($prog['description'])) ?></p>
                    <?php endif; ?>
                    <?php if ($prog['note']): ?>
                        <p class="text-[11px] text-amber-600 mt-1"><i class="fas fa-info-circle mr-1"></i><?= htmlspecialchars($prog['note']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($prog['login_username'] || $prog['has_password']): ?>
            <div class="bg-slate-50 rounded-2xl p-3 space-y-2 text-sm">
                <?php if ($prog['login_username']): ?>
                <div class="flex items-center gap-2">
                    <span class="w-20 shrink-0 text-[11px] font-bold text-slate-400">Username</span>
                    <code class="flex-1 min-w-0 truncate font-bold text-slate-700" data-username><?= htmlspecialchars($prog['login_username']) ?></code>
                    <button type="button" class="prog-btn" title="คัดลอก username" onclick="copyProgramText(this, this.parentElement.querySelector('[data-username]').textContent)"><i class="fas fa-copy"></i></button>
                </div>
                <?php endif; ?>
                <?php if ($prog['has_password']): ?>
                <div class="flex items-center gap-2">
                    <span class="w-20 shrink-0 text-[11px] font-bold text-slate-400">Password</span>
                    <code class="flex-1 min-w-0 truncate font-bold text-slate-700" data-password>••••••••</code>
                    <button type="button" class="prog-btn" title="แสดง/ซ่อน" onclick="toggleProgramPassword(this, <?= (int)$prog['id'] ?>)"><i class="fas fa-eye"></i></button>
                    <button type="button" class="prog-btn" title="คัดลอก password" onclick="copyProgramPassword(this, <?= (int)$prog['id'] ?>)"><i class="fas fa-copy"></i></button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($prog['url']) ?>" target="_blank" rel="noopener noreferrer"
               class="mt-auto inline-flex items-center justify-center gap-2 bg-indigo-600 text-white font-bold rounded-xl py-2.5 text-sm hover:bg-indigo-700 transition">
                <i class="fas fa-arrow-up-right-from-square"></i> เปิดโปรแกรม
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- LINE Official Account -->
    <div class="mt-auto pt-10 flex items-center gap-3">
        <img src="https://qr-official.line.me/gs/L_952uzgli_GW.png?oat_content=qr" alt="LINE QR Code" class="w-14 h-14 rounded-lg shrink-0">
        <div class="min-w-0 text-sm">
            <p class="text-slate-600"><i class="fab fa-line text-green-500 mr-1"></i><b class="text-slate-800">LINE Official Account</b> · รับแจ้งเตือนสถานะ PR, PO และเอกสารต่างๆ</p>
            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5">
                <span class="text-slate-400">LINE ID: <b class="text-green-600">@952uzgli</b></span>
                <button type="button" onclick="copyProgramText(this, '@952uzgli')" class="text-green-600 hover:underline font-semibold"><i class="fas fa-copy"></i> คัดลอก</button>
                <a href="https://line.me/R/ti/p/%40952uzgli" target="_blank" rel="noopener" class="text-green-600 hover:underline font-semibold"><i class="fas fa-user-plus"></i> เพิ่มเพื่อน</a>
            </p>
        </div>
    </div>
</div>

<script>
// รหัสผ่านดึงจากเซิร์ฟเวอร์ตอนกดเท่านั้น (ไม่ฝังไว้ในหน้าเว็บ)
const programPasswordCache = {};
async function fetchProgramPassword(id) {
    if (programPasswordCache[id] !== undefined) return programPasswordCache[id];
    const res = await fetch('company_programs_api.php?action=password&program_id=' + id).then(r => r.json());
    if (res.status !== 'success') throw new Error(res.msg);
    return programPasswordCache[id] = res.password;
}
function flashCopied(btn) {
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check text-emerald-500"></i>';
    setTimeout(() => { btn.innerHTML = old; }, 1200);
}
async function copyProgramText(btn, text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        const t = document.createElement('textarea');
        t.value = text;
        document.body.appendChild(t);
        t.select();
        document.execCommand('copy');
        t.remove();
    }
    flashCopied(btn);
}
async function copyProgramPassword(btn, id) {
    try {
        copyProgramText(btn, await fetchProgramPassword(id));
    } catch (e) {
        Swal.fire({icon: 'error', title: e.message});
    }
}
async function toggleProgramPassword(btn, id) {
    const code = btn.parentElement.querySelector('[data-password]');
    if (code.dataset.shown) {
        code.textContent = '••••••••';
        delete code.dataset.shown;
        btn.innerHTML = '<i class="fas fa-eye"></i>';
        return;
    }
    try {
        code.textContent = await fetchProgramPassword(id);
        code.dataset.shown = '1';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } catch (e) {
        Swal.fire({icon: 'error', title: e.message});
    }
}
</script>

<?php include('footer.php'); ?>
