<?php $_hu = currentUser(); $_hAdmin = $_hu && $_hu['role'] === 'admin'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? '') ?> – <?= e(APP_NAME) ?></title>
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light">
<?php if ($_hu): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= e(url('index.php')) ?>"><?= e(APP_NAME) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
      <?php if ($_hAdmin):
        foreach (['dashboard' => 'Dashboard', 'employees' => 'Employees', 'policies' => 'Policies', 'reports' => 'Reports', 'audit' => 'Audit Log', 'settings' => 'Settings'] as $_hf => $_hl): ?>
        <li class="nav-item"><a class="nav-link" href="<?= e(url("admin/$_hf.php")) ?>"><?= e($_hl) ?></a></li>
      <?php endforeach; else: ?>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('employee/dashboard.php')) ?>">My Breaks</a></li>
      <?php endif; ?>
      </ul>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="d-flex align-items-center gap-3">
        <span class="navbar-text small"><?= e($_hu['full_name'] ?? $_hu['username']) ?></span>
        <?= csrfField() ?>
        <button class="btn btn-outline-light btn-sm">Log out</button>
      </form>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="container pb-5">
<?php foreach ($_SESSION['flash'] ?? [] as [$_ft, $_fm]): ?>
  <div class="alert alert-<?= e($_ft) ?> alert-dismissible fade show"><?= e($_fm) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; unset($_SESSION['flash']); ?>