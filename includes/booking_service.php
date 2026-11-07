<?php
require_once __DIR__ . '/helpers.php';

function bk_in(array $ids): string
{
  $ids = array_map('intval', $ids);
  return $ids ? implode(',', $ids) : '0';
}

function bk_load(int $id): ?array
{
  $b = qa('SELECT * FROM bookings WHERE booking_id=?', [$id])[0] ?? null;
  if (!$b) return null;
  $b['section_term_ids'] = array_map('intval', array_column(qa('SELECT section_term_id FROM booking_sections WHERE booking_id=?', [$id]), 'section_term_id'));
  return $b;
}
function bk_series_future(int $sid, string $fromDate): array
{
  $out = [];
  foreach (qa("SELECT booking_id FROM bookings WHERE series_id=? AND booking_date>=? AND status='scheduled' ORDER BY booking_date", [$sid, $fromDate]) as $r) $out[] = bk_load((int)$r['booking_id']);
  return $out;
}

/* ---------- transaction wrapper (READ COMMITTED so we always see the latest committed bookings) ---------- */
function bk_tx(callable $fn): array
{
  $pdo = db();
  try {
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $pdo->beginTransaction();
    $res = $fn($pdo) ?? [];
    if (!empty($res['errors'])) {
      $pdo->rollBack();
      return ['ok' => false] + $res;
    }
    $pdo->commit();
    return ['ok' => true] + $res;
  } catch (Throwable $x) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    return ['ok' => false, 'errors' => ['System error: ' . $x->getMessage()]];
  }
}

/* ---------- locking: serialises concurrent requests for the same teacher / room / sections ---------- */
function bk_lock(PDO $pdo, array $b): void
{
  qa('SELECT user_id FROM users WHERE user_id=? FOR UPDATE', [$b['teacher_id']]);
  if (!empty($b['room_id'])) qa('SELECT room_id FROM rooms WHERE room_id=? FOR UPDATE', [$b['room_id']]);
  $ids = $b['section_term_ids'] ?? [];
  sort($ids);
  if ($ids) qa('SELECT section_term_id FROM section_terms WHERE section_term_id IN (' . bk_in($ids) . ') ORDER BY section_term_id FOR UPDATE');
}

