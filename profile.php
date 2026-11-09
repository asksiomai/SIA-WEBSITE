<?php
// MANAGE PROFILE (volunteer): updates the volunteer's own row in users.
// Loaded and saved through the REST API: GET api/volunteers.php?me=1 and PUT api/volunteers.php (assets/js/profile.js).
require __DIR__ . '/includes/auth.php';
$u = require_role(['volunteer']);

$pageTitle = 'Manage Profile';
$pageSub = 'Your personal and contact information.';
require __DIR__ . '/includes/header.php';
?>
<div class="card section-card"><div class="card-body">
<form id="profileForm"><fieldset disabled>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">First name *</label><input class="form-control" name="first_name" maxlength="50" required></div>
    <div class="col-md-4"><label class="form-label">Middle name</label><input class="form-control" name="middle_name" maxlength="50"></div>
    <div class="col-md-4"><label class="form-label">Last name *</label><input class="form-control" name="last_name" maxlength="50" required></div>
    <div class="col-md-6"><label class="form-label">Email (login) *</label><input class="form-control" type="email" name="email" maxlength="100" required></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="20"></div>
    <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address" maxlength="255"></div>
    <div class="col-md-6"><label class="form-label">New password <small class="text-muted">(leave blank to keep current)</small></label><input class="form-control" type="password" name="new_password" autocomplete="new-password"></div>
  </div>
  <button class="btn btn-primary mt-4" type="submit">Save Profile</button>
</fieldset></form></div></div>
<?php $extraScript = '<script src="assets/js/profile.js"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
