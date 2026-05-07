<?php
/**
 * API: OTA Command Polling — Sentinel Swarm AMS
 * Hardware devices poll this endpoint for commands from the server.
 *
 * Usage:
 *   GET /api/ota.php?action=poll&device=UoP_Scanner_1&key=HARDWARE_API_KEY
 *   POST /api/ota.php?action=send&device=UoP_Scanner_1&command=START_COURSE MAT3063&key=HARDWARE_API_KEY
 */
require_once '../includes/config.php';
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$hw_key = $_REQUEST['key'] ?? '';
if (!hash_equals(HARDWARE_API_KEY, $hw_key)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$device = trim($_REQUEST['device'] ?? '');

if (empty($device)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Device ID required.']);
    exit;
}

// Commands are stored in a file-based queue per device
$queue_dir = __DIR__ . '/../sys_ota_queue';
if (!is_dir($queue_dir)) {
    mkdir($queue_dir, 0750, true);
}
$queue_file = $queue_dir . '/' . md5($device) . '.json';

// ─── Poll for commands ───────────────────────────────────────────
if ($action === 'poll') {
    if (file_exists($queue_file)) {
        $data = json_decode(file_get_contents($queue_file), true);
        if ($data && !empty($data['command'])) {
            // One-time delivery: delete after sending
            unlink($queue_file);
            echo json_encode(['status' => 'success', 'command' => $data['command']]);
            exit;
        }
    }
    echo json_encode(['status' => 'success', 'command' => '']);
    exit;
}

// ─── Send a command to device ─────────────────────────────────────
if ($action === 'send') {
    $command = trim($_REQUEST['command'] ?? $_POST['command'] ?? '');
    if (empty($command)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Command required.']);
        exit;
    }

    file_put_contents($queue_file, json_encode([
        'device'    => $device,
        'command'   => $command,
        'timestamp' => time(),
    ]), LOCK_EX);

    echo json_encode(['status' => 'success', 'message' => "Command queued for {$device}"]);
    exit;
}

// ─── Clear queue for device ───────────────────────────────────────
if ($action === 'clear') {
    if (file_exists($queue_file)) {
        unlink($queue_file);
    }
    echo json_encode(['status' => 'success', 'message' => "Queue cleared for {$device}"]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => "Unknown action: {$action}"]);
?>
