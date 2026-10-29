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
    abort(403, 'You can only delete your own reports.');
}

try {
    $imagePath = delete_report($id, (int) $user['id']);
    delete_uploaded_image($imagePath);
} catch (RuleViolation $e) {
    flash('error', $e->getMessage());
    redirect('student/item-details.php?id=' . $id);
}

flash('success', 'The report was deleted.');
redirect('student/my-reports.php');
