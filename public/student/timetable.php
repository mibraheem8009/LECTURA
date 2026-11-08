<?php
require_once __DIR__ . '/../../includes/layout.php';
require_viewer();
$sem = current_semester();
$sid = (int)$sem['semester_id'];
$sts = section_terms($sid);
$st = isset($_GET['st']) ? (int)$_GET['st'] : (int)($sts[0]['section_term_id'] ?? 0);
$ref = min(max(date('Y-m-d'), $sem['start_date']), $sem['teaching_end_date']);
$rows = qa("SELECT bs.*, c.course_code, c.course_title, u.full_name AS teacher_name, r.room_name,
   (SELECT GROUP_CONCAT(" . SEC_LABEL . " SEPARATOR ', ') FROM series_sections x JOIN section_terms st ON st.section_term_id=x.section_term_id JOIN sections s ON s.section_id=st.section_id WHERE x.series_id=bs.series_id) AS sections_label
   FROM booking_series bs JOIN courses c ON c.course_id=bs.course_id JOIN users u ON u.user_id=bs.teacher_id LEFT JOIN rooms r ON r.room_id=bs.room_id
   WHERE bs.semester_id=? AND ? BETWEEN bs.start_date AND bs.end_date
   AND EXISTS (SELECT 1 FROM series_sections y WHERE y.series_id=bs.series_id AND y.section_term_id=?)", [$sid, $ref, $st]);
$cells = [];
foreach ($rows as $r) {
  $r += ['status' => 'scheduled', 'booking_type' => 'regular', 'is_modified' => 0];
  place($cells, (int)$r['repeat_day'], $r['start_time'], $r['end_time'], booking_card($r));
}
page_start('Regular Timetable · ' . $sem['semester_name'], 'timetable'); ?>
<form class="row" method="get"><label>Section/Class<select name="st" data-auto>
      <?php foreach ($sts as $x): ?><option value="<?= $x['section_term_id'] ?>" <?= $x['section_term_id'] == $st ? 'selected' : '' ?>><?= e($x['label']) ?> · Sem <?= $x['semester_number'] ?></option><?php endforeach; ?></select></label>
  <span class="busy">The permanent weekly timetable. One-off changes appear in <b>Overview</b>.</span>
</form>
<?= slot_grid($cells) ?>
<?php page_end();
