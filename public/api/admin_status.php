<?php
require __DIR__ . '/../../includes/bootstrap.php';
requireRole('admin', true);
$day = localDay();
$now = time();
$emps = db()->query("SELECT e.id, e.employee_code, e.full_name, p.daily_allowance_minutes AS am
                     FROM employees e JOIN users u ON u.id = e.user_id JOIN break_policies p ON p.id = e.policy_id
                     WHERE u.is_active = 1 AND u.role = 'employee' ORDER BY e.full_name")->fetchAll();
$done = $count = $active = [];
foreach (rangeBreaks($day['start'], $day['end']) as $b) {
    $count[$b['employee_id']] = ($count[$b['employee_id']] ?? 0) + 1;
    if ($b['end_utc']) $done[$b['employee_id']] = ($done[$b['employee_id']] ?? 0) + recordSeconds($b, $now);
}
foreach (db()->query("SELECT * FROM break_records WHERE end_utc IS NULL")->fetchAll() as $b) $active[$b['employee_id']] = $b;
$dayStart = ts($day['start']);
$rows = [];
foreach ($emps as $e) {
    $a = $active[$e['id']] ?? null;
    $rows[] = ['id' => (int)$e['id'], 'code' => $e['employee_code'], 'name' => $e['full_name'],
               'count' => $count[$e['id']] ?? 0, 'completed' => $done[$e['id']] ?? 0, 'allowance' => $e['am'] * 60,
               'active_start' => $a ? ts($a['start_utc']) : null,
               'active_counts' => $a ? ts($a['start_utc']) >= $dayStart : false];
}
jsonOut(['now' => $now, 'employees' => $rows, 'warn_percent' => (int)setting('warn_percent'),
         'forgotten_after' => (int)setting('forgotten_minutes') * 60]);
