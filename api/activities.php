<?php
// REST-style endpoint: activities (table: activities, activity_requirements)
//   GET  api/activities.php            -> list. Volunteer/Super Admin: all activities. Admin / Organizer: own activities.
//                                         (volunteers also get my_status / my_role from their own assignments)
//   GET  api/activities.php?id=5       -> one activity
//   POST api/activities.php            -> create activity (Admin / Organizer, Super Admin)
//   PUT  api/activities.php            -> update activity, body must contain activity_id (owner organizer or Super Admin)
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

// Activities (with requirements) in the shape the frontend needs. $one = activity_id or 0 for the whole list.
function load_activities(PDO $pdo, array $u, $one = 0) {
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
    if ($u['role'] === 'organizer') { $sql .= ' AND a.created_by = ?'; $params[] = $u['user_id']; }
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
        else { $item['created_by'] = (int)$r['created_by']; $item['created_by_name'] = $r['created_by_name']; }
        $out[] = $item;
    }
    return $out;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    $list = load_activities($pdo, $u, $id);
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
    $pdo->prepare('INSERT INTO activities (created_by, activity_name, description, activity_date, location, max_volunteers) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$u['user_id'], $d['name'], $d['desc'] ?: null, $d['date'], $d['loc'] ?: null, $d['max']]);
    $id = (int)$pdo->lastInsertId();
    api_out(['ok' => true, 'message' => 'Activity created successfully.', 'activity' => load_activities($pdo, $u, $id)[0]], 201);
}

// PUT
$a = find_activity($pdo, $u, api_input()['activity_id'] ?? 0);
if (!$a) api_error('Activity not found.', 404);
$d = activity_input();
$taken = active_assignment_count($pdo, $a['activity_id']);
if ($d['max'] < $taken) api_error("This activity already has $taken assigned volunteers, so the maximum cannot be lower than that.", 422);
$pdo->prepare('UPDATE activities SET activity_name = ?, description = ?, activity_date = ?, location = ?, max_volunteers = ? WHERE activity_id = ?')
    ->execute([$d['name'], $d['desc'] ?: null, $d['date'], $d['loc'] ?: null, $d['max'], $a['activity_id']]);
api_out(['ok' => true, 'message' => 'Activity updated successfully.', 'activity' => load_activities($pdo, $u, (int)$a['activity_id'])[0]]);
