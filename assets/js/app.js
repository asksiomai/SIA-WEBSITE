// Shared helpers for talking to the REST-style API (api/*.php) with fetch() + JSON.
// window.CVMS = { csrf, role, userId } is printed by includes/header.php.
(function () {
  const CVMS = window.CVMS = window.CVMS || {};

  CVMS.esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  // api('api/activities.php', { method: 'PUT', body: {...} }) -> parsed JSON, or throws Error(message)
  CVMS.api = async function (url, opts) {
    opts = opts || {};
    const init = { method: opts.method || 'GET', credentials: 'same-origin',
                   headers: { 'Accept': 'application/json', 'X-CSRF-Token': CVMS.csrf } };
    if (opts.body !== undefined) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
    let data;
    try {
      const res = await fetch(url, init);
      data = await res.json();
    } catch (e) {
      throw new Error('Could not reach the server. Please try again.');
    }
    if (!data || !data.ok) throw new Error((data && data.error) || 'The request failed.');
    return data;
  };

  // Bootstrap alert at the top of the page content (same look as the PHP flash messages).
  CVMS.flash = function (type, msg) {
    const old = document.getElementById('jsFlash'); if (old) old.remove();
    const el = document.createElement('div');
    el.id = 'jsFlash';
    el.className = 'alert alert-' + type + ' alert-dismissible fade show';
    el.innerHTML = CVMS.esc(msg) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    const head = document.querySelector('.page-head');
    head.parentNode.insertBefore(el, head);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  CVMS.loading = function (box, text) { box.innerHTML = '<div class="empty">' + CVMS.esc(text || 'Loading…') + '</div>'; };
  CVMS.showError = function (box, msg) { box.innerHTML = '<div class="alert alert-danger m-3">' + CVMS.esc(msg) + '</div>'; };

  // ---- Shared activity helpers: used by View Activities, AI Recommendations (volunteer) and See Activities (organizer) ----

  // Today as YYYY-MM-DD (browser's local date).
  CVMS.today = function () {
    const n = new Date();
    return n.getFullYear() + '-' + String(n.getMonth() + 1).padStart(2, '0') + '-' + String(n.getDate()).padStart(2, '0');
  };

  // 'Past' | 'Today' | 'Upcoming' | 'No date'
  CVMS.activityState = function (a) {
    if (!a.activity_date) return 'No date';
    const t = CVMS.today();
    return a.activity_date < t ? 'Past' : (a.activity_date === t ? 'Today' : 'Upcoming');
  };

  // Long, readable date (e.g. "Saturday, October 17, 2026") without time-zone shifts.
  CVMS.fmtDate = function (iso) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
    if (!m) return 'No date';
    return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])).toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  };

  CVMS.skillBadges = function (reqs) {
    return (reqs || []).map(function (r) {
      return '<span class="badge text-bg-light border me-1">' + CVMS.esc(r.skill_name) + ' · ' + CVMS.esc(r.required_level) + '</span>';
    }).join('');
  };

  // One activity card (the whole card is clickable). $badge = ready-made HTML, $slotsText = e.g. "up to 5".
  CVMS.activityCardHtml = function (a, badge, slotsText) {
    return '<div class="col-lg-6"><div class="card h-100 act-card" role="button" tabindex="0" data-id="' + a.activity_id + '" aria-label="View details of ' + CVMS.esc(a.activity_name) + '"><div class="card-body">' +
      '<div class="d-flex justify-content-between align-items-start gap-2"><h2 class="h6 mb-1">' + CVMS.esc(a.activity_name) + '</h2>' + (badge || '') + '</div>' +
      '<div class="small text-muted mb-2"><i class="bi bi-calendar-event"></i> ' + CVMS.esc(a.activity_date || 'No date') +
      ' &nbsp; <i class="bi bi-geo-alt"></i> ' + CVMS.esc(a.location || 'No location') + ' &nbsp; <i class="bi bi-people"></i> ' + CVMS.esc(slotsText) + '</div>' +
      (a.created_by_name ? '<div class="small text-muted mb-2">Created by ' + CVMS.esc(a.created_by_name) + '</div>' : '') +
      '<p class="small mb-2">' + CVMS.esc(a.description || '') + '</p>' + CVMS.skillBadges(a.requirements) + '</div></div></div>';
  };

  // Calls open(activityId) when an .act-card inside $box is clicked, or activated with Enter / Space.
  CVMS.makeCardsClickable = function (box, open) {
    function fire(e) {
      const card = e.target.closest('.act-card');
      if (card && box.contains(card)) open(Number(card.dataset.id));
    }
    box.addEventListener('click', fire);
    box.addEventListener('keydown', function (e) {
      if ((e.key === 'Enter' || e.key === ' ') && e.target.closest('.act-card')) { e.preventDefault(); fire(e); }
    });
  };

  // Full activity details as HTML. $rows = optional extra [label, text] pairs (all text is escaped).
  CVMS.activityDetailsHtml = function (a, rows) {
    const state = CVMS.activityState(a);
    const stateBadge = { Past: 'text-bg-secondary', Today: 'text-bg-warning', Upcoming: 'text-bg-success' }[state] || 'text-bg-light';
    const info = [
      ['Date', CVMS.esc(CVMS.fmtDate(a.activity_date))],
      ['Status', '<span class="badge ' + stateBadge + '">' + state + '</span>'],
      ['Location', CVMS.esc(a.location || 'No location given')],
      ['Volunteers', CVMS.esc(a.taken + ' of ' + a.max_volunteers + ' slots filled')]
    ].concat((rows || []).map(function (r) { return [CVMS.esc(r[0]), CVMS.esc(r[1])]; }));
    const reqs = a.requirements || [];
    return '<div class="row g-3 mb-3">' + info.map(function (i) {
        return '<div class="col-sm-6"><div class="small text-muted text-uppercase">' + i[0] + '</div><div>' + i[1] + '</div></div>';
      }).join('') + '</div>' +
      '<div class="small text-muted text-uppercase">Description</div>' +
      '<p class="act-desc mb-3">' + (a.description ? CVMS.esc(a.description) : '<span class="text-muted">No description given.</span>') + '</p>' +
      '<div class="small text-muted text-uppercase mb-1">Required skills</div>' +
      (reqs.length
        ? '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Skill</th><th>Required level</th></tr></thead><tbody>' +
          reqs.map(function (r) { return '<tr><td class="fw-semibold">' + CVMS.esc(r.skill_name) + '</td><td>' + CVMS.esc(r.required_level) + '</td></tr>'; }).join('') +
          '</tbody></table></div>'
        : '<p class="text-muted mb-0">No specific skills are required.</p>');
  };

  // Opens the shared details window. opts = { rows: [[label, text], ...], footer: 'ready-made HTML (buttons, notes)' }
  CVMS.showActivity = function (a, opts) {
    opts = opts || {};
    let el = document.getElementById('activityModal');
    if (!el) {
      el = document.createElement('div');
      el.className = 'modal fade text-start';
      el.id = 'activityModal';
      el.tabIndex = -1;
      el.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">' +
        '<div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
        '<div class="modal-body"></div><div class="modal-footer"></div></div></div>';
      document.body.appendChild(el);
    }
    el.querySelector('.modal-title').textContent = a.activity_name;
    el.querySelector('.modal-body').innerHTML = CVMS.activityDetailsHtml(a, opts.rows);
    el.querySelector('.modal-footer').innerHTML = (opts.footer || '') + '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
    bootstrap.Modal.getOrCreateInstance(el).show();
  };

  // Confirm dialog for every <form data-confirm="..."> (works for forms created later by JavaScript too).
  document.addEventListener('submit', function (e) {
    const f = e.target;
    if (f.matches && f.matches('form[data-confirm]') && !confirm(f.dataset.confirm)) e.preventDefault();
  });
})();
