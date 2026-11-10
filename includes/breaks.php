<?php
// Time helpers, break calculations and reports. Database times are UTC; display uses the company timezone.

function tz(): DateTimeZone {
    try { return new DateTimeZone(setting('company_timezone') ?: 'UTC'); } catch (Exception $e) { return new DateTimeZone('UTC'); }
}
function ts(string $utc): int { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->getTimestamp(); }
function toLocal(?string $utc, string $fmt = 'Y-m-d H:i:s'): string {
    if (!$utc) return '—';
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(tz())->format($fmt);
}
function dur(int $s): string { $s = max(0, $s); return sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60); }
function validDate(string $d): bool { $x = DateTime::createFromFormat('Y-m-d', $d); return $x && $x->format('Y-m-d') === $d; }

// UTC start/end of a calendar day in the company timezone.
function localDay(?string $date = null): array {
    $d = ($date && validDate($date)) ? new DateTimeImmutable($date, tz()) : new DateTimeImmutable('today', tz());
    $d = $d->setTime(0, 0);
    $utc = new DateTimeZone('UTC');
    return ['date' => $d->format('Y-m-d'),
            'start' => $d->setTimezone($utc)->format('Y-m-d H:i:s'),
            'end' => $d->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')];
}

function recordSeconds(array $r, int $now): int {
    return max(0, ($r['end_utc'] ? ts($r['end_utc']) : $now) - ts($r['start_utc']));
}
function rangeBreaks(string $startUtc, string $endUtc, ?int $empId = null): array {
    $sql = "SELECT * FROM break_records WHERE start_utc >= ? AND start_utc < ?";
    $p = [$startUtc, $endUtc];
    if ($empId !== null) { $sql .= " AND employee_id = ?"; $p[] = $empId; }
    $st = db()->prepare($sql . " ORDER BY start_utc");
    $st->execute($p);
    return $st->fetchAll();
}
function activeBreakFor(int $empId): ?array {
    $st = db()->prepare("SELECT * FROM break_records WHERE employee_id = ? AND end_utc IS NULL");
    $st->execute([$empId]);
    return $st->fetch() ?: null;
}

// Today's figures for one employee (completed time excludes the running break; the browser adds it live).
function employeeToday(int $empId, int $allowMin): array {
    $day = localDay();
    $now = time();
    $rows = rangeBreaks($day['start'], $day['end'], $empId);
    $completed = 0;
    foreach ($rows as $r) if ($r['end_utc']) $completed += recordSeconds($r, $now);
    $a = activeBreakFor($empId);
    return [
        'now' => $now,
        'count' => count($rows),
        'completed' => $completed,
        'allowance' => $allowMin * 60,
        'active_start' => $a ? ts($a['start_utc']) : null,
        'active_counts' => $a ? ts($a['start_utc']) >= ts($day['start']) : false,
    ];
}

// Break list for a date range with per-day totals and flags.
function historyRows(int $empId, string $from, string $to, int $allowSec): array {
    $rows = rangeBreaks(localDay($from)['start'], localDay($to)['end'], $empId);
    $now = time();
    $forgotten = (int)setting('forgotten_minutes') * 60;
    $tot = $out = [];
    foreach ($rows as $b) {
        $d = toLocal($b['start_utc'], 'Y-m-d');
        $sec = recordSeconds($b, $now);
        $tot[$d] = ($tot[$d] ?? 0) + $sec;
        $out[] = ['id' => (int)$b['id'], 'date' => $d, 'start' => toLocal($b['start_utc'], 'H:i:s'),
                  'end' => $b['end_utc'] ? toLocal($b['end_utc'], 'H:i:s') : null, 'sec' => $sec,
                  'active' => !$b['end_utc'], 'forgotten' => !$b['end_utc'] && $sec > $forgotten,
                  'corrected' => (bool)$b['was_corrected']];
    }
    foreach ($out as &$o) { $o['day_total'] = $tot[$o['date']]; $o['day_over'] = $tot[$o['date']] > $allowSec; }
    return array_reverse($out);
}

function reportPeriod(string $type, array $in): array {
    if ($type === 'monthly') {
        $m = (string)($in['month'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $m) || !validDate($m . '-01')) $m = (new DateTimeImmutable('now', tz()))->format('Y-m');
        $to = (new DateTimeImmutable($m . '-01'))->modify('last day of this month')->format('Y-m-d');
        return [$m . '-01', $to, $m, 'monthly'];
    }
    $d = (string)($in['date'] ?? '');
    if (!validDate($d)) $d = localDay()['date'];
    return [$d, $d, $d, 'daily'];
}

// One row per employee for the period; excess is calculated per calendar day.
function buildReport(string $from, string $to): array {
    $emps = db()->query("SELECT e.id, e.employee_code, e.full_name, u.is_active, p.daily_allowance_minutes AS am
                         FROM employees e JOIN users u ON u.id = e.user_id JOIN break_policies p ON p.id = e.policy_id
                         ORDER BY e.full_name")->fetchAll();
    $now = time();
    $per = [];
    foreach (rangeBreaks(localDay($from)['start'], localDay($to)['end']) as $b) {
        $d = toLocal($b['start_utc'], 'Y-m-d');
        $per[$b['employee_id']]['days'][$d] = ($per[$b['employee_id']]['days'][$d] ?? 0) + recordSeconds($b, $now);
        $per[$b['employee_id']]['n'] = ($per[$b['employee_id']]['n'] ?? 0) + 1;
        if (!$b['end_utc']) $per[$b['employee_id']]['open'] = ($per[$b['employee_id']]['open'] ?? 0) + 1;
    }
    $out = [];
    foreach ($emps as $e) {
        $p = $per[$e['id']] ?? ['days' => [], 'n' => 0];
        if (!$e['is_active'] && !$p['n']) continue;
        $allow = $e['am'] * 60; $over = 0; $excess = 0;
        foreach ($p['days'] as $s) if ($s > $allow) { $over++; $excess += $s - $allow; }
        $out[] = ['code' => $e['employee_code'], 'name' => $e['full_name'], 'breaks' => $p['n'],
                  'total' => array_sum($p['days']), 'allow' => $allow, 'days_over' => $over,
                  'excess' => $excess, 'open' => $p['open'] ?? 0];
    }
    return $out;
}
