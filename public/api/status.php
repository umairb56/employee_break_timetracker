<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = requireRole('employee', true);
$eid = (int)$u['employee_id'];
$t = employeeToday($eid, (int)$u['daily_allowance_minutes']);
$from = (new DateTimeImmutable('now', tz()))->modify('-29 days')->format('Y-m-d');
$to = localDay()['date'];
$t['history'] = historyRows($eid, $from, $to, $t['allowance']);
$t['forgotten_after'] = (int)setting('forgotten_minutes') * 60;
$t['warn_percent'] = (int)setting('warn_percent');
jsonOut($t);
