<?php
require_once __DIR__ . '/../../includes/layout.php';
require_viewer();
$sems = qa('SELECT * FROM semesters ORDER BY start_date DESC');
$cur = current_semester();
$sid = (int)($_GET['sem'] ?? $cur['semester_id']);
$view = ($_GET['view'] ?? 'calendar') === 'weeks' ? 'weeks' : 'calendar';
page_start('Semester Calendar', 'calendar'); ?>
<form class="row" method="get">
  <label>Table<select name="view" data-auto>
      <option value="calendar" <?= $view === 'calendar' ? 'selected' : '' ?>>Semester Calendar</option>
      <option value="weeks" <?= $view === 'weeks' ? 'selected' : '' ?>>Week-wise Plan</option>
    </select></label>
  <label>Semester<select name="sem" data-auto><?php foreach ($sems as $s): ?><option value="<?= $s['semester_id'] ?>" <?= $s['semester_id'] == $sid ? 'selected' : '' ?>><?= e($s['semester_name']) ?></option><?php endforeach; ?></select></label>
</form>
<?php if ($view === 'calendar'): $ev = qa('SELECT * FROM calendar_events WHERE semester_id=? ORDER BY start_date, event_id', [$sid]); ?>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Sr</th>
          <th>Event</th>
          <th>Day</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ev as $n => $x): ?><tr>
            <td><?= $n + 1 ?></td>
            <td><?= e($x['title']) ?><?= $x['is_tentative'] ? ' <span class="b gray">tentative</span>' : '' ?><?= $x['suspends_regular_classes'] ? ' <span class="b red">classes suspended</span>' : '' ?>
              <?php if ($x['description']): ?><div class="cm"><?= e($x['description']) ?></div><?php endif; ?></td>
            <td><?= e(day_range($x['start_date'], $x['end_date'])) ?></td>
            <td><?= e(fmt_range($x['start_date'], $x['end_date'])) ?></td>
          </tr><?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: $wr = qa('SELECT * FROM calendar_weeks WHERE semester_id=? ORDER BY phase, week_number', [$sid]);
  $cum = ['pre_mid' => 0, 'post_mid' => 0]; ?>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Phase</th>
          <th>Teaching week</th>
          <th>Dates</th>
          <th>Lectures</th>
          <th>Cumulative</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($wr as $w): $cum[$w['phase']] += (int)$w['planned_lectures']; ?><tr>
            <td><?= $w['phase'] === 'pre_mid' ? 'Pre-Mid' : 'Post-Mid' ?></td>
            <td><?= (int)$w['week_number'] ?></td>
            <td><?= e(fmt_range($w['start_date'], $w['end_date'])) ?></td>
            <td><?= $w['planned_lectures'] ?? '—' ?></td>
            <td><?= $cum[$w['phase']] ?></td>
            <td><?= e($w['notes']) ?></td>
          </tr><?php endforeach; ?>
        <tr>
          <th colspan="3">Total lectures</th>
          <th colspan="3"><?= array_sum($cum) ?></th>
        </tr>
      </tbody>
    </table>
  </div>
<?php endif;
page_end();
