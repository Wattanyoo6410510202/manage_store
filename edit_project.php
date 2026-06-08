<?php
require_once 'config.php';
include('header.php');

// 1. รับ ID และดึงข้อมูลเดิม
$pj_id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : 0;
$sql_pj = "SELECT * FROM projects WHERE id = '$pj_id'";
$res_pj = mysqli_query($conn, $sql_pj);
$pj = mysqli_fetch_assoc($res_pj);

if (!$pj) {
    echo "<div class='p-10 text-center text-red-500'>ไม่พบข้อมูลโครงการที่ต้องการแก้ไข</div>";
    exit;
}

// ดึงรายชื่อลูกค้า
$customers = mysqli_query($conn, "SELECT id, customer_name FROM customers ORDER BY customer_name ASC");
$suppliers = mysqli_query($conn, "SELECT id, company_name FROM suppliers ORDER BY company_name ASC");

// ดึงเอกสารเชื่อมโยงเดิม
$sql_docs = "SELECT * FROM project_documents WHERE project_id = '$pj_id'";
$res_docs = mysqli_query($conn, $sql_docs);
?>

<script>
window.deleteFile = function(projectId, field, containerId, placeholderId, infoId) {
    Swal.fire({
        title: 'ยืนยันการลบไฟล์?',
        text: "ไฟล์ที่ลบจะไม่สามารถกู้คืนได้",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบไฟล์',
        cancelButtonText: 'ยกเลิก',
        heightAuto: false
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('project_id', projectId);
            fd.append('field', field);

            fetch('api/delete_project_file.php', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const container = document.getElementById(containerId);
                    const placeholder = document.getElementById(placeholderId);
                    const info = document.getElementById(infoId);
                    
                    info.classList.add('hidden');
                    placeholder.classList.remove('hidden');
                    container.classList.remove('bg-indigo-50/30', 'border-indigo-300');
                    container.classList.add('border-slate-200');
                    
                    Swal.fire({title: 'สำเร็จ!', icon: 'success', heightAuto: false});
                } else {
                    Swal.fire({title: 'ผิดพลาด!', text: data.message, icon: 'error', heightAuto: false});
                }
            });
        }
    });
};

function calculateNetValue() {
    let contractInput = document.getElementById('contract_value');
    let inputValue = parseFloat(contractInput.value) || 0;
    let vatToggle = document.getElementById('vat_toggle');
    let vatInToggle = document.getElementById('vat_include_check');
    let whtToggle = document.getElementById('wht_toggle');

    let isVat = (vatToggle && vatToggle.checked);
    let isVatIn = (vatInToggle && vatInToggle.checked);
    let isWht = (whtToggle && whtToggle.checked);

    let actualBase = inputValue;
    let vatAmount = 0;
    let whtAmount = 0;

    if (isVat) {
        if (isVatIn) {
            actualBase = inputValue / 1.07;
            vatAmount = inputValue - actualBase;
        } else {
            actualBase = inputValue;
            vatAmount = inputValue * 0.07;
        }
    }

    whtAmount = isWht ? (actualBase * 0.03) : 0;
    let netValue = isVatIn ? (inputValue - whtAmount) : (inputValue + vatAmount - whtAmount);

    const fmt = (num) => num.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    if (document.getElementById('display_base')) {
        let displayBaseValue = (isVat && isVatIn) ? actualBase : inputValue;
        document.getElementById('display_base').innerText = fmt(displayBaseValue);
    }
    if (document.getElementById('display_vat')) {
        document.getElementById('display_vat').innerText = (isVat ? '+ ' : '') + fmt(vatAmount);
        document.getElementById('vat_row').style.opacity = isVat ? '1' : '0.3';
    }
    if (document.getElementById('display_wht')) {
        document.getElementById('display_wht').innerText = (isWht ? '- ' : '') + fmt(whtAmount);
        document.getElementById('wht_row').style.opacity = isWht ? '1' : '0.3';
    }
    if (document.getElementById('display_net')) {
        document.getElementById('display_net').innerText = fmt(netValue);
    }

    if (document.getElementById('total_wht_amount')) document.getElementById('total_wht_amount').value = whtAmount.toFixed(2);
    if (document.getElementById('total_vat_amount')) document.getElementById('total_vat_amount').value = vatAmount.toFixed(2);
    if (document.getElementById('net_contract_value')) document.getElementById('net_contract_value').value = netValue.toFixed(2);
}

