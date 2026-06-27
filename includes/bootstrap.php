<?php
/**
 * includes/bootstrap.php
 *
 * Single entry point included at the very top of every page.
 * Sets security headers, starts the hardened session, and loads
 * the other includes. Order matters: headers before any output.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

// ---------------------------------------------------------------
// Security headers (defense in depth against XSS, clickjacking,
// MIME sniffing, etc.)
// ---------------------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header(
    "Content-Security-Policy: default-src 'self'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "script-src 'self'; " .
    "img-src 'self' data:; " .
    "frame-ancestors 'none'; " .
    "base-uri 'self'; " .
    "form-action 'self';"
);
if (FORCE_SECURE_COOKIES) {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains');
}
// Older clients / extra clickjacking layer
header('X-XSS-Protection: 1; mode=block');

session_start_secure();
