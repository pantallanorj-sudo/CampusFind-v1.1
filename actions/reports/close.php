<?php
require_once __DIR__ . '/../../includes/init.php';
require_post();
$user = require_role('student');
csrf_verify();

$id     = int_param('id', 'POST');
$report = $id !== null ? find_report($id) : null;
if (!$report) {
    abort(404, 'This report does not exist.');
}
if ((int) $report['user_id'] !== (int) $user['id']) {
    abort(403, 'You can only close your own reports.');
}

try {
    owner_close_report($id, (int) $user['id']);
    flash('success', 'The report was closed.');
} catch (RuleViolation $e) {
    flash('error', $e->getMessage());
}
redirect('student/item-details.php?id=' . $id);
