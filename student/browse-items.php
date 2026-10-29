<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role('student');

$filters = [
    'mode'        => 'browse',
    'q'           => str_param('q'),
    'type'        => str_param('type'),
    'category_id' => int_param('category_id') ?? 0,
    'status'      => str_param('status'),
];
$result = search_reports($filters, (int) (int_param('page') ?? 1), (int) config('cards_per_page', 9));
$items = $result['items'];
$p     = $result['pagination'];
$hasFilters = $filters['q'] !== '' || $filters['type'] !== '' || $filters['category_id'] > 0 || $filters['status'] !== '';

$pageTitle = 'Browse items';
$activeNav = 'browse';
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="page-header">
    <div>
        <h1>Browse items</h1>
        <p class="muted">Search lost and found reports from across campus.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('student/report-item.php?type=found')) ?>"><?= icon('plus', 'icon-sm') ?> Report an item</a>
</div>

<form method="get" action="<?= e(url('student/browse-items.php')) ?>" class="filters card" role="search">
    <div class="filter-field filter-grow">
        <label for="q">Search</label>
        <input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Item name or description">
    </div>
    <div class="filter-field">
        <label for="type">Type</label>
        <select class="input" id="type" name="type">
            <option value="">Lost and found</option>
            <?php foreach (report_types() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $filters['type'] === $value ? ' selected' : '' ?>><?= e($label) ?> items</option>
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
            <option value="">Active reports</option>
            <?php foreach (['open', 'claim_pending', 'awaiting_handover', 'returned'] as $s): ?>
                <option value="<?= e($s) ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= e(item_statuses()[$s]) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($hasFilters): ?><a class="btn btn-secondary" href="<?= e(url('student/browse-items.php')) ?>">Clear filters</a><?php endif; ?>
    </div>
</form>

<p class="result-count" aria-live="polite">
    <?= (int) $p['total'] ?> <?= $p['total'] === 1 ? 'report' : 'reports' ?> found<?= $hasFilters ? ' for your filters' : '' ?>
</p>

<?php if (!$items): ?>
    <div class="empty-state">
        <?= icon('search', 'icon-xl') ?>
        <h3>No reports match</h3>
        <p><?= $hasFilters ? 'Try a different word or clear the filters.' : 'Nothing has been reported yet. Be the first to post a report.' ?></p>
        <?php if ($hasFilters): ?>
            <a class="btn btn-secondary" href="<?= e(url('student/browse-items.php')) ?>">Clear filters</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="item-grid">
        <?php foreach ($items as $item): ?>
            <?= render_item_card($item, 'student/item-details.php?id=' . (int) $item['id'], (int) $item['user_id'] === (int) $user['id']) ?>
        <?php endforeach; ?>
    </div>
    <?= render_pagination($p, 'student/browse-items.php', [
        'q' => $filters['q'], 'type' => $filters['type'],
        'category_id' => $filters['category_id'] ?: '', 'status' => $filters['status'],
    ]) ?>
<?php endif; ?>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
