<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$pdo = get_db();
$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();

$recentLogs = $pdo->query(
    'SELECT al.action, al.details, al.ip_address, al.created_at, a.username
     FROM activity_log al
     LEFT JOIN admins a ON a.id = al.admin_id
     ORDER BY al.created_at DESC
     LIMIT 10'
)->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<h1>Dashboard</h1>
<p>Welcome back, <strong><?= e($_SESSION['admin_name'] ?? $_SESSION['admin_username']) ?></strong>.</p>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-number"><?= $totalUsers ?></div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="stat-card">
    <div class="stat-number"><?= $activeUsers ?></div>
    <div class="stat-label">Active Users</div>
  </div>
</div>

<h2>Recent Activity</h2>
<table class="data-table">
  <thead>
    <tr><th>Admin</th><th>Action</th><th>Details</th><th>IP</th><th>When</th></tr>
  </thead>
  <tbody>
    <?php if (!$recentLogs): ?>
      <tr><td colspan="5">No activity yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($recentLogs as $log): ?>
      <tr>
        <td><?= e($log['username'] ?? 'system') ?></td>
        <td><?= e($log['action']) ?></td>
        <td><?= e($log['details'] ?? '') ?></td>
        <td><?= e($log['ip_address'] ?? '') ?></td>
        <td><?= e($log['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php require __DIR__ . '/includes/footer.php'; ?>