/* ---------- the full clash / rule check. Returns a list of error messages (empty = OK) ---------- */
function bk_validate(PDO $pdo, array $b, array $ignore = []): array
{
  $err = [];
  $s = t($b['start_time']);
  $e = t($b['end_time']);
  $d = $b['booking_date'];
  $online = ($b['delivery_mode'] ?? 'physical') === 'online';
  if ($s >= $e) $err[] = 'End time must be after start time.';
  if ($s < cfg('day_start') || $e > cfg('day_end')) $err[] = 'Classes must be between ' . cfg('day_start') . ' and ' . cfg('day_end') . '.';
  $dow = (int)date('N', strtotime($d));
  if (!in_array($dow, cfg('class_days'), true)) $err[] = 'No classes on Sundays.';
  if ($dow === 5) {
    [$ja, $jb] = cfg('slots')[cfg('friday_skip_slot')];
    if ($s < $jb && $e > $ja) $err[] = "Friday Jummah break ({$ja}–{$jb}): no classes.";
  }

  $sem = qa('SELECT * FROM semesters WHERE semester_id=?', [$b['semester_id']])[0] ?? null;
  if (!$sem) return ['Unknown semester.'];
  if ($d < $sem['start_date'] || $d > $sem['teaching_end_date']) $err[] = "Date is outside the teaching period ({$sem['start_date']} to {$sem['teaching_end_date']}).";
  if ($ev = cal_suspended((int)$b['semester_id'], $d)) $err[] = "Classes are suspended on this date ($ev).";

  $sts = $b['section_term_ids'] ?? [];
  if (!$sts) return array_merge($err, ['Select at least one section.']);
  $rows = qa('SELECT st.section_term_id, st.semester_id, st.student_count, ' . SEC_LABEL . ' AS label
              FROM section_terms st JOIN sections s ON s.section_id=st.section_id WHERE st.section_term_id IN (' . bk_in($sts) . ')');
  if (count($rows) !== count(array_unique($sts))) $err[] = 'Unknown section.';
  $total = 0;
  foreach ($rows as $r) {
    if ($r['semester_id'] != $b['semester_id']) $err[] = "{$r['label']} does not belong to this term.";
    $total += (int)$r['student_count'];
    if (!qa(
      'SELECT 1 FROM course_allocations WHERE course_id=? AND teacher_id=? AND section_term_id=? AND session_type=?',
      [$b['course_id'], $b['teacher_id'], $r['section_term_id'], $b['session_type']]
    ))
      $err[] = "Teacher is not assigned to teach this course ({$b['session_type']}) to {$r['label']}.";
  }

  if (!$online) {
    $room = qa('SELECT * FROM rooms WHERE room_id=?', [$b['room_id'] ?? 0])[0] ?? null;
    if (!$room || !$room['is_active']) $err[] = 'Room is not available.';
    else {
      if (!$room['allow_classes']) $err[] = "{$room['room_name']} is reserved for events.";
      if ($b['session_type'] === 'lab' && $room['room_type'] !== 'lab') $err[] = 'A lab session needs a lab.';
      if ($b['session_type'] === 'theory' && $room['room_type'] === 'lab') $err[] = 'Theory classes cannot use a lab.';
      if ($total > $room['capacity']) $err[] = "{$room['room_name']} holds {$room['capacity']} but $total students attend.";
    }
  }

  // overlap = existing.start < new.end AND existing.end > new.start (partial overlap counts)
  $base = "b.status='scheduled' AND b.booking_date=? AND b.start_time<? AND b.end_time>? AND b.booking_id NOT IN (" . bk_in($ignore) . ')';
  $sel  = "SELECT c.course_code, TIME_FORMAT(b.start_time,'%H:%i') s, TIME_FORMAT(b.end_time,'%H:%i') e FROM bookings b JOIN courses c ON c.course_id=b.course_id";
  $p = [$d, $e, $s];
  foreach (qa("$sel WHERE b.teacher_id=? AND $base", array_merge([$b['teacher_id']], $p)) as $x) $err[] = "Teacher already has {$x['course_code']} {$x['s']}–{$x['e']}.";
  if (!$online && !empty($b['room_id']))
    foreach (qa("$sel WHERE b.room_id=? AND $base", array_merge([$b['room_id']], $p)) as $x) $err[] = "Room already booked: {$x['course_code']} {$x['s']}–{$x['e']}.";
  foreach (qa("$sel JOIN booking_sections bs ON bs.booking_id=b.booking_id WHERE bs.section_term_id IN (" . bk_in($sts) . ") AND $base", $p) as $x)
    $err[] = "A selected section already has {$x['course_code']} {$x['s']}–{$x['e']}.";
  return array_values(array_unique($err));
}

function bk_insert(PDO $pdo, array $b): int
{
  $online = ($b['delivery_mode'] ?? 'physical') === 'online';
  $id = ins('bookings', [
    'semester_id' => $b['semester_id'],
    'series_id' => $b['series_id'] ?? null,
    'course_id' => $b['course_id'],
    'teacher_id' => $b['teacher_id'],
    'room_id' => $online ? null : $b['room_id'],
    'booking_date' => $b['booking_date'],
    'start_time' => $b['start_time'],
    'end_time' => $b['end_time'],
    'session_type' => $b['session_type'],
    'delivery_mode' => $b['delivery_mode'],
    'booking_type' => $b['booking_type'] ?? 'extra',
    'status' => 'scheduled',
    'is_modified' => $b['is_modified'] ?? 0,
    'change_note' => $b['change_note'] ?? null,
    'created_by' => $b['created_by'],
  ]);
  foreach (array_unique($b['section_term_ids']) as $st) qx('INSERT INTO booking_sections (booking_id,section_term_id) VALUES (?,?)', [$id, $st]);
  return $id;
}
/** lock + validate + insert, inside an existing transaction. Returns ['id'=>..] or ['errors'=>[..]] */
function bk_add(PDO $pdo, array $b, array $ignore = []): array
{
  bk_lock($pdo, $b);
  $err = bk_validate($pdo, $b, $ignore);
  return $err ? ['errors' => $err] : ['id' => bk_insert($pdo, $b)];
}
function bk_create(array $b): array
{
  return bk_tx(fn($pdo) => bk_add($pdo, $b));
}

/* ---------- "free right now" lookups used by the booking forms ---------- */
function bk_busy(string $who, array $ids, string $date, string $s, string $e, array $ignore = []): bool
{
  $base = "b.status='scheduled' AND b.booking_date=? AND b.start_time<? AND b.end_time>? AND b.booking_id NOT IN (" . bk_in($ignore) . ')';
  if ($who === 'teacher') return (bool)qa("SELECT 1 FROM bookings b WHERE b.teacher_id=? AND $base LIMIT 1", [$ids[0], $date, $e, $s]);
  return (bool)qa("SELECT 1 FROM bookings b JOIN booking_sections bs ON bs.booking_id=b.booking_id WHERE bs.section_term_id IN (" . bk_in($ids) . ") AND $base LIMIT 1", [$date, $e, $s]);
}
function free_rooms(string $date, string $s, string $e, string $type, int $count, array $ignore = []): array
{
  $typeSql = $type === 'lab' ? "r.room_type='lab'" : "r.room_type<>'lab'";
  return qa("SELECT r.room_id, r.room_name, r.room_type, r.capacity FROM rooms r
    WHERE r.is_active=1 AND r.allow_classes=1 AND r.capacity>=? AND $typeSql
    AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.room_id=r.room_id AND b.status='scheduled' AND b.booking_date=? AND b.start_time<? AND b.end_time>? AND b.booking_id NOT IN (" . bk_in($ignore) . "))
    ORDER BY r.capacity, LENGTH(r.room_name), r.room_name", [$count, $date, $e, $s]);
}

/* ---------- recurring series ---------- */
function series_create(array $s, array $sts): array
{
  return bk_tx(function (PDO $pdo) use ($s, $sts) {
    $online = $s['delivery_mode'] === 'online';
    $sid = ins('booking_series', [
      'semester_id' => $s['semester_id'],
      'course_id' => $s['course_id'],
      'teacher_id' => $s['teacher_id'],
      'room_id' => $online ? null : $s['room_id'],
      'session_type' => $s['session_type'],
      'delivery_mode' => $s['delivery_mode'],
      'repeat_day' => $s['repeat_day'],
      'start_time' => $s['start_time'],
      'end_time' => $s['end_time'],
      'start_date' => $s['start_date'],
      'end_date' => $s['end_date'],
      'created_by' => $s['created_by']
    ]);
    foreach ($sts as $x) qx('INSERT INTO series_sections (series_id,section_term_id) VALUES (?,?)', [$sid, $x]);
    $d = new DateTime($s['start_date']);
    $end = new DateTime($s['end_date']);
    $made = 0;
    $warn = [];
    for (; $d <= $end; $d->modify('+1 day')) {
      if ((int)$d->format('N') !== (int)$s['repeat_day']) continue;
      $ds = $d->format('Y-m-d');
      if ($why = cal_suspended((int)$s['semester_id'], $ds)) {
        $warn[] = "$ds skipped ($why)";
        continue;
      }
      $r = bk_add($pdo, array_merge($s, ['series_id' => $sid, 'booking_date' => $ds, 'booking_type' => 'regular', 'section_term_ids' => $sts]));
      if (isset($r['errors'])) $warn[] = "$ds: " . implode(' ', $r['errors']);
      else $made++;
    }
    if (!$made) return ['errors' => array_merge(['No meetings could be created.'], $warn)];
    return ['series_id' => $sid, 'created' => $made, 'warnings' => $warn];
  });
}
function series_split(PDO $pdo, array $b, array $ch): void
{      // "this and future": new template from this date on
  $s = qa('SELECT * FROM booking_series WHERE series_id=?', [$b['series_id']])[0];
  $g = fn($k) => array_key_exists($k, $ch) ? $ch[$k] : $s[$k];
  $upd = ['room_id' => $g('room_id'), 'start_time' => $g('start_time'), 'end_time' => $g('end_time'), 'delivery_mode' => $g('delivery_mode')];
  if ($s['start_date'] >= $b['booking_date']) {
    qx('UPDATE booking_series SET room_id=?,start_time=?,end_time=?,delivery_mode=? WHERE series_id=?', array_merge(array_values($upd), [$s['series_id']]));
    return;
  }
  $new = array_merge($s, $upd, ['start_date' => $b['booking_date']]);
  unset($new['series_id'], $new['created_at']);
  $nid = ins('booking_series', $new);
  qx('INSERT INTO series_sections (series_id,section_term_id) SELECT ?,section_term_id FROM series_sections WHERE series_id=?', [$nid, $s['series_id']]);
  qx('UPDATE booking_series SET end_date=DATE_SUB(?,INTERVAL 1 DAY) WHERE series_id=?', [$b['booking_date'], $s['series_id']]);
  qx('UPDATE bookings SET series_id=? WHERE series_id=? AND booking_date>=?', [$nid, $s['series_id'], $b['booking_date']]);
}

/* ---------- reschedule / cancel ---------- */
function bk_move(int $id, array $ch, string $scope, string $note = ''): array
{
  return bk_tx(function (PDO $pdo) use ($id, $ch, $scope, $note) {
    $b = bk_load($id);
    if (!$b || $b['status'] !== 'scheduled') return ['errors' => ['This class is not active.']];
    $future = ($scope === 'future' && $b['series_id']);
    $targets = $future ? bk_series_future((int)$b['series_id'], $b['booking_date']) : [$b];
    if ($future) unset($ch['booking_date']);                           // weekday stays the same
    if (($ch['delivery_mode'] ?? '') === 'online') $ch['room_id'] = null;
    $err = [];
    $new = [];
    foreach ($targets as $tg) {
      $nb = array_merge($tg, $ch);
      bk_lock($pdo, $nb);
      foreach (bk_validate($pdo, $nb, [$tg['booking_id']]) as $m) $err[] = ($future ? $tg['booking_date'] . ': ' : '') . $m;
      $new[] = $nb;
    }
    if ($err) return ['errors' => array_values(array_unique($err))];
    if ($future) series_split($pdo, $b, $ch);
    foreach ($new as $nb)
      qx(
        'UPDATE bookings SET booking_date=?,start_time=?,end_time=?,room_id=?,delivery_mode=?,is_modified=?,change_note=? WHERE booking_id=?',
        [$nb['booking_date'], $nb['start_time'], $nb['end_time'], $nb['delivery_mode'] === 'online' ? null : $nb['room_id'], $nb['delivery_mode'], $future ? 0 : 1, $note ?: null, $nb['booking_id']]
      );
    return ['count' => count($new)];
  });
}
function bk_cancel(int $id, string $scope, string $note = 'Cancelled'): array
{
  return bk_tx(function (PDO $pdo) use ($id, $scope, $note) {
    $b = bk_load($id);
    if (!$b) return ['errors' => ['Not found.']];
    $ids = [$id];
    if ($scope === 'future' && $b['series_id']) $ids = array_column(bk_series_future((int)$b['series_id'], $b['booking_date']), 'booking_id');
    qx("UPDATE bookings SET status='cancelled', is_modified=1, change_note=? WHERE booking_id IN (" . bk_in($ids) . ')', [$note]);
    return ['count' => count($ids)];
  });
}

/* ---------- merge two classes (same subject, same teacher, max two sections) ---------- */
function bk_merge(int $a, int $b, int $actor, array $slot): array
{
  return bk_tx(function (PDO $pdo) use ($a, $b, $actor, $slot) {
    $A = bk_load($a);
    $B = bk_load($b);
    if (!$A || !$B || $a === $b) return ['errors' => ['Choose two different classes.']];
    foreach ([$A, $B] as $x) {
      if ($x['status'] !== 'scheduled') return ['errors' => ['Both classes must be active.']];
      if ($x['teacher_id'] != $actor) return ['errors' => ['You can only merge your own classes.']];
      if (count($x['section_term_ids']) !== 1) return ['errors' => ['Only single-section classes can be merged (maximum two sections).']];
    }
    if ($A['course_id'] != $B['course_id'] || $A['session_type'] !== $B['session_type']) return ['errors' => ['Both classes must be the same subject and session type.']];
    if ($A['section_term_ids'][0] == $B['section_term_ids'][0]) return ['errors' => ['Choose classes of two different sections.']];
    $nb = [
      'semester_id' => $A['semester_id'],
      'series_id' => null,
      'course_id' => $A['course_id'],
      'teacher_id' => $actor,
      'room_id' => $slot['room_id'],
      'booking_date' => $slot['booking_date'],
      'start_time' => $slot['start_time'],
      'end_time' => $slot['end_time'],
      'session_type' => $A['session_type'],
      'delivery_mode' => 'physical',
      'booking_type' => 'merged',
      'section_term_ids' => array_merge($A['section_term_ids'], $B['section_term_ids']),
      'created_by' => $actor
    ];
    $r = bk_add($pdo, $nb, [$a, $b]);                       // the two old meetings don't block the new slot
    if (isset($r['errors'])) return $r;
    qx("UPDATE bookings SET status='cancelled', is_modified=1, change_note=? WHERE booking_id IN (?,?)", ["Merged into class #{$r['id']}", $a, $b]);
    return ['id' => $r['id']];
  });
}

/* ---------- requests for an occupied slot ---------- */
function req_create(int $requester, int $bookingId, int $allocId, string $msg): array
{
  $b = qa("SELECT * FROM bookings WHERE booking_id=? AND status='scheduled' AND room_id IS NOT NULL", [$bookingId])[0] ?? null;
  $al = qa('SELECT * FROM course_allocations WHERE allocation_id=? AND teacher_id=?', [$allocId, $requester])[0] ?? null;
  if (!$b || !$al) return ['ok' => false, 'errors' => ['Invalid request.']];
  if ($b['teacher_id'] == $requester) return ['ok' => false, 'errors' => ['That slot is already yours.']];
  if (qa("SELECT 1 FROM slot_requests WHERE booking_id=? AND requester_id=? AND status='pending'", [$bookingId, $requester]))
    return ['ok' => false, 'errors' => ['You already have a pending request for this slot.']];
  ins('slot_requests', ['booking_id' => $bookingId, 'requester_id' => $requester, 'owner_id' => $b['teacher_id'], 'allocation_id' => $allocId, 'message' => mb_substr($msg, 0, 255)]);
  return ['ok' => true];
}
function req_decline(int $id, int $actor): bool
{
  return qx("UPDATE slot_requests SET status='declined', responded_at=NOW() WHERE request_id=? AND owner_id=? AND status='pending'", [$id, $actor]) > 0;
}
function req_withdraw(int $id, int $actor): bool
{
  return qx("UPDATE slot_requests SET status='withdrawn', responded_at=NOW() WHERE request_id=? AND requester_id=? AND status='pending'", [$id, $actor]) > 0;
}
/** $action = 'cancel' | 'reschedule'; $new = booking_date,start_time,end_time,room_id (for reschedule) */
function req_approve(int $id, int $actor, string $action, array $new = []): array
{
  return bk_tx(function (PDO $pdo) use ($id, $actor, $action, $new) {
    $R = qa('SELECT * FROM slot_requests WHERE request_id=? FOR UPDATE', [$id])[0] ?? null;
    if (!$R || $R['status'] !== 'pending' || $R['owner_id'] != $actor) return ['errors' => ['Request not found or already handled.']];
    $O = bk_load((int)$R['booking_id']);
    if (!$O || $O['status'] !== 'scheduled') return ['errors' => ['The original class is no longer scheduled.']];
    $al = qa('SELECT * FROM course_allocations WHERE allocation_id=?', [$R['allocation_id']])[0];
    if ($action === 'cancel') {
      qx("UPDATE bookings SET status='cancelled', is_modified=1, change_note='Slot released to another teacher' WHERE booking_id=?", [$O['booking_id']]);
    } elseif ($action === 'reschedule') {
      $nb = array_merge($O, ['booking_date' => $new['booking_date'] ?? '', 'start_time' => $new['start_time'] ?? '', 'end_time' => $new['end_time'] ?? '', 'room_id' => $new['room_id'] ?? null]);
      bk_lock($pdo, $nb);
      if ($e = bk_validate($pdo, $nb, [$O['booking_id']])) return ['errors' => $e];
      qx(
        'UPDATE bookings SET booking_date=?,start_time=?,end_time=?,room_id=?,is_modified=1,change_note=? WHERE booking_id=?',
        [$nb['booking_date'], $nb['start_time'], $nb['end_time'], $nb['room_id'], 'Moved to free the slot for another teacher', $O['booking_id']]
      );
    } else return ['errors' => ['Choose what happens to the original class.']];
    // the requester takes the freed slot; availability is re-checked here, inside the same transaction
    $mine = [
      'semester_id' => $O['semester_id'],
      'series_id' => null,
      'course_id' => $al['course_id'],
      'teacher_id' => $R['requester_id'],
      'room_id' => $O['room_id'],
      'booking_date' => $O['booking_date'],
      'start_time' => $O['start_time'],
      'end_time' => $O['end_time'],
      'session_type' => $al['session_type'],
      'delivery_mode' => 'physical',
      'booking_type' => 'extra',
      'section_term_ids' => [(int)$al['section_term_id']],
      'created_by' => $actor
    ];
    $r = bk_add($pdo, $mine);
    if (isset($r['errors'])) return $r;
    qx("UPDATE slot_requests SET status='approved', responded_at=NOW() WHERE request_id=?", [$id]);
    return ['id' => $r['id']];
  });
}

/* ---------- calendar edits that suspend classes: keep bookings consistent ---------- */
function reconcile_suspensions(int $sem): array
{
  $cancelled = qx("UPDATE bookings b JOIN calendar_events e ON e.semester_id=b.semester_id AND e.suspends_regular_classes=1 AND b.booking_date BETWEEN e.start_date AND e.end_date
     SET b.status='cancelled', b.is_modified=1, b.change_note=CONCAT('Suspended: ',e.title) WHERE b.semester_id=? AND b.status='scheduled'", [$sem]);
  $restored = 0;
  $kept = 0;
  foreach (
    qa("SELECT b.booking_id FROM bookings b WHERE b.semester_id=? AND b.status='cancelled' AND b.change_note LIKE 'Suspended:%'
     AND NOT EXISTS (SELECT 1 FROM calendar_events e WHERE e.semester_id=b.semester_id AND e.suspends_regular_classes=1 AND b.booking_date BETWEEN e.start_date AND e.end_date)", [$sem]) as $x
  ) {
    $r = bk_tx(function (PDO $pdo) use ($x) {
      $b = bk_load((int)$x['booking_id']);
      bk_lock($pdo, $b);
      if ($e = bk_validate($pdo, $b)) return ['errors' => $e];        // only restore if still clash-free
      qx("UPDATE bookings SET status='scheduled', change_note=NULL WHERE booking_id=?", [$b['booking_id']]);
      return [];
    });
    $r['ok'] ? $restored++ : $kept++;
  }
  return compact('cancelled', 'restored', 'kept');
}
