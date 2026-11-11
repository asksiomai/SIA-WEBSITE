// Add Skills (volunteer): Official Skills + Other Skills on one page.
//   GET    api/volunteers.php?me=1                               (official skills with levels + users.other_skills)
//   POST   api/volunteers.php?resource=skills                    (add a skill, or update the level of one the volunteer already has)
//   DELETE api/volunteers.php?resource=skills&volunteer_skill_id=
//   PUT    api/volunteers.php {other_skills}                     (free text; separate from the official list, not used by AI matching)
(function () {
  const { api, esc, flash, loading, showError } = window.CVMS;
  const LEVELS = ['Beginner', 'Intermediate', 'Advanced'];
  const box = document.getElementById('mySkills');
  const form = document.getElementById('skillForm');
  const otherForm = document.getElementById('otherSkillsForm');
  const otherList = document.getElementById('otherSkillsList');

  function renderOfficial(skills) {
    const rows = skills.map(function (m) {
      const opts = LEVELS.map(function (l) { return '<option' + (l === m.proficiency_level ? ' selected' : '') + '>' + l + '</option>'; }).join('');
      return '<tr data-skill="' + m.skill_id + '" data-level="' + esc(m.proficiency_level) + '"><td class="fw-semibold">' + esc(m.skill_name) + '</td>' +
        '<td><div class="d-flex gap-2"><select class="form-select form-select-sm level-select" style="width:auto" aria-label="Proficiency of ' + esc(m.skill_name) + '">' + opts + '</select>' +
        '<button class="btn btn-sm btn-outline-primary btn-level" disabled>Update</button></div></td>' +
        '<td class="text-end"><button class="btn btn-sm btn-outline-danger btn-remove" data-id="' + m.volunteer_skill_id + '" aria-label="Remove ' + esc(m.skill_name) + '"><i class="bi bi-trash"></i></button></td></tr>';
    }).join('');
    box.innerHTML = '<table class="table align-middle mb-0"><thead><tr><th>My official skills</th><th>Proficiency</th><th class="text-end">Action</th></tr></thead><tbody>' +
      (rows || '<tr><td colspan="3"><div class="empty">You have not added any official skills yet.</div></td></tr>') + '</tbody></table>';
  }

  // Other Skills: textarea (editable) + the saved list shown as separate badges.
  function renderOther(text) {
    otherForm.elements.other_skills.value = text || '';
    const items = (text || '').split(',').map(function (x) { return x.trim(); }).filter(Boolean);
    otherList.innerHTML = items.length
      ? items.map(function (x) { return '<span class="badge text-bg-secondary me-1 mb-1">' + esc(x) + '</span>'; }).join('')
      : '<span class="text-muted small">You have not added any other skills yet.</span>';
  }

  async function load() {
    try {
      const v = (await api('api/volunteers.php?me=1')).volunteer;
      renderOfficial(v.skills);
      renderOther(v.other_skills);
      otherForm.querySelector('fieldset').disabled = false;
    } catch (e) {
      showError(box, e.message);
      otherList.innerHTML = '<span class="text-danger small">' + esc(e.message) + '</span>';
    }
  }

  // Add a skill (adding one the volunteer already has just updates its level; the server never creates a duplicate)
  if (form) form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = form.querySelector('button[type=submit]'); btn.disabled = true;
    try {
      const d = await api('api/volunteers.php?resource=skills', { method: 'POST', body: { skill_id: Number(form.elements.skill_id.value), proficiency_level: form.elements.proficiency_level.value } });
      flash('success', d.message);
      form.reset();
      await load();
    } catch (err) { flash('danger', err.message); }
    btn.disabled = false;
  });

  // Change the level of a saved skill: the Update button turns on only when the level differs from the saved one.
  box.addEventListener('change', function (e) {
    const sel = e.target.closest('.level-select');
    if (!sel) return;
    const tr = sel.closest('tr');
    tr.querySelector('.btn-level').disabled = sel.value === tr.dataset.level;
  });

  box.addEventListener('click', async function (e) {
    const upd = e.target.closest('.btn-level');
    if (upd) {
      const tr = upd.closest('tr'); upd.disabled = true;
      try {
        const d = await api('api/volunteers.php?resource=skills', { method: 'POST', body: { skill_id: Number(tr.dataset.skill), proficiency_level: tr.querySelector('.level-select').value } });
        flash('success', d.message);
        await load();
      } catch (err) { flash('danger', err.message); upd.disabled = false; }
      return;
    }
    const btn = e.target.closest('.btn-remove');
    if (!btn || !confirm('Remove this skill from your profile?')) return;
    try {
      const d = await api('api/volunteers.php?resource=skills&volunteer_skill_id=' + encodeURIComponent(btn.dataset.id), { method: 'DELETE' });
      flash('success', d.message);
      load();
    } catch (err) { flash('danger', err.message); }
  });

  // Save Other Skills (an empty box clears them)
  otherForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = otherForm.querySelector('button[type=submit]'); btn.disabled = true;
    try {
      const d = await api('api/volunteers.php', { method: 'PUT', body: { other_skills: otherForm.elements.other_skills.value } });
      renderOther(d.volunteer.other_skills);          // shows the cleaned-up list (duplicates and blank entries removed)
      flash('success', 'Other skills saved successfully.');
    } catch (err) { flash('danger', err.message); }
    btn.disabled = false;
  });

  loading(box);
  load();
})();
