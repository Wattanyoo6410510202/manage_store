<?php
// พิมพ์ใบขออนุมัติโครงการ (A4) รูปแบบเดียวกับแบบฟอร์มกระดาษ
require 'config.php';
require_once __DIR__ . '/budget_projects_lib.php';

// เปิดจากลิงก์เซ็น (?t=token) ไม่ต้อง login — เห็นเฉพาะเอกสารนี้ และเซ็นได้เฉพาะช่องของตัวเอง
$link_token = (string)($_GET['t'] ?? '');
$link_signer = null;
if ($link_token !== '') {
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
    $link_signer = budget_project_signer_by_token($conn, $link_token);
    if (!$link_signer) {
        http_response_code(410);
        echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>ลิงก์ใช้ไม่ได้</title>'
            . '<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet"></head>'
            . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#f1f5f9;font-family:Sarabun,sans-serif;color:#334155;padding:16px">'
            . '<div style="max-width:380px;background:#fff;border-radius:16px;padding:28px;text-align:center;box-shadow:0 10px 30px -12px rgba(15,23,42,.25)">'
            . '<div style="font-size:40px">🔒</div><h2 style="margin:8px 0">ลิงก์นี้ใช้ไม่ได้แล้ว</h2>'
            . '<p style="margin:0;color:#64748b">ลิงก์อาจหมดอายุ ถูกยกเลิก หรือเซ็นเอกสารนี้ไปแล้ว กรุณาติดต่อผู้ส่งลิงก์เพื่อขอลิงก์ใหม่</p></div></body></html>';
        exit;
    }
} elseif (!isset($_SESSION['user'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$project = budget_project_fetch($conn, $link_signer ? (int)$link_signer['project_id'] : (int)($_GET['id'] ?? 0));
if (!$project) {
    http_response_code(404);
    echo 'ไม่พบโครงการ';
    exit;
}

function bpp_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function bpp_money($value): string
{
    $n = (float)$value;
    return number_format($n, floor($n) == $n ? 0 : 2);
}

// แยกข้อความหลายบรรทัดเป็นรายการ ไม่เอาบรรทัดว่าง
function bpp_lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$text)), 'strlen'));
}

$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$ts = strtotime($project['request_date']);
$date_day = date('j', $ts);
$date_month = $thai_months[(int)date('n', $ts)];
$date_year = (int)date('Y', $ts) + 543;

$units = ['day' => 'วัน', 'month' => 'เดือน', 'year' => 'ปี'];
$company = supplier_display_name($project['company_name']);
$objectives = bpp_lines($project['objectives']);
$results = bpp_lines($project['expected_results']);
$items = $project['items'];
// สร้างแถวว่างเผื่อไว้ แล้วให้แผงตั้งค่าเลือกว่าจะแสดงทั้งหมดกี่แถว
$item_rows = max(15, count($items));

$is_new = $project['project_type'] !== 'additional';
$approved = $project['status'] === 'approved';
$rejected = $project['status'] === 'rejected';

// ลายเซ็นออนไลน์
$signers = $project['signers'];
if ($link_signer) {
    // ลิงก์เซ็น: เซ็นได้เฉพาะช่องของลิงก์ และต้องถึงคิวแล้ว
    $link_role = (string)$link_signer['role_key'];
    $link_block_reason = budget_project_sign_block_reason($project, $signers, $link_role, (int)$link_signer['user_id']);
    $my_roles = $link_block_reason === null ? [$link_role] : [];
} else {
    $link_block_reason = null;
    $my_roles = budget_project_my_sign_roles($project, $signers, (int)($_SESSION['user_id'] ?? 0));
}
// ผู้สร้างโครงการ / admin จัดการลิงก์เซ็นได้ (ไม่แสดงในโหมดลิงก์)
$can_manage_links = !$link_signer && $signers && $project['status'] !== 'rejected'
    && budget_project_can_manage_links($project, (int)($_SESSION['user_id'] ?? 0), (string)($_SESSION['role'] ?? ''))
    && array_filter($signers, fn($s) => empty($s['signed_at']));
$next_signers = budget_project_next_signers($project, $signers);

function bpp_signer_name(string $role, array $signers, string $fallback = ''): string
{
    $name = $signers[$role]['user_name'] ?? '';
    if ($name === '') $name = $fallback;
    return $name !== '' ? $name : str_repeat('.', 40);
}

function bpp_sign_date(?array $signer): string
{
    if (empty($signer['signed_at'])) return '....../....../......';
    $ts = strtotime($signer['signed_at']);
    return date('j/n/', $ts) . ((int)date('Y', $ts) + 543);
}

function bpp_sig_img(?array $signer): string
{
    if (empty($signer['signature_path'])) return '';
    return '<img src="' . bpp_e($signer['signature_path']) . '" alt="ลายเซ็น ' . bpp_e($signer['user_name'] ?? '') . '">';
}

function bpp_sig_line(string $role, string $no, array $signers, array $my_roles): string
{
    return '<div class="sign-line"><span>' . bpp_e($no) . '</span><span class="sig-slot' . (in_array($role, $my_roles, true) ? ' my-turn' : '') . '">'
        . bpp_sig_img($signers[$role] ?? null) . '</span></div>';
}

