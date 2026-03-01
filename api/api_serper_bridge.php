<?php
header('Content-Type: application/json');
include('../config.php'); 

// 1. ดึงค่าการตั้งค่าทั้งหมดที่จำเป็นจาก DB
$settings = [];
$keys = ['serp_api_key', 'user_province', 'user_country', 'ai_shop_limit'];
$sql = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('" . implode("','", $keys) . "')";
$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// ตรวจสอบ API Key
$api_key = $settings['serp_api_key'] ?? '';
if (empty($api_key)) {
    echo json_encode(['error' => 'ไม่พบ API Key ในระบบ']);
    exit;
}

// 2. รับค่าชื่อสินค้า
$input = json_decode(file_get_contents('php://input'), true);
$product = $input['product'] ?? '';

if (empty($product)) {
    echo json_encode(['error' => 'กรุณาระบุชื่อสินค้า']);
    exit;
}

// 3. ตั้งค่าพารามิเตอร์โดยใช้ค่าจากตาราง system_settings (แถวที่ 3, 4, 6)
$params = [
    "engine" => "google_shopping",
    "q" => $product,
    "google_domain" => "google.co.th",
    "hl" => "th",
    "gl" => "th",
    "location" => $settings['user_province'] . ", " . $settings['user_country'], // เช่น "หาดใหญ่, Thailand"
    "num" => $settings['ai_shop_limit'] ?? 8, // จำกัดจำนวนร้านค้าตามแถวที่ 6
    "api_key" => $api_key
];

$url = "https://serpapi.com/search.json?" . http_build_query($params);

// 4. ส่งคำขอด้วย cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
echo $response;
curl_close($ch);