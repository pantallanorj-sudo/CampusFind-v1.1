<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role('student');

$id    = int_param('id');
$claim = $id !== null ? find_claim($id) : null;
// Another student's claim looks exactly like a claim that does not exist.
if (!$claim || (int) $claim['claimant_id'] !== (int) $user['id']) {
    abort(404, 'This claim does not exist.');
}

$pageTitle = 'Claim details';
$activeNav = 'my-claims';
include APP_ROOT . '/includes/layouts/header.php';
?>
<p class="back-link"><a href="<?= e(url('student/my-claims.php')) ?>"><?= icon('arrow-left', 'icon-sm') ?> Back to my claims</a></p>
<div class="page-header">
    <div>
        <div class="badge-row"><?= status_badge($claim['status'], 'claim') ?></div>
        <h1>Claim for <?= e($claim['item_name']) ?></h1>
        <p class="muted">Submitted <?= e(format_datetime($claim['created_at'])) ?></p>
    </div>
</div>

<div class="detail-layout">
    <div class="detail-main">
        <?php if ($claim['status'] === 'approved' && $claim['item_status'] === 'awaiting_handover'): ?>
            <p class="notice notice-success"><?= icon('check-circle', 'icon-sm') ?>
                <span><strong>Your claim was approved.</strong> The item is not marked as returned until you collect it. Bring a valid school ID to the
                <?= e(config('office_name')) ?>, <?= e(config('office_location')) ?> (<?= e(config('office_hours')) ?>).</span></p>
        <?php elseif ($claim['status'] === 'approved' && $claim['item_status'] === 'returned'): ?>
            <p class="notice notice-success"><?= icon('check-circle', 'icon-sm') ?> <span><strong>This item was handed over to you.</strong> Nothing more to do.</span></p>
        <?php elseif ($claim['status'] === 'pending'): ?>
            <p class="notice notice-info"><?= icon('clock', 'icon-sm') ?> <span>Staff have not reviewed this claim yet. You can withdraw it while it is pending.</span></p>
        <?php elseif ($claim['status'] === 'rejected'): ?>
            <p class="notice notice-danger"><?= icon('alert-circle', 'icon-sm') ?> <span>This claim was rejected. See the staff notes below.</span></p>
        <?php endif; ?>

        <article class="card">
            <div class="card-body">
                <h2 class="card-title">Your claim details</h2>
                <p><?= e_multiline($claim['claim_details']) ?></p>
            </div>
        </article>

        <?php if ($claim['staff_notes'] !== null && $claim['staff_notes'] !== ''): ?>
            <article class="card">
                <div class="card-body">
                    <h2 class="card-title">Staff notes</h2>
                    <p><?= e_multiline($claim['staff_notes']) ?></p>
                    <p class="muted">Reviewed by <?= e($claim['reviewer_name'] ?? 'staff') ?> on <?= e(format_datetime($claim['reviewed_at'])) ?></p>
                </div>
            </article>
        <?php endif; ?>
    </div>

    <aside class="detail-side">
        <section class="card side-card">
            <h2 class="card-title">Item</h2>
            <p><strong><?= e($claim['item_name']) ?></strong></p>
            <p class="muted"><?= e($claim['category_name']) ?> &middot; <?= e($claim['item_location']) ?></p>
            <p>Item status: <?= status_badge($claim['item_status']) ?></p>
            <?php if (!in_array($claim['item_status'], ['closed'], true)): ?>
                <a class="btn btn-secondary btn-block" href="<?= e(url('student/item-details.php?id=' . (int) $claim['item_report_id'])) ?>">View item</a>
            <?php endif; ?>
        </section>

        <?php if ($claim['status'] === 'pending'): ?>
            <section class="card side-card">
                <h2 class="card-title">Withdraw claim</h2>
                <p class="muted">Changed your mind or found the item? You can withdraw this claim.</p>
                <form method="post" action="<?= e(url('actions/claims/withdraw.php')) ?>" data-confirm="Withdraw this claim? You can submit a new one later if the item is still available.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $claim['id'] ?>">
                    <button type="submit" class="btn btn-danger-outline btn-block">Withdraw claim</button>
                </form>
            </section>
        <?php endif; ?>
    </aside>
</div>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
