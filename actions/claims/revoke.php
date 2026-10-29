<?php
/** Staff: cancel an approval before handover (for example the claimant never collects the item). */
require_once __DIR__ . '/../../includes/init.php';
require_post();
$admin = require_role('admin');
csrf_verify();

$id    = int_param('id', 'POST');
$claim = $id !== null ? find_claim($id) : null;
if (!$claim) {
    abort(404, 'This claim does not exist.');
}
$notes = validate_staff_notes($_POST['revoke_notes'] ?? '', true);
if ($notes['errors']) {
    flash('error', 'Add a reason (at least 5 characters) for revoking the approval.');
    redirect('admin/view-claim.php?id=' . $id);
}

try {
    revoke_claim($id, (int) $admin['id'], $notes['data']);
    flash('success', 'Approval revoked. The report is open for claims again.');
} catch (RuleViolation $e) {
    flash('error', $e->getMessage());
}
redirect('admin/view-claim.php?id=' . $id);
