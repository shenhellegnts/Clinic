<?php
session_start();
require_once __DIR__ . '/../db.php';

$settings = [];
$settingRows = db_query('SELECT setting_key, setting_value FROM settings');
if ($settingRows) {
    while ($row = $settingRows->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
$clinicName = $settings['clinic_name'] ?? 'M.V. Masangkay Clinic';
$clinicSubtitle = $settings['clinic_subtitle'] ?? 'General Malvar Ave, Santo Tomas, Batangas';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['admin_user'] ?? '');
    $password = $_POST['admin_pass'] ?? '';

    if ($username === '' || $password === '') {
        $loginError = 'Please enter both username and password.';
    } else {
        $user = db_row('SELECT id, username, password_hash, full_name FROM admins WHERE username = ? LIMIT 1', 's', [$username]);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $user['username'];
            $_SESSION['admin_name'] = $user['full_name'];
            header('Location: dashboard.php');
            exit;
        }
        $loginError = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login — M.V. Masangkay Clinic</title>
  <link rel="icon" type="image/jpeg" href="../logo/logo.jpg"/>
  <link rel="stylesheet" href="../css/style.css"/>
  <link rel="stylesheet" href="../css/admin.css"/>
</head>
<body class="admin-glass">

<div class="admin-login-bg">
  <div class="login-card">

    <div class="login-logo">
      <div class="login-logo-icon">
        <img src="../logo/logo.jpg" alt="Logo"/>
      </div>
      <div>
        <div class="login-clinic-name"><?= htmlspecialchars($clinicName) ?></div>
        <div class="login-clinic-sub"><?= htmlspecialchars($clinicSubtitle) ?></div>
      </div>
    </div>

    <span class="login-badge">
      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Admin Portal
    </span>
    <h2>Sign in</h2>
    <p>Authorized clinic staff only.</p>

    <?php if ($loginError): ?>
      <div style="display:flex;background:rgba(248,113,113,.15);border:1px solid rgba(248,113,113,.35);border-radius:12px;padding:12px 16px;font-size:13px;color:#fca5a5;margin-bottom:16px;align-items:center;gap:8px;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
        <span><?= htmlspecialchars($loginError) ?></span>
      </div>
    <?php endif; ?>

    <form method="post" name="loginForm">
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="admin_user" class="form-control" placeholder="Username" autocomplete="username" value="<?= htmlspecialchars($_POST['admin_user'] ?? '') ?>" />
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-password-wrap">
          <input type="password" name="admin_pass" id="admin-pass" class="form-control" placeholder="••••••••" autocomplete="current-password" />
          <button type="button" class="password-toggle" onclick="togglePw(this)" aria-label="Toggle password">
            <svg id="pw-eye" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button class="btn btn-primary btn-block" type="submit">
        Sign in
      </button>
    </form>

    <p class="login-back">Not an admin? <a href="../index.php">Book an appointment here</a></p>
  </div>
</div>

<script>
  function togglePw(btn) {
    const input = document.getElementById('admin-pass');
    const eye = document.getElementById('pw-eye');
    if (input.type === 'password') {
      input.type = 'text';
      eye.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24M1 1l22 22"/>';
    } else {
      input.type = 'password';
      eye.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
  }
</script>
</body>
</html>
