<?php
/** Shared footer. Closes the tags opened in header.php. */

// The page has already read any remembered form input and errors while it rendered.
// Clearing them here stops stale values from showing up on a different form later.
unset($_SESSION['form_old'], $_SESSION['form_errors']);
$showSidebar = isset($authUser) && $authUser !== null && ($layout ?? 'app') === 'app';
?>
<?php if ($showSidebar): ?>
        </main>
        <footer class="app-footer">
            <p>CampusFind &middot; <?= e(config('school_name')) ?> &middot; Lost &amp; Found Desk: <?= e(config('office_location')) ?></p>
        </footer>
    </div>
<?php else: ?>
    </main>
    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <p class="footer-brand">CampusFind</p>
                <p>A centralized lost-and-found system for <?= e(config('school_name')) ?>.</p>
            </div>
            <div>
                <p class="footer-heading">Lost &amp; Found Desk</p>
                <p><?= e(config('office_name')) ?><br><?= e(config('office_location')) ?><br><?= e(config('office_hours')) ?></p>
            </div>
            <div>
                <p class="footer-heading">Quick links</p>
                <p>
                    <a href="<?= e(url('login.php')) ?>">Log in</a><br>
                    <a href="<?= e(url('register.php')) ?>">Student registration</a>
                </p>
            </div>
        </div>
        <div class="container footer-bottom"><p>Course project demo. Sample data is fictional.</p></div>
    </footer>
<?php endif; ?>
</body>
</html>
