<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role('student');

$id     = int_param('id');
$report = $id !== null ? find_report($id) : null;
if (!$report) {
    abort(404, 'This report does not exist.');
}
if ((int) $report['user_id'] !== (int) $user['id']) {
    abort(403, 'You can only edit your own reports.');
}
if (!owner_can_edit($report)) {
    flash('warning', 'Only open reports can be edited. This report is "' . (item_statuses()[$report['status']] ?? $report['status']) . '".');
    redirect('student/item-details.php?id=' . (int) $report['id']);
}

$pageTitle = 'Edit report';
$activeNav = 'my-reports';
include APP_ROOT . '/includes/layouts/header.php';
?>
<p class="back-link"><a href="<?= e(url('student/item-details.php?id=' . (int) $report['id'])) ?>"><?= icon('arrow-left', 'icon-sm') ?> Back to report</a></p>
<div class="page-header">
    <div>
        <h1>Edit <?= e($report['report_type']) ?> report</h1>
        <p class="muted">Changes are saved to the report right away.</p>
    </div>
</div>
<?= render_report_form([
    'action'       => 'actions/reports/update.php',
    'type'         => $report['report_type'],
    'report_id'    => (int) $report['id'],
    'values'       => $report,
    'image_path'   => $report['image_path'],
    'submit_label' => 'Save changes',
    'cancel_path'  => 'student/item-details.php?id=' . (int) $report['id'],
]) ?>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
