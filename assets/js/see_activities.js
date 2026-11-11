// See Activities (organizer, read-only) -> GET api/activities.php?scope=all
// Shows every organizer's activities. Links to Attendance / AI Matching / Assign appear only for the organizer's own activities.
(function () {
  const { api, esc, loading } = window.CVMS;
  const box = document.getElementById('actCards');
  let activities = [];

  loading(box);
  api('api/activities.php?scope=all').then(function (d) {
    activities = d.activities;
    if (!activities.length) { box.innerHTML = '<div class="col-12"><div class="card"><div class="empty">No activities have been created yet.</div></div></div>'; return; }
    box.innerHTML = activities.map(function (a) {
      const badge = (a.is_mine ? '<span class="badge text-bg-primary me-1">Yours</span>' : '') +
                    (CVMS.activityState(a) === 'Past' ? '<span class="badge text-bg-light border">Past</span>' : '');
      return CVMS.activityCardHtml(a, badge, a.taken + ' / ' + a.max_volunteers + ' volunteers');
    }).join('');
  }).catch(function (e) { box.innerHTML = '<div class="col-12"><div class="alert alert-danger">' + esc(e.message) + '</div></div>'; });

  CVMS.makeCardsClickable(box, function (id) {
    const a = activities.find(function (x) { return x.activity_id === id; });
    if (!a) return;
    const q = '?activity_id=' + a.activity_id;
    CVMS.showActivity(a, {
      rows: [['Created by', a.created_by_name + (a.is_mine ? ' (you)' : '')]],
      footer: a.is_mine
        ? '<a class="btn btn-outline-secondary" href="activities.php">Edit activity</a>' +
          '<a class="btn btn-outline-secondary" href="attendance.php' + q + '">Manage Attendance</a>' +
          '<a class="btn btn-outline-secondary" href="matching.php' + q + '">AI Matching</a>' +
          '<a class="btn btn-primary" href="assign.php' + q + '">Select &amp; Assign</a>'
        : '<span class="text-muted small me-auto">Created by another organizer. View only.</span>'
    });
  });
})();
