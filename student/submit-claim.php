<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role('student');

$id     = int_param('id');
$report = $id !== null ? find_report($id) : null;
if (!$report || !report_visible_to($report, $user)) {
    abort(404, 'This report does not exist or is no longer available.');
}

$block = claim_block_reason($report, (int) $user['id']);
if ($block !== null) {
    flash('error', $block);
    redirect('student/item-details.php?id=' . (int) $report['id']);
}

$pageTitle = 'Claim this item';
$activeNav = 'browse';
include APP_ROOT . '/includes/layouts/header.php';
?>
<p class="back-link"><a href="<?= e(url('student/item-details.php?id=' . (int) $report['id'])) ?>"><?= icon('arrow-left', 'icon-sm') ?> Back to item</a></p>
<div class="page-header">
    <div>
        <h1>Claim this item</h1>
        <p class="muted">Staff will compare what you write with the item before approving.</p>
    </div>
</div>

<div class="detail-layout">
    <div class="detail-main">
        <?= error_summary() ?>
        <form method="post" action="<?= e(url('actions/claims/submit.php')) ?>" class="card form-card" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="item_report_id" value="<?= (int) $report['id'] ?>">

            <div class="form-group">
                <label for="claim_details">Prove it is yours</label>
                <textarea class="input" id="claim_details" name="claim_details" rows="8" maxlength="1500" required
                          data-counter="claim-count" aria-describedby="claim-help"<?= field_attrs('claim_details') ?>><?= old('claim_details') ?></textarea>
                <div class="field-foot">
                    <p class="help-text" id="claim-help">Write at least 20 characters.</p>
                    <span class="char-count" id="claim-count" aria-live="polite"></span>
                </div>
                <?= field_error('claim_details') ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" data-loading-text="Sending claim...">Submit claim</button>
                <a class="btn btn-secondary" href="<?= e(url('student/item-details.php?id=' . (int) $report['id'])) ?>">Cancel</a>
            </div>
        </form>
    </div>

    <aside class="detail-side">
        <section class="card side-card">
            <h2 class="card-title">Item you are claiming</h2>
            <p><strong><?= e($report['item_name']) ?></strong></p>
            <p class="muted"><?= e($report['category_name']) ?> &middot; <?= e($report['location']) ?><br>Found <?= e(format_date($report['date_occurred'])) ?></p>
        </section>
        <section class="card side-card">
            <h2 class="card-title">What to include</h2>
            <ul class="plain-list">
                <li>Unique marks, stickers, scratches or damage</li>
                <li>What is inside, or what is attached to it</li>
                <li>Serial number, brand or model</li>
                <li>When and where you last had it</li>
            </ul>
            <p class="help-text">Only you and the reviewing staff can read your claim. Do not post it anywhere else.</p>
        </section>
    </aside>
</div>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
