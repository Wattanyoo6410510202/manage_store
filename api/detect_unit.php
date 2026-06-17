<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

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

$apiKey = getSetting($db, 'groq_api_key');

$input = json_decode(file_get_contents('php://input'), true);
$product = trim($input['product'] ?? '');

// ถ้าไม่มี API Key หรือชื่อสินค้าสั้นเกินไป ให้ลอง fallback
if (empty($apiKey) || mb_strlen($product) < 3) {
    $fallback = guessUnit($product);
    echo json_encode(["unit" => $fallback, "source" => "fallback"]);
    exit;
}

// ใช้ prompt สั้นๆ ให้ Groq ตอบเฉพาะหน่วยนับ
$prompt = "สินค้า: {$product}\n\nเลือกหน่วยนับที่เหมาะสมจากรายการนี้เท่านั้น:\nชิ้น, ตัว, อัน, ชุด, กล่อง, แพ็ค, โหล, ลัง, กิโลกรัม, กรัม, เมตร, เซนติเมตร, ลิตร, มิลลิลิตร, ตัน, คู่, แผ่น, ม้วน, ตลับ, ถุง, ขวด, กระป๋อง, หลอด, เครื่อง, เส้น, ลูก, ใบ, ดอก, ก้อน, ห่อ, ซอง\n\nตอบเฉพาะชื่อหน่วยนับคำเดียว";

$apiUrl = "https://api.groq.com/openai/v1/chat/completions";
$data = [
    "model" => "llama-3.3-70b-versatile",
    "messages" => [
        ["role" => "system", "content" => "ตอบเฉพาะชื่อหน่วยนับภาษาไทยสั้นๆ คำเดียวเท่านั้น ไม่มีข้อความอื่น"],
        ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.1,
    "max_tokens" => 10
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    $unit = trim($result['choices'][0]['message']['content'] ?? '');

    // ล้างอักขระที่ไม่ใช่ภาษาไทยหรืออังกฤษ
    $unit = preg_replace('/[^ก-๙a-zA-Z\/\(\)\s]/u', '', $unit);
    $unit = trim($unit);

    // ตรวจสอบว่าหน่วยที่ได้สมเหตุสมผล
    $validUnits = ['ชิ้น','ตัว','อัน','ชุด','กล่อง','แพ็ค','โหล','ลัง','กิโลกรัม','กรัม','กก.','ก.',
                   'เมตร','ม.','เซนติเมตร','ซม.','ลิตร','ล.','มิลลิลิตร','มล.','ตัน',
                   'คู่','แผ่น','ม้วน','ตลับ','ถุง','ขวด','กระป๋อง','หลอด','เครื่อง',
                   'เส้น','ลูก','ใบ','ดอก','ก้อน','ห่อ','ซอง','รีม','ลัง'];
    
    $found = false;
    foreach ($validUnits as $vu) {
        if ($unit === $vu) { $found = true; break; }
    }

    if ($found && mb_strlen($unit) <= 20) {
        echo json_encode(["unit" => $unit, "source" => "ai"]);
    } else {
        // AI ตอบมาไม่ตรง ให้ fallback
        $fallback = guessUnit($product);
        echo json_encode(["unit" => $fallback, "source" => $fallback ? "fallback" : null]);
    }
} else {
    // API Error ให้ fallback
    $fallback = guessUnit($product);
    echo json_encode(["unit" => $fallback, "source" => $fallback ? "fallback" : null]);
}

// ฟังก์ชัน fallback เดาหน่วยจากคำสำคัญ
function guessUnit($product) {
    if (empty($product)) return null;
    $name = mb_strtolower($product, 'UTF-8');

    $rules = [
        ['keywords' => ['เครื่องปรับอากาศ','แอร์','คอมพิวเตอร์','cpu','พีซี','โน้ตบุ๊ก','笔记本',
                        'ปริ้นเตอร์','printer','พริ้น','เครื่องพิมพ์','แฟกซ์','ถ่ายเอกสาร',
                        'สแกนเนอร์', 'เครื่องซัก', 'เครื่องทำ', 'เครื่องปั๊ม', 'ปั๊มน้ำ',
                        'มอเตอร์', 'ปั้ม'], 'unit' => 'เครื่อง'],
        ['keywords' => ['หมึก','toner','ตลับหมึก','ink','ตลับ'], 'unit' => 'ตลับ'],
        ['keywords' => ['กระดาษ','a4','เอกสาร'], 'unit' => 'รีม'],
        ['keywords' => ['น้ำดื่ม','น้ำเปล่า','น้ำแข็ง'], 'unit' => 'ขวด'],
        ['keywords' => ['น้ำมัน','สารเคมี','liquid','น้ำยา'], 'unit' => 'ลิตร'],
        ['keywords' => ['ข้าวสาร','ทราย','ปูน','ซีเมนต์','ปุ๋ย','อาหารสัตว์'], 'unit' => 'กิโลกรัม'],
        ['keywords' => ['ผ้า','ม่าน','พลาสติกคลุม','สายไฟ','ลวด'], 'unit' => 'เมตร'],
        ['keywords' => ['หลอดไฟ','หลอด'], 'unit' => 'หลอด'],
        ['keywords' => ['ถุงมือ','ถุงพลาสติก','ถุงขยะ'], 'unit' => 'ห่อ'],
        ['keywords' => ['แบตเตอรี่','ถ่าน'], 'unit' => 'ก้อน'],
        ['keywords' => ['ยางรถ','ยาง'], 'unit' => 'เส้น'],
        ['keywords' => ['เก้าอี้','โต๊ะ','ตู้'], 'unit' => 'ตัว'],
        ['keywords' => ['ปากกา','ดินสอ','ยางลบ','ไม้บรรทัด'], 'unit' => 'ด้าม'],
        ['keywords' => ['สมุด','แฟ้ม','ซอง','เอกสาร'], 'unit' => 'เล่ม'],
        ['keywords' => ['กระเป๋า'], 'unit' => 'ใบ'],
        ['keywords' => ['จอ','ทีวี','tv','monitor','จอมอนิเตอร์'], 'unit' => 'เครื่อง'],
        ['keywords' => ['พัดลม','ไดร์', 'ไดร์เป่า'], 'unit' => 'เครื่อง'],
        ['keywords' => ['แก้ว','จาน','ชาม'], 'unit' => 'ใบ'],
        ['keywords' => ['หน้ากาก','mask','แมส'], 'unit' => 'ชิ้น'],
    ];

    foreach ($rules as $rule) {
        foreach ($rule['keywords'] as $kw) {
            if (mb_strpos($name, $kw) !== false) {
                return $rule['unit'];
            }
        }
    }

    // Default
    return 'ชิ้น';
}
