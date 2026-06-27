<?php
/**
 * includes/session.php
 *
 * Hardened session bootstrap. Include this BEFORE any output on
 * every page (login.php, dashboard, etc). Call session_start_secure()
 * once at the top of each entry-point file.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = FORCE_SECURE_COOKIES || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_name(SESSION_NAME);

    session_set_cookie_params([
        'lifetime' => 0,                 // expires when browser closes
        'path'     => '/',
        'domain'   => '',                // current domain only
        'secure'   => $secure,           // only sent over HTTPS when available
        'httponly' => true,              // not accessible to JavaScript -> mitigates XSS cookie theft
        'samesite' => 'Strict',          // mitigates CSRF via cross-site requests
    ]);

    // Use a strong, non-predictable session ID algorithm.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');

    session_start();

    // ---- Idle timeout ----
    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > SESSION_LIFETIME_SECONDS) {
        session_force_logout('Session expired due to inactivity.');
    }
    $_SESSION['last_activity'] = time();

    // ---- Absolute timeout (hard cap regardless of activity) ----
    if (isset($_SESSION['started_at']) &&
        (time() - $_SESSION['started_at']) > SESSION_ABSOLUTE_LIFETIME) {
        session_force_logout('Session expired, please log in again.');
    }
    if (!isset($_SESSION['started_at'])) {
        $_SESSION['started_at'] = time();
    }

    // ---- Session fingerprint binding (basic hijack mitigation) ----
    $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . app_client_ip());
    if (isset($_SESSION['fingerprint'])) {
        if (!hash_equals($_SESSION['fingerprint'], $fingerprint)) {
            session_force_logout('Session validation failed.');
        }
    } else {
        $_SESSION['fingerprint'] = $fingerprint;
    }

    // ---- Periodic session ID regeneration (mitigates fixation) ----
    if (!isset($_SESSION['regenerated_at'])) {
        $_SESSION['regenerated_at'] = time();
    } elseif (time() - $_SESSION['regenerated_at'] > 300) { // every 5 min
        session_regenerate_id(true);
        $_SESSION['regenerated_at'] = time();
    }
}

function session_force_logout(string $reason): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    $_SESSION['flash_error'] = $reason;
    header('Location: login.php');
    exit;
}

/**
 * Best-effort client IP resolution. Proxy headers are spoofable,
 * so this is used only for logging/lockout, never for security
 * decisions that must be trustworthy.
 */
function app_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
