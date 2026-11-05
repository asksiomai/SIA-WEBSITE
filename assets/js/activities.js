// Create / Manage Activities -> GET api/activities.php, POST api/activities.php (create activity + required skills), PUT api/activities.php (edit)
// The required skills are chosen in the same form as the activity and sent together as "requirements".
(function () {
  const { api, esc, flash, loading, showError } = window.CVMS;
  const box = document.getElementById('actTable');
  const isSA = box.dataset.sa === '1';
  const SKILLS = JSON.parse(box.dataset.skills || '[]');      // existing skills from the database
  const LEVELS = ['Beginner', 'Intermediate', 'Advanced'];
  const createForm = document.getElementById('createForm');
  const editForm = document.getElementById('editForm');
  const modalEl = document.getElementById('editModal');
  const editError = document.getElementById('editError');
  let activities = [];

  function formData(form) { const o = {}; new FormData(form).forEach(function (v, k) { o[k] = v; }); return o; }

  // ---- Required skills editor (same markup in the create form and in the edit window)
  function skillRow(skillId, level) {
    const skillOptions = '<option value="">Select skill…</option>' + SKILLS.map(function (s) {
      return '<option value="' + s.skill_id + '"' + (Number(skillId) === Number(s.skill_id) ? ' selected' : '') + '>' + esc(s.skill_name) + '</option>';
    }).join('');
    const levelOptions = '<option value="">Required level…</option>' + LEVELS.map(function (l) {
      return '<option' + (l === level ? ' selected' : '') + '>' + l + '</option>';
    }).join('');
    return '<div class="row g-2 mb-2 req-row">' +
      '<div class="col-md-6"><select class="form-select req-skill" aria-label="Required skill">' + skillOptions + '</select></div>' +
      '<div class="col-md-4"><select class="form-select req-level" aria-label="Required level">' + levelOptions + '</select></div>' +
      '<div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 req-remove" aria-label="Remove this skill"><i class="bi bi-trash"></i></button></div></div>';
  }
  function setRequirements(form, list) {
    form.querySelector('.req-list').innerHTML = (list || []).map(function (r) { return skillRow(r.skill_id, r.required_level); }).join('');
  }
  // Returns [{skill_id, required_level}, ...] or throws an Error with a message the organizer can understand.
  function readRequirements(form) {
    const list = [], seen = {};
    form.querySelectorAll('.req-row').forEach(function (row) {
      const skill = row.querySelector('.req-skill').value, level = row.querySelector('.req-level').value;
      if (!skill || !level) throw new Error('Please choose a skill and a required level in every required-skill row, or remove the empty row.');
      if (seen[skill]) throw new Error('The same skill was added more than once. Each skill can only be required once.');
      seen[skill] = true;
      list.push({ skill_id: Number(skill), required_level: level });
    });
    return list;
  }
  [createForm, editForm].forEach(function (form) {
    form.addEventListener('click', function (e) {
      if (e.target.closest('.req-add')) form.querySelector('.req-list').insertAdjacentHTML('beforeend', skillRow('', ''));
      const remove = e.target.closest('.req-remove');
      if (remove) remove.closest('.req-row').remove();
    });
  });

  async function load() {
    loading(box);
    try { activities = (await api('api/activities.php')).activities; render(); }
    catch (e) { showError(box, e.message); }
  }

  function render() {
    const rows = activities.map(function (a) {
      const skills = a.requirements.map(function (r) {
        return '<span class="badge text-bg-light border me-1">' + esc(r.skill_name) + ' · ' + esc(r.required_level) + '</span>';
      }).join('') || '<span class="text-muted">—</span>';
      return '<tr><td class="fw-semibold">' + esc(a.activity_name) + '</td><td class="text-nowrap">' + esc(a.activity_date) + '</td>' +
        '<td>' + esc(a.location || '—') + '</td><td>' + a.taken + ' / ' + a.max_volunteers + '</td><td>' + skills + '</td>' +
        (isSA ? '<td>' + esc(a.created_by_name) + '</td>' : '') +
        '<td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-secondary btn-edit" data-id="' + a.activity_id + '" aria-label="Edit activity"><i class="bi bi-pencil"></i></button></td></tr>';
    }).join('');
    box.innerHTML = '<table class="table table-hover align-middle"><thead><tr><th>Activity</th><th>Date</th><th>Location</th><th>Volunteers</th><th>Required skills</th>' +
      (isSA ? '<th>Created by</th>' : '') + '<th class="text-end">Actions</th></tr></thead><tbody>' +
      (rows || '<tr><td colspan="7"><div class="empty">No activities yet.</div></td></tr>') + '</tbody></table>';
  }

  // Create -> one POST with the activity and its required skills
  createForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = createForm.querySelector('button[type=submit]');
    let body;
    try { body = formData(createForm); body.requirements = readRequirements(createForm); }
    catch (err) { flash('danger', err.message); return; }
    btn.disabled = true;
    try {
      const d = await api('api/activities.php', { method: 'POST', body: body });
      createForm.reset();
      setRequirements(createForm, []);
      flash('success', d.message);
      load();
    } catch (err) { flash('danger', err.message); }
    btn.disabled = false;
  });

  // Edit button -> fill the shared window (activity fields + required skills)
  box.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-edit');
    if (!btn) return;
    const a = activities.find(function (x) { return x.activity_id === Number(btn.dataset.id); });
    editForm.elements.activity_id.value = a.activity_id;
    editForm.elements.activity_name.value = a.activity_name;
    editForm.elements.activity_date.value = a.activity_date || '';
    editForm.elements.max_volunteers.value = a.max_volunteers;
    editForm.elements.location.value = a.location || '';
    editForm.elements.description.value = a.description || '';
    setRequirements(editForm, a.requirements);
    editError.classList.add('d-none');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });

  // Save changes -> PUT (errors stay inside the window so nothing the organizer typed is lost)
  editForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    editError.classList.add('d-none');
    let body;
    try { body = formData(editForm); body.activity_id = Number(body.activity_id); body.requirements = readRequirements(editForm); }
    catch (err) { editError.textContent = err.message; editError.classList.remove('d-none'); return; }
    try {
      const d = await api('api/activities.php', { method: 'PUT', body: body });
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      flash('success', d.message);
      load();
    } catch (err) { editError.textContent = err.message; editError.classList.remove('d-none'); }
  });
  load();
})();
