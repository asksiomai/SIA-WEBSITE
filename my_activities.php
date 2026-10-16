<?php
// VIEW ACTIVITIES (volunteer): read-only. No apply/join; assignment is decided by the Admin / Organizer.
// The cards are loaded from the REST API: GET api/activities.php (assets/js/my_activities.js).
require __DIR__ . '/includes/auth.php';
$u = require_role(['volunteer']);

$pageTitle = 'View Activities';
$pageSub = 'Community activities and the skills they need. Organizers choose and assign volunteers.';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3" id="actCards"><div class="col-12"><div class="empty">Loading…</div></div></div>
<?php $extraScript = '<script src="assets/js/my_activities.js"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
