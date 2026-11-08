<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/booking_service.php';
require_role(['admin']);
csrf_check();
function f($l, $t = 'text', $o = null, $n = false)
{
  return ['l' => $l, 't' => $t, 'o' => $o, 'n' => $n];
}
$SEM = 'SELECT semester_id id, semester_name label FROM semesters';
$SEC = "SELECT st.section_term_id id, CONCAT(" . SEC_LABEL . ",' · Sem ',st.semester_number) label FROM section_terms st JOIN sections s ON s.section_id=st.section_id";
$T = [
  'semesters' => ['semester_id', ['semester_name' => f('Name'), 'start_date' => f('Start', 'date'), 'teaching_end_date' => f('Teaching ends', 'date'), 'end_date' => f('End', 'date')]],
  'sections' => ['section_id', ['program' => f('Program'), 'batch' => f('Batch'), 'section_name' => f('Section'), 'study_track' => f('Track', 'enum', ['regular', 'self_support'])]],
  'section_terms' => ['section_term_id', ['section_id' => f('Section', 'fk', "SELECT section_id id, CONCAT(program,'-',batch,'-',section_name,' ',study_track) label FROM sections"), 'semester_id' => f('Semester', 'fk', $SEM), 'semester_number' => f('Sem #', 'number'), 'student_count' => f('Students', 'number'), 'max_minutes_per_day' => f('Max min/day', 'number', null, true)]],
  'users' => ['user_id', ['full_name' => f('Name'), 'email' => f('Email', 'email'), 'role' => f('Role', 'enum', ['teacher', 'admin']), 'is_active' => f('Active', 'bool'), 'password_hash' => f('Password', 'password')]],
  'courses' => ['course_id', ['course_code' => f('Code'), 'course_title' => f('Title')]],
  'course_allocations' => ['allocation_id', ['course_id' => f('Course', 'fk', 'SELECT course_id id, course_code label FROM courses'), 'teacher_id' => f('Teacher', 'fk', "SELECT user_id id, full_name label FROM users WHERE role='teacher' ORDER BY full_name"), 'section_term_id' => f('Section', 'fk', $SEC), 'session_type' => f('Type', 'enum', ['theory', 'lab']), 'sessions_per_week' => f('Per week', 'number'), 'duration_minutes' => f('Minutes', 'number')]],
  'rooms' => ['room_id', ['room_name' => f('Name'), 'room_type' => f('Type', 'enum', ['classroom', 'lab', 'hall']), 'capacity' => f('Capacity', 'number'), 'allow_classes' => f('Classes allowed', 'bool'), 'is_active' => f('Active', 'bool')]],
  'calendar_events' => ['event_id', ['semester_id' => f('Semester', 'fk', $SEM), 'title' => f('Title'), 'description' => f('Description', 'textarea', null, true), 'event_type' => f('Type', 'enum', ['classes', 'exam', 'holiday', 'deadline', 'result', 'other']), 'start_date' => f('Start', 'date'), 'end_date' => f('End', 'date'), 'is_tentative' => f('Tentative', 'bool'), 'suspends_regular_classes' => f('Suspends classes', 'bool')]],
  'calendar_weeks' => ['calendar_week_id', ['semester_id' => f('Semester', 'fk', $SEM), 'phase' => f('Phase', 'enum', ['pre_mid', 'post_mid']), 'week_number' => f('Week #', 'number'), 'start_date' => f('Start', 'date'), 'end_date' => f('End', 'date'), 'planned_lectures' => f('Lectures', 'number', null, true), 'notes' => f('Notes', 'text', null, true)]],
  'announcements' => ['ann_id', ['semester_id' => f('Semester', 'fk', $SEM), 'section_term_id' => f('Section (blank = all)', 'fk', $SEC, true), 'kind' => f('Kind', 'enum', ['quiz', 'info', 'notice']), 'title' => f('Title'), 'body' => f('Body', 'textarea', null, true), 'event_date' => f('Date', 'date', null, true)]],
];
$t = $_GET['t'] ?? $_POST['t'] ?? 'rooms';
if (!isset($T[$t]))
  $t = 'rooms';
