
// Manage Volunteer Profile
(function () {
    const form = document.getElementById('profileForm');

    if (!form) {
        console.error('Profile form not found.');
        return;
    }

    const { api, flash } = window.CVMS;

    const FIELDS = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'address'
    ];

    function getField(name) {
        return form.elements.namedItem(name);
    }

    function fill(volunteer) {
        if (!volunteer) {
            throw new Error('Volunteer profile data was not found.');
        }

        FIELDS.forEach(function (name) {
            const field = getField(name);

            if (!field) {
                throw new Error(
                    'Missing form input: ' + name +
                    '. Check the name attribute in profile.php.'
                );
            }

            field.value = volunteer[name] || '';
        });

        const passwordField = getField('new_password');

        if (passwordField) {
            passwordField.value = '';
        }
    }

    const fieldset = form.querySelector('fieldset');

    // Keep form disabled while loading.
    if (fieldset) {
        fieldset.disabled = true;
    }

    // Load volunteer profile.
    api('api/volunteers.php?me=1')
        .then(function (data) {
            fill(data.volunteer);

            if (fieldset) {
                fieldset.disabled = false;
            }
        })
        .catch(function (error) {
            console.error('Profile loading error:', error);
            flash('danger', error.message);
        });

    // Save profile.
    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const body = {};

        for (const name of FIELDS) {
            const field = getField(name);

            if (!field) {
                flash('danger', 'Missing form input: ' + name);
                console.error('Missing input:', name);
                return;
            }

            body[name] = field.value.trim();
        }

        const passwordField = getField('new_password');
        body.new_password = passwordField
            ? passwordField.value
            : '';

        const button = form.querySelector('button[type="submit"]');

        if (button) {
            button.disabled = true;
        }

        try {
            const data = await api('api/volunteers.php', {
                method: 'PUT',
                body: body
            });

            fill(data.volunteer);

            const nameElement = document.querySelector(
                '.topbar .fw-semibold'
            );

            if (nameElement && data.volunteer.full_name) {
                nameElement.textContent =
                    data.volunteer.full_name;
            }

            flash('success', 'Profile updated successfully.');

        } catch (error) {
            console.error('Profile saving error:', error);
            flash('danger', error.message);

        } finally {
            if (button) {
                button.disabled = false;
            }
        }
    });
})();
