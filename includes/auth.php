<?php
/**
 * Authentication and role checks (PHP sessions).
 */
declare(strict_types=1);

// Used so a login for an unknown email takes about as long as a wrong password.
const DUMMY_PASSWORD_HASH = '$2y$10$Z6e1y/NCvHyfMUxcTcyEfOimr9vXWQEavOtAZyv.YEIwHfSK2hNQW';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('campusfind_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_url() . '/',
        'secure'   => $secure,
        'httponly' => true,       // JavaScript cannot read the session cookie
        'samesite' => 'Lax',
    ]);
    session_start();
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/** The logged-in user (without password hash), or null. Always re-read from the database. */
function current_user(): ?array
{
    static $cache = null;
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }
    if ($cache !== null && (int) $cache['id'] === $id) {
        return $cache;
    }
    $stmt = db()->prepare('SELECT id, full_name, email, role, created_at FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $cache = $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function dashboard_path_for(?array $user): string
{
    return ($user !== null && $user['role'] === 'admin') ? 'admin/dashboard.php' : 'student/dashboard.php';
}

function login_user(array $user): void
{
    session_regenerate_id(true);                    // prevents session fixation
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
    // Start a fresh, empty session so we can still show "You have been logged out".
    start_secure_session();
    session_regenerate_id(true);
}

/** Pages for any logged-in user. Others are sent to the login page. */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('warning', 'Please log in to continue.');
        $next = current_request_app_path();
        redirect('login.php' . ($next !== null ? '?next=' . rawurlencode($next) : ''));
    }
    return $user;
}

/** Pages for one role only. The check happens on the server, so changing the URL cannot bypass it. */
function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        abort(403, $role === 'admin'
            ? 'This page is only for school staff accounts.'
            : 'This page is only for student accounts.');
    }
    return $user;
}
