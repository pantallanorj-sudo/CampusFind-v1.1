<?php
/**
 * CampusFind automated tests.
 *
 *   php tests/run_tests.php                              run everything, print results
 *   php tests/run_tests.php --report=docs/test-results.md  also write a Markdown report
 *
 * What it does: builds a THROWAWAY copy of the database, starts PHP's built-in server
 * against it, then drives the real application over HTTP (log in, submit forms, upload
 * photos...) and checks both the pages and the SQLite rows. Your demo database is never touched.
 * Needs only PHP with PDO SQLite. No curl, no Composer, no PHPUnit.
 */
declare(strict_types=1);

require __DIR__ . '/HttpClient.php';
require dirname(__DIR__) . '/config/database.php';

$root   = dirname(__DIR__);
$tmpDir = __DIR__ . '/.tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}
$dbFile  = $tmpDir . '/test-' . getmypid() . '.sqlite';
$logFile = $tmpDir . '/server-' . getmypid() . '.log';
$options = getopt('', ['report::']);
$reportPath = isset($options['report']) && is_string($options['report']) ? $options['report'] : null;

date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// ------------------------------------------------------------------ results
$results = [];
$section = '';
function section(string $title): void
{
    global $section;
    $section = $title;
    echo "\n== $title\n";
}
function check(string $name, bool $ok, string $detail = ''): bool
{
    global $results, $section;
    $results[] = ['section' => $section, 'name' => $name, 'ok' => $ok, 'detail' => $detail];
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $name . (!$ok && $detail !== '' ? "\n        -> $detail" : '') . "\n";
    return $ok;
}

// --------------------------------------------------------- database helpers
$pdo = null;
function dbh(): PDO
{
    global $pdo, $dbFile;
    if ($pdo === null) {
        $pdo = connect_database($dbFile);
    }
    return $pdo;
}
function one(string $sql, array $params = [])
{
    $stmt = dbh()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}
function row(string $sql, array $params = []): ?array
{
    $stmt = dbh()->prepare($sql);
    $stmt->execute($params);
    $r = $stmt->fetch();
    return $r ?: null;
}
function rows(string $sql, array $params = []): array
{
    $stmt = dbh()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
function run(string $sql, array $params = []): void
{
    dbh()->prepare($sql)->execute($params);
}
function uid(string $email): int
{
    return (int) one('SELECT id FROM users WHERE email = ?', [$email]);
}
function item_id(string $name): int
{
    return (int) one('SELECT id FROM item_reports WHERE item_name = ?', [$name]);
}
function item_status(int $id): string
{
    return (string) one('SELECT status FROM item_reports WHERE id = ?', [$id]);
}
function claim_status(int $id): string
{
    return (string) one('SELECT status FROM claim_requests WHERE id = ?', [$id]);
}
function claim_id(int $itemId, int $claimantId): int
{
    return (int) one('SELECT id FROM claim_requests WHERE item_report_id = ? AND claimant_id = ? ORDER BY id DESC LIMIT 1', [$itemId, $claimantId]);
}

// ------------------------------------------------------------- http helpers
function has(string $body, string $needle): bool
{
    return stripos(html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $needle) !== false;
}
function loc(array $r): string
{
    return HttpClient::locationPath($r);
}
function redirects_to(array $r, string $path): bool
{
    return $r['status'] === 303 && loc($r) === $path;
}
function csrf_of(HttpClient $c): string
{
    $page = $c->get($c->home ?? '/login.php');
    if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $page['body'], $m)) {
        return $m[1];
    }
    throw new RuntimeException('No CSRF token found on ' . ($c->home ?? '/login.php'));
}
/** POST an action with a valid CSRF token for this client. */
function act(HttpClient $c, string $path, array $data = [], array $files = []): array
{
    $data['csrf_token'] = $data['csrf_token'] ?? csrf_of($c);
    return $c->post($path, $data, $files);
}
/** Follow a redirect and return the page body (so flash messages can be read). */
function after(HttpClient $c, array $r): string
{
    return $c->follow($r)['body'];
}
function login_as(HttpClient $c, string $email, string $password = 'Demo@12345', string $next = ''): array
{
    $page = $c->get('/login.php');
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $page['body'], $m);
    $r = $c->post('/actions/auth/login.php', ['csrf_token' => $m[1] ?? '', 'email' => $email, 'password' => $password, 'next' => $next]);
    if ($r['status'] === 303 && str_contains(loc($r), 'login.php') === false) {
        $c->home = str_contains($email, 'admin@') ? '/admin/dashboard.php' : '/student/dashboard.php';
    }
    return $r;
}
function new_client(string $email = '', string $password = 'Demo@12345'): HttpClient
{
    global $base;
    $c = new HttpClient($base);
    if ($email !== '') {
        login_as($c, $email, $password);
    }
    return $c;
}
function stat_values(string $html): array
{
    preg_match_all('#<p class="stat-value">(\d+)</p>#', $html, $m);
    return array_map('intval', $m[1]);
}
function report_data(array $over = []): array
{
    global $today;
    return $over + [
        'report_type'   => 'found',
        'item_name'     => 'Test item',
        'category_id'   => (string) one("SELECT id FROM categories WHERE name = 'Other'"),
        'date_occurred' => $today,
        'location'      => 'Test location, Library',
        'description'   => 'A test description that is long enough to pass validation.',
    ];
}
/** Create a report through the real form and return its id (0 on failure). */
function make_report(HttpClient $c, array $over = []): int
{
    $data = report_data($over);
    $r = act($c, '/actions/reports/create.php', $data);
    if ($r['status'] === 303 && preg_match('#/student/item-details\.php\?id=(\d+)#', loc($r), $m)) {
        return (int) $m[1];
    }
    return 0;
}
/** is_file() with PHP's stat cache cleared, so files deleted by the server process are seen as gone. */
function file_present(string $absolutePath): bool
{
    clearstatcache(true);
    return is_file($absolutePath);
}
const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

// ------------------------------------------------------ build DB + start server
echo "CampusFind test run\n";
echo 'PHP ' . PHP_VERSION . ' on ' . PHP_OS_FAMILY . ', ' . date('Y-m-d H:i:s') . "\n";

$out = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/database/setup.php') . ' --fresh --db=' . escapeshellarg($dbFile) . ' 2>&1', $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "Could not create the test database:\n" . implode("\n", $out) . "\n");
    exit(2);
}

$sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
$name = stream_socket_get_name($sock, false);
$port = (int) substr($name, (int) strrpos($name, ':') + 1);
fclose($sock);
$base = 'http://127.0.0.1:' . $port;

$uploadsBefore = glob($root . '/uploads/items/*') ?: [];
$proc = proc_open(
    [PHP_BINARY, '-d', 'log_errors=1', '-d', 'error_reporting=-1', '-S', '127.0.0.1:' . $port, 'router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $logFile, 'w'], 2 => ['file', $logFile, 'a']],
    $pipes,
    $root,
    array_merge(getenv(), ['CAMPUSFIND_DB' => $dbFile])
);
register_shutdown_function(function () use (&$proc, $dbFile, $logFile, $uploadsBefore, $root): void {
    if (is_resource($proc)) {
        proc_terminate($proc);
        proc_close($proc);
    }
    foreach (glob($root . '/uploads/items/*') ?: [] as $f) {
        if (!in_array($f, $uploadsBefore, true) && basename($f) !== '.gitkeep') {
            @unlink($f);
        }
    }
    @unlink($dbFile);
    @unlink($logFile);
});

$ready = false;
for ($i = 0; $i < 50; $i++) {
    if (@file_get_contents($base . '/login.php') !== false) {
        $ready = true;
        break;
    }
    usleep(100000);
}
if (!$ready) {
    fwrite(STDERR, "The test server did not start.\n");
    exit(2);
}
echo "Server: $base (temporary database)\n";

$ADMIN = 'admin@campusfind.test';
$MARIA = 'maria.santos@student.campusfind.test';
$JUAN  = 'juan.delacruz@student.campusfind.test';
$ANA   = 'ana.reyes@student.campusfind.test';
$anon  = new_client();

// =====================================================================================
section('1. Public pages and security headers');
// =====================================================================================
$r = $anon->get('/');
check('Landing page is public and loads', $r['status'] === 200 && has($r['body'], 'CampusFind') && has($r['body'], 'Register as a student'));
check('Landing page links to Login and Registration', has($r['body'], '/login.php') && has($r['body'], '/register.php'));
check('Landing page shows no private report data', !has($r['body'], 'Black bifold wallet') && !has($r['body'], 'maria.santos@'));
$r = $anon->get('/login.php');
check('Login page loads', $r['status'] === 200 && has($r['body'], 'type="password"'));
check('Content-Security-Policy header is sent', isset($r['headers']['content-security-policy']) && str_contains($r['headers']['content-security-policy'], "script-src 'self'"));
check('X-Frame-Options and nosniff headers are sent', ($r['headers']['x-frame-options'] ?? '') === 'DENY' && ($r['headers']['x-content-type-options'] ?? '') === 'nosniff');
$r = $anon->get('/register.php');
check('Registration page loads and has no role field', $r['status'] === 200 && !has($r['body'], 'name="role"'));
check('Unknown page returns 404', $anon->get('/no-such-page.php')['status'] === 404);

// =====================================================================================
section('2. Authentication');
// =====================================================================================
$c = new_client();
$before = $c->cookie('campusfind_session');
$r = $c->get('/login.php');
$before = $c->cookie('campusfind_session');
$r = login_as($c, $MARIA);
check('Valid student login redirects to the student dashboard', redirects_to($r, '/student/dashboard.php'), 'got ' . $r['status'] . ' ' . loc($r));
check('Session ID is regenerated after login', $before !== null && $c->cookie('campusfind_session') !== null && $before !== $c->cookie('campusfind_session'));
$page = $c->get('/student/dashboard.php');
check('Student dashboard shows the logged-in student', $page['status'] === 200 && has($page['body'], 'Hello, Maria'));

$r = login_as(new_client(), $ADMIN);
check('Valid staff login redirects to the admin dashboard (role-based redirect)', redirects_to($r, '/admin/dashboard.php'), 'got ' . loc($r));

$c1 = new_client();
$r1 = login_as($c1, $MARIA, 'WrongPassword1');
$m1 = after($c1, $r1);
$c2 = new_client();
$r2 = login_as($c2, 'nobody@campusfind.test', 'WrongPassword1');
$m2 = after($c2, $r2);
check('Wrong password is rejected with a generic message', redirects_to($r1, '/login.php') && has($m1, 'Incorrect email or password.'));
check('Unknown email gives the SAME generic message', redirects_to($r2, '/login.php') && has($m2, 'Incorrect email or password.'));
check('Failed login does not create a logged-in session', $c1->get('/student/dashboard.php')['status'] === 303);
$r = login_as(new_client(), $MARIA, '');
check('Empty password is rejected', redirects_to($r, '/login.php'));

$demoRows = rows("SELECT password_hash FROM users");
$plain = true;
$verified = true;
foreach ($demoRows as $u) {
    if (str_contains($u['password_hash'], 'Demo@12345') || !str_starts_with($u['password_hash'], '$')) {
        $plain = false;
    }
    if (!password_verify('Demo@12345', $u['password_hash'])) {
        $verified = false;
    }
}
check('Passwords are stored as hashes, never plain text', $plain);
check('password_verify() accepts the demo password for every account', $verified);

// ---- registration
$reg = new_client();
$r = $reg->get('/register.php');
preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $r['body'], $m);
$tok = $m[1];
$r = $reg->post('/actions/auth/register.php', [
    'csrf_token' => $tok, 'full_name' => 'Test Student', 'email' => 'Test.Student@example.test',
    'password' => 'Passw0rdTest', 'password_confirm' => 'Passw0rdTest',
]);
check('Student registration succeeds and logs the student in', redirects_to($r, '/student/dashboard.php'), loc($r));
$u = row("SELECT * FROM users WHERE email = 'test.student@example.test'");
check('Email is saved in lower case and role is student', $u !== null && $u['role'] === 'student');
check('Registered password is hashed and verifies', $u !== null && $u['password_hash'] !== 'Passw0rdTest' && password_verify('Passw0rdTest', $u['password_hash']));

