<?php
require_once __DIR__ . '/../../includes/init.php';
require_post_with_csrf();

$email    = strtolower(clean_line($_POST['email'] ?? ''));
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$next     = safe_app_path($_POST['next'] ?? '', '');
$backUrl  = 'login.php' . ($next !== '' ? '?next=' . rawurlencode($next) : '');

remember_form(['email' => $email], []);

if ($email === '' || $password === '') {
    flash('error', 'Enter your email and password.');
    redirect($backUrl);
}

$user = find_user_by_email($email);

// Same amount of work whether or not the email exists, and ONE generic message for every failure.
$hash     = $user['password_hash'] ?? DUMMY_PASSWORD_HASH;
$verified = password_verify($password, $hash);

if ($user === null || !$verified) {
    flash('error', 'Incorrect email or password.');
    redirect($backUrl);
}

if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $stmt = db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $stmt->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['id']]);
}

login_user($user);                                  // also regenerates the session ID
unset($_SESSION['form_old'], $_SESSION['form_errors']);
flash('success', 'Welcome back, ' . $user['full_name'] . '.');

// Role-based redirect. "next" is only used when it is a safe page for this role.
$target = dashboard_path_for($user);
if ($next !== '') {
    $isAdminPath = strpos($next, 'admin/') === 0;
    $isStudentPath = strpos($next, 'student/') === 0;
    if (($user['role'] === 'admin' && $isAdminPath) || ($user['role'] === 'student' && $isStudentPath)) {
        $target = $next;
    }
}
redirect($target);
