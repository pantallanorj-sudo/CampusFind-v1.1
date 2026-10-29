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
    abort(403, 'You can only edit your own reports.');
}
$back = 'student/edit-report.php?id=' . $id;

if (!owner_can_edit($report)) {
    flash('error', 'Only open reports can be edited.');
    redirect('student/item-details.php?id=' . $id);
}

$result = validate_report_input($_POST);
$errors = $result['errors'];
[$photoError, $photoExt] = validate_image_upload($_FILES['photo'] ?? null);
if ($photoError !== null) {
    $errors['photo'] = $photoError;
}
if ($errors) {
    remember_form($_POST, $errors);
    redirect($back);
}

$newImage = null;
try {
    if ($photoExt !== null) {
        $newImage = store_uploaded_image($_FILES['photo'], $photoExt);
    }
    $oldImage = update_report($id, (int) $user['id'], $result['data'], $newImage, !empty($_POST['remove_image']));
    delete_uploaded_image($oldImage);                    // only after the database change succeeded
} catch (RuleViolation $e) {
    delete_uploaded_image($newImage);
    flash('error', $e->getMessage());
    redirect('student/item-details.php?id=' . $id);
} catch (Throwable $e) {
    delete_uploaded_image($newImage);
    throw $e;
}

flash('success', 'Your report was updated.');
redirect('student/item-details.php?id=' . $id);
