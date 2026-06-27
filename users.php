<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$pdo = get_db();

// ---- Handle delete (POST only, CSRF-checked) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_require_valid();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        log_activity((int) $_SESSION['admin_id'], 'user_deleted', "user_id={$id}");
        $_SESSION['flash_success'] = 'User deleted successfully.';
    }
    header('Location: users.php');
    exit;
}

// ---- Search ----
$search = clean_input((string) ($_GET['q'] ?? ''));
$search = mb_substr($search, 0, 150);

// ---- Pagination ----
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = USERS_PER_PAGE;
$offset = ($page - 1) * $perPage;

if ($search !== '') {
    $likeTerm = '%' . $search . '%';
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE full_name LIKE :s OR email LIKE :s');
    $countStmt->execute([':s' => $likeTerm]);
    $totalRows = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, phone, status, created_at
         FROM users
         WHERE full_name LIKE :s OR email LIKE :s
         ORDER BY id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':s', $likeTerm, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
} else {
    $totalRows = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, phone, status, created_at
         FROM users
         ORDER BY id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
}

$users = $stmt->fetchAll();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$pageTitle = 'Manage Users';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <h1>Manage Users</h1>
  <a href="user_form.php" class="btn btn-primary">+ Add New User</a>
</div>

<form method="get" action="users.php" class="search-form">
  <input type="text" name="q" placeholder="Search by name or email"
         value="<?= e($search) ?>" maxlength="150">
  <button type="submit" class="btn">Search</button>
  <?php if ($search !== ''): ?>
    <a href="users.php" class="btn btn-secondary">Clear</a>
  <?php endif; ?>
</form>

<table class="data-table">
  <thead>
    <tr>
      <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Created</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$users): ?>
      <tr><td colspan="7">No users found.</td></tr>
    <?php endif; ?>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int) $u['id'] ?></td>
        <td><?= e($u['full_name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['phone'] ?? '') ?></td>
        <td><span class="badge badge-<?= $u['status'] === 'active' ? 'success' : 'muted' ?>"><?= e($u['status']) ?></span></td>
        <td><?= e($u['created_at']) ?></td>
        <td class="actions">
          <a href="user_form.php?id=<?= (int) $u['id'] ?>" class="btn btn-small">Edit</a>
          <form method="post" action="users.php" class="inline-form"
                onsubmit="return confirm('Delete this user? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <button type="submit" class="btn btn-small btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="users.php?page=<?= $p ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
       class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
