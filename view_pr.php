<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
if (empty($id)) {
    die("ไม่พบรหัสเอกสาร");
}

// SQL สำหรับดึงข้อมูล PR (ดึงข้อมูล Supplier และข้อมูลบริษัทเรามาโชว์)
$sql = "SELECT p.*, 
               s.company_name as supplier_name, s.address as sup_address, s.tax_id as sup_tax, s.phone as sup_phone,
               own.company_name as my_company, own.tax_id as my_tax, own.phone as my_phone, 
               own.email as my_email, own.address as my_address, own.logo_path
        FROM pr p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN suppliers own ON p.supplier_id = own.id -- หรือเปลี่ยนเป็นตารางตั้งค่าบริษัทคุณเอง
        WHERE p.id = '$id' LIMIT 1";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

if (!$data) { die("ไม่พบข้อมูลเอกสารในระบบ"); }

// ดึงรายการสินค้าของ PR
$sql_items = "SELECT * FROM pr_items WHERE pr_id = '$id' ORDER BY id ASC";
$res_items = mysqli_query($conn, $sql_items);
$num_rows = mysqli_num_rows($res_items);

$logo_path = !empty($data['logo_path']) ? 'uploads/' . $data['logo_path'] : '';

// คำนวณความแน่นของตารางตามจำนวนรายการ
if ($num_rows <= 5) {
    $dynamic_padding = '20px 15px'; $dynamic_font_size = '14px';
} elseif ($num_rows <= 10) {
    $dynamic_padding = '12px 15px'; $dynamic_font_size = '13px';
} else {
    $dynamic_padding = '5px 15px'; $dynamic_font_size = '12px';
}
?>

