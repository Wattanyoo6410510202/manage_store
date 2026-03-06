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
               p.project_name, p.project_no, p.contractor_name, p.contract_value, p.start_date, p.end_date,
               s.company_name as my_company, s.address as my_address, s.tax_id as my_tax, s.phone as my_phone, s.logo_path
        FROM project_milestones m
        LEFT JOIN projects p ON m.project_id = p.id
        LEFT JOIN suppliers s ON s.id = 1 
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
$logo_path = (!empty($first['logo_path']) && file_exists('uploads/' . $first['logo_path']))
    ? 'uploads/' . $first['logo_path'] : '';

// 2. คำนวณยอดรวมจากทุกงวดที่เลือก
$total_amount = 0;
$total_vat = 0;
$total_wht = 0;
$total_net = 0;

foreach ($milestones as $m) {
    $total_amount += $m['amount'];
    $total_vat += $m['vat_amount'];
    $total_wht += $m['wht_amount'];
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

// คำนวณความแน่นของตารางตามจำนวนรายการ
if ($num_rows <= 5) {
    $dynamic_padding = '20px 15px';
    $dynamic_font_size = '14px';
} elseif ($num_rows <= 10) {
    $dynamic_padding = '12px 15px';
    $dynamic_font_size = '13px';
} else {
    $dynamic_padding = '5px 15px';
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
        <div style="display: flex; gap: 15px;">
            <!-- <?php if ($logo_path): ?>
                <img src="<?= $logo_path ?>" style="width: 70px; height: 70px; object-fit: contain;">
            <?php else: ?>
                <div
                    style="width: 70px; height: 70px; background: #0f172a; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;">
                    <?= mb_substr($data['my_company'], 0, 1, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <div style="font-size: 11px; line-height: 1.4;">
                <h1 style="margin: 0 0 4px; font-size: 18px; color: #0f172a;"><?= $data['my_company'] ?></h1>
                <p style="margin: 0; color: #64748b;"><?= $data['my_address'] ?></p>
                <p style="margin: 2px 0 0; color: #64748b;"><b>Tax ID:</b> <?= $data['my_tax'] ?> | <b>Tel:</b>
                    <?= $data['my_phone'] ?></p>
            </div> -->
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 28px; color: var(--primary-color); font-weight: 900;">ใบสรุปงวดงาน</h2>
            <p style="margin: 0; font-size: 10px; letter-spacing: 3px; color: #94a3b8; text-transform: uppercase;">
                MILESTONE SUMMARY REPOR
            </p>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 15px; gap: 15px;">
        <div
            style="flex: 1; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; background: #f8fafc; ">
            <p
                style="margin: 0 0 5px; font-size: 9px; font-weight: bold; color: var(--primary-color); text-transform: uppercase;">
                ข้อมูลโครงการ (Project Description)
            </p>
            <h3 style="margin: 0; font-size: 14px; color: #0f172a;"><?= $first['project_name'] ?></h3>

            <div style="margin: 5px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
                <?= $first['project_no'] ?><br>

                <?php if (!empty($data['cust_tax'])): ?>
                    <b>ผู้รับจ้าง:</b> <?= $first['contractor_name'] ?: '-' ?><br>
                <?php endif; ?>

                <div
                    style="border: 1px solid var(--border-color); border-radius: 12px; padding: 15px; text-align: right;">
                    <p style="margin: 0 0 5px; font-size: 10px; font-weight: bold; color: #64748b;">TOTAL CONTRACT VALUE
                    </p>
                    <h2 style="margin: 0; color: var(--primary-color);">฿
                        <?= number_format($first['contract_value'], 2) ?>
                    </h2>
                    <div style="margin-top: 5px; font-size: 11px; color: #94a3b8;">
                        ระยะเวลา: <?= date('d/m/Y', strtotime($first['start_date'])) ?> -
                        <?= date('d/m/Y', strtotime($first['end_date'])) ?>
                    </div>
                </div>


                <?php if (!empty($data['contact_person']) || !empty($data['cust_phone']) || !empty($data['cust_email'])): ?>
                    <div style="margin-top: 4px;">
                        <?php if (!empty($data['cust_email'])): ?>
                            <span style="color: #64748b;"> | อีเมล: </span><span
                                style="color: #0f172a; text-decoration: none;"><?= htmlspecialchars($data['cust_email']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- <div style="width: 250px; font-size: 12px;">
            <div
                style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">เลขที่ / No.</span>
                <span style="font-weight: bold; color: #0f172a;"><?= htmlspecialchars($data['doc_no']) ?></span>
            </div>

            <div
                style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">วันที่ / Date</span>
                <span
                    style="color: #0f172a; font-weight: bold;"><?= date('d/m/Y', strtotime($data['created_at'])) ?></span>
            </div>
            <div
                style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">วันที่ต้องการสินค้า</span>
                <span style="color: #0f172a; font-weight: bold;">
                    <?= (!empty($data['due_date']) && $data['due_date'] != '0000-00-00') ? date('d/m/Y', strtotime($data['due_date'])) : '-' ?>
                </span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                <span style="color: #64748b;">เลขที่อ้างอิง</span>
                <span style="color: #0f172a; font-weight: bold;"><?= $data['reference_no'] ?></span>
            </div>
        </div> -->
    </div>

    <div class="item-section">
        <table class="table-items">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th align="left">งวดงาน / รายละเอียด</th>
                    <th width="15%" align="right">จำนวนเงิน</th>
                    <th width="12%" align="right">VAT (7%)</th>
                    <th width="12%" align="right">WHT (3%)</th>
                    <th width="15%" align="right">ยอดสุทธิ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($milestones as $index => $m): ?>
                    <tr>
                        <td align="center"><?= $index + 1 ?></td>
                        <td>
                            <div style="font-weight: bold;"><?= htmlspecialchars($m['milestone_name']) ?></div>
                            <div style="font-size: 11px; color: #94a3b8;">
                                <?= htmlspecialchars($m['remarks']) ?>
                            </div>
                        </td>
                        <td align="right"><?= number_format($m['amount'], 2) ?></td>
                        <td align="right"><?= number_format($m['vat_amount'], 2) ?></td>
                        <td align="right" style="color: #ef4444;">-<?= number_format($m['wht_amount'], 2) ?></td>
                        <td align="right" style="font-weight: bold;"><?= number_format($m['net_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="doc-footer">
        <div style="display: flex; justify-content: space-between; margin-bottom: 40px;">
            <div style="width: 55%; padding-right: 30px;">
                <p style="font-size: 11px; font-weight: bold; text-decoration: underline; margin-bottom: 5px;">หมายเหตุ:
                </p>
                <p style="font-size: 11px; color: #64748b; line-height: 1.6;"><?= nl2br($data['notes']) ?: '-' ?></p>
            </div>
            <div style="width: 40%;">
                <div style="background: #f1f5f9; padding: 15px; border-radius: 8px;">
                    <table width="100%" style="font-size: 14px; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 5px 0;">รวมเงินก่อนภาษี</td>
                            <td align="right"><b><?= number_format($total_amount, 2) ?></b></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0;">ภาษีมูลค่าเพิ่ม (VAT)</td>
                            <td align="right"><?= number_format($total_vat, 2) ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; color: #ef4444;">หัก ณ ที่จ่าย (WHT)</td>
                            <td align="right" style="color: #ef4444;">-<?= number_format($total_wht, 2) ?></td>
                        </tr>
                        <tr style="font-size: 18px; color: var(--primary-color); font-weight: 900;">
                            <td style="padding-top: 15px; border-top: 2px solid #cbd5e1;">ยอดรวมเบิกสุทธิ</td>
                            <td align="right" style="padding-top: 15px; border-top: 2px solid #cbd5e1;">฿
                                <?= number_format($total_net, 2) ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right" style="font-size: 11px; color: #64748b; padding-top: 8px;">
                                ( <?= BahtText($total_net) ?> )
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; margin-top: 50px; text-align: center;">

            <div style="width: 30%;">
                <div class="sig-box"
                    style="border-bottom: 1px solid #cbd5e1; height: 60px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center;">
                    <?php if (!empty($data['creator_signature'])): ?>
                        <img src="uploads/signatures/<?= $data['creator_signature'] ?>"
                            style="max-height: 60px; max-width: 140px; object-fit: contain;">
                    <?php endif; ?>
                </div>
                <p style="margin: 0; font-weight: bold; font-size: 12px;">ผู้จัดทำ</p>
                <p style="margin: 2px 0 0; font-size: 10px; color: #64748b;">(
                    <?= $data['creator_name'] ?: '................................' ?> )
                </p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">วันที่
                    <?= date('d/m/Y', strtotime($data['created_at'])) ?>
                </p>
            </div>

            <div style="width: 30%;">
                <div class="sig-box"
                    style="border-bottom: 1px solid #cbd5e1; height: 60px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center;">
                    <?php if ($data['status'] === 'approved' && !empty($data['approver_signature'])): ?>
                        <img src="uploads/signatures/<?= $data['approver_signature'] ?>"
                            style="max-height: 60px; max-width: 140px; object-fit: contain;">
                    <?php endif; ?>
                </div>
                <p style="margin: 0; font-weight: bold; font-size: 12px;">ผู้อนุมัติ</p>
                <p style="margin: 2px 0 0; font-size: 10px; color: #64748b;">(
                    <?= $data['approver_name'] ?: '................................................................' ?>
                    )
                </p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">วันที่
                    <?= $data['approved_at'] ? date('d/m/Y', strtotime($data['approved_at'])) : '......../......../........' ?>
                </p>
            </div>

            <div style="width: 30%;">
                <div class="sig-box" style="border-bottom: 1px solid #cbd5e1; height: 60px; margin-bottom: 10px;"></div>
                <p style="margin: 0; font-weight: bold; font-size: 12px;">ผู้รับสั่งซื้อ / ร้านค้า</p>
                <p style="margin: 2px 0 0; font-size: 10px; color: #64748b;">( <?= $data['customer_name'] ?> )</p>
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