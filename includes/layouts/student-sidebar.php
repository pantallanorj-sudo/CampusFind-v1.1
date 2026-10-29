<?php
/** Student navigation. $activeNav highlights the current link. */
$studentLinks = [
    'dashboard'    => ['student/dashboard.php',    'Dashboard',    'grid'],
    'browse'       => ['student/browse-items.php', 'Browse items', 'search'],
    'report-lost'  => ['student/report-item.php?type=lost',  'Report lost item',  'plus'],
    'report-found' => ['student/report-item.php?type=found', 'Report found item', 'package'],
    'my-reports'   => ['student/my-reports.php',   'My reports',   'file-text'],
    'my-claims'    => ['student/my-claims.php',    'My claims',    'clipboard'],
];
?>
<aside class="sidebar" id="sidebar" aria-label="Student navigation">
    <a class="brand brand-light sidebar-brand" href="<?= e(url('student/dashboard.php')) ?>">
        <span class="brand-mark"><?= icon('search', 'icon-sm') ?></span><span class="brand-name">CampusFind</span>
    </a>
    <nav class="sidebar-nav">
        <?php foreach ($studentLinks as $key => [$href, $label, $iconName]): ?>
            <a class="sidebar-link<?= $activeNav === $key ? ' active' : '' ?>" href="<?= e(url($href)) ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>>
                <?= icon($iconName) ?><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-user">
        <p class="sidebar-user-name"><?= e($authUser['full_name']) ?></p>
        <p class="sidebar-user-role">Student</p>
        <form method="post" action="<?= e(url('logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="sidebar-link sidebar-logout"><?= icon('log-out') ?><span>Log out</span></button>
        </form>
    </div>
</aside>
