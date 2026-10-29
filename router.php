<?php
/**
 * Router for PHP's built-in development server:
 *     php -S localhost:8000 router.php
 *
 * It does two things: (1) blocks direct web access to private folders, because the
 * built-in server ignores .htaccess files; (2) lets everything else run normally.
 * Apache users get the same protection from the .htaccess files in each private folder.
 */
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Resolve ../ tricks, then compare against the private folders.
$real = realpath(__DIR__ . $path);
$root = realpath(__DIR__);
if ($real !== false && $root !== false) {
    $relative = str_replace('\\', '/', substr($real, strlen($root)));
    if (preg_match('#^/(config|database|includes|tests|docs|uploads)(/|$)#i', $relative) || preg_match('#/\.[^/]+#', $relative)
        || preg_match('#^/(README\.md|router\.php)$#i', $relative)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 Forbidden\n";
        return true;
    }
}

// Unknown paths get a clean 404 (the built-in server would otherwise fall back to index.php).
if ($real === false && $path !== '/') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 Not Found\n";
    return true;
}

return false;   // serve the requested file or PHP script as usual
