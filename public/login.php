<?php
require __DIR__ . '/../includes/bootstrap.php';
if (currentUser()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $username = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $st = db()->prepare("SELECT * FROM users WHERE username = ?");
    $st->execute([$username]);
    $u = $st->fetch();
    // Always run password_verify so response time doesn't reveal whether the username exists
    $hash = $u['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
    $passOk = password_verify($pass, $hash);
    $locked = $u && $u['locked_until'] && ts($u['locked_until']) > time();
    if ($u && $passOk && $u['is_active'] && !$locked) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        db()->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_utc = UTC_TIMESTAMP() WHERE id = ?")->execute([$u['id']]);
        redirect('index.php');
    }
    if ($u && !$locked) {
        $n = (int)$u['failed_attempts'] + 1;
        db()->prepare("UPDATE users SET failed_attempts = ?, locked_until = " . ($n >= 5 ? "DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)" : "NULL") . " WHERE id = ?")->execute([$n, $u['id']]);
    }
    $error = $locked ? 'Too many failed attempts. Please try again in 15 minutes.' : 'Invalid username or password.';
}
$pageTitle = 'Login';
include __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center mt-5">
  <div class="col-md-5 col-lg-4">
    <div class="card shadow-sm"><div class="card-body p-4">
      <h1 class="h4 text-center mb-4"><?= e(APP_NAME) ?></h1>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <?= csrfField() ?>
        <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" required autofocus maxlength="50"></div>
        <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
        <button class="btn btn-primary w-100">Log in</button>
      </form>
    </div></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
