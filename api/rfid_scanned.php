<?php
/**
 * API: RFID Scanned — Sentinel Swarm AMS
 * 
 * Two roles:
 *  1. Hardware PUSH: ESP32 sends scanned RFID UID to this endpoint.
 *     Authenticated via X-Hardware-Key header or hardware_key param.
 *  2. Dashboard CONSUME: Admin UI polls to consume the buffered UID.
 *     Authenticated via session (auth.php).
 */
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$buffer_file = __DIR__ . '/../sys_latest_rfid.txt';

// ─── Dashboard Consume (GET) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'consume') {
    // Requires a valid session
    require '../includes/auth.php';

    if (!file_exists($buffer_file)) {
        echo json_encode(['status' => 'empty']);
        exit;
    }

    $uid = trim(file_get_contents($buffer_file));
    if (!empty($uid) && preg_match('/^[a-zA-Z0-9]{1,20}$/', $uid)) {
        // Atomically clear the buffer after reading
        file_put_contents($buffer_file, '', LOCK_EX);
        echo json_encode(['status' => 'success', 'uid' => strtoupper($uid)]);
    } else {
        echo json_encode(['status' => 'empty']);
    }
    exit;
}

// ─── Hardware Push (GET or POST from ESP32) ───────────────────────
$hw_key = $_SERVER['HTTP_X_HARDWARE_KEY'] ?? ($_GET['hardware_key'] ?? ($_POST['hardware_key'] ?? ''));

if (!hash_equals(HARDWARE_API_KEY, $hw_key)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$uid = trim($_GET['uid'] ?? ($_POST['uid'] ?? ''));

if (empty($uid)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing UID parameter.']);
    exit;
}

// Validate UID: alphanumeric only, 1–20 chars
if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $uid)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid UID format. Must be 1-20 alphanumeric characters.']);
    exit;
}

// Rate limit: ignore pushes within 2 seconds of the last one
if (file_exists($buffer_file)) {
    $last_modified = filemtime($buffer_file);
    if ((time() - $last_modified) < 2) {
        echo json_encode(['status' => 'ok', 'message' => 'Rate limited — duplicate suppressed.']);
        exit;
    }
}

$written = file_put_contents($buffer_file, strtoupper($uid), LOCK_EX);
if ($written !== false) {
    echo json_encode(['status' => 'ok', 'message' => 'UID received.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to write RFID buffer.']);
}
?>
