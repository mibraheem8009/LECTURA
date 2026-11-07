<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit('Run from the command line: php database/seed.php');
}
require_once __DIR__ . '/../includes/booking_service.php';
if (qa('SELECT COUNT(*) n FROM semesters')[0]['n'] > 0)
  exit("Already seeded.\n");

/* ---- users ---- */
$admin = ins('users', ['full_name' => 'Admin', 'email' => 'bcsf24a023@pucit.edu.pk', 'password_hash' => password_hash('admin@23', PASSWORD_DEFAULT), 'role' => 'admin']);
$names = [
  'sabaa' => 'Ms. Sabaa Inaam',
  'shahzad' => 'Mr. Shahzad Akram',
  'komal' => 'Dr. Komal Hassan',
  'ahmed' => 'Dr. Ahmed Hassan',
  'nazar' => 'Dr. Nazar Khan',
  'asif' => 'Mr. Muhammad Asif',
  'nasteen' => 'Ms. Nasteen Fatima',
  'khadija' => 'Ms. Khadija Maryam',
  'mateen' => 'Mr. Abdul Mateen',
  'irfana' => 'Dr. Irfana Bibi',
  'javed' => 'Dr. Javed Majeed',
  'zubaida' => 'Ms. Zubaida Waheed',
  'afzal' => 'Mr. Afzal Mahmood',
  'aftab' => 'Dr. Aftab Akram',
  'ehtisham' => 'Mr. Ehtisham Badar',
  'saadia' => 'Dr. Saadia Shahzad',
  'ahbutt' => 'Dr. Ahmed Hassan Butt',
  'hafiz' => 'Mr. Hafiz Hussain',
  'ammara' => 'Ms. Ammara Kanwal',
  'tayyaba' => 'Dr. Tayyaba Tariq',
  'noman' => 'Dr. Noman Shafi',
  'zobia' => 'Dr. Zobia Sohail',
  'tba' => 'Math Deficiency Instructor (TBA)'
];
$hash = password_hash('Teacher@123', PASSWORD_DEFAULT);
$tid = [];
foreach ($names as $k => $n)
  $tid[$k] = ins('users', ['full_name' => $n, 'email' => "$k@pucit.edu.pk", 'password_hash' => $hash, 'role' => 'teacher']);

/* ---- semester, sections ---- */
$sem = ins('semesters', ['semester_name' => 'Fall 2026', 'start_date' => '2026-09-07', 'teaching_end_date' => '2027-01-01', 'end_date' => '2027-01-22']);
$secDef = ['F23' => ['F23', 'regular', 7, 55], 'F24' => ['F24', 'regular', 5, 58], 'F25' => ['F25', 'regular', 3, 60], 'F26R' => ['F26', 'regular', 1, 45], 'F26S' => ['F26', 'self_support', 1, 30]];
$st = [];
foreach ($secDef as $k => [$batch, $track, $no, $cnt]) {
  $s = ins('sections', ['program' => 'BCS', 'batch' => $batch, 'section_name' => 'A', 'study_track' => $track]);
  $st[$k] = ins('section_terms', ['section_id' => $s, 'semester_id' => $sem, 'semester_number' => $no, 'student_count' => $cnt]);
}

/* ---- rooms ---- */
$room = [];
$R = [
  ['Al-Khwarizmi Hall', 'hall', 80, 1],
  ['LAB A', 'lab', 65, 1],
  ['LAB B', 'lab', 100, 1],
  ['LAB C', 'lab', 48, 1],
  ['LAB D', 'lab', 48, 1],
  ['Old Seminar Hall', 'hall', 100, 1],
  ['New Seminar Hall', 'hall', 80, 1],
  ['Senate Hall', 'hall', 150, 0]
];
for ($i = 1; $i <= 16; $i++)
  $R[] = ["ROOM $i", 'classroom', 65, 1];
