<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/booking_service.php';
require_role(['teacher']);
csrf_check();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['slot']) || empty($_POST['room']))
    $r = ['ok' => false, 'errors' => ['Choose a free slot and room.']];
  else
    $r = bk_merge((int) $_POST['a'], (int) $_POST['b'], (int) uid(), [
      'booking_date' => $_POST['date'],
      'start_time' => $_POST['slot'],
      'end_time' => add_min($_POST['slot'], (int) $_POST['dur']),
      'room_id' => (int) $_POST['room']
    ]);
  if ($r['ok']) {
    flash('Classes merged. Students see the merged class in their overview.');
    redirect('teacher/overview.php');
  }
  $errors = $r['errors'];
}
$sem = current_semester();
$list = fetch_bookings("b.semester_id=? AND b.teacher_id=? AND b.status='scheduled' AND b.delivery_mode='physical' AND b.booking_type<>'merged'
   AND b.booking_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 45 DAY)
   AND (SELECT COUNT(*) FROM booking_sections z WHERE z.booking_id=b.booking_id)=1", [$sem['semester_id'], uid()]);
page_start('Merge Classes', 'overview'); ?>
<?php if ($errors): ?>
  <div class="flash err">
    <ul><?php foreach ($errors as $m)
      echo '<li>' . e($m) . '</li>'; ?></ul>
  </div><?php endif; ?>
<div class="card form">
  <p class="busy">Merge two sections of the <b>same subject</b> (max two) into one class in a slot that is free for both
    sections. The two original meetings are cancelled automatically.</p>
  <form method="post"><?= csrf_field() ?>
    <label>Class 1<select name="a" id="a" required>
        <option value="">— select —</option>
        <?php foreach ($list as $x): ?>
          <option value="<?= $x['booking_id'] ?>" data-course="<?= $x['course_id'] ?>"
            data-type="<?= $x['session_type'] ?>" data-st="<?= $x['st_ids'] ?>">
            <?= e(date('D M j', strtotime($x['booking_date'])) . ' ' . t($x['start_time']) . ' · ' . $x['course_code'] . ' · ' . $x['sections_label'] . ' · ' . $x['room_name']) ?>
          </option><?php endforeach; ?>
      </select></label>
    <label>Class 2 (same subject, different section)<select name="b" id="b" required></select></label>
    <label>Merged date<input type="date" name="date" id="date" required></label>
    <label>Duration<select name="dur" id="dur"></select></label>
    <label>Free slot<select name="slot" id="slot" required></select></label>
    <label>Free room (fits both sections)<select name="room" id="room" required></select></label>
    <button class="btn">Merge classes</button> <a class="btn ghost" href="<?= url('teacher/overview.php') ?>">Back</a>
  </form>
</div>
<?php page_end('<script>
const A=document.getElementById("a"),Bs=document.getElementById("b"),date=document.getElementById("date"),dur=document.getElementById("dur"),slot=document.getElementById("slot"),room=document.getElementById("room");
const all=[...A.options].filter(o=>o.value);date.min=new Date().toISOString().slice(0,10);durOptions(dur,"theory");
function ctx(){const a=A.selectedOptions[0],b=Bs.selectedOptions[0];if(!a||!a.value||!b||!b.value)return null;return{sts:a.dataset.st+","+b.dataset.st,type:a.dataset.type};}
const P=picker({date,dur,slot,room,ctx,ignore:()=>A.value+","+Bs.value});
A.addEventListener("change",()=>{const a=A.selectedOptions[0];Bs.innerHTML="";Bs.add(new Option("— select —",""));
  if(a&&a.value){all.filter(o=>o.value!==a.value&&o.dataset.course===a.dataset.course&&o.dataset.type===a.dataset.type&&o.dataset.st!==a.dataset.st)
    .forEach(o=>{const c=new Option(o.text,o.value);c.dataset.st=o.dataset.st;Bs.add(c);});durOptions(dur,a.dataset.type);}P.slots();});
Bs.addEventListener("change",()=>P.slots());
</script>');