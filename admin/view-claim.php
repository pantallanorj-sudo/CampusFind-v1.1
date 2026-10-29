<?php
require_once __DIR__ . '/../includes/init.php';
$admin = require_role('admin');

$id    = int_param('id');
$claim = $id !== null ? find_claim($id) : null;
if (!$claim) {
    abort(404, 'This claim does not exist.');
}

$stmt = db()->prepare("SELECT COUNT(*) FROM claim_requests WHERE item_report_id = :item AND status = 'pending' AND id != :id");
$stmt->execute([':item' => $claim['item_report_id'], ':id' => $claim['id']]);
$otherPending = (int) $stmt->fetchColumn();

$isPending      = $claim['status'] === 'pending';
$awaitingPickup = $claim['status'] === 'approved' && $claim['item_status'] === 'awaiting_handover';
$isReturned     = $claim['status'] === 'approved' && $claim['item_status'] === 'returned';

$pageTitle = 'Claim #' . (int) $claim['id'];
$activeNav = 'claims';
include APP_ROOT . '/includes/layouts/header.php';
?>
<p class="back-link"><a href="<?= e(url('admin/manage-claims.php')) ?>"><?= icon('arrow-left', 'icon-sm') ?> Back to claims</a></p>
<div class="page-header">
    <div>
        <div class="badge-row"><?= status_badge($claim['status'], 'claim') ?></div>
        <h1>Claim for <?= e($claim['item_name']) ?></h1>
        <p class="muted">Claim #<?= (int) $claim['id'] ?> &middot; submitted <?= e(format_datetime($claim['created_at'])) ?></p>
    </div>
</div>

<div class="detail-layout">
    <div class="detail-main">
        <?php if ($isPending): ?>
            <p class="notice notice-info"><?= icon('clock', 'icon-sm') ?> <span>This claim is waiting for review. Compare the details below with the physical item before deciding.</span></p>
        <?php elseif ($awaitingPickup): ?>
            <p class="notice notice-success"><?= icon('check-circle', 'icon-sm') ?> <span><strong>Approved, not yet collected.</strong> The item is still &ldquo;Awaiting handover&rdquo;. Record the handover once the claimant collects it.</span></p>
        <?php elseif ($isReturned): ?>
            <p class="notice notice-success"><?= icon('check-circle', 'icon-sm') ?> <span><strong>Handed over.</strong> The item is marked as returned.</span></p>
        <?php endif; ?>

        <article class="card">
            <div class="card-body">
                <h2 class="card-title">Claim details <span class="badge badge-private">Private</span></h2>
                <p><?= e_multiline($claim['claim_details']) ?></p>
                <p class="help-text">Visible only to staff and the claimant.</p>
            </div>
        </article>

        <article class="card">
            <div class="card-body">
                <h2 class="card-title">Item</h2>
                <div class="badge-row"><?= type_badge($claim['report_type']) ?> <?= status_badge($claim['item_status']) ?></div>
                <p><strong><?= e($claim['item_name']) ?></strong></p>
                <p><?= e_multiline($claim['item_description']) ?></p>
                <dl class="detail-list">
                    <div><dt>Category</dt><dd><?= e($claim['category_name']) ?></dd></div>
                    <div><dt>Found at</dt><dd><?= e($claim['item_location']) ?></dd></div>
                    <div><dt>Date found</dt><dd><?= e(format_date($claim['item_date_occurred'])) ?></dd></div>
                </dl>
                <a class="btn btn-secondary" href="<?= e(url('admin/view-report.php?id=' . (int) $claim['item_report_id'])) ?>">Open full report and history</a>
            </div>
        </article>

        <?php if ($claim['reviewed_at'] !== null): ?>
            <article class="card">
                <div class="card-body">
                    <h2 class="card-title">Review</h2>
                    <p class="muted">Reviewed by <?= e($claim['reviewer_name'] ?? 'staff') ?> on <?= e(format_datetime($claim['reviewed_at'])) ?></p>
                    <?php if ($claim['staff_notes']): ?>
                        <p><?= e_multiline($claim['staff_notes']) ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endif; ?>
    </div>

    <aside class="detail-side">
        <section class="card side-card">
            <h2 class="card-title">Claimant</h2>
            <p><strong><?= e($claim['claimant_name']) ?></strong><br><span class="muted"><?= e($claim['claimant_email']) ?></span></p>
        </section>

        <?php if ($isPending): ?>
            <section class="card side-card">
                <h2 class="card-title">Review this claim</h2>
                <form method="post" action="<?= e(url('actions/claims/review.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $claim['id'] ?>">
                    <div class="form-group">
                        <label for="staff_notes">Staff notes</label>
                        <textarea class="input" id="staff_notes" name="staff_notes" rows="4" maxlength="500" required aria-describedby="notes-help"></textarea>
                        <p class="help-text" id="notes-help">Required. Explain your decision. The claimant can read these notes.</p>
                    </div>
                    <?php if ($otherPending > 0): ?>
                        <p class="notice notice-warning"><?= icon('info', 'icon-sm') ?> <span>Approving also rejects the <?= $otherPending ?> other pending claim<?= $otherPending === 1 ? '' : 's' ?> for this item.</span></p>
                    <?php endif; ?>
                    <div class="side-actions">
                        <button type="submit" name="decision" value="approve" class="btn btn-primary btn-block"
                                data-confirm="Approve this claim? The item will move to Awaiting handover.">Approve claim</button>
                        <button type="submit" name="decision" value="reject" class="btn btn-danger-outline btn-block"
                                data-confirm="Reject this claim? The report stays open for other claims.">Reject claim</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($awaitingPickup): ?>
            <section class="card side-card">
                <h2 class="card-title">Record handover</h2>
                <p class="muted">Only record this after the claimant shows valid ID and physically collects the item.</p>
                <form method="post" action="<?= e(url('actions/claims/handover.php')) ?>" data-confirm="Record the handover? The item will be marked as returned.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $claim['id'] ?>">
                    <div class="form-group">
                        <label for="handover_notes">Handover notes <span class="optional">(optional)</span></label>
                        <textarea class="input" id="handover_notes" name="handover_notes" rows="3" maxlength="500" placeholder="e.g. ID checked, received by claimant"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" data-loading-text="Saving...">Record handover</button>
                </form>
            </section>

            <section class="card side-card">
                <details>
                    <summary>Revoke approval</summary>
                    <p class="muted">Use this if the claimant does not collect the item or the approval was a mistake. The claim becomes rejected and the report reopens.</p>
                    <form method="post" action="<?= e(url('actions/claims/revoke.php')) ?>" data-confirm="Revoke this approval?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $claim['id'] ?>">
                        <div class="form-group">
                            <label for="revoke_notes">Reason</label>
                            <textarea class="input" id="revoke_notes" name="revoke_notes" rows="3" maxlength="500" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger-outline btn-block">Revoke approval</button>
                    </form>
                </details>
            </section>
        <?php endif; ?>
    </aside>
</div>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
