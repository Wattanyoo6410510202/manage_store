<?php
require_once __DIR__ . '/inspection_workflow.php';
require_once __DIR__ . '/supplier_display.php';
require_once __DIR__ . '/inspection_document_options.php';

$round = inspection_fetch_one($conn, "SELECT * FROM inspection_rounds WHERE id = ? LIMIT 1", 'i', [$dynamic_round_id]);
if (!$round) {
    echo '<div class="p-10 text-center font-bold text-rose-600">ไม่พบรอบตรวจรับงาน</div>';
    return;
}

$context = inspection_load_context($conn, (int)$round['project_id'], (int)$round['milestone_id']);
$project = $context['project'] ?: [];
$liveProject = $project;
$liveContractReferenceNo = trim((string)($project['contract_reference_no'] ?? ($project['contract_no'] ?? '')));
$results = inspection_fetch_all($conn, "SELECT * FROM inspection_round_results WHERE round_id = ? ORDER BY category, item_order, id", 'i', [$dynamic_round_id]);
$approvals = inspection_fetch_all($conn, "SELECT * FROM inspection_approvals WHERE round_id = ? ORDER BY created_at, id", 'i', [$dynamic_round_id]);
$attachments = inspection_fetch_all($conn, "SELECT * FROM inspection_attachments WHERE round_id = ? ORDER BY id", 'i', [$dynamic_round_id]);
$document = inspection_fetch_one($conn, "SELECT * FROM inspection_documents WHERE round_id = ? ORDER BY id DESC LIMIT 1", 'i', [$dynamic_round_id]);
$snapshot = ($document && ($document['status'] ?? '') === 'final') ? json_decode((string)$document['snapshot_json'], true) : null;
if (is_array($snapshot)) {
    $project = $snapshot['project'] ?? $project;
    foreach (['supplier_name', 'customer_name'] as $projectNameField) {
        if (empty($project[$projectNameField]) && !empty($liveProject[$projectNameField])) {
            $project[$projectNameField] = $liveProject[$projectNameField];
        }
    }
    if (empty($project['contract_reference_no']) && $liveContractReferenceNo !== '') {
        $project['contract_reference_no'] = $liveContractReferenceNo;
    }
    $results = $snapshot['items'] ?? $results;
    $approvals = $snapshot['approvals'] ?? $approvals;
    $attachments = $snapshot['attachments'] ?? $attachments;
}
$contractReferenceNo = trim((string)($project['contract_reference_no'] ?? ($project['contract_no'] ?? '')));
$employerName = supplier_display_name(trim((string)($project['supplier_name'] ?? '')));
$supplierName = trim((string)($project['customer_name'] ?? ''));

