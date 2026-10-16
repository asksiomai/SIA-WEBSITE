<?php
// ADD SKILLS (volunteer): volunteer_skills links the volunteer to a global skill with a proficiency level.
// The volunteer's skills are loaded/added/removed through the REST API: api/volunteers.php?resource=skills (assets/js/my_skills.js).
require __DIR__ . '/includes/auth.php';
$u = require_role(['volunteer']);

$skills = $pdo->query('SELECT skill_id, skill_name FROM skills ORDER BY skill_name')->fetchAll();

$pageTitle = 'Add Skills';
$pageSub = 'Choose from the skills list and set how well you know each one.';
require __DIR__ . '/includes/header.php';
?>
<div class="card section-card"><div class="card-body">
  <?php if (!$skills): ?><div class="empty">No skills are available yet. Please ask an organizer to add skills.</div><?php else: ?>
  <form id="skillForm" class="row g-2">
    <div class="col-md-5"><select class="form-select" name="skill_id" required><option value="">Select skill…</option>
      <?php foreach ($skills as $s): ?><option value="<?= (int)$s['skill_id'] ?>"><?= h($s['skill_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><select class="form-select" name="proficiency_level" required><option value="">Proficiency…</option>
      <?php foreach (LEVELS as $l): ?><option><?= h($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add skill</button></div>
  </form>
  <div class="form-text mt-2">Adding a skill you already have updates its level.</div>
  <?php endif; ?>
</div></div>
<div class="card section-card"><div class="table-responsive" id="mySkills"><div class="empty">Loading…</div></div></div>
<?php $extraScript = '<script src="assets/js/my_skills.js"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