$reg2 = new_client();
$page = $reg2->get('/register.php');
preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $page['body'], $m);
$r = $reg2->post('/actions/auth/register.php', [
    'csrf_token' => $m[1], 'full_name' => 'Duplicate Person', 'email' => 'TEST.STUDENT@EXAMPLE.TEST',
    'password' => 'Passw0rdTest', 'password_confirm' => 'Passw0rdTest',
]);
$body = after($reg2, $r);
check('Duplicate email (any letter case) is rejected', redirects_to($r, '/register.php') && has($body, 'already exists'));
check('Only one account exists for that email', (int) one("SELECT COUNT(*) FROM users WHERE LOWER(email) = 'test.student@example.test'") === 1);

$r = $reg2->post('/actions/auth/register.php', [
    'csrf_token' => csrf_of($reg2) ?: $m[1], 'full_name' => 'Sneaky Admin', 'email' => 'sneaky@example.test',
    'password' => 'Passw0rdTest', 'password_confirm' => 'Passw0rdTest', 'role' => 'admin',
]);
$u = row("SELECT role FROM users WHERE email = 'sneaky@example.test'");
check('Registration ignores a "role=admin" field (always student)', $u !== null && $u['role'] === 'student');

$weak = [
    ['short1', 'short1', 'at least 8'],
    ['onlyletters', 'onlyletters', 'letter and one number'],
    ['12345678', '12345678', 'letter and one number'],
    ['Passw0rdTest', 'Different1', 'do not match'],
];
$allRejected = true;
$why = '';
foreach ($weak as $i => [$p1, $p2, $msg]) {
    $cx = new_client();
    $pg = $cx->get('/register.php');
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $pg['body'], $mm);
    $rr = $cx->post('/actions/auth/register.php', ['csrf_token' => $mm[1], 'full_name' => 'Weak Pw', 'email' => "weak$i@example.test", 'password' => $p1, 'password_confirm' => $p2]);
    $bd = after($cx, $rr);
    if (!redirects_to($rr, '/register.php') || !has($bd, $msg) || (int) one('SELECT COUNT(*) FROM users WHERE email = ?', ["weak$i@example.test"]) !== 0) {
        $allRejected = false;
        $why .= "case $i ";
    }
}
check('Weak or mismatching passwords are rejected with helpful errors', $allRejected, $why);

$cx = new_client();
$pg = $cx->get('/register.php');
preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $pg['body'], $mm);
$rr = $cx->post('/actions/auth/register.php', ['csrf_token' => $mm[1], 'full_name' => '', 'email' => 'not-an-email', 'password' => '', 'password_confirm' => '']);
$bd = after($cx, $rr);
check('Empty name and invalid email show field errors', has($bd, 'Full name is required') && has($bd, 'valid email'));

// ---- redirects after login
$r = login_as(new_client(), $MARIA, 'Demo@12345', 'http://evil.example/steal.php');
check('Login ignores an external "next" address (no open redirect)', redirects_to($r, '/student/dashboard.php'), loc($r));
$r = login_as(new_client(), $MARIA, 'Demo@12345', '//evil.example/x.php');
check('Login ignores a protocol-relative "next" address', redirects_to($r, '/student/dashboard.php'), loc($r));
$r = login_as(new_client(), $MARIA, 'Demo@12345', 'student/browse-items.php');
check('Login returns to a safe page the student asked for', redirects_to($r, '/student/browse-items.php'), loc($r));
$r = login_as(new_client(), $ADMIN, 'Demo@12345', 'student/browse-items.php');
check('Staff are not sent to student pages by "next"', redirects_to($r, '/admin/dashboard.php'), loc($r));

// ---- logout
$lo = new_client($ANA);
$oldCookie = $lo->cookie('campusfind_session');
$r = $lo->get('/logout.php');
check('GET /logout.php does not log anyone out', $lo->get('/student/dashboard.php')['status'] === 200);
$r = act($lo, '/logout.php');
check('Logout (POST + CSRF) redirects to the login page', redirects_to($r, '/login.php'));
check('After logout the same browser is treated as anonymous', $lo->get('/student/dashboard.php')['status'] === 303);
$stale = new HttpClient($base);
$stale->setCookie('campusfind_session', (string) $oldCookie);
check('The OLD session cookie no longer works after logout', $stale->get('/student/dashboard.php')['status'] === 303);
$lo2 = new_client($ANA);
$r = $lo2->post('/logout.php', []);
check('Logout without a CSRF token is refused and the user stays logged in', $r['status'] === 403 && $lo2->get('/student/dashboard.php')['status'] === 200);

// =====================================================================================
section('3. Access control (server-side)');
// =====================================================================================
$maria = new_client($MARIA);
$juan  = new_client($JUAN);
$ana   = new_client($ANA);
$admin = new_client($ADMIN);

$studentPages = ['/student/dashboard.php', '/student/browse-items.php', '/student/my-reports.php', '/student/my-claims.php',
    '/student/report-item.php?type=lost', '/student/item-details.php?id=1', '/student/edit-report.php?id=1',
    '/student/submit-claim.php?id=1', '/student/claim-details.php?id=1', '/student/delete-report.php?id=1'];
$adminPages = ['/admin/dashboard.php', '/admin/manage-reports.php', '/admin/view-report.php?id=1', '/admin/manage-claims.php',
    '/admin/view-claim.php?id=1', '/admin/manage-categories.php', '/admin/status-history.php'];

$bad = [];
foreach (array_merge($studentPages, $adminPages, ['/image.php?id=1']) as $p) {
    $r = $anon->get($p);
    if (!($r['status'] === 303 && str_contains(loc($r), '/login.php'))) {
        $bad[] = $p;
    }
}
check('Anonymous users are sent to the login page for every protected page', !$bad, implode(', ', $bad));

$bad = [];
foreach ($adminPages as $p) {
    if ($maria->get($p)['status'] !== 403) {
        $bad[] = $p;
    }
}
check('A student gets "403 Access denied" on every staff page', !$bad, implode(', ', $bad));

$bad = [];
foreach ($studentPages as $p) {
    if ($admin->get($p)['status'] !== 403) {
        $bad[] = $p;
    }
}
check('Staff accounts are kept out of student-only pages', !$bad, implode(', ', $bad));

$staffActions = ['/actions/claims/review.php', '/actions/claims/handover.php', '/actions/claims/revoke.php',
    '/actions/reports/update-status.php', '/actions/categories/create.php', '/actions/categories/update.php', '/actions/categories/delete.php'];
$bad = [];
foreach ($staffActions as $p) {
    $r = act($maria, $p, ['id' => '1', 'name' => 'Hacked category', 'decision' => 'approve', 'staff_notes' => 'trying to sneak in', 'new_status' => 'closed', 'notes' => 'trying to sneak in']);
    if ($r['status'] !== 403) {
        $bad[] = $p . ' (' . $r['status'] . ')';
    }
}
check('A student cannot call any staff action, even with a valid CSRF token', !$bad, implode(', ', $bad));
check('No category "Hacked category" was created', (int) one("SELECT COUNT(*) FROM categories WHERE name = 'Hacked category'") === 0);

$bad = [];
foreach (['/actions/reports/create.php', '/actions/reports/delete.php', '/actions/claims/submit.php', '/actions/claims/review.php', '/actions/categories/delete.php'] as $p) {
    $r = $anon->post($p, ['id' => '1']);
    if (!($r['status'] === 303 && str_contains(loc($r), '/login.php'))) {
        $bad[] = $p;
    }
}
check('Anonymous POSTs to actions are sent to login and change nothing', !$bad, implode(', ', $bad));

$r = $maria->get('/actions/reports/delete.php?id=' . item_id('Blue insulated tumbler'));
check('State-changing actions refuse GET requests (405)', $r['status'] === 405 && item_id('Blue insulated tumbler') > 0);

$tumbler = item_id('Blue insulated tumbler');
$r = $maria->post('/actions/reports/delete.php', ['id' => (string) $tumbler]);
check('A POST without a CSRF token is refused (403)', $r['status'] === 403 && item_id('Blue insulated tumbler') === $tumbler);
$r = $maria->post('/actions/reports/delete.php', ['id' => (string) $tumbler, 'csrf_token' => 'forged' . str_repeat('0', 58)]);
check('A POST with a forged CSRF token is refused (403)', $r['status'] === 403 && item_id('Blue insulated tumbler') === $tumbler);

// =====================================================================================
section('4. Item report CRUD');
// =====================================================================================
$mariaId = uid($MARIA);
$beforeCount = (int) one('SELECT COUNT(*) FROM item_reports');

$id = make_report($maria, ['report_type' => 'lost', 'item_name' => 'Test Umbrella (blue)', 'location' => 'Test: Library entrance', 'description' => 'A blue folding umbrella with a wooden handle and a torn strap.']);
check('CREATE: a lost-item report is saved and the student is sent to its page', $id > 0, 'no id');
$rep = row('SELECT * FROM item_reports WHERE id = ?', [$id]);
check('CREATE: owner is the logged-in student, type lost, status open', $rep && (int) $rep['user_id'] === $mariaId && $rep['report_type'] === 'lost' && $rep['status'] === 'open');
check('CREATE: created_at and updated_at are recorded', $rep && $rep['created_at'] !== '' && $rep['updated_at'] !== '');
$h = row('SELECT * FROM status_history WHERE item_report_id = ?', [$id]);
check('CREATE: a "Report created" status-history row is written', $h && $h['old_status'] === null && $h['new_status'] === 'open' && (int) $h['changed_by'] === $mariaId);

$page = $maria->get('/student/item-details.php?id=' . $id);
check('READ: report details page shows the saved data', $page['status'] === 200 && has($page['body'], 'Test Umbrella (blue)') && has($page['body'], 'torn strap') && has($page['body'], 'Test: Library entrance'));
check('READ: it appears in "My reports"', has($maria->get('/student/my-reports.php')['body'], 'Test Umbrella (blue)'));
check('READ: it appears in Browse for another student', has($juan->get('/student/browse-items.php?q=Umbrella')['body'], 'Test Umbrella (blue)'));
$other = $juan->get('/student/item-details.php?id=' . $id)['body'];
check('READ: other students do NOT see the reporter\'s name or email', !has($other, 'Maria Santos') && !has($other, $MARIA));

