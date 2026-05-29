<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php'); exit;
}

/* ── Active section — preserved across reloads via ?s= param ── */
$validSections  = ['dashboard','queue','appointments','patients','sms','services','analytics'];
$activeSection  = $_GET['s'] ?? 'dashboard';
if (!in_array($activeSection, $validSections, true)) $activeSection = 'dashboard';

/* ── Settings ──────────────────────────────────────────── */
$settings = [];
$sr = db_query('SELECT setting_key, setting_value FROM settings');
if ($sr) while ($r = $sr->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];

$clinicName    = $settings['clinic_name']            ?? 'M.V. Masangkay Clinic';
$clinicSub     = $settings['clinic_subtitle']        ?? 'X-Ray & Laboratory Clinic';
$smsTplCalled  = $settings['sms_template_called']    ?? 'Queue [Queue #] — You are now being called. Please proceed to the clinic.';
$smsTplApprove = $settings['sms_template_approved']  ?? 'Your appointment has been approved. Queue: [Queue #].';
$smsTplReject  = $settings['sms_template_rejected']  ?? 'Your appointment request has been declined.';
$adminName     = $_SESSION['admin_name'] ?? 'Clinic Admin';
$adminInitial  = strtoupper(substr($adminName, 0, 1)) ?: 'A';

/* ── Dates ─────────────────────────────────────────────── */
$today = date('Y-m-d');

/* Appointments tab — any date (past OR future) */
$apptDate = $_GET['date'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $apptDate)) $apptDate = $today;
$apptDateLabel = (new DateTime($apptDate))->format('F j, Y');
$apptIsToday   = ($apptDate === $today);

/* ── Auto-disregard skipped patients after 5 PM ─────────── */
if (intval(date('H')) >= 17) {
    db_query("UPDATE appointments SET status='disregarded' WHERE status='skipped' AND DATE(preferred_date)=?", 's', [$today]);
}

/* ── DASHBOARD STATS — always TODAY (no date picker) ────── */
function dayStat(string $where, array $params = []): int {
    global $today;
    $q = "SELECT COUNT(*) AS c FROM appointments WHERE DATE(preferred_date)=?" . ($where ? " AND $where" : '');
    $types = 's' . str_repeat('s', count($params));
    $r = db_row($q, $types, array_merge([$today], $params));
    return $r['c'] ?? 0;
}

$stats = [
    'total'     => dayStat(''),
    'pending'   => dayStat("status='pending'"),
    'approved'  => dayStat("status IN ('confirmed','in_progress')"),
    'rejected'  => dayStat("status='rejected'"),
    'completed' => dayStat("status='done'"),
];

/* ── QUEUE DATA (today only) ───────────────────────────── */
$nowServing = db_row("SELECT * FROM appointments WHERE status='in_progress' AND DATE(created_at)=? LIMIT 1", 's', [$today]);

$queueWaiting = [];
$wr = db_query("SELECT * FROM appointments WHERE status='confirmed' AND DATE(created_at)=? ORDER BY CASE WHEN priority_flags IS NOT NULL AND priority_flags!='' THEN 0 ELSE 1 END, preferred_date ASC, created_at ASC", 's', [$today]);
if ($wr) while ($r = $wr->fetch_assoc()) $queueWaiting[] = $r;

$queueSkipped = [];
$skr = db_query("SELECT * FROM appointments WHERE status='skipped' AND DATE(created_at)=? ORDER BY created_at ASC", 's', [$today]);
if ($skr) while ($r = $skr->fetch_assoc()) $queueSkipped[] = $r;

$queueDone = [];
$dr = db_query("SELECT * FROM appointments WHERE status='done' AND DATE(created_at)=? ORDER BY created_at DESC LIMIT 20", 's', [$today]);
if ($dr) while ($r = $dr->fetch_assoc()) $queueDone[] = $r;

$queueRejected = [];
$rjr = db_query("SELECT * FROM appointments WHERE status='rejected' AND DATE(created_at)=? ORDER BY created_at DESC", 's', [$today]);
if ($rjr) while ($r = $rjr->fetch_assoc()) $queueRejected[] = $r;

$queueBadgeCount = count($queueWaiting) + ($nowServing ? 1 : 0);

/* ── APPOINTMENTS filtered by preferred_date (appointment date) ── */
$allAppts = [];
/* Sort: queue# numerically ascending (NULLs/pending at bottom), then by submission time */
$ar = db_query(
    "SELECT a.*, p.dob, p.address AS patient_address, p.sex AS patient_sex, p.company AS patient_company
     FROM appointments a LEFT JOIN patients p ON a.patient_id=p.id
     WHERE DATE(a.preferred_date)=?
     ORDER BY
       CASE WHEN a.queue_number IS NULL OR a.queue_number='' THEN 1 ELSE 0 END,
       CAST(a.queue_number AS UNSIGNED) ASC,
       a.created_at ASC",
    's', [$apptDate]
);
if ($ar) while ($r = $ar->fetch_assoc()) {
    if ($r['medical_history']) $r['medical_history_data'] = json_decode($r['medical_history'], true);
    else $r['medical_history_data'] = null;
    $allAppts[] = $r;
}


