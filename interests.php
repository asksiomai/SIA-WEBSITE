<?php
// SET INTERESTS (volunteer): interests and availability are stored in the volunteer's users row.
// Loaded and saved through the REST API: GET api/volunteers.php?me=1 and PUT api/volunteers.php (assets/js/interests.js).
require __DIR__ . '/includes/auth.php';
$u = require_role(['volunteer']);

$pageTitle = 'Set Interests';
$pageSub = 'Interests and availability help the system recommend activities that fit you.';
require __DIR__ . '/includes/header.php';
?>
<div class="card section-card"><div class="card-body">
<form id="interestsForm"><fieldset disabled>
  <div class="mb-3"><label class="form-label">Interests</label>
    <textarea class="form-control" name="interests" rows="3" placeholder="e.g. teaching, environment, computers, health"></textarea>
    <div class="form-text">Separate interests with commas.</div></div>
  <div class="mb-3"><label class="form-label">Availability</label>
    <input class="form-control" name="availability" maxlength="255" placeholder="e.g. Weekends, or Monday, Wednesday, Saturday">
    <div class="form-text">Write the days you are free (for example: Weekends, Weekdays, or specific days).</div></div>
  <button class="btn btn-primary" type="submit">Save</button>
</fieldset></form></div></div>
<?php $extraScript = '<script src="assets/js/interests.js"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
