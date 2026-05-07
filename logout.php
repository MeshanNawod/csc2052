<?php
/**
 * Logout — UOP AMS
 * Properly destroys the session and all associated data before redirecting.
 */
require_once 'includes/config.php';

$sessionPath = __DIR__ . '/sessions';
if (is_dir($sessionPath)) {
    session_save_path($sessionPath);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => SESSION_COOKIE_SECURE,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Clear all session variables
$_SESSION = [];

// Destroy the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session on the server
session_destroy();

header('Location: login.php');
exit;
?>
