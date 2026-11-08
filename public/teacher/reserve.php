<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/booking_service.php';
require_role(['teacher']);
csrf_check();
$sem = current_semester();
$sid = (int) $sem['semester_id'];
$errors = [];
$allocs = qa('SELECT a.allocation_id, a.session_type, a.duration_minutes, a.section_term_id, c.course_id, c.course_code, c.course_title, ' . SEC_LABEL . ' AS label
  FROM course_allocations a JOIN courses c ON c.course_id=a.course_id JOIN section_terms st ON st.section_term_id=a.section_term_id
  JOIN sections s ON s.section_id=st.section_id WHERE a.teacher_id=? AND st.semester_id=? ORDER BY c.course_code, label', [uid(), $sid]);
$byId = array_column($allocs, null, 'allocation_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (($_POST['action'] ?? '') === 'reserve') {
    $al = $byId[(int) ($_POST['alloc'] ?? 0)] ?? null;
    $mode = ($_POST['mode'] ?? '') === 'online' ? 'online' : 'physical';
    if (!$al || empty($_POST['slot']) || ($mode === 'physical' && empty($_POST['room'])))
      $errors[] = 'Please fill in every field.';
    else {
      $r = bk_create([
        'semester_id' => $sid,
        'series_id' => null,
        'course_id' => $al['course_id'],
        'teacher_id' => uid(),
        'room_id' => $mode === 'physical' ? (int) $_POST['room'] : null,
        'booking_date' => $_POST['date'],
        'start_time' => $_POST['slot'],
        'end_time' => add_min($_POST['slot'], (int) $_POST['dur']),
        'session_type' => $al['session_type'],
        'delivery_mode' => $mode,
        'booking_type' => 'extra',
        'section_term_ids' => [(int) $al['section_term_id']],
        'created_by' => uid()
      ]);
      if ($r['ok']) {
        flash('Slot reserved. It now appears in the students\' overview.');
        redirect('teacher/overview.php');
      }
      $errors = $r['errors'];
    }
  } elseif (($_POST['action'] ?? '') === 'request') {
    $r = req_create((int) uid(), (int) ($_POST['booking'] ?? 0), (int) ($_POST['alloc2'] ?? 0), trim($_POST['message'] ?? ''));
    if ($r['ok']) {
      flash('Request sent to the teacher who holds the slot.');
      redirect('teacher/requests.php');
    }
    $errors = $r['errors'];
  }
}
$opts = '';
foreach ($allocs as $a)
  $opts .= '<option value="' . $a['allocation_id'] . '" data-sts="' . $a['section_term_id'] . '" data-type="' . $a['session_type'] . '" data-dur="' . $a['duration_minutes'] . '">' . e($a['course_code'] . ' · ' . $a['label'] . ' · ' . ucfirst($a['session_type'])) . '</option>';
page_start('Reserve Slot', 'reserve'); ?>
<?php if ($errors): ?>
  <div class="flash err">
    <ul><?php foreach ($errors as $m)
      echo '<li>' . e($m) . '</li>'; ?></ul>
  </div><?php endif; ?>
<div class="two">
  <div>
    <form method="post" class="card form">
      <h2>Reserve a free slot</h2><?= csrf_field() ?><input type="hidden" name="action" value="reserve">
      <p class="busy">Only slots that are free <b>right now</b> for you and the section are offered.</p>
      <label>Class<select name="alloc" id="alloc" required>
          <option value="">— select —</option><?= $opts ?>
        </select></label>
      <label>Date<input type="date" name="date" id="date" required></label>
      <label>Delivery<select name="mode" id="mode">
          <option value="physical">Physical (room / lab)</option>
          <option value="online">Online</option>
        </select></label>
      <label>Duration<select name="dur" id="dur"></select></label>
      <label>Free slot<select name="slot" id="slot" required></select></label>
      <label id="roomwrap">Free room<select name="room" id="room"></select></label>
      <button class="btn">Reserve</button>
    </form>
  </div>
  <div>
    <form method="post" class="card form">
      <h2>Ask for an occupied slot</h2><?= csrf_field() ?><input type="hidden" name="action" value="request">
      <label>For my class<select name="alloc2" required>
          <option value="">— select —</option><?= $opts ?>
        </select></label>
      <label>Date<input type="date" id="d2" required></label>
      <label>Slot<select
          id="s2"><?php foreach (slots() as $i => [$a, $b])
            echo "<option value=\"$a|$b\">Slot $i · {$a}–{$b}</option>"; ?></select></label>
      <label>Occupied booking<select name="booking" id="occ" required></select></label>
      <label>Message<input name="message" maxlength="255" required placeholder="e.g. Extra lab before the quiz"></label>
      <button class="btn">Send request</button>
    </form>
  </div>
</div>
<?php page_end('<script>
const A=document.getElementById("alloc"),M=document.getElementById("mode");
const date=document.getElementById("date"),dur=document.getElementById("dur"),slot=document.getElementById("slot"),room=document.getElementById("room");
date.min=new Date().toISOString().slice(0,10);
const P=picker({date,dur,slot,room,ctx:()=>{const o=A.selectedOptions[0];return o&&o.value?{sts:o.dataset.sts,type:o.dataset.type,online:M.value==="online"?1:0}:null;}});
A.addEventListener("change",()=>{const o=A.selectedOptions[0];if(o&&o.value)durOptions(dur,o.dataset.type,o.dataset.dur);P.slots();});
M.addEventListener("change",()=>{document.getElementById("roomwrap").hidden=M.value==="online";P.slots();});
const d2=document.getElementById("d2"),s2=document.getElementById("s2"),occ=document.getElementById("occ");d2.min=date.min;
async function loadOcc(){if(!d2.value){return;}const[ss,se]=s2.value.split("|");const r=await jget({mode:"occupied",date:d2.value,ss,se});
  fill(occ,r.items||[],(r.items&&r.items.length)?"— choose —":(r.msg||"No occupied rooms in this slot"));}
d2.addEventListener("change",loadOcc);s2.addEventListener("change",loadOcc);
</script>');