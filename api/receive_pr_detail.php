<?php
require_once '../config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['user_name'] ?? '';
if (!$user_id) { echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ']); exit; }

$pr_id = (int)($_POST['pr_id'] ?? 0);
if (!$pr_id) { echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสเอกสาร']); exit; }

$pr = mysqli_query($conn, "SELECT id, status, received_status, doc_no FROM pr WHERE id = $pr_id AND deleted_at IS NULL")->fetch_assoc();
if (!$pr) { echo json_encode(['status' => 'error', 'message' => 'ไม่พบเอกสาร']); exit; }
if ($pr['status'] !== 'approved') { echo json_encode(['status' => 'error', 'message' => 'เอกสารยังไม่ได้รับการอนุมัติ']); exit; }
if ($pr['received_status'] === 'received') { echo json_encode(['status' => 'error', 'message' => 'รายการนี้รับของแล้ว']); exit; }

$po_id = (int)($_POST['po_id'] ?? 0);
$note = trim($_POST['note'] ?? '');
$items_json = $_POST['items'] ?? '[]';
$items = json_decode($items_json, true) ?? [];
if (empty($items)) { echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการสินค้า']); exit; }

$all_full = true;
foreach ($items as $it) {
    $rqty = floatval($it['received_qty'] ?? 0);
    $oqty = floatval($it['ordered_qty'] ?? 0);
    if ($rqty < $oqty) { $all_full = false; break; }
}

$received_status = $all_full ? 'received' : 'partial';

$conn->begin_transaction();
try {
    $doc_no = 'RCP-' . date('ym') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("INSERT INTO receiving_reports (pr_id, po_id, doc_no, received_by, note) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisis", $pr_id, $po_id, $doc_no, $user_id, $note);
    $stmt->execute();
    $receiving_id = $stmt->insert_id;
    $stmt->close();

    $stmt_item = $conn->prepare("INSERT INTO receiving_report_items (receiving_id, pr_item_id, po_item_id, item_desc, item_unit, ordered_qty, received_qty, diff_qty, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($items as $it) {
        $pr_item_id = !empty($it['pr_item_id']) ? (int)$it['pr_item_id'] : null;
        $po_item_id = !empty($it['po_item_id']) ? (int)$it['po_item_id'] : null;
        $item_desc = $it['item_desc'] ?? '';
        $item_unit = $it['item_unit'] ?? '';
        $ordered_qty = floatval($it['ordered_qty'] ?? 0);
        $received_qty = floatval($it['received_qty'] ?? 0);
        $diff_qty = $received_qty - $ordered_qty;
        $reason = trim($it['reason'] ?? '');

        $stmt_item->bind_param("iiissddds", $receiving_id, $pr_item_id, $po_item_id, $item_desc, $item_unit, $ordered_qty, $received_qty, $diff_qty, $reason);
        $stmt_item->execute();
    }
    $stmt_item->close();

    $stmt_upd = $conn->prepare("UPDATE pr SET received_status = ?, received_at = NOW(), received_by = ? WHERE id = ?");
    $stmt_upd->bind_param("sii", $received_status, $user_id, $pr_id);
    $stmt_upd->execute();
    $stmt_upd->close();

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => $received_status === 'received' ? 'รับของครบถ้วนเรียบร้อย' : 'บันทึกการรับของบางส่วนแล้ว',
        'doc_no' => $doc_no,
        'received_status' => $received_status
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
$conn->close();
