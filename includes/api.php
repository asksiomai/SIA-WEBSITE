<?php
// Small helper shared by the REST-style endpoints in /api.
// Every endpoint: session login -> role check (require_role) -> CSRF check (includes/auth.php) -> JSON out.
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

function api_out($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function api_error($message, $code = 400)
{
    api_out(['ok' => false, 'error' => $message], $code);
}

// Request body as an array: JSON body (fetch) or ordinary form fields.
function api_input()
{
    static $in = null;
    if ($in !== null)
        return $in;
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json))
        return $in = $json;
    if ($_SERVER['REQUEST_METHOD'] === 'POST')
        return $in = $_POST;
    parse_str((string) $raw, $form);
    return $in = $form;
}

// Only the listed HTTP methods are allowed on an endpoint.
function api_methods(array $allowed)
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        api_error('Method not allowed.', 405);
    }
}

// Any uncaught error becomes a clean JSON 500 (details are not shown to the user).
set_exception_handler(function ($e) {
    global $pdo;
    if (isset($pdo) && $pdo->inTransaction())
        $pdo->rollBack();
    api_error('The request could not be completed.', 500);
});

// "HH:MM" or "HH:MM:SS" check used for attendance times.
function valid_time($t)
{
    return is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $t);
}
function valid_date($d)
{
    $dt = DateTime::createFromFormat('Y-m-d', (string) $d);
    return $dt && $dt->format('Y-m-d') === $d;
}
