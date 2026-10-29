<?php
require_once __DIR__ . '/../includes/init.php';
$admin = require_role('admin');

$categories = list_categories_with_counts();

$editing = null;
$editId  = int_param('edit');
if ($editId !== null) {
    foreach ($categories as $cat) {
        if ((int) $cat['id'] === $editId) {
            $editing = $cat;
            break;
        }
    }
    if ($editing === null) {
        flash('error', 'That category does not exist.');
        redirect('admin/manage-categories.php');
    }
}

$pageTitle = 'Manage categories';
$activeNav = 'categories';
include APP_ROOT . '/includes/layouts/header.php';
?>
<div class="page-header">
    <div>
        <h1>Manage categories</h1>
        <p class="muted">Categories group reports so students can filter and search.</p>
    </div>
</div>

<div class="detail-layout categories-layout">
    <div class="detail-main">
        <div class="table-wrap card">
            <table class="table">
                <thead>
                    <tr><th scope="col">Category</th><th scope="col">Reports</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td data-label="Category">
                                <span class="table-title"><?= e($cat['name']) ?></span>
                                <?php if ($cat['description']): ?><span class="muted block"><?= e($cat['description']) ?></span><?php endif; ?>
                            </td>
                            <td data-label="Reports"><?= (int) $cat['report_count'] ?></td>
                            <td class="row-actions">
                                <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/manage-categories.php?edit=' . (int) $cat['id'])) ?>">Edit</a>
                                <?php if ((int) $cat['report_count'] === 0): ?>
                                    <form method="post" action="<?= e(url('actions/categories/delete.php')) ?>" class="inline-form"
                                          data-confirm="Delete the category &quot;<?= e($cat['name']) ?>&quot;?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                        <button type="submit" class="btn btn-danger-outline btn-sm">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted in-use">In use</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="help-text">A category that still has reports cannot be deleted. Rename it instead, or move the reports first.</p>
    </div>

    <aside class="detail-side">
        <?= error_summary() ?>
        <section class="card side-card">
            <h2 class="card-title"><?= $editing ? 'Edit category' : 'Add category' ?></h2>
            <form method="post" action="<?= e(url($editing ? 'actions/categories/update.php' : 'actions/categories/create.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
                <div class="form-group">
                    <label for="name">Name</label>
                    <input class="input" type="text" id="name" name="name" maxlength="60" required value="<?= old('name', $editing['name'] ?? '') ?>"<?= field_attrs('name') ?>>
                    <?= field_error('name') ?>
                </div>
                <div class="form-group">
                    <label for="description">Description <span class="optional">(optional)</span></label>
                    <input class="input" type="text" id="description" name="description" maxlength="200" value="<?= old('description', $editing['description'] ?? '') ?>"<?= field_attrs('description') ?>>
                    <?= field_error('description') ?>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" data-loading-text="Saving..."><?= $editing ? 'Save category' : 'Add category' ?></button>
                    <?php if ($editing): ?><a class="btn btn-secondary" href="<?= e(url('admin/manage-categories.php')) ?>">Cancel</a><?php endif; ?>
                </div>
            </form>
        </section>
    </aside>
</div>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