/* ── ALL PATIENTS (for patients tab) ───────────────────── */
$patients = [];
$pr = db_query("SELECT p.*,
    (SELECT COUNT(*) FROM appointments WHERE patient_id=p.id) AS appt_count,
    (SELECT MAX(created_at) FROM appointments WHERE patient_id=p.id) AS last_visit,
    (SELECT queue_number FROM appointments WHERE patient_id=p.id AND queue_number IS NOT NULL ORDER BY created_at DESC LIMIT 1) AS last_queue
    FROM patients p ORDER BY p.name ASC LIMIT 100");
if ($pr) while ($r = $pr->fetch_assoc()) $patients[] = $r;

/* ── SMS LOGS — always TODAY (no date picker) ────────────── */
$smsFailed    = db_row("SELECT COUNT(*) AS c FROM sms_logs WHERE status='failed'  AND DATE(created_at)=?", 's', [$today])['c'] ?? 0;
$smsPending   = db_row("SELECT COUNT(*) AS c FROM sms_logs WHERE status='pending' AND DATE(created_at)=?", 's', [$today])['c'] ?? 0;
$smsSentCount = db_row("SELECT COUNT(*) AS c FROM sms_logs WHERE status='sent'    AND DATE(created_at)=?", 's', [$today])['c'] ?? 0;
$smsLogs = [];
$slr = db_query(
    "SELECT sl.*, COALESCE(a.queue_number,'') AS queue_number
     FROM sms_logs sl
     LEFT JOIN appointments a ON sl.appointment_id = a.id
     WHERE DATE(sl.created_at)=?
     ORDER BY sl.created_at DESC",
    's', [$today]
);
if ($slr) while ($r = $slr->fetch_assoc()) $smsLogs[] = $r;

/* ── SERVICES ────────────────────────────────────────────── */
$serviceCategories = [];
$scr = db_query('SELECT * FROM service_categories ORDER BY sort_order ASC, name ASC');
if ($scr) while ($r = $scr->fetch_assoc()) $serviceCategories[] = $r;

$services = [];
$svr = db_query('SELECT s.*, COALESCE(s.description,"") AS description, c.name AS category_name FROM services s JOIN service_categories c ON s.category_id=c.id ORDER BY c.sort_order ASC, s.name ASC');
if ($svr) while ($r = $svr->fetch_assoc()) $services[] = $r;

$basicServices = array_values(array_filter($services, fn($s) => $s['is_basic']));
$otherServices = array_values(array_filter($services, fn($s) => !$s['is_basic']));

/* ── ANALYTICS (today default) ──────────────────────────── */
$weeklyCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $d = (new DateTime())->modify("-{$i} days")->format('Y-m-d');
    $c = db_row("SELECT COUNT(*) AS c FROM appointments WHERE DATE(created_at)=?", 's', [$d])['c'] ?? 0;
    $weeklyCounts[] = ['date' => $d, 'label' => (new DateTime($d))->format('D'), 'count' => (int)$c];
}
$topServices = [];
$tsRes = db_query("SELECT services FROM appointments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)");
$svcMap = [];
if ($tsRes) while ($r = $tsRes->fetch_assoc()) {
    foreach (explode(',', $r['services']) as $s) { $n=trim($s); if($n) $svcMap[$n]=($svcMap[$n]??0)+1; }
}
arsort($svcMap);
$topServices = array_slice($svcMap, 0, 5, true);

/* ── HELPERS ─────────────────────────────────────────────── */
function fdt($v){ if(!$v) return '—'; return (new DateTime($v))->format('M j, Y · g:i A'); }
function fd($v) { if(!$v) return '—'; return (new DateTime($v))->format('M j, Y'); }
function age($dob){ if(!$dob) return '—'; return (new DateTime($dob))->diff(new DateTime())->y; }

function statusTag(string $s): string {
    return match($s) {
        'pending'     => 'orange',
        'confirmed'   => 'blue',
        'in_progress' => 'cyan',
        'done'        => 'green',
        'rejected'    => 'red',
        'skipped'     => 'yellow',
        'disregarded' => 'gray',
        default       => 'gray',
    };
}
function statusLabel(string $s): string {
    return match($s) {
        'pending'     => 'Pending',
        'confirmed'   => 'Approved',
        'in_progress' => 'In Progress',
        'done'        => 'Completed',
        'rejected'    => 'Rejected',
        'skipped'     => 'Skipped',
        'disregarded' => 'Disregarded',
        default       => ucfirst($s),
    };
}
function priorityTags(?string $flags, string $size=''): string {
    if (!$flags) return '';
    $out=''; foreach(explode(',',$flags) as $f){ $f=trim($f); if(!$f) continue;
        $lbl = match($f){ 'senior'=>'👴 Senior','pregnant'=>'🤰 Pregnant','pwd'=>'♿ PWD',default=>ucfirst($f) };
        $out.='<span class="priority-tag priority-'.$f.($size?' '.$size:'').'">'.$lbl.'</span>';
    } return $out;
}

