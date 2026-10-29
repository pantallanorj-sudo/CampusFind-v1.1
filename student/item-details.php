<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role('student');

$id     = int_param('id');
$report = $id !== null ? find_report($id) : null;
if (!$report || !report_visible_to($report, $user)) {
    abort(404, 'This report does not exist or is no longer available.');
}

$uid     = (int) $user['id'];
$isOwner = (int) $report['user_id'] === $uid;
$isFound = $report['report_type'] === 'found';

$claimBlock = ($isFound && !$isOwner) ? claim_block_reason($report, $uid) : null;
$canClaim   = $isFound && !$isOwner && $claimBlock === null;
$myClaim    = ($isFound && !$isOwner) ? active_claim_for_user((int) $report['id'], $uid) : null;

$canEdit      = $isOwner && owner_can_edit($report);
$deleteBlock  = $isOwner ? delete_block_reason($report) : null;
$canDelete    = $isOwner && $deleteBlock === null;
$canClose     = $isOwner && $report['status'] === 'open';

$pendingOnMine = 0;
if ($isOwner && $isFound) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM claim_requests WHERE item_report_id = :id AND status = 'pending'");
    $stmt->execute([':id' => $report['id']]);
    $pendingOnMine = (int) $stmt->fetchColumn();
}

// Owners can follow the status trail. Notes are left out because they can mention other students.
$timeline = $isOwner ? history_for_report((int) $report['id']) : [];

$pageTitle = $report['item_name'];
$activeNav = $isOwner ? 'my-reports' : 'browse';
include APP_ROOT . '/includes/layouts/header.php';
?>
<p class="back-link"><a href="<?= e(url($isOwner ? 'student/my-reports.php' : 'student/browse-items.php')) ?>"><?= icon('arrow-left', 'icon-sm') ?> Back to <?= $isOwner ? 'my reports' : 'browse' ?></a></p>

<div class="page-header">
    <div>
        <div class="badge-row"><?= type_badge($report['report_type']) ?> <?= status_badge($report['status']) ?> <?php if ($isOwner): ?><span class="badge badge-own">Your report</span><?php endif; ?></div>
        <h1><?= e($report['item_name']) ?></h1>
    </div>
</div>

<div class="detail-layout">
    <div class="detail-main">
        <article class="card">
            <?php if (!empty($report['image_path'])): ?>
                <img class="detail-image" src="<?= e(url('image.php?id=' . (int) $report['id'])) ?>" alt="Photo of <?= e($report['item_name']) ?>">
            <?php endif; ?>
            <div class="card-body">
                <h2 class="card-title">Description</h2>
                <p><?= e_multiline($report['description']) ?></p>
                <dl class="detail-list">
                    <div><dt>Category</dt><dd><?= e($report['category_name']) ?></dd></div>
                    <div><dt><?= $isFound ? 'Found at' : 'Lost at' ?></dt><dd><?= e($report['location']) ?></dd></div>
                    <div><dt><?= $isFound ? 'Date found' : 'Date lost' ?></dt><dd><?= e(format_date($report['date_occurred'])) ?></dd></div>
                    <div><dt>Reported on</dt><dd><?= e(format_date($report['created_at'])) ?></dd></div>
                    <div><dt>Status</dt><dd><?= status_badge($report['status']) ?> <span class="muted"><?= e(item_status_descriptions()[$report['status']] ?? '') ?></span></dd></div>
                </dl>
            </div>
        </article>

        <?php if ($timeline): ?>
            <article class="card">
                <div class="card-body">
                    <h2 class="card-title">Status trail</h2>
                    <ol class="timeline">
                        <?php foreach ($timeline as $row): ?>
                            <li>
                                <span class="timeline-dot"></span>
                                <div>
                                    <strong><?= e(item_statuses()[$row['new_status']] ?? $row['new_status']) ?></strong>
                                    <span class="muted"><?= e(format_datetime($row['created_at'])) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </article>
        <?php endif; ?>
    </div>

    <aside class="detail-side">
        <?php if ($isOwner): ?>
            <section class="card side-card">
                <h2 class="card-title">Manage your report</h2>
                <?php if ($isFound && $pendingOnMine > 0): ?>
                    <p class="notice notice-info"><?= icon('info', 'icon-sm') ?> <span><?= $pendingOnMine ?> claim<?= $pendingOnMine === 1 ? ' is' : 's are' ?> waiting for staff review. Staff handle verification, so you do not need to do anything.</span></p>
                <?php endif; ?>
                <?php if ($isFound && in_array($report['status'], ['open', 'claim_pending'], true)): ?>
                    <p class="muted">Please bring the item to the <?= e(config('office_name')) ?> (<?= e(config('office_location')) ?>) if you have not already.</p>
                <?php endif; ?>

                <div class="side-actions">
                    <?php if ($canEdit): ?>
                        <a class="btn btn-secondary btn-block" href="<?= e(url('student/edit-report.php?id=' . (int) $report['id'])) ?>"><?= icon('edit', 'icon-sm') ?> Edit report</a>
                    <?php endif; ?>
                    <?php if ($canClose): ?>
                        <form method="post" action="<?= e(url('actions/reports/close.php')) ?>"
                              data-confirm="Close this report? It will be archived and will no longer appear in Browse.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $report['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-block"><?= icon('check', 'icon-sm') ?> <?= $isFound ? 'Close report' : 'I found it, close report' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                        <a class="btn btn-danger-outline btn-block" href="<?= e(url('student/delete-report.php?id=' . (int) $report['id'])) ?>"><?= icon('trash', 'icon-sm') ?> Delete report</a>
                    <?php endif; ?>
                </div>
                <?php if (!$canEdit): ?>
                    <p class="help-text">Only open reports can be edited.</p>
                <?php endif; ?>
                <?php if ($deleteBlock !== null): ?>
                    <p class="help-text"><?= e($deleteBlock) ?></p>
                <?php endif; ?>
            </section>
        <?php elseif ($isFound): ?>
            <section class="card side-card">
                <h2 class="card-title">Is this yours?</h2>
                <?php if ($canClaim): ?>
                    <p>Send a claim with details only the owner would know. Staff will compare them with the item.</p>
                    <a class="btn btn-primary btn-block" href="<?= e(url('student/submit-claim.php?id=' . (int) $report['id'])) ?>"><?= icon('clipboard', 'icon-sm') ?> Claim this item</a>
                <?php else: ?>
                    <p class="notice notice-warning"><?= icon('info', 'icon-sm') ?> <span><?= e($claimBlock) ?></span></p>
                    <?php if ($myClaim): ?>
                        <a class="btn btn-secondary btn-block" href="<?= e(url('student/claim-details.php?id=' . (int) $myClaim['id'])) ?>">View my claim</a>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="card side-card">
                <h2 class="card-title">Did you find this?</h2>
                <p>Lost items cannot be claimed. If you found it, report it as a found item or bring it to the <?= e(config('office_name')) ?>.</p>
                <a class="btn btn-primary btn-block" href="<?= e(url('student/report-item.php?type=found')) ?>">Report a found item</a>
            </section>
        <?php endif; ?>

        <section class="card side-card">
            <h2 class="card-title">Lost &amp; Found Desk</h2>
            <p><?= e(config('office_location')) ?><br><?= e(config('office_hours')) ?></p>
        </section>
    </aside>
</div>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
