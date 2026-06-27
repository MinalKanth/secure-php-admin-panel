<?php
/**
 * login.php
 *
 * Admin login. There is intentionally NO registration flow anywhere
 * in this app - admins are created only via the CLI script
 * create_admin.php, run directly on the server by a trusted operator.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

// Already logged in? go straight to dashboard.
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();

    // Lightweight session-based throttle: max 10 attempts / 5 min,
    // independent of which username was tried. Slows down brute force
    // before we even touch the database.
    if (!rate_limit_check('login', 10, 300)) {
        $error = 'Too many login attempts. Please wait a few minutes and try again.';
    } else {
        $username = clean_input((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
        } elseif (mb_strlen($username) > 50 || mb_strlen($password) > 200) {
            $error = 'Invalid credentials.';
        } else {
            $admin = find_admin_by_username($username);

            // Always run password_verify, even on a fake hash, so that
            // response timing does not reveal whether the username exists.
            $dummyHash = '$2y$12$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWX';
            $hashToCheck = $admin['password_hash'] ?? $dummyHash;
            $passwordOk = password_verify($password, $hashToCheck);

            if (!$admin) {
                $error = 'Invalid username or password.';
            } elseif (is_account_locked($admin)) {
                $error = 'This account is temporarily locked due to repeated failed login attempts. Please try again later.';
            } elseif (!$passwordOk) {
                register_failed_attempt((int) $admin['id']);
                log_activity((int) $admin['id'], 'login_failed', 'Bad password');
                $error = 'Invalid username or password.';
            } else {
                // Successful login.
                session_regenerate_id(true);
                $_SESSION['admin_id']       = (int) $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name']     = $admin['full_name'] ?? $admin['username'];
                $_SESSION['fingerprint']    = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . app_client_ip());
                $_SESSION['started_at']     = time();

                reset_failed_attempts((int) $admin['id']);
                record_successful_login((int) $admin['id']);
                log_activity((int) $admin['id'], 'login_success');

                // Optional: rehash if PHP's default cost factor has increased.
                if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    get_db()->prepare('UPDATE admins SET password_hash = :h WHERE id = :id')
                        ->execute([':h' => $newHash, ':id' => $admin['id']]);
                }

                header('Location: dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
  <div class="auth-card">
    <h1><?= e(APP_NAME) ?></h1>
    <p class="auth-subtitle">Administrator sign in</p>

    <?php if ($error !== ''): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" autocomplete="off" novalidate>
      <?= csrf_field() ?>

      <label for="username">Username</label>
      <input type="text" id="username" name="username" maxlength="50"
             autocomplete="username" required autofocus
             value="<?= e($_POST['username'] ?? '') ?>">

      <label for="password">Password</label>
      <input type="password" id="password" name="password" maxlength="200"
             autocomplete="current-password" required>

      <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>
  </div>
</body>
</html>
