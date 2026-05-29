<?php
require_once __DIR__ . '/db.php';

$settings = [];
$settingRows = db_query('SELECT setting_key, setting_value FROM settings');
if ($settingRows) {
    while ($row = $settingRows->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
$siteName    = $settings['clinic_name']     ?? 'M.V. Masangkay Clinic';
$siteSubtitle = $settings['clinic_subtitle'] ?? 'X-Ray & Laboratory';

$serviceCategories = [];
$categoryRows = db_query('SELECT id, name, slug FROM service_categories ORDER BY sort_order, name');
if ($categoryRows) {
    while ($row = $categoryRows->fetch_assoc()) {
        $serviceCategories[] = $row;
    }
}

$services = [];
$serviceRows = db_query('SELECT s.*, c.slug AS category_slug, c.name AS category_name FROM services s JOIN service_categories c ON s.category_id = c.id WHERE s.active = 1 ORDER BY c.sort_order, s.name');
if ($serviceRows) {
    while ($row = $serviceRows->fetch_assoc()) {
        $services[] = $row;
    }
}

$today        = date('Y-m-d');

$queueNow     = db_row("SELECT queue_number, services FROM appointments WHERE status = 'in_progress' AND DATE(created_at) = ? ORDER BY created_at ASC LIMIT 1", 's', [$today]);
$waitingCount = db_row("SELECT COUNT(*) AS cnt FROM appointments WHERE status = 'confirmed' AND DATE(created_at) = ?", 's', [$today]);
$doneToday    = db_row("SELECT COUNT(*) AS cnt FROM appointments WHERE status = 'done' AND DATE(created_at) = ?", 's', [$today]);
$waitingCount = $waitingCount['cnt'] ?? 0;
$doneToday    = $doneToday['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($siteName) ?></title>
  <link rel="icon" type="image/jpeg" href="logo/logo.jpg"/>
  <link rel="stylesheet" href="css/style.css"/>
  <link rel="stylesheet" href="css/patient.css"/>
</head>
<body class="patient-portal">


<nav class="top-nav">
  <div class="nav-brand">
    <div class="nav-logo">
      <img src="logo/logo.jpg" alt="<?= htmlspecialchars($siteName) ?>" class="nav-logo-img"/>
    </div>
    <div class="nav-brand-text">
      <div class="clinic-title"><?= htmlspecialchars($siteName) ?></div>
      <div class="clinic-sub"><?= htmlspecialchars($siteSubtitle) ?></div>
    </div>
  </div>

  <div class="nav-right">
    <button class="profile-btn" onclick="toggleDropdown()">
      <div class="profile-avatar" id="profile-avatar-initial">?</div>
      <span id="profile-btn-label">Login / Register</span>
      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="dropdown-menu" id="dropdown-menu">
      <div class="dropdown-item" id="dd-login-item" onclick="goPatientStep(1); closeDropdown()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
        Login / Register
      </div>
      <div class="dropdown-item" id="dd-profile-item" style="display:none;" onclick="goPatientStep(0); closeDropdown()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        My Profile
      </div>
      <div class="dropdown-item" id="dd-booking-item" style="display:none;" onclick="goPatientStep(7); closeDropdown()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        My Appointments
      </div>
      <div class="dropdown-item" id="dd-queue-item" style="display:none;" onclick="goPatientStep(6); closeDropdown()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Track Queue
      </div>
      <div class="dropdown-divider"></div>
      <a class="dropdown-item admin-link" href="admin/login.php">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Admin Login
      </a>
    </div>
  </div>
</nav>


<div class="modal-overlay" id="patient-select-modal">
  <div class="modal-box">
    <div class="card-icon" style="margin-bottom:20px;">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
    </div>
    <h3>Continue as…</h3>
    <p class="modal-sub">Select an existing profile or create a new one linked to your mobile number.</p>

    <div class="patient-list" id="patient-list-container">

    </div>

    <div class="modal-btn-row" style="margin-top:20px;">
      <button class="btn btn-gray btn-sm" onclick="closePatientModal(); goPatientStep(1);">Back</button>
      <button class="btn btn-primary" style="flex:1;" onclick="selectPatientAndContinue()">
        Continue →
      </button>
    </div>
  </div>
</div>


<div class="modal-overlay booking-modal" id="booking-confirm-modal">
  <div class="modal-box">
    <div class="booking-modal-icon">
      <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>
    <h3>Confirm Booking</h3>
    <p class="modal-desc">Please review your appointment details before submitting. Your slot will be reserved upon confirmation.</p>

    <div class="booking-summary-confirm g-card-inner" style="text-align:left;">
      <div class="bsc-row"><span class="bsc-label">Patient</span><span class="bsc-value" id="bsc-patient">—</span></div>
      <div class="bsc-row"><span class="bsc-label">Mobile</span><span class="bsc-value" id="bsc-mobile">—</span></div>
      <div class="bsc-row"><span class="bsc-label">Services</span><span class="bsc-value" id="bsc-services">—</span></div>
      <div class="bsc-row"><span class="bsc-label">Date</span><span class="bsc-value" id="bsc-date">—</span></div>
      <div class="bsc-row"><span class="bsc-label">Total</span><span class="bsc-value" style="color:var(--accent);" id="bsc-total">—</span></div>
    </div>

    <div class="modal-btn-row">
      <button class="btn btn-gray btn-sm" onclick="closeBookingModal()">Edit</button>
      <button class="btn btn-primary" style="flex:1;" id="confirm-submit-btn" onclick="submitBookingConfirmed()">
        Submit Appointment
      </button>
    </div>
  </div>
</div>

<main class="patient-main">
  <div class="patient-content">

    <div class="patient-screen active" id="pstep-0">
      <div class="landing-wrap">
        <div class="landing-hero">
          <h1><?= htmlspecialchars($siteName) ?>,<br><em>Appointment</em><br>System</h1>
          <p>Skip the long lines. Book your lab tests and check-ups online. Get your queue number and receive SMS updates — no waiting inside the clinic.</p>
          <div class="hero-cta">
            <button class="btn btn-primary" onclick="goPatientStep(1)">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              Book Appointment
            </button>
          </div>
        </div>

        <div>
          <div class="queue-preview">
            <div class="queue-preview-header">

              <div class="qpreview-live-badge">
                <div class="live-dot" style="background:#4ade80;"></div> Live
              </div>
              <div class="qlabel" style="margin-top:6px;">Now Serving</div>
              <div class="qnum"><?php if ($queueNow && !empty($queueNow['queue_number'])): ?>#<span id="live-now"><?= htmlspecialchars($queueNow['queue_number']) ?></span><?php else: ?><span id="live-now">—</span><?php endif; ?></div>
              <div class="qbadge"><?= htmlspecialchars($queueNow['services'] ?? '—') ?></div>
            </div>
            <div class="queue-preview-body">
              <div class="queue-mini-stats">
                <div class="qms">
                  <div class="qval" id="live-waiting"><?= htmlspecialchars($waitingCount) ?></div>
                  <div class="qlbl">Waiting</div>
                </div>
                <div class="qms">
                  <div class="qval" id="live-done"><?= htmlspecialchars($doneToday) ?></div>
                  <div class="qlbl">Done today</div>
                </div>
              </div>
              <p class="clinic-hours"><strong>Open today</strong> · 8:00 AM – 5:00 PM</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="patient-screen" id="pstep-1">
      <div class="otp-card">
        <div class="g-card">
          <div class="card-icon">
            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
          </div>
          <div class="step-label">Authentication</div>
          <h3>Enter your mobile number</h3>
          <p class="desc">We'll send you a one-time password (OTP) to verify your identity.</p>
          <div class="form-group">
            <label class="form-label">Mobile Number</label>
            <div class="phone-group">
              <div class="phone-prefix">🇵🇭 +63</div>
              <input type="tel" class="form-control" id="mobile-input" placeholder="9XX XXX XXXX" maxlength="10" inputmode="numeric"/>
            </div>
          </div>
          <label class="another-booking-row" id="another-booking-row" onclick="toggleBookingForOtherClick(this)">
            <input type="checkbox" id="booking-for-other-check"/>
            <span class="another-booking-text">
              <span class="another-booking-checkbox"></span>
              <span>
                <span class="another-booking-label">Booking for another person?</span>
              </span>
            </span>
          </label>

          <button class="btn btn-primary btn-block" onclick="sendOTP()">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013 12.62 19.79 19.79 0 01.06 4.1a2 2 0 012-2.18h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.13 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
            Send OTP
          </button>
          <p class="disclaimer mt-8">By continuing, you agree to receive SMS notifications.</p>
          <button class="btn btn-gray btn-sm btn-block mt-8" onclick="goPatientStep(0)">Back</button>
        </div>
      </div>
    </div>


    <div class="patient-screen" id="pstep-2">
      <div class="otp-card">
        <div class="g-card">
          <div class="card-icon">
            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <div class="step-label">Verification</div>
          <h3>Enter OTP</h3>
          <p class="desc" id="otp-sent-msg">We sent a 6-digit code to <strong id="otp-phone-display">your number</strong>. Enter it below to verify your identity.</p>


          <div class="otp-boxes">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
          </div>
          <button class="btn btn-primary btn-block" onclick="verifyOTP()">Verify &amp; Continue</button>
          <p class="hint-text mt-8">Didn't get a code? <a href="#" onclick="sendOTP(); return false;">Resend OTP</a> · <a href="#" onclick="goPatientStep(1); return false;">Change number</a></p>
          <button class="btn btn-gray btn-sm btn-block mt-8" onclick="goPatientStep(1)">Back</button>
        </div>
      </div>
    </div>


    <div class="patient-screen" id="pstep-3">
      <div class="profile-card">
        <div class="g-card">
          <div class="step-label">Step 1 of 3 — Patient Profile</div>
          <h2>Personal Information</h2>
          <p>This information is used for accurate record preparation.</p>


          <div class="form-row-3">
            <div class="form-group">
              <label class="form-label">First Name *</label>
              <input type="text" class="form-control" id="patient-firstname" placeholder="Juan"/>
            </div>
            <div class="form-group">
              <label class="form-label">Middle Name</label>
              <input type="text" class="form-control" id="patient-middlename" placeholder="(optional)"/>
            </div>
            <div class="form-group">
              <label class="form-label">Last Name *</label>
              <input type="text" class="form-control" id="patient-lastname" placeholder="Dela Cruz"/>
            </div>
          </div>


          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Suffix</label>
              <select class="form-select" id="patient-suffix">
                <option value="None">None</option>
                <option value="Jr.">Jr.</option>
                <option value="Sr.">Sr.</option>
                <option value="III">III</option>
                <option value="IV">IV</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Gender *</label>
              <select class="form-select" id="patient-sex">
                <option value="Male" selected>Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>


          <div class="form-group">
            <label class="form-label">Date of Birth *</label>
            <input type="date" class="form-control" id="patient-dob"/>
          </div>


          <div class="form-group">
            <label class="form-label">Address</label>
            <input type="text" class="form-control" id="patient-address" placeholder="Street, Barangay, City"/>
          </div>


          <div class="form-group">
            <label class="form-label">Company / School <span style="opacity:.55;font-weight:400;text-transform:none;">(optional)</span></label>
            <input type="text" class="form-control" id="patient-company" placeholder="e.g. ABC Corp, University of Santo Tomas"/>
          </div>


          <div class="form-group">
            <label class="form-label">Mobile Number <span style="position:static;transform:none;display:inline-flex;margin-left:6px;"</span></label>
            <input type="text" class="form-control" id="patient-mobile-display" readonly placeholder="+63 —"/>
          </div>


          <div class="form-group">
            <div class="priority-section-label">Priority Status</div>
            <div class="priority-flags">
              <div class="priority-pill" id="pill-senior" onclick="togglePriority('senior')">
                Senior Citizen
                <span class="pill-check">✓</span>
              </div>
              <div class="priority-pill" id="pill-pregnant" onclick="togglePriority('pregnant')">
                Pregnant
                <span class="pill-check">✓</span>
              </div>
              <div class="priority-pill" id="pill-pwd" onclick="togglePriority('pwd')">

                PWD
                <span class="pill-check">✓</span>
              </div>
            </div>
          </div>

          <div style="display:flex;gap:12px;margin-top:8px;">
            <button class="btn btn-gray btn-sm" onclick="goPatientStep(2)">Back</button>
            <button class="btn btn-primary" style="flex:1;" onclick="saveProfileAndContinue()">Continue</button>
          </div>
        </div>
      </div>
    </div>


    <div class="patient-screen" id="pstep-4">
      <div class="medical-card">
        <div class="step-label">Step 2 of 3 — Medical History <span style="margin-left:8px;"></span></div>
        <h2>Medical History Form</h2>
        <p>Please complete all sections accurately. This information helps the clinic prepare for your appointment.</p>


        <div class="medical-section">
          <div class="medical-section-title">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            I. Background Information
          </div>
          <div class="form-group">
            <label class="form-label">Hereditary Diseases in Family</label>
            <textarea class="form-control" id="med-hereditary" placeholder="e.g. Diabetes, Cancer, Hypertension..."></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">History of Hospitalization</label>
            <textarea class="form-control" id="med-hospitalization" placeholder="Describe any past hospitalizations..."></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">History of Surgery</label>
            <textarea class="form-control" id="med-surgery" placeholder="Describe any past surgeries..."></textarea>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Allergies (Food or Medicine)</label>
            <textarea class="form-control" id="med-allergies" placeholder="e.g. Penicillin, shellfish, aspirin..."></textarea>
          </div>
        </div>


        <div class="medical-section">
          <div class="medical-section-title">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 6v6l4 2"/></svg>
            II. Lifestyle
          </div>
          <div class="lifestyle-row">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Smoking Habit (sticks/day)</label>
              <input type="number" class="form-control" id="med-smoking" placeholder="0 = non-smoker" min="0"/>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Drinking Habit</label>
              <select class="form-select" id="med-drinking">
                <option value="">Select frequency</option>
                <option value="Never">Never</option>
                <option value="Occasionally">Occasionally</option>
                <option value="Regularly">Regularly (Weekly)</option>
                <option value="Daily">Daily</option>
              </select>
            </div>
          </div>
        </div>


        <div class="medical-section">
          <div class="medical-section-title">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            III. Symptoms Checklist
          </div>
          <div class="check-grid" id="symptoms-grid">
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="Cough / Cold">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              Cough / Cold
            </div>
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="Tooth Decay">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              Tooth Decay
            </div>
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="Has Tattoo">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              Has Tattoo
            </div>
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="Fever / Joint Pain">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              Fever / Joint Pain
            </div>
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="Constipation">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              Constipation
            </div>
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="Skin Problems">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              Skin Problems
            </div>
          </div>
        </div>


        <div class="medical-section" style="margin-bottom:28px;">
          <div class="medical-section-title">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
            IV. Pre-existing Conditions
          </div>
          <div class="check-grid" id="conditions-grid">
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="Heart Disease">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              Heart Disease
            </div>
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="Diabetes">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              Diabetes
            </div>
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="Goiter">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              Goiter
            </div>
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="Asthma / Lung Disease">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              Asthma / Lung Disease
            </div>
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="High Blood">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              High Blood
            </div>
            <div class="check-pill" onclick="toggleCheckPill(this)" data-val="Epilepsy">
              <span class="check-pill-box"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
              Epilepsy
            </div>
          </div>
        </div>

        <div style="display:flex;gap:12px;">
          <button class="btn btn-gray btn-sm" onclick="goPatientStep(3)">Back to Profile</button>
          <button class="btn btn-primary" style="flex:1;" onclick="saveMedicalAndContinue()">Continue to Services</button>
        </div>
      </div>
    </div>


    <div class="patient-screen" id="pstep-5">
      <div class="step-label" style="margin-bottom:4px;">Step 3 of 3 — Choose Services</div>
      <h2 class="section-heading">Choose your services</h2>
      <p class="section-sub">Select one or more tests and procedures.</p>

      <div class="booking-layout">
        <div class="booking-panel">


          <div class="search-bar-glass">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" id="service-search" placeholder="Search services..." oninput="searchServices(this.value)"/>
          </div>


          <div class="basic5-banner" id="basic5-banner" onclick="toggleBasic5()">
            <div class="basic5-info">
              <div class="b5-title">Basic 5 Package</div>
              <div class="b5-sub">CBC · Urinalysis · Fecalysis · Physical Exam · Chest X-Ray</div>
            </div>
            <div class="basic5-toggle" id="basic5-toggle"></div>
          </div>


          <div class="service-tabs">
            <button class="service-tab active" onclick="filterServices(this,'all')">All Services</button>
            <?php foreach ($serviceCategories as $category): ?>
              <button class="service-tab" onclick="filterServices(this,'<?= htmlspecialchars($category['slug']) ?>')"><?= htmlspecialchars($category['name']) ?></button>
            <?php endforeach; ?>
          </div>


          <div class="services-grid" id="services-grid">
            <?php foreach ($services as $service): ?>
            <div class="service-item<?= $service['is_basic'] ? ' selected' : '' ?>"
                 data-cat="<?= htmlspecialchars($service['category_slug']) ?>"
                 data-basic="<?= $service['is_basic'] ? 'true' : 'false' ?>"
                 data-name="<?= htmlspecialchars($service['name']) ?>"
                 data-price="<?= htmlspecialchars(number_format($service['price'], 0, '.', '')) ?>"
                 onclick="toggleService(this)">
              <div class="check-badge">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
              </div>
              <div class="service-name"><?= htmlspecialchars($service['name']) ?></div>
              <div class="service-price"><strong>₱<?= htmlspecialchars(number_format($service['price'], 0, '.', '')) ?></strong></div>
            </div>
            <?php endforeach; ?>
          </div>


          <div class="form-group">
            <label class="form-label">Custom Service Request <span style="opacity:.55;font-weight:400;text-transform:none;">(optional)</span></label>
            <input type="text" class="form-control" id="custom-service" placeholder="e.g. Specialized blood panel, other test..."/>
          </div>


          <div class="form-group">
            <label class="form-label">Preferred Appointment Date *</label>
            <input type="date" class="form-control" id="appt-datetime"/>
          </div>

          <button class="btn btn-gray btn-sm mt-8" onclick="goPatientStep(4)">Back to Medical History</button>
        </div>

        <div>
          <div class="booking-summary">
            <h4>Booking Summary</h4>
            <div class="summary-patient" id="sum-patient-name">Patient Name</div>
            <div class="summary-phone" id="sum-patient-phone">+63 —</div>
            <div class="summary-divider"></div>
            <div class="summary-items" id="summary-items"></div>
            <div class="summary-empty" id="summary-empty">No services selected yet</div>
            <div class="summary-divider"></div>
            <div class="summary-total">
              <span>Total</span>
              <span id="summary-total">₱0</span>
            </div>
            <button class="btn-confirm-booking" onclick="openBookingModal()">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Confirm Booking
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="patient-screen" id="pstep-6">
      <div class="queue-wrap">

        <div id="queue-state-pending">
          <div class="pending-review-card">
            <div class="pending-spinner-wrap">
              <div class="pending-spinner-ring"></div>
              <img src="logo/logo.jpg" alt="<?= htmlspecialchars($siteName) ?>" class="pending-logo"/>
            </div>
            <h3>Under Medical Review</h3>
            <p>Your appointment has been submitted and is being reviewed by the clinic staff. You will receive an SMS once your queue number is confirmed.</p>
            <div class="pending-appt-summary">
              <div class="pas-row"><span>Patient</span><strong id="pend-patient-name">—</strong></div>
              <div class="pas-row"><span>Services</span><strong id="pend-services">—</strong></div>
              <div class="pas-row"><span>Date</span><strong id="pend-date">—</strong></div>
              <div class="pas-row"><span>Status</span><strong><span class="pend-status-chip">Pending Review</span></strong></div>
            </div>
            <div class="sms-note" style="margin-top:22px;">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              <span>We'll send an SMS to <strong id="sms-phone-display">your number</strong> once your appointment is approved or if more information is needed.</span>
            </div>
          </div>
        </div>

        <div id="queue-state-approved" style="display:none;">
          <div class="queue-card">
            <div class="queue-header">
              <div class="queue-live-pill">
                <div class="live-dot" style="background:#4ade80;"></div>
                Live
                <span class="queue-live-sub">· Updates every few seconds</span>
              </div>
              <div class="queue-label" style="margin-top:28px;">Your Queue Number</div>
              <div class="queue-number"><sup>#</sup><span id="my-queue-num">—</span></div>
              <div class="queue-type-badge" id="my-queue-type">—</div>
            </div>
            <div class="queue-body">
              <div class="queue-stats">
                <div class="queue-stat">
                  <div class="val" id="ahead-count">—</div>
                  <div class="lbl">Ahead of you</div>
                </div>
                <div class="queue-stat">
                  <div class="val" id="total-today">—</div>
                  <div class="lbl">Total today</div>
                </div>
              </div>
              <div class="serving-row">
                <div class="serving-card now-serving">
                  <div class="sc-lbl">Now Serving</div>
                  <div class="sc-val" id="now-serving-info">—</div>
                  <div class="sc-lbl" style="margin-top:6px;" id="now-serving-service">—</div>
                </div>
                <div class="serving-card">
                  <div class="sc-lbl">Up Next</div>
                  <div class="sc-val" id="up-next-val">—</div>
                </div>
              </div>
              <div class="sms-note">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span>You'll receive an SMS when you're being called. No need to wait inside the clinic.</span>
              </div>
              <div style="display:flex;gap:12px;margin-top:18px;">
                <button class="btn btn-danger btn-sm" style="margin-left:auto;" onclick="cancelQueue()">Cancel queue</button>
              </div>
            </div>
          </div>

          <div class="appt-details-card">
            <div class="appt-details-title">Appointment Details</div>
            <table class="appt-table">
              <tr><td>Patient</td><td id="appt-det-patient">—</td></tr>
              <tr><td>Services</td><td id="appt-det-services">—</td></tr>
              <tr><td>Date</td><td id="appt-det-date">—</td></tr>
              <tr><td>Queue #</td><td id="appt-det-queue" class="appt-queue-num">—</td></tr>
              <tr><td>Status</td><td><span class="appt-approved-chip">✓ Approved</span></td></tr>
            </table>
          </div>
        </div>

        <div id="queue-state-rejected" style="display:none;">
          <div class="pending-review-card" style="border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.06);">
            <div class="pending-spinner-wrap" style="border-color:rgba(239,68,68,.25);background:rgba(239,68,68,.12);">
              <svg width="28" height="28" fill="none" stroke="#fca5a5" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <h3 style="color:#fca5a5;">Appointment Not Approved</h3>
            <p>Your appointment request was not approved based on your medical record review. Please contact the clinic directly for more information.</p>
            <div style="display:flex;gap:12px;margin-top:24px;flex-wrap:wrap;justify-content:center;">
              <button class="btn btn-gray btn-sm" onclick="goPatientStep(0)">← Return to Home</button>
              <button class="btn btn-primary btn-sm" onclick="goPatientStep(1)">Try Again</button>
            </div>
          </div>
        </div>

      </div>
    </div>

    <div class="patient-screen" id="pstep-7">
      <div class="history-wrap">

        <div class="history-page-header">
          <div>
            <h2>My Appointments</h2>
            <p>All bookings linked to <strong id="hist-mobile-display">your number</strong>.</p>
          </div>
          <button class="btn btn-primary btn-sm" onclick="goPatientStep(5)">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            New Booking
          </button>
        </div>

        <div id="hist-loading" class="hist-loading-wrap">
          <div class="hist-spinner"></div>
          <span>Loading your appointments…</span>
        </div>

        <div id="hist-empty" class="hist-empty-wrap" style="display:none;">
          <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:#94a3b8;margin-bottom:16px;">
            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>
          </svg>
          <p>No appointments found.</p>
          <button class="btn btn-primary btn-sm" onclick="goPatientStep(5)">Book your first appointment</button>
        </div>

        <div id="hist-list" class="hist-list" style="display:none;"></div>

      </div>
    </div>

  </div>
</main>

<div class="toast-container" id="toast-container"></div>

<div id="dd-backdrop" style="position:fixed;inset:0;z-index:998;display:none;" onclick="closeDropdown()"></div>

<script src="js/patient.js"></script>
</body>
</html>