foreach ($R as [$n, $ty, $cap, $al])
  $room[$n] = ins('rooms', ['room_name' => $n, 'room_type' => $ty, 'capacity' => $cap, 'allow_classes' => $al]);

/* ---- courses ---- */
$C = [
  'COAL' => 'Computer Organization & Assembly Language',
  'MGT' => 'Introduction to Management',
  'CALC' => 'Calculus & Analytical Geometry',
  'DBS' => 'Database Systems',
  'OOP' => 'Object Oriented Programming',
  'QT3' => 'Quran Translation-III',
  'ICT' => 'Application of ICT',
  'ENG' => 'Functional English',
  'MATHDEF' => 'Math Deficiency - I',
  'PF' => 'Programming Fundamentals',
  'DISC' => 'Discrete Structure',
  'ECO' => 'Introduction to Economics',
  'PS' => 'Pakistan Studies',
  'QT1' => 'Quran Translation-I',
  'TBW' => 'Technical and Business Writing',
  'ENT' => 'Entrepreneurship',
  'PDC' => 'Parallel & Distributed Computing',
  'GDD' => 'Game Design and Development',
  'CV' => 'Computer Vision',
  'PP' => 'Professional Practices',
  'QT7' => 'Quran Translation-VII',
  'AOA' => 'Analysis of Algorithms',
  'AI' => 'Artificial Intelligence',
  'HCI' => 'Human Computer Interaction',
  'WEB' => 'Web Technologies',
  'TOA' => 'Theory of Automata and Formal Languages',
  'CA' => 'Computer Architecture',
  'QT5' => 'Quran Translation-V'
];
$cid = [];
foreach ($C as $k => $v)
  $cid[$k] = ins('courses', ['course_code' => $k, 'course_title' => $v]);

/* ---- timetable: [sections, course, type, teacher, weekday(1=Mon), start, end, room|null=online] ---- */
$tt = [];
$add = function (...$r) use (&$tt) {
  $tt[] = $r; };
