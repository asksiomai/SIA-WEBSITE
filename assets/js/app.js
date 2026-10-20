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

  // Confirm dialog for every <form data-confirm="..."> (works for forms created later by JavaScript too).
  document.addEventListener('submit', function (e) {
    const f = e.target;
    if (f.matches && f.matches('form[data-confirm]') && !confirm(f.dataset.confirm)) e.preventDefault();
  });
})();
