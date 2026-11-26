<?php
require_once __DIR__ . '/../includes/session_init.php';

require_authenticated(true);
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    require_csrf(true);
    $_SESSION['last_activity_at'] = time();
} elseif ($method !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$remaining = max(0, SESSION_IDLE_SECONDS - (time() - (int) $_SESSION['last_activity_at']));
session_write_close();
echo json_encode(['remaining' => $remaining]);
