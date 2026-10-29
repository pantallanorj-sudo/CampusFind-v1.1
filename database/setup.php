<?php
/**
 * CampusFind database setup (command line only).
 *
 *   php database/setup.php            create the database (stops if one already exists)
 *   php database/setup.php --fresh    delete the old database and rebuild it
 *   php database/setup.php --db=/tmp/test.sqlite --fresh
 *
 * Steps: schema.sql -> demo users (passwords hashed with password_hash) -> seed.sql
 *
 * DEVELOPMENT ONLY: the demo accounts below share a known password. Change or remove
 * them before any real deployment.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line: php database/setup.php');
}

require_once __DIR__ . '/../config/database.php';

const DEMO_PASSWORD = 'Demo@12345';

$demoUsers = [
    ['Rowena Mendoza',   'admin@campusfind.test',                    'admin'],
    ['Maria Santos',             'maria.santos@student.campusfind.test',     'student'],
    ['Juan Dela Cruz',           'juan.delacruz@student.campusfind.test',    'student'],
    ['Ana Reyes',                'ana.reyes@student.campusfind.test',        'student'],
];

$options = getopt('', ['fresh', 'db::', 'no-sample', 'help']);
if (isset($options['help'])) {
    echo "Usage: php database/setup.php [--fresh] [--no-sample] [--db=/path/to/file.sqlite]\n";
    exit(0);
}

$path = isset($options['db']) && is_string($options['db']) && $options['db'] !== ''
    ? $options['db']
    : db_path();

date_default_timezone_set('Asia/Manila');

if (is_file($path)) {
    if (!isset($options['fresh'])) {
        fwrite(STDERR, "A database already exists at:\n  $path\nRun with --fresh to delete it and start over.\n");
        exit(1);
    }
    unlink($path);
}

$dir = dirname($path);
if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
    fwrite(STDERR, "Could not create folder: $dir\n");
    exit(1);
}

$uploadDir = dirname(__DIR__) . '/uploads/items';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

try {
    $pdo = connect_database($path);

    // 1. Tables and indexes
    $pdo->exec((string) file_get_contents(__DIR__ . '/schema.sql'));

    // 2. Demo users (passwords are hashed by PHP, never stored as plain text)
    $now = date('Y-m-d H:i:s');
    $insert = $pdo->prepare(
        'INSERT INTO users (full_name, email, password_hash, role, created_at)
         VALUES (:name, :email, :hash, :role, :created)'
    );
    foreach ($demoUsers as [$name, $email, $role]) {
        $insert->execute([
            ':name'    => $name,
            ':email'   => $email,
            ':hash'    => password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT),
            ':role'    => $role,
            ':created' => $now,
        ]);
    }

    // 3. Categories, sample reports, claims and history
    if (isset($options['no-sample'])) {
        // Categories are needed for the app to work, so keep just those.
        $seed = (string) file_get_contents(__DIR__ . '/seed.sql');
        $categoriesOnly = substr($seed, 0, (int) strpos($seed, '-- --------------------------------------------------------- item reports'));
        $pdo->exec($categoriesOnly);
    } else {
        $pdo->exec((string) file_get_contents(__DIR__ . '/seed.sql'));
    }

    $violations = $pdo->query('PRAGMA foreign_key_check')->fetchAll();
    if ($violations) {
        throw new RuntimeException('Foreign key check failed after seeding.');
    }
} catch (Throwable $e) {
    if (isset($pdo)) {
        unset($pdo);
    }
    if (is_file($path)) {
        unlink($path);
    }
    fwrite(STDERR, 'Setup failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "CampusFind database created: $path\n\n";
echo "Development-only demo accounts (password for all: " . DEMO_PASSWORD . ")\n";
foreach ($demoUsers as [$name, $email, $role]) {
    printf("  %-8s %-42s %s\n", $role, $email, $name);
}
echo "\nChange or remove these accounts before any real deployment.\n";
