<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role('student');

$filters = ['mode' => 'mine', 'claimant_id' => (int) $user['id'], 'status' => str_param('status'), 'q' => str_param('q')];
$result = search_claims($filters, (int) (int_param('page') ?? 1), (int) config('rows_per_page', 10));
$claims = $result['claims'];
$p      = $result['pagination'];
$hasFilters = $filters['status'] !== '' || $filters['q'] !== '';

$pageTitle = 'My claims';
$activeNav = 'my-claims';
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="page-header">
    <div>
        <h1>My claims</h1>
        <p class="muted">Claims you submitted for found items.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('student/browse-items.php?type=found')) ?>">Browse found items</a>
</div>

<form method="get" action="<?= e(url('student/my-claims.php')) ?>" class="filters card" role="search">
    <div class="filter-field filter-grow">
        <label for="q">Search</label>
        <input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Item name">
    </div>
    <div class="filter-field">
        <label for="status">Claim status</label>
        <select class="input" id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (claim_statuses() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Apply</button>
        <?php if ($hasFilters): ?><a class="btn btn-secondary" href="<?= e(url('student/my-claims.php')) ?>">Clear filters</a><?php endif; ?>
    </div>
</form>

<?php if (!$claims): ?>
    <div class="empty-state">
        <?= icon('clipboard', 'icon-xl') ?>
        <h3><?= $hasFilters ? 'No claims match your filters' : 'You have not submitted any claims' ?></h3>
        <p><?= $hasFilters ? 'Try different filters.' : 'If you see your item in the found list, open it and choose Claim this item.' ?></p>
        <?php if (!$hasFilters): ?><a class="btn btn-primary" href="<?= e(url('student/browse-items.php?type=found')) ?>">Browse found items</a><?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap card">
        <table class="table">
            <thead>
                <tr><th scope="col">Item</th><th scope="col">Submitted</th><th scope="col">Claim status</th><th scope="col">Item status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr>
            </thead>
            <tbody>
                <?php foreach ($claims as $c): ?>
                    <tr>
                        <td data-label="Item"><a class="table-title" href="<?= e(url('student/claim-details.php?id=' . (int) $c['id'])) ?>"><?= e($c['item_name']) ?></a></td>
                        <td data-label="Submitted"><?= e(format_datetime($c['created_at'])) ?></td>
                        <td data-label="Claim status"><?= status_badge($c['status'], 'claim') ?></td>
                        <td data-label="Item status"><?= status_badge($c['item_status']) ?></td>
                        <td class="row-actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('student/claim-details.php?id=' . (int) $c['id'])) ?>">View claim</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($p, 'student/my-claims.php', ['q' => $filters['q'], 'status' => $filters['status']]) ?>
<?php endif; ?>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
