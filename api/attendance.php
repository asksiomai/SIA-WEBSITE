<?php
// REST-style endpoint: attendance, volunteer hours and participation history
// (tables: attendance, assignments, activities - no extra hours/participation table)
//   GET  api/attendance.php                  -> Volunteer: own hours + participation history
//   GET  api/attendance.php?activity_id=1    -> Admin / Organizer, Super Admin: assigned volunteers + attendance records of the activity
//   POST api/attendance.php                  -> record attendance {assignment_id, attendance_date, status, check_in_time, check_out_time, remarks}
//   PUT  api/attendance.php                  -> edit a record     {attendance_id, attendance_date, status, check_in_time, check_out_time, remarks}
// No DELETE: attendance is a record of what happened; mistakes are corrected with PUT.
require __DIR__ . '/../includes/api.php';
$u = require_role(['volunteer', 'organizer', 'super_admin'], true);
api_methods(['GET', 'POST', 'PUT']);
$method = $_SERVER['REQUEST_METHOD'];

function hhmm($t) { return $t ? substr($t, 0, 5) : null; }

// Keeps assignments.assignment_status consistent with its attendance records:
//   at least one Present/Late record -> Completed;  none (e.g. edited to Absent) -> back to Assigned.
// Cancelled assignments are never touched.
function sync_assignment_status(PDO $pdo, $assignment_id) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE assignment_id = ? AND status IN ('Present','Late')");
    $st->execute([$assignment_id]);
    $new = $st->fetchColumn() > 0 ? 'Completed' : 'Assigned';
    $pdo->prepare("UPDATE assignments SET assignment_status = ? WHERE assignment_id = ? AND assignment_status IN ('Assigned','Completed')")->execute([$new, $assignment_id]);
}

// Validates the body and calculates hours. Absent = 0.00 hours and no times; Present/Late = check-out minus check-in.
function attendance_input(array $in) {
    $date = (string)($in['attendance_date'] ?? '');
    $status = (string)($in['status'] ?? '');
    $remarks = trim((string)($in['remarks'] ?? ''));
    if (!valid_date($date)) api_error('Please choose a valid attendance date.', 422);
    if (!in_array($status, ['Present', 'Late', 'Absent'], true)) api_error('Please choose an attendance status (Present, Late or Absent).', 422);
    if ($status === 'Absent') return [$date, $status, null, null, 0.00, $remarks ?: null];
    $inT = (string)($in['check_in_time'] ?? ''); $outT = (string)($in['check_out_time'] ?? '');
    if ($inT === '' || $outT === '') api_error('Check-in and check-out time are required for Present or Late.', 422);
    if (!valid_time($inT) || !valid_time($outT)) api_error('Please enter valid check-in and check-out times.', 422);
    $secs = strtotime("2000-01-01 $outT") - strtotime("2000-01-01 $inT");
    if ($secs <= 0) api_error('Check-out time must be later than check-in time.', 422);
    return [$date, $status, strlen($inT) === 5 ? "$inT:00" : $inT, strlen($outT) === 5 ? "$outT:00" : $outT, round($secs / 3600, 2), $remarks ?: null];
}

