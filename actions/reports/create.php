<?php
require_once __DIR__ . '/../../includes/init.php';
require_post();
$user = require_role('student');
csrf_verify();

$type = str_param('report_type', 'POST');
if (!array_key_exists($type, report_types())) {
    flash('error', 'Choose whether you lost or found the item.');
    redirect('student/report-item.php');
}
$back = 'student/report-item.php?type=' . $type;

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

$imagePath = null;
try {
    if ($photoExt !== null) {
        $imagePath = store_uploaded_image($_FILES['photo'], $photoExt);
    }
    $reportId = create_report((int) $user['id'], $type, $result['data'], $imagePath);
} catch (RuleViolation $e) {
    delete_uploaded_image($imagePath);
    flash('error', $e->getMessage());
    redirect($back);
} catch (Throwable $e) {
    delete_uploaded_image($imagePath);
    throw $e;
}

flash('success', $type === 'found'
    ? 'Your found-item report was saved. Please bring the item to the ' . config('office_name') . '.'
    : 'Your lost-item report was saved. We will keep it open.');
redirect('student/item-details.php?id=' . $reportId);
