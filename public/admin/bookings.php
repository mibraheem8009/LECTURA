<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/booking_service.php';
require_role(['admin']);
csrf_check();
$sem = current_semester();
$sid = (int) $sem['semester_id'];
if (isset($_POST['cancel'])) {
  bk_cancel((int) $_POST['id'], $_POST['scope'] === 'future' ? 'future' : 'this', 'Cancelled by admin');
  flash('Cancelled.');
  redirect('admin/bookings.php');
}
$from = $_GET['from'] ?? date('Y-m-d');
$to = $_GET['to'] ?? date('Y-m-d', strtotime('+7 days'));
$tid = (int) ($_GET['teacher'] ?? 0);
$stf = (int) ($_GET['st'] ?? 0);
$w = 'b.semester_id=? AND b.booking_date BETWEEN ? AND ?';
$p = [$sid, $from, $to];
if ($tid) {
  $w .= ' AND b.teacher_id=?';
  $p[] = $tid;
}
if ($stf) {
  $w .= ' AND EXISTS (SELECT 1 FROM booking_sections x WHERE x.booking_id=b.booking_id AND x.section_term_id=?)';
  $p[] = $stf;
}
$rows = fetch_bookings($w, $p);
$teachers = qa("SELECT user_id, full_name FROM users WHERE role='teacher' ORDER BY full_name");
$sts = section_terms($sid);
page_start('Bookings', 'bookings'); ?>
<form class="row" method="get">
  <label>From<input type="date" name="from" value="<?= e($from) ?>"></label><label>To<input type="date" name="to"
      value="<?= e($to) ?>"></label>
  <label>Teacher<select name="teacher">
      <option value="0">All</option><?php foreach ($teachers as $t): ?>
        <option value="<?= $t['user_id'] ?>" <?= $t['user_id'] == $tid ? 'selected' : '' ?>><?= e($t['full_name']) ?>
        </option><?php endforeach; ?>
    </select></label>
  <label>Section<select name="st">
      <option value="0">All</option><?php foreach ($sts as $s): ?>
        <option value="<?= $s['section_term_id'] ?>" <?= $s['section_term_id'] == $stf ? 'selected' : '' ?>>
          <?= e($s['label']) ?></option><?php endforeach; ?>
    </select></label>
  <button class="btn">Filter</button>
</form>
<div class="tbl-wrap">
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Time</th>
        <th>Course</th>
        <th>Teacher</th>
        <th>Room</th>
        <th>Sections</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['booking_date']) ?></td>
          <td><?= t($r['start_time']) . '–' . t($r['end_time']) ?></td>
          <td><?= e($r['course_code']) ?>   <?= $r['session_type'] === 'lab' ? '(Lab)' : '' ?></td>
          <td><?= e($r['teacher_name']) ?></td>
          <td><?= $r['delivery_mode'] === 'online' ? 'Online' : e($r['room_name']) ?></td>
          <td><?= e($r['sections_label']) ?></td>
          <td><?= e($r['status']) ?>   <?= e($r['booking_type'] !== 'regular' ? '· ' . $r['booking_type'] : '') ?></td>
          <td><?php if ($r['status'] === 'scheduled'): ?><a class="btn sm"
                href="<?= url('teacher/edit_booking.php?id=' . $r['booking_id']) ?>">Edit</a>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id"
                  value="<?= $r['booking_id'] ?>"><input type="hidden" name="scope" value="this"><button
                  class="btn sm ghost" name="cancel" value="1"
                  onclick="return confirm('Cancel this meeting?')">Cancel</button></form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach;
      if (!$rows)
        echo '<tr><td colspan="8" class="busy">No bookings in this range.</td></tr>'; ?>
    </tbody>
  </table>
</div>
<?php page_end();