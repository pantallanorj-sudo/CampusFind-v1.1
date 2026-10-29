<?php
/** Prints and clears queued flash messages (success / error / warning / info). */
$flashIcons = ['success' => 'check-circle', 'error' => 'alert-circle', 'warning' => 'alert-circle', 'info' => 'info'];
foreach (get_flashes() as $flashItem):
    $flashType = isset($flashIcons[$flashItem['type']]) ? $flashItem['type'] : 'info';
    ?>
    <div class="flash flash-<?= e($flashType) ?>" role="<?= $flashType === 'error' ? 'alert' : 'status' ?>">
        <span class="flash-icon"><?= icon($flashIcons[$flashType]) ?></span>
        <p><?= e($flashItem['message']) ?></p>
        <button type="button" class="flash-close" data-dismiss aria-label="Dismiss message"><?= icon('x', 'icon-sm') ?></button>
    </div>
<?php endforeach; ?>
