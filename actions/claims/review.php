<?php
/** Staff: approve or reject a pending claim. Approval does NOT mark the item as returned. */
require_once __DIR__ . '/../../includes/init.php';
require_post();
$admin = require_role('admin');
csrf_verify();

$id    = int_param('id', 'POST');
$claim = $id !== null ? find_claim($id) : null;
if (!$claim) {
    abort(404, 'This claim does not exist.');
}
$back = 'admin/view-claim.php?id=' . $id;

$decision = str_param('decision', 'POST');
if (!in_array($decision, ['approve', 'reject'], true)) {
    flash('error', 'Choose approve or reject.');
    redirect($back);
}

$notes = validate_staff_notes($_POST['staff_notes'] ?? '', true);
if ($notes['errors']) {
    flash('error', 'Add staff notes (at least 5 characters). The claimant can read them.');
    redirect($back);
}

try {
    if ($decision === 'approve') {
        approve_claim($id, (int) $admin['id'], $notes['data']);
        flash('success', 'Claim approved. The item is now awaiting handover. Record the handover when the claimant collects it.');
    } else {
        reject_claim($id, (int) $admin['id'], $notes['data']);
        flash('success', 'Claim rejected. The report stays open for other claims.');
    }
} catch (RuleViolation $e) {
    flash('error', $e->getMessage());
}
redirect($back);
