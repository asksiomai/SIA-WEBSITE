# Community Volunteer Management System (PHP + MySQL + Bootstrap 5)

## Run it (XAMPP or Laragon)
1. Copy this folder to `htdocs/cvms` (XAMPP) or `www/cvms` (Laragon).
2. Start Apache + MySQL. Make sure the `volunteer_management` database is imported from your `volunteer_management.sql`.
3. Check `config/db.php` (default: user `root`, empty password).
4. Optional demo data: import `database_seed_demo.sql` (rows only; the schema is untouched).
5. Open `http://localhost/cvms/`.

Demo logins (password `password123`): superadmin@cvms.local, organizer@cvms.local, juan@cvms.local, ana@cvms.local, mark@cvms.local, carla@cvms.local, paolo@cvms.local.

Without the demo data there is no Super Admin: register a volunteer, then in phpMyAdmin set that row's `users.role` to `super_admin` once.

## Where things are
- REST-style API (JSON, `fetch()`): `api/volunteers.php`, `api/activities.php`, `api/assignments.php`, `api/attendance.php`, `api/recommendations.php` (shared helper `includes/api.php`, page scripts in `assets/js/`)
- Login/roles: `login.php`, `register.php`, `includes/auth.php` (`require_role()` guards each page)
- AI matching: `includes/matching.php` (score 60 skills + 15 interests + 10 availability + 15 experience), `api/recommendations.php`, `matching.php`
- Assign / cancel: `assign.php` · Attendance: `attendance.php` · Reports: `reports.php`

## REST-style API (all endpoints need a login session; the CSRF token is sent in the `X-CSRF-Token` header; no password is ever returned)
| Endpoint | Methods | Who | Used by page |
|---|---|---|---|
| `api/volunteers.php` | GET, PUT, POST/DELETE `?resource=skills` | GET list: Organizer, Super Admin · `?role=organizer`: Super Admin · `?me=1`, profile/interests/skills: the volunteer · role change (PUT): Super Admin | volunteers.php, organizers.php, profile.php, interests.php, my_skills.php |
| `api/activities.php` | GET, POST, PUT | GET: all roles (volunteer read-only) · POST/PUT: Organizer (own), Super Admin | activities.php, my_activities.php |
| `api/assignments.php` | GET, POST (assign), PUT (Cancel Assignment) | Organizer (own activities), Super Admin | assign.php |
| `api/attendance.php` | GET, POST, PUT | GET own hours: volunteer · everything else: Organizer (own activities), Super Admin | attendance.php, my_hours.php |
| `api/recommendations.php` | GET, POST (generate AI scores) | Organizer (own activities), Super Admin | matching.php, assign.php |

There is intentionally no Delete Volunteer, Delete Organizer or Delete Activity, and no DELETE for assignments or attendance: history stays linked to accounts and activities (foreign keys). A volunteer who no longer takes part is handled with **Cancel Assignment** (`assignment_status = 'Cancelled'`), and the Super Admin changes an organizer's role instead of deleting the account. The only DELETE calls left are the small list-maintenance ones that already existed (volunteer removes own skill, organizer removes an activity requirement, Manage Skills). Reports stay server-rendered PHP.
