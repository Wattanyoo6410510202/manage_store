<?php

require_once __DIR__ . '/stock_common.php';

$requestSupId = (int)($stock_actor['sup_id'] ?? 0);
if ($requestSupId <= 0) {
    http_response_code(403);
    die('กรุณาผูกบัญชีผู้ใช้กับบริษัท/หน่วยงานก่อนดูสถานะใบเบิก');
}

$listStmt = $conn->prepare(
    'SELECT w.*, s.company_name,
            COUNT(wi.id) item_count,
            COALESCE(SUM(wi.requested_quantity), 0) requested_total,
            COALESCE(SUM(wi.received_quantity), 0) received_total
     FROM stock_withdrawals w
     INNER JOIN suppliers s ON s.id = w.sup_id
     LEFT JOIN stock_withdrawal_items wi ON wi.withdrawal_id = w.id
     WHERE w.requester_id = ? AND w.sup_id = ?
     GROUP BY w.id
     ORDER BY w.id DESC
     LIMIT 150'
);
$listStmt->bind_param('ii', $stock_actor['id'], $requestSupId);
$listStmt->execute();
$withdrawals = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

include __DIR__ . '/header.php';
?>

<div class="mx-auto max-w-6xl space-y-6 p-4 md:p-8" data-stock-ui="procurement" data-stock-my-withdrawals>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-800">สถานะใบเบิกของฉัน</h1>
            <p class="mt-1 text-sm text-slate-500">ติดตามขั้นตอนการจ่ายสินค้าและยืนยันจำนวนที่ได้รับจริง</p>
        </div>
        <a href="stock_withdrawals.php" class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"><i class="fas fa-cart-plus mr-2"></i>สร้างใบเบิกใหม่</a>
    </div>

    <div class="space-y-3">
        <?php foreach ($withdrawals as $row): ?>
        <article data-stock-status-card class="overflow-hidden rounded-2xl border border-slate-300 bg-white shadow-sm">
            <header data-stock-status-card-header class="flex flex-col gap-4 border-b border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between md:px-5">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-black text-slate-950"><?= stock_e($row['doc_no']) ?></h2>
                        <span data-stock-withdrawal-status class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= stock_withdrawal_status_badge_class((string)$row['status']) ?>"><?= stock_e(stock_status_label((string)$row['status'])) ?></span>
                    </div>
                    <p class="mt-1 text-xs font-medium text-slate-600"><i class="far fa-calendar-alt mr-1.5 text-slate-400"></i><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?> <span class="mx-1 text-slate-300">·</span> <?= stock_e(supplier_display_name($row['company_name'])) ?></p>
                </div>
                <a href="stock_withdrawal_view.php?id=<?= (int)$row['id'] ?>" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"><i class="fas fa-arrow-right mr-2"></i>ดูรายละเอียด</a>
            </header>
            <div data-stock-status-card-body class="px-4 py-4 md:px-5 md:py-5">
                <div>
                    <div class="text-xs font-semibold text-slate-500">วัตถุประสงค์การเบิก</div>
                    <p class="mt-1 text-sm font-medium leading-6 text-slate-800"><?= stock_e($row['purpose']) ?></p>
                </div>
                <div data-stock-status-card-metrics class="mt-4 grid grid-cols-3 overflow-hidden rounded-xl border border-slate-200 bg-slate-50 text-center">
                    <div class="px-2 py-3 sm:px-3"><div class="text-xs font-medium text-slate-500">รายการ</div><div class="mt-1 text-base font-black text-slate-900"><?= (int)$row['item_count'] ?></div></div>
                    <div class="border-x border-slate-200 px-2 py-3 sm:px-3"><div class="text-xs font-medium text-slate-500">ขอเบิก</div><div class="mt-1 text-base font-black text-slate-900"><?= number_format((float)$row['requested_total'], 2) ?></div></div>
                    <div class="px-2 py-3 sm:px-3"><div class="text-xs font-medium text-slate-500">รับแล้ว</div><div class="mt-1 text-base font-black text-slate-900"><?= number_format((float)$row['received_total'], 2) ?></div></div>
                </div>
            </div>
        </article>
        <?php endforeach; ?>

        <?php if (!$withdrawals): ?>
        <div class="rounded-2xl border border-slate-100 bg-white px-5 py-12 text-center text-slate-500 shadow-sm">
            <i class="fas fa-clipboard-list mb-3 block text-3xl text-slate-300"></i>
            <p class="font-semibold text-slate-700">ยังไม่มีใบเบิกสินค้า</p>
            <p class="mt-1 text-sm">เมื่อสร้างใบเบิกแล้ว สถานะจะแสดงในหน้านี้</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
