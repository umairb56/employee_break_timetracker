<?php
require __DIR__ . '/../../includes/bootstrap.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $act = $_POST['action'] ?? '';
    $pid = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $min = filter_var($_POST['minutes'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1440]]);
    try {
        if ($act === 'delete') {
            $c = db()->prepare("SELECT COUNT(*) FROM employees WHERE policy_id = ?"); $c->execute([$pid]);
            if ($c->fetchColumn() > 0) flash('danger', 'This policy is assigned to employees and cannot be deleted.');
            else { db()->prepare("DELETE FROM break_policies WHERE id = ?")->execute([$pid]); audit('policy.delete', 'policy', $pid); flash('success', 'Policy deleted.'); }
        } elseif ($name === '' || mb_strlen($name) > 100 || $min === false) {
            flash('danger', 'Enter a name and an allowance between 0 and 1440 minutes.');
        } elseif ($act === 'update') {
            db()->prepare("UPDATE break_policies SET name = ?, daily_allowance_minutes = ? WHERE id = ?")->execute([$name, $min, $pid]);
            audit('policy.update', 'policy', $pid, ['name' => $name, 'minutes' => $min]); flash('success', 'Policy updated.');
        } else {
            db()->prepare("INSERT INTO break_policies (name, daily_allowance_minutes) VALUES (?, ?)")->execute([$name, $min]);
            audit('policy.create', 'policy', (int)db()->lastInsertId(), ['name' => $name, 'minutes' => $min]); flash('success', 'Policy created.');
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') flash('danger', 'A policy with that name already exists.'); else throw $ex;
    }
    redirect('admin/policies.php');
}
$list = db()->query("SELECT p.*, (SELECT COUNT(*) FROM employees e WHERE e.policy_id = p.id) AS n FROM break_policies p ORDER BY name")->fetchAll();
$pageTitle = 'Break policies';
include __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3">Break policies</h1>
<div class="alert alert-info py-2 small">Changing a policy's allowance applies to all its employees, including when viewing past days in reports.</div>
<div class="card shadow-sm mb-4"><div class="table-responsive">
<table class="table align-middle mb-0">
  <thead><tr><th>Name</th><th style="width:170px">Daily allowance (min)</th><th>Employees</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($list as $p): ?>
    <tr>
      <td><input form="pf<?= (int)$p['id'] ?>" name="name" class="form-control form-control-sm" value="<?= e($p['name']) ?>" maxlength="100" required></td>
      <td><input form="pf<?= (int)$p['id'] ?>" name="minutes" type="number" min="0" max="1440" class="form-control form-control-sm" value="<?= (int)$p['daily_allowance_minutes'] ?>" required></td>
      <td><?= (int)$p['n'] ?></td>
      <td class="text-end">
        <form id="pf<?= (int)$p['id'] ?>" method="post" class="d-inline"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"></form>
        <button form="pf<?= (int)$p['id'] ?>" name="action" value="update" class="btn btn-sm btn-outline-primary">Save</button>
        <button form="pf<?= (int)$p['id'] ?>" name="action" value="delete" class="btn btn-sm btn-outline-danger" <?= $p['n'] ? 'disabled' : '' ?> formnovalidate onclick="return confirm('Delete this policy?')">Delete</button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<form method="post" class="card shadow-sm" style="max-width:600px"><div class="card-body">
  <h2 class="h6">Add policy</h2><?= csrfField() ?><input type="hidden" name="action" value="create">
  <div class="row g-2">
    <div class="col-7"><input name="name" class="form-control" placeholder="Policy name" maxlength="100" required></div>
    <div class="col-3"><input name="minutes" type="number" min="0" max="1440" class="form-control" placeholder="Minutes" required></div>
    <div class="col-2"><button class="btn btn-primary w-100">Add</button></div>
  </div>
</div></form>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
