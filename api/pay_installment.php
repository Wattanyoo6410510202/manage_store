<?php
require_once '../config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['user_name'] ?? '';

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ กรุณา Login ใหม่']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$paid_at = $_POST['paid_at'] ?? '';

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสงวด']);
    exit;
}
if (!$paid_at) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุวันที่ชำระ']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM installment_schedule WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $ins = $stmt->get_result()->fetch_assoc();

    if (!$ins) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลงวดนี้']);
        exit;
    }

    if ($ins['status'] === 'paid') {
        echo json_encode(['status' => 'error', 'message' => 'งวดนี้ชำระแล้ว']);
        exit;
    }

    // อัปโหลดสลิป (ถ้ามี)
    $payment_slip = null;
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/payments/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
        $new_name = 'INSTALLMENT_' . $id . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $upload_dir . $new_name)) {
            $payment_slip = $new_name;
        }
    }

    $paid_at_dt = $paid_at . ' ' . date('H:i:s');
    $status = 'paid';

    if ($payment_slip) {
        $stmt = $conn->prepare("UPDATE installment_schedule SET status = ?, paid_at = ?, paid_amount = amount, payment_slip = ? WHERE id = ?");
        $stmt->bind_param("sssi", $status, $paid_at_dt, $payment_slip, $id);
    } else {
        $stmt = $conn->prepare("UPDATE installment_schedule SET status = ?, paid_at = ?, paid_amount = amount WHERE id = ?");
        $stmt->bind_param("ssi", $status, $paid_at_dt, $id);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึกการชำระงวดที่ ' . $ins['installment_no'] . ' เรียบร้อยแล้ว',
            'paid_at' => $paid_at
        ]);
    } else {
        throw new Exception($conn->error);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}

if (isset($stmt)) $stmt->close();
$conn->close();
