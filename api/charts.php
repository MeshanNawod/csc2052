<?php
/**
 * Charts API — Returns JSON data for attendance analytics
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$course_code = $_GET['course_code'] ?? '';

try {
    if ($course_code) {
        // Daily attendance for a specific course
        $stmt = $pdo->prepare("
            SELECT DATE(timestamp) as date, COUNT(*) as count
            FROM attendance_logs
            WHERE course_code = ?
            GROUP BY DATE(timestamp)
            ORDER BY DATE(timestamp) ASC
            LIMIT 30
        ");
        $stmt->execute([$course_code]);
    } else {
        // Overall daily attendance
        $stmt = $pdo->query("
            SELECT DATE(timestamp) as date, COUNT(*) as count
            FROM attendance_logs
            GROUP BY DATE(timestamp)
            ORDER BY DATE(timestamp) ASC
            LIMIT 30
        ");
    }

    $data = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
