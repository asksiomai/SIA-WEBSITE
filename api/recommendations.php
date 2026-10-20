<?php
// REST-style JSON endpoint for AI matching.
//   GET  api/recommendations.php?activity_id=1   -> stored recommendations for the activity
//   POST api/recommendations.php (activity_id, csrf_token) -> recalculate scores, store them, return them
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/matching.php';
header('Content-Type: application/json');

$u = require_role(['super_admin', 'organizer'], true);
$a = find_activity($pdo, $u, $_REQUEST['activity_id'] ?? 0);
if (!$a) { http_response_code(404); exit(json_encode(['ok' => false, 'error' => 'Activity not found.'])); }

try {
    $breakdown = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') $breakdown = generate_recommendations($pdo, $a);
    elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit(json_encode(['ok' => false, 'error' => 'Method not allowed.'])); }

    echo json_encode(['ok' => true, 'generated' => $_SERVER['REQUEST_METHOD'] === 'POST',
                      'recommendations' => get_recommendations($pdo, $a['activity_id'], $breakdown)]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Recommendations could not be generated.']);
}
