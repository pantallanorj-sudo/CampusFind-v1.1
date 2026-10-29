<?php
require_once __DIR__ . '/../includes/init.php';
$admin = require_role('admin');

$id     = int_param('id');
$report = $id !== null ? find_report_for_staff($id) : null;
if (!$report) {
    abort(404, 'This report does not exist.');
}

$claims  = claims_for_report((int) $report['id']);
$history = history_for_report((int) $report['id']);
$options = manual_status_options($report);
$isFound = $report['report_type'] === 'found';

$pageTitle = 'Report #' . (int) $report['id'];
$activeNav = 'reports';
include APP_ROOT . '/includes/layouts/header.php';
?>
<p class="back-link"><a href="<?= e(url('admin/manage-reports.php')) ?>"><?= icon('arrow-left', 'icon-sm') ?> Back to reports</a></p>
<div class="page-header">
    <div>
        <div class="badge-row"><?= type_badge($report['report_type']) ?> <?= status_badge($report['status']) ?></div>
        <h1><?= e($report['item_name']) ?></h1>
        <p class="muted">Report #<?= (int) $report['id'] ?> &middot; created <?= e(format_datetime($report['created_at'])) ?></p>
    </div>
</div>

<div class="detail-layout">
    <div class="detail-main">
        <article class="card">
            <?php if (!empty($report['image_path'])): ?>
                <img class="detail-image" src="<?= e(url('image.php?id=' . (int) $report['id'])) ?>" alt="Photo of <?= e($report['item_name']) ?>">
            <?php endif; ?>
            <div class="card-body">
                <h2 class="card-title">Description</h2>
                <p><?= e_multiline($report['description']) ?></p>
                <dl class="detail-list">
                    <div><dt>Category</dt><dd><?= e($report['category_name']) ?></dd></div>
                    <div><dt><?= $isFound ? 'Found at' : 'Lost at' ?></dt><dd><?= e($report['location']) ?></dd></div>
                    <div><dt><?= $isFound ? 'Date found' : 'Date lost' ?></dt><dd><?= e(format_date($report['date_occurred'])) ?></dd></div>
                    <div><dt>Last updated</dt><dd><?= e(format_datetime($report['updated_at'])) ?></dd></div>
                    <div><dt>Reported by</dt><dd><?= e($report['owner_name']) ?> <span class="muted">(<?= e($report['owner_email']) ?>)</span></dd></div>
                </dl>
            </div>
        </article>

        <?php if ($isFound): ?>
            <article class="card">
                <div class="card-body">
                    <h2 class="card-title">Claims on this item</h2>
                    <?php if (!$claims): ?>
                        <p class="muted">No one has claimed this item yet.</p>
                    <?php else: ?>
                        <div class="list-card inline-list">
                            <?php foreach ($claims as $c): ?>
                                <a class="list-row" href="<?= e(url('admin/view-claim.php?id=' . (int) $c['id'])) ?>">
                                    <div>
                                        <strong><?= e($c['claimant_name']) ?></strong>
                                        <span class="muted block">Submitted <?= e(format_datetime($c['created_at'])) ?></span>
                                    </div>
                                    <?= status_badge($c['status'], 'claim') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endif; ?>

        <article class="card">
            <div class="card-body">
                <h2 class="card-title">Status history</h2>
                <ol class="timeline">
                    <?php foreach (array_reverse($history) as $h): ?>
                        <li>
                            <span class="timeline-dot"></span>
                            <div>
                                <strong><?= $h['old_status'] === null ? 'Created as ' . e(item_statuses()[$h['new_status']] ?? $h['new_status'])
                                    : e(item_statuses()[$h['old_status']] ?? $h['old_status']) . ' &rarr; ' . e(item_statuses()[$h['new_status']] ?? $h['new_status']) ?></strong>
                                <span class="muted block"><?= e(format_datetime($h['created_at'])) ?> &middot; <?= e($h['changed_by_name']) ?></span>
                                <?php if ($h['notes']): ?><span class="block"><?= e($h['notes']) ?></span><?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </article>
    </div>

    <aside class="detail-side">
        <section class="card side-card">
            <h2 class="card-title">Update status</h2>
            <p>Current status: <?= status_badge($report['status']) ?></p>
            <?php if ($report['status'] === 'awaiting_handover'): ?>
                <p class="notice notice-info"><?= icon('info', 'icon-sm') ?> <span>An approved claim is waiting for pickup. Record the handover or revoke the approval from the claim page.</span></p>
            <?php elseif ($report['status'] === 'returned'): ?>
                <p class="help-text">Returned reports are final and cannot be changed.</p>
            <?php elseif (!$options): ?>
                <p class="help-text">No manual status changes are available right now.</p>
            <?php else: ?>
                <form method="post" action="<?= e(url('actions/reports/update-status.php')) ?>" data-confirm="Change this report's status?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $report['id'] ?>">
                    <div class="form-group">
                        <label for="new_status">New status</label>
                        <select class="input" id="new_status" name="new_status" required>
                            <?php foreach ($options as $opt): ?>
                                <option value="<?= e($opt) ?>"><?= e(item_statuses()[$opt]) ?><?= $opt === 'returned' ? ' (lost item recovered)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Note for the history</label>
                        <textarea class="input" id="notes" name="notes" rows="3" maxlength="500" required></textarea>
                    </div>
                    <?php if (in_array('closed', $options, true) && $report['status'] === 'claim_pending'): ?>
                        <p class="help-text">Closing this report also ends any claims still pending.</p>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-block" data-loading-text="Saving...">Save status</button>
                </form>
            <?php endif; ?>
        </section>
    </aside>
</div>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
