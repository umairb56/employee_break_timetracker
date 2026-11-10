<?php
require __DIR__ . '/../../includes/bootstrap.php';
requireRole('admin');
$zones = DateTimeZone::listIdentifiers();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $zone = (string)($_POST['company_timezone'] ?? '');
    $warn = filter_var($_POST['warn_percent'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    $long = filter_var($_POST['forgotten_minutes'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 1440]]);
    if (!in_array($zone, $zones, true) || $warn === false || $long === false) {
        flash('danger', 'Please enter valid values.');
    } else {
        $old = ['company_timezone' => setting('company_timezone'), 'warn_percent' => setting('warn_percent'), 'forgotten_minutes' => setting('forgotten_minutes')];
        $new = ['company_timezone' => $zone, 'warn_percent' => (string)$warn, 'forgotten_minutes' => (string)$long];
        $up = db()->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($new as $k => $v) $up->execute([$k, $v]);
        audit('settings.update', 'settings', null, ['old' => $old, 'new' => $new]);
        flash('success', 'Settings saved.');
    }
    redirect('admin/settings.php');
}
$pageTitle = 'Settings';
include __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3">Settings</h1>
<form method="post" class="card shadow-sm" style="max-width:600px"><div class="card-body">
  <?= csrfField() ?>
  <div class="mb-3"><label class="form-label">Company timezone</label>
    <select name="company_timezone" class="form-select">
      <?php foreach ($zones as $z): ?><option <?= $z === setting('company_timezone') ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?>
    </select>
    <div class="form-text">Used for display, daily totals and reports. Timestamps are always stored in UTC.</div></div>
  <div class="mb-3"><label class="form-label">“Near limit” warning threshold (% of allowance used)</label>
    <input type="number" name="warn_percent" min="1" max="100" class="form-control" value="<?= e(setting('warn_percent')) ?>" required></div>
  <div class="mb-3"><label class="form-label">Flag an open break as “long / check” after (minutes)</label>
    <input type="number" name="forgotten_minutes" min="5" max="1440" class="form-control" value="<?= e(setting('forgotten_minutes')) ?>" required>
    <div class="form-text">The break is never closed automatically — it stays active until the employee ends it or an admin corrects it.</div></div>
  <button class="btn btn-primary">Save settings</button>
</div></form>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
