<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/booking_service.php';
require_role(['teacher']);
csrf_check();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rid = (int) ($_POST['rid'] ?? 0);
  $do = $_POST['do'] ?? '';
  if ($do === 'decline') {
    req_decline($rid, (int) uid());
    flash('Request declined.');
    redirect('teacher/requests.php');
  }
  if ($do === 'withdraw') {
    req_withdraw($rid, (int) uid());
    flash('Request withdrawn.');
    redirect('teacher/requests.php');
  }
  if ($do === 'approve') {
    $act = $_POST['action'] ?? '';
    $new = ['booking_date' => $_POST['date'] ?? '', 'start_time' => $_POST['slot'] ?? '', 'end_time' => add_min($_POST['slot'] ?? '08:15', (int) ($_POST['dur'] ?? 120)), 'room_id' => (int) ($_POST['room'] ?? 0)];
    $r = req_approve($rid, (int) uid(), $act, $new);
    if ($r['ok']) {
      flash('Approved. Your original class was updated and the slot now belongs to the requester.');
      redirect('teacher/requests.php');
    }
    $errors = $r['errors'];
    $_GET['respond'] = $rid;
  }
}
$inc = qa("SELECT q.*, u.full_name requester, b.booking_date, b.start_time, b.end_time, r.room_name, c.course_code
  FROM slot_requests q JOIN users u ON u.user_id=q.requester_id JOIN bookings b ON b.booking_id=q.booking_id
  LEFT JOIN rooms r ON r.room_id=b.room_id JOIN courses c ON c.course_id=b.course_id WHERE q.owner_id=? ORDER BY q.status='pending' DESC, q.created_at DESC", [uid()]);
$out = qa("SELECT q.*, u.full_name owner, b.booking_date, b.start_time, b.end_time, r.room_name
  FROM slot_requests q JOIN users u ON u.user_id=q.owner_id JOIN bookings b ON b.booking_id=q.booking_id
  LEFT JOIN rooms r ON r.room_id=b.room_id WHERE q.requester_id=? ORDER BY q.created_at DESC", [uid()]);
page_start('Slot Requests', 'requests');
if ($errors)
  echo '<div class="flash err"><ul>' . implode('', array_map(fn($m) => '<li>' . e($m) . '</li>', $errors)) . '</ul></div>';

if (!empty($_GET['respond'])):                                   // ---- approve screen ----
  $q = qa("SELECT * FROM slot_requests WHERE request_id=? AND owner_id=? AND status='pending'", [(int) $_GET['respond'], uid()])[0] ?? null;
  $o = $q ? fetch_bookings('b.booking_id=?', [$q['booking_id']])[0] ?? null : null;
  if (!$o):
    echo '<div class="flash err">Request not found.</div>';
  else: ?>
    <div class="card form">
      <h2>Respond to request</h2>
      <p><b>Message:</b> <?= e($q['message']) ?></p><?= booking_card($o) ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="approve"><input type="hidden" name="rid"
          value="<?= $q['request_id'] ?>">
        <label>What happens to your original class?<span>
            <label style="flex-direction:row;gap:6px"><input type="radio" name="action" value="cancel" style="min-width:0"
                required> Cancel it (affects: <?= e($o['sections_label']) ?>)</label>
            <label style="flex-direction:row;gap:6px"><input type="radio" name="action" value="reschedule"
                style="min-width:0"> Reschedule it to a free slot</label></span></label>
        <div id="resched" hidden>
          <label>New date<input type="date" name="date" id="date"></label>
          <label>Duration<select name="dur" id="dur"></select></label>
          <label>Free slot<select name="slot" id="slot"></select></label>
          <label>Free room<select name="room" id="room"></select></label>
        </div>
        <button class="btn">Approve</button>
        <button class="btn danger" name="do" value="decline" formnovalidate>Decline</button>
        <a class="btn ghost" href="<?= url('teacher/requests.php') ?>">Back</a>
      </form>
    </div>
    <?php page_end('<script>
const B=' . json_encode(['id' => $o['booking_id'], 'sts' => $o['st_ids'], 'type' => $o['session_type'], 'teacher' => $o['teacher_id'], 'dur' => to_min($o['end_time']) - to_min($o['start_time'])]) . ';
const date=document.getElementById("date"),dur=document.getElementById("dur"),slot=document.getElementById("slot"),room=document.getElementById("room");
durOptions(dur,B.type,B.dur);date.min=new Date().toISOString().slice(0,10);
const P=picker({date,dur,slot,room,ignore:()=>String(B.id),ctx:()=>({sts:B.sts,type:B.type,teacher:B.teacher})});
document.querySelectorAll("input[name=action]").forEach(r=>r.addEventListener("change",()=>{document.getElementById("resched").hidden=(r.value!=="reschedule");}));
</script>');
    exit; endif; endif; ?>

<h2>Requests for my slots</h2>
<div class="tbl-wrap">
  <table>
    <thead>
      <tr>
        <th>From</th>
        <th>Slot</th>
        <th>Message</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($inc as $q): ?>
        <tr>
          <td><?= e($q['requester']) ?></td>
          <td><?= e($q['booking_date'] . ' ' . t($q['start_time']) . '–' . t($q['end_time']) . ' · ' . $q['room_name']) ?></td>
          <td><?= e($q['message']) ?></td>
          <td><?= e($q['status']) ?></td>
          <td><?php if ($q['status'] === 'pending'): ?><a class="btn sm"
                href="?respond=<?= $q['request_id'] ?>">Respond</a>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="rid"
                  value="<?= $q['request_id'] ?>"><button class="btn sm ghost" name="do" value="decline">Decline</button>
              </form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach;
      if (!$inc)
        echo '<tr><td colspan="5" class="busy">No requests.</td></tr>'; ?>
    </tbody>
  </table>
</div>
<h2>My requests</h2>
<div class="tbl-wrap">
  <table>
    <thead>
      <tr>
        <th>Teacher</th>
        <th>Slot</th>
        <th>Message</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($out as $q): ?>
        <tr>
          <td><?= e($q['owner']) ?></td>
          <td><?= e($q['booking_date'] . ' ' . t($q['start_time']) . '–' . t($q['end_time']) . ' · ' . $q['room_name']) ?></td>
          <td><?= e($q['message']) ?></td>
          <td><?= e($q['status']) ?></td>
          <td><?php if ($q['status'] === 'pending'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="rid" value="<?= $q['request_id'] ?>"><button
                  class="btn sm ghost" name="do" value="withdraw">Withdraw</button></form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach;
      if (!$out)
        echo '<tr><td colspan="5" class="busy">You have not asked for any slots.</td></tr>'; ?>
    </tbody>
  </table>
</div>
<?php page_end();