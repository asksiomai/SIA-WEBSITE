# Community Volunteer Management System (PHP + MySQL + Bootstrap 5)

## Run it (XAMPP or Laragon)
1. Copy this folder to `htdocs/cvms` (XAMPP) or `www/cvms` (Laragon).
2. Start Apache + MySQL. Make sure the `volunteer_management` database is imported from your `volunteer_management.sql`.
3. Check `config/db.php` (default: user `root`, empty password).
4. Optional demo data: import `database_seed_demo.sql` (rows only; the schema is untouched).
5. Open `http://localhost/cvms/`.

**Before using this version on an existing database, run `migrations/001_schedule_and_other_skills.sql`** (back up first; see the comments inside the file).

Demo logins (password `password123`): superadmin@cvms.local, organizer@cvms.local, juan@cvms.local, ana@cvms.local, mark@cvms.local, carla@cvms.local, paolo@cvms.local.

Without the demo data there is no Super Admin: register a volunteer, then in phpMyAdmin set that row's `users.role` to `super_admin` once.

## Where things are
- REST-style API (JSON, `fetch()`): `api/volunteers.php`, `api/activities.php`, `api/assignments.php`, `api/attendance.php`, `api/recommendations.php` (shared helper `includes/api.php`, page scripts in `assets/js/`)
- Login/roles: `login.php`, `register.php`, `includes/auth.php` (`require_role()` guards each page)
- AI matching: `includes/matching.php` (score 60 skills + 15 interests + 10 availability + 15 experience), `api/recommendations.php`, `matching.php`
- Activities (with their required skills in ONE form): `activities.php` · Organizer "See Activities" (read only, all activities): `see_activities.php`
- Volunteer extras: AI Recommendations `my_recommendations.php` (same matching formula, read only) · Achievements `my_achievements.php` (badges calculated from real attendance)
- Passwords: 8+ characters, only letters, numbers and periods (`^[A-Za-z0-9.]{8,}$`), checked in the browser and on the server (`password_error()` in `includes/functions.php`). Login never trims or changes the typed password, so older accounts still work.
- Activity schedule: `activity_date` is the Start date; `end_date`, `start_time`, `end_time` were added (all required in Create / Edit Activity). Older activities show "Time not set" until edited. Sorting on the activity pages is done in the browser (`CVMS.sortActivities` in `assets/js/app.js`).
- Volunteer "Other Skills" (`users.other_skills`, free text on Manage Profile, shown to organizers in Manage Volunteers; NOT used by AI matching).
- Assign / cancel: `assign.php` · Attendance: `attendance.php` · Reports: `reports.php`

## REST-style API (all endpoints need a login session; the CSRF token is sent in the `X-CSRF-Token` header; no password is ever returned)
| Endpoint | Methods | Who | Used by page |
|---|---|---|---|
| `api/volunteers.php` | GET, PUT, POST/DELETE `?resource=skills` | GET list: Organizer, Super Admin · `?role=organizer`: Super Admin · `?me=1`, profile/interests/skills: the volunteer · role change (PUT): Super Admin | volunteers.php, organizers.php, profile.php, interests.php, my_skills.php |
| `api/activities.php` | GET, POST, PUT | GET: all roles (volunteer read-only; Organizer own list, or `?scope=all` for every activity, read only) · POST/PUT (activity + `requirements` list): Organizer (own), Super Admin | activities.php, see_activities.php, my_activities.php |
| `api/assignments.php` | GET, POST (assign), PUT (Cancel Assignment) | Organizer (own activities), Super Admin | assign.php |
| `api/attendance.php` | GET, POST, PUT | GET own hours: volunteer · everything else: Organizer (own activities), Super Admin | attendance.php, my_hours.php |
| `api/recommendations.php` | GET, POST (generate AI scores) | Organizer (own activities), Super Admin · Volunteer: GET only (own suggestions, nothing is saved or assigned) | matching.php, assign.php, my_recommendations.php |

There is intentionally no Delete Volunteer, Delete Organizer or Delete Activity, and no DELETE for assignments or attendance: history stays linked to accounts and activities (foreign keys). A volunteer who no longer takes part is handled with **Cancel Assignment** (`assignment_status = 'Cancelled'`), and the Super Admin changes an organizer's role instead of deleting the account. The only DELETE calls left are the small list-maintenance ones that already existed (volunteer removes own skill, Manage Skills; an activity's required skills are changed in the Create / Edit Activity form). Reports stay server-rendered PHP.
