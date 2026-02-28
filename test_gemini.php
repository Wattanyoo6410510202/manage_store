<?php
$apiKey = "AIzaSyCuiJeZiEMpsoLkDxa_JpnAo-CkKcXDYvs";
// ปรับชื่อโมเดลให้ตรงกับรายการที่ระบบรองรับ (แนะนำ gemini-2.5-flash)
$modelName = "gemini-2.5-flash"; 
$url = "https://generativelanguage.googleapis.com/v1beta/models/" . $modelName . ":generateContent?key=" . $apiKey;

$data = ["contents" => [["parts" => [["text" => "Test API Status"]]]]];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: " . $httpCode . "\n";
echo "Response: " . $response;
?>