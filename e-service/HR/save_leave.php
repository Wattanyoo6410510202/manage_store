<?php
/**
 * API สำหรับบันทึกใบลาลง Google Sheet (เวอร์ชันแก้ไขตัด Error Check)
 */

header('Content-Type: application/json; charset=utf-8');

// 1. ป้องกันการเข้าถึงโดยตรงถ้าไม่ใช่ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// 2. รับข้อมูลจากหน้า Form
$name = $_POST['name'] ?? '';
$position = $_POST['position'] ?? '';
$department = $_POST['department'] ?? '';
$leave_type = $_POST['leave_type'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$duration = $_POST['duration'] ?? '';
$reason = $_POST['reason'] ?? '';
$contact = $_POST['contact'] ?? '';

// 3. URL ของ Google Apps Script (ใส่ของจารได้เลย)
$google_script_url = "https://script.google.com/macros/s/AKfycbxkocwQJPoa4yDP9M-abzy6EDh5L-oBNrWo2KZ8rldD2jRyyr1Uh-3nBhBo-qV2jvy5cA/exec";

// 4. เตรียมข้อมูลส่งไปยัง Google Sheet
$data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'name' => $name,
    'position' => $position,
    'department' => $department,
    'leave_type' => $leave_type,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'duration' => $duration,
    'reason' => $reason,
    'contact' => $contact
];

// 5. ใช้ cURL ในการส่งข้อมูล
$ch = curl_init($google_script_url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // บังคับให้ตามไปหน้า Redirect (Google ใช้บ่อย)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ข้ามการเช็ค SSL Certificate (แก้ปัญหาบน Localhost/XAMPP)
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);           // ป้องกันค้างถ้าเน็ตช้า
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// 6. ตรวจสอบผลลัพธ์และส่งกลับไปยังหน้าฟอร์ม
if ($error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'cURL Error: ' . $error
    ]);
} else {
    // ถ้าสำเร็จ จะส่ง Success กลับไป
    echo json_encode([
        'status' => 'success',
        'message' => 'บันทึกข้อมูลลง Google Sheet เรียบร้อยแล้ว',
        'http_code' => $httpCode,
        'debug' => $response
    ]);
}
?>