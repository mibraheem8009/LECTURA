<?php
require_once __DIR__ . '/../../includes/layout.php';
require_viewer();
$sem = current_semester();
$sid = (int)$sem['semester_id'];
$sts = section_terms($sid);
$st = isset($_GET['st']) ? (int)$_GET['st'] : (int)($sts[0]['section_term_id'] ?? 0);
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$view = $_GET['day'] ?? 'date';
$wk = week_dates($date);
if ($view === 'week') {
  $from = $wk[1];
  $to = $wk[6];
} elseif (ctype_digit((string)$view) && $view >= 1 && $view <= 6) {
  $from = $to = $wk[(int)$view];
} else {
  $view = 'date';
  $from = $to = $date;
}

$where = 'b.semester_id=? AND b.booking_date BETWEEN ? AND ?';
$p = [$sid, $from, $to];
if ($st) {
  $where .= ' AND EXISTS (SELECT 1 FROM booking_sections x WHERE x.booking_id=b.booking_id AND x.section_term_id=?)';
  $p[] = $st;
}
$rows = fetch_bookings($where, $p);
$susp = suspended_map($sid, $from, $to);
$byDate = [];
foreach ($rows as $r) $byDate[$r['booking_date']][] = $r;
$active = count(array_filter($rows, fn($r) => $r['status'] === 'scheduled'));
$changed = count(array_filter($rows, fn($r) => $r['status'] === 'cancelled' || $r['is_modified'] || $r['booking_type'] !== 'regular'));
$ann  = qa('SELECT * FROM announcements WHERE semester_id=? AND (section_term_id IS NULL OR section_term_id=?) AND (event_date IS NULL OR event_date>=CURDATE()) ORDER BY (event_date IS NULL), event_date LIMIT 8', [$sid, $st]);
$next = qa('SELECT * FROM calendar_events WHERE semester_id=? AND end_date>=CURDATE() ORDER BY start_date LIMIT 4', [$sid]);

page_start('Overview · ' . $sem['semester_name'], 'overview'); ?>
<form class="row" method="get">
  <label>Section/Class<select name="st" data-auto>
      <option value="0">All sections</option>
      <?php foreach ($sts as $x): ?><option value="<?= $x['section_term_id'] ?>" <?= $x['section_term_id'] == $st ? 'selected' : '' ?>><?= e($x['label']) ?> · Sem <?= $x['semester_number'] ?></option><?php endforeach; ?>
    </select></label>
  <label>Date<input type="date" name="date" value="<?= e($date) ?>" data-auto></label>
  <label>Show<select name="day" data-auto>
      <option value="date" <?= $view === 'date' ? 'selected' : '' ?>>Selected date</option>
      <option value="week" <?= $view === 'week' ? 'selected' : '' ?>>Whole week</option>
      <?php for ($i = 1; $i <= 6; $i++): ?><option value="<?= $i ?>" <?= (string)$view === (string)$i ? 'selected' : '' ?>><?= DAYS[$i] ?> (<?= date('M j', strtotime($wk[$i])) ?>)</option><?php endfor; ?>
    </select></label>
</form>
<div class="stats">
  <div class="stat"><b><?= $active ?></b><span>Classes in view</span></div>
  <div class="stat"><b><?= $changed ?></b><span>Changed / extra / cancelled</span></div>
  <div class="stat"><b><?= e(date('D, M j', strtotime($from))) ?></b><span>From</span></div>
</div>
<div class="two">
  <div>
    <?php for ($d = new DateTime($from); $d <= new DateTime($to); $d->modify('+1 day')): $ds = $d->format('Y-m-d');
      if ($d->format('N') == 7) continue; ?>
      <div class="card daycard">
        <h3><?= DAYS[(int)$d->format('N')] ?> · <?= $d->format('M j, Y') ?></h3>
        <?php if (isset($susp[$ds])): ?><div class="busy">🏖 <?= e($susp[$ds]) ?> — regular classes suspended</div>
        <?php elseif (empty($byDate[$ds])): ?><div class="busy">No classes scheduled.</div>
        <?php else: foreach ($byDate[$ds] as $r) echo booking_card($r);
        endif; ?>
      </div>
    <?php endfor; ?>
  </div>
  <div>
    <div class="card">
      <h3>Quizzes &amp; info</h3>
      <?php if (!$ann) echo '<div class="busy">Nothing posted.</div>';
      foreach ($ann as $a): ?>
        <div class="ann"><span class="b <?= $a['kind'] === 'quiz' ? 'amber' : 'blue' ?>"><?= e(strtoupper($a['kind'])) ?></span> <b><?= e($a['title']) ?></b>
          <?php if ($a['event_date']): ?><div class="cm"><?= e(date('D, M j', strtotime($a['event_date']))) ?></div><?php endif; ?><div class="cm"><?= e($a['body']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <h3>Upcoming calendar</h3>
      <?php foreach ($next as $n): ?><div class="ann"><b><?= e($n['title']) ?></b><?= $n['is_tentative'] ? ' <span class="b gray">tentative</span>' : '' ?><div class="cm"><?= e(fmt_range($n['start_date'], $n['end_date'])) ?></div>
        </div><?php endforeach; ?>
    </div>
  </div>
</div>
<?php page_end();
