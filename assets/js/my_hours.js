// View Volunteer Hours (volunteer) -> GET api/attendance.php (own hours + participation history only)
(function () {
  const { api, esc, loading, showError } = window.CVMS;
  const box = document.getElementById('histBox');
  const BADGE = { Assigned: 'text-bg-primary', Completed: 'text-bg-success', Cancelled: 'text-bg-secondary', Present: 'text-bg-success', Late: 'text-bg-warning', Absent: 'text-bg-danger' };
  const badge = function (s) { return '<span class="badge ' + (BADGE[s] || 'text-bg-light') + '">' + esc(s) + '</span>'; };

  loading(box);
  api('api/attendance.php').then(function (d) {
    document.getElementById('totalHours').textContent = d.total_hours.toFixed(2);
    document.getElementById('doneCount').textContent = d.completed_activities;
    const rows = d.history.map(function (r) {
      return '<tr><td class="fw-semibold">' + esc(r.activity_name) + '</td><td>' + esc(r.attendance_date || r.activity_date) + '</td><td>' + esc(r.role) + '</td>' +
        '<td>' + (r.attendance_status ? badge(r.attendance_status) : '—') + '</td><td>' + r.hours_rendered.toFixed(2) + '</td><td>' + badge(r.assignment_status) + '</td></tr>';
    }).join('');
    box.innerHTML = '<table class="table align-middle"><thead><tr><th>Activity</th><th>Date</th><th>My role</th><th>Attendance</th><th>Hours</th><th>Assignment</th></tr></thead><tbody>' +
      (rows || '<tr><td colspan="6"><div class="empty">No participation yet. Once an organizer assigns you to an activity, it will appear here.</div></td></tr>') + '</tbody></table>';
  }).catch(function (e) { showError(box, e.message); });
})();
