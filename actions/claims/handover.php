<?php
/** Staff: record that the item was physically handed over. This is the ONLY way an item becomes "returned". */
require_once __DIR__ . '/../../includes/init.php';
require_post();
$admin = require_role('admin');
csrf_verify();

$id    = int_param('id', 'POST');
$claim = $id !== null ? find_claim($id) : null;
if (!$claim) {
    abort(404, 'This claim does not exist.');
}
$notes = validate_staff_notes($_POST['handover_notes'] ?? '', false);
if ($notes['errors']) {
    flash('error', 'Handover notes must be at most 500 characters.');
    redirect('admin/view-claim.php?id=' . $id);
}

try {
    record_handover($id, (int) $admin['id'], $notes['data']);
    flash('success', 'Handover recorded. The item is now marked as returned.');
} catch (RuleViolation $e) {
    flash('error', $e->getMessage());
}
redirect('admin/view-claim.php?id=' . $id);
