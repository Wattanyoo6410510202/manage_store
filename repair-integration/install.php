<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/config.php';
$target=$conn->query('SELECT DATABASE() AS database_name,@@hostname AS server_name')->fetch_assoc();
echo json_encode(['database'=>$target['database_name'],'server'=>$target['server_name'],'connection'=>$conn->host_info],JSON_UNESCAPED_SLASHES)."\n";
$options=getopt('',['check','apply','expect-db:','expect-server:']);
if(!isset($options['apply'])) {echo "Read-only preflight; use --apply --expect-db=... --expect-server=... to add tables.\n";exit;}
if(($options['expect-db'] ?? '')!==$target['database_name'] || ($options['expect-server'] ?? '')!==$target['server_name']) {fwrite(STDERR,"Database/server confirmation does not match; nothing changed.\n");exit(1);}
foreach(['users','suppliers','stock_products','stock_balances','stock_withdrawals','stock_withdrawal_items'] as $table) {
    $stmt=$conn->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$stmt->execute([$table]);
    $engine=$stmt->get_result()->fetch_row()[0] ?? '';$stmt->close();
    if(strtoupper($engine)!=='INNODB') {throw new RuntimeException('Required InnoDB table missing: '.$table);}
}
$conn->query(file_get_contents(__DIR__.'/schema.sql'));
echo "Repair integration schema ready. Existing stock data unchanged.\n";
