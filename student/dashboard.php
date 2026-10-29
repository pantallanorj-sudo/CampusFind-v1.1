<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role('student');

$stats    = student_stats((int) $user['id']);
$activity = student_recent_activity((int) $user['id']);

$pageTitle = 'Student dashboard';
$activeNav = 'dashboard';
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="page-header">
    <div>
        <h1>Hello, <?= e(explode(' ', $user['full_name'])[0]) ?></h1>
        <p class="muted">Here is what is happening with your reports and claims.</p>
    </div>
</div>

<section class="stat-grid" aria-label="Your summary">
    <article class="stat-card">
        <p class="stat-label">Total reports</p>
        <p class="stat-value"><?= (int) $stats['total_reports'] ?></p>
        <p class="stat-note">Lost and found reports you created</p>
    </article>
    <article class="stat-card">
        <p class="stat-label">Open reports</p>
        <p class="stat-value"><?= (int) $stats['open_reports'] ?></p>
        <p class="stat-note">Not yet returned or closed</p>
    </article>
    <article class="stat-card">
        <p class="stat-label">Pending claims</p>
        <p class="stat-value"><?= (int) $stats['pending_claims'] ?></p>
        <p class="stat-note">Your claims waiting for staff</p>
    </article>
    <article class="stat-card">
        <p class="stat-label">Returned items</p>
        <p class="stat-value"><?= (int) $stats['resolved'] ?></p>
        <p class="stat-note">Reported or claimed by you</p>
    </article>
</section>

<section aria-labelledby="quick-actions">
    <h2 id="quick-actions" class="section-heading">Quick actions</h2>
    <div class="action-grid">
        <a class="action-card" href="<?= e(url('student/report-item.php?type=lost')) ?>">
            <span class="action-icon"><?= icon('plus') ?></span>
            <strong>Report lost item</strong>
            <span>Tell us what you lost and where.</span>
        </a>
        <a class="action-card" href="<?= e(url('student/report-item.php?type=found')) ?>">
            <span class="action-icon"><?= icon('package') ?></span>
            <strong>Report found item</strong>
            <span>Help an owner find their belongings.</span>
        </a>
        <a class="action-card" href="<?= e(url('student/browse-items.php')) ?>">
            <span class="action-icon"><?= icon('search') ?></span>
            <strong>Browse items</strong>
            <span>Search every lost and found report.</span>
        </a>
        <a class="action-card" href="<?= e(url('student/my-reports.php')) ?>">
            <span class="action-icon"><?= icon('file-text') ?></span>
            <strong>My reports</strong>
            <span>Edit, close or delete your reports.</span>
        </a>
        <a class="action-card" href="<?= e(url('student/my-claims.php')) ?>">
            <span class="action-icon"><?= icon('clipboard') ?></span>
            <strong>My claims</strong>
            <span>Follow the progress of your claims.</span>
        </a>
    </div>
</section>

<section aria-labelledby="recent-activity">
    <h2 id="recent-activity" class="section-heading">Recent activity</h2>
    <?php if (!$activity): ?>
        <div class="empty-state">
            <?= icon('clock', 'icon-xl') ?>
            <h3>No activity yet</h3>
            <p>When you report an item or submit a claim, it will show up here.</p>
        </div>
    <?php else: ?>
        <ul class="activity-list card">
            <?php foreach ($activity as $row): ?>
                <?php $href = $row['kind'] === 'claim'
                    ? 'student/claim-details.php?id=' . (int) $row['ref_id']
                    : 'student/item-details.php?id=' . (int) $row['item_id']; ?>
                <li>
                    <span class="activity-icon"><?= icon($row['kind'] === 'claim' ? 'clipboard' : 'file-text', 'icon-sm') ?></span>
                    <div class="activity-text">
                        <a href="<?= e(url($href)) ?>"><?= e($row['title']) ?></a>
                        <span class="muted"><?= $row['kind'] === 'claim' ? 'Your claim' : 'Your report' ?> &middot; <?= e(format_datetime($row['ts'])) ?></span>
                    </div>
                    <?= status_badge($row['status'], $row['kind'] === 'claim' ? 'claim' : 'item') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