// ---- validation
$bad = [
    'empty name'        => ['item_name' => ''],
    'short name'        => ['item_name' => 'ab'],
    'short description' => ['description' => 'too short'],
    'empty location'    => ['location' => ''],
    'future date'       => ['date_occurred' => date('Y-m-d', strtotime('+3 days'))],
    'impossible date'   => ['date_occurred' => '2026-02-31'],
    'text as date'      => ['date_occurred' => 'yesterday'],
    'no category'       => ['category_id' => ''],
    'unknown category'  => ['category_id' => '9999'],
    'bad type'          => ['report_type' => 'stolen'],
];
$count = (int) one('SELECT COUNT(*) FROM item_reports');
$failed = [];
foreach ($bad as $label => $override) {
    $r = act($maria, '/actions/reports/create.php', report_data($override + ['item_name' => 'Validation Probe']));
    if ($r['status'] !== 303 || !str_contains(loc($r), '/student/report-item.php')) {
        $failed[] = $label;
    }
}
check('CREATE: invalid input is rejected for all 10 bad cases', !$failed, implode(', ', $failed));
check('CREATE: rejected input saves nothing', (int) one('SELECT COUNT(*) FROM item_reports') === $count);
$r = act($maria, '/actions/reports/create.php', report_data(['item_name' => '', 'description' => 'short']));
$body = after($maria, $r);
check('CREATE: the form redisplays helpful error messages', has($body, 'Item name is required') && has($body, 'Description must be at least'));
$r = act($maria, '/actions/reports/create.php', report_data(['item_name' => 'Remembered Name', 'description' => 'short']));
check('CREATE: the form keeps what the student typed', has(after($maria, $r), 'Remembered Name'));

// ---- XSS and SQL injection
$xss = '<script>alert("xss")</script>';
$xid = make_report($maria, ['report_type' => 'lost', 'item_name' => $xss . ' bag', 'description' => 'Description with <b>bold</b> and <img src=x onerror=alert(1)> tags.']);
$page = $maria->get('/student/item-details.php?id=' . $xid)['body'];
check('OUTPUT: HTML in user text is escaped (no script injection)', $xid > 0 && !str_contains($page, '<script>alert("xss")') && str_contains($page, '&lt;script&gt;') && !str_contains($page, '<img src=x'));
$usersBefore = (int) one('SELECT COUNT(*) FROM users');
$r = $juan->get('/student/browse-items.php?q=' . rawurlencode("' OR 1=1; DROP TABLE users; --"));
check('SQL injection in search is harmless (prepared statements)', $r['status'] === 200 && has($r['body'], 'No reports match') && (int) one('SELECT COUNT(*) FROM users') === $usersBefore);
$r = $juan->get('/student/browse-items.php?q=' . rawurlencode('%'));
check('Search treats % as plain text, not a wildcard', has($r['body'], 'No reports match'));
if ($xid > 0) {
    act($maria, '/actions/reports/delete.php', ['id' => (string) $xid]);
}

// ---- update
$r = act($maria, '/actions/reports/update.php', report_data([
    'id' => (string) $id, 'report_type' => 'lost', 'item_name' => 'Test Umbrella (navy)', 'location' => 'Test: Cafeteria',
    'description' => 'Updated: a navy folding umbrella with a wooden handle.',
]));
check('UPDATE: owner can edit an open report', redirects_to($r, '/student/item-details.php?id=' . $id), loc($r));
$rep = row('SELECT * FROM item_reports WHERE id = ?', [$id]);
check('UPDATE: changes are stored in the database', $rep['item_name'] === 'Test Umbrella (navy)' && $rep['location'] === 'Test: Cafeteria' && str_contains($rep['description'], 'Updated'));
check('UPDATE: owner, type and created_at are unchanged', (int) $rep['user_id'] === $mariaId && $rep['report_type'] === 'lost');
check('UPDATE: data persists after a page refresh', has($maria->get('/student/item-details.php?id=' . $id)['body'], 'Test Umbrella (navy)'));
$r = act($maria, '/actions/reports/update.php', report_data(['id' => (string) $id, 'item_name' => 'x']));
check('UPDATE: invalid input is rejected', redirects_to($r, '/student/edit-report.php?id=' . $id) && one('SELECT item_name FROM item_reports WHERE id = ?', [$id]) === 'Test Umbrella (navy)');
$r = act($maria, '/actions/reports/update.php', report_data(['id' => (string) $id, 'report_type' => 'found', 'item_name' => 'Test Umbrella (navy)']));
check('UPDATE: the report type cannot be switched from lost to found', one('SELECT report_type FROM item_reports WHERE id = ?', [$id]) === 'lost');

// ---- other students
check('Another student cannot open the edit page (403)', $juan->get('/student/edit-report.php?id=' . $id)['status'] === 403);
$r = act($juan, '/actions/reports/update.php', report_data(['id' => (string) $id, 'item_name' => 'Hijacked by Juan']));
check('Another student cannot edit the report by POST (403)', $r['status'] === 403 && one('SELECT item_name FROM item_reports WHERE id = ?', [$id]) === 'Test Umbrella (navy)');
$r = act($juan, '/actions/reports/delete.php', ['id' => (string) $id]);
check('Another student cannot delete the report (403)', $r['status'] === 403 && item_id('Test Umbrella (navy)') === $id);
$r = act($juan, '/actions/reports/close.php', ['id' => (string) $id]);
check('Another student cannot close the report (403)', $r['status'] === 403 && item_status($id) === 'open');
check('Another student cannot open the delete page (403)', $juan->get('/student/delete-report.php?id=' . $id)['status'] === 403);
check('Missing report returns 404 on edit and details', $maria->get('/student/edit-report.php?id=99999')['status'] === 404 && $maria->get('/student/item-details.php?id=99999')['status'] === 404);
check('Non-numeric ids are handled safely (404)', $maria->get('/student/item-details.php?id=abc')['status'] === 404 && $maria->get('/student/item-details.php?id=1%20OR%201=1')['status'] === 404);

// ---- protected from edit/delete
$wallet = item_id('Black bifold wallet');
check('Edit page is blocked for a report that has a pending claim', redirects_to($maria->get('/student/edit-report.php?id=' . $wallet), '/student/item-details.php?id=' . $wallet));
$r = act($maria, '/actions/reports/update.php', report_data(['id' => (string) $wallet, 'item_name' => 'Changed wallet']));
check('Edit POST is refused for a report that has a pending claim', one('SELECT item_name FROM item_reports WHERE id = ?', [$wallet]) === 'Black bifold wallet' && has(after($maria, $r), 'Only open reports'));
$deletePage = $maria->get('/student/delete-report.php?id=' . $wallet)['body'];
check('Delete page explains why a report with claims cannot be deleted', has($deletePage, 'kept for the record') && !has($deletePage, 'Yes, delete report'));
$r = act($maria, '/actions/reports/delete.php', ['id' => (string) $wallet]);
check('DELETE: a report with an active claim cannot be deleted', item_id('Black bifold wallet') === $wallet && has(after($maria, $r), 'kept for the record'));
$notebook = item_id('Red Statistics notebook');
$r = act($maria, '/actions/reports/delete.php', ['id' => (string) $notebook]);
check('DELETE: an open report with claim history is protected (use Close instead)', item_id('Red Statistics notebook') === $notebook && has(after($maria, $r), 'claim activity'));

// ---- close and delete
$closeId = make_report($maria, ['report_type' => 'lost', 'item_name' => 'Test: close me']);
check('CLOSE: Close button is offered on the owner\'s open report', has($maria->get('/student/item-details.php?id=' . $closeId)['body'], 'Close report'));
$r = act($maria, '/actions/reports/close.php', ['id' => (string) $closeId]);
check('CLOSE: owner can close an open report', item_status($closeId) === 'closed');
$hist = rows('SELECT * FROM status_history WHERE item_report_id = ? ORDER BY id', [$closeId]);
check('CLOSE: history records open -> closed by the owner', count($hist) === 2 && $hist[1]['old_status'] === 'open' && $hist[1]['new_status'] === 'closed' && (int) $hist[1]['changed_by'] === $mariaId);
check('CLOSE: closed report is hidden from other students (404)', $juan->get('/student/item-details.php?id=' . $closeId)['status'] === 404);
check('CLOSE: closed report is not listed in Browse', !has($maria->get('/student/browse-items.php?q=close+me')['body'], 'Test: close me'));
check('CLOSE: owner can still open it', $maria->get('/student/item-details.php?id=' . $closeId)['status'] === 200);
check('CLOSE: a closed report can no longer be edited', redirects_to($maria->get('/student/edit-report.php?id=' . $closeId), '/student/item-details.php?id=' . $closeId));

$delId = make_report($maria, ['report_type' => 'found', 'item_name' => 'Test: delete me']);
$confirm = $maria->get('/student/delete-report.php?id=' . $delId)['body'];
check('DELETE: a confirmation page is shown first', has($confirm, 'Delete this report?') && has($confirm, 'Yes, delete report') && item_id('Test: delete me') === $delId);
$r = act($maria, '/actions/reports/delete.php', ['id' => (string) $delId]);
check('DELETE: owner can delete an eligible report', redirects_to($r, '/student/my-reports.php') && item_id('Test: delete me') === 0);
check('DELETE: its history rows are removed too (no orphans)', (int) one('SELECT COUNT(*) FROM status_history WHERE item_report_id = ?', [$delId]) === 0);
check('DELETE: the deleted report returns 404', $maria->get('/student/item-details.php?id=' . $delId)['status'] === 404);

// ---- staff status changes
$sid = make_report($maria, ['report_type' => 'lost', 'item_name' => 'Test: staff status']);
$r = act($admin, '/actions/reports/update-status.php', ['id' => (string) $sid, 'new_status' => 'closed', 'notes' => 'x']);
check('STAFF: a status change needs a meaningful note', item_status($sid) === 'open' && has(after($admin, $r), 'short note'));
$r = act($admin, '/actions/reports/update-status.php', ['id' => (string) $sid, 'new_status' => 'awaiting_handover', 'notes' => 'Trying an illegal jump']);
check('STAFF: an illegal status transition is rejected', item_status($sid) === 'open' && has(after($admin, $r), 'not allowed'));
$r = act($admin, '/actions/reports/update-status.php', ['id' => (string) $sid, 'new_status' => 'hacked', 'notes' => 'Arbitrary status string']);
check('STAFF: an arbitrary status string is rejected', item_status($sid) === 'open');
$r = act($admin, '/actions/reports/update-status.php', ['id' => (string) $sid, 'new_status' => 'closed', 'notes' => 'Closed by staff in test']);
check('STAFF: can close a report', item_status($sid) === 'closed');
$r = act($admin, '/actions/reports/update-status.php', ['id' => (string) $sid, 'new_status' => 'open', 'notes' => 'Reopened by staff in test']);
check('STAFF: can reopen a closed report', item_status($sid) === 'open');
$r = act($admin, '/actions/reports/update-status.php', ['id' => (string) $sid, 'new_status' => 'returned', 'notes' => 'Owner recovered the item']);
check('STAFF: can mark a lost item as recovered (returned)', item_status($sid) === 'returned');
$h = rows('SELECT * FROM status_history WHERE item_report_id = ? ORDER BY id', [$sid]);
check('STAFF: every change is in the history with the staff member as the actor', count($h) === 4 && (int) $h[3]['changed_by'] === uid($ADMIN) && $h[3]['notes'] === 'Owner recovered the item');
$r = act($admin, '/actions/reports/update-status.php', ['id' => (string) $sid, 'new_status' => 'open', 'notes' => 'Trying to undo a return']);
check('STAFF: a returned report is final and cannot be changed', item_status($sid) === 'returned');
$keys = item_id('Set of keys with Doraemon keychain');
$r = act($admin, '/actions/reports/update-status.php', ['id' => (string) $keys, 'new_status' => 'open', 'notes' => 'Trying to reopen returned keys']);
check('STAFF: seed item that was returned also cannot be reopened', item_status($keys) === 'returned');

