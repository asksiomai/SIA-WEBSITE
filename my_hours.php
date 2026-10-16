<?php
// VIEW VOLUNTEER HOURS (volunteer): own hours + participation history from assignments, attendance and activities.
// Loaded from the REST API: GET api/attendance.php (assets/js/my_hours.js). A volunteer only ever receives their own records.
require __DIR__ . '/includes/auth.php';
$u = require_role(['volunteer']);

$pageTitle = 'View Volunteer Hours';
$pageSub = 'Your hours and participation history.';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body d-flex align-items-center gap-3"><span class="stat-icon"><i class="bi bi-clock-history"></i></span><div><div class="stat-num" id="totalHours">—</div><div class="text-muted small">Total hours rendered</div></div></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body d-flex align-items-center gap-3"><span class="stat-icon"><i class="bi bi-check2-circle"></i></span><div><div class="stat-num" id="doneCount">—</div><div class="text-muted small">Completed activities</div></div></div></div></div>
</div>
<div class="card section-card"><div class="table-responsive" id="histBox"><div class="empty">Loading…</div></div></div>
<?php $extraScript = '<script src="assets/js/my_hours.js"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
