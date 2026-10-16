<?php
// MANAGE ATTENDANCE: check-in / check-out / hours / status / remarks (attendance table).
// Everything on this page goes through the REST API (assets/js/attendance.js):
//   GET api/attendance.php?activity_id=, POST api/attendance.php (new record), PUT api/attendance.php (edit record).
// Present or Late -> hours = check-out minus check-in, and the assignment becomes Completed.
// Absent -> hours_rendered = 0.00. The API keeps assignment status and attendance status consistent.
require __DIR__ . '/includes/auth.php';
$u = require_role(['super_admin', 'organizer']);
$a = find_activity($pdo, $u, $_GET['activity_id'] ?? 0);

$pageTitle = 'Manage Attendance';
$pageSub = $a ? $a['activity_name'] . ' · ' . $a['activity_date'] : 'Choose an activity to record attendance.';
require __DIR__ . '/includes/header.php';
if (!$a) { activity_picker(visible_activities($pdo, $u), 'attendance.php', 'Attendance is recorded for volunteers who are assigned to the activity.'); require __DIR__ . '/includes/footer.php'; exit; }
?>
<div id="attApp" data-activity-id="<?= (int)$a['activity_id'] ?>">
<div class="card section-card"><div class="table-responsive" id="attForm"><div class="empty">Loading…</div></div></div>
<div class="card section-card"><div class="card-body pb-0"><h2 class="h6">Attendance records</h2></div><div class="table-responsive" id="attRecords"><div class="empty">Loading…</div></div></div>
</div>
<?php $extraScript = '<script src="assets/js/attendance.js"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
