<?php
/**
 * CSRF protection. Every form that changes data includes csrf_field(),
 * and every action file calls require_post_with_csrf() before doing anything.
 */
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        abort(403, 'Your form expired or could not be verified. Go back, reload the page and try again.');
    }
}

function require_post(): void
{
    if (!is_post()) {
        header('Allow: POST');
        abort(405);
    }
}

function require_post_with_csrf(): void
{
    require_post();
    csrf_verify();
}
