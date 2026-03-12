<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
if (empty($id)) {
    die("ไม่พบรหัสเอกสาร");
}

$sql = "SELECT p.*, 
               s.company_name as supplier_name, s.address as sup_address, s.tax_id as sup_tax, s.phone as sup_phone,
               IF(p.is_internal = 1, u_creator.name, c.customer_name) as customer_name,
               IF(p.is_internal = 1, 'ภายในองค์กร', c.address) as cust_address,
               IF(p.is_internal = 1, '-', c.tax_id) as cust_tax,
               -- เพิ่ม 2 บรรทัดนี้ครับ --
               IF(p.is_internal = 1, '-', c.phone) as cust_phone,
               IF(p.is_internal = 1, '-', c.email) as cust_email,
               
               -- ข้อมูลผู้จัดทำ (Created By)
               u_creator.name as creator_name,
               sig_creator.path as creator_signature,
               
               -- ข้อมูลผู้อนุมัติ (Approved By)
               u_app.name as approver_name,
               sig_app.path as approver_signature,

               own.company_name as my_company, own.tax_id as my_tax, own.phone as my_phone, 
               own.email as my_email, own.address as my_address, own.logo_path
        FROM pr p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN customers c ON p.customer_id = c.id 
        LEFT JOIN suppliers own ON p.supplier_id = own.id 
        -- Join หาคนทำ (ใช้ users_id ตามตาราง signatures)
        LEFT JOIN users u_creator ON p.created_by = u_creator.id
        LEFT JOIN signatures sig_creator ON u_creator.id = sig_creator.users_id
        -- Join หาคนอนุมัติ (ใช้ users_id ตามตาราง signatures)
        LEFT JOIN users u_app ON p.approved_by = u_app.id
        LEFT JOIN signatures sig_app ON u_app.id = sig_app.users_id
        WHERE p.id = '$id' LIMIT 1";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

// ดึงรายการสินค้าของ PR
$sql_items = "SELECT * FROM pr_items WHERE pr_id = '$id' ORDER BY id ASC";
$res_items = mysqli_query($conn, $sql_items);
$num_rows = mysqli_num_rows($res_items);

$logo_path = !empty($data['logo_path']) ? 'uploads/' . $data['logo_path'] : '';


$prepared_by_id = $data['created_by'];
$sig_sql = "SELECT path FROM signatures WHERE users_id = '$prepared_by_id' LIMIT 1";
$sig_res = mysqli_query($conn, $sig_sql);
$sig_data = mysqli_fetch_assoc($sig_res);

$sum_discount_res = mysqli_query($conn, "SELECT SUM(item_discount) as total_discount FROM pr_items WHERE pr_id = '" . $data['id'] . "'");
$row_discount = mysqli_fetch_assoc($sum_discount_res);
$total_discount = $row_discount['total_discount'] ?? 0;

$num_rows = mysqli_num_rows($res_items);

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

