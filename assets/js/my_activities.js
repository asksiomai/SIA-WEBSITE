// View Activities (volunteer, read-only) -> GET api/activities.php
// Every card is clickable and opens the activity details. Opening an activity never assigns or changes anything.
(function () {
  const { api, esc, loading } = window.CVMS;
  const box = document.getElementById('actCards');
  const BADGE = { Assigned: 'text-bg-primary', Completed: 'text-bg-success', Cancelled: 'text-bg-secondary' };
  let activities = [];

  loading(box);
  api('api/activities.php').then(function (d) {
    activities = d.activities;
    if (!activities.length) { box.innerHTML = '<div class="col-12"><div class="card"><div class="empty">No activities have been posted yet.</div></div></div>'; return; }
    box.innerHTML = activities.map(function (a) {
      const past = CVMS.activityState(a) === 'Past';
      const badge = a.my_status
        ? '<span class="badge ' + (BADGE[a.my_status] || 'text-bg-light') + '">' + esc(a.my_status) + (a.my_role ? ' · ' + esc(a.my_role) : '') + '</span>'
        : (past ? '<span class="badge text-bg-light border">Past</span>' : '');
      return CVMS.activityCardHtml(a, badge, 'up to ' + a.max_volunteers);
    }).join('');
  }).catch(function (e) { box.innerHTML = '<div class="col-12"><div class="alert alert-danger">' + esc(e.message) + '</div></div>'; });

  CVMS.makeCardsClickable(box, function (id) {
    const a = activities.find(function (x) { return x.activity_id === id; });
    if (!a) return;
    CVMS.showActivity(a, {
      rows: a.my_status ? [['My assignment', a.my_status + (a.my_role ? ' · ' + a.my_role : '')]] : [],
      footer: '<span class="text-muted small me-auto">View only. Organizers choose and assign volunteers.</span>'
    });
  });
})();
