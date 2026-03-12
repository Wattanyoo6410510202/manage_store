<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

$ids_param = isset($_GET['ids']) ? mysqli_real_escape_string($conn, $_GET['ids']) : '';
if (empty($ids_param)) {
    die("<div style='text-align:center; padding:50px;'>ไม่พบข้อมูลครับจาร</div>");
}

$ids = array_map('intval', explode(',', $ids_param));
$ids_string = implode(',', $ids);

// 1. Query ดึงข้อมูลโครงการและงวดงานทั้งหมดที่เลือก
$sql = "SELECT m.*, 
               p.project_name, p.project_no, p.contractor_name, p.contract_value, 
               p.total_vat_amount, p.total_wht_amount, p.net_contract_value,
               -- เพิ่ม 3 ฟิลด์นี้ครับจาร (เช็คชื่อคอลัมน์ใน DB ของจารอีกทีนะครับ)
               p.bank_name, p.bank_account_name, p.bank_account_no, 
               p.start_date, p.end_date, p.project_remarks,
               s.company_name as my_company, s.address as my_address, 
               s.tax_id as my_tax, s.phone as my_phone, s.logo_path
        FROM project_milestones m
        LEFT JOIN projects p ON m.project_id = p.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id 
        WHERE m.id IN ($ids_string)
        ORDER BY m.id ASC";
$result = mysqli_query($conn, $sql);
$milestones = [];
while ($row = mysqli_fetch_assoc($result)) {
    $milestones[] = $row;
}

if (empty($milestones)) {
    die("ไม่พบข้อมูล");
}

$first = $milestones[0]; // ใช้ข้อมูลโครงการจากแถวแรก


// แก้บรรทัดนี้: เปลี่ยนจาก $pj['id'] เป็น $first['project_id']
$sql_history = "SELECT milestone_name, retention_percent, retention_amount, deduction_note 
                FROM project_milestones 
                WHERE project_id = " . intval($first['project_id']) . " 
                ORDER BY id ASC";
$res_history = mysqli_query($conn, $sql_history);
$logo_path = (!empty($first['logo_path']) && file_exists('uploads/' . $first['logo_path']))
    ? 'uploads/' . $first['logo_path'] : '';

// 2. คำนวณยอดรวมจากทุกงวดที่เลือก
$total_amount = 0;
$total_vat = 0;
$total_wht = 0;
$total_net = 0;
$total_other = 0; // เพิ่มตัวแปรสำหรับยอดหักอื่นๆ

foreach ($milestones as $m) {
    $total_amount += $m['amount'];
    $total_vat += $m['vat_amount'];
    $total_wht += $m['wht_amount'];
    $total_other += $m['other_deduction_amount'];
    $total_net += $m['net_amount'];

}

// ฟังก์ชันอ่านเลขเงินไทย
function BahtText($amount)
{
    $amount_number = number_format($amount, 2, '.', '');
    $pt = strpos($amount_number, '.');
    $number = ($pt === false) ? $amount_number : substr($amount_number, 0, $pt);
    $fraction = ($pt === false) ? "00" : substr($amount_number, $pt + 1);
    $ret = "";
    $baht = ReadNumber($number);
    if ($baht != "")
        $ret .= $baht . "บาท";
    if ($fraction == "00") {
        $ret .= "ถ้วน";
    } else {
        $ret .= ReadNumber($fraction) . "สตางค์";
    }
    return $ret;
}
function ReadNumber($number)
{
    $position_call = array("แสน", "หมื่น", "พัน", "ร้อย", "สิบ", "");
    $number_call = array("", "หนึ่ง", "สอง", "สาม", "สี่", "ห้า", "หก", "เจ็ด", "แปด", "เก้า");
    $number = intval($number);
    if ($number == 0)
        return "";
    $ret = "";
    if ($number > 1000000) {
        $ret .= ReadNumber(intval($number / 1000000)) . "ล้าน";
        $number = $number % 1000000;
    }
    $t = sprintf("%06d", $number);
    $adjective = false;
    for ($i = 0; $i < 6; $i++) {
        $n = substr($t, $i, 1);
        if ($n != "0") {
            if ($i == 4 && $n == "1")
                $ret .= "สิบ";
            elseif ($i == 4 && $n == "2")
                $ret .= "ยี่สิบ";
            elseif ($i == 5 && $n == "1" && $adjective)
                $ret .= "เอ็ด";
            else
                $ret .= $number_call[$n] . $position_call[$i];
            $adjective = true;
        } else {
            $adjective = false;
        }
    }
    return $ret;
}

