<?php

session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../config.php';

$page = basename((string)($argv[1] ?? ''));
$role = (string)($argv[2] ?? '');
$expected = (string)($argv[3] ?? '');
$allowedPages = ['stock.php', 'stock_receiving.php', 'stock_withdrawals.php', 'stock_my_withdrawals.php', 'stock_withdrawal_view.php'];
if ($page === '') {
    $cases = [
        ['stock.php', 'procure', 'ภาพรวม Stock'],
        ['stock_receiving.php', 'procure', 'หมวดหมู่พัสดุ'],
        ['stock_withdrawals.php', 'procure', 'ใบเบิกพัสดุ'],
        ['stock_withdrawals.php', 'gmhr', 'ใบเบิกพัสดุ'],
        ['stock_my_withdrawals.php', 'gmhr', 'สถานะใบเบิกของฉัน'],
        ['stock_withdrawal_view.php', 'gmhr', 'ใบเบิก–จ่ายพัสดุ'],
    ];
    foreach ($cases as $case) {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
            . ' ' . escapeshellarg($case[0]) . ' ' . escapeshellarg($case[1]) . ' ' . escapeshellarg($case[2]);
        passthru($command, $exitCode);
        if ($exitCode !== 0) {
            exit($exitCode);
        }
    }
    exit(0);
}
if (!in_array($page, $allowedPages, true) || $role === '' || $expected === '') {
    fwrite(STDERR, "Usage: php stock_page_render_smoke.php <page> <role> <expected-text>\n");
    exit(1);
}

