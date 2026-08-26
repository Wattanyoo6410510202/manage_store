<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/stock_workflow.php';
require_once __DIR__ . '/stock_repository.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stockActorStmt = $conn->prepare('SELECT id, name, role, sup_id FROM users WHERE id = ? LIMIT 1');
$stockActorStmt->bind_param('i', $_SESSION['user_id']);
$stockActorStmt->execute();
$stock_actor = $stockActorStmt->get_result()->fetch_assoc();
$stockActorStmt->close();
if (!$stock_actor) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$_SESSION['role'] = $stock_actor['role'];
$_SESSION['sup_id'] = $stock_actor['sup_id'];

function stock_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function stock_csrf_token(): string
{
    if (empty($_SESSION['stock_csrf'])) {
        $_SESSION['stock_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['stock_csrf'];
}

function stock_verify_csrf(): void
{
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['stock_csrf'] ?? '', (string)$_POST['csrf'])) {
        throw new DomainException('คำขอหมดอายุ กรุณาลองใหม่');
    }
}

function stock_flash(string $type, string $message): void
{
    $_SESSION['stock_flash'] = ['type' => $type, 'message' => $message];
}

function stock_take_flash(): ?array
{
    $flash = $_SESSION['stock_flash'] ?? null;
    unset($_SESSION['stock_flash']);
    return $flash;
}

function stock_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function stock_require_manager(array $actor): void
{
    if (!stock_can_manage((string)$actor['role'])) {
        http_response_code(403);
        die('คุณไม่มีสิทธิ์จัดการ Stock');
    }
}

function stock_selected_sup_id(array $actor): int
{
    return stock_visible_sup_id(
        (string)$actor['role'],
        (int)($actor['sup_id'] ?? 0),
        (int)($_GET['sup_id'] ?? $_POST['sup_id'] ?? 0)
    );
}

function stock_companies(mysqli $conn): array
{
    return $conn->query('SELECT id, company_name FROM suppliers ORDER BY company_name')->fetch_all(MYSQLI_ASSOC);
}

function stock_render_flash(?array $flash): void
{
    if (!$flash) {
        return;
    }
    $classes = $flash['type'] === 'success'
        ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
        : 'bg-rose-50 text-rose-800 border-rose-200';
    echo '<div class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold ' . $classes . '">'
        . stock_e($flash['message']) . '</div>';
}

function stock_withdrawal_status_badge_class(string $status): string
{
    return match ($status) {
        'waiting_issue' => 'bg-amber-100 text-amber-800',
        'waiting_confirmation' => 'bg-indigo-100 text-indigo-800',
        'partially_fulfilled' => 'bg-sky-100 text-sky-800',
        'discrepancy' => 'bg-rose-100 text-rose-800',
        'completed' => 'bg-emerald-100 text-emerald-800',
        'cancelled' => 'bg-slate-200 text-slate-700',
        default => 'bg-slate-100 text-slate-700',
    };
}

function stock_error_message(Throwable $exception, string $fallback = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่'): string
{
    if ($exception instanceof DomainException) {
        return $exception->getMessage();
    }
    error_log('[stock] ' . $exception->getMessage());
    return $fallback;
}
