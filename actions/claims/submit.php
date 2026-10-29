<?php
require_once __DIR__ . '/../../includes/init.php';
require_post();
$user = require_role('student');
csrf_verify();

$itemId = int_param('item_report_id', 'POST');
$report = $itemId !== null ? find_report($itemId) : null;
if (!$report || !report_visible_to($report, $user)) {
    abort(404, 'This report does not exist or is no longer available.');
}
$back = 'student/submit-claim.php?id=' . $itemId;

$result = validate_claim_details($_POST['claim_details'] ?? '');
if ($result['errors']) {
    remember_form($_POST, $result['errors']);
    redirect($back);
}

try {
    $claimId = submit_claim($itemId, (int) $user['id'], $result['data']);
} catch (RuleViolation $e) {
    flash('error', $e->getMessage());
    redirect('student/item-details.php?id=' . $itemId);
}

flash('success', 'Your claim was submitted. Staff will review it and you can follow its status here.');
redirect('student/claim-details.php?id=' . $claimId);
