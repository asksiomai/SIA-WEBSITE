// Manage Profile (volunteer) -> GET api/volunteers.php?me=1 (fill form), PUT api/volunteers.php (save)
(function () {
  const { api, flash } = window.CVMS;
  const form = document.getElementById('profileForm');
  const FIELDS = ['first_name', 'middle_name', 'last_name', 'email', 'phone', 'address', 'other_skills'];
  const PASSWORD_RULE = /^[A-Za-z0-9.]{8,}$/;     // same rule as the server (the password is never trimmed or changed)

  function fill(v) { FIELDS.forEach(function (k) { form.elements[k].value = v[k] || ''; }); form.elements.new_password.value = ''; }

  api('api/volunteers.php?me=1').then(function (d) { fill(d.volunteer); form.querySelector('fieldset').disabled = false; })
    .catch(function (e) { flash('danger', e.message); });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const body = {}; FIELDS.concat(['new_password']).forEach(function (k) { body[k] = form.elements[k].value; });
    if (body.new_password !== '' && !PASSWORD_RULE.test(body.new_password)) {
      flash('danger', 'New password: it must be at least 8 characters and can only contain letters (A-Z, a-z), numbers (0-9) and periods (.). Spaces and other special characters are not allowed.');
      return;
    }
    const btn = form.querySelector('button[type=submit]'); btn.disabled = true;
    try {
      const d = await api('api/volunteers.php', { method: 'PUT', body: body });
      fill(d.volunteer);
      const name = document.querySelector('.topbar .fw-semibold'); if (name) name.textContent = d.volunteer.full_name;
      flash('success', 'Profile updated successfully.');
    } catch (err) { flash('danger', err.message); }
    btn.disabled = false;
  });
})();
