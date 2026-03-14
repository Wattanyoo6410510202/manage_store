<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';

if (empty($id)) {
    die("ไม่พบเลขที่เอกสาร");
}

// 1. ดึงข้อมูล Invoice และข้อมูลลูกค้า
$sql = "SELECT i.*, c.customer_name, c.address as cust_address, c.tax_id, c.contact_person, c.phone 
        FROM invoices i 
        LEFT JOIN customers c ON i.customer_id = c.id 
        WHERE i.id = '$id' LIMIT 1";
$res = mysqli_query($conn, $sql);
$item = mysqli_fetch_assoc($res);

if (!$item) {
    die("ไม่พบข้อมูลในระบบ");
}

// 2. ดึงข้อมูลบริษัทเรา (Suppliers)
$sql_sup = "SELECT * FROM suppliers LIMIT 1";
$res_sup = mysqli_query($conn, $sql_sup);
$sup = mysqli_fetch_assoc($res_sup);

$logo_path = !empty($sup['logo_path']) ? 'uploads/' . $sup['logo_path'] : '';

// 3. เช็คส่วนลด (ใช้ชื่อฟิลด์ตามมาตรฐาน invoice_items)
$sum_discount_res = mysqli_query($conn, "SELECT SUM(discount_amount) as total_discount FROM invoice_items WHERE invoice_id = '$id'");
$row_discount = mysqli_fetch_assoc($sum_discount_res);
$total_discount = $row_discount['total_discount'] ?? 0;

// --- ลายเซ็น (Logic เดิมของพี่เป๊ะ) ---
$prepared_by_id = $data['created_by'];
$sig_sql = "SELECT path FROM signatures WHERE users_id = '$prepared_by_id' LIMIT 1";
$sig_data = mysqli_fetch_assoc(mysqli_query($conn, $sig_sql));
$real_path = !empty($sig_data['path']) ? 'uploads/signatures/' . $sig_data['path'] : '';
$prepared_sig = (!empty($real_path) && file_exists($real_path)) ? $real_path : '';

