<?php
// Session-based authentication + role protection.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// CSRF protection for every state-changing request (POST, PUT, PATCH, DELETE).
// The token comes from the form field "csrf_token" or, for fetch() calls, the "X-CSRF-Token" header.
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $expected = $_SESSION['csrf'] ?? '';
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || !hash_equals($expected, (string)$sent)) {
        http_response_code(419);
        if (strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false) {
            header('Content-Type: application/json');
            exit(json_encode(['ok' => false, 'error' => 'Session expired. Please reload the page.']));
        }
        exit('Your session expired. Please go back, reload the page and try again.');
    }
}

// The logged-in user, read fresh from the database on each request so role changes take effect immediately.
function current_user() {
    global $pdo;
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $st = $pdo->prepare('SELECT user_id, last_name, first_name, middle_name, email, role, phone, address, interests, availability FROM users WHERE user_id = ?');
        $st->execute([$_SESSION['user_id']]);
        $user = $st->fetch() ?: null;
    }
    return $user;
}

// Allow only the listed roles. Returns the user row.
function require_role(array $roles, $json = false) {
    $u = current_user();
    if (!$u) {
        if ($json) { http_response_code(401); header('Content-Type: application/json'); exit(json_encode(['ok' => false, 'error' => 'Please log in.'])); }
        redirect('login.php');
    }
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        if ($json) { header('Content-Type: application/json'); exit(json_encode(['ok' => false, 'error' => 'Not allowed.'])); }
        exit('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Access denied</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh"><div class="text-center"><h1 class="h3">Access denied</h1><p class="text-muted">Your role does not have access to this page.</p><a class="btn btn-primary" href="dashboard.php">Back to my dashboard</a></div></body></html>');
    }
    return $u;
}
