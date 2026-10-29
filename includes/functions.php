<?php
/**
 * General helpers: settings, URLs, escaping, flash messages, form memory,
 * pagination and the shared error page.
 */
declare(strict_types=1);

// ------------------------------------------------------------------ settings
function config(string $key, $default = null)
{
    return $GLOBALS['campusfind_config'][$key] ?? $default;
}

// ------------------------------------------------------------------ escaping
/** Escape text for safe output inside HTML. Use it on EVERYTHING that came from users or the database. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape and keep line breaks. */
function e_multiline($value): string
{
    return nl2br(e($value), false);
}

// ---------------------------------------------------------------------- URLs
/** Folder the project is served from, e.g. '' (php -S) or '/CampusFind' (XAMPP). */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = config('base_url');
    if (is_string($configured) && $configured !== '') {
        return $base = rtrim($configured, '/');
    }

    $root       = str_replace('\\', '/', (string) (realpath(APP_ROOT) ?: APP_ROOT));
    $scriptFile = str_replace('\\', '/', (string) (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: ''));
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if ($scriptFile !== '' && stripos($scriptFile, $root) === 0) {
        $relative = substr($scriptFile, strlen($root));          // e.g. /student/dashboard.php
        if ($relative !== '' && substr($scriptName, -strlen($relative)) === $relative) {
            return $base = rtrim(substr($scriptName, 0, -strlen($relative)), '/');
        }
    }

    $docRoot = str_replace('\\', '/', (string) (realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''));
    if ($docRoot !== '' && stripos($root, $docRoot) === 0) {
        return $base = rtrim(substr($root, strlen($docRoot)), '/');
    }
    return $base = '';
}

/** Build a URL for a project file: url('student/dashboard.php?x=1'). Works from any folder. */
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** Redirect to a page inside the project and stop. */
function redirect(string $path)
{
    header('Location: ' . url($path), true, 303);
    exit;
}

/**
 * Only allow redirects to .php pages inside this project (blocks "open redirect" tricks).
 * Returns the cleaned project-relative path, or $default.
 */
function safe_app_path($path, string $default): string
{
    if (!is_string($path)) {
        return $default;
    }
    $path = ltrim(trim($path), '/');
    if ($path === '' || strlen($path) > 300) {
        return $default;
    }
    if (preg_match('#^[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*\.php(\?[A-Za-z0-9_=&%.\-]*)?$#', $path) !== 1) {
        return $default;
    }
    return $path;
}

/** The current GET request as a project-relative path (used for "log in, then come back"). */
function current_request_app_path(): ?string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return null;
    }
    $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $base = base_url();
    if ($base !== '' && strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }
    $safe = safe_app_path($uri, '');
    return $safe !== '' ? $safe : null;
}

// --------------------------------------------------------------------- input
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** A positive integer from the query string / form, or null when missing or invalid. */
function int_param(string $key, string $source = 'GET'): ?int
{
    $bag   = $source === 'POST' ? $_POST : $_GET;
    $value = $bag[$key] ?? null;
    if (is_string($value) && ctype_digit($value) && strlen($value) < 10 && (int) $value > 0) {
        return (int) $value;
    }
    return null;
}

function str_param(string $key, string $source = 'GET'): string
{
    $bag   = $source === 'POST' ? $_POST : $_GET;
    $value = $bag[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

// -------------------------------------------------------------------- dates
function now(): string
{
    return date('Y-m-d H:i:s');
}

function format_date(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $ts = strtotime($date);
    return $ts === false ? e($date) : date('M j, Y', $ts);
}

function format_datetime(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $ts = strtotime($date);
    return $ts === false ? e($date) : date('M j, Y g:i A', $ts);
}

function excerpt(string $text, int $length = 110): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $length) {
        return rtrim(mb_substr($text, 0, $length - 1, 'UTF-8')) . '…';
    }
    if (!function_exists('mb_strlen') && strlen($text) > $length) {
        return rtrim(substr($text, 0, $length - 1)) . '...';
    }
    return $text;
}

// ------------------------------------------------------------ flash messages
/** Queue a one-time message: success, error, warning or info. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ------------------------------------------------------- form memory + errors
/** Remember what the user typed (never passwords) and the validation errors, then the form page shows them. */
function remember_form(array $input, array $errors): void
{
    unset($input['password'], $input['password_confirm'], $input['csrf_token']);
    $_SESSION['form_old']    = $input;
    $_SESSION['form_errors'] = $errors;
}

