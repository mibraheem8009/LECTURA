<?php
require_once __DIR__ . '/../../includes/layout.php';
require_viewer();
$sem = current_semester();
$sid = (int) $sem['semester_id'];
$sts = section_terms($sid);
$mode = ($_GET['mode'] ?? 'rooms') === 'sections' ? 'sections' : 'rooms';
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$wk = week_dates($date);
$day = $_GET['day'] ?? 'week';
$days = $day === 'week' ? [1, 2, 3, 4, 5, 6] : [max(1, min(6, (int) $day))];
$rtype = in_array($_GET['rtype'] ?? '', ['classroom', 'lab', 'hall'], true) ? $_GET['rtype'] : '';
$st = isset($_GET['st']) ? (int) $_GET['st'] : (int) ($sts[0]['section_term_id'] ?? 0);
$count = 0;
foreach ($sts as $x)
  if ($x['section_term_id'] == $st)
    $count = (int) $x['student_count'];

$rooms = qa('SELECT * FROM rooms WHERE is_active=1 AND allow_classes=1' . ($rtype ? ' AND room_type=?' : '') . ' ORDER BY room_type, LENGTH(room_name), room_name', $rtype ? [$rtype] : []);
$B = qa("SELECT b.booking_id, b.room_id, b.booking_date, b.start_time, b.end_time, c.course_code, GROUP_CONCAT(bs.section_term_id) AS sts
         FROM bookings b JOIN courses c ON c.course_id=b.course_id JOIN booking_sections bs ON bs.booking_id=b.booking_id
         WHERE b.status='scheduled' AND b.semester_id=? AND b.booking_date BETWEEN ? AND ? GROUP BY b.booking_id, c.course_id", [$sid, $wk[1], $wk[6]]);
$susp = suspended_map($sid, $wk[1], $wk[6]);
$cells = [];
foreach ($days as $d) {
  $dt = $wk[$d];
  foreach (slots() as $i => [$ss, $se]) {
    $busy = [];
    $secBusy = null;
    foreach ($B as $b)
      if ($b['booking_date'] === $dt && t($b['start_time']) < $se && t($b['end_time']) > $ss) {
        if ($b['room_id'])
          $busy[$b['room_id']] = 1;
        if ($mode === 'sections' && in_array((string) $st, explode(',', $b['sts']), true))
          $secBusy = $b['course_code'];
      }
    if ($mode === 'sections' && $secBusy) {
      $cells[$d][$i][] = '<div class="busy">Section busy · ' . e($secBusy) . '</div>';
      continue;
    }
    $chips = '';
    foreach ($rooms as $r) {
      if (isset($busy[$r['room_id']]))
        continue;
      if ($mode === 'sections' && $r['capacity'] < $count)
        continue;
      $chips .= '<span class="chip' . ($r['room_type'] === 'lab' ? ' lab' : '') . '" title="' . e($r['room_name'] . ' · ' . DAYS[$d] . ' · Slot ' . $i) . '">' . e($r['room_name']) . '</span>';
    }
    $cells[$d][$i][] = $chips ? '<div class="chips">' . $chips . '</div>' : '<div class="busy">No free room</div>';
  }
}
page_start('Availability · week of ' . date('M j', strtotime($wk[1])), 'availability'); ?>
<form class="row" method="get">
  <label>Filter by<select name="mode" data-auto>
      <option value="rooms" <?= $mode === 'rooms' ? 'selected' : '' ?>>Rooms</option>
      <option value="sections" <?= $mode === 'sections' ? 'selected' : '' ?>>Sections</option>
    </select></label>
  <?php if ($mode === 'sections'): ?><label>Section<select name="st" data-auto><?php foreach ($sts as $x): ?>
          <option value="<?= $x['section_term_id'] ?>" <?= $x['section_term_id'] == $st ? 'selected' : '' ?>>
            <?= e($x['label']) ?> (<?= $x['student_count'] ?> students)</option><?php endforeach; ?>
      </select></label>
  <?php else: ?><label>Room type<select name="rtype" data-auto>
        <option value="">All</option>
        <?php foreach (['classroom' => 'Classrooms', 'lab' => 'Labs', 'hall' => 'Halls'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= $rtype === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
      </select></label><?php endif; ?>
  <label>Week of<input type="date" name="date" value="<?= e($date) ?>" data-auto></label>
  <label>Days<select name="day" data-auto>
      <option value="week">Whole week</option>
      <?php for ($i = 1; $i <= 6; $i++): ?>
        <option value="<?= $i ?>" <?= (string) $day === (string) $i ? 'selected' : '' ?>><?= DAYS[$i] ?></option>
      <?php endfor; ?>
    </select></label>
</form>
<p class="busy">
  <?= $mode === 'sections' ? 'Slots where the section has no class, with the rooms free in that slot (big enough for the section).' : 'Rooms with no booking in each slot.' ?>
  Based on scheduled bookings only.</p>
<?= slot_grid($cells, $days, $wk, $susp) ?>
<?php page_end();