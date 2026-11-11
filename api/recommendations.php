<?php
// REST-style JSON endpoint for AI matching.
//   Organizer / Super Admin:
//     GET  api/recommendations.php?activity_id=1   -> stored recommendations (volunteers) for the activity
//     POST api/recommendations.php (activity_id, csrf_token) -> recalculate scores, store them, return them
//   Volunteer:
//     GET  api/recommendations.php                 -> upcoming activities that match MY skills (read only, nothing is saved or assigned)
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/matching.php';
header('Content-Type: application/json');

$u = require_role(['volunteer', 'super_admin', 'organizer'], true);

// ---- Volunteer: suggestions only. Never writes to the database and never assigns anyone.
if ($u['role'] === 'volunteer') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit(json_encode(['ok' => false, 'error' => 'Method not allowed.'])); }
    try {
        echo json_encode(['ok' => true] + recommend_activities_for_volunteer($pdo, $u));
    } catch (Throwable $e) {
        error_log('CVMS recommendations (volunteer) error: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Recommendations could not be loaded.']);
    }
    exit;
}

// ---- Organizer / Super Admin (unchanged)
$a = find_activity($pdo, $u, $_REQUEST['activity_id'] ?? 0);
if (!$a) { http_response_code(404); exit(json_encode(['ok' => false, 'error' => 'Activity not found.'])); }

try {
    $breakdown = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') $breakdown = generate_recommendations($pdo, $a);
    elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit(json_encode(['ok' => false, 'error' => 'Method not allowed.'])); }

    echo json_encode(['ok' => true, 'generated' => $_SERVER['REQUEST_METHOD'] === 'POST',
                      'recommendations' => get_recommendations($pdo, $a['activity_id'], $breakdown)]);
} catch (Throwable $e) {
    error_log('CVMS recommendations (organizer) error: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Recommendations could not be generated.']);
}
