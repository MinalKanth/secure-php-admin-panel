<?php
/**
 * config/config.php
 *
 * Central configuration. In a real deployment, move this file
 * OUTSIDE the public web root and require() it from here, or at
 * minimum rely on the .htaccess included in this package to block
 * direct HTTP access to /config.
 *
 * Never commit real credentials to version control.
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// Error handling: never display raw errors to visitors in prod.
// ---------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');       // do not leak errors to browser
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// ---------------------------------------------------------------
// Database credentials - EDIT THESE for your environment
// ---------------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'admin_panel');
define('DB_USER', 'admin_panel_user');   // use a dedicated low-privilege DB user, not root
define('DB_PASS', 'CHANGE_THIS_PASSWORD');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------
// App / security settings
// ---------------------------------------------------------------
define('APP_ENV', 'production');         // 'production' or 'development'
define('APP_NAME', 'Admin Panel');

// Force HTTPS for cookies if the site is served over TLS.
// Set to true once you have a valid SSL certificate installed.
define('FORCE_SECURE_COOKIES', false);

// Login throttling
define('MAX_LOGIN_ATTEMPTS', 5);     // attempts before temporary lock
define('LOCKOUT_MINUTES', 15);       // lockout duration

// Session settings
define('SESSION_NAME', 'admin_sess');
define('SESSION_LIFETIME_SECONDS', 60 * 30); // 30 min idle timeout
define('SESSION_ABSOLUTE_LIFETIME', 60 * 60 * 8); // 8 hr hard cap

// CSRF token lifetime (regenerated on each form render anyway)
define('CSRF_TOKEN_LENGTH', 32);

// Pagination
define('USERS_PER_PAGE', 10);

// Timezone (set explicitly - don't rely on server default)
date_default_timezone_set('UTC');
