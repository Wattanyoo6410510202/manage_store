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
               IF(p.is_internal = 1, '-', c.phone) as cust_phone,
               IF(p.is_internal = 1, '-', c.email) as cust_email,
               u_creator.name as creator_name,
               sig_creator.path as creator_signature,
               
               u_app0.name as app0_name, sig_app0.path as app0_sig,
               u_app1.name as app1_name, sig_app1.path as app1_sig,
               u_app2.name as app2_name, sig_app2.path as app2_sig,
               u_app3.name as app3_name, sig_app3.path as app3_sig,
               u_app4.name as app4_name, sig_app4.path as app4_sig,

               own.company_name as my_company, own.tax_id as my_tax, own.phone as my_phone, 
               own.email as my_email, own.address as my_address, own.logo_path,
                ec.name as expense_cat_name,
                bt.name as budget_type_name,
                obj.name as objective_name,
                st.store_name as store_name,
                pm.name as payment_method_name
         FROM pr p
         LEFT JOIN payment_methods pm ON p.payment_method = pm.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN stores st ON p.store_id = st.id
        LEFT JOIN customers c ON p.customer_id = c.id 
        LEFT JOIN suppliers own ON p.supplier_id = own.id 
        LEFT JOIN users u_creator ON p.created_by = u_creator.id
        LEFT JOIN signatures sig_creator ON u_creator.id = sig_creator.users_id
        
        LEFT JOIN users u_app0 ON p.approved_by_0 = u_app0.id
        LEFT JOIN signatures sig_app0 ON u_app0.id = sig_app0.users_id

        LEFT JOIN users u_app1 ON p.approved_by = u_app1.id
        LEFT JOIN signatures sig_app1 ON u_app1.id = sig_app1.users_id
        
        LEFT JOIN users u_app2 ON p.approved_by_1 = u_app2.id
        LEFT JOIN signatures sig_app2 ON u_app2.id = sig_app2.users_id
        
        LEFT JOIN users u_app3 ON p.approved_by_2 = u_app3.id
        LEFT JOIN signatures sig_app3 ON u_app3.id = sig_app3.users_id

        LEFT JOIN users u_app4 ON p.approved_by_3 = u_app4.id
        LEFT JOIN signatures sig_app4 ON u_app4.id = sig_app4.users_id

        LEFT JOIN expense_categories ec ON p.expense_cat_id = ec.id
        LEFT JOIN budget_types bt ON p.budget_type_id = bt.id
        LEFT JOIN pr_objectives obj ON p.objective_id = obj.id
        WHERE p.id = '$id' LIMIT 1";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

$sql_items = "SELECT * FROM pr_items WHERE pr_id = '$id' ORDER BY id ASC";
$res_items = mysqli_query($conn, $sql_items);
$num_rows = mysqli_num_rows($res_items);

$logo_path = !empty($data['logo_path']) ? 'uploads/' . $data['logo_path'] : '';

$sum_discount_res = mysqli_query($conn, "SELECT SUM(item_discount) as total_discount FROM pr_items WHERE pr_id = '" . $data['id'] . "'");
$row_discount = mysqli_fetch_assoc($sum_discount_res);
$total_discount = $row_discount['total_discount'] ?? 0;

$dynamic_padding = ($num_rows <= 5) ? '5px 8px' : (($num_rows <= 10) ? '3px 10px' : '2px 12px');
$dynamic_font_size = ($num_rows <= 5) ? '14px' : (($num_rows <= 10) ? '13px' : '12px');

$budget_info = null;
if ($data['budget_type_id']) {
    $bt_id = $data['budget_type_id'];
    $bt_sql = "SELECT bt.*, 
                (bt.budget_amount + COALESCE((SELECT SUM(a.amount) FROM budget_adjustments a WHERE a.budget_type_id = bt.id), 0)) as total_budget,
                (SELECT SUM(p.grand_total) FROM pr p WHERE p.budget_type_id = bt.id AND p.status = 'approved' AND p.deleted_at IS NULL AND p.id != '$id') as spent_before
                FROM budget_types bt 
                WHERE bt.id = $bt_id";
    $bt_res = mysqli_query($conn, $bt_sql);
    $budget_info = mysqli_fetch_assoc($bt_res);
}

// จัดการรายการที่จะนำมาแสดงในส่วนท้ายเอกสาร
$display_list = [];
$display_list[] = ['label' => 'ผู้จัดทำ', 'name' => $data['creator_name'], 'sig' => $data['creator_signature'], 'date' => $data['created_at'], 'is_empty' => false];

