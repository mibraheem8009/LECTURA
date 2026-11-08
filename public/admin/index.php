<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/booking_service.php';
require_role(['admin']);
csrf_check();
$sem = current_semester();
$sid = (int) $sem['semester_id'];
if (isset($_POST['reconcile'])) {
  $r = reconcile_suspensions($sid);
  flash("Calendar reconciled: {$r['cancelled']} cancelled, {$r['restored']} restored, {$r['kept']} left cancelled (would clash).");
  redirect('admin/index.php');
}
$n = fn($sql, $p = []) => qa($sql, $p)[0]['n'];
page_start('Admin Home', 'admin'); ?>
<div class="stats">
  <div class="stat">
    <b><?= $n("SELECT COUNT(*) n FROM bookings WHERE semester_id=? AND status='scheduled'", [$sid]) ?></b><span>Scheduled
      meetings</span></div>
  <div class="stat">
    <b><?= $n("SELECT COUNT(*) n FROM bookings WHERE semester_id=? AND status='cancelled'", [$sid]) ?></b><span>Cancelled
      meetings</span></div>
  <div class="stat"><b><?= $n("SELECT COUNT(*) n FROM users WHERE role='teacher' AND is_active=1") ?></b><span>Active
      teachers</span></div>
  <div class="stat"><b><?= $n("SELECT COUNT(*) n FROM slot_requests WHERE status='pending'") ?></b><span>Pending slot
      requests</span></div>
</div>
<div class="card">
  <h2>Admin tools</h2>
  <p>Full edit access: <a href="<?= url('admin/crud.php') ?>">Data tables</a> (semesters, sections, users, courses,
    allocations, rooms, calendar, weeks, announcements),
    <a href="<?= url('admin/bookings.php') ?>">Bookings</a> and <a href="<?= url('admin/series.php') ?>">New weekly
      class</a>.
  </p>
  <form method="post"><?= csrf_field() ?><button class="btn" name="reconcile" value="1">Re-apply calendar suspensions to
      bookings</button></form>
</div>
<?php page_end();