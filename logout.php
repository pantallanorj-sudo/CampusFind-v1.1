<?php
/** Log out. Must be a POST with a CSRF token (the "Log out" buttons are small forms). */
require_once __DIR__ . '/includes/init.php';

if (!is_post()) {
    redirect(is_logged_in() ? dashboard_path_for(current_user()) : 'login.php');
}
csrf_verify();

logout_user();
flash('success', 'You have been logged out.');
redirect('login.php');
