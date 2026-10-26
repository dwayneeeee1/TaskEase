<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

requireValidCsrfToken();

if (($db = (new Database())->tryConnect()) !== null) {
    revokeRememberToken($db);
} else {
    clearRememberCookie();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: login.php');
exit;
