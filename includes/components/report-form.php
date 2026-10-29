<?php
/**
 * The lost/found report form, used for both "create" and "edit".
 * $o keys: action, type, report_id (edit only), values (defaults), submit_label, cancel_path, image_path
 */
declare(strict_types=1);

function render_report_form(array $o): string
{
    $type   = $o['type'];
    $v      = $o['values'] ?? [];
    $isEdit = !empty($o['report_id']);
    $cats   = list_categories();
    $selectedCat = (string) (form_old()['category_id'] ?? ($v['category_id'] ?? ''));

    ob_start();
    ?>
    <?= error_summary() ?>
    <form method="post" action="<?= e(url($o['action'])) ?>" enctype="multipart/form-data" class="card form-card" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="report_type" value="<?= e($type) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $o['report_id'] ?>"><?php endif; ?>

        <div class="form-group">
            <label for="item_name">Item name</label>
            <input class="input" type="text" id="item_name" name="item_name" maxlength="100" required
                   value="<?= old('item_name', $v['item_name'] ?? '') ?>"
                   placeholder="<?= $type === 'found' ? 'e.g. Black bifold wallet' : 'e.g. Gray Jansport backpack' ?>"<?= field_attrs('item_name') ?>>
            <?= field_error('item_name') ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category_id">Category</label>
                <select class="input" id="category_id" name="category_id" required<?= field_attrs('category_id') ?>>
                    <option value="">Choose a category</option>
                    <?php foreach ($cats as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"<?= $selectedCat === (string) $cat['id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error('category_id') ?>
            </div>
            <div class="form-group">
                <label for="date_occurred"><?= $type === 'found' ? 'Date found' : 'Date lost' ?></label>
                <input class="input" type="date" id="date_occurred" name="date_occurred" required max="<?= e(date('Y-m-d')) ?>"
                       value="<?= old('date_occurred', $v['date_occurred'] ?? date('Y-m-d')) ?>"<?= field_attrs('date_occurred') ?>>
                <?= field_error('date_occurred') ?>
            </div>
        </div>

        <div class="form-group">
            <label for="location"><?= $type === 'found' ? 'Where was it found?' : 'Where did you lose it?' ?></label>
            <input class="input" type="text" id="location" name="location" maxlength="150" required
                   value="<?= old('location', $v['location'] ?? '') ?>"
                   placeholder="e.g. Main Library, 2nd floor"<?= field_attrs('location') ?>>
            <?= field_error('location') ?>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea class="input" id="description" name="description" rows="5" maxlength="1000" required
                      data-counter="description-count" aria-describedby="description-help"<?= field_attrs('description') ?>><?= old('description', $v['description'] ?? '') ?></textarea>
            <div class="field-foot">
                <p class="help-text" id="description-help">
                    <?php if ($type === 'found'): ?>
                        Describe the item in general terms (colour, size, brand). Keep unique details such as serial numbers,
                        names on IDs or what is inside private. Staff will ask the owner to describe them.
                    <?php else: ?>
                        Include anything that helps identify it: colour, brand, marks, what is inside.
                    <?php endif; ?>
                </p>
                <span class="char-count" id="description-count" aria-live="polite"></span>
            </div>
            <?= field_error('description') ?>
        </div>

        <div class="form-group">
            <label for="photo">Photo <span class="optional">(optional)</span></label>
            <?php if ($isEdit && !empty($o['image_path'])): ?>
                <div class="current-photo">
                    <img src="<?= e(url('image.php?id=' . (int) $o['report_id'])) ?>" alt="Current photo of the item">
                    <label class="checkbox"><input type="checkbox" name="remove_image" value="1"> Remove this photo</label>
                </div>
            <?php endif; ?>
            <input class="input" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp"
                   data-preview="photo-preview" data-max-bytes="<?= (int) config('upload_max_bytes') ?>" aria-describedby="photo-help"<?= field_attrs('photo') ?>>
            <p class="help-text" id="photo-help">JPG, PNG or WebP, up to <?= (int) (config('upload_max_bytes') / 1048576) ?> MB.<?= $isEdit ? ' Choosing a new photo replaces the current one.' : '' ?></p>
            <img id="photo-preview" class="photo-preview" alt="Preview of the selected photo" hidden>
            <?= field_error('photo') ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" data-loading-text="Saving..."><?= e($o['submit_label']) ?></button>
            <a class="btn btn-secondary" href="<?= e(url($o['cancel_path'])) ?>">Cancel</a>
        </div>
    </form>
    <?php
    return (string) ob_get_clean();
}
