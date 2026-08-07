<?php
require_once 'config.php';
require_once 'inspection_workflow.php';

$round_id = (int)($_GET['round_id'] ?? 0);
if ($round_id <= 0) {
    http_response_code(400);
    exit('ไม่พบรอบตรวจที่ระบุ');
}

$round = inspection_fetch_one($conn, 'SELECT * FROM inspection_rounds WHERE id = ? LIMIT 1', 'i', [$round_id]);
if (!$round) {
    http_response_code(404);
    exit('ไม่พบรอบตรวจนี้');
}

$context = inspection_load_context($conn, (int)$round['project_id'], (int)$round['milestone_id']);
$project = $context['project'] ?? [];
$checklist = $context['checklist'] ?? [];
$results = inspection_fetch_all($conn, 'SELECT * FROM inspection_round_results WHERE round_id = ? ORDER BY category, item_order, id', 'i', [$round_id]);
$approvals = inspection_fetch_all($conn, 'SELECT * FROM inspection_approvals WHERE round_id = ? ORDER BY created_at, id', 'i', [$round_id]);
$attachments = inspection_fetch_all($conn, 'SELECT * FROM inspection_attachments WHERE round_id = ? ORDER BY id', 'i', [$round_id]);
$summary = inspection_compare_results($results);

$resultByItem = [];
foreach ($results as $result) {
    $itemId = (int)$result['checklist_item_id'];
    $step = (string)($result['inspection_step'] ?? 'inspector_1');
    if (!isset($resultByItem[$itemId])) {
        $resultByItem[$itemId] = [];
    }
    $resultByItem[$itemId][$step] = $result;
}

$items = [];
foreach ($results as $result) {
    $itemId = (int)$result['checklist_item_id'];
    if (!isset($items[$itemId])) {
        $items[$itemId] = $result;
    }
}
$items = array_values($items);

$attachmentsByResult = [];
foreach ($attachments as $attachment) {
    $attachmentsByResult[(int)$attachment['result_id']][] = $attachment;
}

$resultLabel = static function ($status): string {
    return [
        'pass' => 'ผ่าน',
        'fail' => 'ไม่ผ่าน',
        'conditional_pass' => 'มีเงื่อนไข',
        'not_applicable' => 'ไม่เกี่ยวข้อง',
        '' => 'ยังไม่ระบุ',
    ][(string)$status] ?? 'ยังไม่ระบุ';
};
$resultClass = static function ($status): string {
    return [
        'pass' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'fail' => 'border-rose-200 bg-rose-50 text-rose-700',
        'conditional_pass' => 'border-amber-200 bg-amber-50 text-amber-800',
        'not_applicable' => 'border-slate-200 bg-slate-100 text-slate-700',
        '' => 'border-slate-200 bg-slate-50 text-slate-500',
    ][(string)$status] ?? 'border-slate-200 bg-slate-50 text-slate-500';
};
$dateLabel = static function ($value): string {
    if (!$value || $value === '0000-00-00') return '-';
    $time = strtotime((string)$value);
    return $time ? date('d/m/Y', $time) : (string)$value;
};
$approvalStepLabels = [
    'inspector_1' => 'ผู้ตรวจรับ 1',
    'inspector_2' => 'ผู้ตรวจรับ 2',
    'procurement' => 'จัดซื้อ',
    'md' => 'MD',
    'gmacc' => 'GMACC',
];
$approvalActionLabels = ['approve' => 'ยืนยัน', 'return' => 'ตีกลับ', 'reject' => 'ไม่อนุมัติ'];

include 'header.php';
?>

