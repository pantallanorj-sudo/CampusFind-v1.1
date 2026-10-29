<?php
require_once __DIR__ . '/../includes/init.php';
$admin = require_role('admin');

// With no status in the address, show the claims that need work first.
$status = array_key_exists('status', $_GET) ? str_param('status') : 'pending';
if ($status === 'all') {
    $status = '';                                   // "all" is used in links because empty values are dropped from URLs
}
$filters = ['mode' => 'admin', 'status' => $status, 'q' => str_param('q')];
$result = search_claims($filters, (int) (int_param('page') ?? 1), (int) config('rows_per_page', 10));
$claims = $result['claims'];
$p      = $result['pagination'];

$pageTitle = 'Manage claims';
$activeNav = 'claims';
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="page-header">
    <div>
        <h1>Manage claims</h1>
        <p class="muted">Review claims, then record handovers once items are collected.</p>
    </div>
</div>

<form method="get" action="<?= e(url('admin/manage-claims.php')) ?>" class="filters card" role="search">
    <div class="filter-field filter-grow">
        <label for="q">Search</label>
        <input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Item, claimant name or email">
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
        <a class="btn btn-secondary" href="<?= e(url('admin/manage-claims.php?status=all')) ?>">Show all claims</a>
    </div>
</form>

<p class="result-count" aria-live="polite"><?= (int) $p['total'] ?> <?= $p['total'] === 1 ? 'claim' : 'claims' ?></p>

<?php if (!$claims): ?>
    <div class="empty-state">
        <?= icon('clipboard', 'icon-xl') ?>
        <h3>No claims here</h3>
        <p><?= $filters['status'] === 'pending' ? 'There are no pending claims. Nice work.' : 'No claims match these filters.' ?></p>
    </div>
<?php else: ?>
    <div class="table-wrap card">
        <table class="table">
            <thead>
                <tr><th scope="col">Item</th><th scope="col">Claimant</th><th scope="col">Submitted</th><th scope="col">Claim</th><th scope="col">Item status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr>
            </thead>
            <tbody>
                <?php foreach ($claims as $c): ?>
                    <tr>
                        <td data-label="Item"><a class="table-title" href="<?= e(url('admin/view-claim.php?id=' . (int) $c['id'])) ?>"><?= e($c['item_name']) ?></a><span class="muted block">Claim #<?= (int) $c['id'] ?></span></td>
                        <td data-label="Claimant"><?= e($c['claimant_name']) ?><span class="muted block"><?= e($c['claimant_email']) ?></span></td>
                        <td data-label="Submitted"><?= e(format_datetime($c['created_at'])) ?></td>
                        <td data-label="Claim"><?= status_badge($c['status'], 'claim') ?></td>
                        <td data-label="Item status"><?= status_badge($c['item_status']) ?></td>
                        <td class="row-actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('admin/view-claim.php?id=' . (int) $c['id'])) ?>"><?= $c['status'] === 'pending' ? 'Review' : 'View' ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($p, 'admin/manage-claims.php', ['q' => $filters['q'], 'status' => $filters['status'] === '' ? 'all' : $filters['status']]) ?>
<?php endif; ?>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
