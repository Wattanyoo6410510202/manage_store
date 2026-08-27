<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
if (empty($id)) {
    die("ไม่พบรหัสโครงการ");
}

$sql = "SELECT p.*, 
               u_creator.name as creator_name, sig_creator.path as creator_sig,
               u_app.name as approver_name, sig_app.path as approver_sig,
               c.customer_name, c.address as cust_address, c.tax_id as cust_tax,
               own.company_name as my_company, own.address as my_address, own.tax_id as my_tax, own.phone as my_phone, own.logo_path
        FROM projects p
        LEFT JOIN users u_creator ON p.created_by = u_creator.id
        LEFT JOIN signatures sig_creator ON u_creator.id = sig_creator.users_id
        LEFT JOIN users u_app ON p.approved_by = u_app.id
        LEFT JOIN signatures sig_app ON u_app.id = sig_app.users_id
        LEFT JOIN customers c ON p.customer_id = c.id
        LEFT JOIN suppliers own ON p.supplier_id = own.id
        WHERE p.id = '$id' LIMIT 1";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    die("ไม่พบข้อมูลโครงการ");
}

$logo_path = !empty($data['logo_path']) ? 'uploads/' . $data['logo_path'] : '';

$display_list = [];
$display_list[] = ['label' => 'ผู้จัดทำโครงการ', 'name' => $data['creator_name'], 'sig' => $data['creator_sig'], 'date' => $data['created_at'], 'is_empty' => false];
$display_list[] = ['label' => 'ผู้อนุมัติโครงการ', 'name' => $data['approver_name'], 'sig' => $data['approver_sig'], 'date' => $data['updated_at'], 'is_empty' => empty($data['approved_by'])];

$col_width = (100 / count($display_list)) . '%';
?>

<style>
    :root { --primary-color: #2563eb; }
    .page-container { background: white; padding: 40px; min-height: 297mm; margin: 20px auto; width: 210mm; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    .sig-box { border-bottom: 1px dashed #cbd5e1; width: 160px; height: 60px; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; }
    .sig-box img { max-height: 50px; object-fit: contain; }
    .sig-wrapper { text-align: center; margin-top: 50px; }
    .sig-title { font-weight: bold; font-size: 12px; margin: 0; }
    .sig-name { font-size: 11px; margin: 4px 0 0; }
    .sig-date { font-size: 9px; color: #94a3b8; margin: 2px 0 0; }

    @media print {
        body * { visibility: hidden; }
        #quotation-content, #quotation-content * { visibility: visible; }
        #quotation-content { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 10mm; box-shadow: none; }
        @page { size: A4; margin: 5mm; }
    }
</style>

<div class="no-print mb-4 flex justify-end gap-2 max-w-4xl mx-auto">
    <button onclick="window.print()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm hover:bg-indigo-700">
        <i class="fas fa-print mr-2"></i> พิมพ์เอกสาร
    </button>
</div>

<div id="quotation-content" class="page-container">
    <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
        <div style="display: flex; gap: 15px;">
            <?php if ($logo_path): ?> <img src="<?= $logo_path ?>" style="width: 65px; height: 65px; object-fit: contain;"> <?php endif; ?>
            <div style="font-size: 10px; line-height: 1.4;">
                <h1 style="margin: 0; font-size: 16px; color: #0f172a;"><?= htmlspecialchars(supplier_display_name($data['my_company'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                <p style="margin: 0; color: #64748b;"><?= htmlspecialchars($data['my_address'] ?? '') ?></p>
                <p style="margin: 0; color: #64748b;">Tax ID: <?= htmlspecialchars($data['my_tax'] ?? '') ?> | Tel: <?= htmlspecialchars($data['my_phone'] ?? '') ?></p>
            </div>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 900;">ใบอนุมัติโครงการ</h2>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6 mb-8">
        <!-- Card 1: ข้อมูลโครงการ -->
        <div class="border border-slate-200 rounded-lg p-5">
            <h3 class="font-bold text-slate-800 border-b pb-2 mb-3">ข้อมูลโครงการ</h3>
            <div class="space-y-2 text-sm">
                <div><span class="text-slate-500">เลขที่:</span> <span class="font-bold"><?= htmlspecialchars($data['project_no']) ?></span></div>
                <div><span class="text-slate-500">ชื่อ:</span> <span class="font-bold"><?= htmlspecialchars($data['project_name']) ?></span></div>
                <div><span class="text-slate-500">ลูกค้า:</span> <span class="font-bold"><?= htmlspecialchars($data['customer_name'] ?? '-') ?></span></div>
                <div><span class="text-slate-500">สถานะ:</span> <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px] uppercase"><?= $data['project_status'] == 'completed' ? 'เสร็จสิ้น' : 'กำลังดำเนินการ' ?></span></div>
                <div><span class="text-slate-500">ระยะเวลา:</span> <span class="font-bold"><?= (!empty($data['start_date']) ? date('d/m/Y', strtotime($data['start_date'])) : '-') ?> ถึง <?= (!empty($data['end_date']) ? date('d/m/Y', strtotime($data['end_date'])) : '-') ?></span></div>
            </div>
        </div>

        <!-- Card 2: ค่าใช้จ่าย -->
        <div class="border border-slate-200 rounded-lg p-5">
            <h3 class="font-bold text-slate-800 border-b pb-2 mb-3">รายละเอียดการเงิน</h3>
            <div class="space-y-2 text-sm">
                <div><span class="text-slate-500">มูลค่าสัญญา:</span> <span class="font-bold"><?= number_format($data['contract_value'], 2) ?></span></div>
                <div><span class="text-slate-500">ภาษีมูลค่าเพิ่ม:</span> <span class="font-bold"><?= number_format($data['total_vat_amount'], 2) ?></span></div>
                <div><span class="text-slate-500">หัก ณ ที่จ่าย:</span> <span class="font-bold"><?= number_format($data['total_wht_amount'], 2) ?></span></div>
                <div class="pt-2 border-t mt-2"><span class="text-slate-500">ยอดสุทธิ:</span> <span class="font-bold text-indigo-600 text-lg"><?= number_format($data['net_contract_value'], 2) ?></span></div>
            </div>
        </div>
    </div>
    
    <div class="border border-slate-200 rounded-lg p-5 mb-8">
        <span class="text-slate-500 text-sm font-bold">รายละเอียดเพิ่มเติม:</span>
        <p class="mt-1 text-sm text-slate-700"><?= nl2br(htmlspecialchars($data['project_remarks'] ?? '-')) ?></p>
    </div>

    <div style="display: flex; margin-top: 50px;">
        <?php foreach($display_list as $item): ?>
            <div style="width: <?= $col_width ?>;" class="sig-wrapper">
                <div class="sig-box">
                    <?php if (!$item['is_empty'] && !empty($item['sig'])): ?>
                        <img src="uploads/signatures/<?= $item['sig'] ?>">
                    <?php endif; ?>
                </div>
                <p class="sig-title"><?= $item['label'] ?></p>
                <p class="sig-name">( <?= (!empty($item['name'])) ? htmlspecialchars($item['name']) : '..................................' ?> )</p>
                <p class="sig-date"><?= (!empty($item['date'])) ? 'วันที่: ' . date('d/m/Y', strtotime($item['date'])) : 'วันที่: ....................' ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include('footer.php'); ?>