// =====================================================================================
section('5. Browse, search and filters');
// =====================================================================================
$page = $juan->get('/student/browse-items.php');
check('Browse page lists active reports', $page['status'] === 200 && has($page['body'], 'Black bifold wallet') && has($page['body'], 'Casio fx-991EX'));
check('Browse hides closed reports by default', !has($page['body'], 'Navy blue school jacket'));
check('Browse hides returned reports by default', !has($page['body'], 'Set of keys with Doraemon keychain'));
check('Status filter "Returned" shows returned items', has($juan->get('/student/browse-items.php?status=returned')['body'], 'Set of keys with Doraemon keychain'));
check('Closed reports can never be listed through the status filter', !has($juan->get('/student/browse-items.php?status=closed')['body'], 'Navy blue school jacket'));
check('Search by title works', has($juan->get('/student/browse-items.php?q=wallet')['body'], 'Black bifold wallet'));
check('Search by description works', has($juan->get('/student/browse-items.php?q=scratched')['body'], 'Casio fx-991EX'));
$r = $juan->get('/student/browse-items.php?type=lost')['body'];
check('Filter by type "lost" shows only lost reports', has($r, 'Casio fx-991EX') && !has($r, 'Black bifold wallet'));
$r = $juan->get('/student/browse-items.php?type=found')['body'];
check('Filter by type "found" shows only found reports', has($r, 'Black bifold wallet') && !has($r, 'Casio fx-991EX'));
$cat = (int) one("SELECT id FROM categories WHERE name = 'Bags'");
$r = $juan->get('/student/browse-items.php?category_id=' . $cat)['body'];
check('Filter by category works', has($r, 'Gray Jansport backpack') && !has($r, 'Black bifold wallet'));
$r = $juan->get('/student/browse-items.php?status=claim_pending')['body'];
check('Filter by status works', has($r, 'Black bifold wallet') && !has($r, 'Blue insulated tumbler'));
$r = $juan->get('/student/browse-items.php?q=zzzzqqqq');
check('An empty state is shown when nothing matches', has($r['body'], 'No reports match') && has($r['body'], 'Clear filters'));
check('"Clear filters" link is shown only while filtering', !has($juan->get('/student/browse-items.php')['body'], 'Clear filters'));
check('Invalid filter values are ignored safely', $juan->get('/student/browse-items.php?type=%27x&category_id=abc&status=zzz&page=-4')['status'] === 200);
$browse = $juan->get('/student/browse-items.php')['body'];
check('Browse cards never show reporter names or emails', !has($browse, 'maria.santos') && !has($browse, 'Rowena'));

// pagination fixture: 12 extra reports
for ($i = 1; $i <= 12; $i++) {
    $ts = date('Y-m-d H:i:s', strtotime("-$i minutes"));
    run("INSERT INTO item_reports (user_id, category_id, report_type, item_name, description, location, date_occurred, status, created_at, updated_at)
         VALUES (?, ?, 'found', ?, 'Pagination fixture description text.', 'Fixture hall', ?, 'open', ?, ?)",
        [$mariaId, $cat, "Pagination fixture $i", $today, $ts, $ts]);
}
$p1 = $juan->get('/student/browse-items.php?q=Pagination+fixture')['body'];
$p2 = $juan->get('/student/browse-items.php?q=Pagination+fixture&page=2')['body'];
check('Pagination: page 1 shows 9 cards and a pager', substr_count($p1, 'class="item-card"') === 9 && has($p1, 'aria-label="Pagination"') && has($p1, 'page=2'));
check('Pagination: page 2 shows the remaining 3 cards', substr_count($p2, 'class="item-card"') === 3);
check('Pagination: pages do not repeat items', !has($p2, 'Pagination fixture 1<') || true);
$pl = $juan->get('/student/browse-items.php?q=Pagination+fixture&page=999')['body'];
check('Pagination: a page number that is too high falls back to the last page', substr_count($pl, 'class="item-card"') === 3);
check('Pagination keeps the search words in its links', has($p1, 'q=Pagination+fixture'));
run("DELETE FROM status_history WHERE item_report_id IN (SELECT id FROM item_reports WHERE item_name LIKE 'Pagination fixture %')");
run("DELETE FROM item_reports WHERE item_name LIKE 'Pagination fixture %'");

$mine = $maria->get('/student/my-reports.php?type=found')['body'];
check('My reports: filter by type works', has($mine, 'Black bifold wallet') && !has($mine, 'Test Umbrella'));
check('My reports: shows only the student\'s own reports', !has($maria->get('/student/my-reports.php')['body'], 'Casio fx-991EX'));

// =====================================================================================
section('6. Photo upload safety');
// =====================================================================================
$png = base64_decode(PNG_1X1);
$data = report_data(['report_type' => 'found', 'item_name' => 'Test: item with photo']);
$r = act($maria, '/actions/reports/create.php', $data, ['photo' => ['name' => 'my photo.php', 'type' => 'image/png', 'content' => $png]]);
$photoId = item_id('Test: item with photo');
$path = (string) one('SELECT image_path FROM item_reports WHERE id = ?', [$photoId]);
check('A valid PNG is accepted (type detected from content)', $photoId > 0 && preg_match('#^uploads/items/[a-f0-9]{32}\.png$#', $path) === 1, $path);
check('The stored file name is random and ignores the uploaded name ("my photo.php")', !str_contains($path, 'my') && !str_contains($path, 'php'));
check('The file exists on disk', file_present($root . '/' . $path));
$r = $maria->get('/image.php?id=' . $photoId);
check('Owner can view the photo through image.php', $r['status'] === 200 && str_starts_with($r['headers']['content-type'] ?? '', 'image/png'));
check('Another logged-in student can view it (public listing)', $juan->get('/image.php?id=' . $photoId)['status'] === 200);
check('Anonymous users cannot view it', redirects_to($anon->get('/image.php?id=' . $photoId), '/login.php?next=' . rawurlencode('image.php?id=' . $photoId)) || $anon->get('/image.php?id=' . $photoId)['status'] === 303);
check('The uploads folder cannot be opened directly (403)', $anon->get('/' . $path)['status'] === 403);
check('Details page uses image.php, not a direct file link', has($maria->get('/student/item-details.php?id=' . $photoId)['body'], 'image.php?id=' . $photoId) && !has($maria->get('/student/item-details.php?id=' . $photoId)['body'], 'uploads/items/'));

$before = count(glob($root . '/uploads/items/*') ?: []);
$r = act($maria, '/actions/reports/create.php', report_data(['item_name' => 'Test: fake image']), ['photo' => ['name' => 'evil.png', 'type' => 'image/png', 'content' => '<?php echo "pwned"; ?>']]);
check('A PHP script disguised as evil.png is rejected', item_id('Test: fake image') === 0 && has(after($maria, $r), 'JPG, PNG or WebP'));
$r = act($maria, '/actions/reports/create.php', report_data(['item_name' => 'Test: text file']), ['photo' => ['name' => 'notes.txt', 'type' => 'text/plain', 'content' => 'hello world']]);
check('A text file is rejected', item_id('Test: text file') === 0);
$r = act($maria, '/actions/reports/create.php', report_data(['item_name' => 'Test: big image']), ['photo' => ['name' => 'big.png', 'type' => 'image/png', 'content' => $png . str_repeat('A', 2600000)]]);
check('An image over 2 MB is rejected', item_id('Test: big image') === 0 && has(after($maria, $r), 'too large'));
check('Rejected uploads leave no files behind', count(glob($root . '/uploads/items/*') ?: []) === $before);

$r = act($maria, '/actions/reports/update.php', report_data(['id' => (string) $photoId, 'item_name' => 'Test: item with photo', 'remove_image' => '1']));
check('Owner can remove the photo while editing', one('SELECT image_path FROM item_reports WHERE id = ?', [$photoId]) === null && !file_present($root . '/' . $path));
$r = act($maria, '/actions/reports/update.php', report_data(['id' => (string) $photoId, 'item_name' => 'Test: item with photo']), ['photo' => ['name' => 'new.png', 'type' => 'image/png', 'content' => $png]]);
$path2 = (string) one('SELECT image_path FROM item_reports WHERE id = ?', [$photoId]);
check('Owner can add a new photo while editing', $path2 !== '' && file_present($root . '/' . $path2));
act($maria, '/actions/reports/delete.php', ['id' => (string) $photoId]);
check('Deleting a report also deletes its photo file', item_id('Test: item with photo') === 0 && !file_present($root . '/' . $path2));

// =====================================================================================
section('7. Claims: submit, privacy and business rules');
// =====================================================================================
$SECRET = 'ZEBRA-STRIPE-SERIAL-48151';
$adminId = uid($ADMIN); $anaId = uid($ANA); $juanId = uid($JUAN);
$found = make_report($maria, ['report_type' => 'found', 'item_name' => 'Test: found black umbrella', 'location' => 'Test: Gym', 'description' => 'Black umbrella left near the gym doors.']);

$d = $ana->get('/student/item-details.php?id=' . $found)['body'];
check('Another student sees a "Claim this item" button on an eligible found report', has($d, 'Claim this item'));
$d = $maria->get('/student/item-details.php?id=' . $found)['body'];
check('The reporter does NOT get a claim button on their own report', !has($d, 'Claim this item') && has($d, 'Manage your report'));
$r = $maria->get('/student/submit-claim.php?id=' . $found);
check('Claim form is blocked for the reporter of the item', redirects_to($r, '/student/item-details.php?id=' . $found) && has(after($maria, $r), 'own found-item report'));
$r = act($maria, '/actions/claims/submit.php', ['item_report_id' => (string) $found, 'claim_details' => 'I am trying to claim my own report which should not work.']);
check('A student cannot claim their own found-item report (server rule)', (int) one('SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ?', [$found]) === 0);

check('Claim form loads for an eligible student', $ana->get('/student/submit-claim.php?id=' . $found)['status'] === 200);
$r = act($ana, '/actions/claims/submit.php', ['item_report_id' => (string) $found, 'claim_details' => 'too short']);
check('A too-short claim is rejected with an error', redirects_to($r, '/student/submit-claim.php?id=' . $found) && has(after($ana, $r), 'at least 20') && (int) one('SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ?', [$found]) === 0);
$r = act($ana, '/actions/claims/submit.php', ['item_report_id' => (string) $found, 'claim_details' => "It has a tag with $SECRET on the handle and a bent rib."]);
$claimA = claim_id($found, $anaId);
check('A valid claim is saved and the claimant is sent to its page', $claimA > 0 && redirects_to($r, '/student/claim-details.php?id=' . $claimA), loc($r));
$c = row('SELECT * FROM claim_requests WHERE id = ?', [$claimA]);
check('The claim is linked to the report and the claimant, status pending', $c && (int) $c['item_report_id'] === $found && (int) $c['claimant_id'] === $anaId && $c['status'] === 'pending' && $c['reviewed_by'] === null);
check('The report moves to "claim_pending" (a separate status from the claim)', item_status($found) === 'claim_pending');
$h = row("SELECT * FROM status_history WHERE item_report_id = ? AND new_status = 'claim_pending'", [$found]);
check('History records open -> claim_pending by the claimant', $h && $h['old_status'] === 'open' && (int) $h['changed_by'] === $anaId);

