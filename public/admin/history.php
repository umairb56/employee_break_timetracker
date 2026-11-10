<?php
require __DIR__ . '/../../includes/bootstrap.php';
requireRole('admin');
$st = db()->prepare("SELECT e.id, e.employee_code, e.full_name, u.is_active, p.daily_allowance_minutes AS am
                     FROM employees e JOIN users u ON u.id = e.user_id JOIN break_policies p ON p.id = e.policy_id WHERE e.id = ?");
$st->execute([(int)($_GET['id'] ?? 0)]);
$emp = $st->fetch();
if (!$emp) { http_response_code(404); exit('Employee not found.'); }
$to = validDate((string)($_GET['to'] ?? '')) ? $_GET['to'] : localDay()['date'];
$from = validDate((string)($_GET['from'] ?? '')) ? $_GET['from'] : (new DateTimeImmutable($to))->modify('-29 days')->format('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];
$rows = historyRows((int)$emp['id'], $from, $to, $emp['am'] * 60);
$pageTitle = 'Break history';
include __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
  <div><h1 class="h4 mb-0"><?= e($emp['full_name']) ?> <small class="text-muted">(<?= e($emp['employee_code']) ?>)</small></h1>
  <div class="text-muted small">Allowance: <?= (int)$emp['am'] ?> min/day · Times shown in <?= e(setting('company_timezone')) ?></div></div>
  <form class="d-flex gap-2 align-items-end" method="get">
    <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
    <div><label class="form-label small mb-0">From</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
    <div><label class="form-label small mb-0">To</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
    <button class="btn btn-sm btn-primary">Show</button>
  </form>
</div>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-sm table-hover mb-0 align-middle">
  <thead><tr><th>Date</th><th>Start</th><th>End</th><th>Duration</th><th>Day total</th><th>Flags</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['forgotten'] ? 'table-danger' : ($r['active'] ? 'table-primary' : '') ?>">
      <td><?= e($r['date']) ?></td><td><?= e($r['start']) ?></td><td><?= e($r['end'] ?? '—') ?></td>
      <td class="num"><?= dur($r['sec']) ?></td><td class="num"><?= dur($r['day_total']) ?></td>
      <td>
        <?= $r['active'] ? '<span class="badge bg-primary">Active</span> ' : '' ?>
        <?= $r['forgotten'] ? '<span class="badge bg-dark">Possibly forgotten</span> ' : '' ?>
        <?= $r['day_over'] ? '<span class="badge bg-danger">Over allowance</span> ' : '' ?>
        <?= $r['corrected'] ? '<span class="badge bg-secondary">Corrected</span>' : '' ?>
      </td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/record_edit.php?id=' . $r['id'])) ?>">Correct</a></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="text-muted">No breaks in this period.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