function form_errors(): array
{
    static $errors = null;
    if ($errors === null) {
        $errors = $_SESSION['form_errors'] ?? [];
        unset($_SESSION['form_errors']);
    }
    return $errors;
}

function form_old(): array
{
    static $old = null;
    if ($old === null) {
        $old = $_SESSION['form_old'] ?? [];
        unset($_SESSION['form_old']);
    }
    return $old;
}

/** Previously typed value for a field (already escaped). */
function old(string $key, $default = ''): string
{
    $old = form_old();
    return e($old[$key] ?? $default);
}

function field_error(string $key): string
{
    $errors = form_errors();
    if (!isset($errors[$key])) {
        return '';
    }
    return '<p class="field-error" id="err-' . e($key) . '">' . icon('alert-circle', 'icon-sm') . '<span>' . e($errors[$key]) . '</span></p>';
}

/** Accessibility attributes for an input that has an error. */
function field_attrs(string $key): string
{
    return isset(form_errors()[$key]) ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"' : '';
}

/** Run-time summary box listing all validation problems on a form. */
function error_summary(): string
{
    $errors = form_errors();
    if (!$errors) {
        return '';
    }
    $html = '<div class="flash flash-error" role="alert"><span class="flash-icon">' . icon('alert-circle') . '</span><div>'
          . '<p><strong>Please fix the highlighted fields.</strong></p></div></div>';
    return $html;
}

// ---------------------------------------------------------------- pagination
function paginate(int $total, int $perPage, int $requestedPage): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page  = min(max(1, $requestedPage), $pages);
    return [
        'total'    => $total,
        'per_page' => $perPage,
        'pages'    => $pages,
        'page'     => $page,
        'offset'   => ($page - 1) * $perPage,
    ];
}

/** Build a query string, dropping empty values. */
function build_query(array $params): string
{
    $clean = [];
    foreach ($params as $key => $value) {
        if ($value !== '' && $value !== null) {
            $clean[$key] = $value;
        }
    }
    return http_build_query($clean);
}

function render_pagination(array $p, string $path, array $query = []): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $link = static function (int $page, string $label, string $class = '', ?string $aria = null) use ($path, $query): string {
        $qs = build_query(array_merge($query, ['page' => $page]));
        return '<a class="' . $class . '" href="' . e(url($path . '?' . $qs)) . '"' . ($aria ? ' aria-label="' . e($aria) . '"' : '') . '>' . $label . '</a>';
    };

    $html = '<nav class="pagination" aria-label="Pagination">';
    $html .= $p['page'] > 1
        ? $link($p['page'] - 1, 'Previous', 'page-link', 'Previous page')
        : '<span class="page-link disabled">Previous</span>';

    $start = max(1, $p['page'] - 2);
    $end   = min($p['pages'], $p['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= $i === $p['page']
            ? '<span class="page-link current" aria-current="page">' . $i . '</span>'
            : $link($i, (string) $i, 'page-link', 'Page ' . $i);
    }

    $html .= $p['page'] < $p['pages']
        ? $link($p['page'] + 1, 'Next', 'page-link', 'Next page')
        : '<span class="page-link disabled">Next</span>';
    $html .= '</nav>';
    return $html;
}

// --------------------------------------------------------------- error pages
/** Show an error page (403, 404, ...) inside the normal layout and stop. */
function abort(int $code, ?string $message = null)
{
    $titles = [
        403 => ['Access denied', 'You do not have permission to open this page.'],
        404 => ['Page not found', 'We could not find what you were looking for.'],
        405 => ['Action not allowed', 'This action must be submitted from a form.'],
    ];
    [$heading, $defaultMessage] = $titles[$code] ?? ['Something went wrong', 'Please try again.'];
    $message = $message ?? $defaultMessage;

    http_response_code($code);
    $pageTitle = $heading;
    $layout    = is_logged_in() ? 'app' : 'public';
    $activeNav = '';
    include APP_ROOT . '/includes/layouts/header.php';
    ?>
    <section class="error-page">
        <p class="error-code"><?= (int) $code ?></p>
        <h1><?= e($heading) ?></h1>
        <p><?= e($message) ?></p>
        <p>
            <?php if (is_logged_in()): ?>
                <a class="btn btn-primary" href="<?= e(url(dashboard_path_for(current_user()))) ?>">Go to my dashboard</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Log in</a>
                <a class="btn btn-secondary" href="<?= e(url('index.php')) ?>">Back to home</a>
            <?php endif; ?>
        </p>
    </section>
    <?php
    include APP_ROOT . '/includes/layouts/footer.php';
    exit;
}
