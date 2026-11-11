<?php
// ADD SKILLS (volunteer): ONE page for both kinds of skills.
//   1) Official Skills: volunteer_skills links the volunteer to a skill of the global skills list (the one Admin / Organizer manage in
//      Manage Skills) with a proficiency level. These are the ONLY skills used by AI matching.
//   2) Other Skills (optional): free text saved in users.other_skills for skills that are not in the official list.
//      Not verified, not added to the skills table, no approval workflow, and NOT used by AI matching.
// Everything is loaded/saved through the REST API (assets/js/my_skills.js):
//   GET api/volunteers.php?me=1 · POST/DELETE api/volunteers.php?resource=skills · PUT api/volunteers.php {other_skills}
require __DIR__ . '/includes/auth.php';
$u = require_role(['volunteer']);

$skills = $pdo->query('SELECT skill_id, skill_name FROM skills ORDER BY skill_name')->fetchAll();

$pageTitle = 'Add Skills';
$pageSub = 'Choose from the official skills list and set how well you know each one. Skills that are not on the list go under Other Skills.';
require __DIR__ . '/includes/header.php';
?>
<div class="card section-card"><div class="card-body">
  <h2 class="h6 mb-1">Official skills</h2>
  <p class="text-muted small mb-3">These skills are used in AI matching.</p>
  <?php if (!$skills): ?><div class="empty">No official skills are available yet. Please ask an organizer to add skills.</div><?php else: ?>
  <form id="skillForm" class="row g-2">
    <div class="col-md-5"><select class="form-select" name="skill_id" required aria-label="Skill"><option value="">Select skill…</option>
      <?php foreach ($skills as $s): ?><option value="<?= (int)$s['skill_id'] ?>"><?= h($s['skill_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><select class="form-select" name="proficiency_level" required aria-label="Proficiency"><option value="">Proficiency…</option>
      <?php foreach (LEVELS as $l): ?><option value="<?= h($l) ?>"><?= h($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-plus-lg"></i> Add skill</button></div>
  </form>
  <div class="form-text mt-2">Adding a skill you already have updates its level. You can also change a level in the list below.</div>
  <?php endif; ?>
</div>
<div class="table-responsive" id="mySkills"><div class="empty">Loading…</div></div></div>

<div class="card section-card"><div class="card-body">
  <h2 class="h6 mb-1">Other skills <small class="text-muted fw-normal">(optional)</small></h2>
  <p class="text-muted small mb-3">Skills that are not in the official list above. They are shown to organizers, but they are not verified and are not used in AI matching scores.</p>
  <form id="otherSkillsForm"><fieldset disabled>
    <textarea class="form-control" name="other_skills" rows="3" maxlength="1500" aria-label="Other skills" placeholder="e.g. Janitor, Bartender, House Painter"></textarea>
    <div class="form-text">Separate skills with commas or put each one on a new line (up to 20 skills, 60 characters each).</div>
    <button class="btn btn-primary mt-3" type="submit">Save other skills</button>
  </fieldset></form>
  <div class="small text-muted text-uppercase mt-4 mb-2">Your saved other skills</div>
  <div id="otherSkillsList"><span class="text-muted small">Loading…</span></div>
</div></div>
<?php $extraScript = '<script src="' . h(asset('assets/js/my_skills.js')) . '"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
