<?php
/**
 * Devices Registry API — UOP AMS
 * Manages discovered hardware nodes (ESP32s, RPi) and their block states.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireCsrfIfMutating();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$action = $_REQUEST['action'] ?? 'list';
$registry_file = __DIR__ . '/../devices_registry.json';

function loadRegistry($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    return $content ? (json_decode($content, true) ?: []) : [];
}

function saveRegistry($file, $registry) {
    file_put_contents($file, json_encode($registry, JSON_PRETTY_PRINT), LOCK_EX);
}

// ─── LIST: Return all devices with online status ─────────────────
if ($action === 'list') {
    $registry = loadRegistry($registry_file);
    $devices  = [];
    $now      = time();

    foreach ($registry as $ip => $info) {
        $online = ($now - (int)$info['last_seen']) <= 30; // 30s window
        $devices[] = [
            'ip'        => $ip,
            'name'      => $info['name'] ?? 'Unknown Node',
            'type'      => $info['type'] ?? 'esp32',
            'online'    => $online,
            'blocked'   => !empty($info['blocked']),
            'last_seen' => (int)$info['last_seen'],
        ];
    }

    // Sort: online first
    usort($devices, fn($a, $b) => $b['online'] <=> $a['online']);
    echo json_encode(['status' => 'ok', 'devices' => $devices]);
    exit;
}

// ─── BLOCK: Block a device IP ────────────────────────────────────
if ($action === 'block') {
    $ip = trim($_POST['ip'] ?? $_GET['ip'] ?? '');
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) { echo json_encode(['status' => 'error', 'message' => 'Valid IP required']); exit; }

    $registry = loadRegistry($registry_file);
    if (isset($registry[$ip])) {
        $registry[$ip]['blocked'] = true;
        saveRegistry($registry_file, $registry);
        echo json_encode(['status' => 'ok', 'message' => "Device $ip blocked."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Device not found.']);
    }
    exit;
}

// ─── UNBLOCK: Unblock a device IP ────────────────────────────────
if ($action === 'unblock') {
    $ip = trim($_POST['ip'] ?? $_GET['ip'] ?? '');
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) { echo json_encode(['status' => 'error', 'message' => 'Valid IP required']); exit; }

    $registry = loadRegistry($registry_file);
    if (isset($registry[$ip])) {
        $registry[$ip]['blocked'] = false;
        saveRegistry($registry_file, $registry);
        echo json_encode(['status' => 'ok', 'message' => "Device $ip unblocked."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Device not found.']);
    }
    exit;
}

// ─── RENAME: Give a device a friendly name ───────────────────────
if ($action === 'rename') {
    $ip   = trim($_POST['ip'] ?? '');
    $name = trim($_POST['name'] ?? '');
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP) || !$name) { echo json_encode(['status' => 'error', 'message' => 'Valid IP and name required']); exit; }

    $registry = loadRegistry($registry_file);
    if (isset($registry[$ip])) {
        $registry[$ip]['name'] = substr($name, 0, 40);
        saveRegistry($registry_file, $registry);
        echo json_encode(['status' => 'ok', 'message' => "Device renamed to $name."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Device not found.']);
    }
    exit;
}

// ─── FORGET: Remove device from registry ─────────────────────────
if ($action === 'forget') {
    $ip = trim($_POST['ip'] ?? $_GET['ip'] ?? '');
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) { echo json_encode(['status' => 'error', 'message' => 'Valid IP required']); exit; }
    $registry = loadRegistry($registry_file);
    if (isset($registry[$ip])) {
        unset($registry[$ip]);
        saveRegistry($registry_file, $registry);
        echo json_encode(['status' => 'ok', 'message' => "Device $ip removed."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Device not found.']);
    }
    exit;
}

// ─── REGISTER: Manually add a new device ─────────────────────────
if ($action === 'register') {
    $ip   = trim($_POST['ip'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? 'esp32');
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP) || !$name) { echo json_encode(['status' => 'error', 'message' => 'Valid IP and name required']); exit; }

    $registry = loadRegistry($registry_file);
    if (isset($registry[$ip])) {
        echo json_encode(['status' => 'error', 'message' => 'Device already exists. Use rename to change name.']);
        exit;
    }
    $registry[$ip] = [
        'name'      => substr($name, 0, 40),
        'type'      => in_array($type, ['esp32', 'rpi', 'other']) ? $type : 'esp32',
        'last_seen' => time(),
        'blocked'   => false,
    ];
    saveRegistry($registry_file, $registry);
    echo json_encode(['status' => 'ok', 'message' => "Device $name ($ip) registered."]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
