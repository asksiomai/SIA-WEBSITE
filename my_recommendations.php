<?php
// AI RECOMMENDATIONS (volunteer): upcoming activities that match the volunteer's own skills.
// Uses the same matching formula as the organizer's AI Matching (includes/matching.php) and is READ ONLY:
// GET api/recommendations.php (assets/js/my_recommendations.js). Nothing is saved and nobody is assigned.
require __DIR__ . '/includes/auth.php';
$u = require_role(['volunteer']);

$pageTitle = 'AI Recommendations';
$pageSub = 'Upcoming activities that fit the skills in your profile.';
require __DIR__ . '/includes/header.php';
?>
<div class="notice mb-3"><i class="bi bi-info-circle"></i> <strong>SUGGESTED ≠ ASSIGNED.</strong>
  These are only suggestions based on your skills, interests and availability. Organizers decide who is assigned to an activity.</div>
<div class="small text-muted mb-3">Compatibility score (0–100): skills &amp; proficiency 60 · interests 15 · availability 10 · previous completed assignments 15. Past and full activities are not shown.</div>
<div id="recBox"><div class="empty">Loading…</div></div>
<?php $extraScript = '<script src="' . h(asset('assets/js/my_recommendations.js')) . '"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