// F25 (semester 3). NOTE: Mon Calculus moved to ROOM 3 (source sheet double-booked ROOM 2 with F26). Friday labs aligned to slot grid.
$add('F25', 'COAL', 'theory', 'sabaa', 1, '08:15', '10:15', 'ROOM 9');
$add('F25', 'MGT', 'theory', 'shahzad', 1, '10:15', '12:15', 'ROOM 10');
$add('F25', 'CALC', 'theory', 'komal', 1, '12:15', '13:45', 'ROOM 3');
$add('F25', 'DBS', 'theory', 'ahmed', 2, '10:15', '11:45', 'New Seminar Hall');
$add('F25', 'OOP', 'theory', 'nazar', 2, '12:15', '13:45', 'New Seminar Hall');
$add('F25', 'COAL', 'theory', 'sabaa', 3, '08:15', '10:15', 'ROOM 9');
$add('F25', 'DBS', 'theory', 'ahmed', 4, '10:15', '11:45', 'New Seminar Hall');
$add('F25', 'OOP', 'theory', 'nazar', 4, '12:15', '13:45', 'New Seminar Hall');
$add('F25', 'CALC', 'theory', 'komal', 5, '08:15', '09:45', 'ROOM 2');
$add('F25', 'OOP', 'lab', 'nazar', 5, '10:15', '12:15', 'LAB A');
$add('F25', 'DBS', 'lab', 'ahmed', 5, '14:15', '16:15', 'LAB A');
$add('F25', 'QT3', 'theory', 'asif', 6, '08:15', '09:45', null);
// F26 regular (semester 1, ROOM 2) + self support (ROOM 4); shared classes use 'F26R+F26S'
$add('F26R', 'ICT', 'theory', 'nasteen', 1, '10:15', '12:15', 'ROOM 2');
$add('F26R', 'ENG', 'theory', 'khadija', 1, '12:15', '13:45', 'ROOM 2');
$add('F26R+F26S', 'MATHDEF', 'theory', 'tba', 1, '14:15', '15:45', 'New Seminar Hall');
$add('F26R', 'PF', 'theory', 'mateen', 2, '10:15', '11:45', 'ROOM 2');
$add('F26R', 'DISC', 'theory', 'irfana', 2, '12:15', '13:45', 'ROOM 2');
$add('F26R', 'ECO', 'theory', 'afzal', 3, '08:15', '10:15', 'ROOM 2');
$add('F26R', 'ICT', 'theory', 'nasteen', 3, '10:15', '12:15', 'ROOM 2');
$add('F26R', 'ENG', 'theory', 'khadija', 3, '12:15', '13:45', 'ROOM 2');
$add('F26R+F26S', 'MATHDEF', 'theory', 'tba', 3, '14:15', '15:45', 'New Seminar Hall');
$add('F26R', 'PF', 'theory', 'mateen', 4, '10:15', '11:45', 'ROOM 2');
$add('F26R', 'DISC', 'theory', 'irfana', 4, '12:15', '13:45', 'ROOM 2');
$add('F26R', 'PF', 'lab', 'mateen', 5, '08:15', '11:15', 'LAB B');
$add('F26S', 'ICT', 'theory', 'nasteen', 1, '08:15', '10:15', 'ROOM 4');
$add('F26S', 'ENG', 'theory', 'khadija', 1, '10:15', '11:45', 'ROOM 4');
$add('F26S', 'ECO', 'theory', 'afzal', 2, '08:15', '10:15', 'ROOM 4');
$add('F26S', 'DISC', 'theory', 'irfana', 2, '10:15', '11:45', 'ROOM 4');
$add('F26S', 'PF', 'theory', 'mateen', 2, '12:15', '13:45', 'ROOM 4');
$add('F26S', 'ICT', 'theory', 'nasteen', 3, '08:15', '10:15', 'ROOM 4');
$add('F26S', 'ENG', 'theory', 'khadija', 3, '10:15', '11:45', 'ROOM 4');
$add('F26S', 'DISC', 'theory', 'irfana', 4, '10:15', '11:45', 'ROOM 4');
$add('F26S', 'PF', 'theory', 'mateen', 4, '12:15', '13:45', 'ROOM 4');
$add('F26S', 'PF', 'lab', 'mateen', 5, '14:15', '16:15', 'LAB B');
$add('F26R+F26S', 'QT1', 'theory', 'javed', 6, '08:15', '09:45', null);
$add('F26R+F26S', 'PS', 'theory', 'zubaida', 6, '10:15', '11:45', null);
// F23 (semester 7, ROOM 10)
$add('F23', 'TBW', 'theory', 'khadija', 1, '08:15', '09:45', 'ROOM 10');
$add('F23', 'TBW', 'theory', 'khadija', 3, '08:15', '09:45', 'ROOM 10');
$add('F23', 'ENT', 'theory', 'shahzad', 1, '12:15', '14:15', 'ROOM 10');
$add('F23', 'PP', 'theory', 'ahbutt', 3, '10:15', '12:15', 'ROOM 10');
foreach ([2, 4] as $d) {
  $add('F23', 'PDC', 'theory', 'aftab', $d, '08:15', '10:15', 'ROOM 10');
  $add('F23', 'GDD', 'theory', 'ehtisham', $d, '10:15', '12:15', 'ROOM 10');
  $add('F23', 'CV', 'theory', 'saadia', $d, '12:15', '14:15', 'ROOM 10');
}
$add('F23', 'QT7', 'theory', 'hafiz', 6, '08:15', '09:45', null);
// F24 (semester 5, ROOM 12)
$add('F24', 'AOA', 'theory', 'ammara', 1, '08:15', '09:45', 'ROOM 12');
$add('F24', 'AOA', 'theory', 'ammara', 5, '08:15', '09:45', 'ROOM 12');
foreach ([1, 3] as $d) {
  $add('F24', 'AI', 'theory', 'irfana', $d, '10:15', '12:15', 'ROOM 12');
  $add('F24', 'HCI', 'theory', 'tayyaba', $d, '12:15', '14:15', 'ROOM 12');
}
foreach ([2, 4] as $d) {
  $add('F24', 'WEB', 'theory', 'noman', $d, '08:15', '10:15', 'ROOM 12');
  $add('F24', 'TOA', 'theory', 'nasteen', $d, '10:15', '11:45', 'ROOM 12');
  $add('F24', 'CA', 'theory', 'zobia', $d, '12:15', '14:15', 'ROOM 12');
}
$add('F24', 'QT5', 'theory', 'hafiz', 6, '10:15', '11:45', null);

