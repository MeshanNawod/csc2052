<?php
/**
 * QR Attendance API — Processes attendance via scanned QR codes
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$qr_data      = $_POST['qr_data']      ?? '';
$device_name  = $_POST['device_name']  ?? 'QR Scanner';
$course_code  = $_POST['course_code']  ?? '';

// ─── Authentication ───────────────────────────────────────────────
require_once __DIR__ . '/../includes/helpers.php';
if (!verifyHardwareSignature()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid signature']);
    exit;
}

if (!$qr_data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing QR data']);
    exit;
}

// Assuming QR data is the student number
$student_no = trim($qr_data);

try {
    // Check if student exists
    $stmt = $pdo->prepare("SELECT student_name FROM students WHERE student_no = ?");
    $stmt->execute([$student_no]);
    $student = $stmt->fetch();

    if (!$student) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Student not found']);
        exit;
    }

    // Check for duplicates today
    $dupStmt = $pdo->prepare("SELECT id FROM attendance_logs WHERE student_no = ? AND course_code = ? AND DATE(timestamp) = CURDATE() LIMIT 1");
    $dupStmt->execute([$student_no, $course_code]);
    if ($dupStmt->fetch()) {
        echo json_encode(['status' => 'duplicate', 'message' => 'Already recorded', 'student_no' => $student_no]);
        exit;
    }

    // Insert log
    $stmt = $pdo->prepare("INSERT INTO attendance_logs (student_no, course_code, device_name, modality) VALUES (?, ?, ?, 'qr_code')");
    $stmt->execute([$student_no, $course_code, $device_name]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'QR Attendance recorded',
        'student_no' => $student_no,
        'student_name' => $student['student_name']
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
