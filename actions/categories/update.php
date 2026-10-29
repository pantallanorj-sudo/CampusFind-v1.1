<?php
require_once __DIR__ . '/../../includes/init.php';
require_post();
$admin = require_role('admin');
csrf_verify();

$id = int_param('id', 'POST');
$exists = false;
if ($id !== null) {
    $check = db()->prepare('SELECT 1 FROM categories WHERE id = :id');
    $check->execute([':id' => $id]);
    $exists = (bool) $check->fetchColumn();
}
if (!$exists) {
    flash('error', 'That category does not exist.');
    redirect('admin/manage-categories.php');
}

$result = validate_category_input($_POST, $id);
if ($result['errors']) {
    remember_form($_POST, $result['errors']);
    redirect('admin/manage-categories.php?edit=' . $id);
}

try {
    $stmt = db()->prepare('UPDATE categories SET name = :name, description = :desc WHERE id = :id');
    $stmt->execute([
        ':name' => $result['data']['name'],
        ':desc' => $result['data']['description'] !== '' ? $result['data']['description'] : null,
        ':id'   => $id,
    ]);
} catch (PDOException $e) {
    remember_form($_POST, ['name' => 'A category with this name already exists.']);
    redirect('admin/manage-categories.php?edit=' . $id);
}

flash('success', 'Category updated.');
redirect('admin/manage-categories.php');
