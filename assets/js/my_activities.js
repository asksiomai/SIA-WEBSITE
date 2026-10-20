// View Activities (volunteer, read-only) -> GET api/activities.php
(function () {
  const { api, esc, loading, showError } = window.CVMS;
  const box = document.getElementById('actCards');
  const BADGE = { Assigned: 'text-bg-primary', Completed: 'text-bg-success', Cancelled: 'text-bg-secondary' };
  const now = new Date();
  const today = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');   // local date

  loading(box);
  api('api/activities.php').then(function (d) {
    if (!d.activities.length) { box.innerHTML = '<div class="col-12"><div class="card"><div class="empty">No activities have been posted yet.</div></div></div>'; return; }
    box.innerHTML = d.activities.map(function (a) {
      const past = a.activity_date && a.activity_date < today;
      const badge = a.my_status
        ? '<span class="badge ' + (BADGE[a.my_status] || 'text-bg-light') + '">' + esc(a.my_status) + (a.my_role ? ' · ' + esc(a.my_role) : '') + '</span>'
        : (past ? '<span class="badge text-bg-light border">Past</span>' : '');
      const reqs = a.requirements.map(function (r) { return '<span class="badge text-bg-light border me-1">' + esc(r.skill_name) + ' · ' + esc(r.required_level) + '</span>'; }).join('');
      return '<div class="col-lg-6"><div class="card h-100"><div class="card-body">' +
        '<div class="d-flex justify-content-between align-items-start gap-2"><h2 class="h6 mb-1">' + esc(a.activity_name) + '</h2>' + badge + '</div>' +
        '<div class="small text-muted mb-2"><i class="bi bi-calendar-event"></i> ' + esc(a.activity_date || 'No date') +
        ' &nbsp; <i class="bi bi-geo-alt"></i> ' + esc(a.location || 'No location') + ' &nbsp; <i class="bi bi-people"></i> up to ' + a.max_volunteers + '</div>' +
        '<p class="small mb-2">' + esc(a.description || '') + '</p>' + reqs + '</div></div></div>';
    }).join('');
  }).catch(function (e) { box.innerHTML = '<div class="col-12"><div class="alert alert-danger">' + esc(e.message) + '</div></div>'; });
})();
