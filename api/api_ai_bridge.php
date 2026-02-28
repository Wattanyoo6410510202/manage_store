<?php
header('Content-Type: application/json; charset=utf-8');

// 1. เชื่อมต่อฐานข้อมูล (ปรับเปลี่ยนค่าตามจริงของคุณ)
$db = new mysqli("localhost", "root", "", "procurement_system");
if ($db->connect_error) {
    die(json_encode(["error" => "Database Connection Failed"]));
}

// ฟังก์ชันช่วยดึงค่าจากตาราง system_settings
function getSetting($db, $key)
{
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($row = $result->fetch_assoc()) ? $row['setting_value'] : null;
}

// 2. ดึงค่าการตั้งค่าจากตาราง
$apiKey = getSetting($db, 'gemini_api_key');
$modelName = getSetting($db, 'gemini_model') ?: 'gemini-2.5-flash';
$userLocation = getSetting($db, 'user_province') ?: 'ประเทศไทย';
$shopLimit = getSetting($db, 'ai_shop_limit') ?: '5'; // ถ้าหาไม่เจอให้ใช้ 5 เป็นค่าเริ่มต้น

$input = json_decode(file_get_contents('php://input'), true);
$productName = $input['product'] ?? '';

if (empty($productName)) {
    echo json_encode(["error" => "กรุณาระบุชื่อสินค้า"]);
    exit;
}

// 3. กำหนด URL โดยใช้รุ่นจาก Database
$apiUrl = "https://generativelanguage.googleapis.com/v1/models/{$modelName}:generateContent?key=" . $apiKey;

// 4. ปรับ Prompt ให้เน้นการวิเคราะห์เชิงจัดซื้อและพื้นที่ใกล้เคียง
$prompt = "คุณคือผู้เชี่ยวชาญด้านบริหารงานจัดซื้อ (Strategic Procurement Specialist) 
จงสำรวจและเปรียบเทียบราคาของ '${productName}' โดยให้ความสำคัญกับซัพพลายเออร์ที่ตั้งอยู่ในพื้นที่ ${userLocation} หรือใกล้เคียงเป็นอันดับแรก เพื่อลดต้นทุนด้านโลจิสติกส์

เงื่อนไขในการวิเคราะห์:
1. ปริมาณ: คัดเลือกซัพพลายเออร์ที่ดีที่สุด {$shopLimit} ราย ที่มีความน่าเชื่อถือสูง
2. การวิเคราะห์ต้นทุน: คำนวณราคาเป็น 'บาทไทย (THB)' เท่านั้น
3. เกณฑ์การคัดเลือก (Selection Criteria): พิจารณาจาก ราคา, ระยะทางจาก ${userLocation}, การรับประกัน และความพร้อมในการส่งมอบ
4. การสรุปเชิงธุรกิจ (Business Note): ในช่อง note ให้ระบุจุดเด่นเชิงจัดซื้อ เช่น 'ประหยัดค่าขนส่งเนื่องจากอยู่ในพื้นที่', 'ราคาสูงกว่าแต่มีบริการหลังการขาย', หรือ 'สเปกตรงตามมาตรฐานงานช่าง'
5. รูปแบบการตอบ: ตอบเป็น JSON Array เท่านั้น ห้ามมีข้อความอื่นปน (No Markdown, No Prose)

โครงสร้าง JSON ที่ต้องการ:
[
  {
    \"supplier\": \"ชื่อร้านค้า/บริษัท (ระบุสาขาหรือพิกัดในจังหวัด ${userLocation})\", 
    \"price\": 0.00, 
    \"note\": \"วิเคราะห์ความคุ้มค่า (Value for Money): [ระบุสเปก] | [วิเคราะห์ค่าขนส่ง/ระยะทาง] | [ความเห็นเชิงจัดซื้อ]\", 
    \"url\": \"Link สินค้าหรือชื่อช่องทางติดต่อ\"
  }
]";

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["temperature" => 1]
];

// 5. ส่ง Request (cURL)
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 6. ส่งผลลัพธ์กลับ
if ($httpCode === 200) {
    $result = json_decode($response, true);
    $aiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
    $aiText = str_replace(['```json', '```'], '', $aiText);
    echo trim($aiText);
} else {
    echo json_encode([
        "error" => "Google API Error ($httpCode)",
        "details" => json_decode($response, true)
    ]);
}
?>