/* JS data bridge */
$apptJS = [];
foreach ($allAppts as $a) {
    $apptJS[(int)$a['id']] = [
        'id'              => (int)$a['id'],
        'patient_name'    => $a['patient_name'],
        'patient_mobile'  => $a['patient_mobile'],
        'patient_dob'     => $a['dob'] ?? '',
        'patient_sex'     => $a['patient_sex'] ?? '',
        'patient_company' => $a['patient_company'] ?? '',
        'patient_address' => $a['patient_address'] ?? '',
        'services'        => $a['services'],
        'preferred_date'  => $a['preferred_date'],
        'status'          => $a['status'],
        'queue_number'    => $a['queue_number'],
        'priority_flags'  => $a['priority_flags'] ?? '',
        'medical_history' => $a['medical_history_data'],
        'review_notes'    => $a['review_notes'] ?? '',
        'created_at'      => $a['created_at'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($clinicName) ?> — Admin</title>
  <link rel="icon" type="image/jpeg" href="../logo/logo.jpg"/>
  <link rel="stylesheet" href="../css/style.css"/>
  <link rel="stylesheet" href="../css/admin.css"/>
</head>
<body class="admin-glass">

<!-- TOP NAV -->
<nav class="admin-top-nav">
  <div class="admin-nav-brand">
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="admin-nav-logo">
      <img src="../logo/logo.jpg" alt="Logo"/>
    </div>
    <span class="admin-nav-title"><?= htmlspecialchars($clinicName) ?></span>
    <span class="admin-nav-badge">Admin</span>
  </div>
  <div class="admin-nav-right">
    <div class="live-badge"><div class="live-dot"></div> Live</div>
    <div class="admin-nav-user">
      <div class="admin-avatar"><?= htmlspecialchars($adminInitial) ?></div>
      <span><?= htmlspecialchars($adminName) ?></span>
    </div>
  </div>
</nav>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="admin-layout">
<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-clinic-name"><?= htmlspecialchars($clinicName) ?></div>
    <div class="sidebar-clinic-sub"><?= htmlspecialchars($clinicSub) ?></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Operations</div>
    <div class="nav-item <?= $activeSection==='dashboard'?'active':'' ?>" onclick="showSection('dashboard',this)">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      <span>Dashboard</span>
    </div>
    <div class="nav-item <?= $activeSection==='queue'?'active':'' ?>" onclick="showSection('queue',this)">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      <span>Queue Control</span>
      <?php if ($queueBadgeCount > 0): ?><span class="nav-badge"><?= $queueBadgeCount ?></span><?php endif; ?>
    </div>
    <div class="nav-item <?= $activeSection==='appointments'?'active':'' ?>" onclick="showSection('appointments',this)">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      <span>Appointments</span>
      <?php if ($stats['pending'] > 0): ?><span class="nav-badge"><?= $stats['pending'] ?></span><?php endif; ?>
    </div>
    <div class="nav-item <?= $activeSection==='patients'?'active':'' ?>" onclick="showSection('patients',this)">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span>Patients</span>
    </div>
    <div class="nav-section-label">Communication</div>
    <div class="nav-item <?= $activeSection==='sms'?'active':'' ?>" onclick="showSection('sms',this)">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
      <span>SMS Logs</span>
    </div>
    <div class="nav-section-label">Configuration</div>
    <div class="nav-item <?= $activeSection==='services'?'active':'' ?>" onclick="showSection('services',this)">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a10 10 0 100 20A10 10 0 0012 2z"/><path d="M12 8v4l3 3"/></svg>
      <span>Services</span>
    </div>
    <div class="nav-item <?= $activeSection==='analytics'?'active':'' ?>" onclick="showSection('analytics',this)">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Analytics</span>
    </div>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= htmlspecialchars($adminInitial) ?></div>
      <div><div class="user-name"><?= htmlspecialchars($adminName) ?></div><div class="user-role">Administrator</div></div>
    </div>
    <button class="logout-btn" onclick="adminLogout()">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      Sign Out
    </button>
  </div>
</aside>

<!-- MAIN CONTENT -->
<main class="admin-content">


<section class="admin-section <?= $activeSection==='dashboard'?'active':'' ?>" id="section-dashboard">
  <div class="page-header">
    <div>
      <h2>Dashboard</h2>
      <p id="dashboard-date">Today — <?= htmlspecialchars((new DateTime($today))->format('F j, Y')) ?></p>
    </div>
    <div class="header-right">
      <button class="btn btn-gray btn-sm" onclick="reloadSection()">↻ Refresh</button>
    </div>
  </div>

  <div class="stat-cards">
    <div class="stat-card" onclick="goToAppts('all')" title="View all today's appointments">
      <div class="stat-icon blue"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
      <div><div class="stat-val"><?= $stats['total'] ?></div><div class="stat-lbl">Total Today</div></div>
    </div>
    <div class="stat-card" onclick="goToAppts('pending')" title="Review pending appointments">
      <div class="stat-icon orange"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg></div>
      <div><div class="stat-val"><?= $stats['pending'] ?></div><div class="stat-lbl">Pending</div><?php if($stats['pending']>0): ?><div class="stat-trend down">⚠ Needs review</div><?php endif; ?></div>
    </div>
    <div class="stat-card" onclick="goToAppts('confirmed')" title="View approved appointments">
      <div class="stat-icon cyan"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
      <div><div class="stat-val"><?= $stats['approved'] ?></div><div class="stat-lbl">Approved</div></div>
    </div>
    <div class="stat-card" onclick="goToAppts('done')" title="View completed appointments">
      <div class="stat-icon green"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg></div>
      <div><div class="stat-val"><?= $stats['completed'] ?></div><div class="stat-lbl">Completed</div></div>
    </div>
    <div class="stat-card" onclick="goToAppts('rejected')" title="View rejected appointments">
      <div class="stat-icon red"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg></div>
      <div><div class="stat-val"><?= $stats['rejected'] ?></div><div class="stat-lbl">Rejected</div></div>
    </div>
  </div>

  <div class="dashboard-grid">
    <div class="chart-card">
      <div class="chart-title">Weekly Patient Volume</div>
      <div class="chart-sub">Appointments booked per day</div>
      <div class="bar-chart">
        <?php $mx=max(1,...array_column($weeklyCounts,'count')); foreach($weeklyCounts as $wc): ?>
          <div class="bar-item">
            <div class="bar" style="height:<?= max(6, intval(($wc['count']/$mx)*120)) ?>px;opacity:<?= $wc['date']===$today?'1':'.55' ?>;"></div>
            <div class="bar-label"><?= $wc['label'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Appointments Today -->
  <div class="card mt-24">
    <div class="card-title">
      Appointments Today
      <span style="font-weight:400;color:rgba(255,255,255,.35);margin-left:6px;">— <?= $stats['total'] ?> </span>
      <button class="btn btn-sm btn-gray" style="margin-left:auto;" onclick="showSection('appointments',null)">View All →</button>
    </div>
    <table class="data-table">
      <thead><tr><th>Queue #</th><th>Patient</th><th>Services</th><th>Priority</th><th>Status</th><th>View</th></tr></thead>
      <tbody>
        <?php foreach (array_slice($allAppts, 0, 8) as $appt): ?>
        <tr>
          <td><strong style="color:#7ab8cc;"><?= $appt['queue_number'] ? '#'.htmlspecialchars($appt['queue_number']) : '—' ?></strong></td>
          <td><div class="patient-name-cell"><div class="patient-dot"><?= strtoupper(substr($appt['patient_name'],0,1)) ?></div><?= htmlspecialchars($appt['patient_name']) ?></div></td>
          <td style="font-size:12px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($appt['services']) ?></td>
          <td><?= priorityTags($appt['priority_flags']??'') ?></td>
          <td><span class="tag <?= statusTag($appt['status']) ?>"><?= statusLabel($appt['status']) ?></span></td>
          <td>
            <button class="action-btn" onclick="showSection('appointments',null);setTimeout(()=>openApptModal(<?= $appt['id'] ?>),200);" title="Review appointment">▶</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($allAppts)): ?>
          <tr><td colspan="6" style="text-align:center;color:rgba(255,255,255,.32);padding:20px;">No appointments today</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>


<section class="admin-section <?= $activeSection==='queue'?'active':'' ?>" id="section-queue">
  <div class="page-header">
    <div><h2>Queue Control</h2><p>Manage today's live patient queue. SMS sent automatically on call.</p></div>
    <div class="header-right">
      <div class="live-indicator"><div class="live-dot"></div> Auto-SMS On</div>
      <button class="btn btn-gray btn-sm" onclick="window.location.reload()">↻ Refresh</button>
    </div>
  </div>

  <div class="queue-control-layout">
    <div>
      <!-- Now Serving -->
      <div class="now-serving-card">
        <div class="now-serving-label"><?= $nowServing ? '▶ Now Serving' : 'Queue Ready' ?></div>
        <div class="now-serving-num"><?= $nowServing ? '#'.htmlspecialchars($nowServing['queue_number']) : '—' ?></div>
        <?php if ($nowServing && !empty($nowServing['priority_flags'])): ?>
          <div class="priority-tags-row" style="margin-bottom:6px;"><?= priorityTags($nowServing['priority_flags']) ?></div>
        <?php endif; ?>
        <div class="now-serving-name"><?= htmlspecialchars($nowServing['patient_name'] ?? ($queueBadgeCount>0 ? 'Ready to call' : 'No patients in queue')) ?></div>
        <div class="now-serving-service"><?= htmlspecialchars($nowServing['services'] ?? '') ?></div>
        <div class="queue-actions">
          <?php if ($nowServing): ?>
            <button class="btn btn-sm" style="background:#fff;color:#0c1535;font-weight:700;" onclick="callNext()">✓ Mark Done &amp; Call Next</button>
            <button class="btn btn-sm btn-gray" onclick="markDone()">✔ Mark Done</button>
            <button class="btn btn-sm btn-gray" onclick="skipCurrent(<?= $nowServing['id'] ?>)">⏭ Skip</button>
          <?php else: ?>
            <button class="btn btn-sm" style="background:#fff;color:#0c1535;font-weight:700;" onclick="callNext()">▶ Call Next Patient</button>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Waiting Queue ── -->
      <div class="card" style="margin-bottom:16px;">
        <div class="card-title">
          Waiting Queue
          <span style="font-weight:400;color:rgba(255,255,255,.35);margin-left:6px;">— <?= count($queueWaiting) ?> patient<?= count($queueWaiting)!==1?'s':'' ?></span>
        </div>
        <div class="queue-list" id="queue-list">
          <?php if (empty($queueWaiting)): ?>
            <div class="empty-queue">No patients waiting.</div>
          <?php else: ?>
            <?php foreach ($queueWaiting as $qi): ?>
            <div class="queue-list-item" id="qitem-<?= $qi['id'] ?>">
              <div class="queue-num-badge">#<?= htmlspecialchars($qi['queue_number']) ?></div>
              <div class="queue-item-info">
                <div class="queue-item-name"><?= htmlspecialchars($qi['patient_name']) ?> <?= priorityTags($qi['priority_flags']??'','sm') ?></div>
                <div class="queue-item-service"><?= htmlspecialchars($qi['services']) ?></div>
              </div>
              <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                <span class="tag blue">Waiting</span>
                <?php if (!empty($qi['priority_flags'])): ?>
                  <button class="action-btn" style="background:rgba(167,139,250,.15);border-color:rgba(167,139,250,.3);color:#c4b5fd;" onclick="priorityInsert(<?= $qi['id'] ?>)" title="Move to front">⬆ Priority</button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Skipped Queue ── -->
      <?php if (!empty($queueSkipped)): ?>
      <div class="card" style="margin-bottom:16px;border-left:3px solid rgba(200,130,80,.5);">
        <div class="card-title">⏭ Skipped <span style="font-weight:400;color:rgba(255,255,255,.35);margin-left:6px;">— <?= count($queueSkipped) ?></span></div>
        <div class="queue-list">
          <?php foreach ($queueSkipped as $qi): ?>
          <div class="queue-list-item">
            <div class="queue-num-badge" style="background:rgba(200,130,80,.15);color:#fdba74;border-color:rgba(200,130,80,.3);">#<?= htmlspecialchars($qi['queue_number']) ?></div>
            <div class="queue-item-info">
              <div class="queue-item-name"><?= htmlspecialchars($qi['patient_name']) ?> <?= priorityTags($qi['priority_flags']??'','sm') ?></div>
              <div class="queue-item-service"><?= htmlspecialchars($qi['services']) ?></div>
            </div>
            <div style="display:flex;gap:6px;align-items:center;">
              <span class="tag yellow">Skipped</span>
              <button class="action-btn" onclick="reinsertSkipped(<?= $qi['id'] ?>)">↩ Reinsert</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── Done Today ── -->
      <?php if (!empty($queueDone)): ?>
      <div class="card" style="border-left:3px solid rgba(80,160,130,.5);">
        <div class="card-title">✓ Completed Today <span style="font-weight:400;color:rgba(255,255,255,.35);margin-left:6px;">— <?= count($queueDone) ?></span></div>
        <div class="queue-list">
          <?php foreach ($queueDone as $qi): ?>
          <div class="queue-list-item" style="opacity:.7;">
            <div class="queue-num-badge" style="background:rgba(80,160,130,.15);color:#7bbfaa;border-color:rgba(80,160,130,.3);">#<?= htmlspecialchars($qi['queue_number']) ?></div>
            <div class="queue-item-info">
              <div class="queue-item-name"><?= htmlspecialchars($qi['patient_name']) ?></div>
              <div class="queue-item-service"><?= htmlspecialchars($qi['services']) ?></div>
            </div>
            <span class="tag green">Done</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- Right: SMS Panel -->
    <div>
      <div class="card sms-template-card">
        <div class="card-title">Manual SMS</div>
        <div class="form-group">
          <label class="form-label">Select Patient</label>
          <select class="form-select" id="manual-sms-patient" style="background:rgba(255,255,255,.09)!important;">
            <?php if ($nowServing): ?>
              <optgroup label="Now Serving">
                <option value="<?= htmlspecialchars($nowServing['patient_mobile']) ?>"><?= htmlspecialchars($nowServing['patient_name'].' (#'.$nowServing['queue_number'].')') ?></option>
              </optgroup>
            <?php endif; ?>
            <?php if (!empty($queueWaiting)): ?>
              <optgroup label="Waiting (<?= count($queueWaiting) ?>)">
                <?php foreach ($queueWaiting as $w): ?>
                  <option value="<?= htmlspecialchars($w['patient_mobile']) ?>"><?= htmlspecialchars($w['patient_name'].' (#'.$w['queue_number'].')') ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
            <?php if (!empty($queueSkipped)): ?>
              <optgroup label="Skipped (<?= count($queueSkipped) ?>)">
                <?php foreach ($queueSkipped as $s): ?>
                  <option value="<?= htmlspecialchars($s['patient_mobile']) ?>"><?= htmlspecialchars($s['patient_name'].' (#'.$s['queue_number'].') — Skipped') ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Message</label>
          <textarea class="form-control" id="manual-sms-message" rows="4"
            placeholder="Type your SMS message here…"
            style="resize:vertical;font-size:13px;"></textarea>
        </div>
        <button class="btn btn-sm btn-accent btn-block" onclick="sendManualSMS()">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          Send SMS Now
        </button>
      </div>
    </div>
  </div>
</section>


<section class="admin-section <?= $activeSection==='appointments'?'active':'' ?>" id="section-appointments">
  <div class="page-header">
    <div>
      <h2>Appointments</h2>
      <?php
        $apptDayLabel = $apptDate > $today ? 'Upcoming — ' : ($apptDate < $today ? 'Past — ' : 'Today — ');
      ?>
      <p><?= $apptDayLabel ?><?= htmlspecialchars($apptDateLabel) ?> · <?= count($allAppts) ?> appointment<?= count($allAppts)!==1?'s':'' ?> · Click ▶ to review.</p>
    </div>
    <div class="header-right">
      <?php
        $isFuture = ($apptDate > $today);
        $isPast   = ($apptDate < $today);
      ?>
      <?php if ($isFuture): ?><span class="tag cyan"  style="font-size:11px;">Future date</span><?php endif; ?>
      <?php if ($isPast):   ?><span class="tag orange" style="font-size:11px;">Past date</span><?php endif; ?>
      <input type="date" class="date-input" id="appt-date-picker"
             value="<?= htmlspecialchars($apptDate) ?>"
             onchange="changeViewDate(this.value)" title="Select any date — past or future"/>
      <button class="btn btn-gray btn-sm" onclick="changeViewDate('<?= $today ?>')">Today</button>
    </div>
  </div>

  <div class="card">
    <div class="appointment-filters">
      <button class="filter-btn active" id="appt-tab-today" onclick="filterAppts(this,'today')">Today (<?= count(array_filter($allAppts, fn($a)=>date('Y-m-d',strtotime($a['preferred_date']))===$today)) ?>)</button>
      <button class="filter-btn" onclick="filterAppts(this,'pending')">Pending (<?= count(array_filter($allAppts, fn($a)=>$a['status']==='pending')) ?>)</button>
      <button class="filter-btn" onclick="filterAppts(this,'confirmed')">Approved (<?= count(array_filter($allAppts, fn($a)=>in_array($a['status'],['confirmed','in_progress']))) ?>)</button>
      <button class="filter-btn" onclick="filterAppts(this,'done')">Completed (<?= count(array_filter($allAppts, fn($a)=>$a['status']==='done')) ?>)</button>
      <button class="filter-btn" onclick="filterAppts(this,'rejected')">Rejected (<?= count(array_filter($allAppts, fn($a)=>$a['status']==='rejected')) ?>)</button>
      <div class="search-bar-wrap" style="flex:1;min-width:160px;margin-bottom:0;">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" class="search-input" placeholder="Search patient or service…" oninput="filterApptsSearch(this.value)"/>
      </div>
    </div>
    <table class="data-table">
      <thead><tr><th>Queue #</th><th>Patient</th><th>Services</th><th>Priority</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="appt-table-body">
        <?php foreach ($allAppts as $appt): ?>
        <tr data-status="<?= htmlspecialchars($appt['status']) ?>"
            data-pref-date="<?= htmlspecialchars($appt['preferred_date'] ?? '') ?>"
            data-name="<?= htmlspecialchars($appt['patient_name']) ?>"
            data-queue="<?= htmlspecialchars($appt['queue_number'] ?? '999') ?>">
          <td><strong style="color:#7ab8cc;"><?= $appt['queue_number'] ? '#'.htmlspecialchars($appt['queue_number']) : '—' ?></strong></td>
          <td><div class="patient-name-cell"><div class="patient-dot"><?= strtoupper(substr($appt['patient_name'],0,1)) ?></div><?= htmlspecialchars($appt['patient_name']) ?></div></td>
          <td style="color:rgba(255,255,255,.6);font-size:12px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($appt['services']) ?></td>
          <td><?= priorityTags($appt['priority_flags']??'') ?></td>
          <td style="color:rgba(255,255,255,.45);font-size:12px;"><?= fd($appt['preferred_date']) ?></td>
          <td><span class="tag <?= statusTag($appt['status']) ?>"><?= statusLabel($appt['status']) ?></span></td>
          <td>
            <button class="action-btn" onclick="openApptModal(<?= $appt['id'] ?>)" title="Medical Review">▶ Review</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($allAppts)): ?>
          <tr><td colspan="7" style="text-align:center;color:rgba(255,255,255,.3);padding:30px;">No appointments today.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>


<section class="admin-section <?= $activeSection==='patients'?'active':'' ?>" id="section-patients">
  <div class="page-header">
    <div><h2>Patients</h2><p>All registered patients. Click ▶ to view full profile and medical history.</p></div>
    <div class="header-right">
      <button class="btn btn-sm btn-gray" onclick="exportPatients()">Export CSV</button>
    </div>
  </div>
  <div class="card">
    <!-- Filters row -->
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px;">
      <div class="search-bar-wrap" style="flex:1;min-width:200px;margin-bottom:0;">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" class="search-input" id="patient-search" placeholder="Search name, mobile, or company…" oninput="filterPatients()"/>
      </div>
      <div style="display:flex;align-items:center;gap:6px;">
        <svg width="14" height="14" fill="none" stroke="rgba(255,255,255,.4)" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        <input type="date" class="date-input" id="patient-date-filter" onchange="filterPatients()" title="Filter by last visit date"/>
      </div>
      <div style="display:flex;gap:6px;">
        <button class="filter-btn active" id="sort-surname-btn" onclick="sortPatients('surname')">A–Z Name</button>
        <button class="filter-btn" id="sort-queue-btn" onclick="sortPatients('queue')">Queue #</button>
      </div>
    </div>
    <table class="data-table" id="patient-table">
      <thead><tr><th>Name</th><th>Mobile</th><th>Sex</th><th>Age</th><th>Company</th><th>Address</th><th>Visits</th><th>Last Visit</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($patients as $p): ?>
        <tr data-name="<?= htmlspecialchars($p['name']) ?>"
            data-visits="<?= (int)$p['appt_count'] ?>"
            data-last-visit="<?= htmlspecialchars($p['last_visit'] ?? '') ?>"
            data-queue="<?= htmlspecialchars($p['last_queue'] ?? '999') ?>">
          <td><div class="patient-name-cell"><div class="patient-dot"><?= strtoupper(substr($p['name'],0,1)) ?></div><?= htmlspecialchars($p['name']) ?></div></td>
          <td style="color:rgba(255,255,255,.55);font-size:12px;"><?= htmlspecialchars($p['mobile']) ?></td>
          <td style="color:rgba(255,255,255,.55);"><?= htmlspecialchars($p['sex']) ?></td>
          <td><?= age($p['dob']) ?></td>
          <td style="color:rgba(255,255,255,.55);"><?= htmlspecialchars($p['company']?:'—') ?></td>
          <td style="color:rgba(255,255,255,.55);font-size:12px;"><?= htmlspecialchars($p['address']?:'—') ?></td>
          <td style="color:#22d3ee;font-weight:600;"><?= (int)$p['appt_count'] ?></td>
          <td style="color:rgba(255,255,255,.45);font-size:12px;"><?= fd($p['last_visit']) ?></td>
          <td><button class="action-btn" onclick="openPatientModal(<?= $p['id'] ?>)">▶</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>


<section class="admin-section <?= $activeSection==='sms'?'active':'' ?>" id="section-sms">
  <div class="page-header">
    <div>
      <h2>SMS Logs</h2>
      <p>Today — <?= htmlspecialchars((new DateTime($today))->format('F j, Y')) ?> · <?= $smsSentCount ?> sent, <?= $smsFailed ?> failed</p>
    </div>
    <div class="header-right">
      <button class="btn btn-gray btn-sm" onclick="reloadSection()">↻ Refresh</button>
    </div>
  </div>
  <div class="sms-counter-grid">
    <div class="sms-counter"><div class="sms-counter-icon sent-bg"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div><div class="sms-counter-val"><?= $smsSentCount ?></div><div class="sms-counter-lbl">Sent</div></div></div>
    <div class="sms-counter"><div class="sms-counter-icon fail-bg"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg></div><div><div class="sms-counter-val" style="color:#f87171;"><?= $smsFailed ?></div><div class="sms-counter-lbl">Failed</div></div></div>
    <div class="sms-counter"><div class="sms-counter-icon pend-bg"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div><div><div class="sms-counter-val" style="color:#fb923c;"><?= $smsPending ?></div><div class="sms-counter-lbl">Pending</div></div></div>
  </div>
  <div class="card">
    <!-- Search + Sort toolbar -->
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px;">
      <div class="search-bar-wrap" style="flex:1;min-width:200px;margin-bottom:0;">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" class="search-input" id="sms-search"
               placeholder="Search patient name…"
               oninput="filterSmsLogs(this.value)"/>
      </div>
      <select class="form-select" id="sms-sort-select"
              style="width:auto;padding:8px 32px 8px 12px;font-size:12px;"
              onchange="sortSmsLogs(this.value)">
        <option value="">Sort by…</option>
        <option value="name-az">Name A–Z</option>
        <option value="name-za">Name Z–A</option>
        <option value="queue-asc">Queue # ↑</option>
        <option value="time-desc">Time (newest)</option>
      </select>
    </div>
    <table class="data-table" id="sms-log-table">
      <thead><tr><th>Queue #</th><th>Patient</th><th>Mobile</th><th>Type</th><th>Message</th><th>Status</th><th>Time</th></tr></thead>
      <tbody id="sms-log-body">
        <?php foreach ($smsLogs as $log): ?>
        <tr style="cursor:pointer;"
            data-name="<?= htmlspecialchars($log['patient_name']) ?>"
            data-queue="<?= htmlspecialchars($log['queue_number'] ?? '999') ?>"
            data-time="<?= htmlspecialchars($log['created_at'] ?? '') ?>"
            onclick="openSmsHistory('<?= htmlspecialchars(addslashes($log['patient_mobile'])) ?>','<?= htmlspecialchars(addslashes($log['patient_name'])) ?>')"
            title="View full SMS history">
          <td><strong style="color:#38bdf8;"><?= !empty($log['queue_number']) ? '#'.htmlspecialchars($log['queue_number']) : '—' ?></strong></td>
          <td><div class="patient-name-cell"><div class="patient-dot" style="font-size:10px;"><?= strtoupper(substr($log['patient_name'],0,2)) ?></div><?= htmlspecialchars($log['patient_name']) ?></div></td>
          <td style="color:rgba(255,255,255,.45);font-size:12px;"><?= htmlspecialchars($log['patient_mobile']) ?></td>
          <td><?php $st=$log['sms_type']??'manual'; $tc=match($st){'approved'=>'green','rejected'=>'red','called'=>'cyan','completed'=>'gray',default=>'gray'}; ?><span class="tag <?= $tc ?>"><?= ucfirst($st) ?></span></td>
          <td style="max-width:220px;color:rgba(255,255,255,.5);font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($log['message']) ?></td>
          <td><span class="sms-status <?= htmlspecialchars($log['status']) ?>"><?= ucfirst(htmlspecialchars($log['status'])) ?></span></td>
          <td style="color:rgba(255,255,255,.42);font-size:12px;white-space:nowrap;"><?= (new DateTime($log['created_at']))->format('g:i A') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($smsLogs)): ?>
          <tr><td colspan="7" style="text-align:center;color:rgba(255,255,255,.3);padding:30px;">No SMS logs for today.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>


<section class="admin-section <?= $activeSection==='services'?'active':'' ?>" id="section-services">
  <div class="page-header">
    <div><h2>Services</h2><p>Manage available tests and procedures.</p></div>
    <div class="header-right">
      <button class="svc-add-btn" onclick="openAddService()">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Add Service
      </button>
    </div>
  </div>

  <!-- Basic 5 -->
  <div class="card svc-section-card" style="margin-bottom:18px;">
    <div class="svc-section-header">
      <div>
        <span class="tag cyan" style="margin-right:8px;font-size:11px;letter-spacing:.08em;">BASIC 5</span>
        <span style="font-size:14px;font-weight:600;color:#fff;">Master Package</span>
      </div>
      <span style="font-size:12px;color:rgba(255,255,255,.38);">Auto-selected for patients in one click</span>
    </div>
    <div class="services-admin-grid">
      <?php foreach ($basicServices as $svc): ?>
      <div class="service-admin-card" id="svc-<?= $svc['id'] ?>">
        <div class="service-admin-info">
          <div class="s-cat"><?= htmlspecialchars($svc['category_name']) ?></div>
          <div class="s-name"><?= htmlspecialchars($svc['name']) ?></div>
          <?php if (!empty($svc['description'])): ?>
            <div class="s-desc"><?= htmlspecialchars($svc['description']) ?></div>
          <?php endif; ?>
          <div class="s-price">₱<?= number_format($svc['price'],0) ?></div>
        </div>
        <div class="service-admin-actions">
          <button class="svc-edit-btn" onclick="openEditService(<?= $svc['id'] ?>,'<?= htmlspecialchars(addslashes($svc['name'])) ?>',<?= $svc['price'] ?>,'<?= htmlspecialchars(addslashes($svc['description'])) ?>',<?= $svc['active'] ?>)">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Other Services -->
  <div class="card svc-section-card">
    <div class="svc-section-header">
      <span style="font-size:14px;font-weight:600;color:#fff;">Other Services</span>
      <span style="font-size:12px;color:rgba(255,255,255,.38);"><?= count($otherServices) ?> service<?= count($otherServices)!==1?'s':'' ?></span>
    </div>
    <?php if (empty($otherServices)): ?>
      <p style="text-align:center;color:rgba(255,255,255,.32);font-size:13px;padding:30px 0;">No other services yet. Click "+ Add Service" to create one.</p>
    <?php else: ?>
    <div class="services-admin-grid">
      <?php foreach ($otherServices as $svc): ?>
      <div class="service-admin-card <?= !$svc['active']?'svc-inactive':'' ?>" id="svc-<?= $svc['id'] ?>">
        <div class="service-admin-info">
          <div class="s-cat"><?= htmlspecialchars($svc['category_name']) ?></div>
          <div class="s-name">
            <?= htmlspecialchars($svc['name']) ?>
            <?php if (!$svc['active']): ?><span class="tag gray" style="font-size:9px;margin-left:6px;">Inactive</span><?php endif; ?>
          </div>
          <?php if (!empty($svc['description'])): ?>
            <div class="s-desc"><?= htmlspecialchars($svc['description']) ?></div>
          <?php endif; ?>
          <div class="s-price">₱<?= number_format($svc['price'],0) ?></div>
        </div>
        <div class="service-admin-actions">
          <button class="svc-edit-btn" onclick="openEditService(<?= $svc['id'] ?>,'<?= htmlspecialchars(addslashes($svc['name'])) ?>',<?= $svc['price'] ?>,'<?= htmlspecialchars(addslashes($svc['description'])) ?>',<?= $svc['active'] ?>)">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </button>
          <button class="svc-toggle-btn <?= $svc['active']?'svc-toggle-disable':'svc-toggle-enable' ?>"
                  onclick="toggleServiceActive(<?= $svc['id'] ?>,<?= $svc['active']?0:1 ?>)">
            <?= $svc['active']?'Disable':'Enable' ?>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>


<section class="admin-section <?= $activeSection==='analytics'?'active':'' ?>" id="section-analytics">
  <div class="page-header">
    <div><h2>Analytics</h2><p>Patient volume, income, and service insights.</p></div>
    <div class="header-right">
      <div class="analytics-period-btns">
        <button class="period-btn active" onclick="setPeriod('daily',this)">Daily</button>
        <button class="period-btn" onclick="setPeriod('monthly',this)">Monthly</button>
        <button class="period-btn" onclick="setPeriod('yearly',this)">Yearly</button>
      </div>
      <div class="analytics-date-row">
        <input type="date" class="date-input" id="analytics-date" value="<?= date('Y-m-d') ?>" onchange="loadAnalytics()"/>
        <input type="month" class="date-input" id="analytics-month" value="<?= date('Y-m') ?>" style="display:none;" onchange="loadAnalytics()"/>
        <input type="number" class="date-input" id="analytics-year" value="<?= date('Y') ?>" min="2020" max="2030" style="display:none;width:90px;" onchange="loadAnalytics()"/>
      </div>
    </div>
  </div>

  <!-- 2 main analytics cards — filled by loadAnalytics() -->
  <div class="an-cards-row">
    <!-- Total Patients -->
    <div class="an-card an-card-patients">
      <div class="an-card-icon">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
        </svg>
      </div>
      <div class="an-card-body">
        <div class="an-card-val" id="an-total">—</div>
        <div class="an-card-lbl">Total Patients</div>
        <div class="an-card-sub" id="an-pct">Loading…</div>
      </div>
    </div>

    <!-- Income % -->
    <div class="an-card an-card-income">
      <div class="an-card-icon">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <line x1="12" y1="1" x2="12" y2="23"/>
          <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
        </svg>
      </div>
      <div class="an-card-body">
        <div class="an-card-val" id="an-income">—</div>
        <div class="an-card-lbl">Income vs Previous</div>
        <div class="an-card-sub" id="an-income-pct">Loading…</div>
      </div>
    </div>
  </div>

  <!-- Compact chart + top services side by side -->
  <div class="an-chart-row">
    <div class="chart-card an-chart-compact">
      <div class="chart-title" id="an-vol-title">Volume</div>
      <div class="chart-sub" id="an-vol-sub">Loading…</div>
      <div class="bar-chart an-bar-chart" id="analytics-bar-chart">
        <p style="color:rgba(255,255,255,.3);font-size:12px;margin:auto;">Loading…</p>
      </div>
    </div>
    <div class="chart-card">
      <div class="chart-title">Top Services</div>
      <div class="chart-sub" id="an-svc-sub">Loading…</div>
      <div id="analytics-top-services" style="margin-top:8px;">
        <p style="color:rgba(255,255,255,.3);font-size:12px;">Loading…</p>
      </div>
    </div>
  </div>

  <!-- Patient Search + Appointments List -->
  <div class="card" style="margin-top:18px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
      <div class="card-title" id="an-appts-title" style="margin-bottom:0;">
        Appointments
        <span id="an-appts-count" style="font-weight:400;color:rgba(255,255,255,.35);margin-left:6px;"></span>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex:1;min-width:220px;">
        <div class="search-bar-wrap" style="flex:1;margin-bottom:0;">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" class="search-input" id="an-patient-search"
                 placeholder="Search patient name or service…"
                 oninput="searchAnalyticsAppts(this.value)"/>
        </div>
        <select class="form-select" id="an-sort-select"
                style="width:auto;padding:8px 32px 8px 12px;font-size:12px;flex-shrink:0;"
                onchange="sortAnalyticsAppts(this.value)">
          <option value="">Sort by…</option>
          <option value="queue-asc">Queue # ↑</option>
          <option value="name-az">Name A–Z</option>
          <option value="name-za">Name Z–A</option>
          <option value="date-asc">Date ↑</option>
          <option value="date-desc">Date ↓</option>
        </select>
      </div>
    </div>
    <div id="analytics-appointments-list">
      <p style="color:rgba(255,255,255,.3);font-size:13px;text-align:center;padding:24px;">Select a period to load data.</p>
    </div>
  </div>
</section>

</main>
</div><!-- admin-layout -->


<div class="modal-overlay hidden" id="appt-detail-modal" onclick="if(event.target===this)closeApptModal()">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title" id="appt-modal-title">Medical Review</div>
      <button class="modal-close" onclick="closeApptModal()">✕</button>
    </div>
    <div class="modal-body" id="appt-modal-body"></div>
  </div>
</div>


<div class="modal-overlay hidden" id="patient-modal" onclick="if(event.target===this)closePatientModal()">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title">Patient Profile</div>
      <button class="modal-close" onclick="closePatientModal()">✕</button>
    </div>
    <div class="modal-body" id="patient-modal-body"><p style="color:rgba(255,255,255,.4);">Loading…</p></div>
  </div>
</div>


<div class="modal-overlay hidden" id="service-modal" onclick="if(event.target===this)closeServiceModal()">
  <div class="modal svc-modal">
    <div class="modal-header">
      <div class="modal-title" id="svc-modal-title">Add Service</div>
      <button class="modal-close" onclick="closeServiceModal()">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="svc-id"/>
      <input type="hidden" id="svc-active" value="1"/>

      <!-- Service Name -->
      <div class="form-group">
        <label class="form-label">Service Name *</label>
        <input type="text" class="form-control" id="svc-name" placeholder="e.g. Complete Blood Count (CBC)"/>
      </div>

      <!-- Description -->
      <div class="form-group">
        <label class="form-label">
          Description
          <span style="color:rgba(255,255,255,.35);font-weight:400;text-transform:none;margin-left:4px;">(optional)</span>
        </label>
        <textarea class="form-control" id="svc-description" rows="2"
          placeholder="Brief description of this service…"
          style="resize:none;"></textarea>
      </div>

      <!-- Price -->
      <div class="form-group">
        <label class="form-label">Price (₱) *</label>
        <div style="position:relative;">
          <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.45);font-size:14px;">₱</span>
          <input type="number" class="form-control" id="svc-price" placeholder="0" min="0" style="padding-left:30px;"/>
        </div>
      </div>

      <!-- Category -->
      <div class="form-group">
        <label class="form-label">Category</label>
        <div class="svc-select-wrap">
          <select class="form-select svc-category-select" id="svc-category">
            <?php foreach ($serviceCategories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <svg class="svc-select-arrow" width="12" height="8" fill="none" viewBox="0 0 12 8"><path d="M1 1l5 5 5-5" stroke="rgba(255,255,255,.45)" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
      </div>

      <!-- Status toggle -->
      <div class="form-group" id="svc-status-row">
        <label class="form-label">Status</label>
        <div class="svc-status-toggle">
          <button type="button" class="svc-status-opt active" id="svc-status-active-btn" onclick="setSvcStatus(1)">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
            Active
          </button>
          <button type="button" class="svc-status-opt" id="svc-status-inactive-btn" onclick="setSvcStatus(0)">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            Inactive
          </button>
        </div>
      </div>

      <!-- Actions -->
      <div class="svc-modal-footer">
        <button class="svc-cancel-btn" onclick="closeServiceModal()">Cancel</button>
        <button class="svc-save-btn" onclick="saveService()">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
          Save Service
        </button>
      </div>
    </div>
  </div>
</div>

<!-- SMS History Modal -->
<div class="modal-overlay hidden" id="sms-history-modal" onclick="if(event.target===this)closeSmsHistory()">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header">
      <div class="modal-title" id="sms-history-title">SMS History</div>
      <button class="modal-close" onclick="closeSmsHistory()">✕</button>
    </div>
    <div class="modal-body" id="sms-history-body"></div>
  </div>
</div>

<div class="toast-container" id="toast-container"></div>

<script>
const APPT_DATA = <?= json_encode($apptJS, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_HEX_APOS) ?>;
let currentAnalyticsPeriod = 'daily';
</script>
<script src="../js/admin.js"></script>
</body>
</html>
