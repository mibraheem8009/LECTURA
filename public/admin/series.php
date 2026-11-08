<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/booking_service.php';
require_role(['admin']);
csrf_check();
$sem = current_semester();
$sid = (int) $sem['semester_id'];
$res = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $mode = $_POST['delivery_mode'] === 'online' ? 'online' : 'physical';
  $res = series_create(
    [
      'semester_id' => $sid,
      'course_id' => (int) $_POST['course_id'],
      'teacher_id' => (int) $_POST['teacher_id'],
      'room_id' => $mode === 'physical' ? (int) $_POST['room_id'] : null,
      'session_type' => $_POST['session_type'] === 'lab' ? 'lab' : 'theory',
      'delivery_mode' => $mode,
      'repeat_day' => (int) $_POST['repeat_day'],
      'start_time' => $_POST['start_time'],
      'end_time' => $_POST['end_time'],
      'start_date' => $_POST['start_date'],
      'end_date' => $_POST['end_date'],
      'created_by' => uid()
    ],
    array_map('intval', $_POST['sts'] ?? [])
  );
}
$courses = qa('SELECT * FROM courses ORDER BY course_code');
$teachers = qa("SELECT user_id, full_name FROM users WHERE role='teacher' ORDER BY full_name");
$rooms = qa('SELECT * FROM rooms WHERE is_active=1 AND allow_classes=1 ORDER BY room_type, LENGTH(room_name), room_name');
$sts = section_terms($sid);
page_start('New Weekly Class', 'series'); ?>
<?php if ($res && !$res['ok']): ?>
  <div class="flash err">Not created:<ul><?php foreach ($res['errors'] as $m)
    echo '<li>' . e($m) . '</li>'; ?></ul>
  </div>
<?php elseif ($res): ?>
  <div class="flash ok">Created <?= (int) $res['created'] ?> meetings.<?php if ($res['warnings']): ?>
      <ul><?php foreach ($res['warnings'] as $m)
        echo '<li>' . e($m) . '</li>'; ?></ul><?php endif; ?>
  </div><?php endif; ?>
<form method="post" class="card form"><?= csrf_field() ?>
  <p class="busy">Creates the regular timetable entry (the template) and every dated meeting. The teacher must have a
    course allocation for each section (add under Data Tables → course_allocations). Select several sections for a
    shared class.</p>
  <label>Course<select name="course_id"><?php foreach ($courses as $c): ?>
        <option value="<?= $c['course_id'] ?>"><?= e($c['course_code'] . ' · ' . $c['course_title']) ?></option>
      <?php endforeach; ?>
    </select></label>
  <label>Teacher<select name="teacher_id"><?php foreach ($teachers as $c): ?>
        <option value="<?= $c['user_id'] ?>"><?= e($c['full_name']) ?></option><?php endforeach; ?>
    </select></label>
  <label>Type<select name="session_type">
      <option value="theory">Theory</option>
      <option value="lab">Lab</option>
    </select></label>
  <label>Delivery<select name="delivery_mode">
      <option value="physical">Physical</option>
      <option value="online">Online</option>
    </select></label>
  <label>Room<select name="room_id"><?php foreach ($rooms as $c): ?>
        <option value="<?= $c['room_id'] ?>"><?= e($c['room_name'] . ' (' . $c['capacity'] . ')') ?></option>
      <?php endforeach; ?>
    </select></label>
  <label>Weekday<select
      name="repeat_day"><?php for ($i = 1; $i <= 6; $i++)
        echo "<option value=\"$i\">" . DAYS[$i] . '</option>'; ?></select></label>
  <label>Start time<input type="time" name="start_time" value="08:15" required></label><label>End time<input type="time"
      name="end_time" value="10:15" required></label>
  <label>First date<input type="date" name="start_date" value="<?= e($sem['start_date']) ?>"
      required></label><label>Last date<input type="date" name="end_date" value="<?= e($sem['teaching_end_date']) ?>"
      required></label>
  <label>Sections<select name="sts[]" multiple size="6" required><?php foreach ($sts as $s): ?>
        <option value="<?= $s['section_term_id'] ?>"><?= e($s['label'] . ' · Sem ' . $s['semester_number']) ?></option>
      <?php endforeach; ?>
    </select></label>
  <button class="btn">Create</button>
</form>
<?php page_end();