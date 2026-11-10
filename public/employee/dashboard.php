<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = requireRole('employee');
$pageTitle = 'My Breaks';
$scripts = ['employee.js'];
include __DIR__ . '/../../includes/header.php';
?>
<div id="app" data-api="<?= e(url('api/')) ?>">
  <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h4 mb-0"><?= e($user['full_name']) ?></h1>
      <div class="text-muted">Employee ID: <?= e($user['employee_code']) ?></div>
    </div>
    <div class="text-muted"><?= e((new DateTimeImmutable('now', tz()))->format('l, j F Y')) ?></div>
  </div>
  <div id="msg"></div>
  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card h-100 shadow-sm text-center"><div class="card-body p-4">
        <div class="text-muted mb-1">Break status</div>
        <span id="badge" class="badge fs-6 bg-secondary mb-3">Loading…</span>
        <div id="timer" class="timer">0:00:00</div>
        <div class="text-muted small mb-3" id="timerLabel">Current break timer</div>
        <button id="btnStart" class="btn btn-success btn-lg px-4 me-2" disabled>START BREAK</button>
        <button id="btnEnd" class="btn btn-danger btn-lg px-4" disabled>END BREAK</button>
      </div></div>
    </div>
    <div class="col-lg-6">
      <div class="card h-100 shadow-sm"><div class="card-body">
        <div class="row text-center g-3">
          <div class="col-6"><div class="text-muted small">Daily allowance</div><div class="fs-4" id="allow">–</div></div>
          <div class="col-6"><div class="text-muted small">Breaks today</div><div class="fs-4" id="count">–</div></div>
          <div class="col-6"><div class="text-muted small">Used today</div><div class="fs-4" id="used">–</div></div>
          <div class="col-6"><div class="text-muted small" id="remLabel">Remaining</div><div class="fs-4" id="remain">–</div></div>
        </div>
        <div class="progress mt-4" style="height:14px"><div id="bar" class="progress-bar" style="width:0"></div></div>
        <div id="longWarn" class="alert alert-dark mt-3 mb-0 d-none">Your break has been running for a long time. Please end it if you are back.</div>
      </div></div>
    </div>
  </div>
  <div class="card shadow-sm">
    <div class="card-header">My break history (last 30 days)</div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th>Date</th><th>Start</th><th>End</th><th>Duration</th><th>Day total</th><th></th></tr></thead>
        <tbody id="hist"><tr><td colspan="6" class="text-muted">Loading…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
