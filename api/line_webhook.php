<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/line_messaging_api.php';

$http_body = file_get_contents('php://input');
$json = json_decode($http_body, true);

if (!$json || !isset($json['events'])) {
    http_response_code(200);
    exit;
}

foreach ($json['events'] as $event) {
    // ตอนมีคน add bot เป็นเพื่อน หรือ Unblock
    if ($event['type'] === 'follow') {
        $userId = $event['source']['userId'] ?? '';
        if ($userId) {
            $token = getLineChannelToken();
            if ($token) {
                sendLinePushMessage($userId, "🙏 ขอบคุณที่เพิ่มเพื่อน!\nuserId ของคุณคือ:\n$userId\n\nกรุณาแจ้ง userId นี้ให้ Admin ใส่ในระบบเพื่อรับการแจ้งเตือน");
            }
        }
    }

    // ตอนมีคนส่งข้อความมา
    if ($event['type'] === 'message' && $event['message']['type'] === 'text') {
        $userId = $event['source']['userId'] ?? '';
        $replyToken = $event['replyToken'] ?? '';
        $userText = trim($event['message']['text']);

        if ($userId && $replyToken) {
            if ($userText === 'userId' || $userText === 'userid' || $userText === 'ไอดี' || $userText === 'ยูสเซอร์ไอดี') {
                $replyMsg = "👤 userId ของคุณคือ:\n$userId\n\nกรุณาแจ้ง Admin เพื่อใส่ในระบบรับแจ้งเตือน";
            } else {
                $replyMsg = "🙏 สวัสดีคะ\nพิมพ์ \"userId\" เพื่อดู userId ของคุณ\nหรือติดต่อ Admin เพื่อตั้งค่าการแจ้งเตือน";
            }

            $body = json_encode([
                'replyToken' => $replyToken,
                'messages' => [['type' => 'text', 'text' => $replyMsg]]
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/v2/bot/message/reply');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . getLineChannelToken()
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}

http_response_code(200);
exit;
?>