/* ---- allocations derived from the timetable ---- */
$al = [];
foreach ($tt as [$secs, $co, $ty, $te, $dow, $s, $e, $rm])
  foreach (explode('+', $secs) as $sk) {
    $key = "$sk|$co|$ty|$te";
    $al[$key]['n'] = ($al[$key]['n'] ?? 0) + 1;
    $al[$key]['m'] ??= to_min($e) - to_min($s);
  }
foreach ($al as $key => $v) {
  [$sk, $co, $ty, $te] = explode('|', $key);
  ins('course_allocations', ['course_id' => $cid[$co], 'teacher_id' => $tid[$te], 'section_term_id' => $st[$sk], 'session_type' => $ty, 'sessions_per_week' => $v['n'], 'duration_minutes' => $v['m']]);
}

/* ---- calendar events & weeks (declared BEFORE the timetable so exam/vacation days are skipped) ---- */
$ev = [
  ['Commencement of Classes Fall 2026 (1st – 3rd Semester)', 'classes', '2026-09-07', '2026-09-07', 0, 0, null],
  ['Last Date to Add/Withdraw a Course', 'deadline', '2026-09-16', '2026-09-16', 0, 0, 'Minimum course requirement in a semester is 15 Cr. Hrs. No withdrawal after this date.'],
  ['Mid Term Examination (after 7 teaching weeks)', 'exam', '2026-10-23', '2026-10-31', 0, 1, null],
  ['Application for Mid Term Resit Examination', 'deadline', '2026-10-23', '2026-10-31', 1, 0, 'Inform immediately when a paper is missed.'],
  ['Mid Term Paper Show & Graded Scripts Submission to Exam Branch', 'other', '2026-11-13', '2026-11-13', 0, 0, null],
  ['Winter Vacations', 'holiday', '2026-12-21', '2026-12-25', 1, 1, null],
  ['Display of Forced Withdrawal List (attendance shortage)', 'deadline', '2026-12-30', '2026-12-30', 0, 0, null],
  ['Sessional Finalization in CMS', 'deadline', '2027-01-01', '2027-01-01', 0, 0, null],
  ['End of Classes', 'classes', '2027-01-01', '2027-01-01', 0, 0, null],
  ['Final Term Examination (post mid term 8 teaching weeks)', 'exam', '2027-01-06', '2027-01-14', 0, 1, null],
  ['Application for Final Term Resit Examination', 'deadline', '2027-01-06', '2027-01-14', 1, 0, null],
  ['Course Folder Submission to PCO Office', 'deadline', '2027-01-19', '2027-01-19', 0, 0, null],
  ['Paper Show to Students & Award Lists to Exam Branch', 'other', '2027-01-19', '2027-01-19', 0, 0, null],
  ['Final Result Declaration', 'result', '2027-01-22', '2027-01-22', 0, 0, null],
  ['Commencement of Classes Spring 2027 (2nd – 8th Semester)', 'classes', '2027-01-25', '2027-01-25', 0, 0, null]
];
foreach ($ev as [$ti, $ty, $a, $b, $tent, $sus, $de])
  ins('calendar_events', ['semester_id' => $sem, 'title' => $ti, 'description' => $de, 'event_type' => $ty, 'start_date' => $a, 'end_date' => $b, 'is_tentative' => $tent, 'suspends_regular_classes' => $sus, 'created_by' => $admin, 'updated_by' => $admin]);
