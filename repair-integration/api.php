<?php
declare(strict_types=1);
require_once __DIR__ . '/protocol.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$local = is_file(__DIR__.'/config.local.php') ? require __DIR__.'/config.local.php' : [];
$secret = (string)(getenv('REPAIR_BRIDGE_SECRET') ?: ($local['secret'] ?? ''));
$authenticated = false;
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); throw new InvalidArgumentException('ใช้ POST เท่านั้น'); }
    $body = file_get_contents('php://input',false,null,0,65537);
    if ($body === false || strlen($body)>65536) { throw new InvalidArgumentException('คำขอมีขนาดใหญ่เกินไป'); }
    storeBridgeVerify($secret,(string)($_SERVER['HTTP_X_REPAIR_TIME'] ?? ''),$body,(string)($_SERVER['HTTP_X_REPAIR_SIGNATURE'] ?? ''));
    $authenticated = true;
    $input = json_decode($body,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($input)) { throw new InvalidArgumentException('ข้อมูลไม่ถูกต้อง'); }
    require_once dirname(__DIR__).'/config.php';
    require_once dirname(__DIR__).'/stock_workflow.php';
    require_once dirname(__DIR__).'/stock_repository.php';
    require_once __DIR__.'/service.php';
    $conn->set_charset('utf8mb4');
    $result = ['ok'=>true,'data'=>storeBridgeDispatch($conn,$input)];
} catch (InvalidArgumentException|DomainException $e) {
    http_response_code($authenticated ? 422 : 401);
    $result = ['ok'=>false,'error'=>$e->getMessage(),'retryable'=>false];
} catch (Throwable $e) {
    error_log('[repair-bridge] '.get_class($e).': '.$e->getMessage());
    http_response_code(503);
    $result = ['ok'=>false,'error'=>'ระบบคลังไม่พร้อม กรุณาลองคำขอเดิมอีกครั้ง','retryable'=>true];
}
$output = json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
if ($authenticated) {
    $time = time();
    header('X-Repair-Time: '.$time);
    header('X-Repair-Signature: '.storeBridgeSign($secret,$time,$output));
}
echo $output;
