<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/../config/db.php';

/** Escape output (prevents XSS). */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** CSRF protection for every POST form. */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('Invalid form token. Go back, refresh the page and try again.');
    }
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function home_for(string $role): string {
    return match ($role) {
        'ADMIN'     => BASE_URL . '/admin/dashboard.php',
        'AGENT'     => BASE_URL . '/agent/log-deposit.php',
        default     => BASE_URL . '/collector/dashboard.php',
    };
}

/** Role-based access control: call at the top of every protected page. */
function require_role(string ...$roles): void {
    $u = current_user();
    if (!$u || !in_array($u['role'], $roles, true)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function flash(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }

/** Turn a database exception into a friendly message. */
function db_message(PDOException $e): string {
    $info = $e->errorInfo ?? [];
    if (($info[0] ?? '') === '45000') { return $info[2];}            // our SIGNAL messages
    if ((int)($info[1] ?? 0) === 1062) { return 'That value already exists (duplicate).'; }
    if (in_array((int)($info[1] ?? 0), [3819, 4025], true)) { return 'Value rejected by a database rule (CHECK constraint).'; }
    error_log($e->getMessage());
    return 'Database error. Please check your input.';
}

function ugx($n): string { return 'UGX ' . number_format((float)$n, 0); }
