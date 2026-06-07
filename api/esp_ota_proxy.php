<?php
/**
 * ESP OTA Proxy — Forwards firmware requests to the server
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/octet-stream');

$token = $_GET['token'] ?? '';

if (!hash_equals(HARDWARE_API_KEY, $token)) {
    http_response_code(401);
    die("Unauthorized");
}

$firmware_path = __DIR__ . '/../esp32_firmware/latest.bin';

if (file_exists($firmware_path)) {
    header('Content-Length: ' . filesize($firmware_path));
    readfile($firmware_path);
} else {
    http_response_code(404);
    die("Firmware not found");
}
