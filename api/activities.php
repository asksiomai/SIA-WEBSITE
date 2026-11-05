<?php
// REST-style endpoint: activities (table: activities, activity_requirements)
//   GET  api/activities.php            -> list. Volunteer/Super Admin: all activities. Admin / Organizer: own activities.
//                                         (volunteers also get my_status / my_role from their own assignments)
//   GET  api/activities.php?scope=all  -> Admin / Organizer only: ALL activities of every organizer (read only, "See Activities")
//   GET  api/activities.php?id=5       -> one activity
//   POST api/activities.php            -> create activity + its required skills in one request (Admin / Organizer, Super Admin)
//                                         body: activity fields + requirements: [{skill_id, required_level}, ...]
//   PUT  api/activities.php            -> update activity, body must contain activity_id (owner organizer or Super Admin);
//                                         when "requirements" is sent, the activity's required skills are replaced by that list
// No DELETE: activities that already have assignments are history and must stay (foreign keys).
require __DIR__ . '/../includes/api.php';
$u = require_role(['volunteer', 'organizer', 'super_admin'], true);
api_methods(['GET', 'POST', 'PUT']);
$isStaff = $u['role'] !== 'volunteer';

// Reads and validates the activity fields from the request body.
function activity_input() {
    $in = api_input();
    $d = [
        'name' => trim((string)($in['activity_name'] ?? '')),
        'desc' => trim((string)($in['description'] ?? '')),
        'date' => trim((string)($in['activity_date'] ?? '')),
        'loc'  => trim((string)($in['location'] ?? '')),
        'max'  => (int)($in['max_volunteers'] ?? 0),
    ];
    if ($d['name'] === '') api_error('Activity name is required.', 422);
    if (strlen($d['name']) > 150) api_error('Activity name is too long (150 characters max).', 422);
    if (strlen($d['loc']) > 255) api_error('Location is too long (255 characters max).', 422);
    if (!valid_date($d['date'])) api_error('Please choose a valid activity date.', 422);
    if ($d['max'] < 1) api_error('Maximum volunteers must be at least 1.', 422);
    return $d;
}

// Reads the optional "requirements" list [{skill_id, required_level}, ...] from the request body.
// Returns null when the field was not sent (existing requirements stay as they are), otherwise a validated list.
function requirements_input(PDO $pdo) {
    $in = api_input();
    if (!array_key_exists('requirements', $in)) return null;
    $list = $in['requirements'];
    if ($list === '' || $list === null) $list = [];
    if (!is_array($list)) api_error('The required skills are not valid.', 422);

    $out = []; $seen = [];
    foreach ($list as $row) {
        $skill = is_array($row) ? (int)($row['skill_id'] ?? 0) : 0;
        $level = is_array($row) ? (string)($row['required_level'] ?? '') : '';
        if ($skill <= 0) api_error('Please choose a skill in every required-skill row, or remove the empty row.', 422);
        if (!in_array($level, LEVELS, true)) api_error('Please choose a required level for every skill.', 422);
        if (isset($seen[$skill])) api_error('The same skill was added more than once. Each skill can only be required once.', 422);
        $seen[$skill] = true;
        $out[] = ['skill_id' => $skill, 'required_level' => $level];
    }
    if ($out) {
        $ids = implode(',', array_keys($seen));            // integers only
        $found = (int)$pdo->query("SELECT COUNT(*) FROM skills WHERE skill_id IN ($ids)")->fetchColumn();
        if ($found !== count($out)) api_error('One of the selected skills does not exist.', 422);
    }
    return $out;
}

// Makes the activity_requirements rows of one activity match $reqs. Existing rows keep their requirement_id
// (level updated), new skills are inserted, skills that were removed from the list are deleted.
function save_requirements(PDO $pdo, $activity_id, array $reqs) {
    $st = $pdo->prepare('SELECT requirement_id, skill_id FROM activity_requirements WHERE activity_id = ? ORDER BY requirement_id');
    $st->execute([$activity_id]);
    $have = [];
    foreach ($st->fetchAll() as $r) $have[(int)$r['skill_id']][] = (int)$r['requirement_id'];

    foreach ($reqs as $r) {
        if (!empty($have[$r['skill_id']])) {
            $rid = array_shift($have[$r['skill_id']]);
            $pdo->prepare('UPDATE activity_requirements SET required_level = ? WHERE requirement_id = ?')->execute([$r['required_level'], $rid]);
        } else {
            $pdo->prepare('INSERT INTO activity_requirements (activity_id, skill_id, required_level) VALUES (?, ?, ?)')->execute([$activity_id, $r['skill_id'], $r['required_level']]);
        }
    }
    foreach ($have as $ids) {                                // whatever is left was removed in the form
        foreach ($ids as $rid) $pdo->prepare('DELETE FROM activity_requirements WHERE requirement_id = ? AND activity_id = ?')->execute([$rid, $activity_id]);
    }
}