$stmt = $conn->prepare('SELECT id, username, role, sup_id FROM users WHERE role = ? ORDER BY id LIMIT 1');
$stmt->bind_param('s', $role);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    fwrite(STDERR, "FAIL: no {$role} user available for render test\n");
    exit(1);
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user'] = $user['username'];
$_SESSION['role'] = $user['role'];
$_SESSION['sup_id'] = $user['sup_id'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/' . $page;
$_GET = [];
$_POST = [];

$fixtureTransaction = false;
if ($page === 'stock.php') {
    require_once __DIR__ . '/../stock_repository.php';
    $company = $conn->query('SELECT id FROM suppliers ORDER BY id LIMIT 1')->fetch_assoc();
    if (!$company) {
        fwrite(STDERR, "FAIL: stock overview render test needs one company\n");
        exit(1);
    }
    $conn->begin_transaction();
    $fixtureTransaction = true;
    $_GET['sup_id'] = (int)$company['id'];
    $hiddenSku = 'RENDER-HIDDEN-' . bin2hex(random_bytes(3));
    $productId = stock_create_product(
        $conn,
        (int)$company['id'],
        (int)$user['id'],
        $hiddenSku,
        'สินค้าทดสอบดรอปดาวหมวด',
        'ชิ้น',
        'หมวดทดสอบ'
    );
    $docNo = 'WD-BADGE-' . bin2hex(random_bytes(3));
    $purpose = 'ทดสอบตัวเลขรอจ่าย';
    $withdrawalStmt = $conn->prepare('INSERT INTO stock_withdrawals (doc_no, sup_id, requester_id, purpose) VALUES (?, ?, ?, ?)');
    $withdrawalStmt->bind_param('siis', $docNo, $company['id'], $user['id'], $purpose);
    $withdrawalStmt->execute();
    $withdrawalId = (int)$conn->insert_id;
    $withdrawalStmt->close();
    $conn->query("INSERT INTO stock_withdrawal_items (withdrawal_id, product_id, requested_quantity) VALUES ({$withdrawalId}, {$productId}, 1)");
} elseif ($page === 'stock_withdrawals.php') {
    require_once __DIR__ . '/../stock_repository.php';
    $conn->begin_transaction();
    $fixtureTransaction = true;
    $hiddenSku = 'RENDER-HIDDEN-' . bin2hex(random_bytes(3));
    $productId = stock_create_product($conn, (int)$user['sup_id'], (int)$user['id'], $hiddenSku, 'สินค้าทดสอบเพดานใบเบิก', 'ชิ้น', 'หมวดทดสอบใบเบิก');
    stock_apply_movement($conn, (int)$user['sup_id'], $productId, 'adjustment', 3, 'render_fixture', $productId, (int)$user['id'], 'ทดสอบเพดานใบเบิก');
    $fractionalSku = 'RENDER-FRACTION-' . bin2hex(random_bytes(3));
    $fractionalProductId = stock_create_product($conn, (int)$user['sup_id'], (int)$user['id'], $fractionalSku, 'สินค้าทดสอบยอดต่ำกว่าหนึ่ง', 'ลิตร', 'หมวดทดสอบใบเบิก');
    stock_apply_movement($conn, (int)$user['sup_id'], $fractionalProductId, 'adjustment', 0.5, 'render_fixture', $fractionalProductId, (int)$user['id'], 'ทดสอบค่าเริ่มต้นใบเบิก');
    $docNo = 'WD-LIST-' . bin2hex(random_bytes(3));
    $purpose = 'ทดสอบสีสถานะใบเบิก';
    $withdrawalStmt = $conn->prepare('INSERT INTO stock_withdrawals (doc_no, sup_id, requester_id, purpose) VALUES (?, ?, ?, ?)');
    $withdrawalStmt->bind_param('siis', $docNo, $user['sup_id'], $user['id'], $purpose);
    $withdrawalStmt->execute();
    $withdrawalId = (int)$conn->insert_id;
    $withdrawalStmt->close();
    $conn->query("INSERT INTO stock_withdrawal_items (withdrawal_id, product_id, requested_quantity) VALUES ({$withdrawalId}, {$productId}, 1)");
} elseif ($page === 'stock_my_withdrawals.php') {
    $conn->begin_transaction();
    $fixtureTransaction = true;
    $docNo = 'WD-MINE-' . bin2hex(random_bytes(3));
    $purpose = 'ทดสอบสถานะใบเบิกของฉัน';
    $withdrawalStmt = $conn->prepare('INSERT INTO stock_withdrawals (doc_no, sup_id, requester_id, purpose) VALUES (?, ?, ?, ?)');
    $withdrawalStmt->bind_param('siis', $docNo, $user['sup_id'], $user['id'], $purpose);
    $withdrawalStmt->execute();
    $withdrawalStmt->close();
    $otherUser = $conn->query('SELECT id, sup_id FROM users WHERE id <> ' . (int)$user['id'] . ' AND sup_id > 0 ORDER BY id LIMIT 1')->fetch_assoc();
    if ($otherUser) {
        $otherDocNo = 'WD-OTHER-' . bin2hex(random_bytes(3));
        $otherPurpose = 'ห้ามแสดงใบเบิกของคนอื่น';
        $otherStmt = $conn->prepare('INSERT INTO stock_withdrawals (doc_no, sup_id, requester_id, purpose) VALUES (?, ?, ?, ?)');
        $otherStmt->bind_param('siis', $otherDocNo, $otherUser['sup_id'], $otherUser['id'], $otherPurpose);
        $otherStmt->execute();
        $otherStmt->close();
    }
} elseif ($page === 'stock_withdrawal_view.php') {
    require_once __DIR__ . '/../stock_repository.php';
    $conn->begin_transaction();
    $fixtureTransaction = true;
    $hiddenSku = 'RENDER-HIDDEN-' . bin2hex(random_bytes(3));
    $productId = stock_create_product($conn, (int)$user['sup_id'], (int)$user['id'], $hiddenSku, 'พัสดุทดสอบหน้าใบเบิก', 'ชิ้น');
    $docNo = 'WD-RENDER-' . bin2hex(random_bytes(3));
    $purpose = 'ตรวจหน้าใบเบิก';
    $headerStmt = $conn->prepare('INSERT INTO stock_withdrawals (doc_no, sup_id, requester_id, purpose) VALUES (?, ?, ?, ?)');
    $headerStmt->bind_param('siis', $docNo, $user['sup_id'], $user['id'], $purpose);
    $headerStmt->execute();
    $withdrawalId = (int)$conn->insert_id;
    $headerStmt->close();
    $conn->query("INSERT INTO stock_withdrawal_items (withdrawal_id, product_id, requested_quantity) VALUES ({$withdrawalId}, {$productId}, 5)");
    $withdrawalItemId = (int)$conn->insert_id;
    $fixtureUserId = (int)$user['id'];
    $conn->query("INSERT INTO stock_withdrawal_issues (withdrawal_item_id, issued_quantity, issued_by, issued_at, received_quantity, received_by, received_at, status) VALUES ({$withdrawalItemId}, 2, {$fixtureUserId}, '2026-08-25 16:02:00', 2, {$fixtureUserId}, '2026-08-25 16:05:00', 'confirmed')");
    $conn->query("INSERT INTO stock_withdrawal_actions (withdrawal_id, withdrawal_item_id, action_type, quantity, actor_id, reason, created_at) VALUES ({$withdrawalId}, {$withdrawalItemId}, 'cancel_remaining', 1, {$fixtureUserId}, 'ทดสอบวันที่โดยไม่แสดงเวลา', '2026-08-25 16:10:00')");
    $_GET['id'] = $withdrawalId;
} elseif ($page === 'stock_receiving.php') {
    $customer = $conn->query('SELECT id FROM customers ORDER BY id LIMIT 1')->fetch_assoc();
    if (!$customer) {
        fwrite(STDERR, "FAIL: stock receiving render test needs one customer\n");
        exit(1);
    }
    $conn->begin_transaction();
    $fixtureTransaction = true;
    $docNo = 'PO-RENDER-' . bin2hex(random_bytes(3));
    $status = 'approved';
    $poStmt = $conn->prepare('INSERT INTO po (doc_no, customer_id, supplier_id, status, created_by) VALUES (?, ?, ?, ?, ?)');
    $poStmt->bind_param('siisi', $docNo, $customer['id'], $user['sup_id'], $status, $user['id']);
    $poStmt->execute();
    $poId = (int)$conn->insert_id;
    $poStmt->close();
    $itemDescription = 'สินค้าทดสอบช่องหมวดหมู่';
    $unit = 'ชิ้น';
    $itemStmt = $conn->prepare('INSERT INTO po_items (po_id, item_desc, item_qty, item_unit) VALUES (?, ?, 5, ?)');
    $itemStmt->bind_param('iss', $poId, $itemDescription, $unit);
    $itemStmt->execute();
    $itemStmt->close();
    $_GET['po_id'] = $poId;
}

ob_start();
include __DIR__ . '/../' . $page;
$html = ob_get_clean();
if ($fixtureTransaction) {
    $conn->rollback();
}
if (strpos($html, $expected) === false) {
    fwrite(STDERR, "FAIL: {$page} did not render expected text: {$expected}\n");
    exit(1);
}
if (isset($hiddenSku) && strpos($html, $hiddenSku) !== false) {
    fwrite(STDERR, "FAIL: {$page} must not expose the internal product code\n");
    exit(1);
}
if (strpos($html, 'stock_reconciliation.php') !== false || strpos($html, 'กระทบยอดพัสดุ') !== false) {
    fwrite(STDERR, "FAIL: {$page} must not expose the reconciliation feature\n");
    exit(1);
}
if (strpos($html, 'data-stock-ui="procurement"') === false) {
    fwrite(STDERR, "FAIL: {$page} must use the shared Procurement visual language\n");
    exit(1);
}
foreach (['rounded-2xl', 'shadow-sm', 'focus:ring-indigo-500'] as $uiToken) {
    if (strpos($html, $uiToken) === false) {
        fwrite(STDERR, "FAIL: {$page} is missing the shared UI token: {$uiToken}\n");
        exit(1);
    }
}
if (strpos($html, 'border-slate-100') === false && strpos($html, 'border-slate-200') === false) {
    fwrite(STDERR, "FAIL: {$page} is missing the shared bordered-section treatment\n");
    exit(1);
}
if ($page === 'stock_receiving.php') {
    if (strpos($html, 'name="new_product_name[') === false || strpos($html, 'data-stock-product-picker') === false) {
        fwrite(STDERR, "FAIL: stock receiving must render the product-name autocomplete control\n");
        exit(1);
    }
    if (strpos($html, 'name="new_sku[') !== false) {
        fwrite(STDERR, "FAIL: stock receiving must not ask users to enter an SKU\n");
        exit(1);
    }
    if (strpos($html, '+ เพิ่มหมวดใหม่') === false) {
        fwrite(STDERR, "FAIL: stock receiving must allow procurement to add a company category\n");
        exit(1);
    }
    if (strpos($html, 'name="stock_unit[') === false || strpos($html, 'name="units_per_purchase_unit[') === false) {
        fwrite(STDERR, "FAIL: stock receiving must ask for the issue unit and purchase conversion\n");
        exit(1);
    }
    if (strpos($html, 'data-conversion-preview') === false) {
        fwrite(STDERR, "FAIL: stock receiving must explain the purchase-to-Stock conversion\n");
        exit(1);
    }
}
if ($page === 'stock.php') {
    if (strpos($html, 'id="stockProductsOverview"') === false || strpos($html, 'พัสดุทั้งหมด') === false) {
        fwrite(STDERR, "FAIL: stock overview must focus on the complete product list\n");
        exit(1);
    }
    foreach (['ใกล้ถึงจุดสั่งซื้อ', 'พัสดุหมด', 'เพิ่มพัสดุใหม่', 'value="create_product"'] as $removedOverviewSection) {
        if (strpos($html, $removedOverviewSection) !== false) {
            fwrite(STDERR, "FAIL: stock overview still renders unrelated section: {$removedOverviewSection}\n");
            exit(1);
        }
    }
    if (strpos($html, 'data-stock-category-select') === false) {
        fwrite(STDERR, "FAIL: stock overview must render the standard category select\n");
        exit(1);
    }
    if (strpos($html, 'stock-category-list') !== false || strpos($html, '<datalist') !== false) {
        fwrite(STDERR, "FAIL: stock overview must not use the browser datalist popup\n");
        exit(1);
    }
    if (strpos($html, 'name="sku"') !== false || strpos($html, '>รหัสพัสดุ<') !== false) {
        fwrite(STDERR, "FAIL: Stock UI must generate internal product codes without showing an SKU field\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-pending-count') === false) {
        fwrite(STDERR, "FAIL: Stock sidebar must show the waiting-to-issue badge for managers\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-overview-filter') === false || strpos($html, 'data-stock-overview-search') === false || strpos($html, 'data-stock-overview-category') === false) {
        fwrite(STDERR, "FAIL: stock overview must provide instant name search and a category dropdown\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-overview-row') === false || strpos($html, 'data-stock-overview-result-count') === false || strpos($html, 'data-stock-overview-empty') === false) {
        fwrite(STDERR, "FAIL: stock overview filtering must update rows, result count, and empty state\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-overview-name-text') === false) {
        fwrite(STDERR, "FAIL: stock overview product names must expose a safe highlight target\n");
        exit(1);
    }
    if (strpos($html, 'assets/js/stock-overview-filter.js') === false) {
        fwrite(STDERR, "FAIL: stock overview must load the instant filter behavior\n");
        exit(1);
    }
}
if ($page === 'stock_withdrawals.php') {
    if (strpos($html, 'max="3"') === false || strpos($html, 'จำนวนที่ขอเบิกต้องเป็นจำนวนเต็ม') === false) {
        fwrite(STDERR, "FAIL: withdrawal form must cap requested quantity at current stock\n");
        exit(1);
    }
    if (strpos($html, 'ขอเบิกได้มากกว่ายอดคงเหลือ') !== false) {
        fwrite(STDERR, "FAIL: withdrawal form still advertises over-stock requests\n");
        exit(1);
    }
    if (in_array($role, ['procure', 'admin'], true) && (strpos($html, 'data-stock-withdrawal-status') === false || strpos($html, 'bg-amber-100 text-amber-800') === false)) {
        fwrite(STDERR, "FAIL: waiting Stock withdrawals must render the semantic amber badge\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-category-filter') === false || strpos($html, 'หมวดทดสอบใบเบิก') === false || strpos($html, 'data-stock-product-row') === false) {
        fwrite(STDERR, "FAIL: withdrawal form must filter products by the categories available to the user\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-withdrawal-form-grid') === false || strpos($html, 'data-stock-product-responsive-list') === false) {
        fwrite(STDERR, "FAIL: withdrawal form must use the approved bordered responsive layout\n");
        exit(1);
    }
    if (strpos($html, 'border-slate-200') === false || strpos($html, 'md:grid-cols-[minmax(0,1fr)_9rem_18rem]') === false) {
        fwrite(STDERR, "FAIL: withdrawal product layout must have clear section borders and responsive columns\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-cart-button') === false || strpos($html, 'data-stock-cart-panel') === false || strpos($html, 'data-stock-add-to-cart') === false) {
        fwrite(STDERR, "FAIL: withdrawal products must use an always-visible cart flow\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-cart-count') === false || strpos($html, 'data-stock-cart-items') === false || strpos($html, 'data-stock-cart-empty') === false) {
        fwrite(STDERR, "FAIL: withdrawal cart must show its product-line count and selected items\n");
        exit(1);
    }
    $purposePosition = strpos($html, 'name="purpose"');
    $cartPanelPosition = strpos($html, 'data-stock-cart-panel');
    if ($purposePosition === false || $cartPanelPosition === false || $purposePosition < $cartPanelPosition) {
        fwrite(STDERR, "FAIL: withdrawal purpose must be required inside the cart review panel\n");
        exit(1);
    }
    if (strpos($html, 'assets/js/stock-withdrawal-cart.js') === false) {
        fwrite(STDERR, "FAIL: withdrawal page must load the cart behavior\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-withdrawal-search') === false || strpos($html, 'data-stock-withdrawal-result-count') === false || strpos($html, 'data-stock-product-name-text') === false) {
        fwrite(STDERR, "FAIL: withdrawal product picker must provide instant name search with highlight targets\n");
        exit(1);
    }
    if (strpos($html, 'assets/js/stock-withdrawal-filter.js') === false) {
        fwrite(STDERR, "FAIL: withdrawal page must load combined search, category, and highlight behavior\n");
        exit(1);
    }
    if (strpos($html, 'data-available="0"') === false) {
        fwrite(STDERR, "FAIL: a product below one Stock unit must not be available for withdrawal\n");
        exit(1);
    }
    if (strpos($html, 'step="0.01"') !== false || !preg_match('/data-stock-product-quantity[^>]*min="1"[^>]*step="1"[^>]*inputmode="numeric"/u', $html)) {
        fwrite(STDERR, "FAIL: withdrawal quantity controls must accept whole numbers only\n");
        exit(1);
    }
    if ($role !== 'procure' && $role !== 'admin' && strpos($html, 'ทดสอบสีสถานะใบเบิก') !== false) {
        fwrite(STDERR, "FAIL: regular withdrawal creation page must not mix in the requester status history\n");
        exit(1);
    }
    if ($role !== 'procure' && $role !== 'admin' && strpos($html, 'เลขที่ใบเบิก') !== false) {
        fwrite(STDERR, "FAIL: regular withdrawal creation page must not render the withdrawal-history table\n");
        exit(1);
    }
    if ($role !== 'procure' && $role !== 'admin' && (strpos($html, '>ภาพรวม Stock<') !== false || strpos($html, 'สถานะใบเบิกของฉัน') === false)) {
        fwrite(STDERR, "FAIL: regular Stock navigation must replace overview with the requester status page\n");
        exit(1);
    }
}
if ($page === 'stock_my_withdrawals.php') {
    if (strpos($html, 'data-stock-my-withdrawals') === false || strpos($html, 'ทดสอบสถานะใบเบิกของฉัน') === false || strpos($html, 'data-stock-withdrawal-status') === false) {
        fwrite(STDERR, "FAIL: requester status page must render only the current user's withdrawal progress\n");
        exit(1);
    }
    if (strpos($html, 'ห้ามแสดงใบเบิกของคนอื่น') !== false || strpos($html, '>ภาพรวม Stock<') !== false) {
        fwrite(STDERR, "FAIL: requester status page leaked another user's data or restricted overview navigation\n");
        exit(1);
    }
    if (strpos($html, 'id="stock-submenu"') === false || strpos($html, 'href="stock_my_withdrawals.php"') === false) {
        fwrite(STDERR, "FAIL: requester status page must stay inside the Main Stock navigation group\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-status-card') === false || strpos($html, 'data-stock-status-card-header') === false || strpos($html, 'data-stock-status-card-body') === false || strpos($html, 'data-stock-status-card-metrics') === false) {
        fwrite(STDERR, "FAIL: requester status cards must separate header, purpose, and progress metrics\n");
        exit(1);
    }
    if (strpos($html, 'border-slate-300') === false || strpos($html, 'bg-indigo-600') === false || strpos($html, 'text-white') === false) {
        fwrite(STDERR, "FAIL: requester status cards must have a clear boundary and primary detail action\n");
        exit(1);
    }
}
if ($page === 'stock_withdrawal_view.php') {
    if (strpos($html, 'data-stock-withdrawal-status') === false || strpos($html, 'bg-amber-100 text-amber-800') === false) {
        fwrite(STDERR, "FAIL: withdrawal detail must render the same semantic status badge\n");
        exit(1);
    }
    if ($role !== 'procure' && $role !== 'admin' && strpos($html, 'href="stock_my_withdrawals.php"') === false) {
        fwrite(STDERR, "FAIL: requester withdrawal detail must return to the personal status page\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-withdrawal-document') === false || strpos($html, 'ใบเบิก–จ่ายพัสดุ') === false || strpos($html, 'Stock Withdrawal Voucher') === false) {
        fwrite(STDERR, "FAIL: withdrawal detail must render the formal stock withdrawal document\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-print-toolbar') === false || strpos($html, 'data-stock-print-action="print"') === false || strpos($html, 'data-stock-print-action="pdf"') === false) {
        fwrite(STDERR, "FAIL: withdrawal detail must provide print and PDF actions\n");
        exit(1);
    }
    if (strpos($html, '@media print') === false || strpos($html, '@page') === false) {
        fwrite(STDERR, "FAIL: withdrawal detail must include A4 print styling\n");
        exit(1);
    }
    if (!preg_match('/@page\s*\{[^}]*margin:\s*0\s*;/s', $html) || strpos($html, 'data-stock-print-page') === false || strpos($html, "document.title = '';") === false) {
        fwrite(STDERR, "FAIL: withdrawal print view must suppress browser header and footer space\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-document-columns') === false || strpos($html, 'stock-document-table-frame') === false) {
        fwrite(STDERR, "FAIL: withdrawal print table must use an explicit A4-safe column layout\n");
        exit(1);
    }
    if (strpos($html, 'stock-document-signature-footer') === false) {
        fwrite(STDERR, "FAIL: withdrawal signatures must anchor to the bottom of the printed page\n");
        exit(1);
    }
    foreach (['requester', 'issuer', 'receiver'] as $signatureRole) {
        if (strpos($html, 'data-stock-signature="' . $signatureRole . '"') === false) {
            fwrite(STDERR, "FAIL: withdrawal document is missing the {$signatureRole} signature\n");
            exit(1);
        }
    }
    if (strpos($html, 'data-stock-signature="accounting"') !== false) {
        fwrite(STDERR, "FAIL: withdrawal document must not include an accounting verifier signature\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-document-items') === false || strpos($html, 'รายการพัสดุ') === false || strpos($html, 'ยอดค้าง') === false) {
        fwrite(STDERR, "FAIL: withdrawal document must render the formal stock item table\n");
        exit(1);
    }
    if (strpos($html, 'data-stock-document-audit') !== false || strpos($html, 'ประวัติการจ่ายและการรับพัสดุ') !== false) {
        fwrite(STDERR, "FAIL: withdrawal document must omit the issue and receipt audit section\n");
        exit(1);
    }
    $stockPageStart = strpos($html, '<div class="stock-document-shell');
    $stockPageEnd = $stockPageStart === false ? false : strpos($html, '</main>', $stockPageStart);
    $stockPageHtml = ($stockPageStart === false || $stockPageEnd === false) ? '' : substr($html, $stockPageStart, $stockPageEnd - $stockPageStart);
    if ($stockPageHtml === '') {
        fwrite(STDERR, "FAIL: withdrawal page content could not be isolated for terminology checks\n");
        exit(1);
    }
    if (strpos($stockPageHtml, 'สินค้า') !== false) {
        fwrite(STDERR, "FAIL: withdrawal page terminology must use พัสดุ instead of สินค้า\n");
        exit(1);
    }
    if (strpos($stockPageHtml, 'วันที่และเวลา') !== false || strpos($stockPageHtml, 'วัน–เวลา') !== false || preg_match('/\b\d{2}:\d{2}\b/u', $stockPageHtml)) {
        fwrite(STDERR, "FAIL: withdrawal page dates must not display a time component\n");
        exit(1);
    }
}
if (stripos($html, 'Fatal error') !== false || stripos($html, 'Warning:') !== false) {
    fwrite(STDERR, "FAIL: {$page} rendered a PHP error\n");
    exit(1);
}

echo "{$page} rendered for {$role}.\n";
