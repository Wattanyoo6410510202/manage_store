<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../supplier_display.php';

function request_buy_history_filter_fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$candidateResult = mysqli_query($conn, "
    SELECT u.id AS user_id, u.username, u.name, u.role, u.sup_id,
           s.company_name, p.id AS pr_id, p.doc_no
    FROM users u
    JOIN suppliers s ON s.id = u.sup_id
    JOIN pr p ON p.created_by = u.id
             AND p.is_internal = 1
             AND p.deleted_at IS NULL
    WHERE (u.role LIKE 'staff%' OR u.role = 'acc')
    ORDER BY u.id, p.id
");

$candidate = null;
while ($row = mysqli_fetch_assoc($candidateResult)) {
    if ((string)$row['company_name'] !== supplier_display_name($row['company_name'])) {
        $candidate = $row;
        break;
    }
}

if (!$candidate) {
    request_buy_history_filter_fail('no mapped supplier history fixture is available');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['user_id'] = (int)$candidate['user_id'];
$_SESSION['user'] = (string)$candidate['username'];
$_SESSION['username'] = (string)$candidate['username'];
$_SESSION['user_name'] = (string)$candidate['name'];
$_SESSION['role'] = (string)$candidate['role'];
$_SESSION['sup_id'] = (int)$candidate['sup_id'];
$_SESSION['is_logged_in'] = true;

$_SERVER['PHP_SELF'] = '/manage_store/request_buy_history.php';
$_SERVER['REQUEST_URI'] = '/manage_store/request_buy_history.php';
$_SERVER['HTTP_HOST'] = 'localhost';

ob_start();
include __DIR__ . '/../request_buy_history.php';
$html = ob_get_clean();

$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
libxml_clear_errors();

$xpath = new DOMXPath($document);
$rows = $xpath->query('//tr[@data-id="' . (int)$candidate['pr_id'] . '"]');
if (!$rows || $rows->length !== 1) {
    request_buy_history_filter_fail('the owner history row was not rendered');
}

$supplierCells = $xpath->query('./td[4]', $rows->item(0));
if (!$supplierCells || $supplierCells->length !== 1) {
    request_buy_history_filter_fail('the supplier cell was not rendered');
}

$supplierCell = $supplierCells->item(0);
$searchTerms = trim((string)$supplierCell->getAttribute('data-search'));
$storedName = trim((string)$candidate['company_name']);
$displayName = supplier_display_name($storedName);

if ($searchTerms === '' || strpos($searchTerms, $storedName) === false || strpos($searchTerms, $displayName) === false) {
    request_buy_history_filter_fail('supplier search terms do not include both stored and display names');
}

if (strpos(trim((string)$supplierCell->textContent), $displayName) === false) {
    request_buy_history_filter_fail('the supplier cell does not show the mapped company name');
}

echo "request buy history supplier filter: PASS\n";
