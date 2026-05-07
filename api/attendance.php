<?php
/**
 * Attendance API — Receives attendance records from ESP32 nodes
 * 
 * Usage: POST /api/attendance.php
 * Parameters: finger_id, device_name, course_code, timestamp, modality, method, token, offline_sync
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$method  = $_POST['method']  ?? $_GET['method'] ?? 'plaintext';
$token   = $_POST['token']   ?? $_GET['token']  ?? '';

// ─── Authentication ───────────────────────────────────────────────
$hw_key = $_SERVER['HTTP_X_HARDWARE_KEY'] ?? ($_POST['hardware_key'] ?? '');

if ($method !== 'plaintext') {
    if ($token === '' && $hw_key === '') {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: token required for ' . $method . ' mode.']);
        exit;
    }
    if ($token !== '' && !hash_equals(HEARTBEAT_SECRET, $token)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: invalid token.']);
        exit;
    }
    if ($hw_key !== '' && !hash_equals(HARDWARE_API_KEY, $hw_key)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: invalid hardware key.']);
        exit;
    }
}

// ─── Parameters ───────────────────────────────────────────────────
$finger_id   = trim($_POST['finger_id']   ?? '');
$device_name = trim($_POST['device_name'] ?? 'Unknown');
$course_code = trim($_POST['course_code'] ?? '');
$timestamp   = trim($_POST['timestamp']   ?? date('Y-m-d H:i:s'));
$modality    = trim($_POST['modality']    ?? 'fingerprint');
$offline     = !empty($_POST['offline_sync']);

if (!$finger_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing finger_id']);
    exit;
}

// ─── Register Device ──────────────────────────────────────────────
$ip = $_SERVER['REMOTE_ADDR'];
$registry_file = __DIR__ . '/../devices_registry.json';
$registry = file_exists($registry_file) ? json_decode(file_get_contents($registry_file), true) ?: [] : [];
$registry[$ip] = [
    'type'      => 'esp32',
    'name'      => $device_name,
    'last_seen' => time(),
    'method'    => $method,
    'blocked'   => $registry[$ip]['blocked'] ?? false,
];
file_put_contents($registry_file, json_encode($registry, JSON_PRETTY_PRINT), LOCK_EX);
file_put_contents(__DIR__ . '/../sys_status_esp32.txt', (string)time(), LOCK_EX);

// ─── Admin Override Detection ─────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
    $stmt->execute([$finger_id]);
    $user = $stmt->fetch();
    if ($user && $user['role'] === 'admin') {
        echo json_encode(['status' => 'admin', 'message' => 'Admin login detected']);
        exit;
    }
} catch (PDOException $e) {}

// ─── Record Attendance ────────────────────────────────────────────
try {
    // Check for duplicate: same student, same course, same day
    $dupStmt = $pdo->prepare("
        SELECT id FROM attendance_logs 
        WHERE student_no = ? AND course_code = ? AND DATE(timestamp) = CURDATE()
        LIMIT 1
    ");
    $dupStmt->execute([$finger_id, $course_code]);
    if ($dupStmt->fetch()) {
        http_response_code(200);
        echo json_encode([
            'status' => 'duplicate',
            'message' => 'Already recorded for today',
            'student_no' => $finger_id,
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO attendance_logs (student_no, course_code, timestamp, device_name, modality, is_offline_sync)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$finger_id, $course_code, $timestamp, $device_name, $modality, $offline ? 1 : 0]);
    
    $studentStmt = $pdo->prepare("SELECT student_name FROM students WHERE student_no = ?");
    $studentStmt->execute([$finger_id]);
    $student = $studentStmt->fetch();
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Attendance recorded',
        'student_no' => $finger_id,
        'student_name' => $student['student_name'] ?? 'Unknown',
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
