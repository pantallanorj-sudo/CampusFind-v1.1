<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Campus lost and found';
$layout    = 'public';
$user      = current_user();
include APP_ROOT . '/includes/layouts/header.php';
?>
<section class="hero">
    <div class="container hero-inner">
        <div class="hero-copy">
            <h1>Lost something on campus? Let&rsquo;s get it back to you.</h1>
            <p class="hero-lead">
                CampusFind keeps every lost and found report from <?= e(config('school_name')) ?> in one place.
                Report an item, browse what has been turned in, and claim what is yours.
                Staff check each claim before anything changes hands.
            </p>
            <div class="hero-actions">
                <?php if ($user): ?>
                    <a class="btn btn-light btn-lg" href="<?= e(url(dashboard_path_for($user))) ?>">Go to my dashboard</a>
                <?php else: ?>
                    <a class="btn btn-light btn-lg" href="<?= e(url('login.php')) ?>">Log in</a>
                    <a class="btn btn-outline-light btn-lg" href="<?= e(url('register.php')) ?>">Register as a student</a>
                <?php endif; ?>
            </div>
            <p class="hero-note">Reports and claims are visible only to logged-in members of the school.</p>
        </div>

        <aside class="hero-ticket" aria-label="Example of how a report moves through CampusFind">
            <p class="ticket-label">Example report</p>
            <h2 class="ticket-title">Yellow rain jacket</h2>
            <p class="ticket-meta"><?= icon('map-pin', 'icon-sm') ?> Science Building lobby</p>
            <ol class="ticket-steps">
                <li class="done"><span class="ticket-dot"><?= icon('check', 'icon-xs') ?></span><div><strong>Reported as found</strong><span>A student turns it in online</span></div></li>
                <li class="done"><span class="ticket-dot"><?= icon('check', 'icon-xs') ?></span><div><strong>Claim submitted</strong><span>The owner describes private details</span></div></li>
                <li class="current"><span class="ticket-dot"><?= icon('clock', 'icon-xs') ?></span><div><strong>Staff verify</strong><span>Details are checked, then approved</span></div></li>
                <li><span class="ticket-dot"></span><div><strong>Handed over</strong><span>Staff record the return</span></div></li>
            </ol>
        </aside>
    </div>
</section>

<section class="section" id="how-it-works">
    <div class="container">
        <h2 class="section-title">How CampusFind works</h2>
        <p class="section-lead">Four steps, from the moment something goes missing to the moment it is back in your hands.</p>
        <ol class="steps-grid">
            <li class="step-card">
                <span class="step-number">1</span>
                <h3>Report</h3>
                <p>Lost an item or found one? Post a report with where and when it happened. A photo is optional.</p>
            </li>
            <li class="step-card">
                <span class="step-number">2</span>
                <h3>Browse and claim</h3>
                <p>Search the found items. If one is yours, send a claim with details only the owner would know.</p>
            </li>
            <li class="step-card">
                <span class="step-number">3</span>
                <h3>Staff verify</h3>
                <p>The Lost &amp; Found Desk compares your details with the item, then approves or rejects the claim.</p>
            </li>
            <li class="step-card">
                <span class="step-number">4</span>
                <h3>Collect</h3>
                <p>Bring a valid ID to the desk. Staff record the handover, so the item shows as returned.</p>
            </li>
        </ol>
    </div>
</section>

<section class="section section-muted">
    <div class="container trust-grid">
        <div>
            <span class="trust-icon"><?= icon('shield') ?></span>
            <h3>Claim details stay private</h3>
            <p>What you write in a claim is seen only by you and the staff reviewing it.</p>
        </div>
        <div>
            <span class="trust-icon"><?= icon('check-circle') ?></span>
            <h3>Approval is not a handover</h3>
            <p>An approved claim waits for pickup. An item is marked returned only when staff record the handover.</p>
        </div>
        <div>
            <span class="trust-icon"><?= icon('history') ?></span>
            <h3>Every change is recorded</h3>
            <p>Each status change is logged with who made it and when, so nothing gets lost twice.</p>
        </div>
    </div>
</section>
<?php include APP_ROOT . '/includes/layouts/footer.php'; ?>
