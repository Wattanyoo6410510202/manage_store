<?php
// หน้าเซ็นอนุมัติใบขอซื้อผ่านลิงก์ (?t=token) — ไม่ต้อง login
// แสดงรายละเอียดทั้งหมด: เอกสาร PR, ใบเทียบราคา, ไฟล์แนบ, ลำดับผู้ลงนาม แล้วเซ็นได้เฉพาะช่องของลิงก์นี้เมื่อถึงคิว
require_once 'config.php';
require_once __DIR__ . '/pr_sign_lib.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

const PRS_FONTS = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">';

$token = (string)($_GET['t'] ?? '');
try {
    $link = pr_sign_by_token($conn, $token);
} catch (Throwable $e) {
    $link = null;
}
if (!$link) {
    http_response_code(410);
    ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>ลิงก์ใช้ไม่ได้</title>
    <?= PRS_FONTS ?>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: #f4f4f2; color: #111827; font: 15px/1.6 'IBM Plex Sans Thai', sans-serif; }
        main { max-width: 400px; }
        .mark { width: 40px; height: 40px; border: 1.5px solid #d1d5db; border-radius: 50%; display: grid; place-items: center; color: #6b7280; margin-bottom: 20px; }
        h1 { font-size: 20px; font-weight: 600; margin: 0 0 8px; letter-spacing: -.01em; }
        p { margin: 0; color: #6b7280; }
    </style>
</head>
<body>
<main>
    <div class="mark"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></div>
    <h1>ลิงก์นี้ใช้ไม่ได้แล้ว</h1>
    <p>ลิงก์อาจหมดอายุ ถูกยกเลิก มีการออกลิงก์ใหม่แทน หรือเอกสารนี้ลงนามไปแล้ว หากยังต้องลงนาม โปรดขอลิงก์ใหม่จากผู้ส่ง</p>
</main>
</body>
</html>
<?php
    exit;
}

$id = (int)$link['pr_id'];
require __DIR__ . '/partials/pr_document_data.php';
if (!$data) {
    http_response_code(404);
    exit('ไม่พบใบขอซื้อ');
}
$pr_full = pr_sign_fetch_pr($conn, $id);
$signers = pr_signers($conn, $id);
$me = null;
$my_no = 0;
foreach ($signers as $i => $s) {
    if ((int)$s['id'] === (int)$link['id']) {
        $me = $s;
        $my_no = $i + 1;
    }
}
$block_reason = $me ? pr_sign_block_reason($pr_full, $signers, $me) : 'ไม่พบผู้ลงนาม';
$can_sign = $block_reason === null;
$saved_sig = $can_sign ? pr_sign_saved_signature($conn, (int)$link['user_id']) : null;
$is_stage = !empty($me['stage']);

// ไฟล์แนบ
$e = 'pr_sign_e';
$attachments = [];
$add_file = function (string $label, string $dir, ?string $file, string $note = '') use (&$attachments) {
    $name = basename((string)$file);
    if ($name === '' || !is_file(__DIR__ . '/' . $dir . $name)) return;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $attachments[] = ['label' => $label, 'note' => $note, 'url' => $dir . $name, 'ext' => $ext,
        'image' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
        'size' => filesize(__DIR__ . '/' . $dir . $name)];
};
$add_file('ไฟล์แนบ 1', 'uploads/pr/', $data['attachment_1'] ?? null);
$add_file('ไฟล์แนบ 2', 'uploads/pr/', $data['attachment_2'] ?? null);
$add_file('สลิปชำระเงิน', 'uploads/payments/', $data['payment_slip'] ?? null);
require_once __DIR__ . '/pr_quotes_lib.php';
$quotes = pr_quotes_load($conn, $id);
foreach ($quotes as $q) {
    $add_file('ใบเสนอราคา — ' . ($q['store_name'] ?: 'ร้าน #' . $q['store_id']), 'uploads/pr_quotes/', $q['attachment'] ?? null,
        (int)$q['is_selected'] === 1 ? 'ร้านที่เลือก' : '');
}
$fmt_size = fn(int $b) => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';

$thai_date = function (?string $dt, bool $time = false): string {
    if (!$dt) return '';
    $m = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $ts = strtotime($dt);
    return date('j', $ts) . ' ' . $m[(int)date('n', $ts)] . ' ' . ((int)date('Y', $ts) + 543) . ($time ? ' · ' . date('H:i', $ts) . ' น.' : '');
};

$company = supplier_display_name($data['my_company'] ?? '');
$status_text = ['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ'][$data['status']] ?? $data['status'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f4f4f2">
    <title>ลงนาม <?= $e($data['doc_no']) ?></title>
    <link rel="icon" type="image/png" href="shopping-cart.png">
    <?= PRS_FONTS ?>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f4f2;
            --surface: #ffffff;
            --ink: #111827;
            --muted: #6b7280;
            --faint: #9ca3af;
            --line: #e5e7eb;
            --accent: #1e3a8a;        /* สีหมึกลายเซ็น — สีเน้นสีเดียวของหน้า */
            --accent-soft: #eef2fb;
            --ok: #15803d;
            --no: #b91c1c;
            --wait: #b45309;
            --ui: 'IBM Plex Sans Thai', 'Sarabun', sans-serif;
        }
        * { box-sizing: border-box; }
        body.prs { margin: 0; padding: 0 0 112px; background: var(--bg); color: var(--ink); font: 15px/1.6 var(--ui); -webkit-font-smoothing: antialiased; }
        .num { font-variant-numeric: tabular-nums; }
        .wrap { max-width: 880px; margin: 0 auto; padding: 0 20px; }

        /* หัวหน้า */
        .masthead { border-bottom: 1px solid var(--line); background: var(--surface); }
        .masthead .wrap { display: flex; justify-content: space-between; align-items: center; height: 52px; gap: 16px; font-size: 13px; color: var(--muted); }
        .masthead strong { color: var(--ink); font-weight: 600; }
        .brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .brand img { width: 26px; height: 26px; object-fit: contain; }
        .brand span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* สรุปคำขอ */
        .intro { padding: 36px 0 28px; display: grid; grid-template-columns: 1fr auto; gap: 8px 32px; align-items: end; }
        .eyebrow { font-size: 13px; color: var(--muted); margin: 0 0 4px; }
        h1 { font-size: 30px; line-height: 1.2; font-weight: 600; letter-spacing: -.02em; margin: 0; }
        .meta { margin: 8px 0 0; color: var(--muted); font-size: 14px; }
        .meta b { color: var(--ink); font-weight: 500; }
        .meta .sep { margin: 0 8px; color: var(--line); }
        .total { text-align: right; }
        .total .lbl { font-size: 13px; color: var(--muted); }
        .total .amt { font-size: 30px; font-weight: 600; letter-spacing: -.02em; line-height: 1.2; }
        .total .amt small { font-size: 16px; font-weight: 500; color: var(--muted); margin-left: 4px; }

        .task { border: 1px solid var(--line); background: var(--surface); padding: 14px 18px; border-radius: 8px; font-size: 14px; color: var(--muted); }
        .task b { color: var(--ink); font-weight: 600; }
        .task .st { display: block; font-size: 12px; font-weight: 600; letter-spacing: .02em; margin-bottom: 2px; color: var(--accent); }
        .task.waiting .st { color: var(--wait); }

        /* เมนูส่วน */
        .sections { position: sticky; top: 0; z-index: 20; background: var(--bg); border-bottom: 1px solid var(--line); margin-top: 28px; }
        .sections nav { display: flex; gap: 24px; overflow-x: auto; scrollbar-width: none; }
        .sections nav::-webkit-scrollbar { display: none; }
        .sections a { padding: 12px 0; font-size: 14px; color: var(--muted); text-decoration: none; white-space: nowrap; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .sections a:hover { color: var(--ink); }
        .sections a.on { color: var(--ink); border-bottom-color: var(--ink); font-weight: 500; }
        .sections a span { color: var(--faint); margin-left: 4px; }

        section { padding: 40px 0 8px; scroll-margin-top: 48px; }
        h2 { font-size: 15px; font-weight: 600; margin: 0 0 4px; }
        .sub { font-size: 13px; color: var(--muted); margin: 0 0 18px; }

        /* กระดาษเอกสาร ย่อให้พอดีจอ */
        .paper-fit { overflow: hidden; background: var(--surface); box-shadow: 0 0 0 1px rgba(17,24,39,.06), 0 12px 32px -18px rgba(17,24,39,.25); }
        .paper-fit > .scaler { transform-origin: top left; }
        .paper-fit .page-container, .paper-fit .prq-paper { margin: 0 !important; box-shadow: none !important; }
        .paper-fit .prq-paper, .paper-fit .page-container { min-height: 0 !important; height: auto !important; }
        .paper-fit.actual { overflow-x: auto; height: auto !important; -webkit-overflow-scrolling: touch; }
        .paper-fit.actual > .scaler { transform: none !important; }
        .open-full { margin-top: 10px; }

        /* ไฟล์แนบ */
        .files { list-style: none; margin: 0; padding: 0; background: var(--surface); border: 1px solid var(--line); border-radius: 8px; }
        .files li + li { border-top: 1px solid var(--line); }
        .files a { display: flex; align-items: center; gap: 14px; padding: 12px 16px; color: inherit; text-decoration: none; }
        .files a:hover { background: #fafaf9; }
        .ftype { width: 44px; height: 44px; flex-shrink: 0; border: 1px solid var(--line); border-radius: 6px; display: grid; place-items: center; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; overflow: hidden; background: #fafaf9; }
        .ftype img { width: 100%; height: 100%; object-fit: cover; }
        .ftype.pdf { color: var(--no); }
        .fname { flex: 1; min-width: 0; font-size: 14px; }
        .fname small { display: block; font-size: 12px; color: var(--muted); }
        .fname .tag { color: var(--accent); font-weight: 500; }
        .fopen { font-size: 13px; color: var(--muted); white-space: nowrap; }
        .empty { color: var(--muted); font-size: 14px; padding: 16px 0; margin: 0; }

        /* ลำดับผู้ลงนาม */
        .chain { list-style: none; margin: 0; padding: 0; position: relative; }
        .chain li { position: relative; display: grid; grid-template-columns: 28px 1fr auto; gap: 14px; padding: 0 0 22px; }
        .chain li:not(:last-child)::before { content: ''; position: absolute; left: 13.5px; top: 30px; bottom: 2px; width: 1px; background: var(--line); }
        .chain li.done:not(:last-child)::before { background: var(--accent); opacity: .35; }
        .node { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-size: 12px; font-weight: 600; border: 1px solid var(--line); background: var(--surface); color: var(--muted); }
        .done .node { background: var(--accent); border-color: var(--accent); color: #fff; }
        .rejected .node { background: var(--no); border-color: var(--no); color: #fff; }
        .current .node { border: 2px solid var(--accent); color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
        .who { font-size: 14px; padding-top: 3px; min-width: 0; }
        .who b { font-weight: 600; }
        .who .you { font-size: 12px; color: var(--accent); font-weight: 500; margin-left: 6px; }
        .who small { display: block; font-size: 13px; color: var(--muted); }
        .who q { display: block; font-size: 13px; color: var(--ink); margin-top: 4px; quotes: '“' '”'; }
        .state { font-size: 12px; font-weight: 500; padding-top: 6px; text-align: right; white-space: nowrap; color: var(--faint); }
        .done .state { color: var(--ok); }
        .rejected .state { color: var(--no); }
        .current .state { color: var(--accent); }
        .state img { display: block; max-height: 34px; max-width: 110px; margin: 4px 0 0 auto; }

        /* แถบลงนามด้านล่าง */
        .signbar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; background: rgba(255,255,255,.94); backdrop-filter: saturate(1.4) blur(10px); -webkit-backdrop-filter: saturate(1.4) blur(10px); border-top: 1px solid var(--line); }
        .signbar .wrap { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-top: 12px; padding-bottom: calc(12px + env(safe-area-inset-bottom)); }
        .signbar .as { font-size: 13px; color: var(--muted); min-width: 0; line-height: 1.4; }
        .signbar .as b { display: block; color: var(--ink); font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .btn { appearance: none; border: 1px solid transparent; border-radius: 8px; padding: 11px 22px; font: 600 15px var(--ui); cursor: pointer; white-space: nowrap; transition: background .15s, opacity .15s; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: #172e6e; }
        .btn-quiet { background: transparent; color: var(--muted); border-color: var(--line); }
        .btn-quiet:hover { color: var(--ink); background: #fafaf9; }
        .btn:disabled { opacity: .55; cursor: progress; }
        .btn:focus-visible, .seg label:focus-within, .sections a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

        /* แผ่นลงนาม */
        .sheet-bg { position: fixed; inset: 0; z-index: 100; background: rgba(17,24,39,.45); display: flex; align-items: center; justify-content: center; padding: 16px; }
        .hidden { display: none !important; }
        .sheet { background: var(--surface); width: 520px; max-width: 100%; max-height: 94dvh; overflow-y: auto; border-radius: 12px; box-shadow: 0 24px 60px -20px rgba(17,24,39,.45); }
        .sheet header { padding: 20px 22px 4px; display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .sheet header h3 { margin: 0; font-size: 17px; font-weight: 600; }
        .sheet header p { margin: 2px 0 0; font-size: 13px; color: var(--muted); }
        .x { border: 0; background: none; color: var(--muted); cursor: pointer; padding: 4px; border-radius: 6px; line-height: 0; }
        .x:hover { color: var(--ink); background: #f3f4f6; }
        .sheet .body { padding: 14px 22px 4px; }
        .field-lbl { display: flex; justify-content: space-between; align-items: baseline; font-size: 13px; font-weight: 500; margin: 16px 0 8px; }
        .field-lbl:first-child { margin-top: 0; }
        .seg { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid var(--line); border-radius: 8px; padding: 3px; gap: 3px; background: #fafaf9; }
        .seg label { position: relative; text-align: center; padding: 9px; border-radius: 6px; font-size: 14px; font-weight: 500; color: var(--muted); cursor: pointer; }
        .seg input { position: absolute; opacity: 0; pointer-events: none; }
        .seg label:has(input:checked) { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(17,24,39,.08), 0 0 0 1px var(--line); }
        .seg label.reject:has(input:checked) { color: var(--no); }
        .linkbtn { border: 0; background: none; padding: 0; font: 500 13px var(--ui); color: var(--accent); cursor: pointer; }
        .pad { position: relative; border: 1px solid var(--line); border-radius: 8px; background: #fff; }
        #signPad { display: block; width: 100%; height: 200px; touch-action: none; cursor: crosshair; }
        .baseline { position: absolute; left: 24px; right: 24px; bottom: 46px; border-bottom: 1px solid #d1d5db; pointer-events: none; }
        .baseline::before { content: '×'; position: absolute; left: -2px; bottom: 2px; font-size: 16px; color: var(--faint); }
        .pad-tools { display: flex; justify-content: space-between; font-size: 12px; color: var(--faint); margin-top: 6px; }
        .saved { border: 1px solid var(--line); border-radius: 8px; height: 200px; display: grid; place-items: center; background: #fff; }
        .saved img { max-height: 120px; max-width: 80%; }
        textarea { width: 100%; border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; font: 15px/1.5 var(--ui); color: var(--ink); resize: vertical; }
        textarea:focus { outline: 2px solid var(--accent); outline-offset: -1px; border-color: transparent; }
        .chk { display: flex; gap: 8px; align-items: center; font-size: 14px; color: var(--muted); margin-top: 10px; }
        .chk input { accent-color: var(--accent); width: 16px; height: 16px; }
        .err { color: var(--no); font-size: 13px; margin: 10px 0 0; }
        .sheet footer { display: flex; justify-content: flex-end; gap: 10px; padding: 18px 22px 20px; }
        .legal { font-size: 12px; color: var(--faint); padding: 0 22px; margin: 6px 0 0; }

        /* หลังลงนาม */
        .finished { padding: 72px 0 40px; max-width: 440px; }
        .finished .mark { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; margin-bottom: 22px; color: #fff; background: var(--ok); }
        .finished.rej .mark { background: var(--no); }
        .finished h1 { font-size: 24px; }
        .finished p { color: var(--muted); margin: 8px 0 0; }
        .finished .sig { margin: 28px 0 0; padding-bottom: 6px; border-bottom: 1px solid #d1d5db; height: 84px; display: flex; align-items: flex-end; }
        .finished .sig img { max-height: 76px; max-width: 70%; }
        .finished .sig-cap { font-size: 13px; color: var(--muted); margin-top: 6px; }

        @media (max-width: 640px) {
            .wrap { padding: 0 16px; }
            .intro { grid-template-columns: 1fr; padding: 28px 0 22px; }
            h1, .total .amt { font-size: 26px; }
            .total { text-align: left; margin-top: 8px; }
            .meta .sep { margin: 0 5px; }
            .sections nav { gap: 20px; }
            .sheet-bg { padding: 0; align-items: flex-end; }
            .sheet { width: 100%; border-radius: 16px 16px 0 0; max-height: 96dvh; }
            .sheet footer { padding-bottom: calc(18px + env(safe-area-inset-bottom)); }
            .sheet footer .btn { flex: 1; }
            #signPad, .saved { height: 220px; }
            textarea { font-size: 16px; }
            .masthead .doc-no { display: none; }
        }
        @media print {
            .signbar, .sections, .open-full { display: none !important; }
            body.prs { background: #fff; padding: 0; }
        }
    </style>
</head>
<body class="prs">
<header class="masthead">
    <div class="wrap">
        <div class="brand">
            <?php if (!empty($logo_path) && is_file(__DIR__ . '/' . $logo_path)): ?><img src="<?= $e($logo_path) ?>" alt=""><?php endif; ?>
            <span><strong><?= $e($company ?: 'ใบขอซื้อ') ?></strong></span>
        </div>
        <span class="doc-no num"><?= $e($data['doc_no']) ?></span>
    </div>
</header>

<main id="main">
    <div class="wrap">
        <div class="intro">
            <div>
                <p class="eyebrow">ขออนุมัติใบขอซื้อ · <?= $e($status_text) ?></p>
                <h1 class="num"><?= $e($data['doc_no']) ?></h1>
                <p class="meta">
                    ผู้ขอ <b><?= $e($data['creator_name']) ?></b><span class="sep">|</span><?= $e($thai_date($data['doc_date'])) ?>
                    <?php if (!empty($data['store_name'])): ?><span class="sep">|</span>ร้าน <b><?= $e($data['store_name']) ?></b><?php endif; ?>
                </p>
            </div>
            <div class="total">
                <div class="lbl">ยอดสุทธิ</div>
                <div class="amt num"><?= number_format((float)$data['grand_total'], 2) ?><small>บาท</small></div>
            </div>
        </div>

        <?php if ($can_sign): ?>
            <div class="task">
                <span class="st">ถึงคิวคุณลงนาม</span>
                <b><?= $e($link['user_name']) ?></b> ลงนามในฐานะ <b><?= $e($me['label']) ?></b> — ลำดับที่ <?= $my_no ?> จาก <?= count($signers) ?>
                กรุณาตรวจเอกสาร ใบเทียบราคา และไฟล์แนบด้านล่างก่อนลงนาม
            </div>
        <?php else: ?>
            <div class="task waiting">
                <span class="st">ยังลงนามไม่ได้</span>
                <b><?= $e($link['user_name']) ?></b> — <?= $e($block_reason) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="sections">
        <div class="wrap">
            <nav aria-label="ส่วนของเอกสาร">
                <a href="#doc" class="on">ใบขอซื้อ</a>
                <a href="#quotes">ใบเทียบราคา<?php if ($quotes): ?><span class="num"><?= count($quotes) ?> ร้าน</span><?php endif; ?></a>
                <a href="#files">ไฟล์แนบ<span class="num"><?= count($attachments) ?></span></a>
                <a href="#signers">ผู้ลงนาม<span class="num"><?= count($signers) ?></span></a>
            </nav>
        </div>
    </div>

    <div class="wrap">
        <section id="doc">
            <h2>ใบขอซื้อ</h2>
            <p class="sub">รายการสินค้า ยอดเงิน และงบประมาณตามที่ผู้ขอบันทึก</p>
            <div class="paper-fit" data-width="794"><div class="scaler"><?php include __DIR__ . '/partials/pr_document.php'; ?></div></div>
            <button type="button" class="linkbtn open-full hidden" onclick="toggleActual(this)">ดูขนาดจริง</button>
        </section>

        <section id="quotes">
            <h2>ใบเทียบราคา</h2>
            <?php ob_start(); $prq_standalone = true; include __DIR__ . '/pr_quotes_view.php'; $prq_html = trim(ob_get_clean()); ?>
            <?php if ($prq_html !== ''): ?>
                <p class="sub">เทียบราคา <?= count($quotes) ?> ร้าน · ช่องที่แรเงาคือร้านที่เลือก ตัวหนาคือราคาต่ำสุดของแต่ละรายการ</p>
                <div class="paper-fit" data-width="1123"><div class="scaler"><?= $prq_html ?></div></div>
                <button type="button" class="linkbtn open-full hidden" onclick="toggleActual(this)">ดูขนาดจริง</button>
            <?php else: ?>
                <p class="empty">ใบขอซื้อนี้ไม่มีการเทียบราคา</p>
            <?php endif; ?>
        </section>

        <section id="files">
            <h2>ไฟล์แนบ</h2>
            <?php if ($attachments): ?>
                <p class="sub">แตะเพื่อเปิดไฟล์ในแท็บใหม่</p>
                <ul class="files">
                    <?php foreach ($attachments as $a): ?>
                        <li>
                            <a href="<?= $e($a['url']) ?>" target="_blank" rel="noopener">
                                <span class="ftype <?= $a['ext'] === 'pdf' ? 'pdf' : '' ?>">
                                    <?php if ($a['image']): ?><img src="<?= $e($a['url']) ?>" alt="" loading="lazy"><?php else: ?><?= $e(substr($a['ext'], 0, 4) ?: 'file') ?><?php endif; ?>
                                </span>
                                <span class="fname"><?= $e($a['label']) ?>
                                    <small><?php if ($a['note']): ?><span class="tag"><?= $e($a['note']) ?></span> · <?php endif; ?><?= strtoupper($e($a['ext'])) ?> · <span class="num"><?= $fmt_size((int)$a['size']) ?></span></small>
                                </span>
                                <span class="fopen">เปิด ↗</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty">ไม่มีไฟล์แนบ</p>
            <?php endif; ?>
        </section>

        <section id="signers">
            <h2>ลำดับผู้ลงนาม</h2>
            <p class="sub">ลงนามทีละคนตามลำดับ คนถัดไปจะลงนามได้เมื่อคนก่อนหน้าลงนามแล้ว</p>
            <ol class="chain">
                <?php foreach ($signers as $i => $s):
                    $mine = (int)$s['id'] === (int)$link['id'];
                    $rej = $s['signed_at'] && $s['decision'] === 'rejected';
                    $done = !$rej && pr_sign_row_done($s, $pr_full);
                    $turn = !$done && !$rej && pr_sign_block_reason($pr_full, $signers, $s) === null;
                    $cls = $rej ? 'rejected' : ($done ? 'done' : ($turn ? 'current' : '')); ?>
                    <li class="<?= $cls ?>">
                        <span class="node num"><?php if ($done): ?><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><?php else: ?><?= $i + 1 ?><?php endif; ?></span>
                        <div class="who">
                            <b><?= $e($s['user_name']) ?></b><?php if ($mine): ?><span class="you">คุณ</span><?php endif; ?>
                            <small><?= $e($s['label']) ?><?php if ($s['signed_at']): ?> · <?= $e($thai_date($s['signed_at'], true)) ?><?php endif; ?></small>
                            <?php if (!empty($s['comment'])): ?><q><?= $e($s['comment']) ?></q><?php endif; ?>
                        </div>
                        <div class="state">
                            <?= $rej ? 'ไม่อนุมัติ' : ($done ? ($s['signed_at'] ? 'ลงนามแล้ว' : 'อนุมัติในระบบแล้ว') : ($turn ? 'ถึงคิว' : 'รอ')) ?>
                            <?php if (!empty($s['signature_path'])): ?><img src="<?= $e($s['signature_path']) ?>" alt="ลายเซ็น <?= $e($s['user_name']) ?>"><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>
</main>

<?php if ($can_sign): ?>
<div class="signbar" id="signbar">
    <div class="wrap">
        <div class="as"><b><?= $e($link['user_name']) ?></b>ลงนามในฐานะ<?= $e($me['label']) ?></div>
        <button type="button" class="btn btn-primary" onclick="openSign()">ลงนาม</button>
    </div>
</div>

<div id="sheet" class="sheet-bg hidden" onclick="if (event.target === this) closeSign()">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sheetTitle">
        <header>
            <div>
                <h3 id="sheetTitle">ลงนาม <?= $e($data['doc_no']) ?></h3>
                <p><?= $e($link['user_name']) ?> · <?= $e($me['label']) ?></p>
            </div>
            <button type="button" class="x" onclick="closeSign()" aria-label="ปิด"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
        </header>
        <div class="body">
            <div class="field-lbl">ผลการพิจารณา</div>
            <div class="seg" role="radiogroup">
                <label><input type="radio" name="decision" value="approved" checked><?= $is_stage ? 'อนุมัติ' : 'เห็นชอบ' ?></label>
                <label class="reject"><input type="radio" name="decision" value="rejected">ไม่อนุมัติ</label>
            </div>

            <div class="field-lbl">
                <span>ลายเซ็น</span>
                <?php if ($saved_sig): ?><button type="button" class="linkbtn" id="modeToggle" onclick="sigMode(mode === 'saved' ? 'draw' : 'saved')">วาดลายเซ็นใหม่</button><?php endif; ?>
            </div>
            <?php if ($saved_sig): ?>
                <div class="saved" id="savedBox"><img src="<?= $e($saved_sig) ?>" alt="ลายเซ็นที่บันทึกไว้"></div>
            <?php endif; ?>
            <div id="drawBox" class="<?= $saved_sig ? 'hidden' : '' ?>">
                <div class="pad">
                    <canvas id="signPad" aria-label="พื้นที่วาดลายเซ็น"></canvas>
                    <div class="baseline"></div>
                </div>
                <div class="pad-tools"><span>ใช้นิ้วหรือเมาส์เซ็นเหนือเส้น</span><button type="button" class="linkbtn" onclick="clearPad()">ล้าง</button></div>
            </div>

            <div class="field-lbl"><label for="signComment" id="commentLbl">หมายเหตุ <span style="color:var(--faint);font-weight:400">(ถ้ามี)</span></label></div>
            <textarea id="signComment" rows="2"></textarea>
            <label class="chk hidden" id="resubmitBox"><input type="checkbox" id="allowResubmit" checked> ให้ผู้ขอแก้ไขและยื่นใหม่ได้</label>
            <p class="err hidden" id="signError" role="alert"></p>
        </div>
        <p class="legal">เมื่อยืนยัน ระบบจะบันทึกลายเซ็น วันเวลา และผลการพิจารณาลงในใบขอซื้อนี้<?= $is_stage ? ' และนับเป็นการอนุมัติขั้น ' . $e($me['label']) : '' ?></p>
        <footer>
            <button type="button" class="btn btn-quiet" onclick="closeSign()">ยกเลิก</button>
            <button type="button" class="btn btn-primary" id="signSave" onclick="submitSign()">ยืนยันการลงนาม</button>
        </footer>
    </div>
</div>
<?php endif; ?>

<script>
// ย่อกระดาษ A4 ให้พอดีความกว้าง
function fitPapers() {
    document.querySelectorAll('.paper-fit').forEach(box => {
        const scaler = box.firstElementChild;
        const w = +box.dataset.width;
        const s = Math.min(1, box.clientWidth / w);
        box.nextElementSibling?.classList.toggle('hidden', s >= 0.98 && !box.classList.contains('actual'));
        scaler.style.width = w + 'px';
        if (box.classList.contains('actual')) return;
        scaler.style.transform = 'scale(' + s + ')';
        box.style.height = Math.ceil(scaler.scrollHeight * s) + 'px';
    });
}
addEventListener('resize', fitPapers);
addEventListener('load', fitPapers);
document.fonts && document.fonts.ready.then(fitPapers);
fitPapers();

// ดูกระดาษขนาดจริง (เลื่อนซ้าย-ขวาได้) / ย่อให้พอดีจอ
function toggleActual(btn) {
    const box = btn.previousElementSibling;
    const on = box.classList.toggle('actual');
    btn.textContent = on ? 'ย่อให้พอดีจอ' : 'ดูขนาดจริง';
    if (!on) fitPapers();
}

// ไฮไลต์เมนูตามส่วนที่กำลังอ่าน
(function () {
    const links = [...document.querySelectorAll('.sections a')];
    const secs = [...document.querySelectorAll('section[id]')];
    function update() {
        let cur = secs[0];
        secs.forEach(sec => { if (sec.getBoundingClientRect().top < 120) cur = sec; });
        if (innerHeight + scrollY >= document.body.scrollHeight - 4) cur = secs[secs.length - 1];
        links.forEach(a => a.classList.toggle('on', a.getAttribute('href') === '#' + cur.id));
    }
    addEventListener('scroll', update, {passive: true});
    update();
})();

<?php if ($can_sign): ?>
const TOKEN = <?= json_encode($token) ?>;
const DOC_NO = <?= json_encode((string)$data['doc_no'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const SIGNER = <?= json_encode($link['user_name'] . ' · ' . $me['label'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
let mode = <?= json_encode($saved_sig ? 'saved' : 'draw') ?>;
let hasInk = false;
const pad = document.getElementById('signPad');
const ctx = pad.getContext('2d');
let cssSize = {w: 1, h: 1};

function resizePad() {
    const r = window.devicePixelRatio || 1;
    cssSize = {w: pad.offsetWidth, h: pad.offsetHeight};
    pad.width = cssSize.w * r;
    pad.height = cssSize.h * r;
    ctx.setTransform(r, 0, 0, r, 0, 0);
    ctx.lineWidth = matchMedia('(pointer: coarse)').matches ? 2.6 : 2.1;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1e3a8a';
    hasInk = false;
}
function clearPad() { ctx.clearRect(0, 0, pad.width, pad.height); hasInk = false; }
function sigMode(m) {
    mode = m;
    document.getElementById('savedBox')?.classList.toggle('hidden', m !== 'saved');
    document.getElementById('drawBox').classList.toggle('hidden', m !== 'draw');
    const t = document.getElementById('modeToggle');
    if (t) t.textContent = m === 'saved' ? 'วาดลายเซ็นใหม่' : 'ใช้ลายเซ็นที่บันทึกไว้';
    if (m === 'draw') resizePad();
}
function openSign() {
    document.getElementById('sheet').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    if (mode === 'draw') resizePad();
}
function closeSign() {
    document.getElementById('sheet').classList.add('hidden');
    document.body.style.overflow = '';
}
document.querySelectorAll('input[name="decision"]').forEach(r => r.addEventListener('change', () => {
    const rej = document.querySelector('input[name="decision"]:checked').value === 'rejected';
    document.getElementById('resubmitBox').classList.toggle('hidden', !rej);
    document.getElementById('commentLbl').innerHTML = rej
        ? 'เหตุผลที่ไม่อนุมัติ <span style="color:var(--no);font-weight:400">*</span>'
        : 'หมายเหตุ <span style="color:var(--faint);font-weight:400">(ถ้ามี)</span>';
    document.getElementById('signSave').textContent = rej ? 'ยืนยันไม่อนุมัติ' : 'ยืนยันการลงนาม';
}));

let drawing = false, last = null;
const pt = e => { const b = pad.getBoundingClientRect(); return {x: (e.clientX - b.left) * cssSize.w / b.width, y: (e.clientY - b.top) * cssSize.h / b.height}; };
pad.addEventListener('pointerdown', e => {
    drawing = true; last = pt(e); pad.setPointerCapture(e.pointerId);
    ctx.beginPath(); ctx.arc(last.x, last.y, ctx.lineWidth / 2, 0, Math.PI * 2); ctx.fillStyle = ctx.strokeStyle; ctx.fill();
    hasInk = true;
});
pad.addEventListener('pointermove', e => {
    if (!drawing) return;
    const p = pt(e);
    ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
    last = p;
});
['pointerup', 'pointercancel', 'pointerleave'].forEach(ev => pad.addEventListener(ev, () => { drawing = false; }));

// ตัดขอบว่างรอบลายเซ็น
function trimmed() {
    const {width, height} = pad;
    const d = ctx.getImageData(0, 0, width, height).data;
    let x0 = width, y0 = height, x1 = -1, y1 = -1;
    for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
        if (d[(y * width + x) * 4 + 3] > 0) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; }
    }
    if (x1 < 0) return null;
    const m = 6;
    x0 = Math.max(0, x0 - m); y0 = Math.max(0, y0 - m); x1 = Math.min(width - 1, x1 + m); y1 = Math.min(height - 1, y1 + m);
    const out = document.createElement('canvas');
    out.width = x1 - x0 + 1; out.height = y1 - y0 + 1;
    out.getContext('2d').drawImage(pad, x0, y0, out.width, out.height, 0, 0, out.width, out.height);
    return out.toDataURL('image/png');
}
function showErr(msg) {
    const el = document.getElementById('signError');
    el.textContent = msg;
    el.classList.remove('hidden');
}
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

async function submitSign() {
    const decision = document.querySelector('input[name="decision"]:checked').value;
    const comment = document.getElementById('signComment').value.trim();
    if (decision === 'rejected' && !comment) { document.getElementById('signComment').focus(); return showErr('กรุณาระบุเหตุผลที่ไม่อนุมัติ'); }
    const signature = mode === 'saved' ? 'saved' : (hasInk ? trimmed() : null);
    if (!signature) return showErr('กรุณาเซ็นชื่อในกรอบก่อน');

    const fd = new FormData();
    fd.append('token', TOKEN);
    fd.append('signature', signature);
    fd.append('decision', decision);
    fd.append('comment', comment);
    if (decision === 'rejected' && document.getElementById('allowResubmit').checked) fd.append('allow_resubmit', '1');

    const btn = document.getElementById('signSave');
    const label = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'กำลังบันทึก…';
    try {
        const res = await fetch('api/pr_sign_submit.php', {method: 'POST', body: fd}).then(r => r.json());
        if (res.status === 'success') {
            closeSign();
            document.getElementById('signbar')?.remove();
            document.querySelector('.sections')?.remove();
            const rej = decision === 'rejected';
            const icon = rej
                ? '<path d="M7 7l10 10M17 7L7 17"/>'
                : '<path d="M5 12.5l4.5 4.5L19 7.5"/>';
            const now = new Date();
            document.getElementById('main').innerHTML =
                '<div class="wrap"><div class="finished' + (rej ? ' rej' : '') + '">'
                + '<div class="mark"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">' + icon + '</svg></div>'
                + '<h1>' + esc(res.msg) + '</h1>'
                + '<p>' + esc(DOC_NO) + ' · ' + now.toLocaleString('th-TH', {dateStyle: 'medium', timeStyle: 'short'}) + '</p>'
                + (res.signature ? '<div class="sig"><img src="' + esc(res.signature) + '" alt=""></div><div class="sig-cap">' + esc(SIGNER) + '</div>' : '')
                + '<p style="margin-top:28px">ปิดหน้านี้ได้เลย ลิงก์นี้จะใช้ไม่ได้อีก</p>'
                + '</div></div>';
            scrollTo(0, 0);
            return;
        }
        showErr(res.msg || 'บันทึกไม่สำเร็จ');
    } catch (e) {
        showErr('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ ลองใหม่อีกครั้ง');
    }
    btn.disabled = false;
    btn.textContent = label;
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSign(); });
<?php endif; ?>
</script>
</body>
</html>
