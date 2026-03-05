<?php
require_once 'config.php';
$ids_param = isset($_GET['ids']) ? mysqli_real_escape_string($conn, $_GET['ids']) : '';
if (empty($ids_param)) { die("<div style='text-align:center; padding:50px;'>ไม่พบข้อมูลครับจาร</div>"); }

$ids = array_map('intval', explode(',', $ids_param));
$ids_string = implode(',', $ids);

// Query ดึงข้อมูลโครงการและงวดงานทั้งหมดที่เลือก
$sql = "SELECT m.*, 
               p.project_name, p.project_no, p.contractor_name, p.contract_value, p.start_date, p.end_date,
               s.company_name as my_company, s.address as my_address, s.tax_id as my_tax, s.phone as my_phone
        FROM project_milestones m
        LEFT JOIN projects p ON m.project_id = p.id
        LEFT JOIN suppliers s ON s.id = 1 
        WHERE m.id IN ($ids_string)
        ORDER BY m.id ASC"; 
$result = mysqli_query($conn, $sql);

$milestones = [];
while($row = mysqli_fetch_assoc($result)) { $milestones[] = $row; }
if (count($milestones) == 0) { die("ไม่พบข้อมูล"); }
$first = $milestones[0];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Full_Project_Report_<?= date('Ymd') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #4f46e5; --slate-800: #1e293b; --slate-600: #475569; --slate-500: #64748b; --slate-100: #f1f5f9; }
        * { box-sizing: border-box; font-family: 'Sarabun', sans-serif; }
        body { background: #cbd5e1; margin: 0; padding: 20px; }

        .page {
            width: 210mm; min-height: 297mm; padding: 12mm;
            margin: auto; background: white; box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: relative;
        }

        @media print {
            body { background: none; padding: 0; }
            .page { margin: 0; box-shadow: none; width: 100%; padding: 10mm; }
            .no-print { display: none !important; }
        }

        /* Header section */
        .header-section { display: flex; justify-content: space-between; border-bottom: 2px solid var(--slate-800); padding-bottom: 15px; margin-bottom: 20px; }
        .company-info h2 { margin: 0; color: var(--primary); font-size: 22px; font-weight: 800; }
        .company-info p { margin: 2px 0; font-size: 11px; color: var(--slate-600); }

        /* Project Banner Card */
        .project-card { 
            background: var(--slate-100); border-radius: 12px; padding: 18px; 
            display: grid; grid-template-columns: 1.5fr 1fr; gap: 25px; margin-bottom: 25px;
        }
        .info-label { font-size: 10px; font-weight: bold; color: var(--primary); text-transform: uppercase; }
        .info-value { font-size: 15px; font-weight: 700; color: var(--slate-800); }

        /* Table Style */
        .milestone-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .milestone-table th { 
            background: var(--slate-800); color: white; padding: 10px 5px; 
            text-align: center; border: 1px solid #334155;
        }
        .milestone-table td { padding: 10px 5px; border: 1px solid #e2e8f0; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .status-paid { color: #059669; font-weight: bold; font-size: 10px; background: #ecfdf5; padding: 2px 5px; border-radius: 4px; }

        /* Summary Footer */
        .grand-total-section { margin-top: 20px; display: flex; justify-content: flex-end; }
        .total-table { width: 280px; border-collapse: collapse; }
        .total-table td { padding: 8px; border-bottom: 1px solid #f1f5f9; }
        .total-row-main { background: var(--primary); color: white; font-weight: 800; font-size: 16px; }
    </style>
</head>
<body>

<div class="no-print" style="position: fixed; top: 20px; right: 20px;">
    <button onclick="window.print()" style="padding: 12px 25px; background: var(--primary); color: white; border: none; border-radius: 30px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.4);">
        🖨 พิมพ์รายงานสรุปทั้งหมด
    </button>
</div>

<div class="page">
    <div class="header-section">
        <div class="company-info">
            <h2><?= $first['my_company'] ?></h2>
            <p><?= $first['my_address'] ?></p>
            <p>Tax ID: <?= $first['my_tax'] ?> | Tel: <?= $first['my_phone'] ?></p>
        </div>
        <div style="text-align: right;">
            <h1 style="margin:0; font-size: 24px; font-weight: 900; color: var(--slate-800);">ใบสรุปงวดงาน</h1>
            <p style="margin:0; font-size: 12px; color: var(--slate-500);">MILESTONE SUMMARY REPORT</p>
        </div>
    </div>

    <div class="project-card">
        <div>
            <div class="info-label">ข้อมูลโครงการ (Project Description)</div>
            <div class="info-value" style="font-size: 18px; margin-bottom: 8px;"><?= $first['project_name'] ?></div>
            <div style="font-size: 12px; color: var(--slate-600);">
                เลขที่สัญญา/โครงการ: <b><?= $first['project_no'] ?></b><br>
                ผู้รับจ้าง: <?= $first['contractor_name'] ?: '-' ?>
            </div>
        </div>
        <div style="border-left: 1px solid #cbd5e1; padding-left: 20px;">
            <div class="info-label">มูลค่ารวมโครงการ</div>
            <div class="info-value" style="color: var(--primary); font-size: 20px;">฿ <?= number_format($first['contract_value'], 2) ?></div>
            <div style="margin-top: 10px; display: flex; gap: 15px; font-size: 11px;">
                <div>
                    <span class="info-label">เริ่มสัญญา:</span><br>
                    <b><?= date('d/m/Y', strtotime($first['start_date'])) ?></b>
                </div>
                <div>
                    <span class="info-label">สิ้นสุดสัญญา:</span><br>
                    <b><?= date('d/m/Y', strtotime($first['end_date'])) ?></b>
                </div>
            </div>
        </div>
    </div>

    <table class="milestone-table">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="32%">รายการงวดงาน</th>
                <th width="12%">วันที่เบิก</th>
                <th width="12%">จำนวนเงิน</th>
                <th width="10%">VAT (7%)</th>
                <th width="10%">WHT (3%)</th>
                <th width="12%">ยอดสุทธิ</th>
                <th width="8%">สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sum_amount = 0; $sum_vat = 0; $sum_wht = 0; $sum_net = 0;
            foreach ($milestones as $idx => $m): 
                $sum_amount += $m['amount'];
                $sum_vat += $m['vat_amount'];
                $sum_wht += $m['wht_amount'];
                $sum_net += $m['net_amount'];
            ?>
            <tr>
                <td class="text-center"><?= $idx + 1 ?></td>
                <td>
                    <div style="font-weight:bold;"><?= $m['milestone_name'] ?></div>
                    <div style="font-size:10px; color:var(--slate-500);"><?= $m['remarks'] ?: '' ?></div>
                </td>
                <td class="text-center"><?= date('d/m/Y', strtotime($m['claim_date'])) ?></td>
                <td class="text-right"><?= number_format($m['amount'], 2) ?></td>
                <td class="text-right"><?= number_format($m['vat_amount'], 2) ?></td>
                <td class="text-right" style="color: #ef4444;">-<?= number_format($m['wht_amount'], 2) ?></td>
                <td class="text-right" style="font-weight:bold;"><?= number_format($m['net_amount'], 2) ?></td>
                <td class="text-center"><span class="status-paid">จ่ายแล้ว</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="grand-total-section">
        <table class="total-table">
            <tr>
                <td style="color: var(--slate-500);">รวมจำนวนเงิน</td>
                <td class="text-right"><b><?= number_format($sum_amount, 2) ?></b></td>
            </tr>
            <tr>
                <td style="color: var(--slate-500);">รวมภาษีมูลค่าเพิ่ม</td>
                <td class="text-right"><b><?= number_format($sum_vat, 2) ?></b></td>
            </tr>
            <tr>
                <td style="color: var(--slate-500);">รวมหัก ณ ที่จ่าย</td>
                <td class="text-right" style="color: #ef4444;"><b>-<?= number_format($sum_wht, 2) ?></b></td>
            </tr>
            <tr class="total-row-main">
                <td style="border-radius: 8px 0 0 8px;">ยอดรวมสุทธิทั้งสิ้น</td>
                <td class="text-right" style="border-radius: 0 8px 8px 0;">฿ <?= number_format($sum_net, 2) ?></td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 50px; display: grid; grid-template-columns: 1fr 1fr; gap: 100px; text-align: center;">
        <div>
            <div style="border-bottom: 1px solid #cbd5e1; height: 40px;"></div>
            <p style="font-size: 12px; margin-top: 8px;">เจ้าหน้าที่ผู้ทำรายการ</p>
        </div>
        <div>
            <div style="border-bottom: 1px solid #cbd5e1; height: 40px;"></div>
            <p style="font-size: 12px; margin-top: 8px;">ผู้อนุมัติโครงการ</p>
        </div>
    </div>

    <div style="position: absolute; bottom: 12mm; left: 12mm; right: 12mm; font-size: 10px; color: var(--slate-500); text-align: center; border-top: 1px solid #f1f5f9; padding-top: 10px;">
        รายงานนี้ออกโดยระบบอัตโนมัติ ณ วันที่ <?= date('d/m/Y H:i:s') ?> | หน้า 1 จาก 1
    </div>
</div>

</body>
</html>