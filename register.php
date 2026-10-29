<?php
require_once __DIR__ . '/includes/init.php';

$user = current_user();
if ($user !== null) {
    redirect(dashboard_path_for($user));
}

$pageTitle = 'Student registration';
$layout    = 'public';
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="container">
    <div class="auth-card">
        <h1>Create a student account</h1>
        <p class="muted">Student accounts can report items and submit claims. Staff accounts are created by the school.</p>
        <?= error_summary() ?>

        <form method="post" action="<?= e(url('actions/auth/register.php')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="full_name">Full name</label>
                <input class="input" type="text" id="full_name" name="full_name" value="<?= old('full_name') ?>" maxlength="100" autocomplete="name" required<?= field_attrs('full_name') ?>>
                <?= field_error('full_name') ?>
            </div>
            <div class="form-group">
                <label for="email">School email</label>
                <input class="input" type="email" id="email" name="email" value="<?= old('email') ?>" maxlength="150" autocomplete="email" required<?= field_attrs('email') ?>>
                <?= field_error('email') ?>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input class="input" type="password" id="password" name="password" autocomplete="new-password" required aria-describedby="password-help"<?= field_attrs('password') ?>>
                    <button type="button" class="password-toggle" data-toggle-password="password" aria-label="Show password"><?= icon('eye') ?></button>
                </div>
                <p class="help-text" id="password-help">At least 8 characters, with a letter and a number.</p>
                <?= field_error('password') ?>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <input class="input" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required<?= field_attrs('password_confirm') ?>>
                <?= field_error('password_confirm') ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block" data-loading-text="Creating account...">Create account</button>
        </form>

        <p class="auth-alt">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
    </div>
</div>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
