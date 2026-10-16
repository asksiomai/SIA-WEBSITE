<?php
// MANAGE SKILLS: global skills list (skills table). skill_name is unique.
require __DIR__ . '/includes/auth.php';
$u = require_role(['super_admin', 'organizer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['skill_id'] ?? 0);
    $name = trim($_POST['skill_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    try {
        if ($action === 'add' || $action === 'edit') {
            if ($name === '') throw new RuntimeException('Skill name is required.');
            $st = $pdo->prepare('SELECT COUNT(*) FROM skills WHERE skill_name = ? AND skill_id <> ?');
            $st->execute([$name, $action === 'edit' ? $id : 0]);
            if ($st->fetchColumn() > 0) throw new RuntimeException('This skill already exists.');
            if ($action === 'add') {
                $pdo->prepare('INSERT INTO skills (skill_name, description) VALUES (?, ?)')->execute([$name, $desc ?: null]);
                flash('success', 'Skill added successfully.');
            } else {
                $pdo->prepare('UPDATE skills SET skill_name = ?, description = ? WHERE skill_id = ?')->execute([$name, $desc ?: null, $id]);
                flash('success', 'Skill updated successfully.');
            }
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM skills WHERE skill_id = ?')->execute([$id]);
            flash('success', 'Skill deleted successfully.');
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    } catch (PDOException $e) {
        flash('danger', $action === 'delete' ? 'Unable to delete this record because it is currently being used.' : 'The skill could not be saved.');
    }
    redirect('skills.php');
}

$skills = $pdo->query('SELECT s.*, (SELECT COUNT(*) FROM volunteer_skills v WHERE v.skill_id = s.skill_id) AS vol_count,
                              (SELECT COUNT(*) FROM activity_requirements r WHERE r.skill_id = s.skill_id) AS req_count
                         FROM skills s ORDER BY s.skill_name')->fetchAll();

$pageTitle = 'Manage Skills';
$pageSub = 'The shared list of skills that volunteers can add and activities can require.';
require __DIR__ . '/includes/header.php';
?>
<div class="card section-card"><div class="card-body">
  <h2 class="h6 mb-3">Add a skill</h2>
  <form method="post" class="row g-2">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="col-md-4"><input class="form-control" name="skill_name" placeholder="Skill name (e.g. First Aid)" maxlength="100" required></div>
    <div class="col-md-6"><input class="form-control" name="description" placeholder="Description (optional)"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button></div>
  </form>
</div></div>
<div class="card section-card"><div class="table-responsive">
<table class="table table-hover align-middle">
  <thead><tr><th>Skill</th><th>Description</th><th>Volunteers</th><th>Activities</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($skills as $s): ?>
    <tr>
      <td class="fw-semibold"><?= h($s['skill_name']) ?></td>
      <td><?= h($s['description'] ?: '—') ?></td>
      <td><?= (int)$s['vol_count'] ?></td><td><?= (int)$s['req_count'] ?></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$s['skill_id'] ?>"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" data-confirm="Delete this skill?">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="skill_id" value="<?= (int)$s['skill_id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
        <div class="modal fade text-start" id="edit<?= (int)$s['skill_id'] ?>" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">
          <?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="skill_id" value="<?= (int)$s['skill_id'] ?>">
          <div class="modal-header"><h5 class="modal-title">Edit skill</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <label class="form-label">Skill name</label><input class="form-control mb-3" name="skill_name" value="<?= h($s['skill_name']) ?>" maxlength="100" required>
            <label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= h($s['description']) ?></textarea>
          </div>
          <div class="modal-footer"><button class="btn btn-primary">Save changes</button></div>
        </form></div></div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$skills): ?><tr><td colspan="5"><div class="empty">No skills yet. Add the first one above.</div></td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