function bpp_sign_button(string $role, array $my_roles): string
{
    if (!in_array($role, $my_roles, true)) return '';
    $label = BUDGET_PROJECT_SIGNER_ROLES[$role]['label'];
    return '<button type="button" class="sign-btn no-print" onclick="openSignPad(' . bpp_e(json_encode($role)) . ', ' . bpp_e(json_encode($label)) . ')">✍ เซ็นที่นี่</button>';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>ใบขออนุมัติโครงการ <?= bpp_e($project['project_no'] ?: $project['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    /* ค่าทั้งหมดปรับได้จากแผงตั้งค่า (JS เขียนทับตัวแปรเหล่านี้) */
    :root {
        --fs: 13pt;          /* ขนาดตัวอักษร */
        --title-fs: 15pt;    /* ขนาดหัวเรื่อง */
        --title-h: 12mm;     /* ความสูงแถวหัวเรื่อง */
        --row-h: 8.5mm;      /* ความสูงบรรทัดข้อมูล */
        --item-h: 6.8mm;     /* ความสูงแถวตาราง */
        --label-w: 36mm;     /* ความกว้างหัวข้อด้านซ้าย */
        --pad-y: 10mm;       /* ขอบกระดาษ บน/ล่าง */
        --pad-x: 12mm;       /* ขอบกระดาษ ซ้าย/ขวา */
        --sign-top: 6mm;    /* ระยะก่อนส่วนลายเซ็น */
        --sign-gap: 5mm;     /* ระยะระหว่างส่วนลายเซ็น */
        --zoom: 1;           /* ย่อ/ขยายทั้งเอกสาร */
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e5e7eb; font-family: "Sarabun", "Tahoma", sans-serif; color: #111; }
    .layout { display: flex; justify-content: center; padding: 8px 16px 24px; }
    /* บนจอเล็ก ย่อทั้งหน้ากระดาษให้พอดีความกว้างจอ (JS ตั้ง --fit-scale) */
    .page-wrap { --fit-scale: 1; width: calc(210mm * var(--fit-scale)); height: calc(var(--page-h, 297mm) * var(--fit-scale)); flex-shrink: 0; }
    .page-wrap .page { transform: scale(var(--fit-scale)); transform-origin: top left; }
    .page { width: 210mm; min-height: 297mm; background: #fff; padding: var(--pad-y) var(--pad-x); box-shadow: 0 8px 30px rgba(0,0,0,.12); font-size: var(--fs); flex-shrink: 0; }
    .form { border: 1.5px solid #000; zoom: var(--zoom); }
    .row { display: flex; align-items: flex-end; gap: 8px; padding: 0 10px; min-height: var(--row-h); }
    .row-line { border-bottom: 1px solid #000; }
    .title { justify-content: center; min-height: var(--title-h); font-weight: 700; font-size: var(--title-fs); border-bottom: 1.5px solid #000; padding-bottom: 6px; }
    .date { justify-content: flex-end; }
    .types { padding: 0; }
    .types .box { border-right: 1px solid #000; padding: 4px 10px; display: flex; gap: 22px; }
    .check { display: inline-block; width: 0.8em; height: 0.8em; border: 1px solid #000; margin-right: 5px; position: relative; top: 1px; }
    .check.on::after { content: "✓"; position: absolute; left: 1px; top: -0.45em; font-size: 1.1em; font-weight: 700; }
    .label { width: var(--label-w); flex-shrink: 0; padding-bottom: 2px; }
    .fill { flex: 1; border-bottom: 1px dotted #555; padding: 0 4px 2px; min-height: calc(var(--row-h) * .74); }
    .fill.short { flex: 0 0 26mm; text-align: center; }
    .fill.money { flex: 1 1 20mm; min-width: 0; text-align: center; font-weight: 700; margin-right: 6px; }
    .fill.duration { flex: 0 0 14mm; }
    .bold { font-weight: 700; white-space: nowrap; }
    .fields { padding: 4px 0 8px; border-bottom: 1px solid #000; }
    table.items { width: 100%; border-collapse: collapse; }
    table.items th, table.items td { border: 1px solid #000; padding: 1px 8px; height: var(--item-h); font-size: calc(var(--fs) - 1pt); }
    table.items th { font-weight: 700; }
    table.items tr > :first-child { border-left: 0; width: 22mm; text-align: center; }
    table.items tr > :last-child { border-right: 0; width: 58mm; text-align: right; padding-right: 14mm; }
    table.items thead th:last-child { text-align: center; padding-right: 8px; }
    table.items .total td { font-weight: 700; }
    .results { display: flex; gap: 8px; padding: 4px 10px 8px; border-bottom: 1px solid #000; }
    .results .label { width: auto; white-space: nowrap; }
    .results ul { list-style: none; margin: 0; padding: 0 0 0 12mm; flex: 1; }
    .results li { border-bottom: 1px dotted #555; min-height: calc(var(--row-h) * .74); }
    .signs { padding: var(--sign-top) 8px 6mm; font-size: calc(var(--fs) - 1pt); }
    .sign-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; text-align: center; }
    .sign-line { display: flex; align-items: flex-end; justify-content: center; gap: 4px; height: 9mm; }
    .sign-line span:last-child { display: inline-block; width: 42mm; border-bottom: 1px dotted #555; }
    .sign-date { margin-top: 2px; letter-spacing: 2px; }
    .decision { display: flex; gap: 18mm; margin: var(--sign-gap) 0 0 8mm; align-items: flex-end; }
    .decision .reason { flex: 1; border-bottom: 1px dotted #555; min-height: 7mm; margin-right: 10mm; }
    .approver { width: 70mm; margin: var(--sign-gap) 10mm 0 auto; text-align: center; }
    .approver .sign-line span:last-child { width: 55mm; }
    .ack { margin: var(--sign-gap) 0 0 28mm; white-space: nowrap; }
    .ack div { display: flex; align-items: flex-end; gap: 4px; height: 9mm; }
    .ack .dots { display: inline-block; width: 40mm; border-bottom: 1px dotted #555; }
    /* ลายเซ็นวางทับเส้นประ ไม่ดันระยะบรรทัด */
    .sig-slot { position: relative; }
    .sig-slot img { position: absolute; left: 50%; bottom: -1.5mm; transform: translateX(-50%); max-width: 100%; max-height: 15mm; pointer-events: none; }
    .sig-slot.my-turn { background: rgba(79,70,229,.08); }
    .sign-btn { margin-top: 3px; border: 0; border-radius: 6px; padding: 3px 10px; background: #4f46e5; color: #fff; font: inherit; font-size: 10pt; font-weight: 700; cursor: pointer; }
    .ack .sign-btn { margin: 0 0 0 6px; }
    .pad-wrap { position: relative; border: 1.5px dashed #94a3b8; border-radius: 14px; background: #fff; }
    #signPad { display: block; width: 100%; height: min(34vh, 230px); min-height: 170px; touch-action: none; cursor: crosshair; border-radius: 14px; }
    .pad-hint { position: absolute; left: 0; right: 0; bottom: 24%; border-bottom: 1.5px solid #e2e8f0; margin: 0 7%; pointer-events: none; }
    .pad-hint span { position: absolute; left: 0; bottom: 5px; font-size: 11px; color: #cbd5e1; }
    .pad-note { margin: 0 0 10px; font-size: 12px; color: #64748b; }
    .decision-pick { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 14px 0 10px; font-weight: 700; }
    .decision-pick label { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 11px; border: 1.5px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all .15s; }
    .decision-pick label:has(input[value="approved"]:checked) { border-color: #10b981; background: #ecfdf5; color: #047857; }
    .decision-pick label:has(input[value="rejected"]:checked) { border-color: #ef4444; background: #fef2f2; color: #b91c1c; }
    .decision-pick input { accent-color: currentColor; width: 16px; height: 16px; }
    body.sheet-open { overflow: hidden; }
    /* มือถือ: เซ็นแบบ bottom sheet เต็มความกว้าง ปุ่มใหญ่กดง่าย */
    @media (max-width: 640px) {
        #signModal { padding: 0 !important; align-items: flex-end !important; }
        #signModal .modal { width: 100% !important; max-width: none; max-height: 94dvh; border-radius: 22px 22px 0 0; }
        #signModal .modal-head { position: relative; padding: 22px 18px 10px; border-bottom: 0; }
        #signModal .modal-head::before { content: ""; position: absolute; top: 8px; left: 50%; width: 42px; height: 5px; margin-left: -21px; border-radius: 99px; background: #e2e8f0; }
        #signModal .modal-head h3 { font-size: 17px; }
        #signModal .btn-x { font-size: 18px; }
        #signModal .modal-body { padding: 4px 16px 8px; }
        #signPad { height: 38vh; min-height: 200px; }
        #signModal .modal-foot { padding: 12px 16px calc(14px + env(safe-area-inset-bottom)); gap: 10px; border-top: 1px solid #f1f5f9; }
        #signModal .modal-foot button { flex: 1; padding: 14px; font-size: 15px; border-radius: 14px; }
        #signModal textarea { font-size: 16px; } /* กัน iOS ซูมตอนพิมพ์ */
    }
    .modal textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; font: inherit; resize: vertical; }
    .blank-row.hide { display: none; }

    /* แถบเครื่องมือ + modal ปรับแต่ง (ไม่พิมพ์) */
    /* แถบเครื่องมือลอยด้านบน */
    .toolbar-wrap { position: sticky; top: 0; z-index: 10; padding: 12px 16px 6px; background: linear-gradient(#e5e7eb 70%, rgba(229,231,235,0)); }
    .toolbar { max-width: 900px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 8px 8px 12px; background: rgba(255,255,255,.96); backdrop-filter: blur(8px); border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 10px 30px -12px rgba(15,23,42,.25); font-size: 13px; color: #334155; }
    .tb-info { display: flex; align-items: center; gap: 14px; min-width: 0; flex: 1; }
    .tb-doc { display: flex; align-items: center; gap: 10px; min-width: 0; flex-shrink: 0; }
    .tb-icon-doc { width: 36px; height: 36px; border-radius: 10px; background: #eef2ff; color: #4f46e5; display: grid; place-items: center; flex-shrink: 0; }
    .tb-icon-doc svg { width: 19px; height: 19px; }
    .tb-doc-text { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
    .tb-title { font-weight: 700; color: #0f172a; white-space: nowrap; }
    .tb-sub { font-size: 12px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tb-status { font-weight: 700; }
    .tb-status-pending { color: #d97706; }
    .tb-status-approved { color: #059669; }
    .tb-status-rejected { color: #dc2626; }
    .tb-chips { display: flex; gap: 6px; min-width: 0; flex-wrap: wrap; }
    .chip { display: inline-flex; align-items: center; gap: 4px; max-width: 320px; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chip.fit.ok { background: #ecfdf5; color: #047857; }
    .chip.fit.over { background: #fef2f2; color: #b91c1c; }
    .chip-my-turn { background: #4f46e5; color: #fff; }
    .chip-waiting { background: #fffbeb; color: #b45309; }
    .chip-done { background: #ecfdf5; color: #047857; }
    .chip-blocked { background: #f1f5f9; color: #475569; }
    /* modal ลิงก์เซ็น */
    .link-row { border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; margin-bottom: 8px; }
    .link-row-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .link-row-head b { color: #0f172a; }
    .link-row small { color: #64748b; }
    .link-actions { display: flex; gap: 6px; flex-shrink: 0; }
    .link-actions button { padding: 6px 10px !important; font-size: 12px; }
    .link-out { display: none; margin-top: 8px; }
    .link-out.show { display: block; }
    .link-out input { width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; font: inherit; font-size: 12px; color: #334155; background: #f8fafc; }
    .link-out-btns { display: flex; gap: 6px; margin-top: 6px; }
    .link-out-btns button { flex: 1; }
    .btn-line { background: #06c755; color: #fff; }
    .btn-danger-soft { background: #fef2f2; color: #b91c1c; }
    .link-badge { display: inline-block; margin-top: 2px; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
    .link-badge.on { background: #ecfdf5; color: #047857; }
    .link-badge.off { background: #f1f5f9; color: #64748b; }
    .link-warn { font-size: 12px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 8px 10px; margin: 0 0 10px; }
    .done-screen { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; background: #f1f5f9; padding: 16px; }
    .done-card { max-width: 380px; width: 100%; background: #fff; border-radius: 18px; padding: 28px 22px; text-align: center; box-shadow: 0 10px 30px -12px rgba(15,23,42,.25); }
    .done-card img { max-width: 100%; max-height: 90px; margin: 12px auto; display: block; }
    .tb-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
    .tb-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 14px; border: 0; border-radius: 10px; background: #f1f5f9; color: #334155; font: inherit; font-size: 13px; font-weight: 700; cursor: pointer; transition: background .15s, transform .1s; }
    .tb-btn:hover { background: #e2e8f0; }
    .tb-btn:active { transform: scale(.97); }
    .tb-btn:focus-visible { outline: 2px solid #818cf8; outline-offset: 2px; }
    .tb-btn svg { width: 17px; height: 17px; flex-shrink: 0; }
    .tb-primary { background: #4f46e5; color: #fff; box-shadow: 0 4px 12px -4px rgba(79,70,229,.6); }
    .tb-primary:hover { background: #4338ca; }
    .tb-icon { width: 38px; padding: 0; background: transparent; color: #64748b; }
    .tb-icon:hover { background: #fee2e2; color: #dc2626; }
    .tb-sep { width: 1px; height: 24px; background: #e2e8f0; margin: 0 2px; }
    .modal button { border: 0; border-radius: 8px; padding: 8px 14px; font: inherit; font-weight: 700; cursor: pointer; }
    .btn-primary { background: #4f46e5; color: #fff; }
    .btn-light { background: #fff; color: #334155; box-shadow: inset 0 0 0 1px #cbd5e1; }
    p.fit { margin: 0; padding: 7px 10px; border-radius: 8px; font-weight: 700; font-size: 12px; }
    .fit.ok { background: #ecfdf5; color: #047857; }
    .fit.over { background: #fef2f2; color: #b91c1c; }
    .hidden { display: none !important; }
    .modal-backdrop { position: fixed; inset: 0; z-index: 50; background: rgba(15,23,42,.25); display: flex; justify-content: flex-end; align-items: flex-start; padding: 64px 24px 24px; }
    .modal { width: 440px; max-width: 100%; max-height: calc(100vh - 88px); display: flex; flex-direction: column; background: #fff; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,.25); font-size: 13px; color: #334155; }
    .modal-head { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-bottom: 1px solid #f1f5f9; }
    .modal-head h3 { margin: 0; font-size: 15px; color: #0f172a; }
    .btn-x { background: transparent !important; color: #94a3b8; padding: 4px 8px !important; }
    .modal-body { padding: 12px 18px; overflow-y: auto; }
    .modal-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 18px; border-top: 1px solid #f1f5f9; }
    #controls { display: grid; grid-template-columns: 1fr 1fr; column-gap: 16px; }
    .ctl { margin-bottom: 8px; }
    .ctl label { display: flex; justify-content: space-between; gap: 4px; font-weight: 600; margin-bottom: 2px; font-size: 12px; }
    .ctl output { color: #4f46e5; font-family: monospace; white-space: nowrap; }
    .ctl input[type=range] { width: 100%; accent-color: #4f46e5; }
    .group { grid-column: 1 / -1; margin: 10px 0 4px; font-size: 11px; font-weight: 700; color: #94a3b8; letter-spacing: .08em; }
    .group:first-child { margin-top: 4px; }
    .note { font-size: 11px; color: #94a3b8; margin: 8px 0 0; }
    @media (max-width: 520px) {
        #controls { grid-template-columns: 1fr; }
        .modal-backdrop { padding: 8px; align-items: flex-end; }
        .modal { width: 100%; max-height: 75vh; }
        .toolbar-wrap { padding: 8px 8px 4px; }
        .toolbar { flex-wrap: wrap; gap: 8px; padding: 8px; border-radius: 14px; }
        .tb-info { order: 2; width: 100%; flex-wrap: wrap; gap: 6px; }
        .tb-doc { display: none; }
        .tb-chips { width: 100%; }
        .chip { max-width: 100%; flex: 1 1 auto; justify-content: center; }
        .tb-actions { order: 1; width: 100%; }
        .tb-actions .tb-btn:not(.tb-icon) { flex: 1; height: 42px; }
        .tb-sep { display: none; }
        .layout { padding: 4px 8px 16px; }
    }
    @media print {
        @page { size: A4 portrait; margin: 0; }
        body { background: #fff; }
        .toolbar-wrap, .modal-backdrop, .no-print { display: none !important; }
        .sig-slot.my-turn { background: none; }
        .layout { display: block; padding: 0; }
        .page-wrap { width: auto !important; height: auto !important; }
        .page-wrap .page { transform: none !important; }
        .page { margin: 0; box-shadow: none; min-height: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
<?php
$sign_state = $my_roles ? 'my-turn' : ($link_block_reason !== null ? 'blocked' : ($next_signers ? 'waiting' : 'done'));
$status_labels = ['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ'];
?>
<div class="toolbar-wrap">
    <div class="toolbar" role="toolbar" aria-label="เครื่องมือเอกสาร">
        <div class="tb-info">
            <div class="tb-doc">
                <span class="tb-icon-doc" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                </span>
                <div class="tb-doc-text">
                    <span class="tb-title">ใบขออนุมัติโครงการ</span>
                    <span class="tb-sub"><?= bpp_e($project['project_no'] ?: $project['name']) ?> · <span class="tb-status tb-status-<?= bpp_e($project['status']) ?>"><?= bpp_e($status_labels[$project['status']] ?? $project['status']) ?></span></span>
                </div>
            </div>
            <div class="tb-chips">
                <span class="chip fit ok" data-fit></span>
                <?php if ($signers): ?>
                <span class="chip chip-<?= $sign_state ?>" title="<?= bpp_e($sign_state === 'waiting' ? implode(', ', array_map(fn($s) => $s['user_name'] . ' (' . $s['label'] . ')', $next_signers)) : '') ?>">
                    <?php if ($sign_state === 'my-turn'): ?>
                        ✍ ถึงคิวคุณเซ็น: <?= bpp_e(implode(', ', array_map(fn($r) => BUDGET_PROJECT_SIGNER_ROLES[$r]['label'], $my_roles))) ?>
                    <?php elseif ($sign_state === 'blocked'): ?>
                        ⏳ <?= bpp_e($link_block_reason) ?>
                    <?php elseif ($sign_state === 'waiting'): ?>
                        ⏳ รอเซ็น: <?= bpp_e(implode(', ', array_map(fn($s) => $s['user_name'] . ' (' . $s['label'] . ')', $next_signers))) ?>
                    <?php else: ?>
                        ✓ ลงนามครบแล้ว
                    <?php endif; ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="tb-actions">
            <?php if ($can_manage_links): ?>
            <button type="button" class="tb-btn" onclick="openLinkModal()" title="ส่งลิงก์ให้ผู้ลงนามเซ็นโดยไม่ต้อง login">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
                <span>ลิงก์ให้เซ็น</span>
            </button>
            <?php endif; ?>
            <button type="button" class="tb-btn" onclick="openSettings()" title="ปรับแต่งขนาดและระยะห่าง">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>
                <span>ปรับแต่ง</span>
            </button>
            <button type="button" class="tb-btn tb-primary" onclick="window.print()" title="พิมพ์ หรือบันทึกเป็น PDF">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/></svg>
                <span>พิมพ์ / PDF</span>
            </button>
            <span class="tb-sep" aria-hidden="true"></span>
            <button type="button" class="tb-btn tb-icon" onclick="window.close()" title="ปิด" aria-label="ปิด">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
    </div>
</div>
<div class="layout">
<div class="page-wrap" id="pageWrap">
<div class="page" id="page">
    <div class="form">
        <div class="row title">ใบขออนุมัติโครงการ <?= bpp_e($company) ?></div>

        <div class="row row-line date">
            วันที่ <span class="fill short"><?= bpp_e($date_day) ?></span>
            เดือน <span class="fill short" style="flex-basis:32mm"><?= bpp_e($date_month) ?></span>
            พ.ศ. <span class="fill short"><?= bpp_e($date_year) ?></span>
        </div>

        <div class="row row-line types">
            <div class="box">
                <span><span class="check <?= $is_new ? 'on' : '' ?>"></span>โครงการใหม่</span>
                <span><span class="check <?= $is_new ? '' : 'on' ?>"></span>โครงการเพิ่มเติม</span>
            </div>
            <?php if (!$is_new && ($project['parent_project_no'] || $project['parent_name'])): ?>
                <span style="padding:4px 0">ต่อจากโครงการ <?= bpp_e(trim($project['parent_project_no'] . ' ' . $project['parent_name'])) ?></span>
            <?php endif; ?>
        </div>

        <div class="fields">
            <div class="row">
                <span class="label">แผนก</span><span class="fill"><?= bpp_e($project['department']) ?></span>
                <span style="padding-bottom:2px">ฝ่าย</span><span class="fill"><?= bpp_e($project['division']) ?></span>
            </div>
            <div class="row"><span class="label">เลขที่โครงการ</span><span class="fill"><?= bpp_e($project['project_no']) ?></span></div>
            <div class="row"><span class="label">ชื่อโครงการ</span><span class="fill"><?= bpp_e($project['name']) ?></span></div>
            <div class="row"><span class="label">ผู้รับผิดชอบ</span><span class="fill"><?= bpp_e($project['responsible_name']) ?></span></div>
            <?php foreach ($objectives ?: [''] as $i => $line): ?>
                <div class="row"><span class="label"><?= $i === 0 ? 'วัตถุประสงค์' : '' ?></span><span class="fill"><?= bpp_e($line) ?></span></div>
            <?php endforeach; ?>
            <div class="row">
                <span class="bold" style="padding-bottom:2px">ระยะเวลาในการดำเนินการ (ประมาณ)</span>
                <span class="fill short duration"><?= bpp_e($project['duration_value']) ?></span>
                <span style="padding-bottom:2px"><?= bpp_e($units[$project['duration_unit']] ?? '') ?></span>
                <span class="bold" style="padding-bottom:2px; margin-left:6mm">ค่าใช้จ่ายตลอดโครงการ</span>
                <span class="fill money"><?= bpp_money($project['amount']) ?></span>
                <span class="bold" style="padding-bottom:2px">บาท</span>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr><th>ลำดับ</th><th>รายละเอียดของโครงการ</th><th>ราคา (บาท)</th></tr>
            </thead>
            <tbody>
            <?php for ($i = 0; $i < $item_rows; $i++): $it = $items[$i] ?? null; ?>
                <tr<?= $it ? '' : ' class="blank-row" data-row="' . ($i + 1) . '"' ?>>
                    <td><?= $it ? (int)$it['line_no'] : '' ?></td>
                    <td><?= $it ? bpp_e($it['description']) : '' ?></td>
                    <td><?= $it ? bpp_money($it['amount']) : '' ?></td>
                </tr>
            <?php endfor; ?>
                <tr class="total"><td></td><td style="text-align:center">รวมค่าใช้จ่าย</td><td><?= bpp_money($project['amount']) ?></td></tr>
            </tbody>
        </table>

        <div class="results">
            <span class="label">ผลที่คาดว่าจะได้รับ</span>
            <ul>
                <?php foreach ($results ?: ['', ''] as $line): ?>
                    <li><?= bpp_e($line) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="signs">
            <div class="sign-grid">
                <?php foreach ([['responsible', '(1)'], ['checker', '(2)'], ['endorser', '(3)']] as [$role, $no]): ?>
                <div>
                    <?= bpp_sig_line($role, $no, $signers, $my_roles) ?>
                    <div><?= bpp_e(BUDGET_PROJECT_SIGNER_ROLES[$role]['label']) ?></div>
                    <div>(<?= bpp_e(bpp_signer_name($role, $signers, $role === 'responsible' ? $project['responsible_name'] : '')) ?>)</div>
                    <div class="sign-date"><?= bpp_sign_date($signers[$role] ?? null) ?></div>
                    <?= bpp_sign_button($role, $my_roles) ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="decision">
                <span>( <?= $approved ? '✓' : '&nbsp;&nbsp;' ?> ) อนุมัติ</span>
                <span>( <?= $rejected ? '✓' : '&nbsp;&nbsp;' ?> ) ไม่อนุมัติ</span>
                <span class="reason"><?= $rejected ? bpp_e($project['reject_reason']) : '' ?></span>
            </div>

            <div class="approver">
                <?= bpp_sig_line('approver', '', $signers, $my_roles) ?>
                <div>ผู้อนุมัติ</div>
                <div>(<?= bpp_e(bpp_signer_name('approver', $signers)) ?>)</div>
                <?php if (!empty($signers['approver']['signed_at'])): ?><div class="sign-date"><?= bpp_sign_date($signers['approver']) ?></div><?php endif; ?>
                <?= bpp_sign_button('approver', $my_roles) ?>
            </div>

            <div class="ack">
                <?php foreach (['hr' => 'ฝ่ายทรัพยากรบุคคล', 'accounting' => 'ฝ่ายบัญชี'] as $role => $dept): $s = $signers[$role] ?? null; ?>
                <div>
                    <span class="dots sig-slot"><?= bpp_sig_img($s) ?></span>
                    <span>รับทราบ <?= bpp_e($dept) ?><?= $s ? ' (' . bpp_e($s['user_name']) . ')' : '' ?><?= !empty($s['signed_at']) ? ' ' . bpp_sign_date($s) : '' ?></span>
                    <?= bpp_sign_button($role, $my_roles) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</div>
</div>

<!-- Modal เซ็นชื่อ (ไม่พิมพ์) -->
<div id="signModal" class="modal-backdrop hidden" style="justify-content:center" onclick="if (event.target === this) closeSignPad()">
    <div class="modal" role="dialog" aria-labelledby="signTitle" style="width:560px">
        <div class="modal-head">
            <h3 id="signTitle">เซ็นชื่อ</h3>
            <button type="button" class="btn-x" onclick="closeSignPad()" aria-label="ปิด">✕</button>
        </div>
        <div class="modal-body">
            <p class="pad-note">ใช้นิ้วหรือเมาส์วาดลายเซ็นในกรอบด้านล่าง</p>
            <div class="pad-wrap">
                <canvas id="signPad"></canvas>
                <div class="pad-hint"><span>ลงชื่อบนเส้นนี้</span></div>
            </div>
            <div id="decisionBox" class="hidden">
                <div class="decision-pick">
                    <label><input type="radio" name="decision" value="approved" checked> อนุมัติ</label>
                    <label><input type="radio" name="decision" value="rejected"> ไม่อนุมัติ</label>
                </div>
                <textarea id="signComment" rows="2" placeholder="หมายเหตุ / เหตุผลที่ไม่อนุมัติ"></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-light" onclick="clearSignPad()">ล้าง</button>
            <button type="button" class="btn-primary" id="signSave" onclick="saveSignature()">บันทึกลายเซ็น</button>
        </div>
    </div>
</div>

<?php if ($can_manage_links): ?>
<!-- Modal ลิงก์ให้เซ็นโดยไม่ต้อง login (ไม่พิมพ์) -->
<div id="linkModal" class="modal-backdrop hidden" style="justify-content:center" onclick="if (event.target === this) closeLinkModal()">
    <div class="modal" role="dialog" aria-labelledby="linkTitle" style="width:520px">
        <div class="modal-head">
            <h3 id="linkTitle">🔗 ลิงก์ให้เซ็นโดยไม่ต้อง login</h3>
            <button type="button" class="btn-x" onclick="closeLinkModal()" aria-label="ปิด">✕</button>
        </div>
        <div class="modal-body">
            <p class="link-warn">ใครได้ลิงก์ก็เซ็นแทนผู้ลงนามคนนั้นได้ ส่งให้เจ้าตัวโดยตรงเท่านั้น · ลิงก์ใช้ได้ครั้งเดียว หมดอายุใน <?= BUDGET_PROJECT_SIGN_LINK_DAYS ?> วัน · สร้างลิงก์ใหม่แล้วลิงก์เดิมใช้ไม่ได้ทันที</p>
            <?php foreach ($signers as $role => $s): if (!empty($s['signed_at'])) continue; ?>
            <div class="link-row" data-role="<?= bpp_e($role) ?>">
                <div class="link-row-head">
                    <div>
                        <b><?= bpp_e($s['user_name']) ?></b> <small><?= bpp_e($s['label']) ?></small><br>
                        <span class="link-badge <?= $s['has_active_link'] ? 'on' : 'off' ?>" data-badge><?= $s['has_active_link']
                            ? 'มีลิงก์ใช้ได้ถึง ' . bpp_e(date('j/n/', strtotime($s['sign_token_expires'])) . ((int)date('Y', strtotime($s['sign_token_expires'])) + 543))
                            : 'ยังไม่มีลิงก์' ?></span>
                    </div>
                    <div class="link-actions">
                        <button type="button" class="btn-primary" onclick="createSignLink(<?= bpp_e(json_encode($role)) ?>)"><?= $s['has_active_link'] ? 'สร้างลิงก์ใหม่' : 'สร้างลิงก์' ?></button>
                        <?php if ($s['has_active_link']): ?>
                        <button type="button" class="btn-danger-soft" data-revoke onclick="revokeSignLink(<?= bpp_e(json_encode($role)) ?>)">ยกเลิก</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="link-out">
                    <input type="text" readonly onclick="this.select()" aria-label="ลิงก์เซ็น">
                    <div class="link-out-btns">
                        <button type="button" class="btn-light" onclick="copySignLink(this)">📋 คัดลอกลิงก์</button>
                        <button type="button" class="btn-line" onclick="shareSignLinkLine(this)">ส่งทาง LINE</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal ปรับแต่งการพิมพ์ (ไม่พิมพ์) -->
<div id="settingsModal" class="modal-backdrop hidden" onclick="if (event.target === this) closeSettings()">
    <div class="modal" role="dialog" aria-labelledby="settingsTitle">
        <div class="modal-head">
            <h3 id="settingsTitle">ปรับแต่งการพิมพ์</h3>
            <button type="button" class="btn-x" onclick="closeSettings()" aria-label="ปิด">✕</button>
        </div>
        <div class="modal-body">
            <p class="fit ok" data-fit></p>
            <div id="controls"></div>
            <p class="note">ค่าที่ปรับจะจำไว้ในเบราว์เซอร์นี้ และใช้กับทุกใบขออนุมัติโครงการ</p>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-light" onclick="resetSettings()">ค่าเริ่มต้น</button>
            <button type="button" class="btn-primary" onclick="closeSettings()">เสร็จสิ้น</button>
        </div>
    </div>
</div>

<script>
// key = ชื่อตัวแปร CSS (หรือ rows = จำนวนแถวตาราง)
const PRINT_SETTINGS = [
    {group: 'ขนาด'},
    {key: 'zoom', label: 'ย่อ/ขยายทั้งเอกสาร', unit: '%', min: 70, max: 120, step: 1, def: 100},
    {key: 'fs', label: 'ขนาดตัวอักษร', unit: 'pt', min: 9, max: 16, step: 0.5, def: 13},
    {key: 'title-fs', label: 'ขนาดหัวเรื่อง', unit: 'pt', min: 11, max: 22, step: 0.5, def: 15},
    {key: 'label-w', label: 'ความกว้างหัวข้อซ้าย', unit: 'mm', min: 25, max: 50, step: 1, def: 36},
    {group: 'ระยะห่าง'},
    {key: 'title-h', label: 'ความสูงแถวหัวเรื่อง', unit: 'mm', min: 8, max: 30, step: 0.5, def: 12},
    {key: 'row-h', label: 'ความสูงบรรทัดข้อมูล', unit: 'mm', min: 6, max: 14, step: 0.5, def: 8.5},
    {key: 'item-h', label: 'ความสูงแถวตาราง', unit: 'mm', min: 5, max: 12, step: 0.2, def: 6.8},
    {key: 'rows', label: 'จำนวนแถวในตาราง (ขั้นต่ำ)', unit: 'แถว', min: 0, max: 15, step: 1, def: 7},
    {key: 'sign-top', label: 'ระยะก่อนส่วนลายเซ็น', unit: 'mm', min: 0, max: 25, step: 1, def: 6},
    {key: 'sign-gap', label: 'ระยะระหว่างส่วนลายเซ็น', unit: 'mm', min: 0, max: 20, step: 1, def: 5},
    {group: 'ขอบกระดาษ'},
    {key: 'pad-y', label: 'ขอบบน/ล่าง', unit: 'mm', min: 3, max: 25, step: 1, def: 10},
    {key: 'pad-x', label: 'ขอบซ้าย/ขวา', unit: 'mm', min: 3, max: 25, step: 1, def: 12},
];
const STORAGE_KEY = 'budgetProjectPrintSettings';

function loadSettings() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; } catch (e) { return {}; }
}
function saveSettings(values) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(values)); } catch (e) {}
}

let values = {};

function applySettings() {
    const root = document.documentElement.style;
    PRINT_SETTINGS.filter(s => s.key).forEach(s => {
        const v = values[s.key];
        if (s.key === 'rows') {
            document.querySelectorAll('.blank-row').forEach(tr => tr.classList.toggle('hide', +tr.dataset.row > v));
        } else if (s.key === 'zoom') {
            root.setProperty('--zoom', v / 100);
        } else {
            root.setProperty('--' + s.key, v + s.unit);
        }
        const out = document.getElementById('out-' + s.key);
        if (out) out.textContent = v + ' ' + s.unit;
    });
    fitToScreen();
    checkFit();
}

// เช็กว่าเนื้อหายังอยู่ใน 1 หน้า A4
function checkFit() {
    const page = document.getElementById('page');
    const pxPerMm = 96 / 25.4;
    const padY = values['pad-y'] * pxPerMm;
    const available = 297 * pxPerMm - padY * 2;
    // หารด้วยสเกลที่ย่อไว้บนจอเล็ก เพื่อได้ขนาดจริงบนกระดาษ
    const used = page.querySelector('.form').getBoundingClientRect().height / fitScale;
    const diffMm = Math.abs(available - used) / pxPerMm;
    const ok = used <= available + 1;
    document.querySelectorAll('[data-fit]').forEach(el => {
        el.classList.remove('ok', 'over');
        el.classList.add(ok ? 'ok' : 'over');
        el.textContent = ok
            ? `✓ พอดี 1 หน้า A4 (เหลือ ${diffMm.toFixed(0)} mm)`
            : `⚠ เกิน 1 หน้า A4 ไป ${diffMm.toFixed(0)} mm`;
    });
}

// ย่อหน้ากระดาษ A4 ให้พอดีความกว้างจอ (มือถือ/แท็บเล็ต) — ไม่มีผลตอนพิมพ์
let fitScale = 1;
function fitToScreen() {
    const wrap = document.getElementById('pageWrap');
    const page = document.getElementById('page');
    const layout = wrap.parentElement;
    const style = getComputedStyle(layout);
    const availableWidth = layout.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
    fitScale = Math.min(1, availableWidth / page.offsetWidth);
    wrap.style.setProperty('--fit-scale', fitScale);
    wrap.style.setProperty('--page-h', page.offsetHeight + 'px');
}
window.addEventListener('resize', () => { fitToScreen(); checkFit(); });

function openSettings() {
    document.getElementById('settingsModal').classList.remove('hidden');
}
function closeSettings() {
    document.getElementById('settingsModal').classList.add('hidden');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSettings(); });

function buildControls() {
    const box = document.getElementById('controls');
    box.innerHTML = PRINT_SETTINGS.map(s => s.group
        ? `<div class="group">${s.group}</div>`
        : `<div class="ctl">
               <label for="ctl-${s.key}">${s.label} <output id="out-${s.key}"></output></label>
               <input type="range" id="ctl-${s.key}" min="${s.min}" max="${s.max}" step="${s.step}" value="${values[s.key]}">
           </div>`).join('');
    PRINT_SETTINGS.filter(s => s.key).forEach(s => {
        document.getElementById('ctl-' + s.key).addEventListener('input', e => {
            values[s.key] = parseFloat(e.target.value);
            applySettings();
            saveSettings(values);
        });
    });
}

function resetSettings() {
    values = {};
    PRINT_SETTINGS.filter(s => s.key).forEach(s => { values[s.key] = s.def; });
    saveSettings(values);
    buildControls();
    applySettings();
}

const saved = loadSettings();
PRINT_SETTINGS.filter(s => s.key).forEach(s => {
    const v = parseFloat(saved[s.key]);
    values[s.key] = Number.isFinite(v) ? Math.min(s.max, Math.max(s.min, v)) : s.def;
});
buildControls();
applySettings();
document.fonts && document.fonts.ready.then(() => { fitToScreen(); checkFit(); });

// ===== ลายเซ็นออนไลน์ =====
const PROJECT_ID = <?= (int)$project['id'] ?>;
const LINK_TOKEN = <?= json_encode($link_signer ? $link_token : '') ?>;
let signRole = null;
let padHasInk = false;
const pad = document.getElementById('signPad');
const padCtx = pad.getContext('2d');

// ขนาด CSS ของ canvas ตอนเปิด (ใช้แปลงพิกัดนิ้ว/เมาส์ ถ้าขนาดจอเปลี่ยนระหว่างเซ็น)
let padCssSize = {w: 1, h: 1};
function resizeSignPad() {
    const ratio = window.devicePixelRatio || 1;
    padCssSize = {w: pad.offsetWidth, h: pad.offsetHeight};
    pad.width = padCssSize.w * ratio;
    pad.height = padCssSize.h * ratio;
    padCtx.setTransform(ratio, 0, 0, ratio, 0, 0);
    padCtx.lineWidth = window.matchMedia('(pointer: coarse)').matches ? 2.8 : 2.2;
    padCtx.lineCap = 'round';
    padCtx.lineJoin = 'round';
    padCtx.strokeStyle = '#1e3a8a';
    padHasInk = false;
}

function openSignPad(role, label) {
    signRole = role;
    document.getElementById('signTitle').textContent = 'เซ็นชื่อ: ' + label;
    document.getElementById('decisionBox').classList.toggle('hidden', role !== 'approver');
    document.getElementById('signComment').value = '';
    document.getElementById('signError')?.remove();
    document.getElementById('signModal').classList.remove('hidden');
    document.body.classList.add('sheet-open');
    resizeSignPad();
}

function closeSignPad() {
    document.getElementById('signModal').classList.add('hidden');
    document.body.classList.remove('sheet-open');
}

function clearSignPad() {
    padCtx.clearRect(0, 0, pad.width, pad.height);
    padHasInk = false;
}

let drawing = false;
let last = null;
function padPoint(e) {
    const rect = pad.getBoundingClientRect();
    return {
        x: (e.clientX - rect.left) * padCssSize.w / rect.width,
        y: (e.clientY - rect.top) * padCssSize.h / rect.height,
    };
}
pad.addEventListener('pointerdown', e => {
    drawing = true;
    last = padPoint(e);
    pad.setPointerCapture(e.pointerId);
    padCtx.beginPath();
    padCtx.arc(last.x, last.y, padCtx.lineWidth / 2, 0, Math.PI * 2);
    padCtx.fillStyle = padCtx.strokeStyle;
    padCtx.fill();
    padHasInk = true;
});
pad.addEventListener('pointermove', e => {
    if (!drawing) return;
    const p = padPoint(e);
    padCtx.beginPath();
    padCtx.moveTo(last.x, last.y);
    padCtx.lineTo(p.x, p.y);
    padCtx.stroke();
    last = p;
});
['pointerup', 'pointercancel', 'pointerleave'].forEach(ev => pad.addEventListener(ev, () => { drawing = false; }));

// ตัดขอบว่างรอบลายเซ็น เพื่อให้วางบนเส้นประได้พอดี
function trimmedSignature() {
    const {width, height} = pad;
    const data = padCtx.getImageData(0, 0, width, height).data;
    let minX = width, minY = height, maxX = -1, maxY = -1;
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (data[(y * width + x) * 4 + 3] > 0) {
                if (x < minX) minX = x;
                if (x > maxX) maxX = x;
                if (y < minY) minY = y;
                if (y > maxY) maxY = y;
            }
        }
    }
    if (maxX < 0) return null;
    const padPx = 6;
    minX = Math.max(0, minX - padPx); minY = Math.max(0, minY - padPx);
    maxX = Math.min(width - 1, maxX + padPx); maxY = Math.min(height - 1, maxY + padPx);
    const out = document.createElement('canvas');
    out.width = maxX - minX + 1;
    out.height = maxY - minY + 1;
    out.getContext('2d').drawImage(pad, minX, minY, out.width, out.height, 0, 0, out.width, out.height);
    return out.toDataURL('image/png');
}

async function saveSignature() {
    const signature = padHasInk ? trimmedSignature() : null;
    if (!signature) {
        alertBox('กรุณาเซ็นชื่อในกรอบก่อน');
        return;
    }
    const fd = new FormData();
    fd.append('id', PROJECT_ID);
    fd.append('role', signRole);
    if (LINK_TOKEN) fd.append('token', LINK_TOKEN);
    fd.append('signature', signature);
    if (signRole === 'approver') {
        fd.append('decision', document.querySelector('input[name="decision"]:checked').value);
        fd.append('comment', document.getElementById('signComment').value);
    }
    const btn = document.getElementById('signSave');
    btn.disabled = true;
    btn.textContent = 'กำลังบันทึก...';
    try {
        const res = await fetch('budget_project_sign.php', {method: 'POST', body: fd}).then(r => r.json());
        if (res.status === 'success') {
            if (LINK_TOKEN) {
                showLinkDone(signature, res.msg);
                return;
            }
            location.reload();
            return;
        }
        alertBox(res.msg || 'บันทึกไม่สำเร็จ');
    } catch (e) {
        alertBox('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
    }
    btn.disabled = false;
    btn.textContent = 'บันทึกลายเซ็น';
}

// แจ้งเตือนในหน้า (ไม่ใช้ alert ของเบราว์เซอร์)
function alertBox(msg) {
    let el = document.getElementById('signError');
    if (!el) {
        el = document.createElement('p');
        el.id = 'signError';
        el.className = 'fit over';
        el.style.marginTop = '8px';
        document.querySelector('#signModal .modal-body').appendChild(el);
    }
    el.textContent = msg;
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSignPad(); });

// ===== ลิงก์เซ็นโดยไม่ต้อง login =====
const PROJECT_TITLE = <?= json_encode(trim(($project['project_no'] ? $project['project_no'] . ' · ' : '') . $project['name']), JSON_UNESCAPED_UNICODE) ?>;

function openLinkModal() {
    document.getElementById('linkModal').classList.remove('hidden');
}
function closeLinkModal() {
    document.getElementById('linkModal')?.classList.add('hidden');
}

function signLinkRequest(action, role) {
    const fd = new FormData();
    fd.append('id', PROJECT_ID);
    fd.append('role', role);
    fd.append('action', action);
    return fetch('budget_project_sign_link.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .catch(() => ({status: 'error', msg: 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้'}));
}

async function createSignLink(role) {
    const row = document.querySelector(`.link-row[data-role="${role}"]`);
    const badge = row.querySelector('[data-badge]');
    const res = await signLinkRequest('create', role);
    if (res.status !== 'success') {
        badge.className = 'link-badge off';
        badge.textContent = res.msg;
        return;
    }
    const d = new Date(res.expires.replace(' ', 'T'));
    badge.className = 'link-badge on';
    badge.textContent = `มีลิงก์ใช้ได้ถึง ${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear() + 543}`;
    const out = row.querySelector('.link-out');
    out.querySelector('input').value = res.url;
    out.classList.add('show');
    out.querySelector('input').select();
}

async function revokeSignLink(role) {
    const row = document.querySelector(`.link-row[data-role="${role}"]`);
    const badge = row.querySelector('[data-badge]');
    const res = await signLinkRequest('revoke', role);
    badge.className = 'link-badge off';
    badge.textContent = res.status === 'success' ? 'ยกเลิกลิงก์แล้ว' : res.msg;
    row.querySelector('.link-out').classList.remove('show');
    row.querySelector('[data-revoke]')?.remove();
}

async function copySignLink(btn) {
    const input = btn.closest('.link-out').querySelector('input');
    try {
        await navigator.clipboard.writeText(input.value);
    } catch (e) {
        input.select();
        document.execCommand('copy');
    }
    btn.textContent = '✓ คัดลอกแล้ว';
    setTimeout(() => { btn.textContent = '📋 คัดลอกลิงก์'; }, 1500);
}

function shareSignLinkLine(btn) {
    const row = btn.closest('.link-row');
    const url = row.querySelector('.link-out input').value;
    const name = row.querySelector('b').textContent;
    const text = `เรียน ${name}\nรบกวนเซ็นใบขออนุมัติโครงการ ${PROJECT_TITLE}\n${url}`;
    window.open('https://line.me/R/msg/text/?' + encodeURIComponent(text), '_blank', 'noopener');
}

// เซ็นผ่านลิงก์เสร็จ: ลิงก์ใช้ไม่ได้อีก จึงแสดงหน้ายืนยันแทนการโหลดหน้าใหม่
function showLinkDone(signature, msg) {
    closeSignPad();
    const el = document.createElement('div');
    el.className = 'done-screen';
    el.innerHTML = `<div class="done-card">
        <div style="font-size:44px">✅</div>
        <h2 style="margin:6px 0 4px"></h2>
        <p style="margin:0;color:#64748b" data-title></p>
        <img alt="ลายเซ็นของคุณ">
        <p style="margin:0;color:#94a3b8;font-size:12px">ปิดหน้านี้ได้เลย ขอบคุณครับ</p>
    </div>`;
    el.querySelector('h2').textContent = msg;
    el.querySelector('[data-title]').textContent = 'ใบขออนุมัติโครงการ ' + PROJECT_TITLE;
    el.querySelector('img').src = signature;
    document.body.appendChild(el);
    document.body.classList.add('sheet-open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLinkModal(); });
</script>
</body>
</html>
