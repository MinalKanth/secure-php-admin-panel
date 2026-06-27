<?php
/**
 * create_admin.php
 *
 * Run this from the COMMAND LINE ONLY to create the first (or an
 * additional) admin account:
 *
 *     php create_admin.php
 *
 * This script refuses to run via a web browser. There is no HTTP-
 * accessible registration page anywhere in this app, by design -
 * admin accounts are provisioned by whoever has server/SSH access.
 *
 * After creating the account, delete or move this file off the
 * server (or at least out of the web-accessible directory) if you
 * are worried about it lingering - though the .htaccess included
 * blocks browser access to *.php CLI scripts of this kind anyway
 * via deny rules you can extend.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/config/database.php';

function prompt(string $label, bool $hidden = false): string
{
    echo $label;
    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) {
        system('stty -echo');
        $value = trim((string) fgets(STDIN));
        system('stty echo');
        echo "\n";
    } else {
        $value = trim((string) fgets(STDIN));
    }
    return $value;
}

echo "=== Create Admin Account ===\n";

$username = prompt('Username: ');
$email    = prompt('Email: ');
$fullName = prompt('Full name: ');
$password = prompt('Password (min 12 chars, upper/lower/number): ', true);
$confirm  = prompt('Confirm password: ', true);

$errors = [];
if ($username === '' || mb_strlen($username) > 50 || !preg_match('/^[A-Za-z0-9_.]+$/', $username)) {
    $errors[] = 'Username must be 1-50 chars, letters/numbers/underscore/dot only.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
}
if (mb_strlen($password) < 12) {
    $errors[] = 'Password must be at least 12 characters.';
}
if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $errors[] = 'Password must include upper case, lower case, and a number.';
}
if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
}

if ($errors) {
    echo "\nErrors:\n";
    foreach ($errors as $e) {
        echo " - {$e}\n";
    }
    exit(1);
}

$pdo = get_db();

$check = $pdo->prepare('SELECT id FROM admins WHERE username = :u OR email = :e');
$check->execute([':u' => $username, ':e' => $email]);
if ($check->fetch()) {
    echo "\nError: an admin with that username or email already exists.\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO admins (username, email, password_hash, full_name) VALUES (:u, :e, :h, :n)'
);
$stmt->execute([':u' => $username, ':e' => $email, ':h' => $hash, ':n' => $fullName]);

echo "\nAdmin account created successfully. You can now log in at login.php.\n";
