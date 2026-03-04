<?php
require_once 'config.php';
include('header.php');
// include('assets/alert.php'); // ถ้าจารมีไฟล์นี้ให้เปิดไว้ครับ

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
if (empty($id)) { die("ไม่พบรหัสโครงการ"); }

// ดึงข้อมูลโครงการและข้อมูลบริษัท (Supplier)
$sql = "SELECT p.*, s.company_name as my_company, s.address as my_address, s.tax_id as my_tax, s.phone as my_phone, s.logo_path,
               u.name as creator_name 
        FROM projects p
        LEFT JOIN suppliers s ON s.id = 1 -- สมมติว่าดึงจาก Supplier รายหลัก
        LEFT JOIN users u ON u.id = 1 -- ปรับตาม Session หรือ Created_by
        WHERE p.id = '$id' LIMIT 1";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

// ดึงงวดงาน
$sql_milestones = "SELECT * FROM project_milestones WHERE project_id = '$id' ORDER BY id ASC";
$res_milestones = mysqli_query($conn, $sql_milestones);

$logo_path = !empty($data['logo_path']) ? 'uploads/' . $data['logo_path'] : '';
?>

<link rel="stylesheet" href="assets/css/style_quotation.css">

<div class="floating-toolbar no-print">
    <div class="tool-group">
        <button onclick="history.back()" class="btn-tool btn-back" title="ย้อนกลับ">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
    <div class="tool-group main-tools">
        <button onclick="window.print()" class="btn-tool btn-print" title="พิมพ์เอกสาร">
            <i class="fas fa-print"></i> <span>Print</span>
        </button>
    </div>
    <div class="tool-group export-tools">
        <button onclick="exportPDF()" class="btn-tool btn-pdf"><i class="fas fa-file-pdf"></i></button>
    </div>
</div>

<div id="quotation-content" class="page-container">
    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
        <div style="display: flex; gap: 15px;">
            <?php if ($logo_path): ?>
                <img src="<?= $logo_path ?>" style="width: 70px; height: 70px; object-fit: contain;">
            <?php else: ?>
                <div style="width: 70px; height: 70px; background: #4f46e5; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;">P</div>
            <?php endif; ?>
            <div style="font-size: 11px; line-height: 1.4;">
                <h1 style="margin: 0 0 4px; font-size: 18px; color: #0f172a;"><?= $data['my_company'] ?></h1>
                <p style="margin: 0; color: #64748b;"><?= $data['my_address'] ?></p>
                <p style="margin: 2px 0 0; color: #64748b;"><b>Tax ID:</b> <?= $data['my_tax'] ?> | <b>Tel:</b> <?= $data['my_phone'] ?></p>
            </div>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 24px; color: #4f46e5; font-weight: 900;">รายละเอียดโครงการ</h2>
            <p style="margin: 0; font-size: 10px; letter-spacing: 2px; color: #94a3b8; text-transform: uppercase;">Project Summary</p>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 15px; gap: 15px;">
        <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: #f8fafc;">
            <p style="margin: 0 0 5px; font-size: 9px; font-weight: bold; color: #4f46e5; text-transform: uppercase;">Project Info / ข้อมูลโครงการ</p>
            <h3 style="margin: 0; font-size: 14px; color: #0f172a;"><?= $data['project_name'] ?></h3>
            <p style="margin: 5px 0 0; font-size: 11px; color: #64748b;">เลขที่โครงการ: <?= $data['project_no'] ?></p>
        </div>
        <div style="width: 250px; font-size: 12px;">
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">เริ่มสัญญา</span>
                <span style="font-weight: bold;"><?= date('d/m/Y', strtotime($data['start_date'])) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">สิ้นสุดสัญญา</span>
                <span style="font-weight: bold;"><?= date('d/m/Y', strtotime($data['end_date'])) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                <span style="color: #64748b;">มูลค่าโครงการ</span>
                <span style="font-weight: bold; color: #4f46e5;"><?= number_format($data['contract_value'], 2) ?></span>
            </div>
        </div>
    </div>

    <div class="item-section">
        <table class="table-items">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th align="left">งวดงาน / รายละเอียดการเบิกเงิน</th>
                    <th width="15%" align="right">หัก Retention</th>
                    <th width="20%" align="right">ยอดเบิกสุทธิ</th>
                    <th width="12%" align="center">สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php $count = 1; while ($ms = mysqli_fetch_assoc($res_milestones)): ?>
                <tr>
                    <td align="center"><?= $count++ ?></td>
                    <td><?= $ms['milestone_name'] ?><br><small style="color: #94a3b8;">วันที่: <?= date('d/m/Y', strtotime($ms['claim_date'])) ?></small></td>
                    <td align="right"><?= number_format($ms['retention_amount'], 2) ?></td>
                    <td align="right" style="font-weight: bold;"><?= number_format($ms['net_amount'], 2) ?></td>
                    <td align="center">
                        <span style="font-size: 10px; padding: 2px 8px; border-radius: 4px; background: <?= $ms['status']=='paid' ? '#ecfdf5; color: #059669;' : '#fff7ed; color: #ea580c;' ?>">
                            <?= $ms['status'] == 'paid' ? 'จ่ายแล้ว' : 'ค้างชำระ' ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="doc-footer" style="margin-top: 20px;">
        <div style="display: flex; justify-content: flex-end;">
            <div style="width: 40%; background: #f1f5f9; padding: 15px; border-radius: 8px;">
                <table width="100%" style="font-size: 13px;">
                    <tr>
                        <td>มูลค่าสัญญาทั้งสิ้น</td>
                        <td align="right"><?= number_format($data['contract_value'], 2) ?></td>
                    </tr>
                    <?php 
                        $paid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(net_amount) as total FROM project_milestones WHERE project_id = '$id' AND status = 'paid'"))['total'];
                    ?>
                    <tr style="color: #059669; font-weight: bold;">
                        <td>เบิกรับเงินแล้ว</td>
                        <td align="right"><?= number_format($paid, 2) ?></td>
                    </tr>
                    <tr style="font-size: 16px; font-weight: 900; color: #4f46e5;">
                        <td style="padding-top: 10px; border-top: 1px solid #cbd5e1;">ยอดคงเหลือ</td>
                        <td align="right" style="padding-top: 10px; border-top: 1px solid #cbd5e1;"><?= number_format($data['contract_value'] - $paid, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function exportPDF() {
        const element = document.getElementById('quotation-content');
        const opt = {
            margin: 10,
            filename: 'Project_<?= $data['project_no']; ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>

<?php include('footer.php'); ?>