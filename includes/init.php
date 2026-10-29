<?php
/**
 * Bootstrap file. Every page and action includes this first:
 *   require_once __DIR__ . '/../includes/init.php';
 * It loads settings, helpers, the database, the session and security headers.
 */
declare(strict_types=1);

if (defined('CAMPUSFIND_BOOTSTRAPPED')) {
    return;
}
define('CAMPUSFIND_BOOTSTRAPPED', true);
define('APP_ROOT', dirname(__DIR__));

$GLOBALS['campusfind_config'] = require APP_ROOT . '/config/app.php';

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';

date_default_timezone_set((string) config('timezone', 'Asia/Manila'));

// ---- Friendly error page instead of raw PHP errors ---------------------------
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $e): void {
    error_log('[CampusFind] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $detail = config('debug', false) ? $e->getMessage() : null;
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Something went wrong</title>'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head><body class="public-page">'
       . '<main class="error-page"><h1>Something went wrong</h1>'
       . '<p>The page could not be loaded. Please go back and try again.</p>'
       . ($detail !== null ? '<pre class="debug-box">' . e($detail) . '</pre>' : '')
       . '<p><a class="btn btn-primary" href="' . e(url('index.php')) . '">Back to home</a></p></main></body></html>';
    exit;
});

// ---- Security headers (no inline scripts or styles are used anywhere) ----------
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
         . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}

// ---- Database must exist (never let PDO silently create an empty file) --------
if (!is_file(db_path())) {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Setup needed</title>'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head><body class="public-page">'
       . '<main class="error-page"><h1>The database has not been created yet</h1>'
       . '<p>Open a terminal in the project folder and run:</p>'
       . '<pre class="debug-box">php database/setup.php</pre>'
       . '<p>Then reload this page.</p></main></body></html>';
    exit;
}

require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/validation.php';
require_once APP_ROOT . '/includes/components/icons.php';
require_once APP_ROOT . '/includes/components/status-badge.php';
require_once APP_ROOT . '/includes/components/item-card.php';
require_once APP_ROOT . '/includes/components/report-form.php';
require_once APP_ROOT . '/includes/reports.php';
require_once APP_ROOT . '/includes/claims.php';

start_secure_session();
