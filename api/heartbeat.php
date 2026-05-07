<?php
/**
 * API Heartbeat — Registers device IP + name in devices_registry.json
 * Called by ESP32 firmware every 10 seconds.
 * 
 * Usage: GET /api/heartbeat.php?device=esp32&name=DEVICE_NAME&token=SECRET&method=plaintext|hmac|aes
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$device_type = strtolower(trim($_GET['device'] ?? 'esp32'));
$device_name = trim($_GET['name'] ?? 'Unknown Node');
$token       = $_GET['token']  ?? '';
$method      = $_GET['method'] ?? 'plaintext';

// ─── Authentication ───────────────────────────────────────────────
$hw_key     = $_SERVER['HTTP_X_HARDWARE_KEY'] ?? ($_GET['hardware_key'] ?? '');
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$valid_token  = !empty($token)  && hash_equals(HEARTBEAT_SECRET, $token);
$valid_hwkey  = !empty($hw_key) && hash_equals(HARDWARE_API_KEY, $hw_key);
$legacy_esp   = empty($token)   && empty($hw_key) && strpos($user_agent, 'ESP32HTTPClient') !== false;

if (!$valid_token && !$valid_hwkey && !$legacy_esp) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: invalid or missing token.']);
    exit;
}

// ─── Register Device ──────────────────────────────────────────────
$ip   = $_SERVER['REMOTE_ADDR'];
$time = time();

$registry_file = __DIR__ . '/../devices_registry.json';
$registry = [];
if (file_exists($registry_file)) {
    $content = file_get_contents($registry_file);
    if ($content) $registry = json_decode($content, true) ?: [];
}

if (isset($registry[$ip]) && !empty($registry[$ip]['blocked'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Device is blocked.']);
    exit;
}

$registry[$ip] = [
    'type'      => $device_type,
    'name'      => $device_name,
    'last_seen' => $time,
    'method'    => $method,
    'blocked'   => $registry[$ip]['blocked'] ?? false,
];

file_put_contents($registry_file, json_encode($registry, JSON_PRETTY_PRINT), LOCK_EX);

// Legacy status files
if ($device_type === 'rpi' || $device_type === 'raspberry_pi') {
    file_put_contents(__DIR__ . '/../sys_status_rpi.txt', (string)$time, LOCK_EX);
} else {
    file_put_contents(__DIR__ . '/../sys_status_esp32.txt', (string)$time, LOCK_EX);
}

echo json_encode([
    'status' => 'ok',
    'device' => $device_type,
    'name'   => $device_name,
    'ip'     => $ip,
    'time'   => date('Y-m-d H:i:s', $time),
]);
?>
