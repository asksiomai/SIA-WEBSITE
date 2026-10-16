<?php
// MANAGE VOLUNTEERS: Super Admin + Admin / Organizer can view; only Super Admin can change a volunteer's role.
// The list and the role change use the REST API (api/volunteers.php, via assets/js/volunteers.js).
// There is no "Delete Volunteer": a volunteer's history (assignments, attendance, hours) must stay linked to the account.
require __DIR__ . '/includes/auth.php';
$u = require_role(['super_admin', 'organizer']);
$isSA = $u['role'] === 'super_admin';

// This page only displays data. Changing a role goes through PUT api/volunteers.php; volunteer accounts are never deleted.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit('Method not allowed.'); }

$pageTitle = 'Manage Volunteers';
$pageSub = $isSA ? 'All publicly registered volunteers. You can promote a volunteer to Admin / Organizer (same account, role only changes).' : 'Registered volunteers with their skills, interests and availability.';
require __DIR__ . '/includes/header.php';
?>
<div class="card section-card"><div class="card-body">
  <form class="row g-2" id="searchForm">
    <div class="col-md-6"><input class="form-control" id="searchInput" placeholder="Search name or email"></div>
    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-search"></i> Search</button> <button type="button" class="btn btn-light" id="resetBtn">Reset</button></div>
  </form>
</div></div>
<div class="card section-card"><div class="table-responsive" id="volTable" data-sa="<?= $isSA ? '1' : '0' ?>"><div class="empty">Loading…</div></div></div>
<?php $extraScript = '<script src="assets/js/volunteers.js"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
