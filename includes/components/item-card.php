<?php
/** One item card for the browse page. Shows public details only (no reporter, no claim data). */
declare(strict_types=1);

function render_item_card(array $item, string $detailsPath, bool $isOwner = false): string
{
    ob_start();
    ?>
    <article class="item-card">
        <a class="item-card-media" href="<?= e(url($detailsPath)) ?>" tabindex="-1" aria-hidden="true">
            <?php if (!empty($item['image_path'])): ?>
                <img src="<?= e(url('image.php?id=' . (int) $item['id'])) ?>" alt="" loading="lazy">
            <?php else: ?>
                <span class="item-card-placeholder"><?= icon('package', 'icon-xl') ?></span>
            <?php endif; ?>
        </a>
        <div class="item-card-body">
            <div class="badge-row">
                <?= type_badge($item['report_type']) ?>
                <?= status_badge($item['status']) ?>
                <?php if ($isOwner): ?><span class="badge badge-own">Your report</span><?php endif; ?>
            </div>
            <h3 class="item-card-title">
                <a href="<?= e(url($detailsPath)) ?>"><?= e($item['item_name']) ?></a>
            </h3>
            <p class="meta-line"><?= icon('tag', 'icon-sm') ?><span><?= e($item['category_name']) ?></span></p>
            <p class="meta-line"><?= icon('map-pin', 'icon-sm') ?><span><?= e($item['location']) ?></span></p>
            <p class="meta-line"><?= icon('calendar', 'icon-sm') ?><span><?= e(format_date($item['date_occurred'])) ?></span></p>
            <p class="item-card-excerpt"><?= e(excerpt($item['description'], 100)) ?></p>
            <a class="btn btn-secondary btn-sm" href="<?= e(url($detailsPath)) ?>">View details</a>
        </div>
    </article>
    <?php
    return (string) ob_get_clean();
}
