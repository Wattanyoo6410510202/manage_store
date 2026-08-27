<?php
require_once 'config.php';
include('header.php');

$pr_id = isset($_GET['pr_id']) ? (int)$_GET['pr_id'] : 0;
if (!$pr_id) die("ไม่พบรหัสเอกสาร");

$rcp = mysqli_query($conn, "
    SELECT r.*, u.name as receiver_name
    FROM receiving_reports r
    LEFT JOIN users u ON r.received_by = u.id
    WHERE r.pr_id = $pr_id
    ORDER BY r.id DESC LIMIT 1
")->fetch_assoc();

if (!$rcp) die("ไม่พบใบรับของ");

$pr = mysqli_query($conn, "
    SELECT p.*, s.company_name as supplier_name,
           po.id as po_id, po.doc_no as po_doc_no,
           own.company_name as my_company, own.address as my_address,
           own.tax_id as my_tax, own.phone as my_phone, own.logo_path
    FROM pr p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN po ON po.reference_no = p.doc_no AND po.deleted_at IS NULL
    LEFT JOIN suppliers own ON p.supplier_id = own.id
    WHERE p.id = $pr_id
")->fetch_assoc();

$items = mysqli_query($conn, "
    SELECT * FROM receiving_report_items
    WHERE receiving_id = {$rcp['id']}
    ORDER BY id ASC
");

$logo_path = !empty($pr['logo_path']) ? 'uploads/' . $pr['logo_path'] : '';

$has_diff = false;
$all_full = true;
$temp_items = [];
while ($item = mysqli_fetch_assoc($items)) {
    if ($item['diff_qty'] < 0) { $has_diff = true; $all_full = false; }
    if ($item['diff_qty'] > 0) $all_full = false;
    $temp_items[] = $item;
}
$items_data = $temp_items;
$status_label = $all_full ? 'รับของครบถ้วน' : ($has_diff ? 'รับของไม่ครบ' : 'รับของครบถ้วน');
$status_color = $all_full ? '#10b981' : '#ef4444';
?>
<style>
    @page { size: A4; margin: 10mm; }
    @media print {
        body { margin: 0; padding: 0; background: none; }
        .no-print { display: none !important; }
        .page { padding: 0 !important; width: 100% !important; margin: 0 !important; box-shadow: none !important; }
    }
    body { font-family: 'Sarabun', sans-serif; background: #f1f5f9; }
    .page { background: white; width: 210mm; margin: 20px auto; padding: 30px 35px; min-height: 297mm; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { background: #f8fafc; padding: 8px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border-bottom: 2px solid #e2e8f0; }
    td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; color: #334155; }
    .diff-neg { color: #ef4444; font-weight: 700; }
    .diff-zero { color: #10b981; font-weight: 600; }
    .diff-pos { color: #f59e0b; font-weight: 600; }
</style>

<div class="no-print" style="text-align: center; padding: 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
    <button onclick="window.print()" style="padding: 10px 28px; background: #6366f1; color: white; border: none; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer;">
        <i class="fas fa-print"></i> พิมพ์เอกสาร
    </button>
    <a href="request_buy_history.php" style="padding: 10px 28px; background: white; color: #64748b; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; margin-left: 8px;">
        <i class="fas fa-arrow-left"></i> กลับ
    </a>
</div>

<div class="page">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
        <div style="display: flex; gap: 15px;">
            <?php if ($logo_path): ?>
                <img src="<?= $logo_path ?>" style="width: 60px; height: 60px; object-fit: contain;">
            <?php endif; ?>
            <div>
                <h1 style="margin: 0; font-size: 15px; color: #0f172a;"><?= htmlspecialchars(supplier_display_name($pr['my_company']), ENT_QUOTES, 'UTF-8') ?></h1>
                <p style="margin: 2px 0; font-size: 10px; color: #64748b;"><?= $pr['my_address'] ?></p>
                <?php if (!empty($pr['my_tax'])): ?><p style="margin: 2px 0; font-size: 10px; color: #64748b;">Tax ID: <?= $pr['my_tax'] ?> | Tel: <?= $pr['my_phone'] ?></p><?php endif; ?>
            </div>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 22px; color: #059669; font-weight: 900;">ใบรับของ</h2>
            <p style="margin: 2px 0 0; font-size: 9px; letter-spacing: 2px; color: #94a3b8; text-transform: uppercase;">RECEIVING REPORT</p>
            <p style="margin: 6px 0 0; font-size: 14px; font-weight: 700; color: #0f172a;"><?= $rcp['doc_no'] ?></p>
        </div>
    </div>

    <!-- Info -->
    <div style="display: flex; gap: 15px; margin-bottom: 16px;">
        <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #f8fafc;">
            <h4 style="margin: 0 0 6px; font-size: 12px; color: #0f172a;">ผู้จำหน่าย / Supplier</h4>
            <div style="font-size: 11px; line-height: 1.6;">
                <div><b style="color: #64748b;">ชื่อ:</b> <?= htmlspecialchars(supplier_display_name($pr['supplier_name']), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #f8fafc;">
            <h4 style="margin: 0 0 6px; font-size: 12px; color: #0f172a;">อ้างอิง</h4>
            <div style="font-size: 11px; line-height: 1.6;">
                <div><b style="color: #64748b;">PR:</b> <?= $pr['doc_no'] ?></div>
                <div><b style="color: #64748b;">PO:</b> <?= $pr['po_doc_no'] ?? '-' ?></div>
            </div>
        </div>
        <div style="width: 180px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #f8fafc;">
            <h4 style="margin: 0 0 6px; font-size: 12px; color: #0f172a;">ผู้รับของ / Receiver</h4>
            <div style="font-size: 11px; line-height: 1.6;">
                <div><b style="color: #64748b;">ชื่อ:</b> <?= $rcp['receiver_name'] ?></div>
                <div><b style="color: #64748b;">วันที่:</b> <?= date('d/m/Y H:i', strtotime($rcp['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <!-- Status -->
    <div style="text-align: center; margin-bottom: 16px;">
        <span style="display: inline-block; padding: 6px 24px; border-radius: 30px; font-size: 14px; font-weight: 800; color: white; background: <?= $status_color ?>;">
            <i class="fas <?= $all_full ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i> <?= $status_label ?>
        </span>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th width="4%" style="text-align: center;">#</th>
                <th style="text-align: left;">รายการ / Description</th>
                <th width="10%" style="text-align: center;">จำนวนสั่ง</th>
                <th width="8%" style="text-align: center;">หน่วย</th>
                <th width="12%" style="text-align: center;">รับแล้ว</th>
                <th width="10%" style="text-align: center;">ส่วนต่าง</th>
                <th width="18%" style="text-align: left;">เหตุผล</th>
            </tr>
        </thead>
        <tbody>
            <?php $idx = 1; foreach ($items_data as $item): 
                $diff = floatval($item['diff_qty']);
                $diff_class = $diff < 0 ? 'diff-neg' : ($diff > 0 ? 'diff-pos' : 'diff-zero');
                $diff_label = $diff < 0 ? 'ขาด ' . number_format(abs($diff), 2) : ($diff > 0 ? 'เกิน ' . number_format($diff, 2) : 'ครบ');
            ?>
            <tr>
                <td style="text-align: center; color: #94a3b8;"><?= $idx++ ?></td>
                <td><?= $item['item_desc'] ?></td>
                <td style="text-align: center;"><?= number_format($item['ordered_qty'], 2) ?></td>
                <td style="text-align: center;"><?= $item['item_unit'] ?></td>
                <td style="text-align: center; font-weight: 700;"><?= number_format($item['received_qty'], 2) ?></td>
                <td style="text-align: center;" class="<?= $diff_class ?>"><?= $diff_label ?></td>
                <td style="font-size: 11px; color: #64748b;"><?= !empty($item['reason']) ? htmlspecialchars($item['reason']) : ($diff < 0 ? '<span style="color:#ef4444;">ไม่ได้ระบุเหตุผล</span>' : '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Note -->
    <?php if (!empty($rcp['note'])): ?>
    <div style="margin-top: 20px; padding: 12px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
        <p style="margin: 0; font-size: 11px; font-weight: 700; color: #334155;">หมายเหตุ / Note:</p>
        <p style="margin: 4px 0 0; font-size: 12px; color: #64748b;"><?= nl2br(htmlspecialchars($rcp['note'])) ?></p>
    </div>
    <?php endif; ?>

    <!-- Signatures -->
    <div style="display: flex; justify-content: space-around; margin-top: 50px; padding-top: 20px;">
        <div style="text-align: center; width: 200px;">
            <div style="height: 60px; border-bottom: 1px solid #cbd5e1; margin-bottom: 8px;"></div>
            <p style="margin: 0; font-size: 11px; font-weight: 700; color: #334155;">ผู้รับของ</p>
            <p style="margin: 2px 0 0; font-size: 11px; color: #64748b;">(<?= $rcp['receiver_name'] ?>)</p>
            <p style="margin: 2px 0 0; font-size: 10px; color: #94a3b8;">วันที่: <?= date('d/m/Y', strtotime($rcp['created_at'])) ?></p>
        </div>
        <div style="text-align: center; width: 200px;">
            <div style="height: 60px; border-bottom: 1px solid #cbd5e1; margin-bottom: 8px;"></div>
            <p style="margin: 0; font-size: 11px; font-weight: 700; color: #334155;">ผู้ตรวจสอบ</p>
            <p style="margin: 2px 0 0; font-size: 11px; color: #64748b;">(..................................)</p>
            <p style="margin: 2px 0 0; font-size: 10px; color: #94a3b8;">วันที่: ....................</p>
        </div>
    </div>

    <!-- Footer -->
    <div style="margin-top: 30px; padding-top: 10px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 9px; color: #94a3b8;">
        เอกสารนี้สร้างจากระบบจัดการจัดซื้อจัดจ้าง
    </div>
</div>

<?php include('footer.php'); ?>