$num_rows = count($milestones); // เพิ่มบรรทัดนี้เพื่อแก้ Error ครับ
if ($num_rows <= 5) {
    $dynamic_padding = '5px 8px';
    $dynamic_font_size = '14px';
} elseif ($num_rows <= 10) {
    $dynamic_padding = '3px 10px';
    $dynamic_font_size = '13px';
} else {
    // โหมดนี้จะครอบคลุมตั้งแต่ 11-20 รายการ (และมากกว่านั้นถ้าจารฝืนรันต่อ)
    $dynamic_padding = '2px 12px';
    $dynamic_font_size = '12px';
}
?>
<style>
    :root {
        --row-padding:
            <?= $dynamic_padding ?>
        ;
        --item-font-size:
            <?= $dynamic_font_size ?>
        ;
        --primary-color: #2563eb;
        --border-color: #e2e8f0;
    }

    .table-items tbody td {
        padding: var(--row-padding) !important;
        font-size: var(--item-font-size);
    }

    .page-container {
        background: white;
        padding: 40px;
        min-height: 297mm;
        margin: 0 auto;
        width: 210mm;
    }

    .sig-box {
        border-bottom: 1px solid #94a3b8;
        width: 150px;
        height: 50px;
        margin: 0 auto 10px;
        transition: opacity 0.3s;
    }

    @media print {
        .no-print {
            display: none !important;
        }

        .page-container {
            padding: 0;
            width: 100%;
        }
    }
</style>


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

        <button onclick="toggleSignature()" class="btn-tool btn-sig" id="sigBtn" title="เปิด/ปิด ลายเซ็น">
            <i class="fas fa-pen-nib"></i> <span>Sign</span>
        </button>
    </div>

    <div class="tool-group export-tools">
        <button onclick="exportPDF()" class="btn-tool btn-pdf" title="ดาวน์โหลด PDF">
            <i class="fas fa-file-pdf"></i>
        </button>
        <button onclick="exportWord()" class="btn-tool btn-word" title="ดาวน์โหลด Word">
            <i class="fas fa-file-word"></i>
        </button>
    </div>
</div>


