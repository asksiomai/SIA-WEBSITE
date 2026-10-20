// Create / Manage Activities -> GET api/activities.php, POST api/activities.php (create), PUT api/activities.php (edit)
(function () {
  const { api, esc, flash, loading, showError } = window.CVMS;
  const box = document.getElementById('actTable');
  const isSA = box.dataset.sa === '1';
  const createForm = document.getElementById('createForm');
  const editForm = document.getElementById('editForm');
  const modalEl = document.getElementById('editModal');
  let activities = [];

  function formData(form) { const o = {}; new FormData(form).forEach(function (v, k) { o[k] = v; }); return o; }

  async function load() {
    loading(box);
    try { activities = (await api('api/activities.php')).activities; render(); }
    catch (e) { showError(box, e.message); }
  }

  function render() {
    const rows = activities.map(function (a) {
      return '<tr><td class="fw-semibold">' + esc(a.activity_name) + '</td><td class="text-nowrap">' + esc(a.activity_date) + '</td>' +
        '<td>' + esc(a.location || '—') + '</td><td>' + a.taken + ' / ' + a.max_volunteers + '</td><td>' + a.req_count + '</td>' +
        (isSA ? '<td>' + esc(a.created_by_name) + '</td>' : '') +
        '<td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="requirements.php?activity_id=' + a.activity_id + '">Requirements</a> ' +
        '<button class="btn btn-sm btn-outline-secondary btn-edit" data-id="' + a.activity_id + '"><i class="bi bi-pencil"></i></button></td></tr>';
    }).join('');
    box.innerHTML = '<table class="table table-hover align-middle"><thead><tr><th>Activity</th><th>Date</th><th>Location</th><th>Volunteers</th><th>Requirements</th>' +
      (isSA ? '<th>Created by</th>' : '') + '<th class="text-end">Actions</th></tr></thead><tbody>' +
      (rows || '<tr><td colspan="7"><div class="empty">No activities yet.</div></td></tr>') + '</tbody></table>';
  }

  // Create -> POST, then continue to "Add Requirements" for the new activity
  createForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = createForm.querySelector('button[type=submit], button:not([type])');
    btn.disabled = true;
    try {
      const d = await api('api/activities.php', { method: 'POST', body: formData(createForm) });
      window.location = 'requirements.php?activity_id=' + d.activity.activity_id;
    } catch (err) { flash('danger', err.message); btn.disabled = false; }
  });

  // Edit button -> fill the shared modal
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
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });

  // Save changes -> PUT
  editForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const body = formData(editForm); body.activity_id = Number(body.activity_id);
    try {
      const d = await api('api/activities.php', { method: 'PUT', body: body });
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      flash('success', d.message);
      load();
    } catch (err) { flash('danger', err.message); bootstrap.Modal.getOrCreateInstance(modalEl).hide(); }
  });
  load();
})();