<?php
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
            <h2 style="margin: 0; font-size: 28px; color: var(--primary-color); font-weight: 900;">ใบขอซื้อ</h2>
            <p style="margin: 0; font-size: 10px; letter-spacing: 3px; color: #94a3b8; text-transform: uppercase;">
                PURCHASE REQUISITION
            </p>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 15px; gap: 15px;">
        <div
            style="flex: 1; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; background: #f8fafc; ">
            <h3 style="margin: 0; font-size: 14px; color: #0f172a;"><?= $data['customer_name'] ?></h3>

            <div style="margin: 5px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
                <?php if (!empty($data['contact_person']) || !empty($data['cust_phone']) || !empty($data['cust_email']) || !empty($data['requested_by'])): ?>
                    <div style="margin-top: 10px; font-size: 11px; line-height: 1.6;">

                        <?php if (!empty($data['requested_by'])): ?>
                            <div style="margin-bottom: 2px;">
                                <b style="color: #64748b; min-width: 70px; display: inline-block;">แผนก / ฝ่าย:</b>
                                <span
                                    style="color: #0f172a; font-weight: 500; background: #eff6ff; padding: 0 6px; border-radius: 3px;">
                                    <?= htmlspecialchars($data['requested_by']) ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($data['cust_phone'])): ?>
                            <div style="margin-bottom: 2px;">
                                <b style="color: #64748b; min-width: 70px; display: inline-block;">เบอร์โทร:</b>
                                <span style="color: #0f172a;"><?= htmlspecialchars($data['cust_phone']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($data['cust_email'])): ?>
                            <div>
                                <b style="color: #64748b; min-width: 70px; display: inline-block;">อีเมล:</b>
                                <span
                                    style="color: #0f172a; text-decoration: none;"><?= htmlspecialchars($data['cust_email']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($data['cust_address'])): ?>
                            <div>
                                <b style="color: #64748b; min-width: 70px; display: inline-block;">ที่อยู่:</b>
                                <span
                                    style="color: #0f172a; text-decoration: none;"><?= htmlspecialchars($data['cust_address']) ?></span>
                            </div>
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
                <span style="color: #64748b;">วันที่ต้องการสินค้า</span>
                <span style="color: #0f172a; font-weight: bold;">
                    <?= (!empty($data['due_date']) && $data['due_date'] !== '0000-00-00')
                        ? date('d/m/Y', strtotime($data['due_date']))
                        : '-' ?>
                </span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                <span style="color: #64748b;">เลขที่อ้างอิง</span>
                <span style="color: #0f172a; font-weight: bold;"><?= $data['reference_no'] ?></span>
            </div>
        </div>
    </div>

    <div class="item-section">
        <table class="table-items">
            <thead>
                <tr>
                    <th width="3%">#</th>
                    <th align="left">รายการ / Description</th>
                    <th width="5%" align="center">จำนวน</th>
                    <th width="8%" align="right">หน่วย</th>
                    <th width="12%" align="right">ราคา</th>
                    <?php if ($total_discount > 0): ?>
                        <th width="10%" align="right">ส่วนลด</th>
                    <?php endif; ?>
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
                        <td style="font-weight: 400; vertical-align: top; line-height: 1.4; color: #334155; 
           max-width: 300px; word-break: break-word; overflow-wrap: break-word;">
                            <?= nl2br(htmlspecialchars($item['item_desc'])) ?>
                        </td>
                        <td align="center"><?= number_format($item['item_qty'], 0) ?></td>
                        <td align="right"><?= htmlspecialchars($item['item_unit']) ?></td>
                        <td align="right"><?= number_format($item['item_price'], 2) ?></td>
                        <?php if ($total_discount > 0): ?>
                            <td align="right"><?= number_format($item['item_discount'], 2) ?></td>
                        <?php endif; ?>
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
                            <td style="padding-bottom: 5px; ">
                                ภาษีมูลค่าเพิ่ม <?= (float) ($data['vat_percent'] ?? 7) ?>%
                            </td>
                            <td align="right" style="padding-bottom: 5px; font-weight: 600;">
                                <?= number_format($data['vat'], 2) ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-bottom: 5px;">
                                หัก ณ ที่จ่าย <?= number_format($data['wht_percent'], 0) ?>%
                            </td>
                            <td align="right" style="padding-bottom: 5px;">
                                <?= number_format($data['wht_amount'], 2) ?>
                            </td>
                        </tr>
                        <tr style="font-size: 16px; font-weight: 900; color: var(--primary-color);">
                            <td style="padding-top: 10px; border-top: 1px solid #cbd5e1;">ยอดสุทธิ</td>
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
        <div class="doc-footer" style="display: flex; justify-content: space-between; gap: 20px; margin-top: 30px;">

            <div style="width: 33%; text-align: center;">
                <div class="sig-box"
                    style="height: 60px; display: flex; align-items: center; justify-content: center; border-bottom: 1px dotted #cbd5e1; margin-bottom: 8px;">
                    <?php if (!empty($data['creator_signature'])): ?>
                        <img src="uploads/signatures/<?= $data['creator_signature'] ?>"
                            style="max-height: 50px; object-fit: contain;">
                    <?php endif; ?>
                </div>
                <p style="margin: 0; font-weight: bold; font-size: 12px;">ผู้จัดทำ </p>
                <p style="margin: 4px 0 0; font-size: 11px; color: #64748b;">(
                    <?= $data['creator_name'] ?: '..................................' ?> )
                </p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">วันที่
                    <?= date('d/m/Y', strtotime($data['created_at'])) ?>
                </p>
            </div>

            <div style="width: 33%; text-align: center;">
                <div class="sig-box"
                    style="height: 60px; display: flex; align-items: center; justify-content: center; border-bottom: 1px dotted #cbd5e1; margin-bottom: 8px;">
                    <?php if (!empty($data['approver_signature'])): ?>
                        <img src="uploads/signatures/<?= $data['approver_signature'] ?>"
                            style="max-height: 50px; object-fit: contain;">
                    <?php endif; ?>
                </div>
                <p style="margin: 0; font-weight: bold; font-size: 12px;">แผนกบัญชี / จัดซื้อ</p>
                <p style="margin: 4px 0 0; font-size: 11px; color: #64748b;">(
                    <?= $data['approver_name'] ?: '..................................' ?> )
                </p>
                <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">
                    วันที่
                    <?= ($data['approved_at']) ? date('d/m/Y', strtotime($data['approved_at'])) : '......../......../........' ?>
                </p>
            </div>

            <div style="width: 33%; text-align: center;">
                <div class="sig-box" style="height: 60px; border-bottom: 1px dotted #cbd5e1; margin-bottom: 8px;"></div>
                <p style="margin: 0; font-weight: bold; font-size: 12px;">ผู้อนุมัติ </p>
                <p style="margin: 4px 0 0; font-size: 11px; color: #64748b;">(..................................)</p>
                <p style="margin: 4px 0 0; font-size: 11px; color: #64748b;">วันที่ ......../......../........</p>
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