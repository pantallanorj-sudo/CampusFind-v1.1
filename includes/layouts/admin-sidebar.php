<?php
/** Staff navigation. $activeNav highlights the current link. */
$adminLinks = [
    'dashboard'  => ['admin/dashboard.php',         'Dashboard',         'grid'],
    'reports'    => ['admin/manage-reports.php',    'Manage reports',    'file-text'],
    'claims'     => ['admin/manage-claims.php',     'Manage claims',     'clipboard'],
    'categories' => ['admin/manage-categories.php', 'Manage categories', 'folder'],
    'history'    => ['admin/status-history.php',    'Status history',    'history'],
];
?>
<aside class="sidebar" id="sidebar" aria-label="Staff navigation">
    <a class="brand brand-light sidebar-brand" href="<?= e(url('admin/dashboard.php')) ?>">
        <span class="brand-mark"><?= icon('search', 'icon-sm') ?></span><span class="brand-name">CampusFind</span>
    </a>
    <p class="sidebar-section">Staff area</p>
    <nav class="sidebar-nav">
        <?php foreach ($adminLinks as $key => [$href, $label, $iconName]): ?>
            <a class="sidebar-link<?= $activeNav === $key ? ' active' : '' ?>" href="<?= e(url($href)) ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>>
                <?= icon($iconName) ?><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-user">
        <p class="sidebar-user-name"><?= e($authUser['full_name']) ?></p>
        <p class="sidebar-user-role">Staff</p>
        <form method="post" action="<?= e(url('logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="sidebar-link sidebar-logout"><?= icon('log-out') ?><span>Log out</span></button>
        </form>
    </div>
</aside>
