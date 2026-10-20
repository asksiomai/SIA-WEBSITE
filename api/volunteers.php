<?php
// REST-style endpoint: volunteer / profile data (tables: users, volunteer_skills, skills). Passwords are never returned.
//
//   GET    api/volunteers.php                      -> Admin / Organizer, Super Admin: registered volunteers (optional ?q=search)
//   GET    api/volunteers.php?id=3                 -> one volunteer (staff only)
//   GET    api/volunteers.php?role=organizer       -> Admin / Organizer accounts (Super Admin only)
//   GET    api/volunteers.php?me=1                 -> my own profile, interests, availability and skills
//   PUT    api/volunteers.php                      -> Volunteer updates own profile {first_name, ..., email, new_password}
//                                                     and/or interests {interests, availability}
//   PUT    api/volunteers.php                      -> Super Admin changes a role {user_id, role: "organizer" | "volunteer"}
//                                                     (same account, same user_id: only users.role changes)
//   POST   api/volunteers.php?resource=skills      -> Volunteer adds / updates own skill {skill_id, proficiency_level}
//   DELETE api/volunteers.php?resource=skills&volunteer_skill_id=7 -> Volunteer removes a skill from own profile
// Accounts are not deleted through this API (volunteer history must stay linked to the account).
require __DIR__ . '/../includes/api.php';
$u = require_role(['volunteer', 'organizer', 'super_admin'], true);
api_methods(['GET', 'POST', 'PUT', 'DELETE']);
$method = $_SERVER['REQUEST_METHOD'];
$isSA = $u['role'] === 'super_admin';
$isStaff = $u['role'] !== 'volunteer';

