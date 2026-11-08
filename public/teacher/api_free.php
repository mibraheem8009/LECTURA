<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/booking_service.php';
header('Content-Type: application/json');
if (!in_array(role(), ['teacher', 'admin'], true)) {
  http_response_code(403);
  echo '{"items":[]}';
  exit;
}

$mode = $_GET['mode'] ?? '';
$date = $_GET['date'] ?? '';
$sts = array_values(array_filter(array_map('intval', explode(',', $_GET['sts'] ?? ''))));
$teacher = role() === 'admin' ? (int) ($_GET['teacher'] ?? 0) : (int) uid();
$type = ($_GET['type'] ?? 'theory') === 'lab' ? 'lab' : 'theory';
$dur = (int) ($_GET['dur'] ?? 120);
$online = !empty($_GET['online']);
$ignore = array_values(array_filter(array_map('intval', explode(',', $_GET['ignore'] ?? ''))));
$out = ['items' => []];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  echo json_encode($out);
  exit;
}
$sem = qa('SELECT * FROM semesters WHERE ? BETWEEN start_date AND teaching_end_date LIMIT 1', [$date])[0] ?? null;
if (!$sem) {
  echo json_encode(['items' => [], 'msg' => 'Date is outside the teaching period']);
  exit;
}
if ($why = cal_suspended((int) $sem['semester_id'], $date)) {
  echo json_encode(['items' => [], 'msg' => "No classes: $why"]);
  exit;
}
$dow = (int) date('N', strtotime($date));
if ($dow === 7) {
  echo json_encode(['items' => [], 'msg' => 'No classes on Sunday']);
  exit;
}
$total = $sts ? (int) qa('SELECT SUM(student_count) n FROM section_terms WHERE section_term_id IN (' . bk_in($sts) . ')')[0]['n'] : 0;

if ($mode === 'slots' && $sts && $teacher) {
  foreach (slots() as $i => [$a, $b]) {
    if ($dow === 5 && $i === cfg('friday_skip_slot'))
      continue;
    $end = add_min($a, $dur);
    $why = '';
    if ($end > cfg('day_end'))
      $why = 'runs too late';
    elseif ($dow === 5 && $a < cfg('slots')[3][1] && $end > cfg('slots')[3][0])
      $why = 'Jummah break';
    elseif (bk_busy('teacher', [$teacher], $date, $a, $end, $ignore))
      $why = 'you have a class';
    elseif (bk_busy('sections', $sts, $date, $a, $end, $ignore))
      $why = 'section has a class';
    $n = ($why || $online) ? null : count(free_rooms($date, $a, $end, $type, $total, $ignore));
    if (!$why && !$online && $n === 0)
      $why = 'no free room';
    $out['items'][] = [
      'value' => $a,
      'disabled' => (bool) $why,
      'label' => "Slot $i · {$a}–{$end}" . ($why ? " — $why" : ($online ? ' ✓ free' : " ✓ $n room(s) free"))
    ];
  }
} elseif ($mode === 'rooms' && $sts) {
  $s = $_GET['start'] ?? '';
  $e = add_min($s, $dur);
  foreach (free_rooms($date, $s, $e, $type, $total, $ignore) as $r)
    $out['items'][] = ['value' => $r['room_id'], 'label' => "{$r['room_name']} (capacity {$r['capacity']})"];
} elseif ($mode === 'occupied') {
  $ss = $_GET['ss'] ?? '';
  $se = $_GET['se'] ?? '';
  foreach (fetch_bookings("b.status='scheduled' AND b.room_id IS NOT NULL AND b.booking_date=? AND b.start_time<? AND b.end_time>? AND b.teacher_id<>?", [$date, $se, $ss, (int) uid()]) as $b)
    $out['items'][] = ['value' => $b['booking_id'], 'label' => "{$b['room_name']} · " . t($b['start_time']) . '–' . t($b['end_time']) . " · {$b['course_code']} ({$b['teacher_name']}) · {$b['sections_label']}"];
}
echo json_encode($out);