<?php
require __DIR__ . '/../../includes/bootstrap.php';
requireRole('admin');
$type = ($_GET['type'] ?? '') === 'monthly' ? 'monthly' : 'daily';
[$from, $to, $label] = reportPeriod($type, $_GET);
$rows = buildReport($from, $to);
// Prevent spreadsheet formula injection
$safe = fn($v) => is_string($v) && $v !== '' && strpos("=+-@\t\r", $v[0]) !== false ? "'" . $v : $v;
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="break-report-' . $type . '-' . $label . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens UTF-8 correctly
$w = fn(array $r) => fputcsv($out, array_map($safe, $r), ',', '"', '');
$w(['Report', $type, 'Period', "$from to $to", 'Timezone', setting('company_timezone')]);
$w(['Employee ID', 'Name', 'Breaks', 'Total break time (h:mm:ss)', 'Total seconds', 'Daily allowance (min)', 'Days exceeded', 'Total excess (h:mm:ss)', 'Open breaks']);
foreach ($rows as $r) $w([$r['code'], $r['name'], $r['breaks'], dur($r['total']), $r['total'], intdiv($r['allow'], 60), $r['days_over'], dur($r['excess']), $r['open']]);
fclose($out);
