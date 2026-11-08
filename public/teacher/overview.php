<?php
require_once __DIR__ . '/../../includes/layout.php';
require_role(['teacher']);
$sem = current_semester();
$sid = (int) $sem['semester_id'];
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$wk = week_dates($date);
$rows = fetch_bookings('b.semester_id=? AND b.teacher_id=? AND b.booking_date BETWEEN ? AND ?', [$sid, uid(), $wk[1], $wk[6]]);
$cells = [];
foreach ($rows as $r) {
  $extra = $r['status'] === 'scheduled' ? '<a class="btn sm" href="' . url('teacher/edit_booking.php?id=' . $r['booking_id']) . '">Edit</a>' : '';
  place($cells, (int) date('N', strtotime($r['booking_date'])), $r['start_time'], $r['end_time'], booking_card($r, $extra));
}
$prev = date('Y-m-d', strtotime($wk[1] . ' -7 days'));
$next = date('Y-m-d', strtotime($wk[1] . ' +7 days'));
page_start('Teacher Overview', 'overview'); ?>
<div class="row between">
  <a class="btn" href="<?= url('teacher/merge.php') ?>">⇄ Merge Classes</a>
  <div class="row" style="margin:0"><a class="btn ghost" href="?date=<?= $prev ?>">← Prev week</a>
    <form method="get" style="margin:0"><input type="date" name="date" value="<?= e($date) ?>" data-auto></form>
    <a class="btn ghost" href="?date=<?= $next ?>">Next week →</a>
  </div>
</div>
<?= slot_grid($cells, [1, 2, 3, 4, 5, 6], $wk, suspended_map($sid, $wk[1], $wk[6])) ?>
<?php page_end();