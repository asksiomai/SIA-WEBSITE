<?php
// ADD REQUIREMENTS: skill + required level per activity (activity_requirements table).
require __DIR__ . '/includes/auth.php';
$u = require_role(['super_admin', 'organizer']);
$a = find_activity($pdo, $u, $_REQUEST['activity_id'] ?? 0);

if ($a && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $skill = (int)($_POST['skill_id'] ?? 0);
            $level = $_POST['required_level'] ?? '';
            if (!in_array($level, LEVELS, true)) throw new RuntimeException('Please choose a required level.');
            $st = $pdo->prepare('SELECT COUNT(*) FROM skills WHERE skill_id = ?'); $st->execute([$skill]);
            if (!$st->fetchColumn()) throw new RuntimeException('Please choose a skill.');
            $st = $pdo->prepare('SELECT requirement_id FROM activity_requirements WHERE activity_id = ? AND skill_id = ?');
            $st->execute([$a['activity_id'], $skill]);
            if ($rid = $st->fetchColumn()) {
                $pdo->prepare('UPDATE activity_requirements SET required_level = ? WHERE requirement_id = ?')->execute([$level, $rid]);
                flash('success', 'Requirement updated successfully.');
            } else {
                $pdo->prepare('INSERT INTO activity_requirements (activity_id, skill_id, required_level) VALUES (?, ?, ?)')->execute([$a['activity_id'], $skill, $level]);
                flash('success', 'Requirement added successfully.');
            }
        } elseif ($action === 'remove') {
            $pdo->prepare('DELETE FROM activity_requirements WHERE requirement_id = ? AND activity_id = ?')->execute([(int)($_POST['requirement_id'] ?? 0), $a['activity_id']]);
            flash('success', 'Requirement removed successfully.');
        }
    } catch (RuntimeException $e) { flash('danger', $e->getMessage()); }
    catch (PDOException $e) { flash('danger', 'The requirement could not be saved.'); }
    redirect('requirements.php?activity_id=' . $a['activity_id']);
}

$pageTitle = 'Add Requirements';
$pageSub = $a ? $a['activity_name'] . ' · ' . $a['activity_date'] : 'Choose an activity to set the skills it requires.';
require __DIR__ . '/includes/header.php';

if (!$a) { activity_picker(visible_activities($pdo, $u), 'requirements.php', 'Requirements are added per activity.'); require __DIR__ . '/includes/footer.php'; exit; }

$st = $pdo->prepare('SELECT r.*, s.skill_name FROM activity_requirements r JOIN skills s ON s.skill_id = r.skill_id WHERE r.activity_id = ? ORDER BY s.skill_name');
$st->execute([$a['activity_id']]);
$reqs = $st->fetchAll();
$skills = $pdo->query('SELECT skill_id, skill_name FROM skills ORDER BY skill_name')->fetchAll();
?>
<div class="card section-card"><div class="card-body">
  <h2 class="h6 mb-3">Add a requirement</h2>
  <?php if (!$skills): ?><div class="empty">No skills exist yet. <a href="skills.php">Add skills first.</a></div><?php else: ?>
  <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="activity_id" value="<?= (int)$a['activity_id'] ?>">
    <div class="col-md-5"><select class="form-select" name="skill_id" required><option value="">Select skill…</option>
      <?php foreach ($skills as $s): ?><option value="<?= (int)$s['skill_id'] ?>"><?= h($s['skill_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><select class="form-select" name="required_level" required><option value="">Required level…</option>
      <?php foreach (LEVELS as $l): ?><option><?= h($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button></div>
  </form>
  <?php endif; ?>
</div></div>
<div class="card section-card"><div class="table-responsive"><table class="table align-middle">
  <thead><tr><th>Skill</th><th>Required level</th><th class="text-end">Action</th></tr></thead><tbody>
  <?php foreach ($reqs as $r): ?>
    <tr><td class="fw-semibold"><?= h($r['skill_name']) ?></td><td><?= h($r['required_level']) ?></td>
      <td class="text-end"><form method="post" class="d-inline" data-confirm="Remove this requirement?"><?= csrf_field() ?>
        <input type="hidden" name="action" value="remove"><input type="hidden" name="activity_id" value="<?= (int)$a['activity_id'] ?>"><input type="hidden" name="requirement_id" value="<?= (int)$r['requirement_id'] ?>">
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
  <?php endforeach; ?>
  <?php if (!$reqs): ?><tr><td colspan="3"><div class="empty">No requirements yet.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
<a class="btn btn-outline-primary" href="matching.php?activity_id=<?= (int)$a['activity_id'] ?>"><i class="bi bi-stars"></i> Continue to AI Matching</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
