<?php
require_once __DIR__ . '/includes/init.php';

$user = current_user();
if ($user !== null) {
    redirect(dashboard_path_for($user));
}

$pageTitle = 'Log in';
$layout    = 'public';
$next      = safe_app_path($_GET['next'] ?? '', '');
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="container">
    <div class="auth-card">
        <h1>Log in to CampusFind</h1>
        <p class="muted">Use the email and password you registered with.</p>

        <form method="post" action="<?= e(url('actions/auth/login.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">

            <div class="form-group">
                <label for="email">Email</label>
                <input class="input" type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" data-toggle-password="password" aria-label="Show password"><?= icon('eye') ?></button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block" data-loading-text="Logging in...">Log in</button>
        </form>

        <p class="auth-alt">New student? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
    </div>
</div>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
