<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = requireRole('employee', true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['error' => 'POST required'], 405);
csrfCheck(true);
$st = db()->prepare("UPDATE break_records SET end_utc = UTC_TIMESTAMP() WHERE employee_id = ? AND end_utc IS NULL");
$st->execute([$u['employee_id']]);
if ($st->rowCount() === 0) jsonOut(['error' => 'You do not have an active break.'], 409);
jsonOut(['ok' => true]);