$summary = inspection_compare_results($results);
$resultGroups = inspection_results_by_step($results);
$money = static function ($value): string {
    return number_format((float)($value ?? 0), 2);
};
$percent = static function ($value): string {
    return rtrim(rtrim(number_format((float)($value ?? 0), 2, '.', ''), '0'), '.');
};
$payment = inspection_payment_summary($project);
$date = static function ($value): string {
    if (!$value || $value === '0000-00-00') return '-';
    $time = strtotime((string)$value);
    return $time ? date('d/m/Y', $time) : inspection_h($value);
};
$approvalFor = static function (array $rows, string $step): ?array {
    for ($i = count($rows) - 1; $i >= 0; $i--) {
        if (($rows[$i]['step'] ?? '') === $step && ($rows[$i]['action'] ?? '') === 'approve') return $rows[$i];
    }
    return null;
};
$approvalLabels = [
    'inspector_1' => 'ผู้ตรวจรับ 1',
    'inspector_2' => 'ผู้ตรวจรับ 2',
    'procurement' => 'เจ้าหน้าที่จัดซื้อ',
    'md' => 'OA&HR Manager',
    'gmacc' => 'หัวหน้าบัญชี (GMACC)',
];
$approvalName = static function (?array $approval): string {
    return trim((string)($approval['user_name_snapshot'] ?? '')) ?: '................................';
};
$contractor = trim((string)($project['contractor_name'] ?? ''));
if ($contractor === '') {
    $contractor = $supplierName;
}
$milestoneName = trim((string)($project['milestone_name'] ?? ''));
$milestoneNumber = trim((string)($project['milestone_no'] ?? ''));
$milestoneDetail = '';
if (preg_match('/^\s*(งวดที่\s*[^:]+)\s*:\s*(.*)$/u', $milestoneName, $parts)) {
    $milestoneNumber = trim($parts[1]);
    $milestoneDetail = trim($parts[2]);
} elseif ($milestoneNumber !== '') {
    $milestoneNumber = preg_match('/^\s*งวดที่\b/u', $milestoneNumber)
        ? $milestoneNumber
        : 'งวดที่ ' . $milestoneNumber;
    $milestoneDetail = $milestoneName;
} else {
    $milestoneNumber = $milestoneName;
}
$milestoneNumber = $milestoneNumber ?: '-';
$milestoneDetail = $milestoneDetail ?: '-';
$milestone = $milestoneNumber . ($milestoneDetail !== '-' ? ': ' . $milestoneDetail : '');
$mdApproval = $approvalFor($approvals, 'md');
$procurementApproval = $approvalFor($approvals, 'procurement');
$documentOptions = inspection_document_options();
$documentSelection = inspection_document_selection_from_json($procurementApproval['selected_documents_json'] ?? '');
$hasIssues = !empty($summary['has_fail']) || !empty($summary['has_conflict']);
$inspectionOutcome = !$results ? 'รอสรุปผลการตรวจรับ' : ($hasIssues ? 'มีรายการแก้ไข/เงื่อนไข' : 'ผ่านการตรวจรับทั้งหมด');
$nextAction = $mdApproval ? 'ส่งฝ่ายบัญชีดำเนินการเบิกจ่าย' : 'รอการอนุมัติจาก OA&HR Manager';
$documentNo = $document['document_no'] ?? ('INS-' . str_pad((string)$round['id'], 5, '0', STR_PAD_LEFT));
$resultStatusLabel = static function ($status): string {
    return ['pass' => 'ผ่าน', 'fail' => 'ไม่ผ่าน', 'conditional_pass' => 'มีเงื่อนไข', 'not_applicable' => 'ไม่เกี่ยวข้อง', '' => 'ยังไม่ระบุ'][$status] ?? 'ยังไม่ระบุ';
};
?>
<style>
    :root {
        --doc-ink: #182433;
        --doc-muted: #607086;
        --doc-line: #cbd5e1;
        --doc-soft: #f4f7fb;
        --doc-accent: #1f4f7a;
        --doc-accent-soft: #eaf2f9;
    }
    .dynamic-document-shell { max-width: 940px; margin: 0 auto; }
    .dynamic-document { width: 210mm; min-height: 292mm; box-sizing: border-box; margin: 0 auto; color: var(--doc-ink); font-family: "Sarabun", "Noto Sans Thai", "Tahoma", sans-serif; font-size: 10.5pt; line-height: 1.35; }
    .dynamic-page { width: 210mm; min-height: 292mm; box-sizing: border-box; padding: 11mm 12mm 10mm; background: #fff; border: 1px solid #d9e1eb; box-shadow: 0 16px 45px rgba(15, 23, 42, .10); }
    .doc-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; border-bottom: 2px solid var(--doc-accent); padding-bottom: 9px; }
    .doc-kicker { margin: 0 0 2px; color: var(--doc-accent); font-size: 8.5pt; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .doc-header h1 { margin: 0; font-size: 19pt; line-height: 1.1; font-weight: 800; letter-spacing: -.01em; }
    .doc-subtitle { margin: 4px 0 0; color: var(--doc-muted); font-size: 9.5pt; }
    .doc-id { min-width: 150px; padding: 8px 10px; border: 1px solid var(--doc-line); background: var(--doc-soft); font-size: 9pt; line-height: 1.6; text-align: left; }
    .doc-id strong { color: var(--doc-ink); }
    .lead-letter { margin-top: 10px; padding: 9px 11px; border-left: 3px solid var(--doc-accent); background: #fbfdff; font-size: 10pt; }
    .lead-letter p { margin: 3px 0; }
    .doc-section { margin-top: 11px; page-break-inside: avoid; break-inside: avoid; }
    .section-heading { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px; }
    .section-no { display: inline-flex; width: 22px; height: 22px; align-items: center; justify-content: center; border-radius: 6px; background: var(--doc-accent-soft); color: var(--doc-accent); font-size: 9pt; font-weight: 800; }
    .section-heading h2 { margin: 0; color: var(--doc-ink); font-size: 11.5pt; line-height: 1.15; font-weight: 800; }
    .section-heading p { margin: 1px 0 0; color: var(--doc-muted); font-size: 8.5pt; }
    .field-grid { display: grid; grid-template-columns: 1.25fr 1fr 1fr; gap: 4px 8px; }
    .field { min-width: 0; padding: 4px 6px; border: 1px solid var(--doc-line); background: #fff; }
    .field.full { grid-column: 1 / -1; }
    .field-label { display: block; color: var(--doc-muted); font-size: 8.5pt; font-weight: 700; }
    .field-value { display: block; min-height: 14px; margin-top: 1px; color: var(--doc-ink); font-size: 9.5pt; font-weight: 700; overflow-wrap: anywhere; }
    .money-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; align-items: stretch; }
    .money-card { display: flex; min-height: 42px; flex-direction: column; justify-content: space-between; padding: 7px 9px; border: 1px solid var(--doc-line); background: var(--doc-soft); }
    .money-card.total { border-color: #9bb8d2; background: var(--doc-accent-soft); }
    .money-label { display: block; color: var(--doc-muted); font-size: 8pt; font-weight: 700; }
    .money-value { display: block; margin-top: 2px; font-size: 11pt; font-weight: 800; text-align: right; font-variant-numeric: tabular-nums; }
    .decision-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
    .decision-row-plain { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 18px; padding: 7px 0; border-top: 1px solid var(--doc-line); border-bottom: 1px solid var(--doc-line); }
    .decision-row-plain .decision { min-height: 0; padding: 2px 0; border: 0; background: transparent; }
    .decision-row-plain .decision.active { border: 0; background: transparent; color: var(--doc-accent); }
    .decision { display: flex; min-height: 31px; align-items: center; gap: 6px; padding: 6px 8px; border: 1px solid var(--doc-line); background: #fff; font-weight: 700; }
    .decision.active { border-color: #8fb6d4; background: var(--doc-accent-soft); }
    .checkbox { display: inline-flex; width: 13px; height: 13px; flex: 0 0 13px; align-items: center; justify-content: center; border: 1.3px solid #64748b; background: #fff; }
    .checkbox.checked { border-color: var(--doc-accent); background: var(--doc-accent); }
    .checkbox.checked::after { content: ""; width: 6px; height: 3px; border-left: 1.5px solid #fff; border-bottom: 1.5px solid #fff; transform: rotate(-45deg) translateY(-1px); }
    .inline-note { margin: 5px 0 0; color: var(--doc-muted); font-size: 9pt; }
    .punch-box { min-height: 15mm; padding: 4px 0; border: 0; background: transparent; }
    .punch-box p { margin: 0; font-size: 9.5pt; }
    .attachment-row { display: flex; flex-wrap: wrap; gap: 5px 13px; padding: 6px 8px; border: 1px solid var(--doc-line); background: #fff; }
    .attachment { display: inline-flex; align-items: center; gap: 5px; font-size: 9pt; }
    .attachment-other-detail { margin-top: 5px; padding: 5px 8px; border: 1px solid var(--doc-line); background: var(--doc-soft); color: var(--doc-ink); font-size: 9pt; }
    .result-compare-table { width: 100%; border-collapse: collapse; margin-top: 7px; font-size: 8.5pt; }
    .result-compare-table th, .result-compare-table td { padding: 4px 6px; border-bottom: 1px solid var(--doc-line); text-align: left; vertical-align: top; }
    .result-compare-table th { color: var(--doc-muted); background: var(--doc-soft); font-weight: 800; }
    .result-status-pill { display: inline-block; padding: 2px 6px; border-radius: 999px; background: var(--doc-soft); font-weight: 800; }
    .payment-note { margin-top: 7px; padding: 6px 8px; border: 1px solid #b9cfe0; background: #f5f9fc; color: #2c4d67; font-size: 9pt; line-height: 1.45; }
    .handover-signature { display: grid; grid-template-columns: 1fr 150px; gap: 10px; margin-top: 7px; padding: 5px 7px; border-top: 1px solid var(--doc-line); border-bottom: 1px solid var(--doc-line); }
    .handover-delivery-date { min-height: 18mm; box-sizing: border-box; padding-top: 1px; }
    .handover-delivery-date .signature-date { display: block; margin-top: 9px; }
    .signature-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 6px; margin-top: 6px; }
    .signature-grid .signature { grid-column: span 2; }
    .signature-grid .signature:nth-child(4) { grid-column: 2 / span 2; }
    .signature-grid .signature:nth-child(5) { grid-column: 4 / span 2; }
    .signature { min-height: 18mm; padding: 4px 5px; border: 1px solid var(--doc-line); text-align: center; }
    .signature-line { display: flex; height: 8mm; align-items: flex-end; justify-content: center; margin: 0 8px 2px; border-bottom: 1px solid #64748b; }
    .signature-line img { max-width: 90%; max-height: 7mm; object-fit: contain; }
    .signature-name { font-size: 8.5pt; font-weight: 700; }
    .signature-role { margin-top: 2px; color: var(--doc-muted); font-size: 8.5pt; }
    .signature-date { color: var(--doc-muted); font-size: 7.5pt; }
    .no-print { display: flex; flex-wrap: wrap; gap: 8px; max-width: 940px; margin: 0 auto 14px; }
    .no-print button, .no-print a { display: inline-flex; align-items: center; border: 0; border-radius: 9px; padding: 9px 13px; font-weight: 700; cursor: pointer; text-decoration: none; }
    @media (max-width: 960px) { .dynamic-document, .dynamic-page { width: 100%; min-height: auto; } .dynamic-page { padding: 18px 14px; } }
    @media (max-width: 640px) { .doc-header, .handover-signature { grid-template-columns: 1fr; display: grid; } .doc-id { min-width: 0; } .field-grid, .money-grid, .decision-row, .signature-grid { grid-template-columns: 1fr; } .field.full { grid-column: auto; } }
    @media (max-width: 640px) { .signature-grid .signature { grid-column: auto; } }
    @media print {
        @page { size: A4 portrait; margin: 0; }
        html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
        body * { visibility: hidden !important; }
        #dynamic-inspection-document, #dynamic-inspection-document * { visibility: visible !important; }
        #dynamic-inspection-document { position: absolute; top: 0; left: 0; width: 210mm; min-height: 0; margin: 0; font-size: 10.5pt; line-height: 1.35; }
        .dynamic-page { width: 210mm; min-height: 0; margin: 0; padding: 8mm 11mm 7mm; border: 0; box-shadow: none; }
        .doc-header { padding-bottom: 8px; }
        .lead-letter { margin-top: 9px; padding: 8px 10px; }
        .lead-letter p { margin: 3px 0; }
        .doc-section { margin-top: 9px; }
        .section-heading { margin-bottom: 7px; }
        .field { padding: 4px 6px; }
        .money-card { min-height: 40px; padding: 6px 8px; }
        .decision-row-plain { padding: 6px 0; }
        .attachment-row { padding: 5px 8px; }
        .payment-note { margin-top: 6px; padding: 5px 7px; }
        .handover-signature { margin-top: 6px; padding: 5px 7px; }
        .handover-delivery-date { min-height: 16mm; }
        .handover-delivery-date .signature-date { margin-top: 7px; }
        .signature-grid { margin-top: 6px; gap: 6px; }
        .signature { min-height: 16mm; padding: 4px 5px; }
        .signature-line { height: 7mm; margin: 0 7px 2px; }
        .signature-name { font-size: 8.5pt; }
        .signature-role { margin-top: 2px; font-size: 8.5pt; }
        .signature-date { font-size: 7.5pt; }
        .doc-section, .lead-letter, .handover-signature, .signature-grid { page-break-inside: avoid; break-inside: avoid; }
        .field, .money-card, .decision, .punch-box, .attachment-row, .payment-note, .signature { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="no-print">
    <button type="button" onclick="window.print()" class="bg-slate-800 text-white"><i class="fas fa-print mr-1"></i> พิมพ์เอกสาร</button>
    <button type="button" onclick="exportDynamicInspectionPDF()" class="bg-indigo-600 text-white"><i class="fas fa-file-pdf mr-1"></i> พิมพ์ / บันทึก PDF</button>
    <a href="add_inspection.php?project_id=<?= (int)$round['project_id'] ?>&milestone_id=<?= (int)$round['milestone_id'] ?>" class="border border-slate-300 bg-white text-slate-700">กลับหน้าตรวจงาน</a>
</div>

<main id="dynamic-inspection-document" class="dynamic-document">
    <article class="dynamic-page">
        <header class="doc-header">
            <div>
                <p class="doc-kicker">Procurement / Inspection</p>
                <h1>ใบส่งมอบและตรวจรับงาน</h1>
                <p class="doc-subtitle">Work Handover &amp; Service Acceptance</p>
            </div>
            <div class="doc-id">
                <div><strong>เลขที่เอกสาร:</strong> <?= inspection_h($documentNo) ?></div>
                <div><strong>วันที่ตรวจรับ:</strong> <?= $date($round['inspection_date'] ?? null) ?></div>
            </div>
        </header>

        <section class="lead-letter">
            <p><strong>เรียน</strong> กรรมการผู้จัดการบริษัท <strong><?= inspection_h($employerName ?: '-') ?></strong></p>
            <p>ตามสัญญาจ้างเลขที่ <strong><?= inspection_h($contractReferenceNo ?: '-') ?></strong> ลงวันที่ <strong><?= $date($project['contract_date'] ?? null) ?></strong></p>
            <p>ข้าพเจ้า <strong><?= inspection_h($supplierName ?: '-') ?></strong> ขอส่งมอบงานตาม <strong><?= inspection_h($milestone) ?></strong> เพื่อดำเนินการตรวจรับและเบิกจ่าย เป็นเงิน <strong><?= $money($project['milestone_amount'] ?? 0) ?> บาท</strong></p>
        </section>

        <section class="doc-section">
            <div class="section-heading"><span class="section-no">01</span><div><h2>ข้อมูลโครงการและคู่สัญญา</h2><p>ข้อมูลอ้างอิงสำหรับเอกสารตรวจรับงวดงาน</p></div></div>
            <div class="field-grid">
                <div class="field"><span class="field-label">ชื่อโครงการ</span><span class="field-value"><?= inspection_h($project['project_name'] ?? '-') ?></span></div>
                <div class="field"><span class="field-label">ผู้รับจ้าง</span><span class="field-value"><?= inspection_h($contractor ?: '-') ?></span></div>
                <div class="field"><span class="field-label">สถานที่ส่งมอบ / สถานที่ก่อสร้าง</span><span class="field-value"><?= inspection_h($project['work_location'] ?? '-') ?></span></div>
                <div class="field"><span class="field-label">เลขประจำตัวผู้เสียภาษี</span><span class="field-value"><?= inspection_h($project['tax_id'] ?? '-') ?></span></div>
                <div class="field"><span class="field-label">เลขที่สัญญา</span><span class="field-value"><?= inspection_h($contractReferenceNo ?: '-') ?></span></div>
                <div class="field"><span class="field-label">งวดงาน</span><span class="field-value"><?= inspection_h($milestoneNumber) ?></span></div>
                <div class="field full"><span class="field-label">รายละเอียดงวดงาน</span><span class="field-value"><?= inspection_h($milestoneDetail) ?></span></div>
            </div>
        </section>

        <section class="doc-section">
            <div class="section-heading"><span class="section-no">02</span><div><h2>มูลค่างานและเงื่อนไขการชำระเงิน</h2><p>ใช้ประกอบการออก Invoice / ใบกำกับภาษี</p></div></div>
            <div class="money-grid">
                <div class="money-card"><span class="money-label">มูลค่างานงวดนี้</span><span class="money-value"><?= $money($payment['milestone_amount']) ?></span></div>
                <div class="money-card"><span class="money-label">VAT</span><span class="money-value"><?= $money($payment['vat_amount']) ?></span></div>
                <div class="money-card"><span class="money-label">หัก ณ ที่จ่าย (<?= $percent($payment['wht_percent']) ?>%)</span><span class="money-value">- <?= $money($payment['wht_amount']) ?></span></div>
                <div class="money-card"><span class="money-label">Retention (<?= $percent($payment['retention_percent']) ?>%)</span><span class="money-value">- <?= $money($payment['retention_amount']) ?></span></div>
                <div class="money-card total"><span class="money-label">ยอดโอนสุทธิ</span><span class="money-value"><?= $money($payment['net_transfer_amount']) ?></span></div>
            </div>
            <div class="payment-note">ชำระภายใน <strong><?= (int)$payment['payment_days_after_acceptance'] ?> วัน</strong> หลังตรวจรับงาน · หัก ณ ที่จ่าย <strong><?= $percent($payment['wht_percent']) ?>% (<?= $money($payment['wht_amount']) ?> บาท)</strong> · หักเงินประกันผลงาน (Retention) <strong><?= $percent($payment['retention_percent']) ?>% (<?= $money($payment['retention_amount']) ?> บาท)</strong><?php if ($payment['other_deduction_amount'] > 0): ?> · หักอื่น ๆ <strong><?= $money($payment['other_deduction_amount']) ?> บาท</strong><?php endif; ?></div>
        </section>

        <section class="doc-section">
            <div class="section-heading"><span class="section-no">03</span><div><h2>ผลการตรวจรับและรายการแก้ไข</h2><p>สรุปผลจากการตรวจตามสัญญาจ้าง โดยไม่แสดงตาราง Checklist ในเอกสารฉบับนี้</p></div></div>
            <div class="decision-row decision-row-plain">
                <div class="decision <?= $inspectionOutcome === 'ผ่านการตรวจรับทั้งหมด' ? 'active' : '' ?>"><span class="checkbox <?= $inspectionOutcome === 'ผ่านการตรวจรับทั้งหมด' ? 'checked' : '' ?>"></span>ผ่านการตรวจรับทั้งหมด</div>
                <div class="decision <?= $hasIssues ? 'active' : '' ?>"><span class="checkbox <?= $hasIssues ? 'checked' : '' ?>"></span>มีเงื่อนไข / ต้องแก้ไข</div>
                <div class="decision"><span class="checkbox"></span>ไม่ผ่านการตรวจรับ</div>
            </div>
            <p class="inline-note">กำหนดแก้ไขภายใน <strong><?= $round['fix_within_days'] ? (int)$round['fix_within_days'] : '______' ?> วัน</strong></p>
            <div class="punch-box"><p><strong>รายละเอียด / รายการแก้ไข (Punch List):</strong> <?= $round['punch_list'] ? nl2br(inspection_h($round['punch_list'])) : 'ไม่มีรายการแก้ไขเพิ่มเติม' ?></p></div>
        </section>

        <section class="doc-section">
            <div class="section-heading"><span class="section-no">04</span><div><h2>เอกสารแนบ</h2><p>เลือกเอกสารที่ใช้ประกอบการตรวจรับงวดนี้</p></div></div>
            <div class="attachment-row">
                <?php foreach ($documentOptions as $documentKey => $attachmentLabel): ?>
                    <span class="attachment"><span class="checkbox <?= in_array($documentKey, $documentSelection['selected'], true) ? 'checked' : '' ?>"></span><?= inspection_h($attachmentLabel) ?></span>
                <?php endforeach; ?>
            </div>
            <?php if ($documentSelection['other_detail'] !== ''): ?>
                <div class="attachment-other-detail"><strong>รายละเอียดเอกสารอื่น ๆ:</strong> <?= nl2br(inspection_h($documentSelection['other_detail'])) ?></div>
            <?php endif; ?>
        </section>

        <section class="doc-section">
            <div class="section-heading"><span class="section-no">05</span><div><h2>การรับรองและอนุมัติ</h2><p><?= inspection_h($inspectionOutcome) ?> · <?= inspection_h($nextAction) ?></p></div></div>
            <div class="handover-signature">
                <div><strong>ลงชื่อผู้รับจ้าง / ผู้ส่งมอบงาน</strong><div class="signature-line"></div><span class="signature-name">( <?= inspection_h($contractor ?: '................................') ?> )</span></div>
                <div class="handover-delivery-date"><strong>วันที่ส่งมอบ</strong><span class="signature-date">วันที่ ______ / ______ / ______</span></div>
            </div>
            <div class="result-compare-table" style="display:none" aria-hidden="true">
                <div><strong>ผลตรวจแยกตามผู้ตรวจ:</strong> ผู้ตรวจ 1 และผู้ตรวจ 2 ถูกบันทึกแยกกันในระบบ</div>
                <?php foreach ($summary['items'] as $comparisonItem): ?>
                    <?php $firstResult = $comparisonItem['inspector_1'] ?? []; $secondResult = $comparisonItem['inspector_2'] ?? []; ?>
                    <div><?= inspection_h($firstResult['title'] ?? ($secondResult['title'] ?? '-')) ?> — ผู้ตรวจ 1: <?= inspection_h($resultStatusLabel($comparisonItem['inspector_1_status'])) ?> / ผู้ตรวจ 2: <?= inspection_h($resultStatusLabel($comparisonItem['inspector_2_status'])) ?></div>
                <?php endforeach; ?>
                <?php if ($procurementApproval && !empty($procurementApproval['reason'])): ?><div><strong>เหตุผลจัดซื้อ:</strong> <?= nl2br(inspection_h($procurementApproval['reason'])) ?></div><?php endif; ?>
            </div>
            <div class="signature-grid">
                <?php foreach (['inspector_1', 'inspector_2', 'procurement', 'md', 'gmacc'] as $step): $approval = $approvalFor($approvals, $step); $signaturePath = trim((string)($approval['signature_snapshot'] ?? '')); ?>
                    <div class="signature"><div class="signature-line"><?php if ($signaturePath !== ''): ?><img src="uploads/signatures/<?= rawurlencode(basename($signaturePath)) ?>" alt="ลายเซ็น <?= inspection_h($approvalLabels[$step]) ?>"><?php endif; ?></div><div class="signature-name">( <?= inspection_h($approvalName($approval)) ?> )</div><div class="signature-role"><?= inspection_h($approvalLabels[$step]) ?></div><div class="signature-date">วันที่ <?= $date($approval['created_at'] ?? null) ?></div></div>
                <?php endforeach; ?>
            </div>
        </section>
    </article>
</main>

<script>
function exportDynamicInspectionPDF() {
    // Native browser print embeds Sarabun and keeps Thai text selectable in the PDF.
    const originalTitle = document.title;
    document.title = 'Inspection_<?= str_pad((string)$round['id'], 5, '0', STR_PAD_LEFT) ?>';
    window.print();
    setTimeout(() => { document.title = originalTitle; }, 1000);
}
</script>
