<?php
// VIEW REPORTS: simple tables built from existing data (activities, assignments, attendance, users).
require __DIR__ . '/includes/auth.php';
$u = require_role(['super_admin', 'organizer']);
$isSA = $u['role'] === 'super_admin';

$acts = visible_activities($pdo, $u);
$filter = find_activity($pdo, $u, $_GET['activity_id'] ?? 0);
$ids = $filter ? [(int)$filter['activity_id']] : array_map(fn($a) => (int)$a['activity_id'], $acts);
$in = $ids ? implode(',', $ids) : '0';          // ids are integers from our own query

$summary = $pdo->query("SELECT a.activity_name, a.activity_date, a.location, a.max_volunteers,
        SUM(s.assignment_status = 'Assigned') AS n_assigned, SUM(s.assignment_status = 'Completed') AS n_completed, SUM(s.assignment_status = 'Cancelled') AS n_cancelled,
        (SELECT COALESCE(SUM(t.hours_rendered), 0) FROM attendance t JOIN assignments x ON x.assignment_id = t.assignment_id WHERE x.activity_id = a.activity_id) AS hours
      FROM activities a LEFT JOIN assignments s ON s.activity_id = a.activity_id WHERE a.activity_id IN ($in) GROUP BY a.activity_id ORDER BY a.activity_date DESC")->fetchAll();

$assign = $pdo->query("SELECT a.activity_name, u.first_name, u.middle_name, u.last_name, s.role, s.assignment_status
      FROM assignments s JOIN activities a ON a.activity_id = s.activity_id JOIN users u ON u.user_id = s.user_id
      WHERE a.activity_id IN ($in) ORDER BY a.activity_name, u.last_name")->fetchAll();

$att = $pdo->query("SELECT t.*, a.activity_name, u.first_name, u.middle_name, u.last_name
      FROM attendance t JOIN assignments s ON s.assignment_id = t.assignment_id JOIN activities a ON a.activity_id = s.activity_id JOIN users u ON u.user_id = s.user_id
      WHERE a.activity_id IN ($in) ORDER BY t.attendance_date DESC, u.last_name")->fetchAll();

$hours = $pdo->query("SELECT u.first_name, u.middle_name, u.last_name,
        COUNT(DISTINCT CASE WHEN s.assignment_status = 'Completed' THEN s.assignment_id END) AS completed,
        SUM(t.status = 'Present') AS present, SUM(t.status = 'Late') AS late, SUM(t.status = 'Absent') AS absent,
        COALESCE(SUM(t.hours_rendered), 0) AS hours
      FROM assignments s JOIN users u ON u.user_id = s.user_id LEFT JOIN attendance t ON t.assignment_id = s.assignment_id
      WHERE s.activity_id IN ($in) AND s.assignment_status <> 'Cancelled' GROUP BY u.user_id ORDER BY hours DESC, u.last_name")->fetchAll();

$pageTitle = 'View Reports';
$pageSub = $filter ? 'Report for: ' . $filter['activity_name'] : ($isSA ? 'All activities.' : 'Your activities.');
require __DIR__ . '/includes/header.php';
?>
<form class="card section-card no-print" method="get"><div class="card-body row g-2 align-items-center">
  <div class="col-md-6"><select class="form-select" name="activity_id" onchange="this.form.submit()">
    <option value="">All <?= $isSA ? '' : 'my ' ?>activities</option>
    <?php foreach ($acts as $a): ?><option value="<?= (int)$a['activity_id'] ?>" <?= $filter && $filter['activity_id'] == $a['activity_id'] ? 'selected' : '' ?>><?= h($a['activity_name']) ?> (<?= h($a['activity_date']) ?>)</option><?php endforeach; ?>
  </select></div>
  <div class="col-auto ms-auto"><button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></div>
</div></form>

<h2 class="h6 text-uppercase text-muted">Activity summary</h2>
<div class="card section-card"><div class="table-responsive"><table class="table table-sm align-middle">
  <thead><tr><th>Activity</th><th>Date</th><th>Location</th><th>Max</th><th>Assigned</th><th>Completed</th><th>Cancelled</th><th>Hours</th></tr></thead><tbody>
  <?php foreach ($summary as $r): ?><tr><td class="fw-semibold"><?= h($r['activity_name']) ?></td><td><?= h($r['activity_date']) ?></td><td><?= h($r['location'] ?: '—') ?></td><td><?= (int)$r['max_volunteers'] ?></td>
    <td><?= (int)$r['n_assigned'] ?></td><td><?= (int)$r['n_completed'] ?></td><td><?= (int)$r['n_cancelled'] ?></td><td><?= number_format((float)$r['hours'], 2) ?></td></tr><?php endforeach; ?>
  <?php if (!$summary): ?><tr><td colspan="8"><div class="empty">No data.</div></td></tr><?php endif; ?>
</tbody></table></div></div>

<h2 class="h6 text-uppercase text-muted">Volunteer hours &amp; participation</h2>
<div class="card section-card"><div class="table-responsive"><table class="table table-sm align-middle">
  <thead><tr><th>Volunteer</th><th>Completed assignments</th><th>Present</th><th>Late</th><th>Absent</th><th>Total hours</th></tr></thead><tbody>
  <?php foreach ($hours as $r): ?><tr><td class="fw-semibold"><?= h(full_name($r)) ?></td><td><?= (int)$r['completed'] ?></td><td><?= (int)$r['present'] ?></td><td><?= (int)$r['late'] ?></td><td><?= (int)$r['absent'] ?></td><td><?= number_format((float)$r['hours'], 2) ?></td></tr><?php endforeach; ?>
  <?php if (!$hours): ?><tr><td colspan="6"><div class="empty">No data.</div></td></tr><?php endif; ?>
</tbody></table></div></div>

<h2 class="h6 text-uppercase text-muted">Volunteer assignments</h2>
<div class="card section-card"><div class="table-responsive"><table class="table table-sm align-middle">
  <thead><tr><th>Activity</th><th>Volunteer</th><th>Activity role</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($assign as $r): ?><tr><td><?= h($r['activity_name']) ?></td><td class="fw-semibold"><?= h(full_name($r)) ?></td><td><?= h($r['role']) ?></td><td><span class="badge <?= badge_class($r['assignment_status']) ?>"><?= h($r['assignment_status']) ?></span></td></tr><?php endforeach; ?>
  <?php if (!$assign): ?><tr><td colspan="4"><div class="empty">No data.</div></td></tr><?php endif; ?>
</tbody></table></div></div>

<h2 class="h6 text-uppercase text-muted">Attendance records</h2>
<div class="card section-card"><div class="table-responsive"><table class="table table-sm align-middle">
  <thead><tr><th>Date</th><th>Activity</th><th>Volunteer</th><th>Status</th><th>In</th><th>Out</th><th>Hours</th><th>Remarks</th></tr></thead><tbody>
  <?php foreach ($att as $r): ?><tr><td><?= h($r['attendance_date']) ?></td><td><?= h($r['activity_name']) ?></td><td class="fw-semibold"><?= h(full_name($r)) ?></td>
    <td><span class="badge <?= badge_class($r['status']) ?>"><?= h($r['status']) ?></span></td><td><?= h($r['check_in_time'] ? substr($r['check_in_time'], 0, 5) : '—') ?></td>
    <td><?= h($r['check_out_time'] ? substr($r['check_out_time'], 0, 5) : '—') ?></td><td><?= number_format((float)$r['hours_rendered'], 2) ?></td><td><?= h($r['remarks'] ?: '—') ?></td></tr><?php endforeach; ?>
  <?php if (!$att): ?><tr><td colspan="8"><div class="empty">No data.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
