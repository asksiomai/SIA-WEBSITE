// Select & Assign Volunteers
//   GET  api/recommendations.php?activity_id=  (AI recommendations, already stored)
//   GET  api/assignments.php?activity_id=      (assignments + slots)
//   POST api/assignments.php                   (Assign)           PUT api/assignments.php (Cancel Assignment)
// Nothing is assigned automatically: a volunteer is only assigned when the organizer enters a role and presses Assign.
(function () {
  const { api, esc, flash } = window.CVMS;
  const root = document.getElementById('assignApp');
  const activityId = Number(root.dataset.activityId);
  const highlight = Number(root.dataset.highlight || 0);
  const recBox = document.getElementById('recBox');
  const asBox = document.getElementById('assignBox');
  const slots = document.getElementById('slots');
  const BADGE = { Assigned: 'text-bg-primary', Completed: 'text-bg-success', Cancelled: 'text-bg-secondary' };
  let state = { recs: [], info: null, assignments: [] };

  async function load() {
    try {
      const results = await Promise.all([api('api/recommendations.php?activity_id=' + activityId), api('api/assignments.php?activity_id=' + activityId)]);
      state.recs = results[0].recommendations;
      state.info = results[1].activity;
      state.assignments = results[1].assignments;
      render();
    } catch (e) {
      recBox.innerHTML = asBox.innerHTML = '<div class="alert alert-danger m-3">' + esc(e.message) + '</div>';
    }
  }

  function render() {
    const info = state.info, full = info.taken >= info.max_volunteers;
    slots.textContent = info.taken + ' / ' + info.max_volunteers + ' volunteer slots used';

    const recRows = state.recs.map(function (r) {
      let action;
      if (r.assigned) action = '<span class="badge text-bg-primary">Already assigned</span>';
      else if (full) action = '<span class="text-muted small">Activity is full</span>';
      else action = '<form class="d-flex gap-2 assign-form" data-user="' + r.user_id + '"><input class="form-control form-control-sm" name="role" maxlength="100" placeholder="e.g. Trainer" required>' +
                    '<button class="btn btn-sm btn-primary text-nowrap">Assign</button></form>';
      return '<tr class="' + (r.user_id === highlight ? 'table-warning' : '') + '"><td class="fw-semibold">' + esc(r.name) + '</td><td class="small">' + esc(r.skills) + '</td>' +
        '<td><strong>' + Math.round(r.score) + '%</strong></td><td><span class="badge ' + (r.status === 'Recommended' ? 'text-bg-success' : 'text-bg-secondary') + '">' + esc(r.status) + '</span></td><td>' + action + '</td></tr>';
    }).join('');
    recBox.innerHTML = '<table class="table align-middle"><thead><tr><th>Volunteer</th><th>Skills</th><th>Score</th><th>Status</th><th style="min-width:300px">Assign with activity role</th></tr></thead><tbody>' +
      (recRows || '<tr><td colspan="5"><div class="empty">No recommendations yet. <a href="matching.php?activity_id=' + activityId + '">Generate AI Matching</a> first.</div></td></tr>') + '</tbody></table>';

    const asRows = state.assignments.map(function (s) {
      const cancel = (s.assignment_status === 'Assigned' && !info.started)
        ? '<button class="btn btn-sm btn-outline-danger btn-cancel" data-id="' + s.assignment_id + '"><i class="bi bi-x-circle"></i> Cancel Assignment</button>'
        : '<span class="text-muted small">—</span>';
      return '<tr><td class="fw-semibold">' + esc(s.volunteer_name) + '</td><td>' + esc(s.email) + '</td><td>' + esc(s.role) + '</td>' +
        '<td><span class="badge ' + (BADGE[s.assignment_status] || 'text-bg-light') + '">' + esc(s.assignment_status) + '</span></td><td class="text-end">' + cancel + '</td></tr>';
    }).join('');
    asBox.innerHTML = '<table class="table align-middle"><thead><tr><th>Volunteer</th><th>Email</th><th>Activity role</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>' +
      (asRows || '<tr><td colspan="5"><div class="empty">No volunteers assigned yet.</div></td></tr>') + '</tbody></table>';
  }

  // Assign (POST)
  recBox.addEventListener('submit', async function (e) {
    const f = e.target.closest('.assign-form');
    if (!f) return;
    e.preventDefault();
    const btn = f.querySelector('button'); btn.disabled = true;
    try {
      const d = await api('api/assignments.php', { method: 'POST', body: { activity_id: activityId, user_id: Number(f.dataset.user), role: f.elements.role.value } });
      flash('success', d.message);
    } catch (err) { flash('danger', err.message); }
    load();
  });

  // Cancel Assignment (PUT): only assignment_status changes; the volunteer account is never touched
  asBox.addEventListener('click', async function (e) {
    const btn = e.target.closest('.btn-cancel');
    if (!btn) return;
    if (!confirm("Cancel this assignment? The volunteer's account is not affected.")) return;
    btn.disabled = true;
    try {
      const d = await api('api/assignments.php', { method: 'PUT', body: { assignment_id: Number(btn.dataset.id), assignment_status: 'Cancelled' } });
      flash('success', d.message);
    } catch (err) { flash('danger', err.message); }
    load();
  });
  load();
})();