// Skills of the given users: [user_id => [ {volunteer_skill_id, skill_id, skill_name, proficiency_level}, ... ]]
function skills_of(PDO $pdo, array $ids) {
    if (!$ids) return [];
    $in = implode(',', array_map('intval', $ids));
    $out = [];
    foreach ($pdo->query("SELECT vs.volunteer_skill_id, vs.user_id, vs.skill_id, vs.proficiency_level, s.skill_name
                            FROM volunteer_skills vs JOIN skills s ON s.skill_id = vs.skill_id WHERE vs.user_id IN ($in) ORDER BY s.skill_name") as $r) {
        $out[$r['user_id']][] = ['volunteer_skill_id' => (int)$r['volunteer_skill_id'], 'skill_id' => (int)$r['skill_id'],
                                 'skill_name' => $r['skill_name'], 'proficiency_level' => $r['proficiency_level']];
    }
    return $out;
}

// Users in the JSON shape the frontend uses (explicit column list: the password column is never selected).
function load_users(PDO $pdo, $where, array $params, $order = 'u.last_name, u.first_name') {
    $st = $pdo->prepare("SELECT u.user_id, u.last_name, u.first_name, u.middle_name, u.email, u.role, u.phone, u.address, u.interests, u.availability,
                                (SELECT COUNT(*) FROM activities a WHERE a.created_by = u.user_id) AS activity_count
                           FROM users u WHERE $where ORDER BY $order");
    $st->execute($params);
    $rows = $st->fetchAll();
    $skills = skills_of($pdo, array_column($rows, 'user_id'));
    return array_map(fn($r) => [
        'user_id' => (int)$r['user_id'], 'first_name' => $r['first_name'], 'middle_name' => $r['middle_name'], 'last_name' => $r['last_name'],
        'full_name' => full_name($r), 'email' => $r['email'], 'phone' => $r['phone'], 'address' => $r['address'],
        'interests' => $r['interests'], 'availability' => $r['availability'],
        'role' => $r['role'], 'role_label' => role_label($r['role']), 'activity_count' => (int)$r['activity_count'],
        'skills' => $skills[$r['user_id']] ?? [],
    ], $rows);
}

// ------------------------------------------------------------------ GET
if ($method === 'GET') {
    if (isset($_GET['me'])) {
        $list = load_users($pdo, 'u.user_id = ?', [$u['user_id']]);
        api_out(['ok' => true, 'volunteer' => $list[0]]);
    }
    if (!$isStaff) api_error('Not allowed.', 403);

    if (($_GET['role'] ?? '') === 'organizer') {
        if (!$isSA) api_error('Only the Super Admin can manage Admins / Organizers.', 403);
        api_out(['ok' => true, 'organizers' => load_users($pdo, "u.role = 'organizer'", [])]);
    }
    if (isset($_GET['id'])) {
        $list = load_users($pdo, "u.user_id = ? AND u.role = 'volunteer'", [(int)$_GET['id']]);
        if (!$list) api_error('Volunteer not found.', 404);
        api_out(['ok' => true, 'volunteer' => $list[0]]);
    }
    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $like = "%$q%";
        $list = load_users($pdo, "u.role = 'volunteer' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)", [$like, $like, $like]);
    } else {
        $list = load_users($pdo, "u.role = 'volunteer'", []);
    }
    api_out(['ok' => true, 'volunteers' => $list]);
}

// ------------------------------------------------------------------ POST / DELETE: own skills (volunteer)
if ($method === 'POST' || $method === 'DELETE') {
    if (($_GET['resource'] ?? '') !== 'skills') api_error('Unknown resource. Use ?resource=skills.', 404);
    if ($u['role'] !== 'volunteer') api_error('Only volunteers manage their own skills.', 403);
    $uid = (int)$u['user_id'];

    if ($method === 'DELETE') {
        $st = $pdo->prepare('DELETE FROM volunteer_skills WHERE volunteer_skill_id = ? AND user_id = ?');
        $st->execute([(int)($_GET['volunteer_skill_id'] ?? 0), $uid]);
        if (!$st->rowCount()) api_error('Skill not found on your profile.', 404);
        api_out(['ok' => true, 'message' => 'Skill removed successfully.']);
    }

    $in = api_input();
    $skill = (int)($in['skill_id'] ?? 0);
    $level = (string)($in['proficiency_level'] ?? '');
    if (!in_array($level, LEVELS, true)) api_error('Please choose your proficiency level.', 422);
    $st = $pdo->prepare('SELECT COUNT(*) FROM skills WHERE skill_id = ?'); $st->execute([$skill]);
    if (!$st->fetchColumn()) api_error('Please choose a skill.', 422);
    $st = $pdo->prepare('SELECT volunteer_skill_id FROM volunteer_skills WHERE user_id = ? AND skill_id = ?'); $st->execute([$uid, $skill]);
    if ($id = $st->fetchColumn()) {
        $pdo->prepare('UPDATE volunteer_skills SET proficiency_level = ? WHERE volunteer_skill_id = ?')->execute([$level, $id]);
        api_out(['ok' => true, 'message' => 'Skill level updated successfully.']);
    }
    $pdo->prepare('INSERT INTO volunteer_skills (user_id, skill_id, proficiency_level) VALUES (?, ?, ?)')->execute([$uid, $skill, $level]);
    api_out(['ok' => true, 'message' => 'Skill added successfully.'], 201);
}

// ------------------------------------------------------------------ PUT
$in = api_input();

// (a) Super Admin changes a role. Same account: only users.role is updated.
if (array_key_exists('role', $in)) {
    if (!$isSA) api_error('Only the Super Admin can change roles.', 403);
    $target = (int)($in['user_id'] ?? 0);
    $new = (string)$in['role'];
    if (!in_array($new, ['volunteer', 'organizer'], true)) api_error('The role must be "volunteer" or "organizer".', 422);
    $st = $pdo->prepare("SELECT role FROM users WHERE user_id = ? AND role IN ('volunteer', 'organizer')");
    $st->execute([$target]);
    $current = $st->fetchColumn();
    if (!$current) api_error('Account not found.', 404);
    if ($current === $new) api_out(['ok' => true, 'message' => 'No change made.']);
    $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ? AND role = ?')->execute([$new, $target, $current]);
    api_out(['ok' => true, 'message' => $new === 'organizer'
        ? 'Role updated. This same account is now an Admin / Organizer.'
        : 'Role updated. This account is a Volunteer again.']);
}

// (b) A volunteer updates their own profile and/or interests + availability.
if ($u['role'] !== 'volunteer') api_error('Not allowed.', 403);
$uid = (int)$u['user_id'];
$touched = false;

if (array_key_exists('email', $in)) {                          // profile section
    $d = [];
    foreach (['first_name', 'middle_name', 'last_name', 'email', 'phone', 'address'] as $k) $d[$k] = trim((string)($in[$k] ?? ''));
    $new = (string)($in['new_password'] ?? '');
    if ($d['first_name'] === '' || $d['last_name'] === '') api_error('First name and last name are required.', 422);
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) api_error('Please enter a valid email address.', 422);
    foreach (['first_name' => 50, 'middle_name' => 50, 'last_name' => 50, 'email' => 100, 'phone' => 20, 'address' => 255] as $k => $max) {
        if (strlen($d[$k]) > $max) api_error("The field $k is too long ($max characters max).", 422);
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ?'); $st->execute([$d['email'], $uid]);
    if ($st->fetchColumn() > 0) api_error('Email is already registered.', 409);
    if ($new !== '' && strlen($new) < 8) api_error('New password must be at least 8 characters.', 422);

    $pdo->prepare('UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?')
        ->execute([$d['first_name'], $d['middle_name'] ?: null, $d['last_name'], $d['email'], $d['phone'] ?: null, $d['address'] ?: null, $uid]);
    if ($new !== '') $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
    $touched = true;
}
if (array_key_exists('interests', $in) || array_key_exists('availability', $in)) {      // interests section
    $interests = trim((string)($in['interests'] ?? ''));
    $availability = trim((string)($in['availability'] ?? ''));
    if (strlen($availability) > 255) api_error('Availability is too long (255 characters max).', 422);
    $pdo->prepare('UPDATE users SET interests = ?, availability = ? WHERE user_id = ?')->execute([$interests ?: null, $availability ?: null, $uid]);
    $touched = true;
}
if (!$touched) api_error('Nothing to update.', 422);
$list = load_users($pdo, 'u.user_id = ?', [$uid]);
api_out(['ok' => true, 'message' => 'Saved successfully.', 'volunteer' => $list[0]]);
