<?php
/**
 * Line Notify Utility Function
 */
function sendLineNotify($message, $token) {
    if (empty($token)) return false;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "message=" . $message);
    $headers = array('Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer ' . $token,);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);

    if (curl_error($ch)) {
        // Error logging can be added here
        curl_close($ch);
        return false;
    } else {
        $result_ = json_decode($result, true);
        curl_close($ch);
        return $result_['status'] == 200;
    }
}
?>