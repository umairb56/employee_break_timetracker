<?php
require __DIR__ . '/../../includes/bootstrap.php';
requireRole('admin');
$st = db()->prepare("SELECT b.*, e.full_name, e.employee_code FROM break_records b JOIN employees e ON e.id = b.employee_id WHERE b.id = ?");
$st->execute([(int)($_GET['id'] ?? 0)]);
$rec = $st->fetch();
if (!$rec) { http_response_code(404); exit('Record not found.'); }

$fmt = 'Y-m-d\TH:i:s';
$val = fn(?string $utc) => $utc ? (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(tz())->format($fmt) : '';
$in = ['start' => $val($rec['start_utc']), 'end' => $val($rec['end_utc']), 'reason' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    foreach ($in as $k => $_) $in[$k] = trim((string)($_POST[$k] ?? ''));
    $utc = new DateTimeZone('UTC');
    $conv = function (string $s) use ($utc) {
        $d = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', strlen($s) === 16 ? $s . ':00' : $s, tz());
        return $d ? $d->setTimezone($utc) : null;
    };
    $s = $conv($in['start']);
    $e = $in['end'] !== '' ? $conv($in['end']) : null;
    $now = new DateTimeImmutable('now', $utc);
    if (mb_strlen($in['reason']) < 5 || mb_strlen($in['reason']) > 500) $errors[] = 'A reason (5–500 characters) is mandatory.';
    if (!$s) $errors[] = 'Enter a valid start time.';
    if ($in['end'] !== '' && !$e) $errors[] = 'Enter a valid end time or leave it blank to keep the break open.';
    if ($s && $s > $now) $errors[] = 'Start time cannot be in the future.';
    if ($s && $e && $e <= $s) $errors[] = 'End time must be after the start time.';
    if ($e && $e > $now) $errors[] = 'End time cannot be in the future.';
    if (!$errors) {
        $sUtc = $s->format('Y-m-d H:i:s');
        $eUtc = $e ? $e->format('Y-m-d H:i:s') : null;
        $o = db()->prepare("SELECT COUNT(*) FROM break_records WHERE employee_id = ? AND id <> ? AND start_utc < COALESCE(?, '9999-12-31 00:00:00') AND COALESCE(end_utc, '9999-12-31 00:00:00') > ?");
        $o->execute([$rec['employee_id'], $rec['id'], $eUtc, $sUtc]);
        if ($o->fetchColumn() > 0) $errors[] = 'These times overlap another break of the same employee.';
    }
    if (!$errors) {
        try {
            db()->prepare("UPDATE break_records SET start_utc = ?, end_utc = ?, was_corrected = 1 WHERE id = ?")->execute([$sUtc, $eUtc, $rec['id']]);
            audit('break.correct', 'break_record', (int)$rec['id'],
                  ['employee' => $rec['employee_code'], 'old_start_utc' => $rec['start_utc'], 'old_end_utc' => $rec['end_utc'],
                   'new_start_utc' => $sUtc, 'new_end_utc' => $eUtc], $in['reason']);
            flash('success', 'Break record corrected and logged.');
            redirect('admin/history.php?id=' . $rec['employee_id']);
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') $errors[] = 'This employee already has another open break; give this one an end time.'; else throw $ex;
        }
    }
}
$pageTitle = 'Correct break record';
include __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-1">Correct break record</h1>
<div class="text-muted mb-3"><?= e($rec['full_name']) ?> (<?= e($rec['employee_code']) ?>) · Times in <?= e(setting('company_timezone')) ?></div>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="card shadow-sm" style="max-width:600px"><div class="card-body">
  <?= csrfField() ?>
  <div class="mb-3"><label class="form-label">Start</label><input type="datetime-local" step="1" name="start" class="form-control" value="<?= e($in['start']) ?>" required></div>
  <div class="mb-3"><label class="form-label">End <small class="text-muted">(blank = break still open)</small></label><input type="datetime-local" step="1" name="end" class="form-control" value="<?= e($in['end']) ?>"></div>
  <div class="mb-3"><label class="form-label">Reason for correction (required)</label><textarea name="reason" class="form-control" rows="3" maxlength="500" required><?= e($in['reason']) ?></textarea></div>
  <button class="btn btn-primary">Save correction</button>
  <a class="btn btn-link" href="<?= e(url('admin/history.php?id=' . $rec['employee_id'])) ?>">Cancel</a>
</div></form>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
