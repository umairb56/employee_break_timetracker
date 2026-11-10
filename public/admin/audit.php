<?php
require __DIR__ . '/../../includes/bootstrap.php';
requireRole('admin');
$per = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = (int)db()->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$st = db()->prepare("SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute();
$logs = $st->fetchAll();
$pages = max(1, (int)ceil($total / $per));
$pageTitle = 'Audit log';
include __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3">Audit log <small class="text-muted">(<?= $total ?> entries, times in <?= e(setting('company_timezone')) ?>)</small></h1>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-sm table-hover mb-0 align-top">
  <thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Target</th><th>Reason</th><th>Details</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($logs as $l): ?>
    <tr><td class="num"><?= e(toLocal($l['created_at'])) ?></td><td><?= e($l['username'] ?? '—') ?></td><td><code><?= e($l['action']) ?></code></td>
      <td><?= e($l['entity_type']) ?> #<?= e($l['entity_id']) ?></td><td><?= e($l['reason'] ?? '') ?></td>
      <td><small class="text-break"><?= e($l['details']) ?></small></td><td><?= e($l['ip_address']) ?></td></tr>
  <?php endforeach; if (!$logs): ?><tr><td colspan="7" class="text-muted">No entries.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<nav class="mt-3"><ul class="pagination pagination-sm">
  <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?>
    <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
  <?php endfor; ?>
</ul></nav>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
