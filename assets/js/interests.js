// Set Interests (volunteer) -> GET api/volunteers.php?me=1 (fill form), PUT api/volunteers.php (save interests + availability)
(function () {
  const { api, flash } = window.CVMS;
  const form = document.getElementById('interestsForm');

  api('api/volunteers.php?me=1').then(function (d) {
    form.elements.interests.value = d.volunteer.interests || '';
    form.elements.availability.value = d.volunteer.availability || '';
    form.querySelector('fieldset').disabled = false;
  }).catch(function (e) { flash('danger', e.message); });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = form.querySelector('button[type=submit]'); btn.disabled = true;
    try {
      await api('api/volunteers.php', { method: 'PUT', body: { interests: form.elements.interests.value, availability: form.elements.availability.value } });
      flash('success', 'Interests and availability updated successfully.');
    } catch (err) { flash('danger', err.message); }
    btn.disabled = false;
  });
})();
