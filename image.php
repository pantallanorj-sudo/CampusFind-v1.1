<?php
/**
 * Serves an uploaded item photo. Photos are never linked directly:
 * the uploads folder is closed to the web, and this script checks that the
 * person is logged in and allowed to see the report first.
 */
require_once __DIR__ . '/includes/init.php';
$user = require_login();

$id     = int_param('id');
$report = $id !== null ? find_report($id) : null;
if (!$report || empty($report['image_path']) || !report_visible_to($report, $user)) {
    http_response_code(404);
    exit('Not found');
}

$file = APP_ROOT . '/uploads/items/' . basename($report['image_path']);
$info = is_file($file) ? @getimagesize($file) : false;
if ($info === false || empty($info['mime'])) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: ' . $info['mime']);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=3600');
readfile($file);
