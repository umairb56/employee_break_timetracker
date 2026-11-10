<?php
require __DIR__ . '/../../includes/bootstrap.php';
requireRole('admin');
$type = ($_GET['type'] ?? '') === 'monthly' ? 'monthly' : 'daily';
[$from, $to, $label] = reportPeriod($type, $_GET);
$rows = buildReport($from, $to);
$exportUrl = url('admin/export.php?type=' . $type . '&' . ($type === 'monthly' ? 'month' : 'date') . '=' . $label);
$pageTitle = 'Reports';
include __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3">Reports</h1>
<form method="get" class="row g-2 align-items-end mb-3">
  <div class="col-auto"><label class="form-label small mb-0">Report</label>
    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="daily" <?= $type === 'daily' ? 'selected' : '' ?>>Daily</option><option value="monthly" <?= $type === 'monthly' ? 'selected' : '' ?>>Monthly</option>
    </select></div>
  <div class="col-auto"><label class="form-label small mb-0"><?= $type === 'monthly' ? 'Month' : 'Date' ?></label>
    <?php if ($type === 'monthly'): ?><input type="month" name="month" value="<?= e($label) ?>" class="form-control form-control-sm">
    <?php else: ?><input type="date" name="date" value="<?= e($label) ?>" class="form-control form-control-sm"><?php endif; ?></div>
  <div class="col-auto"><button class="btn btn-sm btn-primary">Show</button>
    <a class="btn btn-sm btn-outline-success" href="<?= e($exportUrl) ?>">Export CSV</a></div>
</form>
<div class="text-muted small mb-2">Period: <?= e($from) ?> to <?= e($to) ?> (<?= e(setting('company_timezone')) ?>). “Days exceeded” and “Excess” are calculated per day against each employee's daily allowance.</div>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
  <thead><tr><th>ID</th><th>Name</th><th>Breaks</th><th>Total break time</th><th>Daily allowance</th><th>Days exceeded</th><th>Total excess</th><th>Open breaks</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['open'] ? 'table-warning' : ($r['days_over'] ? 'table-danger' : '') ?>">
      <td><?= e($r['code']) ?></td><td><?= e($r['name']) ?></td><td><?= (int)$r['breaks'] ?></td>
      <td class="num"><?= dur($r['total']) ?></td><td class="num"><?= dur($r['allow']) ?></td>
      <td><?= (int)$r['days_over'] ?></td><td class="num"><?= $r['excess'] ? dur($r['excess']) : '—' ?></td><td><?= (int)$r['open'] ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="text-muted">No data.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
