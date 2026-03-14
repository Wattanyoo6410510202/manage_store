<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';

if (empty($id)) {
    die("ไม่พบเลขที่เอกสาร");
}

// 1. ดึงข้อมูล Invoice และข้อมูลลูกค้า
$sql = "SELECT i.*, 
               i.sub_total AS subtotal, 
               i.vat_amount AS vat_amount, 
               i.total_amount AS grand_total, 
               c.customer_name, c.address as cust_address, c.tax_id as cust_tax, 
               c.contact_person, c.phone as cust_phone, c.email as cust_email,
               s.company_name as my_company, 
               s.tax_id as my_tax, 
               s.phone as my_phone, 
               s.email as my_email, 
               s.address as my_address, 
               s.logo_path,
               u.name as creator_name,
               app.name as approver_name
        FROM invoices i 
        LEFT JOIN customers c ON i.customer_id = c.id 
        LEFT JOIN suppliers s ON i.supplier_id = s.id
        LEFT JOIN users u ON i.created_by = u.id
        LEFT JOIN users app ON i.approved_by = app.id
        WHERE i.id = '$id' LIMIT 1";
$res = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($res);

if (!$data) {
    die("ไม่พบข้อมูลในระบบ");
}

// 2. ดึงรายการสินค้า
$res_items = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id = '$id'");
$num_rows = mysqli_num_rows($res_items);


// จัดการ Path ของ Logo ให้เรียบร้อย
$logo_path = (!empty($sup['logo_path']) && file_exists('uploads/' . $sup['logo_path']))
    ? 'uploads/' . $sup['logo_path']
    : '';
// 4. เช็คส่วนลดรวม
$sum_discount_res = mysqli_query($conn, "SELECT SUM(discount_amount) as total_discount FROM invoice_items WHERE invoice_id = '$id'");
$row_discount = mysqli_fetch_assoc($sum_discount_res);
$total_discount = $row_discount['total_discount'] ?? 0;

// --- ลายเซ็น (Logic เดิมของจาร) ---
$prepared_by_id = $data['created_by'];
$sig_sql = "SELECT path FROM signatures WHERE users_id = '$prepared_by_id' LIMIT 1";
$sig_result = mysqli_query($conn, $sig_sql);
$sig_data = mysqli_fetch_assoc($sig_result);
$prepared_sig = (!empty($sig_data['path']) && file_exists('uploads/signatures/' . $sig_data['path'])) ? 'uploads/signatures/' . $sig_data['path'] : '';

$approved_sig = '';
if ($data['status'] === 'approved' || $data['status'] === 'paid') {
    if (!empty($data['approved_by'])) {
        $approver_id = $data['approved_by'];
        $sig_app_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT path FROM signatures WHERE users_id = '$approver_id' LIMIT 1"));
        if (!empty($sig_app_data['path']) && file_exists('uploads/signatures/' . $sig_app_data['path'])) {
            $approved_sig = 'uploads/signatures/' . $sig_app_data['path'];
        }
    }
}

// --- Logic ปรับ Font/Padding ---
if ($num_rows <= 5) {
    $dynamic_padding = '12px 15px';
    $dynamic_font_size = '14px';
} elseif ($num_rows <= 10) {
    $dynamic_padding = '8px 12px';
    $dynamic_font_size = '13px';
} else {
    $dynamic_padding = '5px 10px';
    $dynamic_font_size = '12px';
}
?>

<style>
    :root {
        --primary-color: #7c3aed;
        /* สีม่วงสำหรับใบเสร็จ */
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
        border-bottom: 1px solid #f1f5f9;
    }

    .receipt-watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 80px;
        color: rgba(0, 0, 0, 0.03);
        font-weight: 900;
        pointer-events: none;
        z-index: 0;
    }
</style>

