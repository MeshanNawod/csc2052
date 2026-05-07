<?php
/**
 * API: Logs — Sentinel Swarm AMS
 * Returns filtered attendance log entries or distinct course codes.
 * Requires an active admin session.
 */
require '../includes/auth.php';
require '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only GET requests are served
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method or missing action.']);
    exit;
}

$action = $_GET['action'];

// ─── Action: Fetch Logs ───────────────────────────────────────────
if ($action === 'fetch_logs') {
    $search_student = trim($_GET['student_no']   ?? '');
    $course_code    = trim($_GET['course_code']  ?? '');
    $date_from      = trim($_GET['date_from']    ?? '');
    $date_to        = trim($_GET['date_to']      ?? '');

    $device         = trim($_GET['device']       ?? '');

    // Validate date inputs
    if (!empty($date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $date_from = '';
    }
    if (!empty($date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $date_to = '';
    }

    $query  = "SELECT l.id, l.student_no,
                      COALESCE(s.student_name, 'Unknown') AS student_name,
                      l.device_name, l.timestamp, l.course_code, l.modality, l.is_offline_sync
               FROM attendance_logs l
               LEFT JOIN students s ON l.student_no = s.student_no
               WHERE 1=1";
    $params = [];

    // Teachers only see logs for their courses
    if (function_exists('isTeacher') && isTeacher()) {
        $teacherId = $_SESSION['teacher_id'] ?? 0;
        $query .= " AND l.course_code IN (SELECT course_code FROM teacher_courses WHERE teacher_id = ?)";
        $params[] = $teacherId;
    }
    // Students only see their own logs
    if (function_exists('isStudent') && isStudent()) {
        $studentNo = $_SESSION['student_no'] ?? $_SESSION['username'] ?? '';
        $query .= " AND l.student_no = ?";
        $params[] = $studentNo;
    }

    if (!empty($search_student)) {
        $query   .= " AND (l.student_no LIKE ? OR s.student_name LIKE ?)";
        $params[] = '%' . $search_student . '%';
        $params[] = '%' . $search_student . '%';
    }
    if (!empty($course_code)) {
        $query   .= " AND l.course_code LIKE ?";
        $params[] = '%' . $course_code . '%';
    }
    if (!empty($device)) {
        $query   .= " AND l.device_name LIKE ?";
        $params[] = '%' . $device . '%';
    }
    if (!empty($date_from)) {
        $query   .= " AND DATE(l.timestamp) >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $query   .= " AND DATE(l.timestamp) <= ?";
        $params[] = $date_to;
    }

    $query .= " ORDER BY l.timestamp DESC LIMIT 500";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
    } catch (PDOException $e) {
        error_log('[Sentinel Swarm Logs API] fetch_logs: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve logs.']);
    }
    exit;
}

// ─── Action: Get Distinct Course Codes ───────────────────────────
if ($action === 'get_courses') {
    try {
        $stmt = $pdo->query(
            "SELECT DISTINCT course_code
             FROM attendance_logs
             WHERE course_code IS NOT NULL
               AND course_code != ''
               AND course_code != 'MANUAL_ENTRY'
             ORDER BY course_code ASC"
        );
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        error_log('[Sentinel Swarm Logs API] get_courses: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve course list.']);
    }
    exit;
}

// ─── Action: Get Today's Count ─────────────────────────────────────
if ($action === 'today_count') {
    $device = trim($_GET['device'] ?? '');
    $today  = date('Y-m-d');
    
    $query  = "SELECT COUNT(*) FROM attendance_logs WHERE DATE(timestamp) = ?";
    $params = [$today];
    
    if (!empty($device)) {
        // Use LIKE to match partial device names (ESP32, UoP_Scanner_1, etc.)
        if ($device === 'ESP32') {
            $query .= " AND (device_name = ? OR device_name LIKE ? OR device_name LIKE ?)";
            $params[] = 'ESP32';
            $params[] = '%ESP32%';
            $params[] = '%UoP_Scanner%';
        } else if ($device === 'WEB_DASHBOARD') {
            $query .= " AND device_name = ?";
            $params[] = 'WEB_DASHBOARD';
        } else {
            $query .= " AND device_name LIKE ?";
            $params[] = '%' . $device . '%';
        }
    }
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode(['count' => $stmt->fetchColumn() ?: 0]);
    } catch (PDOException $e) {
        error_log('[Sentinel Swarm Logs API] today_count: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve today\'s count.']);
    }
    exit;
}

// ─── Unknown Action ────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => "Unknown action: {$action}"]);
?>