<main class="mx-auto max-w-6xl pb-20">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="mb-2 flex items-center gap-2 text-xs font-black uppercase tracking-[2px] text-indigo-500">
                <i class="fas fa-list-check" aria-hidden="true"></i> Inspection history
            </div>
            <h1 class="text-2xl font-black text-slate-900 md:text-3xl">Checklist รอบตรวจที่ <?= (int)$round['round_no'] ?></h1>
            <p class="mt-1 text-sm text-slate-500">ดูข้อมูลย้อนหลังแบบอ่านอย่างเดียว ไม่สามารถแก้ไขผลตรวจรอบนี้ได้</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="add_inspection.php?project_id=<?= (int)$round['project_id'] ?>&milestone_id=<?= (int)$round['milestone_id'] ?>" class="inline-flex items-center rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:bg-slate-200">
                <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i>กลับหน้าตรวจงาน
            </a>
            <?php if (!empty($results)): ?>
                <a href="view_inspection.php?round_id=<?= $round_id ?>&history=1" class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-black text-white transition hover:bg-indigo-700">
                    <i class="fas fa-file-lines mr-2" aria-hidden="true"></i>ดูเอกสาร A4
                </a>
            <?php endif; ?>
        </div>
    </div>

    <section class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold text-slate-400">โครงการ</p><p class="mt-1 font-black text-slate-800"><?= inspection_h($project['project_name'] ?? '-') ?></p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold text-slate-400">งวดงาน</p><p class="mt-1 font-black text-slate-800"><?= inspection_h($project['milestone_name'] ?? '-') ?></p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold text-slate-400">วันที่ตรวจ</p><p class="mt-1 font-black text-slate-800"><?= inspection_h($dateLabel($round['inspection_date'] ?? null)) ?></p></div>
        <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4"><p class="text-xs font-bold text-indigo-500">สถานะรอบตรวจ</p><p class="mt-1 font-black text-indigo-800"><?= inspection_h(inspection_status_label((string)($round['status'] ?? ''))) ?></p></div>
    </section>

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <span class="font-black text-slate-800">สรุปผล Checklist</span>
            <span class="rounded-full bg-slate-100 px-3 py-1 font-bold text-slate-600"><?= count($items) ?> รายการ</span>
            <span class="rounded-full bg-rose-50 px-3 py-1 font-bold text-rose-700">มีปัญหา <?= (int)($summary['issue_count'] ?? 0) ?> รายการ</span>
            <span class="rounded-full bg-emerald-50 px-3 py-1 font-bold text-emerald-700"><?= !empty($summary['passed']) ? 'ผลรวมผ่าน' : 'ยังไม่ผ่านทั้งหมด' ?></span>
        </div>
    </section>

    <?php if (!$items): ?>
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center font-bold text-amber-800">รอบนี้ยังไม่มีรายการ Checklist ที่บันทึกไว้</section>
    <?php else: ?>
        <section class="space-y-4" aria-label="รายการ Checklist ย้อนหลัง">
            <?php foreach ($items as $index => $item): ?>
                <?php
                $itemId = (int)$item['checklist_item_id'];
                $first = $resultByItem[$itemId]['inspector_1'] ?? [];
                $second = $resultByItem[$itemId]['inspector_2'] ?? [];
                $category = (string)($item['category'] ?? 'ทั่วไป');
                ?>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 md:flex-row md:items-start md:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-xs font-black text-indigo-700"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <div class="min-w-0">
                                <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-black text-indigo-700"><?= inspection_h($category) ?></span>
                                <h2 class="mt-2 text-base font-black text-slate-900"><?= inspection_h($item['title'] ?? '-') ?></h2>
                            </div>
                        </div>
                        <?php if (!empty($item['is_required'])): ?><span class="inline-flex shrink-0 rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-black text-rose-700">ข้อบังคับ</span><?php endif; ?>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-bold text-slate-400">รายละเอียดงาน</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700"><?= inspection_h($item['detail'] ?? '-') ?: '-' ?></p></div>
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-bold text-slate-400">อ้างอิงสัญญา / BOQ</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700"><?= inspection_h($item['contract_ref'] ?? '-') ?: '-' ?></p></div>
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-bold text-slate-400">เกณฑ์การผ่าน</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700"><?= inspection_h($item['acceptance_criteria'] ?? '-') ?: '-' ?></p></div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                        <?php foreach ([['label' => 'ผู้ตรวจรับ 1', 'result' => $first], ['label' => 'ผู้ตรวจรับ 2', 'result' => $second]] as $inspector): ?>
                            <?php $result = $inspector['result']; $resultStatus = (string)($result['result_status'] ?? ''); ?>
                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-black text-slate-800"><?= $inspector['label'] ?></p>
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-black <?= $resultClass($resultStatus) ?>"><?= inspection_h($resultLabel($resultStatus)) ?></span>
                                </div>
                                <div class="mt-3 space-y-2 text-sm">
                                    <div><span class="font-bold text-slate-400">หมายเหตุ:</span> <span class="whitespace-pre-line text-slate-700"><?= inspection_h($result['note'] ?? '-') ?: '-' ?></span></div>
                                    <div><span class="font-bold text-slate-400">ผู้รับผิดชอบแก้ไข:</span> <span class="text-slate-700"><?= inspection_h($result['responsible_person'] ?? '-') ?: '-' ?></span></div>
                                    <div><span class="font-bold text-slate-400">กำหนดแก้ไข:</span> <span class="text-slate-700"><?= inspection_h($dateLabel($result['due_date'] ?? null)) ?></span></div>
                                    <?php if (!empty($result['correction_note'])): ?><div class="rounded-lg bg-amber-50 p-2 text-amber-800"><span class="font-bold">หมายเหตุจากรอบก่อน:</span> <?= nl2br(inspection_h($result['correction_note'])) ?></div><?php endif; ?>
                                    <?php if (!empty($attachmentsByResult[(int)($result['id'] ?? 0)])): ?><div><span class="font-bold text-slate-400">หลักฐาน:</span> <?php foreach ($attachmentsByResult[(int)$result['id']] as $attachment): ?><a class="ml-1 inline-flex items-center gap-1 text-indigo-700 underline" href="uploads/inspections/<?= inspection_h($attachment['stored_name']) ?>" target="_blank" rel="noopener"><i class="fas fa-paperclip" aria-hidden="true"></i><?= inspection_h($attachment['original_name']) ?></a><?php endforeach; ?></div><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-base font-black text-slate-900"><i class="fas fa-list-check mr-2 text-amber-500" aria-hidden="true"></i>Punch List / รายการแก้ไข</h2>
            <p class="mt-3 whitespace-pre-line text-sm text-slate-700"><?= inspection_h($round['punch_list'] ?? '-') ?: '-' ?></p>
            <p class="mt-3 text-sm text-slate-500">กำหนดแก้ไขภายใน <span class="font-black text-slate-800"><?= (int)($round['fix_within_days'] ?? 0) ?> วัน</span></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-base font-black text-slate-900"><i class="fas fa-timeline mr-2 text-indigo-500" aria-hidden="true"></i>ประวัติการยืนยัน</h2>
            <?php if (!$approvals): ?>
                <p class="mt-3 text-sm text-slate-500">ยังไม่มีการยืนยันในรอบนี้</p>
            <?php else: ?>
                <div class="mt-3 space-y-2">
                    <?php foreach ($approvals as $approval): ?>
                        <div class="flex items-start justify-between gap-3 rounded-xl bg-slate-50 p-3 text-sm"><div><p class="font-black text-slate-800"><?= inspection_h($approvalStepLabels[$approval['step'] ?? ''] ?? 'ขั้นตอน') ?> · <?= inspection_h($approvalActionLabels[$approval['action'] ?? ''] ?? 'ดำเนินการ') ?></p><p class="mt-0.5 text-xs text-slate-500"><?= inspection_h($approval['user_name_snapshot'] ?? '-') ?></p></div><time class="shrink-0 text-xs text-slate-400"><?= inspection_h($dateLabel($approval['created_at'] ?? null)) ?></time></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