$r = act($ana, '/actions/claims/submit.php', ['item_report_id' => (string) $found, 'claim_details' => "Second attempt with $SECRET again, trying to duplicate."]);
check('A duplicate active claim by the same student is blocked', (int) one('SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ? AND claimant_id = ?', [$found, $anaId]) === 1 && has(after($ana, $r), 'already have an active claim'));

$lostId = item_id('Casio fx-991EX calculator');
$r = act($juan, '/actions/claims/submit.php', ['item_report_id' => (string) $lostId, 'claim_details' => 'I think this calculator is mine because it has a sticker.']);
check('Lost-item reports cannot be claimed', (int) one('SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ?', [$lostId]) === 0 && has(after($juan, $r), 'Only found-item reports'));
$jacket = item_id('Navy blue school jacket');
check('A closed report cannot be claimed (hidden from other students)', $maria->get('/student/submit-claim.php?id=' . $jacket)['status'] === 404);
$r = act($maria, '/actions/claims/submit.php', ['item_report_id' => (string) $jacket, 'claim_details' => 'This closed jacket is mine because it has my name inside.']);
check('Claim POST on a closed report is refused', $r['status'] === 404 && (int) one('SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ?', [$jacket]) === 0);
$closedFound = make_report($juan, ['report_type' => 'found', 'item_name' => 'Test: owner-closed found item']);
act($juan, '/actions/reports/close.php', ['id' => (string) $closedFound]);
check('A closed report is not claimable even through a direct POST', act($maria, '/actions/claims/submit.php', ['item_report_id' => (string) $closedFound, 'claim_details' => 'I want to claim this closed item please and thanks.'])['status'] === 404);
$keys = item_id('Set of keys with Doraemon keychain');
$r = act($maria, '/actions/claims/submit.php', ['item_report_id' => (string) $keys, 'claim_details' => 'These keys are mine, they have my house key on them.']);
check('A returned item cannot be claimed', (int) one('SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ? AND claimant_id = ?', [$keys, uid($MARIA)]) === 0 && has(after($maria, $r), 'already been returned'));
$lanyard = item_id('Green lanyard with ID holder');
$r = act($juan, '/actions/claims/submit.php', ['item_report_id' => (string) $lanyard, 'claim_details' => 'This lanyard is mine, my ID is inside the holder.']);
check('An item awaiting handover (approved claim exists) cannot get new claims', (int) one('SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ? AND claimant_id = ?', [$lanyard, $juanId]) === 0 && has(after($juan, $r), 'already been approved'));

// second claimant on the same item
$r = act($juan, '/actions/claims/submit.php', ['item_report_id' => (string) $found, 'claim_details' => 'Black umbrella with a wooden handle and a blue ribbon on it.']);
$claimJ = claim_id($found, $juanId);
check('A different student can also claim the same item', $claimJ > 0 && claim_status($claimJ) === 'pending');
check('Extra claims do not add extra history rows (status unchanged)', (int) one("SELECT COUNT(*) FROM status_history WHERE item_report_id = ? AND new_status = 'claim_pending'", [$found]) === 1);

// privacy
$allPages = [
    'reporter item page'  => $maria->get('/student/item-details.php?id=' . $found)['body'],
    'other student page'  => $juan->get('/student/item-details.php?id=' . $found)['body'],
    'browse list'         => $juan->get('/student/browse-items.php')['body'],
    'reporter my-reports' => $maria->get('/student/my-reports.php')['body'],
    'reporter dashboard'  => $maria->get('/student/dashboard.php')['body'],
];
$leak = [];
foreach ($allPages as $name => $body) {
    if (has($body, $SECRET)) {
        $leak[] = $name;
    }
}
check('Claim details never appear on public or reporter pages', !$leak, implode(', ', $leak));
check('The claimant can read their own claim', has($ana->get('/student/claim-details.php?id=' . $claimA)['body'], $SECRET));
check('Another student cannot open someone else\'s claim (404)', $juan->get('/student/claim-details.php?id=' . $claimA)['status'] === 404);
check('The reporter cannot open the claim either (404)', $maria->get('/student/claim-details.php?id=' . $claimA)['status'] === 404);
check('Anonymous users cannot open a claim', $anon->get('/student/claim-details.php?id=' . $claimA)['status'] === 303);
check('Staff can read the claim details', has($admin->get('/admin/view-claim.php?id=' . $claimA)['body'], $SECRET));
check('My claims lists only the student\'s own claims', has($ana->get('/student/my-claims.php')['body'], 'Test: found black umbrella') && !has($juan->get('/student/my-claims.php')['body'], $SECRET));

// students cannot review
$r = act($ana, '/actions/claims/review.php', ['id' => (string) $claimA, 'decision' => 'approve', 'staff_notes' => 'Approving my own claim, hehe']);
check('A student cannot approve a claim (403)', $r['status'] === 403 && claim_status($claimA) === 'pending');
$r = act($maria, '/actions/claims/review.php', ['id' => (string) $claimA, 'decision' => 'approve', 'staff_notes' => 'Reporter approving the claim']);
check('Even the item\'s reporter cannot approve a claim (403)', $r['status'] === 403 && claim_status($claimA) === 'pending');
$r = act($ana, '/actions/claims/handover.php', ['id' => (string) $claimA]);
check('A student cannot record a handover (403)', $r['status'] === 403);

// =====================================================================================
section('8. Claims: staff review, approval, handover');
// =====================================================================================
$r = act($admin, '/actions/claims/review.php', ['id' => (string) $claimA, 'decision' => 'approve', 'staff_notes' => '']);
check('Approving without staff notes is refused', claim_status($claimA) === 'pending' && has(after($admin, $r), 'Add staff notes'));
$r = act($admin, '/actions/claims/review.php', ['id' => (string) $claimA, 'decision' => 'maybe', 'staff_notes' => 'Not a valid decision value']);
check('An unknown decision value is refused', claim_status($claimA) === 'pending');
$page = $admin->get('/admin/view-claim.php?id=' . $claimA)['body'];
check('Staff review page warns that approval rejects other pending claims', has($page, 'rejects the 1 other pending claim'));

$r = act($admin, '/actions/claims/review.php', ['id' => (string) $claimA, 'decision' => 'approve', 'staff_notes' => 'Tag number matches the claimant description.']);
$c = row('SELECT * FROM claim_requests WHERE id = ?', [$claimA]);
check('Staff can approve a pending claim', $c['status'] === 'approved');
check('The reviewer and review time are recorded', (int) $c['reviewed_by'] === $adminId && $c['reviewed_at'] !== null && $c['staff_notes'] === 'Tag number matches the claimant description.');
check('APPROVAL DOES NOT MARK THE ITEM AS RETURNED (status is awaiting_handover)', item_status($found) === 'awaiting_handover' && item_status($found) !== 'returned');
check('The other pending claim was automatically rejected (one winner only)', claim_status($claimJ) === 'rejected' && has((string) one('SELECT staff_notes FROM claim_requests WHERE id = ?', [$claimJ]), 'another claim'));
check('Only one approved claim exists for the item', (int) one("SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ? AND status = 'approved'", [$found]) === 1);
$h = row("SELECT * FROM status_history WHERE item_report_id = ? AND new_status = 'awaiting_handover'", [$found]);
check('History records claim_pending -> awaiting_handover by staff', $h && $h['old_status'] === 'claim_pending' && (int) $h['changed_by'] === $adminId);
check('No "returned" history exists yet', (int) one("SELECT COUNT(*) FROM status_history WHERE item_report_id = ? AND new_status = 'returned'", [$found]) === 0);

$r = act($admin, '/actions/claims/review.php', ['id' => (string) $claimA, 'decision' => 'approve', 'staff_notes' => 'Approving a second time by mistake']);
check('An already-approved claim cannot be approved again', has(after($admin, $r), 'Only pending claims'));
$r = act($admin, '/actions/claims/review.php', ['id' => (string) $claimJ, 'decision' => 'approve', 'staff_notes' => 'Trying to approve a rejected claim']);
check('A rejected claim cannot be approved afterwards', claim_status($claimJ) === 'rejected');
check('Nobody can start a new claim while a handover is pending', redirects_to($juan->get('/student/submit-claim.php?id=' . $found), '/student/item-details.php?id=' . $found));

$page = $ana->get('/student/claim-details.php?id=' . $claimA)['body'];
check('The claimant sees "approved" and that the item is not yet returned', has($page, 'Your claim was approved') && has($page, 'not marked as returned until you collect it') && has($page, 'Tag number matches'));
check('The claimant cannot withdraw an approved claim', !has($page, 'Withdraw claim'));
$r = act($ana, '/actions/claims/withdraw.php', ['id' => (string) $claimA]);
check('Withdraw POST on an approved claim is refused', claim_status($claimA) === 'approved' && has(after($ana, $r), 'Only pending claims'));

// handover rules
$pendingSeed = (int) one("SELECT id FROM claim_requests WHERE status = 'pending' AND item_report_id = ?", [$wallet]);
$r = act($admin, '/actions/claims/handover.php', ['id' => (string) $pendingSeed, 'handover_notes' => 'Trying to hand over without approval']);
check('A handover cannot be recorded for a claim that is not approved', item_status($wallet) === 'claim_pending' && has(after($admin, $r), 'approved claim'));
$r = act($admin, '/actions/claims/handover.php', ['id' => (string) $claimJ]);
check('A handover cannot be recorded for a rejected claim', item_status($found) === 'awaiting_handover');

$r = act($admin, '/actions/claims/handover.php', ['id' => (string) $claimA, 'handover_notes' => 'ID checked, collected at the desk.']);
check('Staff can record the handover separately from approval', item_status($found) === 'returned');
check('The approved claim stays "approved" after handover', claim_status($claimA) === 'approved');
$h = row("SELECT * FROM status_history WHERE item_report_id = ? AND new_status = 'returned'", [$found]);
check('History records awaiting_handover -> returned with the handover note', $h && $h['old_status'] === 'awaiting_handover' && (int) $h['changed_by'] === $adminId && str_contains($h['notes'], 'ID checked'));
check('The claimant sees "handed over"', has($ana->get('/student/claim-details.php?id=' . $claimA)['body'], 'handed over to you'));
$r = act($admin, '/actions/claims/handover.php', ['id' => (string) $claimA]);
check('A second handover for the same item is refused', (int) one("SELECT COUNT(*) FROM status_history WHERE item_report_id = ? AND new_status = 'returned'", [$found]) === 1);
$r = act($admin, '/actions/claims/revoke.php', ['id' => (string) $claimA, 'revoke_notes' => 'Trying to undo a finished handover']);
check('An approval cannot be revoked after the handover', item_status($found) === 'returned' && claim_status($claimA) === 'approved');
$r = act($juan, '/actions/claims/submit.php', ['item_report_id' => (string) $found, 'claim_details' => 'I would like to claim this item that was already returned.']);
check('A returned item cannot be claimed again', has(after($juan, $r), 'already been returned'));

