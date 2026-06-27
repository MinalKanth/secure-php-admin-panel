<?php
/**
 * config/database.php
 *
 * Returns a single shared PDO instance configured with secure defaults:
 * - Real prepared statements (no emulation) -> immune to SQL injection
 *   as long as you always bind parameters, which every query.php
 *   function in this app does.
 * - Exceptions on error instead of silent failure.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Never expose DB connection details to the client.
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('A server error occurred. Please try again later.');
    }

    return $pdo;
}
