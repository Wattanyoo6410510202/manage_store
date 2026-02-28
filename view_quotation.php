<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
if (empty($id)) {
    die("ไม่พบรหัสเอกสาร");
}

$sql = "SELECT q.*, 
               c.customer_name, c.address as cust_address, c.tax_id as cust_tax, c.contact_person, c.phone as cust_phone, c.email as cust_email,
               s.company_name as my_company, s.tax_id as my_tax, s.phone as my_phone, 
               s.email as my_email, s.address as my_address, s.logo_path
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN suppliers s ON q.supplier_id = s.id 
        WHERE q.id = '$id' LIMIT 1";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);
$sql_items = "SELECT * FROM quotation_items WHERE quotation_id = '$id' ORDER BY id ASC";
$res_items = mysqli_query($conn, $sql_items);
$logo_path = !empty($data['logo_path']) ? 'uploads/' . $data['logo_path'] : '';
?>
<?php
$num_rows = mysqli_num_rows($res_items);

if ($num_rows <= 5) {
    // โหมดโล่ง (1-5 รายการ)
    $padding = '20px 15px';
    $font_size = '14px';
    $line_height = '1.8';
} elseif ($num_rows <= 10) {
    // โหมดปกติ (6-10 รายการ)
    $padding = '12px 15px';
    $font_size = '13px';
    $line_height = '1.6';
} elseif ($num_rows <= 15) {
    // โหมดประหยัดพื้นที่ (11-15 รายการ)
    $padding = '6px 15px';
    $font_size = '12px';
    $line_height = '1.4';
} else {
    // โหมดอัดแน่น (16 รายการขึ้นไป)
    $padding = '3px 15px';
    $font_size = '11px';
    $line_height = '1.2';
}
?>

<style>
    /* ใช้ CSS Variable เพื่อความคลีน */
    :root {
        --row-padding:
            <?= $dynamic_padding ?>
        ;
        --item-font-size:
            <?= $dynamic_font_size ?>
        ;
    }

    .table-items tbody td {
        padding: var(--row-padding) !important;
        font-size: var(--item-font-size);
    }
</style>

