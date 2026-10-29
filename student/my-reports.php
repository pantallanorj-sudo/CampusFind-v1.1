<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role('student');

$filters = [
    'mode'    => 'mine',
    'user_id' => (int) $user['id'],
    'q'       => str_param('q'),
    'type'    => str_param('type'),
    'status'  => str_param('status'),
];
$result = search_reports($filters, (int) (int_param('page') ?? 1), (int) config('rows_per_page', 10));
$rows   = $result['items'];
$p      = $result['pagination'];
$hasFilters = $filters['q'] !== '' || $filters['type'] !== '' || $filters['status'] !== '';

$pageTitle = 'My reports';
$activeNav = 'my-reports';
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="page-header">
    <div>
        <h1>My reports</h1>
        <p class="muted">Every lost and found report you have created.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-secondary" href="<?= e(url('student/report-item.php?type=lost')) ?>">Report lost item</a>
        <a class="btn btn-primary" href="<?= e(url('student/report-item.php?type=found')) ?>">Report found item</a>
    </div>
</div>

<form method="get" action="<?= e(url('student/my-reports.php')) ?>" class="filters card" role="search">
    <div class="filter-field filter-grow">
        <label for="q">Search</label>
        <input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Item name or description">
    </div>
    <div class="filter-field">
        <label for="type">Type</label>
        <select class="input" id="type" name="type">
            <option value="">All types</option>
            <?php foreach (report_types() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $filters['type'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="status">Status</label>
        <select class="input" id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (item_statuses() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Apply</button>
        <?php if ($hasFilters): ?><a class="btn btn-secondary" href="<?= e(url('student/my-reports.php')) ?>">Clear filters</a><?php endif; ?>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="empty-state">
        <?= icon('file-text', 'icon-xl') ?>
        <h3><?= $hasFilters ? 'No reports match your filters' : 'You have not reported anything yet' ?></h3>
        <p><?= $hasFilters ? 'Try different filters.' : 'Lost something or found something? Create your first report.' ?></p>
        <?php if (!$hasFilters): ?>
            <a class="btn btn-primary" href="<?= e(url('student/report-item.php?type=lost')) ?>">Report a lost item</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap card">
        <table class="table">
            <thead>
                <tr><th scope="col">Item</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Date</th><th scope="col">Claims</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td data-label="Item">
                            <a class="table-title" href="<?= e(url('student/item-details.php?id=' . (int) $r['id'])) ?>"><?= e($r['item_name']) ?></a>
                            <span class="muted block"><?= e($r['category_name']) ?> &middot; <?= e($r['location']) ?></span>
                        </td>
                        <td data-label="Type"><?= type_badge($r['report_type']) ?></td>
                        <td data-label="Status"><?= status_badge($r['status']) ?></td>
                        <td data-label="Date"><?= e(format_date($r['date_occurred'])) ?></td>
                        <td data-label="Claims"><?= $r['report_type'] === 'found' ? (int) $r['pending_claims'] . ' pending' : '<span class="muted">Not applicable</span>' ?></td>
                        <td class="row-actions">
                            <a class="btn btn-secondary btn-sm" href="<?= e(url('student/item-details.php?id=' . (int) $r['id'])) ?>">View</a>
                            <?php if (owner_can_edit($r)): ?>
                                <a class="btn btn-secondary btn-sm" href="<?= e(url('student/edit-report.php?id=' . (int) $r['id'])) ?>">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($p, 'student/my-reports.php', ['q' => $filters['q'], 'type' => $filters['type'], 'status' => $filters['status']]) ?>
<?php endif; ?>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
