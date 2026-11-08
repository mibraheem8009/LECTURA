<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/booking_service.php';
require_role(['teacher', 'admin']);
csrf_check();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$b = bk_load($id);
$back = role() === 'admin' ? 'admin/bookings.php' : 'teacher/overview.php';
if (!$b || (role() === 'teacher' && $b['teacher_id'] != uid())) {
  flash('Class not found, or it is not yours.', 'err');
  redirect($back);
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $scope = ($_POST['scope'] ?? 'this') === 'future' ? 'future' : 'this';
  if (isset($_POST['cancel']))
    $r = bk_cancel($id, $scope, 'Cancelled by ' . $_SESSION['name']);
  elseif (empty($_POST['slot']))
    $r = ['ok' => false, 'errors' => ['Choose a free slot.']];
  else {
    $mode = ($_POST['mode'] ?? '') === 'online' ? 'online' : 'physical';
    $r = bk_move($id, [
      'booking_date' => $_POST['date'] ?? $b['booking_date'],
      'start_time' => $_POST['slot'],
      'end_time' => add_min($_POST['slot'], (int) $_POST['dur']),
      'delivery_mode' => $mode,
      'room_id' => $mode === 'physical' ? (int) ($_POST['room'] ?? 0) : null
    ], $scope, 'Rescheduled by ' . $_SESSION['name']);
  }
  if ($r['ok']) {
    flash('Saved. Students now see the updated timetable.');
    redirect($back);
  }
  $errors = $r['errors'];
}
$info = fetch_bookings('b.booking_id=?', [$id])[0];
$dur = to_min($b['end_time']) - to_min($b['start_time']);
page_start('Edit class', 'overview'); ?>
<?php if ($errors): ?>
  <div class="flash err">Could not save:<ul><?php foreach ($errors as $m)
    echo '<li>' . e($m) . '</li>'; ?></ul>
  </div><?php endif; ?>
<div class="card form"><?= booking_card($info) ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <?php if ($b['series_id']): ?><label>Apply change to<span><label style="flex-direction:row;gap:6px"><input
              type="radio" name="scope" value="this" checked style="min-width:0"> Only this meeting</label>
          <label style="flex-direction:row;gap:6px"><input type="radio" name="scope" value="future" style="min-width:0">
            This and future meetings (same weekday)</label></span></label><?php endif; ?>
    <label>Delivery<select name="mode" id="mode">
        <option value="physical" <?= $b['delivery_mode'] === 'physical' ? 'selected' : '' ?>>Physical</option>
        <option value="online" <?= $b['delivery_mode'] === 'online' ? 'selected' : '' ?>>Online</option>
      </select></label>
    <label>Date<input type="date" name="date" id="date" required></label>
    <label>Duration<select name="dur" id="dur"></select></label>
    <label>Free slot<select name="slot" id="slot" required></select></label>
    <label id="roomwrap">Free room<select name="room" id="room"></select></label>
    <button class="btn">Save changes</button>
    <button class="btn danger" name="cancel" value="1"
      onclick="return confirm('Cancel this class? The room and time become free.')">Cancel class</button>
    <a class="btn ghost" href="<?= url($back) ?>">Back</a>
  </form>
</div>
<?php page_end('<script>
const B=' . json_encode(['id' => $id, 'sts' => implode(',', $b['section_term_ids']), 'type' => $b['session_type'], 'teacher' => $b['teacher_id'], 'date' => $b['booking_date'], 'start' => t($b['start_time']), 'room' => $b['room_id'], 'dur' => $dur]) . ';
const date=document.getElementById("date"),dur=document.getElementById("dur"),slot=document.getElementById("slot"),room=document.getElementById("room"),mode=document.getElementById("mode");
date.value=B.date;date.min=new Date().toISOString().slice(0,10);durOptions(dur,B.type,B.dur);
const P=picker({date,dur,slot,room,ignore:()=>String(B.id),ctx:()=>({sts:B.sts,type:B.type,teacher:B.teacher,online:mode.value==="online"?1:0})});
P.slots(B.start,B.room);
mode.addEventListener("change",()=>{document.getElementById("roomwrap").hidden=mode.value==="online";P.slots();});
document.querySelectorAll("input[name=scope]").forEach(r=>r.addEventListener("change",()=>{date.disabled=(r.value==="future"&&r.checked);}));
</script>');