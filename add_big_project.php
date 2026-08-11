<?php
require_once 'config.php';
include('header.php');

$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$bp = null;
$selected_project_ids = [];

if ($edit_id > 0) {
    $res = mysqli_query($conn, "SELECT * FROM big_projects WHERE id = $edit_id AND deleted_at IS NULL");
    $bp = mysqli_fetch_assoc($res);
    if (!$bp) {
        echo "<div class='p-10 text-center text-red-500'>ไม่พบข้อมูล</div>";
        exit;
    }
    $res_items = mysqli_query($conn, "SELECT project_id FROM big_project_items WHERE big_project_id = $edit_id");
    while ($item = mysqli_fetch_assoc($res_items)) {
        $selected_project_ids[] = $item['project_id'];
    }
}

$customers = mysqli_query($conn, "SELECT id, customer_name FROM customers ORDER BY customer_name ASC");
$projects = mysqli_query($conn, "SELECT id, project_name, project_no FROM projects ORDER BY id DESC");
?>

<div>
    <form action="api/save_big_project.php" method="POST">
        <?php if ($edit_id): ?>
            <input type="hidden" name="id" value="<?= $edit_id ?>">
        <?php endif; ?>

        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-800"><?= $edit_id ? 'แก้ไข' : 'สร้าง' ?>โปรเจคใหญ่</h2>
                <p class="text-slate-500 text-sm">รวมหลายโปรเจคย่อยไว้ในโปรเจคใหญ่เดียวกัน</p>
            </div>
            <div class="flex gap-3">
                <?php if (can_manage_projects()): ?>
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-2.5 rounded-xl font-bold transition-all">
                    <i class="fas fa-save mr-2"></i> บันทึก
                </button>
                <?php else: ?>
                <div class="bg-slate-200 text-slate-500 px-8 py-2.5 rounded-xl font-bold flex items-center gap-2 cursor-not-allowed">
                    <i class="fas fa-eye"></i> ดูได้อย่างเดียว
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-info-circle text-indigo-500"></i> ข้อมูลโปรเจคใหญ่
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อโปรเจคใหญ่ <span class="text-red-500">*</span></label>
                            <input type="text" name="name" required value="<?= htmlspecialchars($bp['name'] ?? '') ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 focus:ring-2 focus:ring-indigo-500"
                                placeholder="เช่น โครงการปรับปรุงอาคารสำนักงาน">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">รายละเอียด</label>
                            <textarea name="description" rows="3"
                                class="w-full border border-slate-200 rounded-xl p-2.5 focus:ring-2 focus:ring-indigo-500"
                                placeholder="รายละเอียดเพิ่มเติม..."><?= htmlspecialchars($bp['description'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">ลูกค้า</label>
                            <select name="customer_id" id="customer_select"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                                <option value="">-- เลือกลูกค้า --</option>
                                <?php while ($c = mysqli_fetch_assoc($customers)): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($bp['customer_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['customer_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">สถานะ</label>
                            <select name="status"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                                <option value="active" <?= ($bp['status'] ?? 'active') == 'active' ? 'selected' : '' ?>>กำลังดำเนินการ</option>
                                <option value="completed" <?= ($bp['status'] ?? '') == 'completed' ? 'selected' : '' ?>>เสร็จสิ้น</option>
                                <option value="cancelled" <?= ($bp['status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>ยกเลิก</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">วันที่เริ่ม</label>
                            <input type="date" name="start_date" value="<?= htmlspecialchars($bp['start_date'] ?? '') ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">วันที่สิ้นสุด</label>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($bp['end_date'] ?? '') ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">หมายเหตุ</label>
                            <textarea name="remarks" rows="2"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none"
                                placeholder="หมายเหตุ..."><?= htmlspecialchars($bp['remarks'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-link text-indigo-500"></i> โปรเจคย่อยในโปรเจคใหญ่
                    </h3>
                    <div class="mb-4">
                        <button type="button" onclick="toggleAllProjects()"
                            class="text-indigo-600 hover:text-indigo-800 font-bold text-xs bg-indigo-50 px-3 py-1.5 rounded-lg transition-all">
                            <i class="fas fa-list mr-1"></i> แสดง/ซ่อน รายการโปรเจค
                        </button>
                    </div>
                    <div id="projectList" class="hidden border border-slate-200 rounded-xl p-4 max-h-[400px] overflow-y-auto">
                        <div class="mb-3">
                            <input type="text" id="projectSearch" placeholder="ค้นหาโปรเจค..."
                                class="w-full border border-slate-200 rounded-lg p-2 text-[12px] outline-none focus:ring-1 focus:ring-indigo-500"
                                onkeyup="filterProjectOptions()">
                        </div>
                        <div class="space-y-2" id="projectOptions">
                            <?php 
                            $project_ids_list = [];
                            while ($p = mysqli_fetch_assoc($projects)):
                                $project_ids_list[] = $p;
                            ?>
                                <label class="project-option flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 cursor-pointer transition-all"
                                    data-search="<?= htmlspecialchars($p['project_name'] . ' ' . $p['project_no']) ?>">
                                    <input type="checkbox" name="project_ids[]" value="<?= $p['id'] ?>"
                                        <?= in_array($p['id'], $selected_project_ids) ? 'checked' : '' ?>
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-[12px] font-medium text-slate-700">
                                        <?= htmlspecialchars($p['project_name']) ?>
                                        <span class="text-slate-400 font-normal">(<?= htmlspecialchars($p['project_no']) ?>)</span>
                                    </span>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div id="selectedProjectTags" class="flex flex-wrap gap-2">
                        <?php 
                        if (!empty($project_ids_list)) {
                            foreach ($project_ids_list as $p):
                                if (in_array($p['id'], $selected_project_ids)):
                        ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-[11px] font-medium">
                                        <i class="fas fa-check-circle text-[10px]"></i>
                                        <?= htmlspecialchars($p['project_name']) ?>
                                        <button type="button" onclick="uncheckProject(<?= $p['id'] ?>)" class="text-indigo-400 hover:text-red-500 ml-1">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </span>
                        <?php 
                                endif;
                            endforeach;
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-900 rounded-2xl p-6 text-white">
                    <h3 class="font-bold text-indigo-300 mb-4 border-b border-indigo-800 pb-2">มูลค่าโปรเจคใหญ่</h3>
                    <div class="space-y-3 text-sm">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">มูลค่ารวม (รวม VAT)</label>
                            <input type="number" step="0.01" name="contract_value" id="contract_value"
                                value="<?= htmlspecialchars($bp['contract_value'] ?? '') ?>"
                                oninput="calculateNetValue()"
                                class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-bold outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="0.00">
                        </div>

                        <div class="flex justify-between">
                            <span class="text-slate-400">VAT (7%):</span>
                            <input type="hidden" name="total_vat_amount" id="total_vat_amount">
                            <span id="display_vat" class="text-indigo-400">+ 0.00</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-slate-400">หัก ณ ที่จ่าย (3%):</span>
                            <input type="hidden" name="total_wht_amount" id="total_wht_amount">
                            <span id="display_wht" class="text-rose-400">- 0.00</span>
                        </div>

                        <div class="flex justify-between border-t border-slate-800 pt-2">
                            <span class="font-bold text-slate-200">มูลค่าสุทธิ:</span>
                            <input type="hidden" name="net_contract_value" id="net_contract_value">
                            <span id="display_net" class="font-bold text-lg text-indigo-400">0.00</span>
                        </div>

                        <div class="flex gap-4 mt-4 pt-3 border-t border-slate-800">
                            <label class="inline-flex items-center cursor-pointer">
                                <span class="mr-2 text-[10px] font-bold text-slate-400 uppercase">คิด VAT</span>
                                <input type="checkbox" id="vat_toggle" checked
                                    class="hidden peer" onchange="calculateNetValue()">
                                <div class="w-9 h-5 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600 relative"></div>
                            </label>
                            <label class="inline-flex items-center cursor-pointer">
                                <span class="mr-2 text-[10px] font-bold text-slate-400 uppercase">หัก ณ ที่จ่าย</span>
                                <input type="checkbox" id="wht_toggle"
                                    class="hidden peer" onchange="calculateNetValue()">
                                <div class="w-9 h-5 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-500 relative"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    let allProjects = [];

    <?php 
    mysqli_data_seek($projects, 0);
    while ($p = mysqli_fetch_assoc($projects)): 
    ?>
        allProjects.push({ id: <?= $p['id'] ?>, name: '<?= htmlspecialchars($p['project_name'], ENT_QUOTES) ?>', no: '<?= htmlspecialchars($p['project_no'], ENT_QUOTES) ?>' });
    <?php endwhile; ?>

    function toggleAllProjects() {
        const list = document.getElementById('projectList');
        list.classList.toggle('hidden');
    }

    function filterProjectOptions() {
        const q = document.getElementById('projectSearch').value.toLowerCase();
        document.querySelectorAll('.project-option').forEach(el => {
            const txt = el.getAttribute('data-search').toLowerCase();
            el.style.display = txt.includes(q) ? '' : 'none';
        });
    }

    function uncheckProject(id) {
        const cb = document.querySelector(`input[name="project_ids[]"][value="${id}"]`);
        if (cb) {
            cb.checked = false;
            cb.dispatchEvent(new Event('change'));
        }
    }

    document.querySelectorAll('input[name="project_ids[]"]').forEach(cb => {
        cb.addEventListener('change', function () {
            const container = document.getElementById('selectedProjectTags');
            container.innerHTML = '';
            document.querySelectorAll('input[name="project_ids[]"]:checked').forEach(c => {
                const id = c.value;
                const p = allProjects.find(x => x.id == id);
                if (p) {
                    const span = document.createElement('span');
                    span.className = 'inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-[11px] font-medium';
                    span.innerHTML = `<i class="fas fa-check-circle text-[10px]"></i> ${p.name} <button type="button" onclick="uncheckProject(${p.id})" class="text-indigo-400 hover:text-red-500 ml-1"><i class="fas fa-times"></i></button>`;
                    container.appendChild(span);
                }
            });
        });
    });

    function calculateNetValue() {
        const input = parseFloat(document.getElementById('contract_value').value) || 0;
        const hasVat = document.getElementById('vat_toggle').checked;
        const hasWht = document.getElementById('wht_toggle').checked;

        let base = input;
        let vatAmount = 0;
        let whtAmount = 0;

        if (hasVat) {
            vatAmount = input * 0.07;
            base = input;
        }

        if (hasWht) {
            whtAmount = base * 0.03;
        }

        const net = input + vatAmount - whtAmount;

        const fmt = (n) => n.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        document.getElementById('display_vat').innerText = '+ ' + fmt(vatAmount);
        document.getElementById('display_wht').innerText = '- ' + fmt(whtAmount);
        document.getElementById('display_net').innerText = fmt(net);

        document.getElementById('total_vat_amount').value = vatAmount.toFixed(2);
        document.getElementById('total_wht_amount').value = whtAmount.toFixed(2);
        document.getElementById('net_contract_value').value = net.toFixed(2);
    }

    document.addEventListener('change', function (e) {
        if (['vat_toggle', 'wht_toggle'].includes(e.target.id)) {
            calculateNetValue();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        calculateNetValue();
        <?php if ($edit_id && !empty($selected_project_ids)): ?>
        document.querySelectorAll('input[name="project_ids[]"]:checked').forEach(c => {
            const id = c.value;
            const p = allProjects.find(x => x.id == id);
            if (p) {
                const container = document.getElementById('selectedProjectTags');
                const span = document.createElement('span');
                span.className = 'inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-[11px] font-medium';
                span.innerHTML = `<i class="fas fa-check-circle text-[10px]"></i> ${p.name} <button type="button" onclick="uncheckProject(${p.id})" class="text-indigo-400 hover:text-red-500 ml-1"><i class="fas fa-times"></i></button>`;
                container.appendChild(span);
            }
        });
        <?php endif; ?>
    });
</script>

<?php include('footer.php'); ?>
