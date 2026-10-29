<?php
require_once __DIR__ . '/../../includes/init.php';
require_post();
$admin = require_role('admin');
csrf_verify();

$result = validate_category_input($_POST);
if ($result['errors']) {
    remember_form($_POST, $result['errors']);
    redirect('admin/manage-categories.php');
}

try {
    $stmt = db()->prepare('INSERT INTO categories (name, description, created_at) VALUES (:name, :desc, :created)');
    $stmt->execute([
        ':name'    => $result['data']['name'],
        ':desc'    => $result['data']['description'] !== '' ? $result['data']['description'] : null,
        ':created' => now(),
    ]);
} catch (PDOException $e) {
    // UNIQUE(name) caught a duplicate that slipped past the check above.
    remember_form($_POST, ['name' => 'A category with this name already exists.']);
    redirect('admin/manage-categories.php');
}

flash('success', 'Category "' . $result['data']['name'] . '" was added.');
redirect('admin/manage-categories.php');
