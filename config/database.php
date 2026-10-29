<?php
/**
 * Database connection (SQLite through PDO).
 *
 * The database file lives in /database/campusfind.sqlite. Set the environment
 * variable CAMPUSFIND_DB to use a different file (the automated tests do this
 * so they never touch your demo data).
 */
declare(strict_types=1);

function db_path(): string
{
    $override = getenv('CAMPUSFIND_DB');
    if ($override !== false && $override !== '') {
        return $override;
    }
    return dirname(__DIR__) . '/database/campusfind.sqlite';
}

/** Open a new PDO connection with foreign keys switched ON. */
function connect_database(string $path): PDO
{
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // SQLite ignores foreign keys unless this is enabled on EVERY connection.
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
}

/** Shared connection for the current request. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = connect_database(db_path());
    }
    return $pdo;
}
