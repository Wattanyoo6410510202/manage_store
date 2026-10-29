<?php
// รวมเข้ากับ dashboard.php แล้ว
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: dashboard.php' . ($query !== '' ? '?' . $query : ''));
exit;