for ($i = 0; $i < 7; $i++) {
  $s = strtotime("2026-09-07 +$i weeks");
  ins('calendar_weeks', ['semester_id' => $sem, 'phase' => 'pre_mid', 'week_number' => $i + 1, 'start_date' => date('Y-m-d', $s), 'end_date' => date('Y-m-d', strtotime('+4 days', $s)), 'planned_lectures' => 2]);
}
for ($i = 0; $i < 7; $i++) {
  $s = strtotime("2026-11-02 +$i weeks");
  ins('calendar_weeks', ['semester_id' => $sem, 'phase' => 'post_mid', 'week_number' => $i + 1, 'start_date' => date('Y-m-d', $s), 'end_date' => date('Y-m-d', strtotime('+4 days', $s)), 'planned_lectures' => 2]);
}
ins('calendar_weeks', ['semester_id' => $sem, 'phase' => 'post_mid', 'week_number' => 8, 'start_date' => '2026-12-28', 'end_date' => '2027-01-01', 'planned_lectures' => 2, 'notes' => 'Winter vacations Dec 21–25 (tentative)']);
ins('announcements', ['semester_id' => $sem, 'section_term_id' => $st['F25'], 'kind' => 'quiz', 'title' => 'Database Systems – Quiz 1', 'body' => 'Chapters 1–3. Bring your student card.', 'event_date' => date('Y-m-d', strtotime('+7 days')), 'created_by' => $admin]);
ins('announcements', ['semester_id' => $sem, 'section_term_id' => $st['F24'], 'kind' => 'quiz', 'title' => 'Artificial Intelligence – Quiz 1', 'body' => 'Search algorithms.', 'event_date' => date('Y-m-d', strtotime('+10 days')), 'created_by' => $admin]);
ins('announcements', ['semester_id' => $sem, 'section_term_id' => null, 'kind' => 'info', 'title' => 'Welcome to Fall 2026', 'body' => 'Check the Overview tab regularly. Rescheduled, merged and cancelled classes are marked with badges.', 'event_date' => null, 'created_by' => $admin]);
ins('announcements', ['semester_id' => $sem, 'section_term_id' => null, 'kind' => 'notice', 'title' => 'Friday Jummah break', 'body' => 'No classes from 12:15 to 14:15 on Fridays.', 'event_date' => null, 'created_by' => $admin]);

/* ---- create the regular timetable through the SAME clash-checked code the website uses ---- */
$ok = 0;
$bad = 0;
foreach ($tt as [$secs, $co, $ty, $te, $dow, $s, $e, $rm]) {
  $r = series_create([
    'semester_id' => $sem,
    'course_id' => $cid[$co],
    'teacher_id' => $tid[$te],
    'room_id' => $rm ? $room[$rm] : null,
    'session_type' => $ty,
    'delivery_mode' => $rm ? 'physical' : 'online',
    'repeat_day' => $dow,
    'start_time' => $s,
    'end_time' => $e,
    'start_date' => '2026-09-07',
    'end_date' => '2027-01-01',
    'created_by' => $admin,
  ], array_map(fn($k) => $st[$k], explode('+', $secs)));
  if ($r['ok']) {
    $ok++;
    echo "OK   $secs $co " . DAYS[$dow] . " $s-$e : {$r['created']} meetings\n";
  } else {
    $bad++;
    echo "FAIL $secs $co " . DAYS[$dow] . " $s-$e : " . implode(' | ', array_slice($r['errors'], 0, 3)) . "\n";
  }
}
echo "\nDone. $ok timetable entries created, $bad failed.\n";
echo "Admin:   bcsf24a023@pucit.edu.pk / admin@23   (change this password after first login)\n";
echo "Teacher: mateen@pucit.edu.pk / Teacher@123  (every teacher uses Teacher@123)\n";