// Activities (with requirements) in the shape the frontend needs. $one = activity_id or 0 for the whole list.
// $all = true lets an organizer see every organizer's activities (read only); otherwise organizers get their own.
function load_activities(PDO $pdo, array $u, $one = 0, $all = false) {
    $volunteer = $u['role'] === 'volunteer';
    $sql = "SELECT a.*, CONCAT(cu.first_name, ' ', cu.last_name) AS created_by_name,
                   (SELECT COUNT(*) FROM activity_requirements r WHERE r.activity_id = a.activity_id) AS req_count,
                   (SELECT COUNT(*) FROM assignments s WHERE s.activity_id = a.activity_id AND s.assignment_status IN ('Assigned','Completed')) AS taken";
    $params = [];
    if ($volunteer) {
        $sql .= ", (SELECT s.assignment_status FROM assignments s WHERE s.activity_id = a.activity_id AND s.user_id = ? ORDER BY FIELD(s.assignment_status, 'Assigned', 'Completed', 'Cancelled') LIMIT 1) AS my_status,
                   (SELECT s.role FROM assignments s WHERE s.activity_id = a.activity_id AND s.user_id = ? ORDER BY FIELD(s.assignment_status, 'Assigned', 'Completed', 'Cancelled') LIMIT 1) AS my_role";
        $params = [$u['user_id'], $u['user_id']];
    }
    $sql .= ' FROM activities a JOIN users cu ON cu.user_id = a.created_by WHERE 1 = 1';
    if ($u['role'] === 'organizer' && !$all) { $sql .= ' AND a.created_by = ?'; $params[] = $u['user_id']; }
    if ($one) { $sql .= ' AND a.activity_id = ?'; $params[] = $one; }
    $sql .= $volunteer ? ' ORDER BY (a.activity_date >= CURDATE()) DESC, a.activity_date ASC' : ' ORDER BY a.activity_date DESC, a.activity_id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    $reqs = [];
    if ($rows) {
        $ids = implode(',', array_map(fn($r) => (int)$r['activity_id'], $rows));   // integers from our own query
        foreach ($pdo->query("SELECT r.requirement_id, r.activity_id, r.skill_id, r.required_level, s.skill_name
                                FROM activity_requirements r JOIN skills s ON s.skill_id = r.skill_id
                               WHERE r.activity_id IN ($ids) ORDER BY s.skill_name") as $r) {
            $reqs[$r['activity_id']][] = ['requirement_id' => (int)$r['requirement_id'], 'skill_id' => (int)$r['skill_id'],
                                          'skill_name' => $r['skill_name'], 'required_level' => $r['required_level']];
        }
    }
    $out = [];
    foreach ($rows as $r) {
        $item = [
            'activity_id'    => (int)$r['activity_id'],
            'activity_name'  => $r['activity_name'],
            'description'    => $r['description'],
            'activity_date'  => $r['activity_date'],
            'location'       => $r['location'],
            'max_volunteers' => (int)$r['max_volunteers'],
            'taken'          => (int)$r['taken'],
            'req_count'      => (int)$r['req_count'],
            'requirements'   => $reqs[$r['activity_id']] ?? [],
        ];
        if ($volunteer) { $item['my_status'] = $r['my_status']; $item['my_role'] = $r['my_role']; }
        else { $item['created_by'] = (int)$r['created_by']; $item['created_by_name'] = $r['created_by_name']; $item['is_mine'] = (int)$r['created_by'] === (int)$u['user_id']; }
        $out[] = $item;
    }
    return $out;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    $all = $isStaff && ($_GET['scope'] ?? '') === 'all';
    $list = load_activities($pdo, $u, $id, $all);
    if ($id) {
        if (!$list) api_error('Activity not found.', 404);
        api_out(['ok' => true, 'activity' => $list[0]]);
    }
    api_out(['ok' => true, 'activities' => $list]);
}

// POST and PUT are staff-only.
if (!$isStaff) api_error('Not allowed.', 403);

if ($method === 'POST') {
    $d = activity_input();
    $reqs = requirements_input($pdo) ?? [];
    // Activity and its required skills are saved together: if one part fails, nothing is saved.
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO activities (created_by, activity_name, description, activity_date, location, max_volunteers) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$u['user_id'], $d['name'], $d['desc'] ?: null, $d['date'], $d['loc'] ?: null, $d['max']]);
    $id = (int)$pdo->lastInsertId();
    save_requirements($pdo, $id, $reqs);
    $pdo->commit();
    api_out(['ok' => true, 'message' => 'Activity created successfully.', 'activity' => load_activities($pdo, $u, $id)[0]], 201);
}

// PUT
$a = find_activity($pdo, $u, api_input()['activity_id'] ?? 0);
if (!$a) api_error('Activity not found.', 404);
$d = activity_input();
$taken = active_assignment_count($pdo, $a['activity_id']);
if ($d['max'] < $taken) api_error("This activity already has $taken assigned volunteers, so the maximum cannot be lower than that.", 422);
$reqs = requirements_input($pdo);                              // null = "requirements" not sent: leave them unchanged
$pdo->beginTransaction();
$pdo->prepare('UPDATE activities SET activity_name = ?, description = ?, activity_date = ?, location = ?, max_volunteers = ? WHERE activity_id = ?')
    ->execute([$d['name'], $d['desc'] ?: null, $d['date'], $d['loc'] ?: null, $d['max'], $a['activity_id']]);
if ($reqs !== null) save_requirements($pdo, (int)$a['activity_id'], $reqs);
$pdo->commit();
api_out(['ok' => true, 'message' => 'Activity updated successfully.', 'activity' => load_activities($pdo, $u, (int)$a['activity_id'])[0]]);
