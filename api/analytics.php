<?php
/**
 * API: Analytics — Sentinel Swarm AMS
 * Returns per-student attendance statistics for a given course.
 * Requires an active admin session.
 */
require '../includes/auth.php';
require '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or missing action.']);
    exit;
}

// ─── Action: Course Statistics ────────────────────────────────────
if ($_GET['action'] === 'course_stats') {
    $course = trim($_GET['course_code'] ?? '');

    if (empty($course)) {
        echo json_encode(['total' => 0, 'students' => [], 'message' => 'Course code is required.']);
        exit;
    }

    // Sanitize: course codes should be alphanumeric
    if (!preg_match('/^[a-zA-Z0-9_\- ]{1,30}$/', $course)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid course code format.']);
        exit;
    }

    // Teachers can only access their own courses
    if (function_exists('isTeacher') && isTeacher()) {
        $teacherId = $_SESSION['teacher_id'] ?? 0;
        $stmtCheck = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
        $stmtCheck->execute([$teacherId, $course]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied. Not assigned to this course.']);
            exit;
        }
    }

    // Students can only access their own stats
    if (function_exists('isStudent') && isStudent()) {
        $studentNo = $_SESSION['student_no'] ?? $_SESSION['username'] ?? '';
        $stmtCheck = $pdo->prepare("SELECT 1 FROM student_courses WHERE student_no = ? AND course_code = ?");
        $stmtCheck->execute([$studentNo, $course]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied. Not enrolled in this course.']);
            exit;
        }
    }

    try {
        // Count distinct lecture days (sessions) for this course
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT DATE(timestamp))
             FROM attendance_logs
             WHERE course_code = ?"
        );
        $stmt->execute([$course]);
        $total_sessions = (int)$stmt->fetchColumn();

        if ($total_sessions === 0) {
            echo json_encode(['total' => 0, 'students' => []]);
            exit;
        }

        // Get all students who attended, with their attendance count
        $stmt2 = $pdo->prepare(
            "SELECT a.student_no,
                    COALESCE(s.student_name, '-') AS student_name,
                    COUNT(DISTINCT DATE(a.timestamp)) AS attended
             FROM attendance_logs a
             LEFT JOIN students s ON a.student_no = s.student_no
             WHERE a.course_code = ?
             GROUP BY a.student_no, s.student_name
             ORDER BY a.student_no ASC"
        );
        $stmt2->execute([$course]);

        $results = [];
        while ($row = $stmt2->fetch()) {
            $attended = (int)$row['attended'];
            $pct      = $total_sessions > 0 ? round(($attended / $total_sessions) * 100, 1) : 0.0;
            $results[] = [
                'student_no'   => $row['student_no'],
                'student_name' => $row['student_name'],
                'attended'     => $attended,
                'total'        => $total_sessions,
                'percentage'   => $pct,
            ];
        }

        echo json_encode(['total' => $total_sessions, 'students' => $results]);

    } catch (PDOException $e) {
        error_log('[Sentinel Swarm Analytics] course_stats: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to calculate analytics.']);
    }
    exit;
}

// ─── Unknown Action ────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
?>
