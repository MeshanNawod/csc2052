<?php
/**
 * System Logs API — Receives and stores debug logs from hardware nodes
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

$device_name = $_POST['device_name'] ?? 'Unknown';
$log_message = $_POST['message'] ?? '';
$level       = $_POST['level'] ?? 'INFO';

// ─── Authentication ───────────────────────────────────────────────
require_once __DIR__ . '/../includes/helpers.php';
if (!verifyHardwareSignature()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid signature']);
    exit;
}

if (!$log_message) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty log message']);
    exit;
}

$log_entry = sprintf(
    "[%s] [%s] [%s] %s\n",
    date('Y-m-d H:i:s'),
    strtoupper($level),
    $device_name,
    $log_message
);

$log_file = __DIR__ . '/../logs/system_activity.log';
file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

echo json_encode(['status' => 'ok', 'message' => 'Log stored']);
