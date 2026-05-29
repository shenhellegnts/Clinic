<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$period = $_GET['period'] ?? 'daily';
$date   = $_GET['date']   ?? date('Y-m-d');
$month  = $_GET['month']  ?? date('Y-m');
$year   = (int)($_GET['year'] ?? date('Y'));

function calcIncome($apptRes): float {
    $income = 0.0;
    if (!$apptRes) return $income;
    while ($row = $apptRes->fetch_assoc()) {
        foreach (explode(',', $row['services']) as $sn) {
            $sn = trim($sn);
            if (!$sn) continue;
            $sp = db_row("SELECT price FROM services WHERE name = ? LIMIT 1", 's', [$sn]);
            if ($sp) $income += (float)$sp['price'];
        }
    }
    return $income;
}

function topServicesFromRes($res): array {
    $map = [];
    if ($res) while ($r = $res->fetch_assoc()) {
        foreach (explode(',', $r['services']) as $s) {
            $n = trim($s); if ($n) $map[$n] = ($map[$n] ?? 0) + 1;
        }
    }
    arsort($map);
    return array_slice($map, 0, 8, true);
}

function apptList($res): array {
    $list = [];
    if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;
    return $list;
}

$data = [];

if ($period === 'daily') {
    $d    = $date ?: date('Y-m-d');
    $prev = date('Y-m-d', strtotime($d . ' -1 day'));

    $total    = db_row("SELECT COUNT(*) AS c FROM appointments WHERE DATE(preferred_date)=?", 's', [$d])['c'] ?? 0;
    $done     = db_row("SELECT COUNT(*) AS c FROM appointments WHERE DATE(preferred_date)=? AND status='done'", 's', [$d])['c'] ?? 0;
    $approved = db_row("SELECT COUNT(*) AS c FROM appointments WHERE DATE(preferred_date)=? AND status IN ('confirmed','in_progress')", 's', [$d])['c'] ?? 0;
    $rejected = db_row("SELECT COUNT(*) AS c FROM appointments WHERE DATE(preferred_date)=? AND status IN ('rejected','cancelled')", 's', [$d])['c'] ?? 0;
    $skipped  = db_row("SELECT COUNT(*) AS c FROM appointments WHERE DATE(preferred_date)=? AND status IN ('skipped','disregarded')", 's', [$d])['c'] ?? 0;
    $uniquePat= db_row("SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE DATE(preferred_date)=?", 's', [$d])['c'] ?? 0;

    $incRes  = db_query("SELECT services FROM appointments WHERE DATE(preferred_date)=? AND status='done'", 's', [$d]);
    $income  = calcIncome($incRes);

    $prevDone = db_row("SELECT COUNT(*) AS c FROM appointments WHERE DATE(preferred_date)=? AND status='done'", 's', [$prev])['c'] ?? 0;
    $pctChange = $total > 0
        ? round((($total - (db_row("SELECT COUNT(*) AS c FROM appointments WHERE DATE(preferred_date)=?", 's', [$prev])['c'] ?? 0)) / max(1, $total)) * 100)
        : 0;

    $prevIncRes = db_query("SELECT services FROM appointments WHERE DATE(preferred_date)=? AND status='done'", 's', [$prev]);
    $prevIncome = calcIncome($prevIncRes);
    $incomePct  = $prevIncome > 0 ? round((($income - $prevIncome) / $prevIncome) * 100) : ($income > 0 ? 100 : 0);

    $topServices = topServicesFromRes(db_query("SELECT services FROM appointments WHERE DATE(preferred_date)=?", 's', [$d]));

    $appointments = apptList(db_query(
        "SELECT id,patient_name,services,preferred_date,status,queue_number,priority_flags
         FROM appointments WHERE DATE(preferred_date)=? ORDER BY CAST(queue_number AS UNSIGNED) ASC, created_at ASC",
        's', [$d]
    ));

    $data = compact('total','done','approved','rejected','skipped','uniquePat','income','incomePct','pctChange','topServices','appointments','d');

} elseif ($period === 'monthly') {
    $m  = $month ?: date('Y-m');
    [$y, $mo] = explode('-', $m);
    $y  = (int)$y; $mo = (int)$mo;
    $prevM  = date('Y-m', strtotime($m . '-01 -1 month'));
    [$py, $pmo] = explode('-', $prevM);
    $py = (int)$py; $pmo = (int)$pmo;

    $total    = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=?", 'ii', [$y,$mo])['c'] ?? 0;
    $done     = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=? AND status='done'", 'ii', [$y,$mo])['c'] ?? 0;
    $approved = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=? AND status IN ('confirmed','in_progress')", 'ii', [$y,$mo])['c'] ?? 0;
    $rejected = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=? AND status IN ('rejected','cancelled')", 'ii', [$y,$mo])['c'] ?? 0;
    $skipped  = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=? AND status IN ('skipped','disregarded')", 'ii', [$y,$mo])['c'] ?? 0;
    $uniquePat= db_row("SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=?", 'ii', [$y,$mo])['c'] ?? 0;

    $prevTotal = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=?", 'ii', [$py,$pmo])['c'] ?? 0;
    $pctChange = $prevTotal > 0 ? round((($total - $prevTotal) / $prevTotal) * 100) : ($total > 0 ? 100 : 0);

    $incRes     = db_query("SELECT services FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=? AND status='done'", 'ii', [$y,$mo]);
    $income     = calcIncome($incRes);
    $prevIncRes = db_query("SELECT services FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=? AND status='done'", 'ii', [$py,$pmo]);
    $prevIncome = calcIncome($prevIncRes);
    $incomePct  = $prevIncome > 0 ? round((($income - $prevIncome) / $prevIncome) * 100) : ($income > 0 ? 100 : 0);

    $topServices = topServicesFromRes(db_query("SELECT services FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=?", 'ii', [$y,$mo]));

    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $mo, $y);
    $dailyVolume = [];
    for ($i = 1; $i <= $daysInMonth; $i++) {
        $cnt = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=? AND DAY(preferred_date)=?", 'iii', [$y,$mo,$i])['c'] ?? 0;
        $dailyVolume[] = ['day' => $i, 'count' => (int)$cnt];
    }

    $appointments = apptList(db_query(
        "SELECT id,patient_name,services,preferred_date,status,queue_number,priority_flags
         FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=?
         ORDER BY preferred_date ASC, CAST(queue_number AS UNSIGNED) ASC",
        'ii', [$y,$mo]
    ));

    $data = compact('total','done','approved','rejected','skipped','uniquePat','income','incomePct','pctChange','topServices','dailyVolume','appointments','m');

} elseif ($period === 'yearly') {
    $yr     = $year ?: (int)date('Y');
    $prevYr = $yr - 1;

    $total    = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=?", 'i', [$yr])['c'] ?? 0;
    $done     = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND status='done'", 'i', [$yr])['c'] ?? 0;
    $approved = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND status IN ('confirmed','in_progress')", 'i', [$yr])['c'] ?? 0;
    $rejected = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND status IN ('rejected','cancelled')", 'i', [$yr])['c'] ?? 0;
    $skipped  = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND status IN ('skipped','disregarded')", 'i', [$yr])['c'] ?? 0;
    $uniquePat= db_row("SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE YEAR(preferred_date)=?", 'i', [$yr])['c'] ?? 0;

    $prevTotal = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=?", 'i', [$prevYr])['c'] ?? 0;
    $pctChange = $prevTotal > 0 ? round((($total - $prevTotal) / $prevTotal) * 100) : ($total > 0 ? 100 : 0);

    $incRes     = db_query("SELECT services FROM appointments WHERE YEAR(preferred_date)=? AND status='done'", 'i', [$yr]);
    $income     = calcIncome($incRes);
    $prevIncRes = db_query("SELECT services FROM appointments WHERE YEAR(preferred_date)=? AND status='done'", 'i', [$prevYr]);
    $prevIncome = calcIncome($prevIncRes);
    $incomePct  = $prevIncome > 0 ? round((($income - $prevIncome) / $prevIncome) * 100) : ($income > 0 ? 100 : 0);

    $topServices = topServicesFromRes(db_query("SELECT services FROM appointments WHERE YEAR(preferred_date)=?", 'i', [$yr]));

    $monthlyVolume = [];
    for ($i = 1; $i <= 12; $i++) {
        $cnt = db_row("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(preferred_date)=? AND MONTH(preferred_date)=?", 'ii', [$yr,$i])['c'] ?? 0;
        $monthlyVolume[] = ['month' => date('M', mktime(0,0,0,$i,1)), 'count' => (int)$cnt];
    }

    $appointments = apptList(db_query(
        "SELECT id,patient_name,services,preferred_date,status,queue_number,priority_flags
         FROM appointments WHERE YEAR(preferred_date)=?
         ORDER BY preferred_date ASC, CAST(queue_number AS UNSIGNED) ASC",
        'i', [$yr]
    ));

    $data = compact('total','done','approved','rejected','skipped','uniquePat','income','incomePct','pctChange','topServices','monthlyVolume','appointments','yr');
}

echo json_encode(['success' => true, 'period' => $period, 'data' => $data]);
