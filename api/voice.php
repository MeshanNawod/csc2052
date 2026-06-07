<?php
/**
 * Voice Attendance API — Processes attendance via voice module triggers
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$voice_id     = $_POST['voice_id']     ?? '';
$device_name  = $_POST['device_name']  ?? 'Voice Module';
$course_code  = $_POST['course_code']  ?? '';

// ─── Authentication ───────────────────────────────────────────────
require_once __DIR__ . '/../includes/helpers.php';
if (!verifyHardwareSignature()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid signature']);
    exit;
}

if (!$voice_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing voice ID']);
    exit;
}

try {
    // In this system, voice_id is usually mapped to a student number
    $stmt = $pdo->prepare("SELECT student_no, student_name FROM students WHERE student_no = ?");
    $stmt->execute([$voice_id]);
    $student = $stmt->fetch();

    if (!$student) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Voice ID not recognized']);
        exit;
    }

    $student_no = $student['student_no'];

    // Check for duplicates
    $dupStmt = $pdo->prepare("SELECT id FROM attendance_logs WHERE student_no = ? AND course_code = ? AND DATE(timestamp) = CURDATE() LIMIT 1");
    $dupStmt->execute([$student_no, $course_code]);
    if ($dupStmt->fetch()) {
        echo json_encode(['status' => 'duplicate', 'message' => 'Already recorded', 'student_no' => $student_no]);
        exit;
    }

    // Insert log
    $stmt = $pdo->prepare("INSERT INTO attendance_logs (student_no, course_code, device_name, modality) VALUES (?, ?, ?, 'voice')");
    $stmt->execute([$student_no, $course_code, $device_name]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'Voice attendance recorded',
        'student_no' => $student_no,
        'student_name' => $student['student_name']
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