<div id="quotation-content" class="page-container">
    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
        <div style="display: flex; gap: 15px; align-items: flex-start;">
            <?php
            $header = !empty($milestones) ? $milestones[0] : null;
            ?>

            <?php if (!empty($header['logo_path']) && file_exists('uploads/' . $header['logo_path'])): ?>
                <img src="uploads/<?= $header['logo_path'] ?>"
                    style="width: 70px; height: 70px; object-fit: contain; flex-shrink: 0;">
            <?php endif; ?>

            <div style="font-size: 11px; line-height: 1.4;">
                <?php if (!empty($header['my_company'])): ?>
                    <h1 style="margin: 0 0 4px; font-size: 18px; color: #0f172a;">
                        <?= $header['my_company'] ?>
                    </h1>
                <?php endif; ?>

                <?php if (!empty($header['my_address'])): ?>
                    <p style="margin: 0; color: #64748b;"><?= $header['my_address'] ?></p>
                <?php endif; ?>

                <?php if (!empty($header['my_tax']) || !empty($header['my_phone'])): ?>
                    <p style="margin: 2px 0 0; color: #64748b;">
                        <?php if (!empty($header['my_tax'])): ?>
                            <b>Tax ID:</b> <?= $header['my_tax'] ?>
                        <?php endif; ?>

                        <?php if (!empty($header['my_phone'])): ?>
                            <span style="margin-left: 10px;"><b>Tel:</b> <?= $header['my_phone'] ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 25px; color: var(--primary-color); font-weight: 900;">ใบสรุปงวดงาน</h2>
            <p style="margin: 0; font-size: 10px; letter-spacing: 3px; color: #94a3b8; text-transform: uppercase;">
                MILESTONE SUMMARY REPOR
            </p>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 12px; gap: 10px;">
        <div
            style="flex: 1.2; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; background: #f8fafc;">
            <p style="margin: 0 0 2px; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                ข้อมูลโครงการ
            </p>
            <h3 style="margin: 0; font-size: 14px; color: #0f172a; line-height: 1.3;"><?= $first['project_name'] ?></h3>

            <div
                style="margin-top: 4px; font-size: 11px; color: #64748b; display: flex; flex-wrap: wrap; gap: 4px 12px;">
                <div><strong>เลขที่:</strong> <?= $first['project_no'] ?: '-' ?></div>
                <div><strong>ผู้รับจ้าง:</strong> <?= $first['contractor_name'] ?: '-' ?></div>

                <div
                    style="flex-basis: 100%; display: flex; gap: 10px; margin-top: 2px; padding-top: 2px; border-top: 1px dashed #e2e8f0; font-size: 10px;">
                    <div><strong>ธนาคาร:</strong> <?= $first['bank_name'] ?: '-' ?></div>
                    <div><strong>ชื่อบัญชี:</strong> <?= $first['bank_account_name'] ?: '-' ?></div>
                    <div><strong>เลขบัญชี:</strong> <?= $first['bank_account_no'] ?: '-' ?></div>
                </div>
            </div>

            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px; padding-top: 4px; border-top: 1px solid #f1f5f9;">
                <span style="font-size: 10px; color: #94a3b8; font-weight: 700;">ระยะเวลาโครงการ</span>
                <div
                    style="font-size: 11px; color: #475569; font-weight: 800; background: #fff; padding: 2px 8px; border-radius: 6px; border: 1px solid #f1f5f9;">
                    <?= ($first['start_date'] != '0000-00-00' && $first['start_date']) ? date('d/m/Y', strtotime($first['start_date'])) : 'รอกำหนด' ?>
                    <span style="color: #cbd5e1; margin: 0 4px;">-</span>
                    <?= ($first['end_date'] != '0000-00-00' && $first['end_date']) ? date('d/m/Y', strtotime($first['end_date'])) : 'รอกำหนด' ?>
                </div>
            </div>
        </div>

        <div
            style="flex: 1; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; background: #f8fafc; display: flex; flex-direction: column; justify-content: center;">
            <div style="text-align: right; margin-bottom: 4px;">
                <p style="margin: 0; font-size: 10px; font-weight: 700; color: #64748b;">มูลค่าสัญญา (ฐานเงินต้น)</p>
                <h2 style="margin: 0; color: #1e293b; font-size: 16px; font-weight: 900;">
                    <?= number_format($first['contract_value'], 2) ?>
                </h2>
            </div>

            <div
                style="display: flex; justify-content: space-between; font-size: 10px; padding: 4px 0; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; margin: 2px 0;">
                <span><b style="color: #64748b;">VAT:</b> <span
                        style="color: #475569;">+<?= number_format($first['total_vat_amount'], 2) ?></span></span>
                <span><b style="color: #64748b;">หัก ณ ที่จ่าย:</b> <span
                        style="color: #475569;">-<?= number_format($first['total_wht_amount'], 2) ?></span></span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 4px;">
                <span style="font-size: 10px; font-weight: 800; color: #1e293b;">รวมสุทธิ</span>
                <span style="font-size: 16px; font-weight: 900; color: #1e293b;">
                    <?= number_format($first['net_contract_value'], 2) ?> <span style="font-size: 9px;"> THB</span>
                </span>
            </div>
        </div>
    </div>

    <div class="item-section">
        <div class="item-section">
            <table class="table-items">
                <thead>
                    <tr>
                        <th width="10%">วันที่เบิก</th>
                        <th align="left">งวดงาน / รายละเอียด</th>
                        <th width="15%" align="right">จำนวนเงิน</th>
                        <th width="12%" align="right">VAT (7%)</th>
                        <th width="12%" align="right">หัก (3%)</th>
                        <th width="15%" align="right">ยอดสุทธิ</th>
                        <th width="10%" align="right">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($milestones as $index => $m): ?>
                        <tr>
                            <td align="center"><?= date('d/m/Y', strtotime($m['claim_date'])) ?></td>
                            <td>
                                <div
                                    style="font-weight: bold;  max-width: 300px; word-break: break-word; overflow-wrap: break-word;">
                                    <?= htmlspecialchars($m['milestone_name']) ?>
                                </div>
                                <div
                                    style="font-size: 11px; color: #94a3b8;  max-width: 300px; word-break: break-word; overflow-wrap: break-word;">
                                    <?= htmlspecialchars($m['remarks']) ?>
                                </div>
                            </td>
                            <td align="right"><?= number_format($m['amount'], 2) ?></td>
                            <td align="right"><?= number_format($m['vat_amount'], 2) ?></td>
                            <td align="right" style="color: #1e293b;">-<?= number_format($m['wht_amount'], 2) ?></td>
                            <td align="right" style="font-weight: bold;"><?= number_format($m['net_amount'], 2) ?></td>
                            <td class="px-4 py-4 text-right">
                                <span class="font-bold <?= $m['status'] == 'paid' ?>">
                                    <?= $m['status'] == 'paid' ? 'ชำระแล้ว' : 'ค้างชำระ' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="doc-footer">
            <div style="display: flex; justify-content: space-between; margin-bottom: 40px;">
                <div style="width: 55%; padding-right: 30px;">
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <?php
                        // รีเซ็ตตัวชี้ข้อมูลเพื่อให้วนลูปใหม่ได้
                        mysqli_data_seek($res_history, 0);
                        while ($row = mysqli_fetch_assoc($res_history)):
                            // เช็คว่ามีรายการหักไหม
                            $has_deduction = ($row['retention_percent'] > 0 || !empty($row['deduction_note']));

                            // ถ้าไม่มีการหักเงิน ให้ข้ามงวดนี้ไปเลย ไม่ต้องแสดงผล
                            if (!$has_deduction)
                                continue;
                            ?>
                            <div style="margin-bottom: 2px; font-size: 10px; line-height: 1.4; color: #000;">
                                <strong>- <?= htmlspecialchars($row['milestone_name']) ?></strong>
                                <?php if ($row['retention_percent'] > 0): ?>
                                    หัก<?= !empty($row['deduction_note']) ? htmlspecialchars($row['deduction_note']) : 'เงินประกัน' ?>
                                    <?= number_format($row['retention_percent'], 0) ?>%
                                    (<?= number_format($row['retention_amount'], 2) ?> บาท)
                                <?php elseif (!empty($row['deduction_note'])): ?>
                                    หมายเหตุ: <?= htmlspecialchars($row['deduction_note']) ?>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <div style="margin-top: 8px;">
                        <p style="font-size: 10px; font-weight: bold; margin-bottom: 2px;">หมายเหตุ:</p>
                        <p style="font-size: 10px; color: #64748b; line-height: 1.4; margin: 0;">
                            <?= !empty($pj['project_remarks']) ? $pj['project_remarks'] : '' ?>
                        </p>
                    </div>
                </div>
                <div style="width: 40%;">
                    <div style="background: #f8fafc; padding: 10px; border-radius: 12px; border: 1px solid #f1f5f9;">
                        <table width="100%" style="font-size: 11px; border-collapse: collapse; line-height: 1.2;">
                            <tr>
                                <td style="padding: 2px 0; color: #64748b;">รวมเงินก่อนภาษี</td>
                                <td align="right" style="color: #1e293b;"><b><?= number_format($total_amount, 2) ?></b>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px 0; color: #64748b;">ภาษีมูลค่าเพิ่ม</td>
                                <td align="right" style="color: #1e293b;"><?= number_format($total_vat, 2) ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 2px 0; color: #64748b;">หัก ณ ที่จ่าย</td>
                                <td align="right" style="color: #1e293b;">-<?= number_format($total_wht, 2) ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 2px 0; color: #64748b;">หัก อื่นๆ (เช่นประกันผลงาน)</td>
                                <td align="right" style="color: #1e293b;">-<?= number_format($total_other, 2) ?></td>
                            </tr>
                            <tr style="font-size: 14px; color: #1e293b; font-weight: 900;">
                                <td style="padding-top: 8px; border-top: 1px solid #e2e8f0;">ยอดสุทธิ</td>
                                <td align="right" style="padding-top: 8px; border-top: 1px solid #e2e8f0;">
                                    <?= number_format($total_net, 2) ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" align="right"
                                    style="font-size: 9px; color: #94a3b8; padding-top: 2px; font-style: italic;">
                                    ( <?= BahtText($total_net) ?> )
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div
                style="position: absolute; bottom: 30px; left: 0; width: 100%; display: flex; justify-content: space-between; text-align: center; gap: 20px; padding: 0 40px;">

                <div style="width: 32%;">
                    <div
                        style="height: 60px; display: flex; align-items: center; justify-content: center; border-bottom: 1px dotted #cbd5e1; margin-bottom: 8px;">
                        <?php if (!empty($first['creator_signature'])): ?>
                            <img src="uploads/signatures/<?= $first['creator_signature'] ?>"
                                style="max-height: 50px; object-fit: contain;">
                        <?php endif; ?>
                    </div>
                    <p style="margin: 0; font-weight: bold; font-size: 13px; color: #0f172a;">ผู้จัดทำ</p>
                    <p style="margin: 4px 0 0; font-size: 11px; color: #64748b;">
                        (<?= $_SESSION['user_name'] ?? '................................' ?>)
                    </p>
                    <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">
                        วันที่:
                        <?= isset($first['created_at']) ? date('d/m/Y', strtotime($first['created_at'])) : '......../......../........' ?>
                    </p>
                </div>

                <div style="width: 32%;">
                    <div
                        style="height: 60px; display: flex; align-items: center; justify-content: center; border-bottom: 1px dotted #cbd5e1; margin-bottom: 8px;">
                        <?php if (($first['status'] ?? '') === 'approved' && !empty($first['approver_signature'])): ?>
                            <img src="uploads/signatures/<?= $first['approver_signature'] ?>"
                                style="max-height: 50px; object-fit: contain;">
                        <?php endif; ?>
                    </div>
                    <p style="margin: 0; font-weight: bold; font-size: 13px; color: #0f172a;">ผู้อนุมัติ</p>
                    <p style="margin: 4px 0 0; font-size: 11px; color: #64748b;">(
                        <?= $first['approver_name'] ?? '.......................................' ?> )
                    </p>
                    <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">วันที่
                        <?= !empty($first['approved_at']) ? date('d/m/Y', strtotime($first['approved_at'])) : '......../......../........' ?>
                    </p>
                </div>

                <div style="width: 32%;">
                    <div style="height: 60px; border-bottom: 1px dotted #cbd5e1; margin-bottom: 8px;"></div>
                    <p style="margin: 0; font-weight: bold; font-size: 13px; color: #0f172a;">ผู้รับจ้าง / ร้านค้า</p>
                    <p style="margin: 4px 0 0; font-size: 11px; color: #64748b;">
                        <?= !empty($first['contractor_name']) ? $first['contractor_name'] : '(.........................................)' ?>
                    </p>
                    <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">วันที่ ......../......../........</p>
                </div>

            </div>
        </div>

        <div
            style="margin-top: 40px; text-align: center; font-size: 9px; color: #cbd5e1; text-transform: uppercase; letter-spacing: 1px;">
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function exportPDF() {
            const element = document.getElementById('quotation-content');
            element.style.overflow = 'hidden';

            const opt = {
                margin: 0,
                filename: 'Quotation_<?php echo $data['doc_no']; ?>.pdf',
                image: { type: 'jpeg', quality: 1.0 },
                html2canvas: {
                    scale: 3,
                    useCORS: true,
                    letterRendering: true,
                    logging: false,
                    scrollY: 0 // บังคับให้เริ่มจับภาพจากด้านบนสุด ป้องกันหน้าว่าง
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // แก้ไขตรงนี้: ใช้ workflow แบบแยกส่วนเพื่อเข้าถึงตัวแปร pdf
            html2pdf().set(opt).from(element).toPdf().get('pdf').then(function (pdf) {
                // เช็คจำนวนหน้าทั้งหมด
                const totalPages = pdf.internal.getNumberOfPages();

                // ถ้ามีมากกว่า 1 หน้า ให้วนลบทิ้งตั้งแต่หน้า 2 เป็นต้นไป
                if (totalPages > 1) {
                    for (let i = totalPages; i > 1; i--) {
                        pdf.deletePage(i);
                    }
                }
            }).save().then(() => {
                element.style.overflow = '';
            });
        }
    </script>
    <script>
        function toggleSignature() {
            // 1. ถ้าจารต้องการให้กดแล้วเด้งไปหน้าสร้างเลย
            window.location.href = 'create_signature.php';

            // หรือถ้าอยากให้เปิด Tab ใหม่ (จะได้ไม่หลุดจากหน้า PR)
            // window.open('create_signature.php', '_blank');
        }

        // 2. ส่งออกเป็น Word (.doc)
        function exportWord() {
            const header = "<html xmlns:o='urn:schemas-microsoft-com:office:office' " +
                "xmlns:w='urn:schemas-microsoft-com:office:word' " +
                "xmlns='http://www.w3.org/TR/REC-html40'>" +
                "<head><meta charset='utf-8'><title>Export Word</title></head><body>";
            const footer = "</body></html>";
            const sourceHTML = header + document.getElementById("quotation-content").innerHTML + footer;

            const source = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent(sourceHTML);
            const fileDownload = document.createElement("a");
            document.body.appendChild(fileDownload);
            fileDownload.href = source;
            fileDownload.download = 'Quotation_<?php echo $data["doc_no"]; ?>.doc';
            fileDownload.click();
            document.body.removeChild(fileDownload);
        }
    </script>

    <?php include('footer.php'); ?>