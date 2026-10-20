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
            ['Create Activities', 'activities.php', 'bi-calendar-plus', 'Create community activities.'],
            ['Add Requirements', 'requirements.php', 'bi-list-check', 'Set the skills and levels each activity needs.'],
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
        ['View Volunteer Hours', 'my_hours.php', 'bi-clock-history', 'Your hours and participation history.'],
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
            . '<span><strong>' . h($a['activity_name']) . '</strong><br><small class="text-muted">' . h($a['activity_date'] ?: 'No date') . ' &middot; ' . h($a['location'] ?: 'No location') . '</small></span>'
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
