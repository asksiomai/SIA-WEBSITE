<?php
// One dashboard file; the content shown depends on users.role (checked on the server).
require __DIR__ . '/includes/auth.php';
$u = require_role(['volunteer', 'organizer', 'super_admin']);
$uid = (int)$u['user_id'];

function scalar(PDO $pdo, $sql, $params = []) { $st = $pdo->prepare($sql); $st->execute($params); return (float)$st->fetchColumn(); }

$stats = [];
if ($u['role'] === 'super_admin') {
    $stats = [
        ['Admins / Organizers', (int)scalar($pdo, "SELECT COUNT(*) FROM users WHERE role='organizer'"), 'bi-person-badge'],
        ['Volunteers', (int)scalar($pdo, "SELECT COUNT(*) FROM users WHERE role='volunteer'"), 'bi-people'],
        ['Activities', (int)scalar($pdo, 'SELECT COUNT(*) FROM activities'), 'bi-calendar-event'],
        ['Active assignments', (int)scalar($pdo, "SELECT COUNT(*) FROM assignments WHERE assignment_status IN ('Assigned','Completed')"), 'bi-person-check'],
    ];
    $sub = 'Full overview of the Community Volunteer Management System.';
} elseif ($u['role'] === 'organizer') {
    $stats = [
        ['My activities', (int)scalar($pdo, 'SELECT COUNT(*) FROM activities WHERE created_by = ?', [$uid]), 'bi-calendar-event'],
        ['Registered volunteers', (int)scalar($pdo, "SELECT COUNT(*) FROM users WHERE role='volunteer'"), 'bi-people'],
        ['Volunteers assigned', (int)scalar($pdo, "SELECT COUNT(*) FROM assignments a JOIN activities ac ON ac.activity_id=a.activity_id WHERE ac.created_by = ? AND a.assignment_status IN ('Assigned','Completed')", [$uid]), 'bi-person-check'],
        ['Hours recorded', number_format(scalar($pdo, 'SELECT COALESCE(SUM(t.hours_rendered),0) FROM attendance t JOIN assignments a ON a.assignment_id=t.assignment_id JOIN activities ac ON ac.activity_id=a.activity_id WHERE ac.created_by = ?', [$uid]), 2), 'bi-clock-history'],
    ];
    $sub = 'Create activities, review recommended volunteers, and track attendance.';
} else {
    $stats = [
        ['Activities assigned', (int)scalar($pdo, "SELECT COUNT(*) FROM assignments WHERE user_id = ? AND assignment_status IN ('Assigned','Completed')", [$uid]), 'bi-calendar-check'],
        ['Hours rendered', number_format(scalar($pdo, 'SELECT COALESCE(SUM(t.hours_rendered),0) FROM attendance t JOIN assignments a ON a.assignment_id=t.assignment_id WHERE a.user_id = ?', [$uid]), 2), 'bi-clock-history'],
        ['My skills', (int)scalar($pdo, 'SELECT COUNT(*) FROM volunteer_skills WHERE user_id = ?', [$uid]), 'bi-patch-check'],
        ['Upcoming activities', (int)scalar($pdo, 'SELECT COUNT(*) FROM activities WHERE activity_date >= CURDATE()'), 'bi-calendar-event'],
    ];
    $sub = 'Keep your profile up to date so organizers can find the right activities for you.';
}
$pageTitle = 'Welcome, ' . $u['first_name'];
$pageSub = $sub;
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3 mb-4">
  <?php foreach ($stats as $s): ?>
    <div class="col-6 col-xl-3"><div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
      <span class="stat-icon"><i class="bi <?= h($s[2]) ?>"></i></span>
      <div><div class="stat-num"><?= h($s[1]) ?></div><div class="text-muted small"><?= h($s[0]) ?></div></div>
    </div></div></div>
  <?php endforeach; ?>
</div>
<h2 class="h6 text-uppercase text-muted mb-3">Your functions</h2>
<div class="row g-3">
  <?php foreach (menu_for($u['role']) as $m): ?>
    <div class="col-md-6 col-xl-4"><a class="card shortcut" href="<?= h($m[1]) ?>"><div class="card-body">
      <span class="shortcut-icon"><i class="bi <?= h($m[2]) ?>"></i></span>
      <h3><?= h($m[0]) ?></h3><p><?= h($m[3]) ?></p>
    </div></a></div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
