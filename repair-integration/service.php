<?php
declare(strict_types=1);
require_once __DIR__ . '/protocol.php';

function storeBridgeRows(mysqli $conn, string $sql, array $params = []): array
{
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function storeBridgeWrite(mysqli $conn, string $sql, array $params): void
{
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $stmt->close();
}

function storeBridgeDispatch(mysqli $conn, array $input): array
{
    $action = (string)($input['action'] ?? '');
    if ($action === 'directory') {
        if (($input['source_role'] ?? 0) !== 1) { throw new DomainException('เฉพาะผู้ดูแลระบบเท่านั้น'); }
        return ['companies'=>storeBridgeRows($conn,'SELECT id, company_name AS name FROM suppliers ORDER BY company_name'),
            'users'=>storeBridgeRows($conn,'SELECT id, username, name, sup_id FROM users WHERE sup_id > 0 ORDER BY name')];
    }
    $actorId = (int)($input['actor_id'] ?? 0);
    $supId = (int)($input['sup_id'] ?? 0);
    $actor = storeBridgeRows($conn,'SELECT id, sup_id FROM users WHERE id = ?',[$actorId])[0] ?? null;
    if ((!$actor || $supId <= 0 || (int)$actor['sup_id'] !== $supId) && !in_array($action,['create','cancel'],true)) {
        throw new DomainException('บัญชีเว็บคลังไม่ได้อยู่ในบริษัทของงานซ่อม กรุณาให้ Admin ตรวจการจับคู่');
    }
    if ($action === 'products') {
        return ['products'=>storeBridgeRows($conn,
            'SELECT p.id, p.sku, p.name, p.unit, COALESCE(b.quantity,0) AS quantity FROM stock_products p
             LEFT JOIN stock_balances b ON b.product_id=p.id WHERE p.sup_id=? AND p.is_active=1 ORDER BY p.name',[$supId])];
    }
    $requestId = (int)($input['request_id'] ?? 0);
    if ($requestId <= 0) { throw new DomainException('ไม่พบเลขงานซ่อม'); }
    if ($action === 'lookup') {
        $row=storeBridgeRows($conn,'SELECT w.id AS withdrawal_id,w.doc_no FROM repair_bridge_withdrawals link
            JOIN stock_withdrawals w ON w.id=link.withdrawal_id WHERE link.operation_key=? AND link.repair_request_id=?
            AND link.sup_id=? AND link.store_actor_id=? AND link.source_actor_id=?',
            [(string)($input['operation_key'] ?? ''),$requestId,$supId,$actorId,(int)($input['source_actor_id'] ?? 0)])[0] ?? null;
        return $row===null?['found'=>false]:array_merge(['found'=>true],$row);
    }
    if ($action === 'list') {
        $withdrawals = storeBridgeRows($conn,
            'SELECT w.id, w.doc_no, w.status, w.created_at, w.purpose, u.name AS requester_name
             FROM repair_bridge_withdrawals link JOIN stock_withdrawals w ON w.id=link.withdrawal_id
             JOIN users u ON u.id=w.requester_id
             WHERE link.repair_request_id=? AND link.sup_id=? ORDER BY w.id DESC',[$requestId,$supId]);
        foreach ($withdrawals as &$withdrawal) {
            $withdrawal['items'] = storeBridgeRows($conn,
                'SELECT p.sku,p.name,p.unit,i.requested_quantity,i.issued_quantity,i.received_quantity,i.cancelled_quantity
                 FROM stock_withdrawal_items i JOIN stock_products p ON p.id=i.product_id WHERE i.withdrawal_id=? ORDER BY i.id',[$withdrawal['id']]);
        }
        unset($withdrawal);
        return ['withdrawals'=>$withdrawals];
    }
    if (!in_array($action,['create','cancel'],true)) { throw new DomainException('ไม่รองรับคำขอนี้'); }
    $key = (string)($input['operation_key'] ?? '');
    $number = trim((string)($input['request_number'] ?? ''));
    $purpose = trim((string)($input['purpose'] ?? ''));
    $sourceActor = (int)($input['source_actor_id'] ?? 0);
    if (!preg_match('/^[a-f0-9]{32,64}$/D',$key) || $actorId<=0 || $supId<=0 || $sourceActor <= 0 || $number === '' || mb_strlen($number)>50
        || $purpose === '' || mb_strlen($purpose)>1000 || !is_array($input['quantities'] ?? null)) {
        throw new DomainException('ข้อมูลอ้างอิงงานหรือวัตถุประสงค์ไม่ถูกต้อง');
    }
    $items = storeBridgeQuantities($input['quantities']);
    $digest = hash('sha256',json_encode([$actorId,$supId,$requestId,$number,$sourceActor,$purpose,$items],JSON_THROW_ON_ERROR));
    $conn->begin_transaction();
    try {
        storeBridgeWrite($conn,
            'INSERT INTO repair_bridge_withdrawals (operation_key,payload_hash,repair_request_id,repair_number,source_actor_id,store_actor_id,sup_id)
             VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',[$key,$digest,$requestId,$number,$sourceActor,$actorId,$supId]);
        $link = storeBridgeRows($conn,'SELECT * FROM repair_bridge_withdrawals WHERE operation_key=? FOR UPDATE',[$key])[0];
        if (!hash_equals($link['payload_hash'],$digest)) { throw new DomainException('คำขอเดิมถูกเปลี่ยนข้อมูล กรุณาตรวจสอบใบเบิกเดิมก่อน'); }
        if ($link['withdrawal_id'] !== null) {
            $existing = storeBridgeRows($conn,'SELECT id AS withdrawal_id,doc_no FROM stock_withdrawals WHERE id=?',[$link['withdrawal_id']])[0];
            $conn->commit();
            return $existing;
        }
        if($action==='cancel') {
            // A durable tombstone serializes with in-flight creates; late workers cannot create this operation.
            storeBridgeWrite($conn,'UPDATE repair_bridge_withdrawals SET cancelled=1 WHERE id=?',[$link['id']]);
            $conn->commit();return ['cancelled'=>true];
        }
        if((int)$link['cancelled']===1) {throw new DomainException('คำขอนี้ถูกยกเลิกแล้ว กรุณาสร้างคำขอใหม่');}
        $actor=storeBridgeRows($conn,'SELECT id,sup_id FROM users WHERE id=? LOCK IN SHARE MODE',[$actorId])[0] ?? null;
        if(!$actor || (int)$actor['sup_id']!==$supId) {throw new DomainException('บัญชีเว็บคลังไม่ได้อยู่ในบริษัทของงานซ่อม กรุณาให้ Admin ตรวจการจับคู่');}
        // Native withdrawals lock document numbering before products. Preserve that order.
        $docNo = stock_generate_doc_no($conn,'WD','stock_withdrawals');
        // Lock in product-id order, matching the store's product and balance checks.
        foreach ($items as $item) {
            $product = storeBridgeRows($conn,'SELECT id FROM stock_products WHERE id=? AND sup_id=? AND is_active=1 FOR UPDATE',[$item['product_id'],$supId])[0] ?? null;
            if (!$product) { throw new DomainException('อะไหล่ไม่อยู่ในบริษัทนี้หรือถูกปิดใช้งาน'); }
            $balance = storeBridgeRows($conn,'SELECT quantity FROM stock_balances WHERE product_id=? FOR UPDATE',[$item['product_id']])[0] ?? null;
            stock_validate_withdrawal_request_quantity((float)$item['quantity'],(float)($balance['quantity'] ?? 0));
        }
        storeBridgeWrite($conn,'INSERT INTO stock_withdrawals (doc_no,sup_id,requester_id,purpose) VALUES (?,?,?,?)',
            [$docNo,$supId,$actorId,'งานซ่อม '.$number.' — '.$purpose]);
        $withdrawalId = (int)$conn->insert_id;
        foreach ($items as $item) {
            storeBridgeWrite($conn,'INSERT INTO stock_withdrawal_items (withdrawal_id,product_id,requested_quantity) VALUES (?,?,?)',[$withdrawalId,$item['product_id'],$item['quantity']]);
        }
        storeBridgeWrite($conn,'UPDATE repair_bridge_withdrawals SET withdrawal_id=? WHERE id=?',[$withdrawalId,$link['id']]);
        $conn->commit();
        return ['withdrawal_id'=>$withdrawalId,'doc_no'=>$docNo];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
