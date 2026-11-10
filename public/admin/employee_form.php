<?php
require __DIR__ . '/../../includes/bootstrap.php';
requireRole('admin');
$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id) {
    $st = db()->prepare("SELECT e.*, u.username FROM employees e JOIN users u ON u.id = e.user_id WHERE e.id = ?");
    $st->execute([$id]);
    $edit = $st->fetch();
    if (!$edit) { http_response_code(404); exit('Employee not found.'); }
}
$policies = db()->query("SELECT id, name, daily_allowance_minutes FROM break_policies ORDER BY name")->fetchAll();
$f = ['employee_code' => $edit['employee_code'] ?? '', 'full_name' => $edit['full_name'] ?? '',
      'username' => $edit['username'] ?? '', 'policy_id' => $edit['policy_id'] ?? ($policies[0]['id'] ?? 0)];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    $pw = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9._-]{1,30}$/', $f['employee_code'])) $errors[] = 'Employee ID: 1–30 letters, numbers, dot, dash or underscore.';
    if ($f['full_name'] === '' || mb_strlen($f['full_name']) > 100) $errors[] = 'Full name is required (max 100 characters).';
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $f['username'])) $errors[] = 'Username: 3–50 letters, numbers, dot, dash or underscore.';
    if (!in_array((int)$f['policy_id'], array_column($policies, 'id'))) $errors[] = 'Choose a valid break policy.';
    if (($edit === null || $pw !== '') && strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            if ($edit) {
                $changes = [];
                foreach (['employee_code', 'full_name', 'policy_id'] as $k) if ((string)$edit[$k] !== $f[$k]) $changes[$k] = [$edit[$k], $f[$k]];
                if ($edit['username'] !== $f['username']) $changes['username'] = [$edit['username'], $f['username']];
                $pdo->prepare("UPDATE employees SET employee_code=?, full_name=?, policy_id=? WHERE id=?")->execute([$f['employee_code'], $f['full_name'], (int)$f['policy_id'], $id]);
                $pdo->prepare("UPDATE users SET username=? WHERE id=?")->execute([$f['username'], $edit['user_id']]);
                if ($pw !== '') {
                    $pdo->prepare("UPDATE users SET password_hash=?, failed_attempts=0, locked_until=NULL WHERE id=?")->execute([password_hash($pw, PASSWORD_DEFAULT), $edit['user_id']]);
                    $changes['password'] = 'reset';
                }
                audit('employee.update', 'employee', $id, $changes);
                $msg = 'Employee updated.';
            } else {
                $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'employee')")->execute([$f['username'], password_hash($pw, PASSWORD_DEFAULT)]);
                $uid = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO employees (user_id, employee_code, full_name, policy_id) VALUES (?,?,?,?)")->execute([$uid, $f['employee_code'], $f['full_name'], (int)$f['policy_id']]);
                $newId = (int)$pdo->lastInsertId();
                audit('employee.create', 'employee', $newId, ['employee_code' => $f['employee_code'], 'username' => $f['username'], 'policy_id' => (int)$f['policy_id']]);
                $msg = 'Employee created.';
            }
            $pdo->commit();
            flash('success', $msg);
            redirect('admin/employees.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($ex->getCode() === '23000') $errors[] = 'That Employee ID or username is already in use.'; else throw $ex;
        }
    }
}
$pageTitle = $edit ? 'Edit employee' : 'Add employee';
include __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e($pageTitle) ?></h1>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="card shadow-sm" style="max-width:600px" autocomplete="off"><div class="card-body">
  <?= csrfField() ?>
  <div class="mb-3"><label class="form-label">Employee ID</label><input name="employee_code" class="form-control" value="<?= e($f['employee_code']) ?>" required maxlength="30"></div>
  <div class="mb-3"><label class="form-label">Full name</label><input name="full_name" class="form-control" value="<?= e($f['full_name']) ?>" required maxlength="100"></div>
  <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" value="<?= e($f['username']) ?>" required maxlength="50"></div>
  <div class="mb-3"><label class="form-label"><?= $edit ? 'New password (leave blank to keep current)' : 'Password' ?></label>
    <input type="password" name="password" class="form-control" minlength="8" <?= $edit ? '' : 'required' ?> autocomplete="new-password"></div>
  <div class="mb-3"><label class="form-label">Break policy</label>
    <select name="policy_id" class="form-select">
      <?php foreach ($policies as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$f['policy_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> — <?= (int)$p['daily_allowance_minutes'] ?> min/day</option><?php endforeach; ?>
    </select></div>
  <button class="btn btn-primary">Save</button>
  <a class="btn btn-link" href="<?= e(url('admin/employees.php')) ?>">Cancel</a>
</div></form>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
