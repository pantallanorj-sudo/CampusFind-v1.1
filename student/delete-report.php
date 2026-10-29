<?php
/** Confirmation step for deleting a report. The real deletion is a POST to actions/reports/delete.php. */
require_once __DIR__ . '/../includes/init.php';
$user = require_role('student');

$id     = int_param('id');
$report = $id !== null ? find_report($id) : null;
if (!$report) {
    abort(404, 'This report does not exist.');
}
if ((int) $report['user_id'] !== (int) $user['id']) {
    abort(403, 'You can only delete your own reports.');
}
$block = delete_block_reason($report);

$pageTitle = 'Delete report';
$activeNav = 'my-reports';
include APP_ROOT . '/includes/layouts/header.php';
?>
<p class="back-link"><a href="<?= e(url('student/item-details.php?id=' . (int) $report['id'])) ?>"><?= icon('arrow-left', 'icon-sm') ?> Back to report</a></p>
<div class="narrow">
    <div class="card form-card">
        <h1>Delete this report?</h1>
        <p><strong><?= e($report['item_name']) ?></strong><br>
           <span class="muted"><?= e(report_types()[$report['report_type']]) ?> &middot; <?= e($report['location']) ?> &middot; <?= e(format_date($report['date_occurred'])) ?></span></p>

        <?php if ($block !== null): ?>
            <p class="notice notice-warning"><?= icon('info', 'icon-sm') ?> <span><?= e($block) ?></span></p>
            <a class="btn btn-secondary" href="<?= e(url('student/item-details.php?id=' . (int) $report['id'])) ?>">Back to report</a>
        <?php else: ?>
            <p class="notice notice-danger"><?= icon('alert-circle', 'icon-sm') ?> <span>This permanently removes the report and its photo. This cannot be undone.</span></p>
            <form method="post" action="<?= e(url('actions/reports/delete.php')) ?>" class="form-actions">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $report['id'] ?>">
                <button type="submit" class="btn btn-danger" data-loading-text="Deleting...">Yes, delete report</button>
                <a class="btn btn-secondary" href="<?= e(url('student/item-details.php?id=' . (int) $report['id'])) ?>">Keep report</a>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
