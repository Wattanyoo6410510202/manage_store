<?php
function getLineChannelToken() {
    global $conn;
    $result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'LINE_CHANNEL_ACCESS_TOKEN' LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        return trim($row['setting_value']);
    }
    return null;
}

function sendLinePushMessage($userId, $message) {
    $token = getLineChannelToken();
    if (empty($token) || empty($userId)) return false;

    $messages = [
        [
            'type' => 'text',
            'text' => $message
        ]
    ];

    $body = json_encode([
        'to' => $userId,
        'messages' => $messages
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/v2/bot/message/push');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200;
}
?>