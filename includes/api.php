<?php
// Loaded by every file in api/. Answers in JSON, never HTML.

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/jobs.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e) {
    global $config;
    http_response_code(500);
    echo json_encode(['error' => $config['app']['debug'] ? $e->getMessage() : 'Something went wrong. Please try again.']);
});

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $code = 400): never
{
    json_out(['error' => $message], $code);
}

// Logged-in user for an API call; POST calls must also send the CSRF token header.
function api_user(?string $role = null): array
{
    $user = current_user();
    if (!$user) json_error('Please log in again.', 401);
    if ($role !== null && $user['role'] !== $role) json_error('Not allowed.', 403);
    if ($user['must_change_password']) json_error('Please set a new password first.', 403);

    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && !hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        json_error('Your session expired. Refresh the page.', 403);
    }
    return $user;
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Use POST.', 405);
}

// JSON request body as an array.
function input(): array
{
    static $data = null;
    if ($data === null) {
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
    }
    return $data;
}

function input_float(string $key): ?float
{
    $v = filter_var(input()[$key] ?? null, FILTER_VALIDATE_FLOAT);
    return $v === false ? null : $v;
}

function valid_lat_lng(?float $lat, ?float $lng): bool
{
    return $lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
}
