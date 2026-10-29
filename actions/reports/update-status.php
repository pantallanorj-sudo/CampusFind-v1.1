<?php
/** Staff: change a report's status by hand (close, reopen, or mark a lost item recovered). */
require_once __DIR__ . '/../../includes/init.php';
require_post();
$admin = require_role('admin');
csrf_verify();

$id     = int_param('id', 'POST');
$report = $id !== null ? find_report($id) : null;
if (!$report) {
    abort(404, 'This report does not exist.');
}
$back = 'admin/view-report.php?id=' . $id;

$newStatus = str_param('new_status', 'POST');
$notes     = validate_staff_notes($_POST['notes'] ?? '', true);
if ($notes['errors']) {
    flash('error', 'Add a short note (at least 5 characters) explaining the status change.');
    redirect($back);
}

try {
    staff_change_item_status($id, $newStatus, (int) $admin['id'], $notes['data']);
    flash('success', 'Status updated to "' . (item_statuses()[$newStatus] ?? $newStatus) . '".');
} catch (RuleViolation $e) {
    flash('error', $e->getMessage());
}
redirect($back);