$real_apps = [];
$apps = [
    ['id' => $data['approved_by_0'] ?? null, 'name' => $data['app0_name'] ?? '', 'sig' => $data['app0_sig'] ?? '', 'date' => $data['approved_at_0'] ?? '', 'role' => 'หัวหน้างาน'],
    ['id' => $data['approved_by'] ?? null, 'name' => $data['app1_name'] ?? '', 'sig' => $data['app1_sig'] ?? '', 'date' => $data['approved_at'] ?? '', 'role' => 'จัดซื้อ'],
    ['id' => $data['approved_by_1'] ?? null, 'name' => $data['app2_name'] ?? '', 'sig' => $data['app2_sig'] ?? '', 'date' => $data['approved_at_1'] ?? '', 'role' => 'บัญชี'],
    ['id' => $data['approved_by_2'] ?? null, 'name' => $data['app3_name'] ?? '', 'sig' => $data['app3_sig'] ?? '', 'date' => $data['approved_at_2'] ?? '', 'role' => 'Mgr'],
    ['id' => $data['approved_by_3'] ?? null, 'name' => $data['app4_name'] ?? '', 'sig' => $data['app4_sig'] ?? '', 'date' => $data['approved_at_3'] ?? '', 'role' => 'Mgr2']
];


foreach($apps as $app) {
    if(!empty($app['id'])) {
        $real_apps[] = ['label' => 'ผู้อนุมัติ (' . $app['role'] . ')', 'name' => $app['name'], 'sig' => $app['sig'], 'date' => $app['date'], 'is_empty' => false];
    }
}
foreach($real_apps as $ra) { $display_list[] = $ra; }

if(count($real_apps) == 0) {
    $display_list[] = ['label' => 'แผนกบัญชี / จัดซื้อ', 'name' => '', 'sig' => '', 'date' => '', 'is_empty' => true];
    $display_list[] = ['label' => 'ผู้อนุมัติ', 'name' => '', 'sig' => '', 'date' => '', 'is_empty' => true];
} elseif(count($real_apps) == 1) {
    $display_list[] = ['label' => 'ผู้อนุมัติ', 'name' => '', 'sig' => '', 'date' => '', 'is_empty' => true];
}

$count_total = count($display_list);
$col_width = ($count_total <= 3) ? '33.33%' : '50%';
?>

