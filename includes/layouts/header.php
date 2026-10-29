<?php
/**
 * Shared header. Pages set these variables BEFORE including it:
 *   $pageTitle  (string)  text for the <title> and tab
 *   $layout     ('app' | 'public')  app = sidebar layout for logged-in users (default)
 *   $activeNav  (string)  which sidebar link is highlighted
 */
$pageTitle = $pageTitle ?? 'CampusFind';
$activeNav = $activeNav ?? '';
$authUser  = current_user();
$layout    = $layout ?? 'app';
$showSidebar = $authUser !== null && $layout === 'app';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | CampusFind</title>
    <meta name="description" content="CampusFind is the campus lost-and-found system for students and school staff.">
    <link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="<?= $showSidebar ? 'has-sidebar' : 'public-page' ?>">
<a class="skip-link" href="#main">Skip to main content</a>

<?php if ($showSidebar): ?>
    <header class="topbar">
        <button type="button" class="icon-button" data-nav-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">
            <?= icon('menu') ?>
        </button>
        <a class="brand brand-light" href="<?= e(url(dashboard_path_for($authUser))) ?>">
            <span class="brand-mark"><?= icon('search', 'icon-sm') ?></span><span class="brand-name">CampusFind</span>
        </a>
    </header>
    <div class="nav-overlay" data-nav-overlay></div>

    <?php include APP_ROOT . '/includes/layouts/' . ($authUser['role'] === 'admin' ? 'admin-sidebar.php' : 'student-sidebar.php'); ?>

    <div class="app-main">
        <main id="main" class="content" tabindex="-1">
            <?php include APP_ROOT . '/includes/layouts/flash-messages.php'; ?>
<?php else: ?>
    <header class="public-header">
        <div class="container public-header-inner">
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark"><?= icon('search', 'icon-sm') ?></span><span class="brand-name">CampusFind</span>
            </a>
            <nav class="public-nav" aria-label="Main">
                <?php if ($authUser !== null): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e(url(dashboard_path_for($authUser))) ?>">My dashboard</a>
                    <form method="post" action="<?= e(url('logout.php')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-ghost btn-sm">Log out</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('login.php')) ?>">Log in</a>
                    <a class="btn btn-primary btn-sm" href="<?= e(url('register.php')) ?>">Create account</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main id="main" class="public-main" tabindex="-1">
        <div class="container">
            <?php include APP_ROOT . '/includes/layouts/flash-messages.php'; ?>
        </div>
<?php endif; ?>
