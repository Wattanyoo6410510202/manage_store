<?php
// หน้าพิมพ์แบบฟอร์มเปรียบเทียบราคา (A4 แนวนอน) ของ PR หนึ่งใบ
require_once 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT p.*, own.company_name AS my_company, own.address AS my_address, own.phone AS my_phone,
        own.logo_path, u.name AS creator_name
    FROM pr p
    LEFT JOIN suppliers own ON p.supplier_id = own.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = ? AND p.deleted_at IS NULL");
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
if (!$data) {
    http_response_code(404);
    exit('ไม่พบใบขอซื้อ');
}
$logo_path = !empty($data['logo_path']) ? 'uploads/' . $data['logo_path'] : '';
$prq_standalone = true;
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ใบเทียบราคา <?= htmlspecialchars($data['doc_no'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        body { margin: 0; background: #f1f5f9; font-family: 'Sarabun', sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: center; gap: 8px; padding: 10px; background: rgba(255,255,255,.9); border-bottom: 1px solid #e2e8f0; }
        .toolbar button, .toolbar a { border: 0; border-radius: 10px; padding: 8px 16px; font: 700 13px 'Sarabun', sans-serif; cursor: pointer; text-decoration: none; }
        .btn-print { background: #0f172a; color: #fff; }
        .btn-back { background: #e2e8f0; color: #0f172a; }
        .btn-edit { background: #fef3c7; color: #92400e; }
        .btn-edit.on { background: #f59e0b; color: #fff; }
        .edit-hint { display: none; align-self: center; font-size: 12px; color: #92400e; }
        body.editing .edit-hint { display: inline; }
        body.editing #prq-sheet { outline: 2px dashed #f59e0b; outline-offset: 4px; cursor: text; }
        body.editing #prq-sheet:focus { outline: 2px dashed #f59e0b; }
        .empty { text-align: center; padding: 60px; color: #64748b; }
        @media print { body { background: #fff; } .toolbar { display: none; } body.editing #prq-sheet { outline: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a class="btn-back" href="view_pr_new.php?id=<?= $id ?>"><i class="fas fa-chevron-left"></i> กลับไปใบขอซื้อ</a>
        <button class="btn-edit" id="btnEdit" type="button" onclick="prqToggleEdit()"><i class="fas fa-pen"></i> แก้ไขข้อความ</button>
        <button class="btn-back" type="button" onclick="location.reload()" title="ทิ้งที่แก้ไขทั้งหมด"><i class="fas fa-rotate-left"></i> คืนค่าเดิม</button>
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> พิมพ์ใบเทียบราคา</button>
        <span class="edit-hint">คลิกที่ข้อความบนใบเพื่อแก้ไข (ไม่บันทึกลงระบบ ใช้สำหรับพิมพ์เท่านั้น)</span>
    </div>
    <?php
    ob_start();
    include 'pr_quotes_view.php';
    $sheet = ob_get_clean();
    echo $sheet !== '' ? $sheet : '<div class="empty">ใบขอซื้อนี้ยังไม่มีใบเทียบราคา</div>';
    ?>
    <script>
        // แก้ข้อความบนใบก่อนพิมพ์ — แก้เฉพาะบนหน้าจอ ไม่บันทึกลงระบบ (รีเฟรชแล้วกลับเป็นค่าเดิม)
        function prqToggleEdit() {
            const sheet = document.getElementById('prq-sheet');
            if (!sheet) return;
            const on = !document.body.classList.contains('editing');
            document.body.classList.toggle('editing', on);
            document.getElementById('btnEdit').classList.toggle('on', on);
            if (on) {
                sheet.setAttribute('contenteditable', 'true');
                sheet.setAttribute('spellcheck', 'false');
                sheet.focus();
            } else {
                sheet.removeAttribute('contenteditable');
            }
        }
        window.addEventListener('beforeunload', e => {
            if (document.body.classList.contains('editing')) { e.preventDefault(); e.returnValue = ''; }
        });
    </script>
</body>
</html>
