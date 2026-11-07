<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$err = '';
$dom = cfg('student_email_domain');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $as = $_POST['as'] ?? 'login';

  if ($as === 'student') {
    $mail = strtolower(trim($_POST['student_email'] ?? ''));
    if ($dom && !preg_match('/@' . preg_quote($dom, '/') . '$/', $mail)) {
      $err = "Please enter your @$dom email address to view the dashboard.";
    } else {
      session_regenerate_id(true);
      $_SESSION = ['role' => 'student', 'name' => 'Student'];
      redirect('student/overview.php');
    }
  } else {
    // ONE login for admins and teachers: the role comes from the database
    $email = trim($_POST['email'] ?? '');
    $u = qa("SELECT * FROM users WHERE email=? AND is_active=1 AND role IN ('admin','teacher') LIMIT 1", [$email])[0] ?? null;
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
      session_regenerate_id(true);
      $_SESSION = ['role' => $u['role'], 'user_id' => (int) $u['user_id'], 'name' => $u['full_name']];
      redirect($u['role'] === 'admin' ? 'admin/index.php' : 'teacher/overview.php');
    }
    usleep(400000);                       // slows down password guessing
    $err = 'Invalid email or password.';
  }
}
?><!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lectura · Sign in</title>
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>

<body class="auth">

  <section class="auth-left">
    <div class="auth-brand">
      <img class="auth-logo" src="<?= url('assets/img/logo.jpg') ?>" alt="Lectura">
      <div class="auth-name">LECTURA</div>
      <div class="auth-tag">University timetable &amp; room reservation</div>
    </div>

    <form method="post" class="auth-student">
      <?= csrf_field() ?><input type="hidden" name="as" value="student">
      <div class="quote-mark">“</div>
      <h2>Student</h2>
      <p>View timetables, free rooms and the semester calendar. No account needed.</p>
      <?php if ($dom): ?>
        <input class="auth-input dark" type="email" name="student_email" placeholder="University email (@<?= e($dom) ?>)"
          required>
      <?php endif; ?>
      <button class="auth-btn light">Dashboard</button>
    </form>
  </section>

  <section class="auth-right">
    <form method="post" class="auth-form" autocomplete="on">
      <?= csrf_field() ?><input type="hidden" name="as" value="login">
      <h1>Sign in</h1>
      <p class="auth-sub">Please enter your email and password to sign in</p>
      <?php if ($err): ?>
        <div class="flash err"><?= e($err) ?></div><?php endif; ?>

      <label class="auth-label">Email
        <input class="auth-input" type="email" name="email" placeholder="Email" required autofocus></label>

      <label class="auth-label">Password
        <span class="pw">
          <input class="auth-input" type="password" name="password" id="pw" placeholder="Password" required>
          <button type="button" class="eye" id="eye" aria-label="Show or hide password">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </button>
        </span></label>

      <button class="auth-btn dark">Sign In</button>
      <p class="auth-hint">Teachers and administrators only. Students use the Dashboard button.</p>
    </form>
  </section>

  <script>
    document.getElementById('eye').addEventListener('click', function () {
      var p = document.getElementById('pw'); p.type = p.type === 'password' ? 'text' : 'password';
    });
  </script>
</body>

</html>