<?php
function BahtText($amount)
{
    $amount_number = number_format($amount, 2, '.', '');
    $pt = strpos($amount_number, '.');
    $number = $fraction = "";
    if ($pt === false) {
        $number = $amount_number;
    } else {
        $number = substr($amount_number, 0, $pt);
        $fraction = substr($amount_number, $pt + 1);
    }

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
    $number = $number + 0;
    $ret = "";
    if ($number == 0)
        return $ret;
    if ($number > 1000000) {
        $ret .= ReadNumber(intval($number / 1000000)) . "ล้าน";
        $number = $number % 1000000;
    }

    $t = sprintf("%06d", $number);
    $adjective = false;
    for ($i = 0; $i < 6; $i++) {
        $n = substr($t, $i, 1);
        if ($n != "0") {
            if ($i == 4 && $n == "1") {
                $ret .= "สิบ";
            } elseif ($i == 4 && $n == "2") {
                $ret .= "ยี่สิบ";
            } elseif ($i == 5 && $n == "1" && $adjective) {
                $ret .= "เอ็ด";
            } else {
                $ret .= $number_call[$n] . $position_call[$i];
            }
            $adjective = true;
        } else {
            $adjective = false;
        }
    }
    return $ret;
}
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
            <?php if ($logo_path): ?>
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
            </div>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 28px; color: var(--primary-color); font-weight: 900;">ใบเสนอราคา</h2>
            <p style="margin: 0; font-size: 10px; letter-spacing: 3px; color: #94a3b8; text-transform: uppercase;">
                Quotation</p>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 15px; gap: 15px;">
        <div
            style="flex: 1; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; background: #f8fafc; ">
            <p
                style="margin: 0 0 5px; font-size: 9px; font-weight: bold; color: var(--primary-color); text-transform: uppercase;">
                Customer / ลูกค้า
            </p>
            <h3 style="margin: 0; font-size: 14px; color: #0f172a;"><?= $data['customer_name'] ?></h3>

            <div style="margin: 5px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
                <?= nl2br($data['cust_address']) ?><br>

                <?php if (!empty($data['cust_tax'])): ?>
                    <b>เลขประจำตัวผู้เสียภาษี:</b> <?= $data['cust_tax'] ?><br>
                <?php endif; ?>

                <?php if (!empty($data['contact_person']) || !empty($data['cust_phone']) || !empty($data['cust_email'])): ?>
                    <div style="margin-top: 4px;">
                        <b style="color: #475569;">ติดต่อ:</b>
                        <?= htmlspecialchars($data['contact_person'] ?? '-') ?>

                        <?php if (!empty($data['cust_phone'])): ?>
                            <span style="color: #64748b;"> | โทร: </span><?= htmlspecialchars($data['cust_phone']) ?>
                        <?php endif; ?>

                        <?php if (!empty($data['cust_email'])): ?>
                            <span style="color: #64748b;"> | อีเมล: </span><span
                                style="color: #0f172a; text-decoration: none;"><?= htmlspecialchars($data['cust_email']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div style="width: 250px; font-size: 12px;">
            <div
                style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">เลขที่ / No.</span>
                <span style="font-weight: bold; color: #0f172a;"><?= htmlspecialchars($data['doc_no']) ?></span>
            </div>

            <div
                style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">วันที่ / Date</span>
                <span
                    style="color: #0f172a; font-weight: bold;"><?= date('d/m/Y', strtotime($data['doc_date'])) ?></span>
            </div>

            <div
                style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">ยืนราคา / Valid Until</span>
                <span style="color: #0f172a;"><?= $data['valid_until'] ?> วัน</span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                <span style="color: #64748b;">การชำระเงิน / Payment</span>
                <span style="color: #0f172a;">
                    <?= ($data['payment_term'] == 'cash') ? 'เงินสด' : 'เครดิต ' . $data['payment_term'] . ' วัน' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="item-section">
        <table class="table-items">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th align="left">รายการ / Description</th>
                    <th width="5%" align="center">จำนวน</th>
                    <th width="12%" align="right">ราคา/หน่วย</th>
                    <th width="12%" align="right">ยอดรวม</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                // รีเซ็ต pointer ถ้ามีการเรียก mysqli_num_rows ไปก่อนหน้านี้
                mysqli_data_seek($res_items, 0);
                while ($item = mysqli_fetch_assoc($res_items)):
                    ?>
                    <tr>
                        <td align="center" style="color: #64748b;"><?= $count++ ?></td>
                        <td style="font-weight: 400; vertical-align: top; line-height: 1.4; color: #334155;">
                            <?= nl2br(htmlspecialchars($item['item_desc'])) ?>
                        </td>
                        <td align="center"><?= number_format($item['item_qty'], 0) ?></td>
                        <td align="right"><?= number_format($item['item_price'], 2) ?></td>
                        <td align="right" style="font-weight: 600;"><?= number_format($item['item_total'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
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
                    <table width="100%" style="font-size: 13px;">
                        <tr>
                            <td style="padding-bottom: 5px;">รวมเป็นเงิน</td>
                            <td align="right" style="padding-bottom: 5px;"><?= number_format($data['subtotal'], 2) ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-bottom: 5px;">ภาษีมูลค่าเพิ่ม 7%</td>
                            <td align="right" style="padding-bottom: 5px;"><?= number_format($data['vat'], 2) ?></td>
                        </tr>
                        <tr style="font-size: 16px; font-weight: 900; color: var(--primary-color);">
                            <td style="padding-top: 10px; border-top: 1px solid #cbd5e1;">รวมทั้งสิ้น</td>
                            <td align="right" style="padding-top: 10px; border-top: 1px solid #cbd5e1;">
                                <?= number_format($data['grand_total'], 2) ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right"
                                style="padding-top: 8px; font-size: 11px; color: #64748b; font-style: italic;">
                                ( <?= BahtText($data['grand_total']) ?> )
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; text-align: center; font-size: 12px;">
            <div style="width: 45%;">
                <div class="sig-box"></div>
                <p style="margin: 0; font-weight: bold;">ผู้รับบริการ / Customer</p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">วันที่ ......../......../........</p>
            </div>
            <div style="width: 45%;">
                <div class="sig-box"></div>
                <p style="margin: 0; font-weight: bold;">ผู้มีอำนาจลงนาม / Authorized Signature</p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #64748b;">( <?= $data['my_company'] ?> )</p>
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
    // 1. ลูกเล่นเปิด-ปิดลายเซ็น
    function toggleSignature() {
        const sigBox = document.querySelectorAll('.sig-box');
        const btn = document.getElementById('sigBtn');

        sigBox.forEach(el => {
            el.style.opacity = (el.style.opacity === '0') ? '1' : '0';
        });

        btn.classList.toggle('active');

        // แจ้งเตือนเล็กน้อย
        if (typeof renderAlert === 'function') {
            const status = btn.classList.contains('active') ? 'แสดงลายเซ็น' : 'ซ่อนลายเซ็น';
            renderAlert('success', status);
        }
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