<?php
// ฟังก์ชันแปลงเลขเป็นตัวอักษรภาษาไทย
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
    $number = $number + 0;
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
    <div class="receipt-watermark z-index: 9999;">PAID / ชำระแล้ว</div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 20px; position: relative; z-index: 1;">
        <div style="display: flex; gap: 15px;">
            <?php
            // เช็ค Path รูปให้ถูก
            $display_logo = (!empty($data['logo_path']) && file_exists('uploads/' . $data['logo_path']))
                ? 'uploads/' . $data['logo_path'] : '';
            ?>

            <?php if ($display_logo): ?>
                <img src="<?= $display_logo ?>" style="width: 80px; height: 80px; object-fit: contain;">
            <?php endif; ?>

            <div style="font-size: 12px; line-height: 1.5;">
                <h1 style="margin: 0 0 5px; font-size: 20px; color: #0f172a; font-weight: 800;">
                    <?= htmlspecialchars($data['my_company'] ?? '') ?>
                </h1>

                <?php if (!empty($data['my_address'])): ?>
                    <p style="margin: 0; color: #475569;"><?= htmlspecialchars($data['my_address']) ?></p>
                <?php endif; ?>

                <?php if (!empty($data['my_tax']) || !empty($data['my_phone'])): ?>
                    <p style="margin: 2px 0 0; color: #475569;">
                        <?php if (!empty($data['my_tax'])): ?>
                            <b>Tax ID:</b> <?= htmlspecialchars($data['my_tax']) ?>
                        <?php endif; ?>

                        <?php if (!empty($data['my_tax']) && !empty($data['my_phone'])): ?>
                            |
                        <?php endif; ?>

                        <?php if (!empty($data['my_phone'])): ?>
                            <b>Tel:</b> <?= htmlspecialchars($data['my_phone']) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 900;">
                ใบเสร็จรับเงิน/<br>ใบกำกับภาษี</h2>
            <p style="margin: 0; font-size: 10px; letter-spacing: 2px; color: #94a3b8;">RECEIPT / TAX INVOICE</p>
        </div>
    </div>

    <div
        style="display: flex; justify-content: space-between; margin-bottom: 20px; gap: 20px; position: relative; z-index: 1;">
        <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #f8fafc;">
            <p style="margin: 0 0 8px; font-size: 10px; font-weight: bold; color: var(--primary-color);">CUSTOMER /
                ลูกค้า</p>
            <h3 style="margin: 0 0 5px; font-size: 15px; color: #0f172a;"><?= $data['customer_name'] ?></h3>
            <div style="font-size: 12px; color: #475569; line-height: 1.6;">
                <?= nl2br($data['cust_address']) ?><br>
                <b>เลขประจำตัวผู้เสียภาษี:</b> <?= $data['cust_tax'] ?>
            </div>
        </div>
        <div style="width: 250px; font-size: 13px;">
            <div
                style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">เลขที่ใบเสร็จ</span>
                <span style="font-weight: bold; color: #0f172a;">
                    <?= str_replace("INV", "RT", $data['invoice_no']) ?>
                </span>
            </div>
            <div
                style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">วันที่ออกเอกสาร</span>
                <span style="font-weight: bold; color: #0f172a;">
                    <?= date('d/m/Y') ?>
                </span>
            </div>
            <div
                style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">อ้างอิงใบแจ้งหนี้</span>
                <span><?= $data['invoice_no'] ?></span>
            </div>
        </div>
    </div>

    <div class="item-section">
        <table class="table-items">
            <thead>
                <tr>
                    <th width="5%" style="padding: 12px 10px;">#</th>
                    <th align="left" style="padding: 12px 10px;">รายการ / Description</th>
                    <th width="15%" align="center">จำนวน</th>
                    <th width="15%" align="right">ราคา/หน่วย</th>
                    <th width="15%" align="right" style="padding-right: 15px;">จำนวนเงิน</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                if (mysqli_num_rows($res_items) > 0):
                    mysqli_data_seek($res_items, 0); // รีเซ็ต pointer เพื่อให้ดึงข้อมูลได้ใหม่
                    while ($item = mysqli_fetch_assoc($res_items)):
                        ?>
                        <tr>
                            <td align="center" style="vertical-align: top;"><?= $count++ ?></td>
                            <td style="vertical-align: top;">
                                <div style="font-weight: bold; color: #1e293b; margin-bottom: 2px;">
                                    <?= htmlspecialchars($item['item_name']) ?>
                                </div>
                                <?php if (!empty($item['description'])): ?>
                                    <div style="font-size: 11px; color: #64748b; line-height: 1.4;">
                                        <?= nl2br(htmlspecialchars($item['description'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td align="center" style="vertical-align: top;">
                                <?= number_format($item['qty'], 0) ?>
                                <span
                                    style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($item['unit_name'] ?? 'หน่วย') ?></span>
                            </td>
                            <td align="right" style="vertical-align: top;">
                                <?= number_format($item['price_per_unit'], 2) ?>
                            </td>
                            <td align="right" style="vertical-align: top; font-weight: 600; padding-right: 15px;">
                                <?= number_format($item['total_price'], 2) ?>
                            </td>
                        </tr>
                        <?php
                    endwhile;
                else:
                    ?>
                    <tr>
                        <td colspan="5" align="center" style="padding: 30px; color: #cbd5e1;">ไม่มีรายการสินค้า</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="doc-footer" style="position: relative; z-index: 1;">
        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
            <div style="width: 55%;">
            </div>
            <div style="width: 40%;">
                <table width="100%" style="font-size: 14px;">
                    <tr>
                        <td style="padding: 5px 0;">รวมเงิน / Subtotal</td>
                        <td align="right"><?= number_format($data['sub_total'], 2) ?></td>
                    </tr>

                    <?php if ($data['discount_amount'] > 0): ?>
                        <tr>
                            <td style="padding: 5px 0;">ส่วนลด / Discount</td>
                            <td align="right" class="text-red-500">-<?= number_format($data['discount_amount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($data['vat_amount'] > 0): ?>
                        <tr>
                            <td style="padding: 5px 0;">ภาษีมูลค่าเพิ่ม VAT <?= number_format($data['vat_percent'], 0) ?>%
                            </td>
                            <td align="right"><?= number_format($data['vat_amount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($data['wht_amount'] > 0): ?>
                        <tr>
                            <td style="padding: 5px 0;">หัก ณ ที่จ่าย (<?= number_format($data['wht_percent'], 0) ?>%)</td>
                            <td align="right" class="text-red-500">-<?= number_format($data['wht_amount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (!empty($data['paid_at'])): ?>
                        <tr style="font-size: 16px; font-weight: 900; color: var(--primary-color);">
                            <td style="padding: 10px 0; border-top: 2px solid var(--primary-color);">วันที่ชำระเงิน</td>
                            <td align="right"
                                style="padding: 10px 0; border-top: 2px solid var(--primary-color); font-weight: bold; color: var(--primary-color);">
                                <?= date('d/m/Y', strtotime($data['paid_at'])) ?>
                                <?php if (date('H:i', strtotime($data['paid_at'])) !== '00:00'): ?><br> 
                                    <span style="font-size: 11px; font-weight: normal;"> เวลา
                                        <?= date('H:i', strtotime($data['paid_at'])) ?> น.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr style="font-size: 18px; font-weight: 900; color: var(--primary-color);">
                        <td style="padding: 10px 0; ">ยอดที่ชำระแล้ว</td>
                        <td align="right" style="padding: 10px 0; ">
                            <?= number_format($data['total_amount'], 2) ?> บาท
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2" align="right"
                            style="font-size: 12px; color: #6b7280; font-style: italic; padding: 5px 0 15px 0;">
                            ( <?= BahtText($data['total_amount']) ?> )
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div
            style="display: flex; justify-content: space-between; text-align: center; font-size: 12px; margin-top: 30px;">

            <div style="width: 30%;">
                <div class="sig-box"
                    style="height: 60px; display: flex; align-items: center; justify-content: center; border-bottom: 1px dotted #cbd5e1; margin-bottom: 8px;">

                    <?php if ($prepared_sig): ?>
                        <img src="<?= $prepared_sig ?>?v=<?= time() ?>"
                            style="max-height: 50px; max-width: 100%; object-fit: contain;">

                    <?php else: ?>

                    <?php endif; ?>

                </div>
                <p style="margin: 0; font-weight: bold;">ผู้จัดทำ</p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #64748b;">(
                    <?= $data['creator_name'] ?? $data['created_by'] ?> )
                </p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">วันที่
                    <?= date('d/m/Y', strtotime($data['created_at'])) ?>
                </p>
            </div>

            <div style="width: 30%;">
                <div class="sig-box"
                    style="height: 60px; display: flex; align-items: center; justify-content: center; border-bottom: 1px dotted #cbd5e1; margin-bottom: 8px;">

                    <?php if ($approved_sig): ?>
                        <img src="<?= $approved_sig ?>?v=<?= time() ?>"
                            style="max-height: 50px; max-width: 100%; object-fit: contain;">
                    <?php elseif ($data['status'] === 'approved'): ?>
                        <span style="font-size: 10px; color: #94a3b8; font-style: italic;"></span>
                    <?php else: ?>
                        <span style="font-size: 10px; color: #cbd5e1; font-style: italic;"></span>
                    <?php endif; ?>

                </div>
                <p style="margin: 0; font-weight: bold;">ผู้มีอำนาจลงนามอนุมัติ</p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #64748b;">
                    (
                    <?= !empty($data['approver_name']) ? $data['approver_name'] : '...................................' ?>
                    )
                </p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">
                    วันที่
                    <?= ($data['approved_at']) ? date('d/m/Y', strtotime($data['approved_at'])) : 'วันที่ ......../......../........' ?>
                </p>
            </div>

            <div style="width: 30%;">
                <div class="sig-box" style="height: 60px; border-bottom: 1px dotted #cbd5e1; margin-bottom: 8px;"></div>
                <p style="margin: 0; font-weight: bold;">ลูกค้า</p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #64748b;">( <?= $data['customer_name'] ?> )</p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">วันที่ ......../......../........</p>
            </div>

        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function exportPDF() {
        const element = document.getElementById('quotation-content');
        element.style.overflow = 'hidden';

        const opt = {
            margin: 0,
            filename: '<?php echo $data['doc_no']; ?>.pdf',
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
        fileDownload.download = '<?php echo $data["doc_no"]; ?>.doc';
        fileDownload.click();
        document.body.removeChild(fileDownload);
    }
</script

<?php include('footer.php'); ?>