<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$pdo = get_db();

$editId = (int) ($_GET['id'] ?? 0);
$isEdit = $editId > 0;

$user = ['full_name' => '', 'email' => '', 'phone' => '', 'status' => 'active'];
$errors = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT id, full_name, email, phone, status FROM users WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $found = $stmt->fetch();
    if (!$found) {
        $_SESSION['flash_error'] = 'User not found.';
        header('Location: users.php');
        exit;
    }
    $user = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();

    $fullName = clean_input((string) ($_POST['full_name'] ?? ''));
    $email    = clean_input((string) ($_POST['email'] ?? ''));
    $phone    = clean_input((string) ($_POST['phone'] ?? ''));
    $status   = (string) ($_POST['status'] ?? 'active');
    $postedId = (int) ($_POST['id'] ?? 0);

    // Re-derive edit mode from the posted ID, not just the query string.
    $isEdit = $postedId > 0;

    if ($fullName === '' || mb_strlen($fullName) > 150) {
        $errors[] = 'Full name is required and must be under 150 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors[] = 'A valid email address is required.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{0,30}$/', $phone)) {
        $errors[] = 'Phone number contains invalid characters.';
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Invalid status selected.';
    }

    if (!$errors) {
        // Uniqueness check on email, excluding self when editing.
        if ($isEdit) {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :e AND id != :id');
            $check->execute([':e' => $email, ':id' => $postedId]);
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :e');
            $check->execute([':e' => $email]);
        }
        if ($check->fetch()) {
            $errors[] = 'A user with that email already exists.';
        }
    }

    if (!$errors) {
        if ($isEdit) {
            $pdo->prepare(
                'UPDATE users SET full_name = :n, email = :e, phone = :p, status = :s WHERE id = :id'
            )->execute([
                ':n' => $fullName, ':e' => $email, ':p' => $phone ?: null,
                ':s' => $status, ':id' => $postedId,
            ]);
            log_activity((int) $_SESSION['admin_id'], 'user_updated', "user_id={$postedId}");
            $_SESSION['flash_success'] = 'User updated successfully.';
        } else {
            $pdo->prepare(
                'INSERT INTO users (full_name, email, phone, status, created_by) VALUES (:n, :e, :p, :s, :c)'
            )->execute([
                ':n' => $fullName, ':e' => $email, ':p' => $phone ?: null,
                ':s' => $status, ':c' => (int) $_SESSION['admin_id'],
            ]);
            log_activity((int) $_SESSION['admin_id'], 'user_created', "email={$email}");
            $_SESSION['flash_success'] = 'User created successfully.';
        }
        header('Location: users.php');
        exit;
    }

    // Re-populate form with submitted values on validation failure.
    $user = [
        'id' => $postedId, 'full_name' => $fullName, 'email' => $email,
        'phone' => $phone, 'status' => $status,
    ];
}

$pageTitle = $isEdit ? 'Edit User' : 'Add User';
require __DIR__ . '/includes/header.php';
?>
<h1><?= $isEdit ? 'Edit User' : 'Add New User' ?></h1>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <form method="post" action="user_form.php<?= $isEdit ? '?id=' . (int) $user['id'] : '' ?>" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($user['id'] ?? 0) ?>">

    <label for="full_name">Full Name</label>
    <input type="text" id="full_name" name="full_name" maxlength="150" required
           value="<?= e($user['full_name']) ?>">

    <label for="email">Email</label>
    <input type="email" id="email" name="email" maxlength="150" required
           value="<?= e($user['email']) ?>">

    <label for="phone">Phone (optional)</label>
    <input type="text" id="phone" name="phone" maxlength="30"
           value="<?= e($user['phone'] ?? '') ?>">

    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>

    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create User' ?></button>
    <a href="users.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
