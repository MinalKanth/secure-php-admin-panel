<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$pdo = get_db();
$adminId = (int) $_SESSION['admin_id'];

$stmt = $pdo->prepare('SELECT id, username, email, full_name FROM admins WHERE id = :id');
$stmt->execute([':id' => $adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    // Should not happen, but fail safely.
    session_force_logout('Account not found.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = clean_input((string) ($_POST['full_name'] ?? ''));
        $email    = clean_input((string) ($_POST['email'] ?? ''));

        if ($fullName === '' || mb_strlen($fullName) > 150) {
            $errors[] = 'Full name is required and must be under 150 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (!$errors) {
            // Ensure email isn't already used by a different admin.
            $check = $pdo->prepare('SELECT id FROM admins WHERE email = :e AND id != :id');
            $check->execute([':e' => $email, ':id' => $adminId]);
            if ($check->fetch()) {
                $errors[] = 'That email address is already in use.';
            }
        }

        if (!$errors) {
            $pdo->prepare('UPDATE admins SET full_name = :n, email = :e WHERE id = :id')
                ->execute([':n' => $fullName, ':e' => $email, ':id' => $adminId]);
            log_activity($adminId, 'profile_updated');
            $_SESSION['admin_name'] = $fullName;
            $_SESSION['flash_success'] = 'Profile updated successfully.';
            header('Location: profile.php');
            exit;
        }
    } elseif ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $stmt2 = $pdo->prepare('SELECT password_hash FROM admins WHERE id = :id');
        $stmt2->execute([':id' => $adminId]);
        $currentHash = (string) $stmt2->fetchColumn();

        if (!password_verify($current, $currentHash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (mb_strlen($new) < 12) {
            $errors[] = 'New password must be at least 12 characters long.';
        } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) {
            $errors[] = 'New password must include upper case, lower case, and a number.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        } elseif (password_verify($new, $currentHash)) {
            $errors[] = 'New password must be different from the current password.';
        }

        if (!$errors) {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE admins SET password_hash = :h WHERE id = :id')
                ->execute([':h' => $newHash, ':id' => $adminId]);
            log_activity($adminId, 'password_changed');
            $_SESSION['flash_success'] = 'Password changed successfully.';
            header('Location: profile.php');
            exit;
        }
    }
}

$pageTitle = 'My Profile';
require __DIR__ . '/includes/header.php';
?>
<h1>My Profile</h1>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <h2>Account Details</h2>
  <form method="post" action="profile.php" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_profile">

    <label for="username">Username</label>
    <input type="text" id="username" value="<?= e($admin['username']) ?>" disabled>
    <p class="field-hint">Username cannot be changed.</p>

    <label for="full_name">Full Name</label>
    <input type="text" id="full_name" name="full_name" maxlength="150" required
           value="<?= e($admin['full_name'] ?? '') ?>">

    <label for="email">Email</label>
    <input type="email" id="email" name="email" maxlength="150" required
           value="<?= e($admin['email']) ?>">

    <button type="submit" class="btn btn-primary">Save Changes</button>
  </form>
</div>

<div class="card">
  <h2>Change Password</h2>
  <form method="post" action="profile.php" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">

    <label for="current_password">Current Password</label>
    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required maxlength="200">

    <label for="new_password">New Password</label>
    <input type="password" id="new_password" name="new_password" autocomplete="new-password" required minlength="12" maxlength="200">
    <p class="field-hint">At least 12 characters, with upper case, lower case, and a number.</p>

    <label for="confirm_password">Confirm New Password</label>
    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required minlength="12" maxlength="200">

    <button type="submit" class="btn btn-primary">Change Password</button>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
