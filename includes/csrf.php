<?php
/**
 * includes/csrf.php
 *
 * CSRF protection. Every state-changing form (login, profile update,
 * user create/edit/delete) must include csrf_field() and every
 * handler must call csrf_verify() before doing anything.
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function csrf_verify(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    if ($sent === '' || $stored === '') {
        return false;
    }

    return hash_equals($stored, $sent);
}

/**
 * Call this at the top of every POST handler. Dies with a 403
 * if the token is missing or invalid.
 */
function csrf_require_valid(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(403);
        die('Invalid or expired security token. Please go back, refresh the page, and try again.');
    }
}
