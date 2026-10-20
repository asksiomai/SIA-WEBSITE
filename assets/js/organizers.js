// Manage Admins / Organizers -> GET api/volunteers.php?role=organizer, PUT api/volunteers.php (change role)
(function () {
  const { api, esc, flash, loading, showError } = window.CVMS;
  const box = document.getElementById('orgTable');

  async function load() {
    loading(box);
    try { render((await api('api/volunteers.php?role=organizer')).organizers); }
    catch (e) { showError(box, e.message); }
  }

  function render(list) {
    const rows = list.map(function (o) {
      return '<tr><td class="fw-semibold">' + esc(o.full_name) + '</td><td>' + esc(o.email) + '</td><td>' + esc(o.phone || '—') + '</td>' +
        '<td>' + o.activity_count + '</td><td><span class="role-pill">Admin / Organizer</span></td><td class="text-end text-nowrap">' +
        '<div class="d-inline-flex gap-1 align-items-center">' +
        '<select class="form-select form-select-sm role-select" style="width:auto"><option value="organizer" selected>Organizer</option><option value="volunteer">Volunteer</option></select>' +
        '<button class="btn btn-sm btn-primary btn-role" data-id="' + o.user_id + '">Update</button>' +
        '</div></td></tr>';
    }).join('');
    box.innerHTML = '<table class="table table-hover align-middle"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Activities created</th><th>Role</th><th class="text-end">Actions</th></tr></thead><tbody>' +
      (rows || '<tr><td colspan="6"><div class="empty">No Admin / Organizer accounts yet.</div></td></tr>') + '</tbody></table>';
  }

  box.addEventListener('click', async function (e) {
    const btn = e.target.closest('.btn-role');
    if (!btn) return;
    const role = btn.closest('td').querySelector('.role-select').value;
    if (role === 'organizer') { flash('info', 'No change made.'); return; }
    if (!confirm("Change this account's role? It will lose Admin / Organizer access.")) return;
    btn.disabled = true;
    try {
      const d = await api('api/volunteers.php', { method: 'PUT', body: { user_id: Number(btn.dataset.id), role: role } });
      flash('success', d.message);
      load();
    } catch (err) { flash('danger', err.message); btn.disabled = false; }
  });
  load();
})();