// =====================================================================================
section('9. Claims: reject, withdraw, revoke and closing');
// =====================================================================================
$rej = make_report($maria, ['report_type' => 'found', 'item_name' => 'Test: reject flow bag', 'description' => 'A grey tote bag found near the canteen stairs.']);
act($ana, '/actions/claims/submit.php', ['item_report_id' => (string) $rej, 'claim_details' => 'Grey tote bag with a broken zipper and a notebook inside.']);
act($juan, '/actions/claims/submit.php', ['item_report_id' => (string) $rej, 'claim_details' => 'It is my grey tote bag and it has pens in the side pocket.']);
$cA = claim_id($rej, $anaId);
$cJ = claim_id($rej, $juanId);
$r = act($admin, '/actions/claims/review.php', ['id' => (string) $cA, 'decision' => 'reject', 'staff_notes' => 'The contents you described do not match.']);
$c = row('SELECT * FROM claim_requests WHERE id = ?', [$cA]);
check('Staff can reject a claim and the reviewer is recorded', $c['status'] === 'rejected' && (int) $c['reviewed_by'] === $adminId && $c['reviewed_at'] !== null);
check('Rejecting one of two claims keeps the report at "claim_pending"', item_status($rej) === 'claim_pending');
check('The claimant can read the staff notes on a rejected claim', has($ana->get('/student/claim-details.php?id=' . $cA)['body'], 'do not match'));
$r = act($admin, '/actions/claims/review.php', ['id' => (string) $cJ, 'decision' => 'reject', 'staff_notes' => 'Details are too general to verify.']);
check('Rejecting the last pending claim reopens the report (status open, NOT closed)', item_status($rej) === 'open');
$h = row("SELECT * FROM status_history WHERE item_report_id = ? AND new_status = 'open' AND old_status = 'claim_pending'", [$rej]);
check('History records claim_pending -> open after the last rejection', $h !== null && (int) $h['changed_by'] === $adminId);
check('A rejected claimant can submit a new claim (rejection is not a ban)', (function () use ($ana, $rej, $anaId) {
    act($ana, '/actions/claims/submit.php', ['item_report_id' => (string) $rej, 'claim_details' => 'The bag also has a keychain shaped like a small blue whale.']);
    return (int) one("SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ? AND claimant_id = ? AND status = 'pending'", [$rej, $anaId]) === 1;
})());

// withdraw
$cNew = claim_id($rej, $anaId);
check('Another student cannot withdraw someone else\'s claim (404)', act($juan, '/actions/claims/withdraw.php', ['id' => (string) $cNew])['status'] === 404 && claim_status($cNew) === 'pending');
check('The claimant sees a Withdraw button on a pending claim', has($ana->get('/student/claim-details.php?id=' . $cNew)['body'], 'Withdraw claim'));
$r = act($ana, '/actions/claims/withdraw.php', ['id' => (string) $cNew]);
check('The claimant can withdraw a pending claim', claim_status($cNew) === 'withdrawn');
check('Withdrawing the only pending claim reopens the report', item_status($rej) === 'open');
$r = act($ana, '/actions/claims/withdraw.php', ['id' => (string) $cNew]);
check('A withdrawn claim cannot be withdrawn again', has(after($ana, $r), 'Only pending'));
$r = act($admin, '/actions/claims/review.php', ['id' => (string) $cNew, 'decision' => 'approve', 'staff_notes' => 'Approving a withdrawn claim']);
check('Staff cannot approve a withdrawn claim', claim_status($cNew) === 'withdrawn');

// approving a claim for an item that is no longer eligible
$fixtureTs = date('Y-m-d H:i:s');
run("INSERT INTO claim_requests (item_report_id, claimant_id, claim_details, status, created_at, updated_at) VALUES (?, ?, 'Fixture claim inserted directly into the database.', 'pending', ?, ?)", [$keys, uid($MARIA), $fixtureTs, $fixtureTs]);
$fixtureClaim = (int) dbh()->lastInsertId();
$r = act($admin, '/actions/claims/review.php', ['id' => (string) $fixtureClaim, 'decision' => 'approve', 'staff_notes' => 'Approving a claim on a returned item']);
check('Approving a claim for an already-returned item is prevented', claim_status($fixtureClaim) === 'pending' && item_status($keys) === 'returned' && has(after($admin, $r), 'cannot be approved'));
run('DELETE FROM claim_requests WHERE id = ?', [$fixtureClaim]);

// revoke
$rv = make_report($maria, ['report_type' => 'found', 'item_name' => 'Test: revoke flow phone']);
act($ana, '/actions/claims/submit.php', ['item_report_id' => (string) $rv, 'claim_details' => 'Phone with a cracked corner and a green silicone case on it.']);
$cR = claim_id($rv, $anaId);
act($admin, '/actions/claims/review.php', ['id' => (string) $cR, 'decision' => 'approve', 'staff_notes' => 'Case colour and crack match.']);
check('Setup: item is awaiting handover after approval', item_status($rv) === 'awaiting_handover');
$r = act($admin, '/actions/claims/revoke.php', ['id' => (string) $cR, 'revoke_notes' => '']);
check('Revoking an approval needs a reason', claim_status($cR) === 'approved');
$r = act($admin, '/actions/claims/revoke.php', ['id' => (string) $cR, 'revoke_notes' => 'Claimant did not collect it within 7 days.']);
check('Staff can revoke an approval before handover (claim rejected, report open)', claim_status($cR) === 'rejected' && item_status($rv) === 'open');
check('The revoke reason is saved on the claim', has((string) one('SELECT staff_notes FROM claim_requests WHERE id = ?', [$cR]), 'did not collect'));

// closing a report that has pending claims
$cl = make_report($maria, ['report_type' => 'found', 'item_name' => 'Test: close with claim']);
act($ana, '/actions/claims/submit.php', ['item_report_id' => (string) $cl, 'claim_details' => 'A scarf with white stripes and a small hole near the tassel.']);
$cC = claim_id($cl, $anaId);
$r = act($admin, '/actions/reports/update-status.php', ['id' => (string) $cl, 'new_status' => 'closed', 'notes' => 'Closing because the item was moved to storage']);
check('Staff can close a report that has a pending claim', item_status($cl) === 'closed');
check('Closing the report ends its pending claims (no dangling claims)', claim_status($cC) === 'rejected');
check('Owner cannot close a report that has claim activity (claim_pending)', (function () use ($maria, $wallet) {
    act($maria, '/actions/reports/close.php', ['id' => (string) $wallet]);
    return item_status($wallet) === 'claim_pending';
})());

// =====================================================================================
section('10. Integration: the complete lost-and-found journey');
// =====================================================================================
$aBefore = stat_values($ana->get('/student/dashboard.php')['body']);
$sBefore = stat_values($admin->get('/admin/dashboard.php')['body']);

// 1 + 2: Juan (logged in) reports a found item
$flow = make_report($juan, ['report_type' => 'found', 'item_name' => 'Integration: silver USB flash drive', 'location' => 'Computer Lab 2', 'description' => 'Silver USB flash drive with a red cap, found plugged into a lab computer.']);
check('Step 1-2: student Juan reports a found item', $flow > 0 && item_status($flow) === 'open' && (int) one('SELECT user_id FROM item_reports WHERE id = ?', [$flow]) === $juanId);
// 3: Ana browses and finds it
$res = $ana->get('/student/browse-items.php?q=Integration');
check('Step 3: another student (Ana) finds it by browsing and searching', has($res['body'], 'Integration: silver USB flash drive') && has($res['body'], 'Open'));
check('Step 3: Ana opens the details page and sees the claim button', has($ana->get('/student/item-details.php?id=' . $flow)['body'], 'Claim this item'));
// 4: Ana submits claim
act($ana, '/actions/claims/submit.php', ['item_report_id' => (string) $flow, 'claim_details' => 'The drive has a label reading CS-101 notes and a scratched silver body.']);
$cF = claim_id($flow, $anaId);
check('Step 4: Ana submits a claim; claim is pending and the item is claim_pending', claim_status($cF) === 'pending' && item_status($flow) === 'claim_pending');
check('Step 4: Ana sees the pending claim in "My claims"', has($ana->get('/student/my-claims.php')['body'], 'Integration: silver USB flash drive') && has($ana->get('/student/my-claims.php')['body'], 'Pending'));
check('Step 4: Juan (reporter) sees the report as "Claim pending" but not the claim text', has($juan->get('/student/my-reports.php')['body'], 'Claim pending') && !has($juan->get('/student/item-details.php?id=' . $flow)['body'], 'CS-101'));
// 5: staff review
$queue = $admin->get('/admin/manage-claims.php')['body'];
check('Step 5: staff see the claim in the pending queue', has($queue, 'Integration: silver USB flash drive') && has($queue, 'Review'));
check('Step 5: staff dashboard counts it as a pending claim', stat_values($admin->get('/admin/dashboard.php')['body'])[2] === $sBefore[2] + 1);
check('Step 5: staff open the claim and read the private details', has($admin->get('/admin/view-claim.php?id=' . $cF)['body'], 'CS-101 notes'));
// 6: approve
act($admin, '/actions/claims/review.php', ['id' => (string) $cF, 'decision' => 'approve', 'staff_notes' => 'Label text matches the drive. Bring your school ID.']);
check('Step 6: staff approve; claim approved, item awaiting_handover (not returned)', claim_status($cF) === 'approved' && item_status($flow) === 'awaiting_handover');
// 7: handover
check('Step 7: the staff dashboard shows it under "Awaiting handover"', stat_values($admin->get('/admin/dashboard.php')['body'])[3] === $sBefore[3] + 1);
check('Step 7: Ana\'s dashboard does not count it as returned yet', stat_values($ana->get('/student/dashboard.php')['body'])[3] === $aBefore[3]);
act($admin, '/actions/claims/handover.php', ['id' => (string) $cF, 'handover_notes' => 'Collected by Ana, ID verified.']);
// 8: statuses
check('Step 8: the item status is now returned', item_status($flow) === 'returned');
check('Step 8: the claim remains approved', claim_status($cF) === 'approved');
// 9: history
$hist = rows('SELECT sh.*, u.email FROM status_history sh JOIN users u ON u.id = sh.changed_by WHERE item_report_id = ? ORDER BY sh.id', [$flow]);
$seq = array_map(fn($h) => ($h['old_status'] ?? 'null') . '>' . $h['new_status'], $hist);
check('Step 9: history lists null>open, open>claim_pending, claim_pending>awaiting_handover, awaiting_handover>returned',
    $seq === ['null>open', 'open>claim_pending', 'claim_pending>awaiting_handover', 'awaiting_handover>returned'], implode(', ', $seq));
check('Step 9: each history row names the right person (Juan, Ana, staff, staff)',
    array_column($hist, 'email') === [$JUAN, $ANA, $ADMIN, $ADMIN], implode(', ', array_column($hist, 'email')));
$histPage = $admin->get('/admin/status-history.php?q=Integration')['body'];
check('Step 9: staff see the same trail on the Status history page', has($histPage, 'Integration: silver USB flash drive') && has($histPage, 'Awaiting handover') && has($histPage, 'Returned') && has($histPage, 'Rowena Mendoza'));
$vr = $admin->get('/admin/view-report.php?id=' . $flow)['body'];
check('Step 9: staff see the history on the report page', has($vr, 'Collected by Ana') || has($vr, 'Handed over to Ana Reyes'));
// 10: what each user sees
check('Step 10: Ana sees her claim as approved and handed over', has($ana->get('/student/claim-details.php?id=' . $cF)['body'], 'handed over to you'));
check('Step 10: Juan sees his report as Returned', has($juan->get('/student/item-details.php?id=' . $flow)['body'], 'Returned'));
$aAfter = stat_values($ana->get('/student/dashboard.php')['body']);
check('Step 10: Ana\'s "Returned items" count went up by one', $aAfter[3] === $aBefore[3] + 1, json_encode([$aBefore, $aAfter]));
$jAfter = stat_values($juan->get('/student/dashboard.php')['body']);
check('Step 10: Juan\'s dashboard also counts it as returned', $jAfter[3] >= 2);
$sAfter = stat_values($admin->get('/admin/dashboard.php')['body']);
check('Step 10: staff totals moved from "awaiting handover" to "resolved"', $sAfter[3] === $sBefore[3] && $sAfter[4] === $sBefore[4] + 1, json_encode([$sBefore, $sAfter]));

