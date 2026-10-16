<?php
// SELECT & ASSIGN VOLUNTEERS: human review of the AI recommendations, then assignment with an activity role.
// Everything on this page goes through the REST API (assets/js/assign.js):
//   GET api/recommendations.php, GET api/assignments.php, POST api/assignments.php (Assign), PUT api/assignments.php (Cancel Assignment).
// Cancel Assignment only sets assignments.assignment_status = 'Cancelled' (the user account is never touched).
require __DIR__ . '/includes/auth.php';
$u = require_role(['super_admin', 'organizer']);
$a = find_activity($pdo, $u, $_GET['activity_id'] ?? 0);

$pageTitle = 'Select & Assign Volunteers';
$pageSub = $a ? $a['activity_name'] . ' · ' . $a['activity_date'] : 'Choose an activity to review recommendations and assign volunteers.';
require __DIR__ . '/includes/header.php';
if (!$a) { activity_picker(visible_activities($pdo, $u), 'assign.php', 'You decide who is assigned. Recommendations are only suggestions.'); require __DIR__ . '/includes/footer.php'; exit; }
$aid = (int)$a['activity_id'];
?>
<div id="assignApp" data-activity-id="<?= $aid ?>" data-highlight="<?= (int)($_GET['user_id'] ?? 0) ?>">
<div class="notice mb-3"><i class="bi bi-info-circle"></i> <strong>RECOMMENDED ≠ AUTOMATICALLY ASSIGNED.</strong> A volunteer is only assigned when you enter an activity role and press Assign.</div>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
  <span class="badge text-bg-light border fs-6" id="slots">… volunteer slots used</span>
  <a class="btn btn-sm btn-outline-primary" href="matching.php?activity_id=<?= $aid ?>"><i class="bi bi-stars"></i> Back to AI Matching</a>
</div>

<div class="card section-card"><div class="card-body pb-0"><h2 class="h6">Recommended volunteers</h2></div><div class="table-responsive" id="recBox"><div class="empty">Loading…</div></div></div>

<div class="card section-card"><div class="card-body pb-0"><h2 class="h6">Assignments for this activity</h2></div><div class="table-responsive" id="assignBox"><div class="empty">Loading…</div></div></div>
</div>
<?php $extraScript = '<script src="assets/js/assign.js"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
