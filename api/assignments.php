<?php
// REST-style endpoint: assignments (table: assignments)
//   GET  api/assignments.php?activity_id=1   -> assignments of one activity + slot information
//   POST api/assignments.php                 -> assign a volunteer: {activity_id, user_id, role}   (status = Assigned)
//   PUT  api/assignments.php                 -> Cancel Assignment: {assignment_id, assignment_status: "Cancelled"}
// The AI never calls this endpoint: only the Admin / Organizer or Super Admin assigns, after reviewing recommendations.
// No DELETE: a cancelled assignment stays in the table as a historical record (assignment_status = 'Cancelled').
require __DIR__ . '/../includes/api.php';
$u = require_role(['super_admin', 'organizer'], true);
api_methods(['GET', 'POST', 'PUT']);
$method = $_SERVER['REQUEST_METHOD'];
$in = $method === 'GET' ? $_GET : api_input();

if ($method === 'GET') {
    $a = find_activity($pdo, $u, $_GET['activity_id'] ?? 0);
    if (!$a) api_error('Activity not found.', 404);
    $st = $pdo->prepare('SELECT s.assignment_id, s.activity_id, s.user_id, s.role, s.assignment_status,
                                u.first_name, u.middle_name, u.last_name, u.email,
                                (SELECT COUNT(*) FROM attendance t WHERE t.assignment_id = s.assignment_id) AS attendance_count
                           FROM assignments s JOIN users u ON u.user_id = s.user_id WHERE s.activity_id = ?
                          ORDER BY FIELD(s.assignment_status, "Assigned", "Completed", "Cancelled"), u.last_name');
    $st->execute([$a['activity_id']]);
    $rows = array_map(fn($r) => [
        'assignment_id'     => (int)$r['assignment_id'],
        'activity_id'       => (int)$r['activity_id'],
        'user_id'           => (int)$r['user_id'],
        'volunteer_name'    => full_name($r),
        'email'             => $r['email'],
        'role'              => $r['role'],
        'assignment_status' => $r['assignment_status'],
        'has_attendance'    => (int)$r['attendance_count'] > 0,
    ], $st->fetchAll());
    api_out(['ok' => true,
             'activity' => ['activity_id' => (int)$a['activity_id'], 'activity_name' => $a['activity_name'], 'activity_date' => $a['activity_date'],
                            'max_volunteers' => (int)$a['max_volunteers'],
                            'taken' => active_assignment_count($pdo, $a['activity_id']),
                            'started' => (bool)($a['activity_date'] && $a['activity_date'] < date('Y-m-d'))],
             'assignments' => $rows]);
}

if ($method === 'POST') {
    $a = find_activity($pdo, $u, $in['activity_id'] ?? 0);
    if (!$a) api_error('Activity not found.', 404);
    $aid = (int)$a['activity_id'];
    $uid = (int)($in['user_id'] ?? 0);
    $role = trim((string)($in['role'] ?? ''));
    if ($role === '') api_error("Please enter the volunteer's activity role.", 422);
    if (strlen($role) > 100) api_error('The activity role is too long (100 characters max).', 422);

    $pdo->beginTransaction();
    // Lock the activity row so two assignments at the same moment cannot exceed max_volunteers.
    $pdo->prepare('SELECT activity_id FROM activities WHERE activity_id = ? FOR UPDATE')->execute([$aid]);
    $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ? AND role = 'volunteer'"); $st->execute([$uid]);
    if (!$st->fetchColumn()) { $pdo->rollBack(); api_error('Volunteer not found.', 404); }
    $st = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE activity_id = ? AND user_id = ? AND assignment_status IN ('Assigned','Completed')"); $st->execute([$aid, $uid]);
    if ($st->fetchColumn()) { $pdo->rollBack(); api_error('This volunteer is already assigned to this activity.', 409); }
    if (active_assignment_count($pdo, $aid) >= (int)$a['max_volunteers']) { $pdo->rollBack(); api_error('This activity already has the maximum number of volunteers.', 409); }
    $pdo->prepare("INSERT INTO assignments (activity_id, user_id, role, assignment_status) VALUES (?, ?, ?, 'Assigned')")->execute([$aid, $uid, $role]);
    $newId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE recommendations SET is_selected = 1 WHERE activity_id = ? AND user_id = ?')->execute([$aid, $uid]);
    $pdo->commit();
    api_out(['ok' => true, 'message' => 'Volunteer assigned successfully.', 'assignment_id' => $newId], 201);
}

// PUT = Cancel Assignment (the only change this endpoint allows on an existing assignment).
$st = $pdo->prepare('SELECT * FROM assignments WHERE assignment_id = ?');
$st->execute([(int)($in['assignment_id'] ?? 0)]);
$as = $st->fetch();
if (!$as) api_error('Assignment not found.', 404);
$a = find_activity($pdo, $u, $as['activity_id']);            // organizer can only touch assignments of their own activities
if (!$a) api_error('Assignment not found.', 404);
if (($in['assignment_status'] ?? '') !== 'Cancelled') api_error('Only the status "Cancelled" can be set on an assignment.', 422);
if ($as['assignment_status'] !== 'Assigned') api_error('Only an assignment with the status "Assigned" can be cancelled.', 409);
if ($a['activity_date'] && $a['activity_date'] < date('Y-m-d')) api_error('This activity has already started or finished, so the assignment cannot be cancelled.', 409);
$st = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE assignment_id = ?'); $st->execute([$as['assignment_id']]);
if ($st->fetchColumn()) api_error('This assignment already has attendance records and cannot be cancelled.', 409);

$pdo->prepare("UPDATE assignments SET assignment_status = 'Cancelled' WHERE assignment_id = ?")->execute([$as['assignment_id']]);
$pdo->prepare('UPDATE recommendations SET is_selected = 0 WHERE activity_id = ? AND user_id = ?')->execute([$as['activity_id'], $as['user_id']]);
api_out(['ok' => true, 'message' => 'Assignment cancelled successfully. The volunteer account is not affected.']);