$approved_sig = '';
if ($data['status'] === 'approved' && !empty($data['approved_by'])) {
    $approver_id = $data['approved_by'];
    $sig_app_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT path FROM signatures WHERE users_id = '$approver_id' LIMIT 1"));
    $real_app_path = !empty($sig_app_data['path']) ? 'uploads/signatures/' . $sig_app_data['path'] : '';
    if (!empty($real_app_path) && file_exists($real_app_path)) {
        $approved_sig = $real_app_path;
    }
}
$num_rows = mysqli_num_rows($res_items);
// --- Logic ปรับ Font/Padding ของพี่ ---
if ($num_rows <= 5) {
    $dynamic_padding = '5px 8px';
    $dynamic_font_size = '14px';
} elseif ($num_rows <= 10) {
    $dynamic_padding = '3px 10px';
    $dynamic_font_size = '13px';
} else {
    $dynamic_padding = '2px 12px';
    $dynamic_font_size = '12px';
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
        <button onclick="history.back()" class="btn-tool btn-back"><i class="fas fa-chevron-left"></i></button>
    </div>
    <div class="tool-group main-tools">
        <button onclick="window.print()" class="btn-tool btn-print"><i class="fas fa-print"></i>
            <span>Print</span></button>
        <button onclick="toggleSignature()" class="btn-tool btn-sig"><i class="fas fa-pen-nib"></i>
            <span>Sign</span></button>
    </div>
    <div class="tool-group export-tools">
        <button onclick="exportPDF()" class="btn-tool btn-pdf"><i class="fas fa-file-pdf"></i></button>
        <button onclick="exportWord()" class="btn-tool btn-word"><i class="fas fa-file-word"></i></button>
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
            <h2 style="margin: 0; font-size: 28px; color: var(--primary-color); font-weight: 900;">
                <?= ($data['doc_type'] == 'quotation') ? 'ใบเสนอราคา' : 'ใบแจ้งหนี้' ?>
            </h2>
            <p style="margin: 0; font-size: 10px; letter-spacing: 3px; color: #94a3b8; text-transform: uppercase;">
                <?= $data['doc_type'] ?>
            </p>
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
                <span style="font-weight: bold; color: #0f172a;"><?= htmlspecialchars($data['invoice_no']) ?></span>
            </div>
            <div
                style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">วันที่ / Date</span>
                <span
                    style="color: #0f172a; font-weight: bold;"><?= date('d/m/Y', strtotime($data['invoice_date'])) ?></span>
            </div>
            <div
                style="display: flex; justify-content: space-between; padding: 6px 0;  border-bottom: 1px solid #e2e8f0;">
                <span style="color: #64748b;">วันครบกำหนด</span>
                <span
                    style="color: #0f172a; font-weight: bold;"><?= $data['due_date'] ? date('d/m/Y', strtotime($data['due_date'])) : 'ไม่มีกำหนด' ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                <span style="color: #64748b;">สถานะ</span>
                <span>
                    <?php
                    // จับคู่สถานะ Enum กับ คำไทยและสี
                    $status_map = [
                        'pending' => ['label' => 'รอดำเนินการ'], // สีส้ม
                        'approved' => ['label' => 'อนุมัติแล้ว'], // สีเขียว
                        'paid' => ['label' => 'ชำระเงินแล้ว'], // สีน้ำเงิน
                        'canceled' => ['label' => 'ยกเลิก']  // สีแดง
                    ];

                    $current_status = $data['status'];
                    $label = $status_map[$current_status]['label'] ?? $current_status;
                    $color = $status_map[$current_status]['color'] ?? '#64748b';

                    echo "<span style='color: $0f172a; font-weight: bold;'>$label</span>";
                    ?>
                </span>
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
                <?php $count = 1;
                while ($item = mysqli_fetch_assoc($res_items)): ?>
                    <tr>
                        <td align="center"><?= $count++ ?></td>
                        <td style="font-weight: 400; vertical-align: top; line-height: 1.4; color: #334155; 
           max-width: 300px; word-break: break-word; overflow-wrap: break-word;">
                            <?= nl2br(htmlspecialchars($item['item_name'])) ?>
                        </td>
                        <td align="center "><?= number_format($item['qty'], 0) ?></td>
                        <td align="center"><?= htmlspecialchars($item['unit_name']) ?></td>
                        <td align="right"><?= number_format($item['price_per_unit'], 2) ?></td>
                        <?php if ($total_discount > 0): ?>
                            <td align="right">-<?= number_format($item['discount_amount'], 2) ?></td>
                        <?php endif; ?>
                        <td align="right" style="font-weight: 600;"><?= number_format($item['total_price'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="doc-footer">
        <div style="display: flex; justify-content: space-between; margin-top: 20px;">

            <div style="width: 55%;">
                <?php if (!empty($data['bank_name'])): ?>
                    <p style="font-size: 11px; color: #64748b; font-weight: bold; margin-bottom: 5px;">
                        กรุณาชำระเงินภายในวันที่
                        <span
                            style="color: #0f172a;"><?= $data['due_date'] ? date('d/m/Y', strtotime($data['due_date'])) : '-' ?></span>
                        ตามบัญชีดังนี้:
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
                            <div style="color: #475569;">
                                ชื่อบัญชี: <span style="color: #0f172a;"><?php echo $data['bank_account_name']; ?></span>
                            </div>
                            <div
                                style="color: #0f172a; font-weight: bold; font-size: 12px; letter-spacing: 0.3px; margin-top: 2px;">
                                เลขบัญชี: <?php echo $data['bank_account_number']; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 5px; ">
                    <p
                        style="font-size: 10px; font-weight: bold; color: #0f172a; text-transform: uppercase; margin-bottom: 4px;">
                        หมายเหตุ:</p>
                    <div style="font-size: 11px; color: #0f172a; line-height: 1.4;">
                        <?= !empty($data['remark']) ? nl2br(htmlspecialchars($data['remark'])) : '<span style="color: #cbd5e1;">-</span>' ?>
                    </div>
                </div>
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
                            <td style="padding-bottom: 5px;">
                                ภาษีมูลค่าเพิ่ม <?= number_format($data['vat_percent'], 0) ?>%
                            </td>
                            <td align="right" style="padding-bottom: 5px;">
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
                    <?= $data['creator_real_name'] ?? $data['created_by'] ?> )
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
    <div
        style="margin-top: 40px; text-align: center; font-size: 9px; color: #cbd5e1; text-transform: uppercase; letter-spacing: 1px;">
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    // Script PDF / Word ของพี่ (คงเดิม)
    function exportPDF() { /* ... โค้ดเดิมของพี่ ... */ }
    function exportWord() { /* ... โค้ดเดิมของพี่ ... */ }
    function toggleSignature() { window.location.href = 'create_signature.php'; }
</script>

<?php include('footer.php'); ?>