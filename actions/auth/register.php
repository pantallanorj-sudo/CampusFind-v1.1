<?php
require_once __DIR__ . '/../../includes/init.php';
require_post_with_csrf();

$result = validate_register_input($_POST);
$errors = $result['errors'];
$data   = $result['data'];

if (!$errors && find_user_by_email($data['email']) !== null) {
    $errors['email'] = 'An account with this email already exists. Try logging in instead.';
}

if ($errors) {
    remember_form($_POST, $errors);
    redirect('register.php');
}

try {
    // The role is ALWAYS "student". A "role" field sent by the browser is never read.
    $stmt = db()->prepare(
        "INSERT INTO users (full_name, email, password_hash, role, created_at)
         VALUES (:name, :email, :hash, 'student', :created)"
    );
    $stmt->execute([
        ':name'    => $data['full_name'],
        ':email'   => $data['email'],
        ':hash'    => password_hash($data['password'], PASSWORD_DEFAULT),
        ':created' => now(),
    ]);
} catch (PDOException $e) {
    // The UNIQUE constraint on users.email caught a race between two sign-ups.
    remember_form($_POST, ['email' => 'An account with this email already exists. Try logging in instead.']);
    redirect('register.php');
}

$user = find_user_by_email($data['email']);
login_user($user);
flash('success', 'Your account is ready. Welcome to CampusFind, ' . $user['full_name'] . '!');
redirect('student/dashboard.php');
