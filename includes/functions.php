<?php
// Small helper functions used by every page.
const LEVELS = ['Beginner', 'Intermediate', 'Advanced'];

function h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}
function flash($type, $msg)
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function full_name(array $r)
{
    return trim($r['first_name'] . ' ' . (!empty($r['middle_name']) ? $r['middle_name'] . ' ' : '') . $r['last_name']);
}
function role_label($role)
{
    return ['super_admin' => 'Super Admin', 'organizer' => 'Admin / Organizer', 'volunteer' => 'Volunteer'][$role] ?? $role;
}
// Adds ?v=<last modified time> to a CSS/JS path so browsers load the newest file after an update.
function asset($path)
{
    $file = __DIR__ . '/../' . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}
// One password rule for registration and "change password": at least 8 characters, and ONLY
// letters (A-Z, a-z), numbers (0-9) and periods (.). Spaces and every other symbol are rejected.
// The password is never trimmed, cleaned or changed: it is either accepted exactly as typed, or rejected.
// Same rule as the browser check: ^[A-Za-z0-9.]{8,}$   (\z = real end of text, so a trailing new line is not allowed either)
const PASSWORD_CHARS_MESSAGE = 'Password can only contain letters (A-Z, a-z), numbers (0-9) and periods (.). Spaces and other special characters are not allowed.';
function password_error($password)
{
    $password = (string) $password;
    if (preg_match('/[^A-Za-z0-9.]/', $password))
        return PASSWORD_CHARS_MESSAGE;
    if (strlen($password) < 8)
        return 'Password must be at least 8 characters.';
    return null;
}
// Does this table have this column? Used so the volunteer pages keep working on a database that has not had
// migrations/001_schedule_and_other_skills.sql applied yet (users.other_skills, activities.end_date).
// Table and column names are our own constants, never user input. The answer is cached for the request.
function db_has_column(PDO $pdo, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        $st = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ' . $pdo->quote($column));
        $cache[$key] = (bool) $st->fetch();
    }
    return $cache[$key];
}
// "Other Skills" (free text typed by a volunteer; NOT part of the official skills list and NOT used by AI matching).
// Accepts commas or new lines between skills. Returns [normalized text or null, error message or null].
function normalize_other_skills($raw)
{
    $items = [];
    $seen = [];
    foreach (preg_split('/[,\r\n]+/', (string) $raw) as $part) {
        $part = trim(preg_replace('/\s+/', ' ', $part));
        if ($part === '')
            continue;
        $len = function_exists('mb_strlen') ? mb_strlen($part, 'UTF-8') : strlen($part);
        if ($len > 60)
            return [null, 'Each other skill must be 60 characters or fewer.'];
        $key = function_exists('mb_strtolower') ? mb_strtolower($part, 'UTF-8') : strtolower($part);
        if (isset($seen[$key]))
            continue;
        $seen[$key] = true;
        $items[] = $part;
    }
    if (count($items) > 20)
        return [null, 'Please list at most 20 other skills.'];
    return [$items ? implode(', ', $items) : null, null];
}
// ---- Activity schedule helpers: start date (activity_date), end date, start time, end time ----
// "8:00 AM" style clock text, or null when no time is saved.
function format_clock($t)
{
    if (!$t)
        return null;
    $ts = strtotime('2000-01-01 ' . $t);
    return $ts ? ltrim(date('h:i A', $ts), '0') : null;
}
// "November 15, 2026" / "November 15–17, 2026" / "November 30 – December 2, 2026" / across years.
// Older activities without an end date are shown as a single day.
function format_date_range($start, $end)
{
    $s = $start ? strtotime($start) : false;
    if (!$s)
        return 'No date';
    $e = $end ? strtotime($end) : false;
    if (!$e || $e <= $s)
        return date('F j, Y', $s);
    if (date('Y-m', $s) === date('Y-m', $e))
        return date('F j', $s) . '–' . date('j, Y', $e);
    if (date('Y', $s) === date('Y', $e))
        return date('F j', $s) . ' – ' . date('F j, Y', $e);
    return date('F j, Y', $s) . ' – ' . date('F j, Y', $e);
}
function format_time_range($start, $end)
{
    $a = format_clock($start);
    $b = format_clock($end);
    if ($a && $b)
        return $a . ' – ' . $b;
    return $a ?: ($b ?: 'Time not set');
}
// One line for pages that print a plain-text schedule.
function schedule_text(array $a)
{
    return format_date_range($a['activity_date'] ?? null, $a['end_date'] ?? null) . ' · ' . format_time_range($a['start_time'] ?? null, $a['end_time'] ?? null);
}
function csrf_token()
{
    if (empty($_SESSION['csrf']))
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

// Sidebar + dashboard shortcuts per role (taken from the flowchart): [label, page, icon, description]
function menu_for($role)
{
    if ($role === 'super_admin')
        return [
            ['Manage Admins / Organizers', 'organizers.php', 'bi-person-badge', 'View organizer accounts and change their role.'],
            ['Manage Volunteers', 'volunteers.php', 'bi-people', 'See registered volunteers and promote them to Admin / Organizer.'],
            ['Manage Skills', 'skills.php', 'bi-tools', 'Maintain the list of skills used by volunteers and activities.'],
            ['Manage Activities', 'activities.php', 'bi-calendar-event', 'View, edit and manage all community activities.'],
            ['Generate AI Matching', 'matching.php', 'bi-stars', 'Get recommended volunteers with compatibility scores.'],
            ['Select & Assign Volunteers', 'assign.php', 'bi-person-check', 'Review recommendations, then assign volunteers and roles.'],
            ['Manage Attendance', 'attendance.php', 'bi-clipboard-check', 'Record check-in, check-out and hours rendered.'],
            ['View Reports', 'reports.php', 'bi-bar-chart-line', 'Assignments, attendance, hours and participation history.'],
        ];
    if ($role === 'organizer')
        return [
            ['Manage Volunteers', 'volunteers.php', 'bi-people', 'See registered volunteers, their skills, interests and availability.'],
            ['Manage Skills', 'skills.php', 'bi-tools', 'Maintain the list of skills used by volunteers and activities.'],
            ['See Activities', 'see_activities.php', 'bi-calendar-event', 'View all community activities and their details.'],
            ['Create Activities', 'activities.php', 'bi-calendar-plus', 'Create community activities and set the skills each one requires.'],
            ['Generate AI Matching', 'matching.php', 'bi-stars', 'Get recommended volunteers with compatibility scores.'],
            ['Select & Assign Volunteer', 'assign.php', 'bi-person-check', 'Review recommendations, then assign volunteers and roles.'],
            ['Manage Attendance', 'attendance.php', 'bi-clipboard-check', 'Record check-in, check-out and hours rendered.'],
            ['View Reports', 'reports.php', 'bi-bar-chart-line', 'Assignments, attendance, hours and participation history.'],
        ];
    return [
        ['Manage Profile', 'profile.php', 'bi-person-circle', 'Update your personal and contact information.'],
        ['Add Skills', 'my_skills.php', 'bi-patch-plus', 'Add your skills and proficiency levels.'],
        ['Set Interests', 'interests.php', 'bi-heart', 'Tell us your interests and when you are available.'],
        ['View Activities', 'my_activities.php', 'bi-calendar-event', 'Browse community activities and their requirements.'],
        ['AI Recommendations', 'my_recommendations.php', 'bi-stars', 'Upcoming activities that match your skills.'],
        ['View Volunteer Hours', 'my_hours.php', 'bi-clock-history', 'Your hours and participation history.'],
        ['My Achievements', 'my_achievements.php', 'bi-award', 'Badges earned from your completed volunteer hours.'],
    ];
}

// Activities the user may work with: Super Admin = all, Admin / Organizer = the ones they created.
function visible_activities(PDO $pdo, array $u)
{
    if ($u['role'] === 'super_admin') {
        return $pdo->query('SELECT * FROM activities ORDER BY activity_date DESC, activity_id DESC')->fetchAll();
    }
    $st = $pdo->prepare('SELECT * FROM activities WHERE created_by = ? ORDER BY activity_date DESC, activity_id DESC');
    $st->execute([$u['user_id']]);
    return $st->fetchAll();
}
function find_activity(PDO $pdo, array $u, $id)
{
    $id = (int) $id;
    if ($id <= 0)
        return null;
    $st = $pdo->prepare('SELECT * FROM activities WHERE activity_id = ?');
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a)
        return null;
    if ($u['role'] !== 'super_admin' && (int) $a['created_by'] !== (int) $u['user_id'])
        return null;
    return $a;
}
// Count assignments that occupy a slot in the activity.
function active_assignment_count(PDO $pdo, $activity_id)
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE activity_id = ? AND assignment_status IN ('Assigned','Completed')");
    $st->execute([$activity_id]);
    return (int) $st->fetchColumn();
}
// "Choose an activity" list, shown when a page needs an activity first.
function activity_picker(array $acts, $page, $hint)
{
    echo '<div class="card section-card"><div class="card-body"><h2 class="h6 mb-1">Choose an activity</h2><p class="text-muted small mb-3">' . h($hint) . '</p>';
    if (!$acts) {
        echo '<div class="empty">No activities yet. Create an activity first.</div></div></div>';
        return;
    }
    echo '<div class="list-group list-group-flush">';
    foreach ($acts as $a) {
        echo '<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="' . h($page) . '?activity_id=' . (int) $a['activity_id'] . '">'
            . '<span><strong>' . h($a['activity_name']) . '</strong><br><small class="text-muted">' . h(schedule_text($a)) . ' &middot; ' . h($a['location'] ?: 'No location') . '</small></span>'
            . '<i class="bi bi-chevron-right"></i></a>';
    }
    echo '</div></div></div>';
}
function badge_class($status)
{
    return [
        'Assigned' => 'text-bg-primary',
        'Completed' => 'text-bg-success',
        'Cancelled' => 'text-bg-secondary',
        'Present' => 'text-bg-success',
        'Late' => 'text-bg-warning',
        'Absent' => 'text-bg-danger'
    ][$status] ?? 'text-bg-light';
}