if ($method === 'GET') {
    // ---- Volunteer: own hours and participation history
    if ($u['role'] === 'volunteer') {
        $st = $pdo->prepare("SELECT s.assignment_id, a.activity_name, a.activity_date, s.role, s.assignment_status, t.attendance_date, t.status AS att_status, t.hours_rendered
                               FROM assignments s JOIN activities a ON a.activity_id = s.activity_id
                               LEFT JOIN attendance t ON t.assignment_id = s.assignment_id
                              WHERE s.user_id = ? ORDER BY COALESCE(t.attendance_date, a.activity_date) DESC");
        $st->execute([$u['user_id']]);
        $rows = []; $total = 0.0; $done = [];
        foreach ($st->fetchAll() as $r) {
            $total += (float)$r['hours_rendered'];
            if ($r['assignment_status'] === 'Completed') $done[$r['assignment_id']] = true;
            $rows[] = ['activity_name' => $r['activity_name'], 'activity_date' => $r['activity_date'], 'role' => $r['role'],
                       'assignment_status' => $r['assignment_status'], 'attendance_date' => $r['attendance_date'],
                       'attendance_status' => $r['att_status'], 'hours_rendered' => (float)$r['hours_rendered']];
        }
        api_out(['ok' => true, 'total_hours' => round($total, 2), 'completed_activities' => count($done), 'history' => $rows]);
    }

    // ---- Admin / Organizer, Super Admin: one activity
    $a = find_activity($pdo, $u, $_GET['activity_id'] ?? 0);
    if (!$a) api_error('Activity not found.', 404);
    $aid = (int)$a['activity_id'];

    $st = $pdo->prepare("SELECT s.assignment_id, s.user_id, s.role, s.assignment_status, u.first_name, u.middle_name, u.last_name
                           FROM assignments s JOIN users u ON u.user_id = s.user_id
                          WHERE s.activity_id = ? AND s.assignment_status IN ('Assigned','Completed') ORDER BY u.last_name");
    $st->execute([$aid]);
    $assigned = $st->fetchAll();

    $st = $pdo->prepare("SELECT t.*, s.role AS act_role, u.first_name, u.middle_name, u.last_name
                           FROM attendance t JOIN assignments s ON s.assignment_id = t.assignment_id JOIN users u ON u.user_id = s.user_id
                          WHERE s.activity_id = ? ORDER BY t.attendance_date DESC, t.attendance_id DESC");
    $st->execute([$aid]);
    $records = []; $latest = [];
    foreach ($st->fetchAll() as $t) {
        $rec = ['attendance_id' => (int)$t['attendance_id'], 'assignment_id' => (int)$t['assignment_id'], 'volunteer_name' => full_name($t),
                'role' => $t['act_role'], 'attendance_date' => $t['attendance_date'], 'status' => $t['status'],
                'check_in_time' => hhmm($t['check_in_time']), 'check_out_time' => hhmm($t['check_out_time']),
                'hours_rendered' => (float)$t['hours_rendered'], 'remarks' => $t['remarks']];
        $records[] = $rec;
        if (!isset($latest[$rec['assignment_id']])) $latest[$rec['assignment_id']] = $rec;   // list is newest first
    }
    $out = array_map(fn($s) => ['assignment_id' => (int)$s['assignment_id'], 'user_id' => (int)$s['user_id'], 'volunteer_name' => full_name($s),
                                'role' => $s['role'], 'assignment_status' => $s['assignment_status'], 'latest' => $latest[$s['assignment_id']] ?? null], $assigned);
    api_out(['ok' => true,
             'activity' => ['activity_id' => $aid, 'activity_name' => $a['activity_name'], 'activity_date' => $a['activity_date']],
             'assigned' => $out, 'records' => $records]);
}

// POST and PUT: Admin / Organizer and Super Admin only.
if ($u['role'] === 'volunteer') api_error('Not allowed.', 403);
$in = api_input();

if ($method === 'POST') {
    $st = $pdo->prepare("SELECT * FROM assignments WHERE assignment_id = ? AND assignment_status IN ('Assigned','Completed')");
    $st->execute([(int)($in['assignment_id'] ?? 0)]);
    $as = $st->fetch();
    if (!$as || !find_activity($pdo, $u, $as['activity_id'])) api_error('Assignment not found.', 404);
    [$date, $status, $tin, $tout, $hours, $remarks] = attendance_input($in);

    $st = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE assignment_id = ? AND attendance_date = ?');
    $st->execute([$as['assignment_id'], $date]);
    if ($st->fetchColumn()) api_error('Attendance for this volunteer on that date already exists. Edit the existing record instead.', 409);

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO attendance (assignment_id, attendance_date, check_in_time, check_out_time, hours_rendered, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$as['assignment_id'], $date, $tin, $tout, $hours, $status, $remarks]);
    $newId = (int)$pdo->lastInsertId();
    sync_assignment_status($pdo, $as['assignment_id']);
    $pdo->commit();
    api_out(['ok' => true, 'message' => 'Attendance saved successfully.', 'attendance_id' => $newId], 201);
}

// PUT
$st = $pdo->prepare("SELECT t.attendance_id, t.assignment_id, s.activity_id FROM attendance t JOIN assignments s ON s.assignment_id = t.assignment_id
                      WHERE t.attendance_id = ? AND s.assignment_status IN ('Assigned','Completed')");
$st->execute([(int)($in['attendance_id'] ?? 0)]);
$rec = $st->fetch();
if (!$rec || !find_activity($pdo, $u, $rec['activity_id'])) api_error('Attendance record not found.', 404);
[$date, $status, $tin, $tout, $hours, $remarks] = attendance_input($in);

$st = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE assignment_id = ? AND attendance_date = ? AND attendance_id <> ?');
$st->execute([$rec['assignment_id'], $date, $rec['attendance_id']]);
if ($st->fetchColumn()) api_error('Another attendance record already exists for that date.', 409);

$pdo->beginTransaction();
$pdo->prepare('UPDATE attendance SET attendance_date = ?, check_in_time = ?, check_out_time = ?, hours_rendered = ?, status = ?, remarks = ? WHERE attendance_id = ?')
    ->execute([$date, $tin, $tout, $hours, $status, $remarks, $rec['attendance_id']]);
sync_assignment_status($pdo, $rec['assignment_id']);
$pdo->commit();
api_out(['ok' => true, 'message' => 'Attendance updated successfully.']);
