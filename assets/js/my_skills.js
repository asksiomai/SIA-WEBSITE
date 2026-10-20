// Add Skills (volunteer) -> GET api/volunteers.php?me=1, POST api/volunteers.php?resource=skills, DELETE api/volunteers.php?resource=skills&volunteer_skill_id=
(function () {
  const { api, esc, flash, loading, showError } = window.CVMS;
  const box = document.getElementById('mySkills');
  const form = document.getElementById('skillForm');

  async function load() {
    try {
      const skills = (await api('api/volunteers.php?me=1')).volunteer.skills;
      const rows = skills.map(function (m) {
        return '<tr><td class="fw-semibold">' + esc(m.skill_name) + '</td><td>' + esc(m.proficiency_level) + '</td><td class="text-end">' +
          '<button class="btn btn-sm btn-outline-danger btn-remove" data-id="' + m.volunteer_skill_id + '"><i class="bi bi-trash"></i></button></td></tr>';
      }).join('');
      box.innerHTML = '<table class="table align-middle"><thead><tr><th>My skills</th><th>Proficiency</th><th class="text-end">Action</th></tr></thead><tbody>' +
        (rows || '<tr><td colspan="3"><div class="empty">You have not added any skills yet.</div></td></tr>') + '</tbody></table>';
    } catch (e) { showError(box, e.message); }
  }

  if (form) form.addEventListener('submit', async function (e) {
    e.preventDefault();
    try {
      const d = await api('api/volunteers.php?resource=skills', { method: 'POST', body: { skill_id: Number(form.elements.skill_id.value), proficiency_level: form.elements.proficiency_level.value } });
      flash('success', d.message);
      form.reset();
      load();
    } catch (err) { flash('danger', err.message); }
  });

  box.addEventListener('click', async function (e) {
    const btn = e.target.closest('.btn-remove');
    if (!btn || !confirm('Remove this skill from your profile?')) return;
    try {
      const d = await api('api/volunteers.php?resource=skills&volunteer_skill_id=' + encodeURIComponent(btn.dataset.id), { method: 'DELETE' });
      flash('success', d.message);
      load();
    } catch (err) { flash('danger', err.message); }
  });
  loading(box);
  load();
})();
