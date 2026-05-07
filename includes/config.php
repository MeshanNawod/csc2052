<?php
/**
 * Sentinel Swarm AMS — Central Configuration
 * 
 * Secrets should be set via environment variables in production.
 * Fallbacks below are for local development only.
 */

// ─────────────────────────────────────────────
// APPLICATION SETTINGS
// ─────────────────────────────────────────────
define('APP_NAME', 'Sentinel Swarm AMS');
define('APP_VERSION', '3.0.0');

// ─────────────────────────────────────────────
// ROLE CONSTANTS
// ─────────────────────────────────────────────
define('ROLE_ADMIN', 'admin');
define('ROLE_TEACHER', 'teacher');
define('ROLE_STUDENT', 'student');

// ─────────────────────────────────────────────
// SESSION CONFIGURATION
// ─────────────────────────────────────────────
define('SESSION_LIFETIME', 3600);
define('SESSION_COOKIE_SECURE', false);
define('STUDENT_DEFAULT_PASSWORD_PREFIX', true);

// ─────────────────────────────────────────────
// HARDWARE AUTHENTICATION SECRETS
// Must be set via environment variables in production.
// Generate: php -r "echo bin2hex(random_bytes(32));"
// ─────────────────────────────────────────────
define('HEARTBEAT_SECRET', getenv('SS_HEARTBEAT_SECRET') ?: 'ss_heartbeat_key_2052');
define('HARDWARE_API_KEY', getenv('SS_HARDWARE_API_KEY') ?: 'ss_hw_api_key_2052');

// ─────────────────────────────────────────────
// LOGIN RATE LIMITING
// ─────────────────────────────────────────────
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 15);

// ─────────────────────────────────────────────
// EMAIL NOTIFICATION SETTINGS
// ─────────────────────────────────────────────
define('EMAIL_ENABLED', true);
define('EMAIL_FROM', getenv('SS_EMAIL_FROM') ?: 'sentinel-ams@localhost');
define('EMAIL_FROM_NAME', getenv('SS_EMAIL_FROM_NAME') ?: 'Sentinel AMS');
define('EMAIL_ADMIN_RECIPIENT', getenv('SS_EMAIL_ADMIN') ?: 'admin@localhost');

// ─────────────────────────────────────────────
// ENVIRONMENT DETECTION
// ─────────────────────────────────────────────
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('IS_PRODUCTION', APP_ENV === 'production');

if (!function_exists('asset_url')) {
    function asset_url(string $path): string {
        $publicPath = ltrim($path, '/');
        $filePath = __DIR__ . '/../' . $publicPath;
        $version = is_file($filePath) ? (string) filemtime($filePath) : APP_VERSION;

        return $publicPath . '?v=' . rawurlencode($version);
    }
}