<style>
    :root {
        --row-padding: <?= $dynamic_padding ?>;
        --item-font-size: <?= $dynamic_font_size ?>;
        --primary-color: #2563eb;
        --border-color: #e2e8f0;
    }
    .table-items tbody td {
        padding: var(--row-padding) !important;
        font-size: var(--item-font-size);
    }
    .page-container { background: white; padding: 40px; min-height: 297mm; margin: 0 auto; width: 210mm; }
    .sig-box { border-bottom: 1px solid #94a3b8; width: 150px; height: 50px; margin: 0 auto 10px; transition: opacity 0.3s; }
    @media print { .no-print { display: none !important; } .page-container { padding: 0; width: 100%; } }
</style>

<?php
// ฟังก์ชันอ่านเลขเงินไทย
function BahtText($amount) {
    $amount_number = number_format($amount, 2, '.', '');
    $pt = strpos($amount_number, '.');
    $number = ($pt === false) ? $amount_number : substr($amount_number, 0, $pt);
    $fraction = ($pt === false) ? "00" : substr($amount_number, $pt + 1);
    $ret = "";
    $baht = ReadNumber($number);
    if ($baht != "") $ret .= $baht . "บาท";
    if ($fraction == "00") { $ret .= "ถ้วน"; } else { $ret .= ReadNumber($fraction) . "สตางค์"; }
    return $ret;
}
function ReadNumber($number) {
    $position_call = array("แสน", "หมื่น", "พัน", "ร้อย", "สิบ", "");
    $number_call = array("", "หนึ่ง", "สอง", "สาม", "สี่", "ห้า", "หก", "เจ็ด", "แปด", "เก้า");
    $number = intval($number);
    if ($number == 0) return "";
    $ret = "";
    if ($number > 1000000) { $ret .= ReadNumber(intval($number / 1000000)) . "ล้าน"; $number = $number % 1000000; }
    $t = sprintf("%06d", $number);
    $adjective = false;
    for ($i = 0; $i < 6; $i++) {
        $n = substr($t, $i, 1);
        if ($n != "0") {
            if ($i == 4 && $n == "1") $ret .= "สิบ";
            elseif ($i == 4 && $n == "2") $ret .= "ยี่สิบ";
            elseif ($i == 5 && $n == "1" && $adjective) $ret .= "เอ็ด";
            else $ret .= $number_call[$n] . $position_call[$i];
            $adjective = true;
        } else { $adjective = false; }
    }
    return $ret;
}
?>

<link rel="stylesheet" href="assets/css/style_quotation.css">
<div class="floating-toolbar no-print">
    <div class="tool-group">
        <button onclick="history.back()" class="btn-tool btn-back" title="ย้อนกลับ"><i class="fas fa-chevron-left"></i></button>
    </div>
    <div class="tool-group main-tools">
        <button onclick="window.print()" class="btn-tool btn-print"><i class="fas fa-print"></i> Print</button>
        <button onclick="toggleSignature()" class="btn-tool btn-sig" id="sigBtn"><i class="fas fa-pen-nib"></i> Sign</button>
    </div>
    <div class="tool-group export-tools">
        <button onclick="exportPDF()" class="btn-tool btn-pdf"><i class="fas fa-file-pdf"></i></button>
    </div>
</div>

<div id="pr-content" class="page-container">
    <div style="display: flex; justify-content: space-between; margin-bottom: 20px; border-bottom: 2px solid var(--primary-color); padding-bottom: 15px;">
        <div style="display: flex; gap: 15px;">
            <?php if ($logo_path): ?>
                <img src="<?= $logo_path ?>" style="width: 80px; height: 80px; object-fit: contain;">
            <?php else: ?>
                <div style="width: 80px; height: 80px; background: #1e293b; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: bold;">PR</div>
            <?php endif; ?>
            <div style="font-size: 11px; line-height: 1.4;">
                <h1 style="margin: 0 0 4px; font-size: 20px; color: #0f172a;"><?= $data['my_company'] ?></h1>
                <p style="margin: 0; color: #64748b;"><?= $data['my_address'] ?></p>
                <p style="margin: 2px 0 0; color: #64748b;"><b>Tax ID:</b> <?= $data['my_tax'] ?> | <b>Tel:</b> <?= $data['my_phone'] ?></p>
            </div>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 28px; color: var(--primary-color); font-weight: 900;">ใบขอซื้อ</h2>
            <p style="margin: 0; font-size: 10px; letter-spacing: 2px; color: #94a3b8; text-transform: uppercase;">Purchase Request</p>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 20px; gap: 15px;">
        <div style="flex: 1; border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; background: #f8fafc;">
            <p style="margin: 0 0 5px; font-size: 10px; font-weight: bold; color: var(--primary-color);">SUPPLIER / ผู้ขาย</p>
            <h3 style="margin: 0; font-size: 16px; color: #0f172a;"><?= $data['supplier_name'] ?></h3>
            <div style="margin: 5px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
                <?= nl2br($data['sup_address']) ?><br>
                <b>Tax ID:</b> <?= $data['sup_tax'] ?> | <b>Tel:</b> <?= $data['sup_phone'] ?>
            </div>
        </div>
        <div style="width: 280px; font-size: 13px;">
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dotted #cbd5e1;">
                <span style="color: #64748b;">เลขที่เอกสาร / No.</span>
                <span style="font-weight: bold;"><?= $data['id'] ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dotted #cbd5e1;">
                <span style="color: #64748b;">วันที่ / Date</span>
                <span><?= date('d/m/Y', strtotime($data['created_at'])) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dotted #cbd5e1;">
                <span style="color: #64748b;">กำหนดส่ง / Due Date</span>
                <span style="color: #e11d48; font-weight: bold;"><?= date('d/m/Y', strtotime($data['due_date'])) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                <span style="color: #64748b;">อ้างอิง / Ref No.</span>
                <span><?= $data['reference_no'] ?: '-' ?></span>
            </div>
        </div>
    </div>

    <div class="item-section">
        <table class="table-items" width="100%" style="border-collapse: collapse;">
            <thead>
                <tr style="background: var(--primary-color); color: white;">
                    <th width="5%" style="padding: 10px;">#</th>
                    <th align="left">รายการ / Description</th>
                    <th width="10%" align="center">จำนวน</th>
                    <th width="10%" align="center">หน่วย</th>
                    <th width="15%" align="right">ราคา/หน่วย</th>
                    <th width="15%" align="right">ยอดรวม</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1; $subtotal = 0;
                while ($item = mysqli_fetch_assoc($res_items)):
                    $subtotal += $item['item_total'];
                ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td align="center"><?= $count++ ?></td>
                        <td><?= nl2br(htmlspecialchars($item['item_desc'])) ?></td>
                        <td align="center"><?= number_format($item['item_qty'], 2) ?></td>
                        <td align="center"><?= $item['item_unit'] ?></td>
                        <td align="right"><?= number_format($item['item_price'], 2) ?></td>
                        <td align="right" style="font-weight: 600;"><?= number_format($item['item_total'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">
        <div style="display: flex; justify-content: space-between;">
            <div style="width: 55%;">
                <p style="font-size: 11px; font-weight: bold; text-decoration: underline;">หมายเหตุ / Notes:</p>
                <p style="font-size: 11px; color: #64748b;"><?= nl2br($data['notes']) ?: 'ไม่มีหมายเหตุ' ?></p>
                <div style="margin-top: 20px; font-size: 11px;">
                    <p><b>ผู้ขอซื้อ:</b> <?= $data['requested_by'] ?></p>
                    <p><b>เบอร์ติดต่อ:</b> <?= $data['contact_tel'] ?></p>
                </div>
            </div>
            <div style="width: 35%;">
                <table width="100%" style="font-size: 14px;">
                    <tr>
                        <td style="padding: 5px 0;">รวมเป็นเงิน (Sub Total)</td>
                        <td align="right"><?= number_format($subtotal, 2) ?></td>
                    </tr>
                    <tr style="font-weight: bold; color: var(--primary-color); font-size: 18px;">
                        <td style="padding: 10px 0; border-top: 2px solid #2563eb;">ยอดสุทธิ (Total)</td>
                        <td align="right" style="border-top: 2px solid #2563eb;"><?= number_format($subtotal, 2) ?></td>
                    </tr>
                </table>
                <p style="text-align: right; font-size: 11px; font-style: italic; color: #64748b; margin-top: 5px;">
                    ( <?= BahtText($subtotal) ?> )
                </p>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; margin-top: 50px; text-align: center;">
            <div style="width: 30%;">
                <div class="sig-box"></div>
                <p style="font-size: 12px; font-weight: bold;">ผู้ขอซื้อ (Requested By)</p>
                <p style="font-size: 10px; color: #94a3b8;">วันที่ ....../....../......</p>
            </div>
            <div style="width: 30%;">
                <div class="sig-box"></div>
                <p style="font-size: 12px; font-weight: bold;">ผู้อนุมัติ (Approved By)</p>
                <p style="font-size: 10px; color: #94a3b8;">วันที่ ....../....../......</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function toggleSignature() {
        const sigBoxes = document.querySelectorAll('.sig-box');
        sigBoxes.forEach(box => {
            box.style.opacity = (box.style.opacity === '0') ? '1' : '0';
        });
        document.getElementById('sigBtn').classList.toggle('btn-primary');
    }

    function exportPDF() {
        const element = document.getElementById('pr-content');
        const opt = {
            margin: 5,
            filename: 'PR_<?= $data['id'] ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>

<?php include('footer.php'); ?>