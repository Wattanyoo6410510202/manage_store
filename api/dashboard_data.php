<?php
/**
 * API ส่งข้อมูลแดชบอร์ดออกไปให้เว็บอื่น (อ่านอย่างเดียว) — ยืนยันตัวตนดู external_api_auth.php
 *
 * GET api/dashboard_data.php?period=monthly&month=11&year=2026&sup_id=0
 *   period : daily | monthly | yearly   (ค่าเริ่มต้น monthly)
 *   date   : YYYY-MM-DD (ใช้กับ daily)
 *   month, year, sup_id (0 = ทุกบริษัท)
 */
declare(strict_types=1);

require_once __DIR__ . '/external_api_auth.php';
ext_api_guard();

try {
    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/supplier_display.php';
    require_once dirname(__DIR__) . '/dashboard_lib.php';
    $conn->set_charset('utf8mb4');

    $range = dash_resolve_period(
        (string)($_GET['period'] ?? 'monthly'),
        (string)($_GET['date'] ?? ''),
        (int)($_GET['month'] ?? 0),
        (int)($_GET['year'] ?? 0)
    );
    $supId = max(0, (int)($_GET['sup_id'] ?? 0));
    $companies = dash_fetch_companies($conn);

    $data = dash_build_payload($conn, $range, $supId, dash_company_label($companies, $supId));
    $data['companies'] = array_map(fn ($c) => [
        'id' => (int)$c['id'],
        'name' => supplier_display_name((string)$c['company_name']),
    ], $companies);

    ext_api_out(200, ['ok' => true, 'data' => $data]);
} catch (Throwable $e) {
    error_log('[dashboard-api] ' . get_class($e) . ': ' . $e->getMessage());
    ext_api_out(500, ['ok' => false, 'error' => 'ดึงข้อมูลไม่สำเร็จ']);
}
