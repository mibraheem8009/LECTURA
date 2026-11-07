<?php
require_once __DIR__ . '/db.php';

define('SEC_LABEL', "CONCAT(s.program,'-',s.batch,'-',s.section_name,IF(s.study_track='self_support',' (SS)',''))");
const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

/* ---------- tiny DB helpers ---------- */
function qa(string $sql, array $p = []): array
{
  $s = db()->prepare($sql);
  $s->execute($p);
  return $s->columnCount() ? $s->fetchAll() : [];
}
function qx(string $sql, array $p = []): int
{
  $s = db()->prepare($sql);
  $s->execute($p);
  return $s->rowCount();
}
function ins(string $t, array $d): int
{
  $c = array_keys($d);
  qx("INSERT INTO $t (" . implode(',', $c) . ') VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')', array_values($d));
  return (int) db()->lastInsertId();
}

/* ---------- time helpers ---------- */
function t($x): string
{
  return substr((string) $x, 0, 5);
}
function to_min($x): int
{
  [$h, $m] = explode(':', t($x));
  return $h * 60 + $m;
}
function add_min($x, int $m): string
{
  $v = to_min($x) + $m;
  return sprintf('%02d:%02d', intdiv($v, 60), $v % 60);
}
function slots(): array
{
  return cfg('slots');
}
function slot_index(string $start): int
{
  foreach (slots() as $i => [$a, $b])
    if (t($start) >= $a && t($start) < $b)
      return $i;
  return array_key_last(slots());
}
function week_dates(string $date): array
{            // [1=>Monday date ... 6=>Saturday date]
  $d = new DateTime($date);
  $d->modify('-' . ((int) $d->format('N') - 1) . ' days');
  $o = [];
  for ($i = 1; $i <= 6; $i++) {
    $o[$i] = $d->format('Y-m-d');
    $d->modify('+1 day');
  }
  return $o;
}
function fmt_range(string $a, string $b): string
{
  $x = new DateTime($a);
  $y = new DateTime($b);
  if ($a === $b)
    return $x->format('M j, Y');
  if ($x->format('Y-m') === $y->format('Y-m'))
    return $x->format('M j') . ' – ' . $y->format('j, Y');
  return $x->format('M j, Y') . ' – ' . $y->format('M j, Y');
}
function day_range(string $a, string $b): string
{
  $x = date('l', strtotime($a));
  $y = date('l', strtotime($b));
  return $x === $y ? $x : "$x – $y";
}

