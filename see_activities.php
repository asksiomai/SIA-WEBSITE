<?php
// SEE ACTIVITIES (organizer): read-only list of ALL community activities (every organizer's), with a details window.
// Loaded from the REST API: GET api/activities.php?scope=all (assets/js/see_activities.js).
// Editing, attendance, matching and assigning stay on their own pages and only work for the organizer's own activities.
require __DIR__ . '/includes/auth.php';
$u = require_role(['organizer']);

$pageTitle = 'See Activities';
$pageSub = 'All community activities. Click an activity to see its full details.';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3" id="actCards"><div class="col-12"><div class="empty">Loading…</div></div></div>
<?php $extraScript = '<script src="' . h(asset('assets/js/see_activities.js')) . '"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
