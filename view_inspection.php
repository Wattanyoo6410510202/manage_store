<?php
require_once 'config.php';
include('header.php');

$inspection_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$milestone_id = isset($_GET['milestone_id']) ? intval($_GET['milestone_id']) : 0;

if ($inspection_id > 0) {
    $sql = "SELECT i.*, p.project_name, p.contractor_name, p.contract_value, m.milestone_name, p.bank_account_no, p.bank_account_name, p.bank_name
            FROM milestone_inspections i
            JOIN projects p ON i.project_id = p.id
            JOIN project_milestones m ON i.milestone_id = m.id
            WHERE i.id = $inspection_id";
} elseif ($milestone_id > 0) {
    $sql = "SELECT i.*, p.project_name, p.contractor_name, p.contract_value, m.milestone_name, p.bank_account_no, p.bank_account_name, p.bank_name
            FROM milestone_inspections i
            JOIN projects p ON i.project_id = p.id
            JOIN project_milestones m ON i.milestone_id = m.id
            WHERE i.milestone_id = $milestone_id";
} else {
    die("<div class='p-10 text-center font-bold text-rose-500'>ไม่พบข้อมูลการตรวจรับงานครับ!</div>");
}

$res = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($res);

if (!$data) {
    die("<div class='p-10 text-center font-bold text-rose-500'>ไม่พบข้อมูลการตรวจรับงานครับ!</div>");
}
$project_id = $data['project_id'];
?>

<style>
    /* ปรับแต่งสำหรับการพิมพ์ให้สะอาดที่สุด */
    @media print {
        @page {
            size: A4 portrait;
            margin: 5mm;
        }

        /* ซ่อนทุกส่วนที่เป็น UI ของระบบ */
        header,
        footer,
        nav,
        aside,
        .no-print,
        .toolbar-container,
        .navbar,
        .main-header,
        .main-sidebar,
        .sidebar,
        .top-navbar {
            display: none !important;
        }

        /* บังคับพื้นหลังขาวและตัวอักษรดำ/สีปกติ */
        body,
        .page-container,
        table,
        tr,
        td,
        th,
        div {
            background: white !important;
            color: black !important;
        }

        .page-container {
            box-shadow: none !important;
            margin: 0 auto !important;
            width: 100% !important;
            padding: 0 !important;
            overflow: hidden !important;
            border: none !important;
        }

        /* ตารางและส่วนหัวให้แสดงสีพื้นหลังที่ต้องการเฉพาะรายการ */
        th {
            background: #f8fafc !important;
        }

        .status-badge.pass {
            background: #dcfce7 !important;
            color: #166534 !important;
        }

        .status-badge.fail {
            background: #fee2e2 !important;
            color: #991b1b !important;
        }

        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }

    /* สไตล์ทั่วไป */
    .page-container {
        background: white;
        padding: 20px;
        width: 210mm;
        margin: 0 auto;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        position: relative;
        box-sizing: border-box;
    }

    .doc-header {
        border-bottom: 2px solid #0f172a;
        padding-bottom: 8px;
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }

    /* ปรับตารางให้กะทัดรัดขึ้น */
    .table-check {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        table-layout: fixed;
    }

    .table-check th,
    .table-check td {
        border: 1px solid #e2e8f0;
        padding: 3px 6px;
        font-size: 10px;
    }

    .table-check th {
        background: #f8fafc;
        font-weight: bold;
    }

    .status-badge {
        padding: 1px 4px;
        border-radius: 3px;
        font-size: 9px;
        font-weight: bold;
    }

    .info-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-bottom: 12px;
    }

    .info-box {
        border: 1px solid #e2e8f0;
        padding: 6px;
        border-radius: 4px;
        font-size: 10px;
    }

    /* Toolbar */
    .toolbar-container {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-bottom: 15px;
    }

    .btn-tool {
        padding: 6px 12px;
        border-radius: 6px;
        color: white;
        transition: all 0.2s;
        border: none;
        font-size: 11px;
        font-weight: bold;
        cursor: pointer;
    }
</style>

<div class="toolbar-container no-print">
    <button onclick="history.back()" class="btn-tool" style="background:#64748b"><i class="fas fa-arrow-left mr-1"></i>
        ย้อนกลับ</button>
    <button onclick="window.print()" class="btn-tool" style="background:#4f46e5"><i class="fas fa-print mr-1"></i>
        พิมพ์</button>
    <button onclick="exportPDF()" class="btn-tool" style="background:#ef4444"><i class="fas fa-file-pdf mr-1"></i>
        PDF</button>
    <a href="add_inspection.php?project_id=<?= $project_id ?>&milestone_id=<?= $data['milestone_id'] ?>"
        class="btn-tool" style="background:#f59e0b"><i class="fas fa-edit mr-1"></i> แก้ไข</a>
    <button onclick="deleteInspection(<?= $data['id'] ?>)" class="btn-tool" style="background:#1e293b"><i
            class="fas fa-trash-alt mr-1"></i> ลบ</button>
