<?php
require_once __DIR__ . '/../../includes/init.php';
require_post();
$admin = require_role('admin');
csrf_verify();

$id = int_param('id', 'POST');
if ($id === null) {
    flash('error', 'That category does not exist.');
    redirect('admin/manage-categories.php');
}

try {
    $deleted = with_transaction(function (PDO $pdo) use ($id): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM item_reports WHERE category_id = :id');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuleViolation('This category still has reports, so it cannot be deleted.');
        }
        $del = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        $del->execute([':id' => $id]);
        return $del->rowCount() === 1;
    });
    flash($deleted ? 'success' : 'error', $deleted ? 'Category deleted.' : 'That category does not exist.');
} catch (RuleViolation $e) {
    flash('error', $e->getMessage());
} catch (PDOException $e) {
    flash('error', 'This category is still in use and cannot be deleted.');
}
redirect('admin/manage-categories.php');
