<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

// รับค่าจาก URL
$customer_id = isset($_GET['customer_id']) ? mysqli_real_escape_string($conn, $_GET['customer_id']) : '';
$invoice_ids = isset($_GET['invoice_ids']) ? mysqli_real_escape_string($conn, $_GET['invoice_ids']) : '';

if (empty($customer_id) || empty($invoice_ids)) {
    die("ไม่พบข้อมูลสำหรับการสร้างใบวางบิล");
}

// 1. ดึงข้อมูลบริษัท (Supplier) รายการแรกสุดที่มีในระบบ
$sql_sup = "SELECT * FROM suppliers LIMIT 1";
$res_sup = mysqli_query($conn, $sql_sup);
$sup_data = mysqli_fetch_assoc($res_sup);

// 2. ดึงข้อมูลลูกค้า
$sql_cust = "SELECT * FROM customers WHERE id = '$customer_id' LIMIT 1";
$res_cust = mysqli_query($conn, $sql_cust);
$cust_data = mysqli_fetch_assoc($res_cust);

// รวมข้อมูลเข้าด้วยกันเพื่อใช้งานในโค้ดส่วนล่าง (จำลองโครงสร้างเดิมเพื่อไม่ให้ Error)
if ($cust_data) {
    $data = array_merge($cust_data, [
        'my_company' => $sup_data['company_name'] ?? 'ไม่ได้ระบุชื่อบริษัท',
        'my_tax' => $sup_data['tax_id'] ?? '-',
        'my_phone' => $sup_data['phone'] ?? '-',
        'my_email' => $sup_data['email'] ?? '-',
        'my_address' => $sup_data['address'] ?? '-',
        'logo_path' => $sup_data['logo_path'] ?? '',
        'bank_name' => $sup_data['bank_name'] ?? '-',
        'bank_account_name' => $sup_data['bank_account_name'] ?? '-',
        'bank_account_number' => $sup_data['bank_account_number'] ?? '-',
        'qr_code_path' => $sup_data['qr_code_path'] ?? ''
    ]);
} else {
    die("ไม่พบข้อมูลลูกค้าในระบบ");
}

// 3. ดึงรายการ Invoice ที่ถูกเลือกมาวางบิล
$sql_items = "SELECT * FROM invoices WHERE id IN ($invoice_ids) AND customer_id = '$customer_id' ORDER BY invoice_date ASC";
$res_items = mysqli_query($conn, $sql_items);

// ตัวแปรสะสมยอดรวมจากทุก Invoice
$total_subtotal = 0;
$total_vat = 0;
$total_wht = 0;
$total_grand = 0;

$items_array = [];
if ($res_items) {
    while ($row = mysqli_fetch_assoc($res_items)) {
        $items_array[] = $row;
        $total_subtotal += $row['sub_total'];
        $total_vat += $row['vat_amount'];
        $total_wht += $row['wht_amount'];
        $total_grand += $row['total_amount'];
    }
}

$logo_path = !empty($data['logo_path']) ? 'uploads/' . $data['logo_path'] : '';

// --- ลายเซ็น ---
$user_id = $_SESSION['user_id'] ?? 1;
$sig_sql = "SELECT path FROM signatures WHERE users_id = '$user_id' LIMIT 1";
$sig_res = mysqli_query($conn, $sig_sql);
$sig_data = $sig_res ? mysqli_fetch_assoc($sig_res) : null;
$prepared_sig = (!empty($sig_data['path']) && file_exists('uploads/signatures/' . $sig_data['path'])) ? 'uploads/signatures/' . $sig_data['path'] : '';


$prepared_by_id = $data['created_by'];
$sig_sql = "SELECT path FROM signatures WHERE users_id = '$prepared_by_id' LIMIT 1";
$sig_res = mysqli_query($conn, $sig_sql);
$sig_data = mysqli_fetch_assoc($sig_res);

// 1. กำหนด Path เต็มของไฟล์เพื่อเอาไว้เช็คในเครื่อง (Server Path)
$real_path = !empty($sig_data['path']) ? 'uploads/signatures/' . $sig_data['path'] : '';


?>
<?php
$num_rows = count($items_array);

// --- Logic ปรับค่าตามที่จารจำไว้ ---
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
        --primary-color: #0f172a;
        --border-color: #e2e8f0;
    }

    .table-items tbody td {
        padding: var(--row-padding) !important;
        font-size: var(--item-font-size);
        border-bottom: 1px solid var(--border-color);
    }

    @media print {
        .no-print {
            display: none !important;
        }

        .page-container {
            border: none !important;
            box-shadow: none !important;
            margin: 0 !important;
            width: 100% !important;
        }
    }
</style>

