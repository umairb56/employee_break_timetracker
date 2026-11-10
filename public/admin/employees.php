<?php
require __DIR__ . '/../../includes/bootstrap.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $st = db()->prepare("SELECT e.id, e.employee_code, u.id AS uid, u.is_active FROM employees e JOIN users u ON u.id = e.user_id WHERE e.id = ?");
    $st->execute([(int)($_POST['id'] ?? 0)]);
    if ($emp = $st->fetch()) {
        if ($emp['is_active'] && activeBreakFor((int)$emp['id'])) {
            flash('danger', 'This employee has an open break. End or correct it before deactivating the account.');
        } else {
            $new = $emp['is_active'] ? 0 : 1;
            db()->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$new, $emp['uid']]);
            audit($new ? 'employee.activate' : 'employee.deactivate', 'employee', (int)$emp['id'], ['employee_code' => $emp['employee_code']]);
            flash('success', 'Account ' . ($new ? 'activated.' : 'deactivated.'));
        }
    }
    redirect('admin/employees.php');
}
$list = db()->query("SELECT e.id, e.employee_code, e.full_name, u.username, u.is_active, p.name AS policy, p.daily_allowance_minutes AS am,
                     (SELECT COUNT(*) FROM break_records b WHERE b.employee_id = e.id AND b.end_utc IS NULL) AS open_breaks
                     FROM employees e JOIN users u ON u.id = e.user_id JOIN break_policies p ON p.id = e.policy_id
                     ORDER BY u.is_active DESC, e.full_name")->fetchAll();
$pageTitle = 'Employees';
include __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Employees</h1>
  <a class="btn btn-primary" href="<?= e(url('admin/employee_form.php')) ?>">+ Add employee</a>
</div>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
  <thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Policy</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($list as $r): ?>
    <tr class="<?= $r['is_active'] ? '' : 'text-muted' ?>">
      <td><?= e($r['employee_code']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['username']) ?></td>
      <td><?= e($r['policy']) ?> (<?= (int)$r['am'] ?> min)</td>
      <td><?= $r['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Deactivated</span>' ?>
          <?= $r['open_breaks'] ? '<span class="badge bg-primary">On break</span>' : '' ?></td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/history.php?id=' . $r['id'])) ?>">History</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/employee_form.php?id=' . $r['id'])) ?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure?')">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm <?= $r['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"><?= $r['is_active'] ? 'Deactivate' : 'Activate' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; if (!$list): ?><tr><td colspan="6" class="text-muted">No employees yet.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