</div>

<div id="inspection-content" class="page-container">
    <div class="doc-header">
        <div>
            <h1 style="margin:0; font-size:24px; color:#0f172a; font-weight:900;">ใบตรวจรับงาน</h1>
            <p style="margin:0; font-size:9px; letter-spacing:2px; color:#94a3b8; text-transform:uppercase;">INSPECTION
                REPORT</p>
        </div>
        <div style="text-align:right;">
            <p style="margin:0; font-size:11px; font-weight:bold;">วันที่:
                <?= date('d/m/Y', strtotime($data['inspection_date'])) ?>
            </p>
            <p style="margin:0; font-size:9px; color:#64748b;">Ref:
                INS-<?= str_pad($data['id'], 5, '0', STR_PAD_LEFT) ?></p>
        </div>
    </div>

    <!-- ข้อมูลส่วนตัว (3 คอลัมน์) -->
    <div class="info-grid-3">
        <div style="border:1px solid #e2e8f0; padding:10px; border-radius:6px; font-size:11px;">
            <p style="margin:0 0 5px 0; font-weight:bold; color:#64748b; font-size:8px; text-transform:uppercase;">1.
                ข้อมูลโครงการ</p>
            <p style="margin:2px 0;"><b>ชื่อโครงการ:</b> <?= htmlspecialchars($data['project_name']) ?></p>
            <p style="margin:2px 0;"><b>รายละเอียดงวดงาน:</b> <?= htmlspecialchars($data['milestone_name']) ?></p>
            <p style="margin:2px 0;"><b>มูลค่างาน:</b> <?= number_format($data['contract_value'], 2) ?> บาท</p>
        </div>
        <div style="border:1px solid #e2e8f0; padding:10px; border-radius:6px; font-size:11px;">
            <p style="margin:0 0 5px 0; font-weight:bold; color:#64748b; font-size:8px; text-transform:uppercase;">2.
                ข้อมูลผู้รับจ้าง</p>
            <p style="margin:2px 0;"><b>ชื่อผู้รับจ้าง:</b> <?= htmlspecialchars($data['contractor_name'] ?: '-') ?></p>
            <p style="margin:2px 0;"><b>ชื่อบัญชี:</b> <?= htmlspecialchars($data['bank_account_name'] ?: '-') ?></p>
            <p style="margin:2px 0;"><b>เลขที่บัญชี:</b> <?= htmlspecialchars($data['bank_account_no'] ?: '-') ?></p>
            <p style="margin:2px 0;"><b>ธนาคาร:</b> <?= htmlspecialchars($data['bank_name'] ?: '-') ?></p>
        </div>
        <?php
        $ms_detail_sql = "SELECT amount, vat_amount, wht_amount, total_request_amount FROM project_milestones WHERE id = " . $data['milestone_id'];
        $ms_res = mysqli_query($conn, $ms_detail_sql);
        $ms_detail = mysqli_fetch_assoc($ms_res);
        ?>
        <div style="border:1px solid #e2e8f0; padding:10px; border-radius:6px; font-size:11px; margin-bottom:15px;">
            <p style="margin:0 0 5px 0; font-weight:bold; color:#64748b; font-size:8px; text-transform:uppercase;">4.
                ข้อมูลการชำระเงิน</p>

            <p style="margin:2px 0;"><b>ยอดก่อน VAT:</b> <?= number_format($ms_detail['amount'], 2) ?> บาท</p>
            <p style="margin:2px 0;"><b>VAT:</b> <?= number_format($ms_detail['vat_amount'], 2) ?> บาท</p>
            <p style="margin:2px 0;"><b>ภาษีหัก ณ ที่จ่าย:</b> <?= number_format($ms_detail['wht_amount'], 2) ?> บาท
            </p>
            <p style="margin:2px 0;"><b>ยอดสุทธิ:</b> <b
                    style="color: #4f46e5;"><?= number_format($ms_detail['total_request_amount'], 2) ?> บาท</b></p>

        </div>
    </div>

    <!-- ส่วนที่ 4: ข้อมูลการชำระเงิน -->


    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 15px;">
        <?php
        $groups = [
            'งานโครงสร้างและสถาปัตย์' => ['is_boq_complete' => 'งานดำเนินการครบตาม BOQ', 'is_drawing_match' => 'งานเป็นไปตามแบบ', 'is_spec_match' => 'วัสดุเป็นไปตาม Spec', 'is_quantity_ok' => 'ปริมาณงานถูกต้อง', 'is_on_schedule' => 'งานแล้วเสร็จตามงวด'],
            'ระบบวิศวกรรม' => ['is_electrical_ok' => 'ระบบไฟฟ้าใช้งานได้', 'is_plumbing_ok' => 'ระบบประปาไม่มีการรั่วซึม', 'is_drainage_ok' => 'ระบบระบายน้ำทำงานปกติ', 'is_hvac_ok' => 'ระบบปรับอากาศ'],
            'ความเรียบร้อยและปลอดภัย' => ['is_surface_ok' => 'ผิวงานเรียบร้อย', 'is_paint_ok' => 'งานสี/ผิวตกแต่ง', 'is_cleaned' => 'ความสะอาด', 'is_defect_fixed' => 'งานแก้ไข Defect', 'is_safety_ok' => 'ความปลอดภัย']
        ];
        foreach ($groups as $gName => $items): ?>
            <div>
                <table class="table-check">
                    <thead>
                        <tr>
                            <th colspan="2" style="background:#f1f5f9; font-size:11px;"><?= $gName ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $k => $l): ?>
                            <tr>
                                <td><?= $l ?></td>
                                <td width="20%" align="center">
                                    <span class="status-badge <?= $data[$k] ? 'pass' : 'fail' ?>">
                                        <?= $data[$k] ? '✓' : '✗' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top:15px; font-size:12px; border:1px solid #e2e8f0; padding:10px; border-radius:6px; ">
        <p style="font-weight:bold; margin-bottom:5px;">Punch List / รายการแก้ไข:</p>
        <p style="margin:0;"><?= nl2br(htmlspecialchars($data['punch_list'] ?: '- ไม่มีรายการแก้ไข -')) ?></p>
    </div>
    <div style="border:1px solid #e2e8f0; padding:10px; border-radius:6px; font-size:11px;">
        <p style="margin:0 0 5px 0; font-weight:bold; color:#64748b; font-size:8px; text-transform:uppercase;">3.
            สรุปผลการตรวจ</p>
        <p style="margin:2px 0;"><b>สถานะ:</b> <span
                class="status-badge <?= $data['result_status'] === 'pass' ? 'pass' : 'fail' ?>"><?= $data['result_status'] === 'pass' ? 'ผ่าน' : ($data['result_status'] === 'conditional_pass' ? 'มีเงื่อนไข' : 'ไม่ผ่าน') ?></span>
        </p>
        <p style="margin:2px 0;"><b>MD อนุมัติ:</b> <?= $data['is_md_approved'] ? '✅' : '❌' ?></p>
        <p style="margin:2px 0;"><b>แก้ไขใน:</b> <?= $data['fix_within_days'] ?: '-' ?> วัน</p>
    </div>

    <div
        style="margin-top:40px; display:grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; text-align:center; font-size:11px; mt-2">
        <div>
            <div style="height:40px; border-bottom:1px solid #cbd5e1; margin-bottom:5px;"></div>(
            <?= htmlspecialchars($data['inspector_name_1'] ?: '................') ?> )<br>ผู้ตรวจรับ 1
        </div>
        <div>
            <div style="height:40px; border-bottom:1px solid #cbd5e1; margin-bottom:5px;"></div>(
            <?= htmlspecialchars($data['inspector_name_2'] ?: '................') ?> )<br>ผู้ตรวจรับ 2
        </div>
        <div>
            <div style="height:40px; border-bottom:1px solid #cbd5e1; margin-bottom:5px;"></div>(
            <?= htmlspecialchars($data['procurement_officer'] ?: '................') ?> )<br>เจ้าหน้าที่จัดซื้อ
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function exportPDF() {
        const element = document.getElementById('inspection-content');
        const opt = { filename: 'Inspection_<?= str_pad($data['id'], 5, '0', STR_PAD_LEFT) ?>.pdf', jsPDF: { format: 'a4', orientation: 'portrait' } };
        html2pdf().set(opt).from(element).save();
    }
    function deleteInspection(id) {
        Swal.fire({ title: 'ยืนยันการลบ?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#e11d48', confirmButtonText: 'ใช่, ลบเลย', heightAuto: false }).then((result) => {
            if (result.isConfirmed) {
                $.post('api/delete_inspection.php', { id: id }, function (res) {
                    if (res.status === 'success') { window.location.href = 'detail_project.php?id=<?= $project_id ?>'; }
                    else { Swal.fire({ title: 'เกิดข้อผิดพลาด', text: res.message, icon: 'error', heightAuto: false }); }
                }, 'json');
            }
        });
    }
</script>
<?php include('footer.php'); ?>