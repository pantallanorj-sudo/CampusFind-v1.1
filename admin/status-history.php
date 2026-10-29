<?php
require_once __DIR__ . '/../includes/init.php';
$admin = require_role('admin');

$filters = ['q' => str_param('q'), 'status' => str_param('status')];
$result  = search_status_history($filters, (int) (int_param('page') ?? 1), 15);
$rows    = $result['rows'];
$p       = $result['pagination'];
$hasFilters = $filters['q'] !== '' || $filters['status'] !== '';

$pageTitle = 'Status history';
$activeNav = 'history';
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="page-header">
    <div>
        <h1>Status history</h1>
        <p class="muted">Every status change, with who made it and when.</p>
    </div>
</div>

<form method="get" action="<?= e(url('admin/status-history.php')) ?>" class="filters card" role="search">
    <div class="filter-field filter-grow">
        <label for="q">Item name or report number</label>
        <input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>">
    </div>
    <div class="filter-field">
        <label for="status">Changed to</label>
        <select class="input" id="status" name="status">
            <option value="">Any status</option>
            <?php foreach (item_statuses() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Apply</button>
        <?php if ($hasFilters): ?><a class="btn btn-secondary" href="<?= e(url('admin/status-history.php')) ?>">Clear filters</a><?php endif; ?>
    </div>
</form>

<p class="result-count" aria-live="polite"><?= (int) $p['total'] ?> <?= $p['total'] === 1 ? 'entry' : 'entries' ?></p>

<?php if (!$rows): ?>
    <div class="empty-state">
        <?= icon('history', 'icon-xl') ?>
        <h3>No history found</h3>
        <p>Status changes will be listed here.</p>
    </div>
<?php else: ?>
    <div class="table-wrap card">
        <table class="table">
            <thead>
                <tr><th scope="col">When</th><th scope="col">Item</th><th scope="col">Change</th><th scope="col">Changed by</th><th scope="col">Notes</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $h): ?>
                    <tr>
                        <td data-label="When"><?= e(format_datetime($h['created_at'])) ?></td>
                        <td data-label="Item"><a class="table-title" href="<?= e(url('admin/view-report.php?id=' . (int) $h['item_report_id'])) ?>"><?= e($h['item_name']) ?></a><span class="muted block">#<?= (int) $h['item_report_id'] ?></span></td>
                        <td data-label="Change">
                            <?php if ($h['old_status'] !== null): ?><?= status_badge($h['old_status']) ?> <span class="arrow" aria-label="changed to">&rarr;</span> <?php endif; ?>
                            <?= status_badge($h['new_status']) ?>
                        </td>
                        <td data-label="Changed by"><?= e($h['changed_by_name']) ?><span class="muted block"><?= $h['changed_by_role'] === 'admin' ? 'Staff' : 'Student' ?></span></td>
                        <td data-label="Notes"><?= e($h['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($p, 'admin/status-history.php', ['q' => $filters['q'], 'status' => $filters['status']]) ?>
<?php endif; ?>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
