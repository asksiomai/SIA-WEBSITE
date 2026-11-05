<?php
// MY ACHIEVEMENTS (volunteer): badges earned from REAL attendance records (tables: attendance, assignments, activities).
// What counts: attendance marked Present or Late on an assignment whose status is Completed.
// What does NOT count: Absent records, Cancelled or still-Assigned (not attended) assignments.
// Nothing is stored: the badges and their dates are calculated from the records every time the page opens.
// To change a badge, edit the two lists below: [how many hours / activities, badge name, icon].
require __DIR__ . '/includes/auth.php';
$u = require_role(['volunteer']);
$uid = (int)$u['user_id'];

$hourBadges = [
    [5,   'Getting Started',   'bi-clock-history'],
    [10,  'Steady Helper',     'bi-stopwatch'],
    [25,  'Community Builder', 'bi-hourglass-split'],
    [50,  'Service Leader',    'bi-trophy-fill'],
    [100, 'Service Champion',  'bi-gem'],
];
$activityBadges = [
    [1,  'First Step', 'bi-flag-fill'],
    [3,  'Regular',    'bi-people-fill'],
    [5,  'Committed',  'bi-shield-check'],
    [10, 'Veteran',    'bi-patch-check-fill'],
];

// Every counted attendance record of this volunteer, oldest first (so we can see WHEN each milestone was reached).
$st = $pdo->prepare("SELECT t.attendance_date, t.hours_rendered, s.assignment_id
                       FROM attendance t
                       JOIN assignments s ON s.assignment_id = t.assignment_id
                      WHERE s.user_id = ? AND s.assignment_status = 'Completed' AND t.status IN ('Present', 'Late')
                      ORDER BY t.attendance_date ASC, t.attendance_id ASC");
$st->execute([$uid]);

$totalHours = 0.0;
$completed = 0;
$seen = [];
$hourDate = [];       // badge index => date the hours goal was reached
$activityDate = [];   // badge index => date the activity goal was reached
foreach ($st->fetchAll() as $r) {
    $totalHours = round($totalHours + (float)$r['hours_rendered'], 2);
    if (!isset($seen[$r['assignment_id']])) { $seen[$r['assignment_id']] = true; $completed++; }
    foreach ($hourBadges as $i => $b)
        if (!isset($hourDate[$i]) && $totalHours >= $b[0]) $hourDate[$i] = $r['attendance_date'];
    foreach ($activityBadges as $i => $b)
        if (!isset($activityDate[$i]) && $completed >= $b[0]) $activityDate[$i] = $r['attendance_date'];
}
$earned = count($hourDate) + count($activityDate);

// One group of badge cards. $dates = earned badges (index => date). $have = the volunteer's current number.
function badge_group($title, array $badges, array $dates, $have, $unit)
{
    echo '<h2 class="h6 text-uppercase text-muted mb-3">' . h($title) . '</h2><div class="row g-3 mb-4">';
    foreach ($badges as $i => $b) {
        $isEarned = isset($dates[$i]);
        $goal = $unit === 'hours' ? $b[0] . ' hours' : $b[0] . ($b[0] === 1 ? ' completed activity' : ' completed activities');
        $left = $b[0] - $have;
        echo '<div class="col-6 col-md-4 col-xl-3"><div class="card h-100 achievement ' . ($isEarned ? 'earned' : 'locked') . '"><div class="card-body text-center">'
            . '<div class="medal medal-t' . ($i + 1) . '" aria-hidden="true"><i class="bi ' . h($b[2]) . '"></i></div>'
            . '<h3 class="h6 mt-3 mb-1">' . h($b[1]) . '</h3>'
            . '<div class="small text-muted">' . h($goal) . '</div>';
        if ($isEarned)
            echo '<div class="small mt-2"><span class="badge text-bg-success">Earned</span><br><span class="text-muted">' . h(date('F j, Y', strtotime($dates[$i]))) . '</span></div>';
        else
            echo '<div class="small mt-2 text-muted">Locked · ' . h($unit === 'hours' ? number_format($left, 2) . ' more hours' : $left . ($left === 1 ? ' more activity' : ' more activities')) . '</div>';
        echo '</div></div></div>';
    }
    echo '</div>';
}

$pageTitle = 'My Achievements';
$pageSub = 'Badges earned from your completed volunteer hours and activities.';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-4"><div class="card stat-card"><div class="card-body d-flex align-items-center gap-3"><span class="stat-icon"><i class="bi bi-clock-history"></i></span><div><div class="stat-num"><?= number_format($totalHours, 2) ?></div><div class="text-muted small">Total completed hours</div></div></div></div></div>
  <div class="col-sm-6 col-xl-4"><div class="card stat-card"><div class="card-body d-flex align-items-center gap-3"><span class="stat-icon"><i class="bi bi-check2-circle"></i></span><div><div class="stat-num"><?= (int)$completed ?></div><div class="text-muted small">Completed activities</div></div></div></div></div>
  <div class="col-sm-6 col-xl-4"><div class="card stat-card"><div class="card-body d-flex align-items-center gap-3"><span class="stat-icon"><i class="bi bi-award"></i></span><div><div class="stat-num"><?= (int)$earned ?> / <?= count($hourBadges) + count($activityBadges) ?></div><div class="text-muted small">Badges earned</div></div></div></div></div>
</div>
<?php if ($completed === 0): ?>
  <div class="notice mb-4"><i class="bi bi-info-circle"></i> No badges yet. Once an organizer records that you attended an activity (Present or Late), your hours and badges appear here.</div>
<?php endif; ?>
<?php badge_group('Volunteer hours', $hourBadges, $hourDate, $totalHours, 'hours'); ?>
<?php badge_group('Participation', $activityBadges, $activityDate, $completed, 'activities'); ?>
<div class="small text-muted">Only attendance marked Present or Late counts. Absent records and cancelled assignments are not included.</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
