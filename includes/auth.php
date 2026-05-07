<?php
/**
 * Authentication Guard — Sentinel Swarm AMS v3
 * Multi-role: admin, teacher, student
 * Include at top of any page requiring authentication.
 */
require_once __DIR__ . '/config.php';

// Use project-level session directory (avoids C:\xampp\tmp permission issues)
$sessionPath = __DIR__ . '/../sessions';
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

// ─── ROLE LOCK: Prevent role hijacking after login ────────────────
// Once role_locked is set, user_role CANNOT be changed except by logout/login.
if (isset($_SESSION['role_locked']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] !== $_SESSION['role_locked']) {
    session_unset();
    session_destroy();
    header('Location: /csc2052/login.php?reason=hijack');
    exit;
}

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF validation function
function verifyCsrfToken() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed.']);
        exit;
    }
}

/**
 * Require CSRF validation for any mutating HTTP request (POST/PUT/DELETE/PATCH).
 * Call this at the top of API endpoints that modify state.
 */
function requireCsrfIfMutating() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        verifyCsrfToken();
    }
}

// ─── Role Checking Helpers ─────────────────────────────────────
function requireRole(string ...$roles): void {
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Insufficient permissions.']);
        exit;
    }
}

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isTeacher(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'teacher';
}

function isStudent(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student';
}

function currentRole(): string {
    return $_SESSION['user_role'] ?? 'none';
}

function currentUser(): array {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'teacher_id' => $_SESSION['teacher_id'] ?? null,
        'student_no' => $_SESSION['student_no'] ?? null,
    ];
}

// Security headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 0');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self' http: https:;");
}

// ─── Check for valid authenticated session ──────────────────────
// Supports: admin_logged_in (legacy) OR user_logged_in (new multi-role)
$is_legacy_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_multi_role = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if (!$is_legacy_admin && !$is_multi_role) {
    session_unset();
    session_destroy();
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: /csc2052/login.php' . ($redirect ? '?redirect=' . $redirect : ''));
    exit;
}

// Normalize legacy admin session to multi-role
if ($is_legacy_admin && !$is_multi_role) {
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_role'] = 'admin';
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = $_SESSION['admin_username'] ?? 'admin';
    $_SESSION['full_name'] = 'System Administrator';
}

// Lock role after login to prevent hijacking
if (isset($_SESSION['user_role']) && !isset($_SESSION['role_locked'])) {
    $_SESSION['role_locked'] = $_SESSION['user_role'];
}

// Session timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
    session_unset();
    session_destroy();
    header('Location: /csc2052/login.php?reason=timeout');
    exit;
}
$_SESSION['last_activity'] = time();
?>
