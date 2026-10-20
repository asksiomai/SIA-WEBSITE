// Manage Attendance -> GET api/attendance.php?activity_id=, POST api/attendance.php (new record), PUT api/attendance.php (edit record)
// Present/Late: hours = check-out - check-in. Absent: 0.00 hours. The server keeps assignment_status consistent.
(function () {
  const { api, esc, flash } = window.CVMS;
  const root = document.getElementById('attApp');
  const activityId = Number(root.dataset.activityId);
  const formBox = document.getElementById('attForm');
  const recBox = document.getElementById('attRecords');
  const BADGE = { Present: 'text-bg-success', Late: 'text-bg-warning', Absent: 'text-bg-danger' };
  let state = { activity: null, assigned: [], records: [] };

  async function load() {
    try {
      const d = await api('api/attendance.php?activity_id=' + activityId);
      state = { activity: d.activity, assigned: d.assigned, records: d.records };
      render();
    } catch (e) {
      formBox.innerHTML = recBox.innerHTML = '<div class="alert alert-danger m-3">' + esc(e.message) + '</div>';
    }
  }

  function render() {
    const rows = state.assigned.map(function (s) {
      const r = s.latest || {};
      const opts = ['Present', 'Late', 'Absent'].map(function (o) { return '<option' + ((r.status || 'Present') === o ? ' selected' : '') + '>' + o + '</option>'; }).join('');
      return '<tr data-assignment="' + s.assignment_id + '"><td><div class="fw-semibold">' + esc(s.volunteer_name) + '</div><small class="text-muted">' + esc(s.role) + '</small></td>' +
        '<td><input class="form-control form-control-sm" type="date" name="attendance_date" value="' + esc(r.attendance_date || state.activity.activity_date || '') + '" required></td>' +
        '<td><select class="form-select form-select-sm" name="status">' + opts + '</select></td>' +
        '<td><input class="form-control form-control-sm" type="time" name="check_in_time" value="' + esc(r.check_in_time || '') + '"></td>' +
        '<td><input class="form-control form-control-sm" type="time" name="check_out_time" value="' + esc(r.check_out_time || '') + '"></td>' +
        '<td><input class="form-control form-control-sm" name="remarks" value="' + esc(r.remarks || '') + '"></td>' +
        '<td><button class="btn btn-sm btn-primary btn-save">Save</button></td></tr>';
    }).join('');
    formBox.innerHTML = '<table class="table align-middle"><thead><tr><th>Volunteer / role</th><th>Date</th><th>Status</th><th>Check-in</th><th>Check-out</th><th>Remarks</th><th></th></tr></thead><tbody>' +
      (rows || '<tr><td colspan="7"><div class="empty">No assigned volunteers yet. <a href="assign.php?activity_id=' + activityId + '">Assign volunteers</a> first.</div></td></tr>') + '</tbody></table>';

    const recRows = state.records.map(function (t) {
      return '<tr><td>' + esc(t.attendance_date) + '</td><td>' + esc(t.volunteer_name) + '</td><td><span class="badge ' + (BADGE[t.status] || 'text-bg-light') + '">' + esc(t.status) + '</span></td>' +
        '<td>' + esc(t.check_in_time || '—') + '</td><td>' + esc(t.check_out_time || '—') + '</td><td>' + t.hours_rendered.toFixed(2) + '</td><td>' + esc(t.remarks || '—') + '</td></tr>';
    }).join('');
    recBox.innerHTML = '<table class="table table-sm align-middle"><thead><tr><th>Date</th><th>Volunteer</th><th>Status</th><th>In</th><th>Out</th><th>Hours</th><th>Remarks</th></tr></thead><tbody>' +
      (recRows || '<tr><td colspan="7"><div class="empty">No attendance recorded yet.</div></td></tr>') + '</tbody></table>';
  }

  formBox.addEventListener('click', async function (e) {
    const btn = e.target.closest('.btn-save');
    if (!btn) return;
    const tr = btn.closest('tr');
    const field = function (n) { return tr.querySelector('[name=' + n + ']').value; };
    if (!field('attendance_date')) { flash('danger', 'Please choose a valid attendance date.'); return; }
    const body = { attendance_date: field('attendance_date'), status: field('status'), check_in_time: field('check_in_time'),
                   check_out_time: field('check_out_time'), remarks: field('remarks') };
    const aid = Number(tr.dataset.assignment);
    const existing = state.records.find(function (r) { return r.assignment_id === aid && r.attendance_date === body.attendance_date; });
    btn.disabled = true;
    try {
      let d;
      if (existing) { body.attendance_id = existing.attendance_id; d = await api('api/attendance.php', { method: 'PUT', body: body }); }
      else { body.assignment_id = aid; d = await api('api/attendance.php', { method: 'POST', body: body }); }
      flash('success', d.message);
    } catch (err) { flash('danger', err.message); }
    load();
  });
  load();
})();