function addDocRow() {
    const tbody = document.getElementById('docBody');
    const newRow = document.createElement('tr');
    newRow.className = "border-b border-slate-50";
    newRow.innerHTML = `
        <td class="py-3 pr-2">
            <select name="doc_type[]" class="w-full border border-slate-200 rounded-lg p-2 outline-none">
                <option value="quotation">Quotation</option>
                <option value="pr">PR</option>
                <option value="po">PO</option>
            </select>
        </td>
        <td class="py-3 pr-2">
            <input type="text" name="doc_no[]" required class="w-full border border-slate-200 rounded-lg p-2 outline-none" placeholder="ระบุเลขที่เอกสาร...">
        </td>
        <td class="py-3 text-center">
            <button type="button" onclick="deleteDocRow(this)" class="text-rose-400 hover:text-rose-600"><i class="fas fa-times"></i></button>
        </td>
    `;
    tbody.appendChild(newRow);
}

function deleteDocRow(btn) {
    btn.closest('tr').remove();
}

document.addEventListener('DOMContentLoaded', calculateNetValue);
</script>

<div>
    <form action="api/update_project.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="project_id" value="<?= $pj['id'] ?>">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">แก้ไขโครงการ</h2>
                <p class="text-slate-500 text-sm">เลขที่อ้างอิง: <span
                        class="font-mono font-bold text-indigo-600"><?= $pj['project_no'] ?></span></p>
            </div>
            <div class="flex gap-3">
                <?php if (!is_viewer()): ?>
                <button type="submit"
                    class="bg-indigo-500 hover:bg-indigo-600 text-white px-8 py-2.5 rounded-xl font-bold  transition-all">
                    <i class="fas fa-sync-alt mr-2"></i> อัปเดตข้อมูล
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
                        <i class="fas fa-info-circle text-indigo-500"></i> ข้อมูลงาน
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่องาน</label>
                            <input type="text" name="project_name" value="<?= htmlspecialchars($pj['project_name']) ?>"
                                required
                                class="w-full border border-slate-200 rounded-xl p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">เลขที่โครงการ</label>
                            <input type="text" name="project_no" value="<?= $pj['project_no'] ?>" readonly
                                class="w-full border border-slate-200 rounded-xl p-2.5 bg-slate-50 text-slate-500 cursor-not-allowed">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">ลูกค้า</label>
                                <select name="customer_id"
                                    class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                                    <option value="">-- เลือกลูกค้า --</option>
                                    <?php while ($c = mysqli_fetch_assoc($customers)): ?>
                                    <option value="<?= $c['id'] ?>"
                                        <?= ($c['id'] == $pj['customer_id']) ? 'selected' : '' ?>>
                                        <?= $c['customer_name'] ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">
                                    (Supplier)</label>
                                <select name="supplier_id" id="supplier_select"
                                    class="w-full border border-slate-200 rounded-xl p-2.5 outline-none focus:border-indigo-500">
                                    <option value="">-- เลือก Supplier / ร้านค้า --</option>
                                    <?php
    if (isset($suppliers)):
        mysqli_data_seek($suppliers, 0);
        while ($s = mysqli_fetch_assoc($suppliers)):
    ?>
                                    <option value="<?= $s['id'] ?>"
                                        <?= ($s['id'] == $pj['supplier_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['company_name']) ?>
                                    </option>
                                    <?php
        endwhile;
    endif;
    ?>
                                </select>
                                <p class="mt-1 text-[12px] text-slate-400 font-medium">*
                                    หัวกระดาษ</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">มูลค่างาน (Base)</label>
                            <input type="number" step="0.01" name="contract_value" id="contract_value"
                                value="<?= $pj['contract_value'] ?>" oninput="calculateNetValue()"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none font-bold text-indigo-600 bg-indigo-50/30">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">วันที่เริ่ม</label>
                                <input type="date" name="start_date" value="<?= $pj['start_date'] ?>"
                                    class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">วันที่สิ้นสุด</label>
                                <input type="date" name="end_date" value="<?= $pj['end_date'] ?>"
                                    class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ลิงก์ตรวจงาน</label>
                            <input type="url" name="check_work_url" value="<?= htmlspecialchars($pj['check_work_url'] ?? '') ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none"
                                placeholder="https://example.com/check-work">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-user-tie text-indigo-500"></i> ข้อมูลผู้รับจ้างและการชำระเงิน
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อผู้รับจ้าง / บริษัท</label>
                            <input type="text" name="contractor_name"
                                value="<?= htmlspecialchars($pj['contractor_name']) ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">ธนาคาร</label>
                            <input type="text" name="bank_name" value="<?= htmlspecialchars($pj['bank_name']) ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">เลขที่บัญชี</label>
                            <input type="text" name="bank_account_no"
                                value="<?= htmlspecialchars($pj['bank_account_no']) ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อบัญชี</label>
                            <input type="text" name="bank_account_name"
                                value="<?= htmlspecialchars($pj['bank_account_name']) ?>"
                                class="w-full border border-slate-200 rounded-xl p-2.5 outline-none">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-link text-indigo-500"></i> เอกสารเชื่อมโยง
                        </h3>
                        <button type="button" onclick="addDocRow()"
                            class="text-indigo-600 font-bold text-sm bg-indigo-50 px-4 py-2 rounded-lg hover:bg-indigo-100 transition-all">
                            <i class="fas fa-plus-circle mr-1"></i> เพิ่มเอกสาร
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <tbody id="docBody">
                                <?php if (mysqli_num_rows($res_docs) > 0): ?>
                                <?php while ($d = mysqli_fetch_assoc($res_docs)): ?>
                                <tr class="border-b border-slate-50">
                                    <td class="py-3 pr-2">
                                        <select name="doc_type[]"
                                            class="w-full border border-slate-200 rounded-lg p-2 outline-none">
                                            <option value="quotation"
                                                <?= $d['doc_type'] == 'quotation' ? 'selected' : '' ?>>Quotation
                                            </option>
                                            <option value="pr" <?= $d['doc_type'] == 'pr' ? 'selected' : '' ?>>PR
                                            </option>
                                            <option value="po" <?= $d['doc_type'] == 'po' ? 'selected' : '' ?>>PO
                                            </option>
                                        </select>
                                    </td>
                                    <td class="py-3 pr-2">
                                        <input type="text" name="doc_no[]" value="<?= htmlspecialchars($d['doc_no']) ?>"
                                            required class="w-full border border-slate-200 rounded-lg p-2 outline-none"
                                            placeholder="ระบุเลขที่เอกสาร...">
                                    </td>
                                    <td class="py-3 text-center">
                                        <button type="button" onclick="deleteDocRow(this)"
                                            class="text-rose-400 hover:text-rose-600"><i
                                                class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr class="border-b border-slate-50">
                                    <td class="py-3 pr-2">
                                        <select name="doc_type[]"
                                            class="w-full border border-slate-200 rounded-lg p-2 outline-none">
                                            <option value="quotation">Quotation</option>
                                            <option value="pr">PR</option>
                                            <option value="po">PO</option>
                                        </select>
                                    </td>
                                    <td class="py-3 pr-2">
                                        <input type="text" name="doc_no[]"
                                            class="w-full border border-slate-200 rounded-lg p-2 outline-none"
                                            placeholder="ระบุเลขที่เอกสาร...">
                                    </td>
                                    <td class="py-3 text-center">
                                        <button type="button" onclick="deleteDocRow(this)" class="text-rose-400"><i
                                                class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-6 mt-6">
                    <label class="block text-sm font-bold text-slate-700 mb-1">หมายเหตุเพิ่มเติม (Remarks)</label>
                    <textarea name="project_remarks" rows="3"
                        class="w-full border border-slate-200 rounded-xl p-2.5 outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="ระบุเงื่อนไขพิเศษหรือบันทึกเพิ่มเติม..."><?= htmlspecialchars($pj['project_remarks'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-900 rounded-2xl p-6 text-white ">
                    <div class="flex justify-between items-center mb-4 border-b border-indigo-800 pb-2">
                        <h3 class="font-bold text-indigo-300">สรุปมูลค่างาน</h3>
                       <div class="flex items-center gap-4"> 
    <label class="inline-flex items-center cursor-pointer">
        <span class="mr-2 text-[12px] font-bold text-slate-400">VAT 7%</span>
        <input type="checkbox" id="vat_toggle" name="include_vat" value="yes"
            onchange="calculateNetValue()" class="hidden peer"
            <?php if (isset($pj['total_vat_amount']) && floatval($pj['total_vat_amount']) > 0) echo 'checked'; ?>>
        <div class="w-9 h-5 bg-slate-700 rounded-full peer peer-checked:bg-indigo-600 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
    </label>

    <label class="inline-flex items-center cursor-pointer">
        <span class="mr-2 text-[12px] font-bold text-slate-400">เป็น VAT ใน</span>
        <input type="hidden" name="vat_type_status" value="1">
        <input type="checkbox" id="vat_include_check" name="vat_type_status" value="0"
            onchange="calculateNetValue()" class="hidden peer"
            <?php // ถ้า has_vat ใน DB เป็น 0 ให้ติ๊กสวิตช์นี้
                if (isset($pj['has_vat']) && $pj['has_vat'] == 0 && $pj['has_vat'] !== null) echo 'checked'; 
            ?>>
        <div class="w-9 h-5 bg-slate-700 rounded-full peer peer-checked:bg-cyan-500 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
    </label>

    <label class="inline-flex items-center cursor-pointer">
        <span class="mr-2 text-[12px] font-bold text-slate-400">WHT 3%</span>
        <input type="checkbox" id="wht_toggle" name="include_wht" value="yes"
            onchange="calculateNetValue()" class="hidden peer"
            <?php if (isset($pj['total_wht_amount']) && floatval($pj['total_wht_amount']) > 0) echo 'checked'; ?>>
        <div class="w-9 h-5 bg-slate-700 rounded-full peer peer-checked:bg-rose-500 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
    </label>
</div>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-400">มูลค่างาน:</span>
                            <span id="display_base">0.00</span>
                        </div>

                        <div id="vat_row" class="flex justify-between transition-all">
                            <span class="text-slate-400">VAT (7%):</span>
                            <span id="display_vat" class="text-emerald-400">+ 0.00</span>
                        </div>

                        <div id="wht_row" class="flex justify-between transition-all">
                            <span class="text-slate-400">หัก ณ ที่จ่าย (3%):</span>
                            <span id="display_wht" class="text-rose-400">- 0.00</span>
                        </div>

                        <div class="flex justify-between border-t border-slate-800 pt-2">
                            <span class="font-bold">มูลค่าสุทธิ:</span>
                            <span id="display_net" class="font-bold text-lg text-indigo-400">0.00</span>
                        </div>
                    </div>
                    <input type="hidden" name="total_wht_amount" id="total_wht_amount"
                        value="<?= $pj['total_wht_amount'] ?? 0 ?>">
                    <input type="hidden" name="total_vat_amount" id="total_vat_amount"
                        value="<?= $pj['total_vat_amount'] ?>">
                    <input type="hidden" name="net_contract_value" id="net_contract_value"
                        value="<?= $pj['net_contract_value'] ?>">
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-paperclip text-indigo-500"></i> ไฟล์แนบงาน
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- ไฟล์สัญญา -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase">ไฟล์สัญญา (Contract)</label>
                            <div id="container-contract"
                                class="border-2 border-dashed border-slate-200 rounded-xl p-4 text-center hover:border-indigo-300 transition-all cursor-pointer relative group <?= $pj['attachment_contract'] ? 'bg-indigo-50/30 border-indigo-300' : '' ?>"
                                onclick="document.getElementById('attachment_contract').click()">
                                <div id="placeholder-contract" class="<?= $pj['attachment_contract'] ? 'hidden' : '' ?>">
                                    <i class="fas fa-file-contract text-2xl text-slate-300 mb-1 group-hover:text-indigo-400"></i>
                                    <p class="text-[10px] text-slate-500">คลิกเพื่อแนบสัญญา</p>
                                </div>
                                <input type="file" name="attachment_contract" id="attachment_contract" class="hidden" onchange="updateFilePreview('contract')">
                                <div id="info-contract" class="<?= $pj['attachment_contract'] ? '' : 'hidden' ?>">
                                    <i class="fas fa-check-circle text-xl text-indigo-500 mb-1"></i>
                                    <p id="name-contract" class="text-[10px] text-slate-700 font-bold truncate">
                                        <?= $pj['attachment_contract'] ?: '' ?>
                                    </p>
                                    <button type="button" onclick="deleteFile(<?= $pj['id'] ?>, 'attachment_contract', 'container-contract', 'placeholder-contract', 'info-contract')"
                                        class="mt-2 text-[9px] bg-rose-50 text-rose-500 px-2 py-1 rounded hover:bg-rose-100 transition-all">
                                        <i class="fas fa-trash"></i> ลบไฟล์
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ไฟล์ BOQ -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase">ไฟล์ BOQ</label>
                            <div id="container-boq"
                                class="border-2 border-dashed border-slate-200 rounded-xl p-4 text-center hover:border-indigo-300 transition-all cursor-pointer relative group <?= $pj['attachment_boq'] ? 'bg-indigo-50/30 border-indigo-300' : '' ?>"
                                onclick="document.getElementById('attachment_boq').click()">
                                <div id="placeholder-boq" class="<?= $pj['attachment_boq'] ? 'hidden' : '' ?>">
                                    <i class="fas fa-file-excel text-2xl text-slate-300 mb-1 group-hover:text-indigo-400"></i>
                                    <p class="text-[10px] text-slate-500">คลิกเพื่อแนบ BOQ</p>
                                </div>
                                <input type="file" name="attachment_boq" id="attachment_boq" class="hidden" onchange="updateFilePreview('boq')">
                                <div id="info-boq" class="<?= $pj['attachment_boq'] ? '' : 'hidden' ?>">
                                    <i class="fas fa-check-circle text-xl text-indigo-500 mb-1"></i>
                                    <p id="name-boq" class="text-[10px] text-slate-700 font-bold truncate">
                                        <?= $pj['attachment_boq'] ?: '' ?>
                                    </p>
                                    <button type="button" onclick="deleteFile(<?= $pj['id'] ?>, 'attachment_boq', 'container-boq', 'placeholder-boq', 'info-boq')"
                                        class="mt-2 text-[9px] bg-rose-50 text-rose-500 px-2 py-1 rounded hover:bg-rose-100 transition-all">
                                        <i class="fas fa-trash"></i> ลบไฟล์
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ไฟล์รูปภาพ/อื่นๆ -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase">รูปภาพ/อื่นๆ (Thumbnail)</label>
                            <div id="container-main"
                                class="border-2 border-dashed border-slate-200 rounded-xl p-4 text-center hover:border-indigo-300 transition-all cursor-pointer relative group <?= $pj['attachment_path'] ? 'bg-indigo-50/30 border-indigo-300' : '' ?>"
                                onclick="document.getElementById('attachment').click()">
                                <div id="placeholder-main" class="<?= $pj['attachment_path'] ? 'hidden' : '' ?>">
                                    <i class="fas fa-image text-2xl text-slate-300 mb-1 group-hover:text-indigo-400"></i>
                                    <p class="text-[10px] text-slate-500">คลิกเพื่อแนบรูป</p>
                                </div>
                                <input type="file" name="attachment" id="attachment" class="hidden" onchange="updateFilePreview('main')">
                                <div id="info-main" class="<?= $pj['attachment_path'] ? '' : 'hidden' ?>">
                                    <i class="fas fa-check-circle text-xl text-indigo-500 mb-1"></i>
                                    <p id="name-main" class="text-[10px] text-slate-700 font-bold truncate">
                                        <?= $pj['attachment_path'] ?: '' ?>
                                    </p>
                                    <button type="button" onclick="deleteFile(<?= $pj['id'] ?>, 'attachment_path', 'container-main', 'placeholder-main', 'info-main')"
                                        class="mt-2 text-[9px] bg-rose-50 text-rose-500 px-2 py-1 rounded hover:bg-rose-100 transition-all">
                                        <i class="fas fa-trash"></i> ลบไฟล์
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-amber-50 rounded-2xl p-5 border border-amber-100 mt-6">
                    <h4 class="font-bold text-amber-800 mb-2 text-sm flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i> คำแนะนำและข้อควรระวัง
                    </h4>
                    <ul class="text-[11px] text-amber-700 space-y-2 list-disc ml-4">
                        <li>
                            <strong>ตรวจสอบยอดภาษี:</strong> หากพบว่า <strong>"ยอดรวมสุทธิเพี้ยน"</strong>
                            ให้ตรวจสอบที่ปุ่ม Toggle VAT 7% ว่าเปิดหรือปิดอยู่ตามที่ต้องการหรือไม่
                            (ค่าเริ่มต้นจะถูกตั้งไว้ตามข้อมูลล่าสุดในระบบ)
                        </li>
                        <li>
                            <strong>ข้อมูลธนาคาร:</strong> ข้อมูลส่วนนี้จะถูกดึงไปแสดงในใบเบิกเงิน (Payment Requisition)
                            หากมีการเปลี่ยนบัญชีรับเงินของโครงการ ต้องมาอัปเดตที่หน้านี้เพื่อให้ยอดโอนถูกต้อง
                        </li>
                        <li>
                            <strong>การจัดการไฟล์:</strong> หากต้องการเปลี่ยนไฟล์แนบใหม่ จารสามารถกด
                            <strong>"เปลี่ยนไฟล์"</strong>
                            แล้วเลือกไฟล์ใหม่ทับได้ทันที ระบบจะใช้ไฟล์ล่าสุดในการอ้างอิงเสมอ
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </form>
</div>