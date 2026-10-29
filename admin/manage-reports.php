<?php
require_once __DIR__ . '/../includes/init.php';
$admin = require_role('admin');

$statusParam = str_param('status');
$filters = [
    'mode'        => 'admin',
    'q'           => str_param('q'),
    'type'        => str_param('type'),
    'category_id' => int_param('category_id') ?? 0,
    'status'      => $statusParam,
];
$result = search_reports($filters, (int) (int_param('page') ?? 1), (int) config('rows_per_page', 10));
$rows   = $result['items'];
$p      = $result['pagination'];
$hasFilters = $filters['q'] !== '' || $filters['type'] !== '' || $filters['category_id'] > 0 || $filters['status'] !== '';

$pageTitle = 'Manage reports';
$activeNav = 'reports';
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="page-header">
    <div>
        <h1>Manage reports</h1>
        <p class="muted">All lost and found reports, including closed ones.</p>
    </div>
</div>

<form method="get" action="<?= e(url('admin/manage-reports.php')) ?>" class="filters card" role="search">
    <div class="filter-field filter-grow">
        <label for="q">Search</label>
        <input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Item, description, location or reporter">
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
        <label for="category_id">Category</label>
        <select class="input" id="category_id" name="category_id">
            <option value="">All categories</option>
            <?php foreach (list_categories() as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"<?= $filters['category_id'] === (int) $cat['id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="status">Status</label>
        <select class="input" id="status" name="status">
            <option value="">All statuses</option>
            <option value="unresolved"<?= $filters['status'] === 'unresolved' ? ' selected' : '' ?>>Open (not returned or closed)</option>
            <option value="resolved"<?= $filters['status'] === 'resolved' ? ' selected' : '' ?>>Resolved (returned or closed)</option>
            <?php foreach (item_statuses() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Apply</button>
        <?php if ($hasFilters): ?><a class="btn btn-secondary" href="<?= e(url('admin/manage-reports.php')) ?>">Clear filters</a><?php endif; ?>
    </div>
</form>

<p class="result-count" aria-live="polite"><?= (int) $p['total'] ?> <?= $p['total'] === 1 ? 'report' : 'reports' ?></p>

<?php if (!$rows): ?>
    <div class="empty-state">
        <?= icon('file-text', 'icon-xl') ?>
        <h3>No reports match</h3>
        <p>Try different filters.</p>
        <?php if ($hasFilters): ?><a class="btn btn-secondary" href="<?= e(url('admin/manage-reports.php')) ?>">Clear filters</a><?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap card">
        <table class="table">
            <thead>
                <tr><th scope="col">Item</th><th scope="col">Type</th><th scope="col">Reporter</th><th scope="col">Status</th><th scope="col">Reported</th><th scope="col">Claims</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td data-label="Item">
                            <a class="table-title" href="<?= e(url('admin/view-report.php?id=' . (int) $r['id'])) ?>"><?= e($r['item_name']) ?></a>
                            <span class="muted block">#<?= (int) $r['id'] ?> &middot; <?= e($r['category_name']) ?></span>
                        </td>
                        <td data-label="Type"><?= type_badge($r['report_type']) ?></td>
                        <td data-label="Reporter"><?= e($r['owner_name']) ?></td>
                        <td data-label="Status"><?= status_badge($r['status']) ?></td>
                        <td data-label="Reported"><?= e(format_date($r['created_at'])) ?></td>
                        <td data-label="Claims"><?= $r['report_type'] === 'found' ? (int) $r['pending_claims'] . ' pending' : '<span class="muted">Not applicable</span>' ?></td>
                        <td class="row-actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('admin/view-report.php?id=' . (int) $r['id'])) ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($p, 'admin/manage-reports.php', [
        'q' => $filters['q'], 'type' => $filters['type'],
        'category_id' => $filters['category_id'] ?: '', 'status' => $filters['status'],
    ]) ?>
<?php endif; ?>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
