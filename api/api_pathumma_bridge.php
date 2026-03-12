<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// 1. เชื่อมต่อฐานข้อมูล
require_once '../config.php';
$db = $conn;

function getSetting($db, $key)
{
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($row = $result->fetch_assoc()) ? $row['setting_value'] : null;
}

// 2. ดึงค่าการตั้งค่า
$apiKey = getSetting($db, 'pathumma_api_key');
$userLocation = getSetting($db, 'user_province') ?: 'หาดใหญ่';
$shopLimit = getSetting($db, 'ai_shop_limit') ?: '8';

$input = json_decode(file_get_contents('php://input'), true);
$productName = $input['product'] ?? '';

if (empty($productName))
    die(json_encode(["error" => "กรุณาระบุชื่อสินค้า"]));
if (empty($apiKey))
    die(json_encode(["error" => "ยังไม่ได้ตั้งค่า Pathumma API Key"]));

// 3. กำหนด Endpoint และข้อมูลตาม cURL ที่คุณให้มา
$apiUrl = "http://thaillm.or.th/api/pathumma/v1/chat/completions";

$prompt = "คำสั่ง: ในฐานะผู้เชี่ยวชาญการสืบราคาพัสดุ 
จงค้นหาราคาปัจจุบันของ '$productName' ในพื้นที่ $userLocation (อัปเดตข้อมูลปี 2026)
จำนวน $shopLimit รายการ

ข้อบังคับการตอบ (Strict Rules):
1. ตอบเป็น JSON ARRAY เท่านั้น ห้ามมี <think> หรือข้อความเกริ่นนำใดๆ ทั้งสิ้น
2. ในส่วนของ 'note' ให้ระบุรายละเอียดสินค้า + ตำแหน่งร้านคร่าวๆ + ความคุ้มค่าใน $userLocation
3. **ต้องระบุแหล่งอ้างอิงเว็บไซต์ที่มาในช่อง 'url' โดยเริ่มด้วย www. หรือ http เท่านั้น** (เช่น www.facebook.com/..., www.homepro.co.th, www.shopee.co.th)
4. **ในส่วนของ 'url':** ให้ระบุ URL แบบเต็ม (Full URL) ที่ขึ้นต้นด้วย http หรือ www. เท่านั้น โดยต้องเป็นลิงก์ที่เชื่อมโยงไปยังหน้าร้านค้า, เพจเฟซบุ๊กหลัก, หรือพิกัด Google Maps ที่ตรวจสอบได้จริง
5. หากเป็นร้านค้าในท้องถิ่นที่ไม่มีเว็บ ให้พยายามระบุลิงก์จาก Google Maps (www.google.com/maps...)
6.ข้อความบรรยาย (ไม่ใช่ JSON) ไม่ต้องเอามาเด็ดขาด

รูปแบบโครงสร้าง:
[
  {
    \"supplier\": \"ชื่อร้าน/แหล่งข้อมูล\",
    \"price\": 0000,
    \"note\": \"(รายละเอียดสินค้า) ร้านตั้งอยู่บริเวณ... ตรวจสอบข้อมูลจาก: [ระบุ www.]\",
    \"url\": \"URL ที่ขึ้นต้นด้วย www.\"
  }
]

เริ่มการตอบด้วย [ ทันที:";

$postData = [
    "model" => "/model", // ตามตัวอย่างของคุณ
    "messages" => [
        ["role" => "user", "content" => $prompt]
    ],
    "max_tokens" => 2048,
    "temperature" => 0.3
];

// 4. ส่งข้อมูลด้วย cURL
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "apikey: $apiKey" // แก้ไขจาก Authorization เป็น apikey ตามสเปกของคุณ
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 5. ตรวจสอบการตอบกลับ
if ($response === false) {
    die(json_encode(["error" => "cURL Error: " . $curlError]));
}

$res = json_decode($response, true);
$content = $res['choices'][0]['message']['content'] ?? '';

// ล้างข้อมูลส่วนเกิน (Cleaning) เผื่อ AI ตอบแถมข้อความมา
if (preg_match('/\[\s*{.*}\s*\]/s', $content, $matches)) {
    echo $matches[0];
} else {
    // ถ้าไม่มีโครงสร้าง Array ให้ส่งคืนตามจริงเพื่อ Debug
    echo !empty($content) ? $content : json_encode(["error" => "AI did not return content. HTTP: $httpCode"]);
}