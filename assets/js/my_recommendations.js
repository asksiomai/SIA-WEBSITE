// AI Recommendations (volunteer) -> GET api/recommendations.php (read only: suggestions are never saved and never assign anyone)
(function () {
  const { api, esc, loading } = window.CVMS;
  const box = document.getElementById('recBox');
  let list = [];

  function card(r) {
    const b = r.breakdown;
    return '<div class="rec-card"><div class="row align-items-center g-3">' +
      '<div class="col-md-7"><div class="fw-semibold">' + esc(r.activity_name) + '</div>' +
      '<div class="small text-muted mb-2"><i class="bi bi-calendar-event"></i> ' + esc(CVMS.dateText(r)) + ' &nbsp; <i class="bi bi-clock"></i> ' + esc(CVMS.timeText(r)) + ' &nbsp; <i class="bi bi-geo-alt"></i> ' + esc(r.location || 'No location') + '</div>' +
      CVMS.skillBadges(r.requirements) +
      '<div class="small text-muted text-uppercase mt-3 mb-1">Why this is suggested</div>' +
      '<ul class="small mb-2 ps-3">' + r.reasons.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>' +
      '<div class="breakdown"><span>Skills ' + b.skills + '/60</span><span>Interests ' + b.interests + '/15</span><span>Availability ' + b.availability + '/10</span><span>Experience ' + b.experience + '/15</span></div></div>' +
      '<div class="col-md-3"><div class="score-big">' + Math.round(r.score) + '%</div><div class="score-bar"><span style="width:' + Math.min(r.score, 100) + '%"></span></div></div>' +
      '<div class="col-md-2 text-md-end"><div class="mb-2"><span class="badge ' + (r.status === 'Recommended' ? 'text-bg-success' : 'text-bg-secondary') + '">' + esc(r.status) + '</span></div>' +
      '<button type="button" class="btn btn-sm btn-primary btn-view" data-id="' + r.activity_id + '">View details</button></div>' +
      '</div></div>';
  }

  loading(box);
  api('api/recommendations.php').then(function (d) {
    list = d.recommendations;
    if (!list.length) {
      box.innerHTML = '<div class="card"><div class="empty">' + (d.skills_count
        ? 'No upcoming activities match your skills right now. Check back later, or add more skills.'
        : 'You have not added any skills yet. <a href="my_skills.php">Add your skills</a> so we can suggest activities for you.') + '</div></div>';
      return;
    }
    box.innerHTML = list.map(card).join('');
  }).catch(function (e) { box.innerHTML = '<div class="alert alert-danger">' + esc(e.message) + '</div>'; });

  box.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-view');
    if (!btn) return;
    const a = list.find(function (x) { return x.activity_id === Number(btn.dataset.id); });
    if (a) CVMS.showActivity(a, { footer: '<span class="text-muted small me-auto">Suggestion only. Organizers choose and assign volunteers.</span>' });
  });
})();