[$pk, $cols] = $T[$t];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (isset($_POST['delete'])) {
      qx("DELETE FROM $t WHERE $pk=?", [(int) $_POST['id']]);
      flash('Deleted.');
    } else {
      $id = (int) ($_POST['id'] ?? 0);
      $d = [];
      foreach ($cols as $n => $c) {
        $v = $_POST[$n] ?? null;
        if ($c['t'] === 'bool') {
          $d[$n] = $v ? 1 : 0;
          continue;
        }
        if ($c['t'] === 'password') {
          if ($v !== null && $v !== '')
            $d[$n] = password_hash($v, PASSWORD_DEFAULT);
          continue;
        }
        $d[$n] = (($v === '' || $v === null) && $c['n']) ? null : $v;
      }
      if ($t === 'calendar_events') {
        $d['updated_by'] = uid();
        if (!$id)
          $d['created_by'] = uid();
      }
      if ($t === 'announcements' && !$id)
        $d['created_by'] = uid();
      if ($id)
        qx("UPDATE $t SET " . implode(',', array_map(fn($k) => "$k=?", array_keys($d))) . " WHERE $pk=?", array_merge(array_values($d), [$id]));
      else {
        if ($t === 'users' && empty($d['password_hash']))
          throw new Exception('A password is required for new users.');
        ins($t, $d);
      }
      flash('Saved.');
      if ($t === 'calendar_events') {
        $r = reconcile_suspensions((int) $d['semester_id']);
        flash("Calendar applied to bookings: {$r['cancelled']} cancelled, {$r['restored']} restored.");
      }
    }
  } catch (Throwable $x) {
    flash(str_contains($x->getMessage(), 'foreign key') ? 'Cannot delete: this record is referenced elsewhere. Deactivate it or cancel bookings instead.' : 'Error: ' . $x->getMessage(), 'err');
  }
  redirect("admin/crud.php?t=$t");
}
function field_input(string $n, array $c, $v, bool $new): string
{
  $v = $v ?? '';
  switch ($c['t']) {
    case 'enum':
      $h = "<select name=\"$n\">";
      foreach ($c['o'] as $o)
        $h .= '<option' . ($o == $v ? ' selected' : '') . '>' . e($o) . '</option>';
      return $h . '</select>';
    case 'fk':
      $h = "<select name=\"$n\">" . ($c['n'] ? '<option value="">— none —</option>' : '');
      foreach (qa($c['o']) as $o)
        $h .= '<option value="' . $o['id'] . '"' . ($o['id'] == $v ? ' selected' : '') . '>' . e($o['label']) . '</option>';
      return $h . '</select>';
    case 'bool':
      return "<input type=\"checkbox\" name=\"$n\" value=\"1\"" . (($new ? true : $v) ? ' checked' : '') . ' style="min-width:0">';
    case 'textarea':
      return "<textarea name=\"$n\">" . e($v) . '</textarea>';
    case 'password':
      return "<input type=\"password\" name=\"$n\" placeholder=\"" . ($new ? 'required' : 'leave blank to keep') . "\">";
    default:
      $ty = in_array($c['t'], ['date', 'number', 'email']) ? $c['t'] : 'text';
      return "<input type=\"$ty\" name=\"$n\" value=\"" . e($v) . "\"" . ($c['n'] ? '' : ' required') . '>';
  }
}
$editId = (int) ($_GET['edit'] ?? 0);
$row = $editId ? (qa("SELECT * FROM $t WHERE $pk=?", [$editId])[0] ?? []) : [];
$fk = [];
foreach ($cols as $n => $c)
  if ($c['t'] === 'fk')
    $fk[$n] = array_column(qa($c['o']), 'label', 'id');
$list = qa("SELECT * FROM $t ORDER BY $pk DESC LIMIT 300");
page_start('Data Tables', 'crud'); ?>
<div class="row"><?php foreach (array_keys($T) as $k): ?><a class="btn <?= $k === $t ? '' : 'ghost' ?> sm"
      href="?t=<?= $k ?>"><?= e($k) ?></a><?php endforeach; ?></div>
<form method="post" class="card form">
  <h2><?= $editId ? 'Edit' : 'Add' ?> · <?= e($t) ?></h2><?= csrf_field() ?><input type="hidden" name="t"
    value="<?= e($t) ?>"><input type="hidden" name="id" value="<?= $editId ?>">
  <?php foreach ($cols as $n => $c): ?><label><?= e($c['l']) ?><?= field_input($n, $c, $row[$n] ?? null, !$editId) ?></label><?php endforeach; ?>
  <button class="btn">Save</button> <?php if ($editId): ?><a class="btn ghost"
      href="?t=<?= $t ?>">New</a><?php endif; ?>
</form>
<div class="tbl-wrap">
  <table>
    <thead>
      <tr>
        <th>#</th><?php foreach ($cols as $n => $c)
          if ($c['t'] !== 'password')
            echo '<th>' . e($c['l']) . '</th>'; ?>
        <th></th>
      </tr>
    </thead>
    <tbody>
            <?php foreach ($list as $r): ?>
        <tr>
          <td><?= $r[$pk] ?></td>
                  <?php foreach ($cols as $n => $c) {
                    if ($c['t'] === 'password')
                      continue;
                    $v = $r[$n];
                    echo '<td>' . e($c['t'] === 'fk' ? ($fk[$n][$v] ?? $v) : ($c['t'] === 'bool' ? ($v ? '✔' : '—') : $v)) . '</td>';
                  } ?>
          <td><a class="btn sm" href="?t=<?= $t ?>&edit=<?= $r[$pk] ?>">Edit</a>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="t"
                value="<?= e($t) ?>"><input type="hidden" name="id" value="<?= $r[$pk] ?>"><button class="btn sm ghost"
                name="delete" value="1" onclick="return confirm('Delete this record?')">Delete</button></form>
          </td>
        </tr>
            <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php page_end();