/* ---------- semester / sections ---------- */
function current_semester(): array
{
  $r = qa('SELECT * FROM semesters WHERE CURDATE() BETWEEN start_date AND end_date LIMIT 1')[0]
    ?? qa('SELECT * FROM semesters ORDER BY start_date DESC LIMIT 1')[0] ?? null;
  if (!$r)
    exit('No semester configured. Run: php database/seed.php');
  return $r;
}
function section_terms(int $sem): array
{
  return qa('SELECT st.section_term_id, st.student_count, st.semester_number, ' . SEC_LABEL . ' AS label
             FROM section_terms st JOIN sections s ON s.section_id=st.section_id
             WHERE st.semester_id=? ORDER BY st.semester_number, s.study_track, s.section_name', [$sem]);
}

/* ---------- calendar suspension ---------- */
function cal_suspended(int $sem, string $date): ?string
{
  return qa('SELECT title FROM calendar_events WHERE semester_id=? AND suspends_regular_classes=1 AND ? BETWEEN start_date AND end_date LIMIT 1', [$sem, $date])[0]['title'] ?? null;
}
function suspended_map(int $sem, string $from, string $to): array
{
  $m = [];
  foreach (qa('SELECT title,start_date,end_date FROM calendar_events WHERE semester_id=? AND suspends_regular_classes=1 AND start_date<=? AND end_date>=?', [$sem, $to, $from]) as $ev) {
    $d = new DateTime(max($from, $ev['start_date']));
    $end = new DateTime(min($to, $ev['end_date']));
    for (; $d <= $end; $d->modify('+1 day'))
      $m[$d->format('Y-m-d')] = $ev['title'];
  }
  return $m;
}

/* ---------- reading bookings (most up-to-date schedule) ---------- */
function fetch_bookings(string $where, array $p = []): array
{
  return qa("SELECT b.*, c.course_code, c.course_title, u.full_name AS teacher_name, r.room_name, r.capacity,
      GROUP_CONCAT(DISTINCT " . SEC_LABEL . " ORDER BY s.section_id SEPARATOR ', ') AS sections_label,
      GROUP_CONCAT(DISTINCT bs.section_term_id) AS st_ids
    FROM bookings b
    JOIN courses c ON c.course_id=b.course_id
    JOIN users u ON u.user_id=b.teacher_id
    LEFT JOIN rooms r ON r.room_id=b.room_id
    JOIN booking_sections bs ON bs.booking_id=b.booking_id
    JOIN section_terms st ON st.section_term_id=bs.section_term_id
    JOIN sections s ON s.section_id=st.section_id
    WHERE $where
    GROUP BY b.booking_id, c.course_id, u.user_id, r.room_id
    ORDER BY b.booking_date, b.start_time", $p);
}

/* ---------- rendering ---------- */
function booking_card(array $r, string $extra = ''): string
{
  $cls = 'cls ' . ($r['session_type'] === 'lab' ? 'lab' : 'theory') . ($r['status'] === 'cancelled' ? ' cancelled' : '');
  $b = '';
  if ($r['status'] === 'cancelled')
    $b .= '<span class="b red">Cancelled</span>';
  elseif ($r['booking_type'] === 'merged')
    $b .= '<span class="b purple">Merged</span>';
  elseif ($r['booking_type'] === 'extra')
    $b .= '<span class="b amber">Extra</span>';
  elseif (!empty($r['is_modified']))
    $b .= '<span class="b blue">Changed</span>';
  if ($r['delivery_mode'] === 'online')
    $b .= '<span class="b gray">Online</span>';
  if ($r['session_type'] === 'lab')
    $b .= '<span class="b green">Lab</span>';
  $where = $r['delivery_mode'] === 'online' ? 'Online' : e($r['room_name']);
  $note = !empty($r['change_note']) && $r['status'] === 'cancelled' ? '<div class="cm">' . e($r['change_note']) . '</div>' : '';
  return '<div class="' . $cls . '"><div class="cn">' . e($r['course_code']) . ' · ' . e($r['course_title']) . ' ' . $b . '</div>'
    . '<div class="cm">' . t($r['start_time']) . '–' . t($r['end_time']) . ' · ' . $where . '</div>'
    . '<div class="cm">' . e($r['teacher_name']) . ' — ' . e($r['sections_label']) . '</div>' . $note . $extra . '</div>';
}
function place(array &$cells, int $dow, string $start, string $end, string $html): void
{
  $first = slot_index($start);
  foreach (slots() as $i => [$a, $b]) {
    if ($i === $first)
      $cells[$dow][$i][] = $html;
    elseif ($a >= t($start) && $a < t($end))
      $cells[$dow][$i][] = '<div class="cont">↳ class continues</div>';
  }
}
function slot_grid(array $cells, array $days = [1, 2, 3, 4, 5, 6], array $dates = [], array $susp = []): string
{
  $h = '<div class="tbl-wrap"><table class="grid"><thead><tr><th>Day</th>';
  foreach (slots() as $i => [$a, $b])
    $h .= "<th>Slot $i<small>$a – $b</small></th>";
  $h .= '</tr></thead><tbody>';
  foreach ($days as $d) {
    $dt = $dates[$d] ?? null;
    $h .= '<tr><th class="day">' . DAYS[$d] . ($dt ? '<small>' . date('M j', strtotime($dt)) . '</small>' : '') . '</th>';
    if ($dt && isset($susp[$dt])) {
      $h .= '<td class="brk" colspan="4">' . e($susp[$dt]) . ' — regular classes suspended</td></tr>';
      continue;
    }
    foreach (slots() as $i => $_) {
      if ($d === 5 && $i === cfg('friday_skip_slot')) {
        $h .= '<td class="brk">Jummah Break</td>';
        continue;
      }
      $h .= '<td>' . implode('', $cells[$d][$i] ?? []) . '</td>';
    }
    $h .= '</tr>';
  }
  return $h . '</tbody></table></div>';
}