<style>
    :root {
        --row-padding: <?= $dynamic_padding ?>;
        --item-font-size: <?= $dynamic_font_size ?>;
        --primary-color: #2563eb;
        --border-color: #f1f5f9;
    }
    .table-items tbody td { padding: var(--row-padding) !important; font-size: var(--item-font-size); }
    .page-container { background: white; padding: 40px 40px 20px 40px; min-height: 297mm; margin: 0 auto; width: 210mm; }
    
    .sig-box { border-bottom: 1px dashed #cbd5e1; width: 160px; height: 60px; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; }
    .sig-box img { max-height: 50px; object-fit: contain; }
    
    .sig-wrapper { text-align: center; margin-bottom: 10px; padding: 0 10px; }
    .sig-title { font-weight: bold; font-size: 12px; margin: 0; color: #334155; }
    .sig-name { font-size: 11px; color: #64748b; margin: 4px 0 0; }
    .sig-date { font-size: 9px; color: #94a3b8; margin: 2px 0 0; }
    
    /* การตั้งค่าการปริ้น */
    @page { 
        size: A4; 
        margin: 8mm; /* ลดขอบกระดาษโดยรวมเหลือ 8 มิลลิเมตร */
    }
    @media print {
        body { margin: 0; padding: 0; background: none; }
        .no-print { display: none !important; }
        .page-container { 
            padding: 5mm !important; /* ลดขอบด้านในขณะสั่งพิมพ์ */
            width: 100% !important; 
            margin: 0 !important;
            min-height: 0 !important;
            box-shadow: none !important;
            border: none !important;
        }
        .floating-toolbar { display: none !important; }
    }
</style>

<?php
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
        <button onclick="window.print()" class="btn-tool btn-print" title="พิมพ์เอกสาร"><i class="fas fa-print"></i> <span>Print</span></button>
        <button onclick="toggleSignature()" class="btn-tool btn-sig" id="sigBtn" title="เปิด/ปิด ลายเซ็น"><i class="fas fa-pen-nib"></i> <span>Sign</span></button>
        <?php if ($data['status'] === 'approved' && $data['received_status'] !== 'received'): ?>
        <button onclick="receivePR(<?= $data['id'] ?>)" class="btn-tool bg-emerald-500 hover:bg-emerald-600 text-white border-emerald-400" title="ยืนยันรับของ">
            <i class="fas fa-check-double"></i> <span>รับของแล้ว</span>
        </button>
        <?php endif; ?>
    </div>
    <div class="tool-group export-tools">
        <button onclick="exportPDF()" class="btn-tool btn-pdf" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf"></i></button>
        <button onclick="exportWord()" class="btn-tool btn-word" title="ดาวน์โหลด Word"><i class="fas fa-file-word"></i></button>
    </div>
</div>

<div id="quotation-content" class="page-container">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
        <div style="display: flex; gap: 15px;">
            <?php if ($logo_path): ?>
                <img src="<?= $logo_path ?>" style="width: 65px; height: 65px; object-fit: contain;">
            <?php elseif (!empty($data['my_company']) && $data['my_company'] !== 'ไม่ระบุซัพพลายเออร์'): ?>
                <div style="width: 65px; height: 65px; background: #0f172a; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;">
                    <?= mb_substr($data['my_company'], 0, 1, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($data['my_company']) && $data['my_company'] !== 'ไม่ระบุ'): ?>
                <div style="font-size: 10px; line-height: 1.4;">
                    <h1 style="margin: 0 0 2px; font-size: 16px; color: #0f172a;"><?= $data['my_company'] ?></h1>
                    <?php if (!empty($data['my_address'])): ?><p style="margin: 0; color: #64748b;"><?= $data['my_address'] ?></p><?php endif; ?>
                    <p style="margin: 1px 0 0; color: #64748b;">
                        <?php if (!empty($data['my_tax'])): ?><b>Tax ID:</b> <?= $data['my_tax'] ?><?php endif; ?>
                        <?php if (!empty($data['my_tax']) && !empty($data['my_phone'])): ?> | <?php endif; ?>
                        <?php if (!empty($data['my_phone'])): ?><b>Tel:</b> <?= $data['my_phone'] ?><?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 900;">ใบขอซื้อ</h2>
            <p style="margin: 0; font-size: 9px; letter-spacing: 2px; color: #94a3b8; text-transform: uppercase;">PURCHASE REQUISITION</p>
        </div>
    </div>

    <!-- Info Section -->
    <div style="display: flex; justify-content: space-between; margin-bottom: 10px; gap: 10px;">
        <div style="flex: 1; border: 1px solid #f1f5f9; border-radius: 6px; padding: 8px; background: #f8fafc; ">
            <h3 style="margin: 0; font-size: 13px; color: #0f172a;"><?= $data['customer_name'] ?></h3>
            <div style="margin-top: 5px; font-size: 10px; line-height: 1.5;">                            <div><b style="color: #64748b; min-width: 90px; display: inline-block;">ร้านค้า:</b> <span style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars($data['store_name'] ?? '-') ?></span></div>
                                <div><b style="color: #64748b; min-width: 90px; display: inline-block;">ประเภทค่าใช้จ่าย:</b> <span style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars($data['expense_cat_name'] ?? '-') ?></span></div>
                                <div><b style="color: #64748b; min-width: 90px; display: inline-block;">ประเภทงบประมาณ:</b> <span style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars($data['budget_type_name'] ?? '-') ?></span></div>
                <div><b style="color: #64748b; min-width: 90px; display: inline-block;">วัตถุประสงค์:</b> <span style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars($data['objective_name'] ?? '-') ?></span></div>
                <div><b style="color: #64748b; min-width: 90px; display: inline-block;">ความคาดหวัง:</b> <span style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars($data['expectation'] ?? '-') ?></span></div>
            </div>
        </div>
        
        <!-- Budget Status Box -->
        <?php if ($budget_info): ?>
            <?php 
            $remaining = $budget_info['total_budget'] - $budget_info['spent_before'];
            $is_over = ($data['grand_total'] > $remaining);
            ?>
            <div style="width: 180px; border: 1px solid <?= $is_over ? '#fee2e2' : '#dcfce7' ?>; border-radius: 6px; padding: 8px; background: <?= $is_over ? '#fef2f2' : '#f0fdf4' ?>;">
                <h4 style="margin: 0 0 5px; font-size: 11px; color: <?= $is_over ? '#991b1b' : '#166534' ?>; border-bottom: 1px solid <?= $is_over ? '#fecaca' : '#bbf7d0' ?>; padding-bottom: 3px;">สถานะงบประมาณ</h4>
                <div style="font-size: 10px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b;">งบทั้งหมด:</span>
                        <span style="font-weight: bold;"><?= number_format($budget_info['total_budget'], 2) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 2px;">
                        <span style="color: #64748b;">คงเหลือ:</span>
                        <span style="font-weight: bold; color: <?= $is_over ? '#ef4444' : '#10b981' ?>;"><?= number_format($remaining, 2) ?></span>
                    </div>
                    <?php if ($is_over): ?>
                        <div style="margin-top: 5px; color: #ef4444; font-weight: bold; font-size: 9px; text-align: center; background: white; border-radius: 4px; padding: 2px; border: 1px solid #fecaca;">
                            <i class="fas fa-exclamation-triangle"></i> ยอดเกินงบประมาณ!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="width: 240px; font-size: 11px;">
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">เลขที่ / No.</span> <span style="font-weight: bold; color: #0f172a;"><?= htmlspecialchars($data['doc_no']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">วันที่ / Date</span> <span style="color: #0f172a; font-weight: bold;"><?= date('d/m/Y', strtotime($data['doc_date'])) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">ความสำคัญ</span> <span style="color: #0f172a; font-weight: bold;"><?= htmlspecialchars($data['priority'] ?? 'ปานกลาง') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">วันที่ต้องการสินค้า</span> <span style="color: #0f172a; font-weight: bold;"><?= (!empty($data['due_date']) && $data['due_date'] !== '0000-00-00') ? date('d/m/Y', strtotime($data['due_date'])) : '-' ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">เลขที่อ้างอิง</span> <span style="color: #0f172a; font-weight: bold;"><?= $data['reference_no'] ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">วิธีการชำระ</span> <span style="font-weight: bold; color: #0f172a;"><?= htmlspecialchars($data['payment_method_name'] ?? '-') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0;">
                <span style="color: #64748b;">รับของ</span>
                <span style="font-weight: bold; color: <?= $data['received_status'] === 'received' ? '#10b981' : ($data['received_status'] === 'partial' ? '#f59e0b' : '#94a3b8') ?>;">
                    <?php if ($data['received_status'] === 'received'): ?>
                        <i class="fas fa-check-circle"></i> รับแล้ว <?= $data['received_at'] ? date('d/m/Y', strtotime($data['received_at'])) : '' ?>
                    <?php elseif ($data['received_status'] === 'partial'): ?>
                        <i class="fas fa-minus-circle"></i> รับบางส่วน
                    <?php else: ?>
                        <i class="far fa-circle"></i> ยังไม่ได้รับ
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($data['payment_slip'])): ?>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-top: 1px solid #f1f5f9; margin-top: 4px;">
                <span style="color: #64748b;">สลิปชำระเงิน</span>
                <a href="uploads/payments/<?= htmlspecialchars($data['payment_slip']) ?>" target="_blank" style="color: #2563eb; font-weight: bold; text-decoration: underline;">
                    <i class="fas fa-receipt"></i> ดูสลิป
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items Table -->
    <div class="item-section">
        <table class="table-items">
            <thead>
                <tr>
                    <th width="3%">#</th>
                    <th align="left">รายการ / Description</th>
                    <th width="5%" align="center">จำนวน</th>
                    <th width="8%" align="right">หน่วย</th>
                    <th width="12%" align="right">ราคา</th>
                    <?php if ($total_discount > 0): ?><th width="10%" align="right">ส่วนลด</th><?php endif; ?>
                    <th width="12%" align="right">ยอดรวม</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                mysqli_data_seek($res_items, 0);
                while ($item = mysqli_fetch_assoc($res_items)):
                    ?>
                    <tr>
                        <td align="center" style="color: #64748b;"><?= $count++ ?></td>
                        <td style="font-weight: 400; vertical-align: top; line-height: 1.4; color: #334155; max-width: 300px; word-break: break-word; overflow-wrap: break-word;">
                            <?= nl2br(htmlspecialchars($item['item_desc'])) ?>
                        </td>
                        <td align="center"><?= number_format($item['item_qty'], 0) ?></td>
                        <td align="right"><?= htmlspecialchars($item['item_unit']) ?></td>
                        <td align="right"><?= number_format($item['item_price'], 2) ?></td>
                        <?php if ($total_discount > 0): ?><td align="right"><?= number_format($item['item_discount'], 2) ?></td><?php endif; ?>
                        <td align="right" style="font-weight: 600;"><?= number_format($item['item_total'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer Summary & Signatures -->
    <div class="doc-footer" style="margin-top: 10px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <div style="width: 55%; padding-right: 20px;">
                <p style="font-size: 10px; font-weight: bold; text-decoration: underline; margin-bottom: 3px;">หมายเหตุ:</p>
                <p style="font-size: 10px; color: #64748b; line-height: 1.4;"><?= nl2br($data['notes']) ?: '-' ?></p>
            </div>
            <div style="width: 40%;">
                <div style="background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #f1f5f9;">
                    <table width="100%" style="font-size: 11px;">
                        <tr><td style="padding-bottom: 3px;">รวมเป็นเงิน</td><td align="right" style="padding-bottom: 3px;"><?= number_format($data['subtotal'], 2) ?></td></tr>
                        <tr><td style="padding-bottom: 3px; ">ภาษีมูลค่าเพิ่ม <?= (float) ($data['vat_percent'] ?? 7) ?>%</td><td align="right" style="padding-bottom: 3px; font-weight: 600;"><?= number_format($data['vat'], 2) ?></td></tr>
                        <tr><td style="padding-bottom: 3px;">หัก ณ ที่จ่าย <?= number_format($data['wht_percent'], 0) ?>%</td><td align="right" style="padding-bottom: 3px;"><?= number_format($data['wht_amount'], 2) ?></td></tr>
                        <tr style="font-size: 13px; font-weight: 900; color: var(--primary-color);"><td style="padding-top: 5px; border-top: 1px solid #e2e8f0;">ยอดสุทธิ</td><td align="right" style="padding-top: 5px; border-top: 1px solid #e2e8f0;"><?= number_format($data['grand_total'], 2) ?></td></tr>
                        <tr><td colspan="2" align="right" style="padding-top: 5px; font-size: 9px; color: #64748b; font-style: italic;">( <?= BahtText($data['grand_total']) ?> )</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Signature Section -->
        <div style="display: flex; flex-wrap: wrap; gap: 5px 0; margin-top: 10px;">
            <?php foreach($display_list as $item): ?>
                <div style="width: <?= $col_width ?>;" class="sig-wrapper">
                    <div class="sig-box">
                        <?php if (!$item['is_empty'] && !empty($item['sig'])): ?>
                            <img src="uploads/signatures/<?= $item['sig'] ?>">
                        <?php endif; ?>
                    </div>
                    <p class="sig-title"><?= $item['label'] ?></p>
                    <p class="sig-name">(<?= (!$item['is_empty'] && !empty($item['name'])) ? $item['name'] : '..................................' ?>)</p>
                    <?php if (!$item['is_empty'] && !empty($item['date'])): ?>
                        <p class="sig-date">วันที่: <?= date('d/m/Y', strtotime($item['date'])) ?></p>
                    <?php else: ?>
                        <p class="sig-date">วันที่: ....................</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function receivePR(id) {
        Swal.fire({
            title: 'ยืนยันรับของ?',
            text: 'คุณต้องการยืนยันว่าได้รับสินค้าครบถ้วนแล้วใช่หรือไม่?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'รับของแล้ว',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true,
            heightAuto: false,
            showDenyButton: true,
            denyButtonText: 'รับบางส่วน',
            denyButtonColor: '#f59e0b'
        }).then((result) => {
            let action = '';
            if (result.isConfirmed) action = 'received';
            else if (result.isDenied) action = 'partial';
            else return;

            fetch(`api/receive_pr.php?id=${id}&action=${action}`).then(res => res.json()).then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'สำเร็จ', text: res.message, timer: 1500, showConfirmButton: false }).then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message });
                }
            });
        });
    }

    function exportPDF() {
        const element = document.getElementById('quotation-content');
        const opt = { 
            margin: [5, 5, 5, 5], // [top, left, bottom, right] in mm
            filename: 'PR_<?php echo $data['doc_no']; ?>.pdf', 
            image: { type: 'jpeg', quality: 1.0 }, 
            html2canvas: { scale: 3, useCORS: true, letterRendering: true }, 
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } 
        };
        html2pdf().set(opt).from(element).save();
    }
    function toggleSignature() { window.location.href = 'create_signature.php'; }
    function exportWord() {
        const sourceHTML = "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'><head><meta charset='utf-8'></head><body>" + document.getElementById("quotation-content").innerHTML + "</body></html>";
        const source = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent(sourceHTML);
        const fileDownload = document.createElement("a");
        document.body.appendChild(fileDownload);
        fileDownload.href = source;
        fileDownload.download = 'PR_<?php echo $data["doc_no"]; ?>.doc';
        fileDownload.click();
        document.body.removeChild(fileDownload);
    }

</script>

<?php include('footer.php'); ?>