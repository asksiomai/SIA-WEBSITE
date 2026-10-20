// Manage Volunteers -> GET api/volunteers.php (list/search), PUT api/volunteers.php (promote Volunteer -> Organizer)
(function () {
  const { api, esc, flash, loading, showError } = window.CVMS;
  const box = document.getElementById('volTable');
  const isSA = box.dataset.sa === '1';
  const searchForm = document.getElementById('searchForm');
  const searchInput = document.getElementById('searchInput');

  async function load() {
    loading(box);
    try {
      const q = searchInput.value.trim();
      const d = await api('api/volunteers.php' + (q ? '?q=' + encodeURIComponent(q) : ''));
      render(d.volunteers);
    } catch (e) { showError(box, e.message); }
  }

  function render(list) {
    const rows = list.map(function (v) {
      const skills = v.skills.map(function (s) { return s.skill_name + ' - ' + s.proficiency_level; }).join(', ');
      const actions = isSA
        ? '<div class="d-inline-flex gap-1 align-items-center">' +
          '<select class="form-select form-select-sm role-select" style="width:auto"><option value="volunteer" selected>Volunteer</option><option value="organizer">Organizer</option></select>' +
          '<button class="btn btn-sm btn-primary btn-role" data-id="' + v.user_id + '">Update</button>' +
          '</div>'
        : '<span class="text-muted small">View only</span>';
      return '<tr><td class="fw-semibold">' + esc(v.full_name) + '</td><td>' + esc(v.email) + '</td><td>' + esc(v.phone || '—') + '</td>' +
             '<td>' + esc(v.interests || '—') + '</td><td>' + esc(v.availability || '—') + '</td><td class="small">' + esc(skills || '—') + '</td>' +
             '<td><span class="role-pill">Volunteer</span></td><td class="text-end text-nowrap">' + actions + '</td></tr>';
    }).join('');
    box.innerHTML = '<table class="table table-hover align-middle"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Interests</th><th>Availability</th><th>Skills</th><th>Role</th><th class="text-end">Actions</th></tr></thead><tbody>' +
      (rows || '<tr><td colspan="8"><div class="empty">No volunteers found.</div></td></tr>') + '</tbody></table>';
  }

  // Role dropdown -> confirm -> PUT (same account, only users.role changes)
  box.addEventListener('click', async function (e) {
    const btn = e.target.closest('.btn-role');
    if (!btn) return;
    const role = btn.closest('td').querySelector('.role-select').value;
    if (role === 'volunteer') { flash('info', 'No change made.'); return; }
    if (!confirm("Change this volunteer's role? The same account will become an Admin / Organizer.")) return;
    btn.disabled = true;
    try {
      const d = await api('api/volunteers.php', { method: 'PUT', body: { user_id: Number(btn.dataset.id), role: role } });
      flash('success', d.message);
      load();
    } catch (err) { flash('danger', err.message); btn.disabled = false; }
  });

  searchForm.addEventListener('submit', function (e) { e.preventDefault(); load(); });
  document.getElementById('resetBtn').addEventListener('click', function () { searchInput.value = ''; load(); });
  load();
})();
