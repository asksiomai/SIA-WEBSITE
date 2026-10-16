<?php
// MANAGE ADMINS / ORGANIZERS: Super Admin only. Organizers are users with role = 'organizer'.
// The list and the role change use the REST API (api/volunteers.php?role=organizer, via assets/js/organizers.js).
// There is no "Delete Organizer": the Super Admin changes the role instead (same account, same user_id).
require __DIR__ . '/includes/auth.php';
$u = require_role(['super_admin']);

// This page only displays data. Changing a role goes through PUT api/volunteers.php; organizer accounts are never deleted.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit('Method not allowed.'); }

$pageTitle = 'Manage Admins / Organizers';
$pageSub = 'Accounts with the Admin / Organizer role. To add one, promote an existing volunteer from Manage Volunteers.';
require __DIR__ . '/includes/header.php';
?>
<div class="card section-card"><div class="table-responsive" id="orgTable"><div class="empty">Loading…</div></div></div>
<?php $extraScript = '<script src="assets/js/organizers.js"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