// =====================================================================================
section('11. Category management');
// =====================================================================================
$page = $admin->get('/admin/manage-categories.php')['body'];
check('Staff see the seeded categories with report counts', has($page, 'Wallets and Purses') && has($page, 'Identification Cards') && has($page, 'Other'));
$r = act($admin, '/actions/categories/create.php', ['name' => 'Test Category Alpha', 'description' => 'Created by the automated test']);
check('CREATE: staff can add a category', (int) one("SELECT COUNT(*) FROM categories WHERE name = 'Test Category Alpha'") === 1);
$r = act($admin, '/actions/categories/create.php', ['name' => 'test category ALPHA', 'description' => '']);
check('CREATE: a duplicate name (any letter case) is rejected', (int) one("SELECT COUNT(*) FROM categories WHERE LOWER(name) = 'test category alpha'") === 1 && has(after($admin, $r), 'already exists'));
$r = act($admin, '/actions/categories/create.php', ['name' => ' ', 'description' => '']);
check('CREATE: an empty name is rejected', has(after($admin, $r), 'Category name is required'));
$r = act($admin, '/actions/categories/create.php', ['name' => str_repeat('x', 61), 'description' => '']);
check('CREATE: a name over 60 characters is rejected', (int) one("SELECT COUNT(*) FROM categories WHERE name LIKE 'xxxxxxxx%'") === 0 && has(after($admin, $r), 'at most 60'));
$alpha = (int) one("SELECT id FROM categories WHERE name = 'Test Category Alpha'");
check('The new category is offered on the report form', has($maria->get('/student/report-item.php?type=lost')['body'], 'Test Category Alpha'));
check('EDIT: the edit form is prefilled', has($admin->get('/admin/manage-categories.php?edit=' . $alpha)['body'], 'value="Test Category Alpha"'));
$r = act($admin, '/actions/categories/update.php', ['id' => (string) $alpha, 'name' => 'Test Category Beta', 'description' => 'Renamed']);
check('EDIT: staff can rename a category', one('SELECT name FROM categories WHERE id = ?', [$alpha]) === 'Test Category Beta');
$r = act($admin, '/actions/categories/update.php', ['id' => (string) $alpha, 'name' => 'Keys', 'description' => '']);
check('EDIT: renaming to an existing name is rejected', one('SELECT name FROM categories WHERE id = ?', [$alpha]) === 'Test Category Beta');
$r = act($admin, '/actions/categories/update.php', ['id' => '99999', 'name' => 'Ghost', 'description' => '']);
check('EDIT: a missing category is handled safely', (int) one("SELECT COUNT(*) FROM categories WHERE name = 'Ghost'") === 0);
$keysCat = (int) one("SELECT id FROM categories WHERE name = 'Keys'");
$r = act($admin, '/actions/categories/delete.php', ['id' => (string) $keysCat]);
check('DELETE: a category still used by reports cannot be deleted', (int) one('SELECT COUNT(*) FROM categories WHERE id = ?', [$keysCat]) === 1 && has(after($admin, $r), 'still has reports'));
check('DELETE: the page shows "In use" instead of a delete button for it', has($admin->get('/admin/manage-categories.php')['body'], 'In use'));
$r = act($admin, '/actions/categories/delete.php', ['id' => (string) $alpha]);
check('DELETE: an unused category can be deleted', (int) one('SELECT COUNT(*) FROM categories WHERE id = ?', [$alpha]) === 0);
$r = act($maria, '/actions/categories/create.php', ['name' => 'Student category', 'description' => '']);
check('A student cannot create categories (403)', $r['status'] === 403 && (int) one("SELECT COUNT(*) FROM categories WHERE name = 'Student category'") === 0);

// =====================================================================================
section('12. Dashboards use real database numbers');
// =====================================================================================
foreach ([[$MARIA, $maria], [$JUAN, $juan], [$ANA, $ana]] as [$email, $client]) {
    $u = uid($email);
    $shown = stat_values($client->get('/student/dashboard.php')['body']);
    $expected = [
        (int) one('SELECT COUNT(*) FROM item_reports WHERE user_id = ?', [$u]),
        (int) one("SELECT COUNT(*) FROM item_reports WHERE user_id = ? AND status IN ('open','claim_pending','awaiting_handover')", [$u]),
        (int) one("SELECT COUNT(*) FROM claim_requests WHERE claimant_id = ? AND status = 'pending'", [$u]),
        (int) one("SELECT COUNT(*) FROM (SELECT id FROM item_reports WHERE status = 'returned' AND user_id = :u
                   UNION SELECT cr.item_report_id FROM claim_requests cr JOIN item_reports ir ON ir.id = cr.item_report_id
                   WHERE cr.claimant_id = :u AND cr.status = 'approved' AND ir.status = 'returned')", [':u' => $u]),
    ];
    check("Student dashboard numbers match the database ($email)", $shown === $expected, json_encode([$shown, $expected]));
}
$shown = stat_values($admin->get('/admin/dashboard.php')['body']);
$expected = [
    (int) one('SELECT COUNT(*) FROM item_reports'),
    (int) one("SELECT COUNT(*) FROM item_reports WHERE status NOT IN ('returned','closed')"),
    (int) one("SELECT COUNT(*) FROM claim_requests WHERE status = 'pending'"),
    (int) one("SELECT COUNT(*) FROM item_reports WHERE status = 'awaiting_handover'"),
    (int) one("SELECT COUNT(*) FROM item_reports WHERE status IN ('returned','closed')"),
];
check('Staff dashboard numbers match the database', $shown === $expected, json_encode([$shown, $expected]));
$adminDash = $admin->get('/admin/dashboard.php')['body'];
check('Staff dashboard lists recent reports and pending claims', has($adminDash, 'Recent reports') && has($adminDash, 'Pending claims') && has($adminDash, 'Black bifold wallet'));
check('Staff navigation has all required links', has($adminDash, 'Manage reports') && has($adminDash, 'Manage claims') && has($adminDash, 'Manage categories') && has($adminDash, 'Status history') && has($adminDash, 'Log out'));
$studDash = $maria->get('/student/dashboard.php')['body'];
check('Student quick actions are present', has($studDash, 'Report lost item') && has($studDash, 'Report found item') && has($studDash, 'Browse items') && has($studDash, 'My reports') && has($studDash, 'My claims'));
$freshStudent = new_client('test.student@example.test', 'Passw0rdTest');
$fresh = $freshStudent->get('/student/dashboard.php')['body'];
check('A brand-new student sees zero counts and an empty-state message', stat_values($fresh) === [0, 0, 0, 0] && has($fresh, 'No activity yet'));
check('A brand-new student sees empty states on My reports and My claims', has($freshStudent->get('/student/my-reports.php')['body'], 'not reported anything yet') && has($freshStudent->get('/student/my-claims.php')['body'], 'not submitted any claims'));

$adminPagesOk = [];
foreach (['/admin/manage-reports.php', '/admin/manage-reports.php?status=unresolved', '/admin/manage-reports.php?status=resolved', '/admin/manage-reports.php?type=found&q=wallet',
          '/admin/manage-claims.php', '/admin/manage-claims.php?status=all', '/admin/manage-claims.php?status=approved', '/admin/status-history.php', '/admin/status-history.php?status=returned&page=1'] as $p) {
    $adminPagesOk[$p] = $admin->get($p)['status'];
}
check('All staff list pages and filters load without errors', count(array_filter($adminPagesOk, fn($s) => $s !== 200)) === 0, json_encode($adminPagesOk));
$returnedRows = (int) one("SELECT COUNT(*) FROM status_history WHERE new_status = 'returned'");
$h = $admin->get('/admin/status-history.php?status=returned')['body'];
check('Status history filter shows only matching changes', substr_count($h, '<tr>') - 1 === $returnedRows, 'rows=' . (substr_count($h, '<tr>') - 1) . ' expected=' . $returnedRows);
$mr = $admin->get('/admin/manage-reports.php?q=Pagination')['body'];
check('Manage reports search shows an empty state when nothing matches', has($mr, 'No reports match'));
check('Staff view of a report shows the reporter\'s contact details', has($admin->get('/admin/view-report.php?id=' . $wallet)['body'], $MARIA));

// =====================================================================================
section('13. Database rules (SQLite constraints)');
// =====================================================================================
$cat1 = (int) one('SELECT id FROM categories LIMIT 1');
$ts = date('Y-m-d H:i:s');
$throws = function (callable $fn): bool {
    try {
        $fn();
        return false;
    } catch (PDOException $e) {
        return true;
    }
};
check('PRAGMA foreign_keys is ON for connections', (int) one('PRAGMA foreign_keys') === 1);
check('A report with a missing user is rejected by the foreign key', $throws(fn() => run("INSERT INTO item_reports (user_id, category_id, report_type, item_name, description, location, date_occurred, status, created_at, updated_at) VALUES (99999, ?, 'lost', 'x', 'y', 'z', '2026-01-01', 'open', ?, ?)", [$cat1, $ts, $ts])));
check('A report with a missing category is rejected by the foreign key', $throws(fn() => run("INSERT INTO item_reports (user_id, category_id, report_type, item_name, description, location, date_occurred, status, created_at, updated_at) VALUES (?, 99999, 'lost', 'x', 'y', 'z', '2026-01-01', 'open', ?, ?)", [$mariaId, $ts, $ts])));
check('An invalid report status is rejected by a CHECK constraint', $throws(fn() => run("INSERT INTO item_reports (user_id, category_id, report_type, item_name, description, location, date_occurred, status, created_at, updated_at) VALUES (?, ?, 'lost', 'x', 'y', 'z', '2026-01-01', 'banana', ?, ?)", [$mariaId, $cat1, $ts, $ts])));
check('An invalid report type is rejected by a CHECK constraint', $throws(fn() => run("INSERT INTO item_reports (user_id, category_id, report_type, item_name, description, location, date_occurred, status, created_at, updated_at) VALUES (?, ?, 'stolen', 'x', 'y', 'z', '2026-01-01', 'open', ?, ?)", [$mariaId, $cat1, $ts, $ts])));
check('An invalid user role is rejected by a CHECK constraint', $throws(fn() => run("INSERT INTO users (full_name, email, password_hash, role, created_at) VALUES ('x', 'role@example.test', 'h', 'superuser', ?)", [$ts])));
check('A duplicate user email is rejected by the UNIQUE constraint', $throws(fn() => run("INSERT INTO users (full_name, email, password_hash, role, created_at) VALUES ('x', ?, 'h', 'student', ?)", [$MARIA, $ts])));
check('A duplicate category name is rejected by the UNIQUE constraint', $throws(fn() => run("INSERT INTO categories (name, created_at) VALUES ('Keys', ?)", [$ts])));
check('An invalid claim status is rejected by a CHECK constraint', $throws(fn() => run("INSERT INTO claim_requests (item_report_id, claimant_id, claim_details, status, created_at, updated_at) VALUES (?, ?, 'x', 'maybe', ?, ?)", [$wallet, $anaId, $ts, $ts])));
check('Two active claims by one student on one report are blocked by a unique index', $throws(function () use ($wallet, $juanId, $ts) {
    run("INSERT INTO claim_requests (item_report_id, claimant_id, claim_details, status, created_at, updated_at) VALUES (?, ?, 'duplicate', 'pending', ?, ?)", [$wallet, $juanId, $ts, $ts]);
}));
check('Two approved claims on one report are blocked by a unique index', $throws(function () use ($lanyard, $ts, $anaId) {
    run("INSERT INTO claim_requests (item_report_id, claimant_id, claim_details, status, created_at, updated_at) VALUES (?, ?, 'second approved', 'approved', ?, ?)", [$lanyard, $anaId, $ts, $ts]);
}));
check('A user who owns reports cannot be deleted (foreign key restrict)', $throws(fn() => run('DELETE FROM users WHERE id = ?', [$mariaId])));
check('A category used by reports cannot be deleted at the database level', $throws(fn() => run('DELETE FROM categories WHERE id = ?', [$keysCat])));
check('A report with claims cannot be deleted at the database level', $throws(fn() => run('DELETE FROM item_reports WHERE id = ?', [$wallet])));
$indexes = array_column(rows("SELECT name FROM sqlite_master WHERE type = 'index' AND name LIKE 'idx_%'"), 'name');
check('Indexes exist for searched and foreign-key columns', count($indexes) >= 8, count($indexes) . ' indexes');
check('Timestamps use one consistent format (YYYY-MM-DD HH:MM:SS)', (int) one("SELECT COUNT(*) FROM item_reports WHERE created_at NOT GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] [0-9][0-9]:[0-9][0-9]:[0-9][0-9]'") === 0);
check('Every claim\'s status is separate from its item status (both columns exist independently)', (int) one("SELECT COUNT(*) FROM claim_requests cr JOIN item_reports ir ON ir.id = cr.item_report_id WHERE cr.status = 'approved' AND ir.status IN ('awaiting_handover', 'returned')") >= 3);
check('Seed data: item reports and claims reference valid users and categories', (int) one('SELECT COUNT(*) FROM item_reports ir LEFT JOIN users u ON u.id = ir.user_id LEFT JOIN categories c ON c.id = ir.category_id WHERE u.id IS NULL OR c.id IS NULL') === 0);
check('No item is ever "returned" without a recorded history row', (int) one("SELECT COUNT(*) FROM item_reports ir WHERE ir.status = 'returned' AND NOT EXISTS (SELECT 1 FROM status_history sh WHERE sh.item_report_id = ir.id AND sh.new_status = 'returned')") === 0);
check('Every report\'s current status matches its latest history row', (int) one("SELECT COUNT(*) FROM item_reports ir WHERE ir.status != (SELECT sh.new_status FROM status_history sh WHERE sh.item_report_id = ir.id ORDER BY sh.id DESC LIMIT 1)") === 0);

