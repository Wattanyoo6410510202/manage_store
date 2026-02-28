<?php
// 0. ป้องกัน Error พ่นออกมาปนกับ JSON
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// 1. เชื่อมต่อฐานข้อมูล
$db = new mysqli("localhost", "root", "", "procurement_system");
if ($db->connect_error) {
    die(json_encode(["error" => "Database Connection Failed"]));
}

function getSetting($db, $key)
{
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($row = $result->fetch_assoc()) ? $row['setting_value'] : null;
}

// 2. ดึงค่าการตั้งค่าจากฐานข้อมูล
$apiKey = getSetting($db, 'groq_api_key'); // ดึง Key ที่คุณเพิ่งเพิ่มลง SQL
$userLocation = getSetting($db, 'user_province') ?: 'สงขลา (หาดใหญ่)';
$shopLimit = getSetting($db, 'ai_shop_limit') ?: '8';

$input = json_decode(file_get_contents('php://input'), true);
$productName = $input['product'] ?? '';

if (empty($productName)) {
    die(json_encode(["error" => "กรุณาระบุชื่อสินค้า"]));
}

if (empty($apiKey)) {
    die(json_encode(["error" => "ไม่ได้ตั้งค่า Groq API Key ในระบบ"]));
}

// 3. กำหนดข้อมูลสำหรับส่งไป Groq API
$apiUrl = "https://api.groq.com/openai/v1/chat/completions";

/// 4. เตรียม Prompt ฉบับแก้บั๊ก AI มโนชื่อห้าง
$prompt = "วิเคราะห์และค้นหาข้อมูลสินค้า: '{$productName}' ในพื้นที่ {$userLocation} 
ข้อกำหนดที่ต้องทำตามอย่างเคร่งครัด (Strict Rules):
1. **ห้ามสร้างชื่อห้างสรรพสินค้าที่ไม่มีอยู่จริง** เช่น ห้ามนำชื่อห้างในกรุงเทพฯ มาต่อท้ายด้วยชื่อจังหวัด {$userLocation} เด็ดขาด
2. ค้นหาเฉพาะร้านค้าหรือศูนย์บริการที่มีสาขาจริงใน {$userLocation} เท่านั้น (เช่น เซ็นทรัล, โลตัส, บิ๊กซี หรือร้านดีลเลอร์ท้องถิ่นที่มีชื่อเสียง)
3. หากไม่พบสาขาจริงในพื้นที่ ให้ระบุว่าเป็น 'ตัวแทนจำหน่ายออนไลน์ (Official Online Store)' แทนการมโนชื่อสาขาขึ้นมาเอง
4. วิเคราะห์รุ่น (Model) และสเปกให้สอดคล้องกับราคาตลาดปี 2025-2026 ห้ามใช้ราคาย้อนหลังเกิน 3 ปี 
5. ค้นหามาทั้งหมด {$shopLimit} ร้านค้า

ตอบกลับเป็น JSON Object รูปแบบนี้เท่านั้น:
{
  \"product_analysis\": \"ระบุชื่อแบรนด์ รุ่น และสเปกมาตรฐานที่ใช้เทียบราคา (เช่น เครื่องพิมพ์เลเซอร์สี A4 รุ่นปี 2025)\",
  \"shops\": [
    {
      \"supplier\": \"ชื่อร้านและสาขาที่มีอยู่จริง (เช่น Power Buy เซ็นทรัล หาดใหญ่)\",
      \"price\": 0.00,
      \"location\": \"ที่อยู่จริง หรือจุดสังเกตพิกัดใน {$userLocation}\",
      \"latitude\": \"null\",
      \"longitude\": \"null\",
      \"note\": \"ให้ระบุรายละเอียดสินค้า + ตำแหน่งร้านคร่าวๆ + ความคุ้มค่า ระบุว่าเป็นราคา ซื้อขาด หรือ เช่า, รวม VAT หรือไม่ และการรับประกัน ยาวๆ หน่อย\",
      \"url\": \"ลิงก์เว็บไซต์หรือเพจทางการของร้าน\"
    }
  ],
  \"summary\": \"วิเคราะห์ความสมเหตุสมผลของราคา และคำแนะนำในการเลือกซื้อในพื้นที่ {$userLocation}\"
}";
$data = [
    "model" => "llama-3.3-70b-versatile",
    "messages" => [
        ["role" => "system", "content" => "You are a professional Thai procurement analyst. Respond ONLY with a valid JSON object. Be detailed and helpful."],
        ["role" => "user", "content" => $prompt]
    ],
    "response_format" => ["type" => "json_object"],
    "temperature" => 0.5 // เพิ่มความหลากหลายของคำบรรยาย
];

// 5. ส่ง Request (cURL)
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 6. ส่งผลลัพธ์กลับไปให้ JavaScript
if ($httpCode === 200) {
    $result = json_decode($response, true);
    $aiContent = $result['choices'][0]['message']['content'] ?? '{}';

    // ล้างเศษ Markdown หาก AI เผลอใส่มา
    $aiContent = preg_replace('/```(?:json)?|```/', '', $aiContent);
    echo trim($aiContent);
} else {
    http_response_code($httpCode ?: 500);
    echo json_encode([
        "error" => "Groq API Error ($httpCode)",
        "details" => $curlError ?: $response
    ]);
}
?>