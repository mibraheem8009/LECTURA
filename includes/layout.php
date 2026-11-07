<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

function icon(string $n): string
{
    static $i = [
        'overview' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'timetable' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'availability' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 21v-5h6v5M8 7h2M14 7h2M8 11h2M14 11h2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'back'     => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 16l-4-4 4-4M6 12h10"/>',
        'reserve'  => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'requests' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.2 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.8A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.2Z"/>',
        'admin'    => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'bookings' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . ($i[$n] ?? $i['overview']) . '</svg>';
}
function nav_items(): array
{
    $v = [
        ['overview', 'Overview', 'student/overview.php'],
        ['timetable', 'Timetable', 'student/timetable.php'],
        ['availability', 'Availability', 'student/availability.php'],
        ['calendar', 'Semester Calendar', 'student/calendar.php']
    ];
    if (role() === 'teacher') {
        $v[0] = ['overview', 'Teacher Overview', 'teacher/overview.php'];
        $v[] = ['reserve', 'Reserve Slot', 'teacher/reserve.php'];
        $v[] = ['requests', 'Slot Requests', 'teacher/requests.php'];
    }
    if (role() === 'admin') $v = array_merge([
        ['admin', 'Admin Home', 'admin/index.php'],
        ['bookings', 'Bookings', 'admin/bookings.php'],
        ['series', 'New Weekly Class', 'admin/series.php'],
        ['crud', 'Data Tables', 'admin/crud.php']
    ], $v);
    $v[] = ['back', 'Back to Login', 'logout.php'];
    return $v;
}
function page_start(string $title, string $active = ''): void
{
    $ico = ['series' => 'reserve', 'crud' => 'admin'];
    $name = $_SESSION['name'] ?? 'User';
    $ini = strtoupper(mb_substr(preg_replace('/^(Dr|Mr|Ms)\.\s*/', '', $name), 0, 1) . (explode(' ', trim(preg_replace('/^(Dr|Mr|Ms)\.\s*/', '', $name)))[1][0] ?? ''));
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' · Lectura</title>'
        . '<link rel="stylesheet" href="' . url('assets/css/style.css') . '"></head><body data-api="' . url('teacher/api_free.php') . '"><div class="app"><aside class="sidebar">'
        . '<a class="brand" href="#"><span class="logo">L</span><span class="lbl">Lectura</span></a>';
    foreach (nav_items() as [$key, $label, $href])
        echo '<a href="' . url($href) . '" class="' . ($key === $active ? 'on' : '') . '">' . icon($ico[$key] ?? $key) . '<span class="lbl">' . e($label) . '</span></a>';
    echo '</aside><div class="main"><header class="topbar"><div class="ttl">' . e($title) . '</div><div class="who"><span>' . e($name) . ' · ' . e(ucfirst(role() ?? '')) . '</span><span class="av">' . e($ini) . '</span></div></header><main class="content">' . flashes();
}
function page_end(string $js = ''): void
{
    echo '</main></div></div><script src="' . url('assets/js/app.js') . '"></script>' . $js . '</body></html>';
}
