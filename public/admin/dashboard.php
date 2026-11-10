<?php
require __DIR__ . '/../../includes/bootstrap.php';
requireRole('admin');
$pageTitle = 'Admin Dashboard';
$scripts = ['admin.js'];
include __DIR__ . '/../../includes/header.php';
?>
<div id="app" data-api="<?= e(url('api/admin_status.php')) ?>" data-history="<?= e(url('admin/history.php')) ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Dashboard — <?= e((new DateTimeImmutable('now', tz()))->format('l, j F Y')) ?></h1>
    <small class="text-muted">Auto-refreshes every 15 s · <span id="updated">…</span></small>
  </div>
  <div id="msg"></div>
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm"><div class="card-body"><div class="text-muted small">Total employees</div><div class="display-6" id="cTotal">–</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm border-primary"><div class="card-body"><div class="text-muted small">Currently on break</div><div class="display-6 text-primary" id="cOn">–</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm border-danger"><div class="card-body"><div class="text-muted small">Exceeding allowance</div><div class="display-6 text-danger" id="cOver">–</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm"><div class="card-body"><div class="text-muted small">Total break time today</div><div class="display-6" id="cTotalTime">–</div></div></div></div>
  </div>
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between"><span>Employee status</span><span id="flagged" class="badge bg-dark d-none"></span></div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Current break</th><th>Total today</th><th>Allowance</th><th>Remaining</th><th>Breaks</th><th></th></tr></thead>
        <tbody id="rows"><tr><td colspan="9" class="text-muted">Loading…</td></tr></tbody>
      </table>
    </div>
  </div>
  <div class="small text-muted mt-2">
    <span class="badge bg-success">Normal</span> <span class="badge bg-warning text-dark">Near limit</span>
    <span class="badge bg-primary">On break</span> <span class="badge bg-danger">Exceeded</span>
    <span class="badge bg-dark">Long break – check</span>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
