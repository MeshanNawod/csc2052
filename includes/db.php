<?php
/**
 * Database Connection — Sentinel Swarm AMS
 * Provides a secured PDO connection with proper charset, error mode, and fetch defaults.
 */
require_once __DIR__ . '/config.php';

$host     = 'localhost';
$dbname   = 'csc2052';
$db_user  = 'root';
$db_pass  = '';
$charset  = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Use native prepared statements (safer)
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    error_log('[Sentinel Swarm DB Error] ' . $e->getMessage());

    if (php_sapi_name() !== 'cli') {
        http_response_code(503);
        header('Content-Type: application/json');
    }
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed. Please contact the administrator.']));
}
?>