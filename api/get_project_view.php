<?php
require_once '../config.php';

$id = intval($_GET['id']);

// 1. ดึงข้อมูลโครงการ
$pj = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM projects WHERE id = $id"));

// 2. ดึงยอดเบิกสะสม เพื่อเอามาลบยอดสัญญา (ใช้สำหรับส่งค่าให้ปุ่มสุ่ม)
$collected_sql = mysqli_query($conn, "SELECT SUM(amount) as total FROM project_milestones WHERE project_id = $id AND status = 'paid'");
$collected_data = mysqli_fetch_assoc($collected_sql);
$collected = $collected_data['total'] ?? 0;

// --- จุดสำคัญที่ขาดไป: คำนวณยอดคงเหลือเบิกได้ ---
$current_balance = $pj['contract_value'] - $collected;

// 3. ดึงเอกสารที่ผูกไว้
$docs = mysqli_query($conn, "SELECT * FROM project_documents WHERE project_id = $id");

// 4. ดึงงวดงาน
$milestones = mysqli_query($conn, "SELECT * FROM project_milestones WHERE project_id = $id");
?>
<div class="space-y-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100">
            <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider">มูลค่าสัญญาทั้งหมด</p>
            <p class="text-xl font-black text-indigo-700"><?= number_format($pj['contract_value'], 2) ?> ฿</p>
        </div>
        <div class="bg-emerald-50 p-4 rounded-2xl border border-emerald-100">
            <p class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider">เบิกแล้ว</p>
            <p class="text-xl font-black text-emerald-700">
                <?php
                $collected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(net_amount) as total FROM project_milestones WHERE project_id = $id AND status = 'paid'"))['total'];
                echo number_format($collected, 2);
                ?> ฿
            </p>
        </div>
        <div class="bg-rose-50 p-4 rounded-2xl border border-rose-100">
            <p class="text-[10px] font-bold text-rose-400 uppercase tracking-wider">คงเหลือเบิกได้อีก</p>
            <p class="text-xl font-black text-rose-700"><?= number_format($pj['contract_value'] - $collected, 2) ?> ฿
            </p>
        </div>
    </div>

    <div>
        <h4 class="font-bold text-slate-800 mb-3 flex items-center gap-2">
            <i class="fas fa-link text-indigo-500"></i> เอกสารเชื่อมโยงในระบบ
        </h4>
        <div class="bg-white border border-slate-100 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 font-bold">
                    <tr>
                        <th class="px-4 py-3 text-left">ประเภท</th>
                        <th class="px-4 py-3 text-left">เลขที่เอกสาร</th>
                        <th class="px-4 py-3 text-center">ลิงก์</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (mysqli_num_rows($docs) > 0): ?>
                        <?php while ($d = mysqli_fetch_assoc($docs)): ?>
                            <tr>
                                <td class="px-4 py-3"><span
                                        class="uppercase font-bold text-[10px] px-2 py-0.5 rounded bg-slate-100 text-slate-600"><?= $d['doc_type'] ?></span>
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-700"><?= $d['doc_no'] ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php
                                    $link = ($d['doc_type'] == 'po') ? 'view_po.php?no=' : (($d['doc_type'] == 'pr') ? 'view_pr.php?no=' : 'view_quotation.php?no=');
                                    ?>
                                    <a href="<?= $link . $d['doc_no'] ?>" target="_blank"
                                        class="text-indigo-500 hover:underline"><i class="fas fa-external-link-alt"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="px-4 py-5 text-center text-slate-400 italic">ยังไม่มีเอกสารที่ผูกไว้</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="flex justify-between items-center mb-3">
            <h4 class="font-bold text-slate-800 flex items-center gap-2">
                <i class="fas fa-file-invoice-dollar text-emerald-500"></i> ประวัติการเบิกงวดงาน
            </h4>
            <button onclick="location.href='add_milestone.php?project_id=<?= $id ?>'"
                class="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-lg hover:bg-indigo-100">
                + เพิ่มงวดเบิกเงิน
            </button>

            <button type="button" onclick="randomMilestone(<?= $id ?>, <?= $current_balance ?>)"
                class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1 rounded-lg hover:bg-amber-100 border border-amber-200 transition-all">
                <i class="fas fa-dice"></i> สุ่มงวดงาน
            </button>
        </div>
        <div class="space-y-2 text-sm">
            <?php while ($m = mysqli_fetch_assoc($milestones)): ?>
                <div class="flex justify-between items-center p-3 bg-slate-50 rounded-xl border border-slate-100 group">
                    <div class="flex items-center gap-3">
                        <?php if (!empty($m['claim_attachment'])): ?>
                            <?php
                            $file_path = 'uploads/claims/' . $m['claim_attachment'];
                            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                            $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
                            ?>
                            <button
                                onclick="<?= $is_image ? "viewProofImage('$file_path')" : "window.open('$file_path', '_blank')" ?>"
                                class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-indigo-500 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all shadow-sm"
                                title="ดูหลักฐาน">
                                <i class="fas <?= $is_image ? 'fa-image' : 'fa-file-pdf' ?> text-lg"></i>
                            </button>
                        <?php else: ?>
                            <div
                                class="w-10 h-10 flex items-center justify-center rounded-lg bg-slate-100 text-slate-300 border border-dashed border-slate-200">
                                <i class="fas fa-eye-slash text-xs"></i>
                            </div>
                        <?php endif; ?>

                        <div>
                            <p class="font-bold text-slate-700"><?= $m['milestone_name'] ?></p>
                            <p class="text-[10px] text-slate-400">วันที่เบิก:
                                <?= date('d/m/Y', strtotime($m['claim_date'])) ?>
                            </p>
                        </div>
                    </div>

                    <div class="text-right">
                        <p class="font-bold text-emerald-600"><?= number_format($m['net_amount'], 2) ?> ฿</p>
                        <span
                            class="text-[9px] font-bold px-1.5 py-0.5 rounded-md uppercase <?= $m['status'] == 'paid' ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-white' ?>">
                            <?= $m['status'] ?>
                        </span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function viewProofImage(url) {
        Swal.fire({
            imageUrl: url,
            imageAlt: 'หลักฐานการโอนเงิน',
            showConfirmButton: false,
            showCloseButton: true,
            width: 'auto',
            background: 'transparent',
            padding: '0',
            heightAuto: false, // ย้ายเข้ามาอยู่ใน Object นี้ครับจาร
            customClass: {
                image: 'rounded-xl shadow-2xl max-h-[80vh] object-contain',
                popup: 'bg-transparent shadow-none' // เคลียร์พื้นหลังให้ใสสนิท
            },
            showClass: {
                popup: 'animate__animated animate__zoomIn animate__faster' // ถ้าจารมี Animate.css จะเด้งสวยมาก
            },
            hideClass: {
                popup: 'animate__animated animate__zoomOut animate__faster'
            }
        });
    }
    function randomMilestone(projectId, maxBalance) {
        // 1. เช็กก่อนว่าเงินเหลือไหม
        if (maxBalance <= 0) {
            Swal.fire('งบหมดแล้วจาร!', 'โครงการนี้เบิกครบเต็มจำนวนแล้วครับ', 'info');
            return;
        }

        const milestoneNames = ["ค่าดำเนินการงวดที่ 1", "ค่าวัสดุและอุปกรณ์เสริม", "ค่าแรงติดตั้งระบบ", "งวดส่งมอบงานส่วนที่ 1", "ค่าที่ปรึกษาโครงการ"];

        // 2. สุ่มยอดเงิน (ไม่เกินยอดคงเหลือ และไม่เกิน 100,000 ต่อครั้งเพื่อความสมจริง)
        let limit = Math.min(maxBalance, 100000);
        let randomAmount = Math.floor(Math.random() * (limit - 5000 + 1)) + 5000;

        // 3. คำนวณภาษี (VAT 7%, WHT 3%)
        let vat = randomAmount * 0.07;
        let wht = randomAmount * 0.03;
        let net = (randomAmount + vat) - wht;
        let newBalance = maxBalance - randomAmount;

        // 4. แพ็กใส่ FormData (ตรวจสอบชื่อ Key ให้ตรงกับฝั่ง PHP)
        const formData = new FormData();
        formData.append('project_id', projectId);
        formData.append('milestone_name', milestoneNames[Math.floor(Math.random() * milestoneNames.length)] + " (AUTO)");
        formData.append('amount', randomAmount); // ยอดเงินต้น
        formData.append('vat_amount', vat.toFixed(2));
        formData.append('wht_amount', wht.toFixed(2));
        formData.append('total_request_amount', net.toFixed(2)); // ยอดสุทธิ (รวมภาษี)
        formData.append('net_amount', net.toFixed(2)); // กันเหนียว: ส่งเผื่อถ้า PHP ใช้ชื่อนี้
        formData.append('remaining_balance', newBalance.toFixed(2));
        formData.append('claim_date', new Date().toISOString().split('T')[0]);
        formData.append('status', 'paid');
        formData.append('remarks', 'ข้อมูลสุ่มเพื่อการทดสอบระบบ');

        // 5. คอนเฟิร์มก่อนยิง API
        if (confirm(`สุ่มยอดเบิก: ${randomAmount.toLocaleString()} บาท\n(ยอดสุทธิหลังภาษี: ${net.toLocaleString()} บาท)\n\nต้องการบันทึกงวดงานนี้ทันทีหรือไม่?`)) {
            fetch('api/save_milestone.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        alert("บันทึกไม่สำเร็จ ลองเช็กไฟล์ api/save_milestone.php ดูครับจาร");
                    }
                })
                .catch(err => alert('Error: ' + err));
        }
    }
</script>