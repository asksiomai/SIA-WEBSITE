<?php
// Organizer: "Create Activities" (own activities). Super Admin: "Manage Activities" (all).
// List / create / edit use the REST API (api/activities.php, via assets/js/activities.js).
// There is no "Delete Activity": activities (and their assignments / attendance) stay as records.
require __DIR__ . '/includes/auth.php';
$u = require_role(['super_admin', 'organizer']);
$isSA = $u['role'] === 'super_admin';

// This page only displays data. Create / edit go through api/activities.php; activities are never deleted.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit('Method not allowed.'); }

$pageTitle = $isSA ? 'Manage Activities' : 'Create Activities';
$pageSub = $isSA ? 'All community activities. Open an activity to add requirements, match and assign volunteers.' : 'Create community activities, then add the skills each one requires.';
require __DIR__ . '/includes/header.php';

function activity_fields() { ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Activity name *</label><input class="form-control" name="activity_name" maxlength="150" required></div>
    <div class="col-md-3"><label class="form-label">Date *</label><input class="form-control" type="date" name="activity_date" required></div>
    <div class="col-md-3"><label class="form-label">Max volunteers *</label><input class="form-control" type="number" min="1" name="max_volunteers" required></div>
    <div class="col-md-12"><label class="form-label">Location</label><input class="form-control" name="location" maxlength="255"></div>
    <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
  </div>
<?php } ?>
<div class="card section-card"><div class="card-body">
  <h2 class="h6 mb-3">New activity</h2>
  <form id="createForm">
    <?php activity_fields(); ?>
    <button class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i> Create Activity</button>
  </form>
</div></div>

<div class="card section-card"><div class="table-responsive" id="actTable" data-sa="<?= $isSA ? '1' : '0' ?>"><div class="empty">Loading…</div></div></div>

<div class="modal fade text-start" id="editModal" tabindex="-1"><div class="modal-dialog modal-lg"><form id="editForm" class="modal-content">
  <input type="hidden" name="activity_id">
  <div class="modal-header"><h5 class="modal-title">Edit activity</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><?php activity_fields(); ?></div>
  <div class="modal-footer"><button class="btn btn-primary">Save changes</button></div>
</form></div></div>
<?php $extraScript = '<script src="assets/js/activities.js"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
