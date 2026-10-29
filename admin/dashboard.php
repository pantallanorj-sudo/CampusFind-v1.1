<?php
require_once __DIR__ . '/../includes/init.php';
$admin = require_role('admin');

$stats         = admin_stats();
$recentReports = search_reports(['mode' => 'admin'], 1, 5)['items'];
$pendingClaims = search_claims(['mode' => 'admin', 'status' => 'pending'], 1, 5)['claims'];

$pageTitle = 'Staff dashboard';
$activeNav = 'dashboard';
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="page-header">
    <div>
        <h1>Staff dashboard</h1>
        <p class="muted">Review claims, record handovers and keep reports tidy.</p>
    </div>
</div>

<section class="stat-grid stat-grid-5" aria-label="Summary">
    <a class="stat-card stat-link" href="<?= e(url('admin/manage-reports.php')) ?>">
        <p class="stat-label">Total reports</p>
        <p class="stat-value"><?= (int) $stats['total_reports'] ?></p>
        <p class="stat-note">Lost and found</p>
    </a>
    <a class="stat-card stat-link" href="<?= e(url('admin/manage-reports.php?status=unresolved')) ?>">
        <p class="stat-label">Open reports</p>
        <p class="stat-value"><?= (int) $stats['open_reports'] ?></p>
        <p class="stat-note">Not returned or closed</p>
    </a>
    <a class="stat-card stat-link<?= $stats['pending_claims'] > 0 ? ' stat-attention' : '' ?>" href="<?= e(url('admin/manage-claims.php?status=pending')) ?>">
        <p class="stat-label">Pending claims</p>
        <p class="stat-value"><?= (int) $stats['pending_claims'] ?></p>
        <p class="stat-note">Need review</p>
    </a>
    <a class="stat-card stat-link" href="<?= e(url('admin/manage-claims.php?status=approved')) ?>">
        <p class="stat-label">Awaiting handover</p>
        <p class="stat-value"><?= (int) $stats['awaiting_handover'] ?></p>
        <p class="stat-note">Approved, not collected</p>
    </a>
    <a class="stat-card stat-link" href="<?= e(url('admin/manage-reports.php?status=resolved')) ?>">
        <p class="stat-label">Resolved</p>
        <p class="stat-value"><?= (int) $stats['resolved'] ?></p>
        <p class="stat-note">Returned or closed</p>
    </a>
</section>

<div class="two-col">
    <section aria-labelledby="pending-heading">
        <div class="section-row">
            <h2 id="pending-heading" class="section-heading">Pending claims</h2>
            <a href="<?= e(url('admin/manage-claims.php?status=pending')) ?>">View all</a>
        </div>
        <?php if (!$pendingClaims): ?>
            <div class="empty-state compact">
                <?= icon('check-circle', 'icon-xl') ?>
                <h3>No claims to review</h3>
                <p>New claims will appear here.</p>
            </div>
        <?php else: ?>
            <div class="card list-card">
                <?php foreach ($pendingClaims as $c): ?>
                    <a class="list-row" href="<?= e(url('admin/view-claim.php?id=' . (int) $c['id'])) ?>">
                        <div>
                            <strong><?= e($c['item_name']) ?></strong>
                            <span class="muted block">Claimed by <?= e($c['claimant_name']) ?> &middot; <?= e(format_datetime($c['created_at'])) ?></span>
                        </div>
                        <?= status_badge($c['status'], 'claim') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section aria-labelledby="recent-heading">
        <div class="section-row">
            <h2 id="recent-heading" class="section-heading">Recent reports</h2>
            <a href="<?= e(url('admin/manage-reports.php')) ?>">View all</a>
        </div>
        <?php if (!$recentReports): ?>
            <div class="empty-state compact">
                <?= icon('file-text', 'icon-xl') ?>
                <h3>No reports yet</h3>
                <p>Reports from students will appear here.</p>
            </div>
        <?php else: ?>
            <div class="card list-card">
                <?php foreach ($recentReports as $r): ?>
                    <a class="list-row" href="<?= e(url('admin/view-report.php?id=' . (int) $r['id'])) ?>">
                        <div>
                            <strong><?= e($r['item_name']) ?></strong>
                            <span class="muted block"><?= e(report_types()[$r['report_type']]) ?> &middot; <?= e($r['owner_name']) ?> &middot; <?= e(format_date($r['created_at'])) ?></span>
                        </div>
                        <?= status_badge($r['status']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