<?php
// ฟังก์ชันแปลงเลขเป็นตัวอักษรไทย (คงเดิม)
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

            <?php elseif (!empty($data['my_company']) && $data['my_company'] !== 'ไม่ระบุซัพพลายเออร์'): ?>
                <div
                    style="width: 70px; height: 70px; background: #0f172a; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;">
                    <?= mb_substr($data['my_company'], 0, 1, 'UTF-8') ?>
                </div>

            <?php endif; ?>
            <?php if (!empty($data['my_company']) && $data['my_company'] !== 'ไม่ระบุ'): ?>
                <div style="font-size: 11px; line-height: 1.4;">
                    <h1 style="margin: 0 0 4px; font-size: 18px; color: #0f172a;"><?= $data['my_company'] ?></h1>

                    <?php if (!empty($data['my_address'])): ?>
                        <p style="margin: 0; color: #64748b;"><?= $data['my_address'] ?></p>
                    <?php endif; ?>

                    <p style="margin: 2px 0 0; color: #64748b;">
                        <?php if (!empty($data['my_tax'])): ?>
                            <b>Tax ID:</b> <?= $data['my_tax'] ?>
                        <?php endif; ?>

                        <?php if (!empty($data['my_tax']) && !empty($data['my_phone'])): ?> | <?php endif; ?>

                        <?php if (!empty($data['my_phone'])): ?>
                            <b>Tel:</b> <?= $data['my_phone'] ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 28px; color: var(--primary-color); font-weight: 900;">ใบวางบิล</h2>
            <p style="margin: 0; font-size: 10px; letter-spacing: 3px; color: #94a3b8; text-transform: uppercase;">
                Billing</p>
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
                <?= nl2br($data['address']) ?><br>

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
    </div>


    <div class="item-section">
        <table class="table-items">
            <thead>
                <tr>
                    <th width="5%" style="padding: 12px; text-align: left;">#</th>
                    <th style="text-align: left;">เลขที่ใบแจ้งหนี้</th>
                    <th width="10%" style="text-align: center;">วันที่เอกสาร</th>
                    <th width="12%" style="text-align: center;">วันครบกำหนด</th>
                    <th width="13%" style="text-align: right;">จำนวนเงิน</th>
                    <th width="10%" style="text-align: right;">VAT</th>
                    <th width="10%" style="text-align: right;">WHT</th>
                    <th width="13%" style="text-align: right;">ยอดสุทธิ</th>
                </tr>
            </thead>
            <tbody style="font-size: 12px;">
                <?php if (empty($items_array)): ?>
                    <tr>
                        <td colspan="8" align="center" style="padding: 20px; color: #64748b;">ไม่พบรายการที่เลือก</td>
                    </tr>
                <?php else: ?>
                    <?php $count = 1;
                    foreach ($items_array as $item): ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td align="left" style="padding: 10px;"><?= $count++ ?></td>
                            <td>
                                <b style="color: #0f172a;"><?= htmlspecialchars($item['invoice_no']) ?></b>
                                <?php if (!empty($item['note'])): ?>
                                    <div style="font-size: 10px; color: #94a3b8; font-weight: normal;">
                                        <?= htmlspecialchars($item['note']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td align="right"><?= date('d/m/Y', strtotime($item['invoice_date'])) ?></td>
                            <td align="right">
                                <span
                                    style="<?= (strtotime($item['due_date']) < time()) ? 'color: #ef4444; font-weight: bold;' : '' ?>">
                                    <?= $item['due_date'] ? date('d/m/Y', strtotime($item['due_date'])) : '-' ?>
                                </span>
                            </td>

                            <td align="right"><?= number_format($item['sub_total'], 2) ?></td>

                            <td align="right" style="color: #64748b;">
                                <?= number_format($item['vat_amount'], 2) ?>
                            </td>

                            <td align="right" style="color: #ef4444;">
                                <?= $item['wht_amount'] > 0 ? '-' . number_format($item['wht_amount'], 2) : '0.00' ?>
                            </td>

                            <td align="right" style="font-weight: bold; color: #0f172a;">
                                <?= number_format($item['total_amount'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="display: flex; justify-content: space-between; margin-top: 30px; page-break-inside: avoid;">
        <div style="width: 55%;">
            <?php if (!empty($data['bank_name'])): ?>
                <p style="font-size: 11px; color: #64748b; font-weight: bold; margin-bottom: 5px;">
                    กรุณาชำระเงินตามบัญชีดังนี้
                </p>

                <div style="display: flex; align-items: center; gap: 12px;">
                    <?php if (!empty($data['qr_code_path'])): ?>
                        <div style="flex-shrink: 0;">
                            <img src="uploads/<?= $data['qr_code_path'] ?>"
                                style="width: 90px; height: 90px; object-fit: contain; border: 1px solid #f1f5f9; padding: 2px; border-radius: 4px;"
                                onerror="this.style.display='none'">
                        </div>
                    <?php endif; ?>

                    <div style="font-size: 11px; line-height: 1.4;">
                        <div style="font-weight: bold; color: #0f172a; font-size: 12px; margin-bottom: 2px;">
                            <?php echo $data['bank_name']; ?>
                        </div>
                        <div style="font-weight: bold; color: #0f172a; font-size: 12px; margin-bottom: 2px;">
                            ชื่อบัญชี: <span style="color: #0f172a;"><?php echo $data['bank_account_name']; ?></span>
                        </div>
                        <div
                            style="color: #0f172a; font-weight: bold; font-size: 12px; letter-spacing: 0.3px; margin-top: 2px;">
                            เลขบัญชี: <?php echo $data['bank_account_number']; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div style="width: 40%;">
            <div style="background: #f1f5f9; padding: 15px; border-radius: 8px;">
                <table width="100%" style="font-size: 13px;">
                    <tr>
                        <td style="padding-bottom: 5px;">รวมเป็นเงิน</td>
                        <td align="right" style="padding-bottom: 5px;">
                            <?= number_format($total_subtotal, 2) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 5px;">
                            ภาษีมูลค่าเพิ่ม
                        </td>
                        <td align="right" style="padding-bottom: 5px;">
                            <?= number_format($total_vat, 2) ?>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding-bottom: 5px;">
                            หัก ณ ที่จ่าย
                        </td>
                        <td align="right" style="padding-bottom: 5px;">
                            <?= number_format($total_wht, 2) ?>
                        </td>
                    </tr>
                    <tr style="font-size: 16px; font-weight: 900; color: var(--primary-color);">
                        <td style="padding-top: 10px; border-top: 1px solid #cbd5e1;">ยอดสุทธิ</td>
                        <td align="right" style="padding-top: 10px; border-top: 1px solid #cbd5e1;">
                            <?= number_format($total_grand, 2) ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" align="right"
                            style="padding-top: 8px; font-size: 11px; color: #64748b; font-style: italic;">
                            ( <?= BahtText($total_grand) ?> )
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; text-align: center; font-size: 12px; margin-top: 30px;">

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
            <p style="margin: 4px 0 0; font-size: 10px; color: #64748b;">
                ( <?= $_SESSION['user_name'] ?? '................................' ?> )
            </p>
            <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">วันที่
                <?= date('d/m/Y', strtotime($data['created_at'])) ?>
            </p>
        </div>

        <div style="width: 30%;">
            <div class="sig-box"
                style="height: 60px; display: flex; align-items: center; justify-content: center; border-bottom: 1px dotted #cbd5e1; margin-bottom: 8px;">
            </div>
            <p style="margin: 0; font-weight: bold;">ผู้มีอำนาจลงนามอนุมัติ</p>
            <p style="margin: 4px 0 0; font-size: 10px; color: #64748b;">
                (.............................)
            </p>
            <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">
                วันที่ ......../......../........
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    // 1. ฟังก์ชันเปิดหน้าลายเซ็น (แก้ไขให้ทำงานได้จริง)
    function toggleSignature() {
        // ใช้ path ที่ถูกต้องตามโครงสร้างไฟล์ของคุณ
        window.location.href = 'create_signature.php';
    }

    // 2. ส่งออกเป็น PDF
    function exportPDF() {
        const element = document.getElementById('quotation-content');
        element.style.overflow = 'hidden';

        const opt = {
            margin: 0,
            // เปลี่ยนจาก $data['doc_no'] เป็นชื่อไฟล์กลางๆ หรือวันที่แทน เพราะเราดึงหลาย Invoice
            filename: 'Billing-Summary-<?= date('Ymd-His') ?>.pdf',
            image: { type: 'jpeg', quality: 1.0 },
            html2canvas: {
                scale: 3,
                useCORS: true,
                letterRendering: true,
                logging: false,
                scrollY: 0
            },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).toPdf().get('pdf').then(function (pdf) {
            const totalPages = pdf.internal.getNumberOfPages();
            if (totalPages > 1) {
                for (let i = totalPages; i > 1; i--) {
                    pdf.deletePage(i);
                }
            }
        }).save().then(() => {
            element.style.overflow = '';
        });
    }

    // 3. ส่งออกเป็น Word
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
        fileDownload.download = 'Billing-Summary-<?= date('Ymd') ?>.doc';
        fileDownload.click();
        document.body.removeChild(fileDownload);
    }
</script>
<?php include('footer.php'); ?>