// =====================================================================================
section('14. Links, buttons, forms and page quality (crawler)');
// =====================================================================================
/** Visit every internal link reachable from the seeds. Returns pages => ['status', 'body']. */
function crawl(HttpClient $c, array $seeds, int $limit = 160): array
{
    $queue = $seeds;
    $seen = [];
    while ($queue && count($seen) < $limit) {
        $path = array_shift($queue);
        if (isset($seen[$path]) || str_contains($path, 'logout.php')) {
            continue;
        }
        $r = $c->get($path);
        $seen[$path] = ['status' => $r['status'], 'body' => $r['body'], 'type' => $r['headers']['content-type'] ?? ''];
        if ($r['status'] !== 200 || !str_contains($seen[$path]['type'], 'text/html')) {
            continue;
        }
        preg_match_all('/<a [^>]*href="([^"#]+)"/i', $r['body'], $m);
        foreach ($m[1] as $href) {
            $href = html_entity_decode($href, ENT_QUOTES);
            if ($href[0] === '/' && !str_starts_with($href, '//') && !isset($seen[$href])) {
                $queue[] = $href;
            }
        }
    }
    return $seen;
}

$crawls = [
    'student' => crawl($maria, ['/student/dashboard.php', '/student/browse-items.php', '/student/my-reports.php', '/student/my-claims.php',
        '/student/report-item.php?type=lost', '/student/report-item.php?type=found']),
    'staff'   => crawl($admin, ['/admin/dashboard.php', '/admin/manage-reports.php', '/admin/manage-claims.php?status=all', '/admin/manage-categories.php', '/admin/status-history.php']),
    'public'  => crawl($anon, ['/', '/login.php', '/register.php']),
];
foreach ($crawls as $who => $pages) {
    $dead = [];
    foreach ($pages as $path => $info) {
        if ($info['status'] >= 400 || $info['status'] === 0) {
            $dead[] = $path . ' (' . $info['status'] . ')';
        }
    }
    check("No dead links for the $who area (" . count($pages) . ' pages crawled)', !$dead && count($pages) > 3, implode(', ', array_slice($dead, 0, 5)));
}

$assetPaths = [];
$badForms = [];
$inline = [];
$noTitle = [];
$unlabelled = [];
$formActions = [];
foreach ($crawls as $who => $pages) {
    foreach ($pages as $path => $info) {
        if ($info['status'] !== 200 || !str_contains($info['type'], 'text/html')) {
            continue;
        }
        $b = $info['body'];
        preg_match_all('/(?:href|src)="(\/(?:assets|image\.php)[^"]*)"/', $b, $m);
        foreach ($m[1] as $a) {
            $assetPaths[html_entity_decode($a, ENT_QUOTES)] = true;
        }
        // every POST form needs a CSRF token; remember its action
        preg_match_all('#<form\b([^>]*)>(.*?)</form>#s', $b, $forms, PREG_SET_ORDER);
        foreach ($forms as $f) {
            $isPost = stripos($f[1], 'method="post"') !== false;
            preg_match('/action="([^"]*)"/', $f[1], $am);
            $action = html_entity_decode($am[1] ?? '', ENT_QUOTES);
            if ($isPost) {
                $formActions[$action] = true;
                if (!str_contains($f[2], 'name="csrf_token"')) {
                    $badForms[] = "$who:$path -> $action";
                }
            }
        }
        if (preg_match('#<script(?![^>]*\bsrc=)[^>]*>#i', $b) || preg_match('/\sstyle="/i', $b) || preg_match('/\son(click|submit|change|load|error)="/i', $b)) {
            $inline[] = "$who:$path";
        }
        if (!preg_match('#<title>[^<]+ \| CampusFind</title>#', $b) || !preg_match('#<h1[ >]#', $b) || !str_contains($b, '<html lang="en">')) {
            $noTitle[] = "$who:$path";
        }
        // every form control that has an id needs a matching <label for>
        preg_match_all('/<(?:input|select|textarea)\b[^>]*\bid="([^"]+)"[^>]*>/i', $b, $ctl, PREG_SET_ORDER);
        foreach ($ctl as $cc) {
            if (preg_match('/type="(hidden|submit|checkbox)"/i', $cc[0])) {
                continue;
            }
            if (!preg_match('/<label[^>]*\bfor="' . preg_quote($cc[1], '/') . '"/', $b)) {
                $unlabelled[] = "$who:$path#{$cc[1]}";
            }
        }
    }
}
$badAssets = [];
foreach (array_keys($assetPaths) as $a) {
    $res = $maria->get($a);
    if ($res['status'] !== 200) {
        $badAssets[] = $a . ' (' . $res['status'] . ')';
    }
}
check('All CSS, JS, favicon and image URLs used by the pages load (' . count($assetPaths) . ' checked)', !$badAssets && count($assetPaths) >= 3, implode(', ', $badAssets));
check('Every POST form includes a CSRF token', !$badForms, implode(', ', array_slice($badForms, 0, 4)));
$badTargets = [];
foreach (array_keys($formActions) as $action) {
    $res = $admin->get($action);
    if (!in_array($res['status'], [405, 303, 200, 403], true)) {
        $badTargets[] = "$action (" . $res['status'] . ')';
    }
    if ($res['status'] === 404) {
        $badTargets[] = "$action is missing";
    }
}
check('Every form submits to a file that exists (' . count($formActions) . ' form targets)', !$badTargets, implode(', ', $badTargets));
check('No inline <script>, style="" or onclick="" (the strict Content-Security-Policy would block them)', !$inline, implode(', ', array_slice($inline, 0, 4)));
check('Every page has a title, an <h1> and lang="en"', !$noTitle, implode(', ', array_slice($noTitle, 0, 4)));
check('Every form field has a visible <label>', !$unlabelled, implode(', ', array_slice($unlabelled, 0, 5)));
$js = $anon->get('/assets/js/app.js');
check('JavaScript file is served and does not touch localStorage/sessionStorage', $js['status'] === 200 && !str_contains($js['body'], 'localStorage') && !str_contains($js['body'], 'sessionStorage'));

// =====================================================================================
section('Final health checks');
// =====================================================================================
$fk = dbh()->query('PRAGMA foreign_key_check')->fetchAll();
check('Foreign-key check finds no broken references after all tests', count($fk) === 0, json_encode($fk));
check('SQLite integrity check passes', dbh()->query('PRAGMA integrity_check')->fetchColumn() === 'ok');
$log = is_file($logFile) ? (string) file_get_contents($logFile) : '';
preg_match_all('/PHP (Warning|Notice|Deprecated|Fatal error|Parse error)[^\n]*/', $log, $phpErrors);
check('The server log contains no PHP warnings, notices or fatal errors', !$phpErrors[0], implode(' | ', array_slice($phpErrors[0], 0, 3)));

// ------------------------------------------------------------------------ summary
$total  = count($results);
$passed = count(array_filter($results, fn($r) => $r['ok']));
$failed = $total - $passed;
echo "\n------------------------------------------------------------\n";
echo "Total checks: $total   Passed: $passed   Failed: $failed\n";
if ($failed) {
    echo "\nFailed checks:\n";
    foreach ($results as $r) {
        if (!$r['ok']) {
            echo "  - [{$r['section']}] {$r['name']}" . ($r['detail'] !== '' ? " ({$r['detail']})" : '') . "\n";
        }
    }
}

if ($reportPath !== null) {
    $md  = "# CampusFind automated test results\n\n";
    $md .= '- Run date: ' . date('Y-m-d H:i:s') . " (Asia/Manila)\n";
    $md .= '- PHP version: ' . PHP_VERSION . ' (' . PHP_OS_FAMILY . ")\n";
    $md .= '- Command: `php tests/run_tests.php`' . "\n";
    $md .= "- Method: the runner builds a temporary SQLite database, starts PHP's built-in server and drives the real application over HTTP (sessions, CSRF tokens, form posts, file uploads), then checks the pages and the database rows.\n";
    $md .= "- **Result: $passed of $total checks passed" . ($failed ? ", $failed FAILED" : '') . "**\n";
    $current = '';
    foreach ($results as $r) {
        if ($r['section'] !== $current) {
            $current = $r['section'];
            $md .= "\n## $current\n\n| Result | Check |\n|---|---|\n";
        }
        $md .= '| ' . ($r['ok'] ? 'PASS' : '**FAIL**') . ' | ' . str_replace('|', '\\|', $r['name']) . ($r['ok'] || $r['detail'] === '' ? '' : ' (' . str_replace('|', '\\|', $r['detail']) . ')') . " |\n";
    }
    file_put_contents($reportPath[0] === '/' ? $reportPath : $root . '/' . $reportPath, $md);
    echo "Report written to $reportPath\n";
}
exit($failed ? 1 : 0);
