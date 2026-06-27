<?php
/**
 * includes/auth.php
 *
 * Authentication helpers: login check, rate limiting / account lockout,
 * password verification, and the require_admin() guard used at the
 * top of every protected page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/**
 * Call at the very top of any page that requires a logged-in admin.
 * Redirects to login.php otherwise.
 */
function require_admin(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Look up an admin by username (case-sensitive on purpose - avoids
 * collation-based ambiguity attacks). Returns the row or null.
 */
function find_admin_by_username(string $username): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function is_account_locked(array $admin): bool
{
    if (empty($admin['locked_until'])) {
        return false;
    }
    return strtotime($admin['locked_until']) > time();
}

function register_failed_attempt(int $adminId): void
{
    $pdo = get_db();
    $pdo->prepare('UPDATE admins SET failed_attempts = failed_attempts + 1 WHERE id = :id')
        ->execute([':id' => $adminId]);

    $stmt = $pdo->prepare('SELECT failed_attempts FROM admins WHERE id = :id');
    $stmt->execute([':id' => $adminId]);
    $attempts = (int) $stmt->fetchColumn();

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $lockUntil = (new DateTime())->modify('+' . LOCKOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s');
        $pdo->prepare('UPDATE admins SET locked_until = :l WHERE id = :id')
            ->execute([':l' => $lockUntil, ':id' => $adminId]);
    }
}

function reset_failed_attempts(int $adminId): void
{
    $pdo = get_db();
    $pdo->prepare('UPDATE admins SET failed_attempts = 0, locked_until = NULL WHERE id = :id')
        ->execute([':id' => $adminId]);
}

function record_successful_login(int $adminId): void
{
    $pdo = get_db();
    $pdo->prepare('UPDATE admins SET last_login = NOW(), last_login_ip = :ip WHERE id = :id')
        ->execute([':ip' => app_client_ip(), ':id' => $adminId]);
}

function log_activity(?int $adminId, string $action, string $details = ''): void
{
    $pdo = get_db();
    $pdo->prepare(
        'INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (:a, :ac, :d, :ip)'
    )->execute([
        ':a'  => $adminId,
        ':ac' => $action,
        ':d'  => $details,
        ':ip' => app_client_ip(),
    ]);
}

/**
 * Generic per-key rate limiter using the session, as a lightweight
 * extra layer on top of the DB-backed account lockout above.
 * Limits repeated POSTs (e.g. brute force) even before a valid
 * username is known.
 */
function rate_limit_check(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $now = time();
    $bucket = $_SESSION['rate_limit'][$key] ?? ['count' => 0, 'start' => $now];

    if ($now - $bucket['start'] > $windowSeconds) {
        $bucket = ['count' => 0, 'start' => $now];
    }

    $bucket['count']++;
    $_SESSION['rate_limit'][$key] = $bucket;

    return $bucket['count'] <= $maxAttempts;
}

/** Trim + strip null bytes from raw input. Does NOT replace output escaping. */
function clean_input(string $value): string
{
    $value = str_replace("\0", '', $value);
    return trim($value);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
