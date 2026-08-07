<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    
    // --- เพิ่มส่วนนี้: เช็คว่าสั่งลบหรือไม่ ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $sql = "DELETE FROM project_milestones WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            echo "success";
        } else {
            echo "Error: " . mysqli_error($conn);
        }
        exit; // จบการทำงานตรงนี้เลยถ้าเป็นการลบ
    }
    // -----------------------------------

    // ถ้าไม่ใช่การลบ (เป็นการอัปเดตสถานะปกติ)
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'pending');

    $milestoneStmt = $conn->prepare("SELECT project_id, net_amount FROM project_milestones WHERE id = ? LIMIT 1");
    $milestoneStmt->bind_param('i', $id);
    $milestoneStmt->execute();
    $milestone = $milestoneStmt->get_result()->fetch_assoc();
    $milestoneStmt->close();
    if (!$milestone) {
        http_response_code(404);
        echo 'ไม่พบงวดงานที่ต้องการอัปเดต';
        exit;
    }

    // ห้ามยืนยันการโอนจนกว่ารอบตรวจรับปัจจุบันจะผ่านครบทุกขั้นตอน
    if ($status === 'paid') {
        $roundStmt = $conn->prepare("SELECT status FROM inspection_rounds WHERE milestone_id = ? ORDER BY round_no DESC, id DESC LIMIT 1");
        $roundStmt->bind_param('i', $id);
        $roundStmt->execute();
        $round = $roundStmt->get_result()->fetch_assoc();
        $roundStmt->close();

        $legacyPassed = false;
        if (!$round) {
            $legacyStmt = $conn->prepare("SELECT result_status FROM milestone_inspections WHERE milestone_id = ? ORDER BY id DESC LIMIT 1");
            $legacyStmt->bind_param('i', $id);
            $legacyStmt->execute();
            $legacy = $legacyStmt->get_result()->fetch_assoc();
            $legacyStmt->close();
            $legacyPassed = $legacy && ($legacy['result_status'] ?? '') === 'pass';
        }
        if (($round && ($round['status'] ?? '') !== 'completed') || (!$round && !$legacyPassed)) {
            http_response_code(422);
            echo 'ต้องตรวจรับงานและอนุมัติครบทุกขั้นตอนก่อนบันทึกการโอน';
            exit;
        }
    }

    $paymentFieldsSql = '';
    if ($status === 'paid') {
        $paidAt = trim((string)($_POST['paid_at'] ?? ''));
        if ($paidAt === '') {
            $paidAt = date('Y-m-d');
        }
        $paidDate = DateTime::createFromFormat('Y-m-d', $paidAt);
        if (!$paidDate || $paidDate->format('Y-m-d') !== $paidAt) {
            http_response_code(422);
            echo 'วันที่โอนไม่ถูกต้อง';
            exit;
        }

        $paidAmountInput = trim((string)($_POST['paid_amount'] ?? ''));
        $paidAmount = $paidAmountInput === '' ? (float)($milestone['net_amount'] ?? 0) : (float)$paidAmountInput;
        if ($paidAmount < 0) {
            http_response_code(422);
            echo 'ยอดโอนต้องไม่ติดลบ';
            exit;
        }

        $paymentReference = mysqli_real_escape_string($conn, trim((string)($_POST['payment_reference'] ?? '')));
        $paymentBank = mysqli_real_escape_string($conn, trim((string)($_POST['payment_bank'] ?? '')));
        $paymentNote = mysqli_real_escape_string($conn, trim((string)($_POST['payment_note'] ?? '')));
        $paymentFieldsSql = ", paid_at = '$paidAt', paid_amount = " . number_format($paidAmount, 2, '.', '') . ", payment_reference = '$paymentReference', payment_bank = '$paymentBank', payment_note = '$paymentNote'";
    }

    $update_file_sql = "";
    if (isset($_FILES['claim_attachment']) && $_FILES['claim_attachment']['error'] == 0) {
        $target_dir = "../uploads/claims/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }

        $file_ext = pathinfo($_FILES["claim_attachment"]["name"], PATHINFO_EXTENSION);
        $file_name = "PAYMENT_" . $id . "_" . time() . "." . $file_ext;

        if (move_uploaded_file($_FILES["claim_attachment"]["tmp_name"], $target_dir . $file_name)) {
            $update_file_sql = ", claim_attachment = '$file_name'";
        }
    }

    $sql = "UPDATE project_milestones SET
            status = '$status'
            $paymentFieldsSql
            $update_file_sql
            WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        echo "success";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>
