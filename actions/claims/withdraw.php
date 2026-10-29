<?php
require_once __DIR__ . '/../../includes/init.php';
require_post();
$user = require_role('student');
csrf_verify();

$id    = int_param('id', 'POST');
$claim = $id !== null ? find_claim($id) : null;
if (!$claim || (int) $claim['claimant_id'] !== (int) $user['id']) {
    abort(404, 'This claim does not exist.');
}

try {
    withdraw_claim($id, (int) $user['id']);
    flash('success', 'Your claim was withdrawn.');
} catch (RuleViolation $e) {
    flash('error', $e->getMessage());
}
redirect('student/claim-details.php?id=' . $id);
