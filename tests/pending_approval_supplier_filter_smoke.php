<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../supplier_display.php';

function pending_approval_supplier_filter_fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$candidateResult = mysqli_query($conn, "
    SELECT p.id AS pr_id, p.doc_no, s.company_name
    FROM pr p
    JOIN suppliers s ON s.id = p.supplier_id
    WHERE p.deleted_at IS NULL AND p.is_internal = 1
    ORDER BY p.id
");

$candidate = null;
while ($row = mysqli_fetch_assoc($candidateResult)) {
    if ((string)$row['company_name'] !== supplier_display_name($row['company_name'])) {
        $candidate = $row;
        break;
    }
}

if (!$candidate) {
    pending_approval_supplier_filter_fail('no mapped supplier PR fixture is available');
}

$admin = mysqli_query($conn, "SELECT id, username, name, role, sup_id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetch_assoc();
if (!$admin) {
    pending_approval_supplier_filter_fail('no admin fixture is available');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['user_id'] = (int)$admin['id'];
$_SESSION['user'] = (string)$admin['username'];
$_SESSION['username'] = (string)$admin['username'];
$_SESSION['user_name'] = (string)$admin['name'];
$_SESSION['role'] = (string)$admin['role'];
$_SESSION['sup_id'] = (int)$admin['sup_id'];
$_SESSION['is_logged_in'] = true;

$_GET['type'] = 'pr';
$_SERVER['PHP_SELF'] = '/manage_store/pending_approval.php';
$_SERVER['REQUEST_URI'] = '/manage_store/pending_approval.php?type=pr';
$_SERVER['HTTP_HOST'] = 'localhost';

ob_start();
include __DIR__ . '/../pending_approval.php';
$html = ob_get_clean();

$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
libxml_clear_errors();

$xpath = new DOMXPath($document);
$rows = $xpath->query('//tr[@data-id="' . (int)$candidate['pr_id'] . '"]');
if (!$rows || $rows->length !== 1) {
    pending_approval_supplier_filter_fail('the mapped supplier PR row was not rendered');
}

$supplierCells = $xpath->query('./td[4]', $rows->item(0));
if (!$supplierCells || $supplierCells->length !== 1) {
    pending_approval_supplier_filter_fail('the supplier cell was not rendered');
}

$supplierCell = $supplierCells->item(0);
$searchTerms = trim((string)$supplierCell->getAttribute('data-search'));
$storedName = trim((string)$candidate['company_name']);
$displayName = supplier_display_name($storedName);

if ($searchTerms === '' || strpos($searchTerms, $storedName) === false || strpos($searchTerms, $displayName) === false) {
    pending_approval_supplier_filter_fail('supplier search terms do not include both stored and display names');
}

if (strpos(trim((string)$supplierCell->textContent), $displayName) === false) {
    pending_approval_supplier_filter_fail('the supplier cell does not show the mapped company name');
}

echo "pending approval supplier filter: PASS\n";
