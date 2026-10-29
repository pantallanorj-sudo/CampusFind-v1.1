<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role('student');

$type = str_param('type');
if (!array_key_exists($type, report_types())) {
    $type = 'lost';
}
$isFound = $type === 'found';

$pageTitle = $isFound ? 'Report a found item' : 'Report a lost item';
$activeNav = $isFound ? 'report-found' : 'report-lost';
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p class="muted">
            <?= $isFound
                ? 'Thank you for helping. Tell us what you found and where, then take it to the Lost & Found Desk.'
                : 'Tell us what you lost. We will keep the report open so a finder can match it.' ?>
        </p>
    </div>
</div>

<div class="switch-tabs" role="navigation" aria-label="Report type">
    <a class="<?= $isFound ? '' : 'active' ?>" href="<?= e(url('student/report-item.php?type=lost')) ?>">I lost something</a>
    <a class="<?= $isFound ? 'active' : '' ?>" href="<?= e(url('student/report-item.php?type=found')) ?>">I found something</a>
</div>

<?= render_report_form([
    'action'       => 'actions/reports/create.php',
    'type'         => $type,
    'values'       => [],
    'submit_label' => $isFound ? 'Submit found report' : 'Submit lost report',
    'cancel_path'  => 'student/dashboard.php',
]) ?>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
