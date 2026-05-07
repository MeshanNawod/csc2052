<?php
/**
 * API: Course Schedules — Sentinel Swarm AMS
 * CRUD for weekly recurring course schedules with auto-start and email alerts.
 */
require '../includes/auth.php';
require '../includes/db.php';

requireCsrfIfMutating();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

// ─── GET: List all schedules ────────────────────────────────────────
if ($method === 'GET' && ($action === 'list' || $action === '')) {
    try {
        // Teachers only see their own courses
        $where = '';
        $params = [];
        if (function_exists('isTeacher') && isTeacher()) {
            $teacherId = $_SESSION['teacher_id'] ?? 0;
            $where = "WHERE s.course_code IN (SELECT course_code FROM teacher_courses WHERE teacher_id = ?)";
            $params = [$teacherId];
        }
        $sql = "SELECT s.*, c.course_name FROM course_schedules s LEFT JOIN courses c ON s.course_code = c.course_code $where ORDER BY s.day_of_week ASC, s.start_time ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'schedules' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        error_log('[Schedule API] list: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── POST: Save or update schedule ──────────────────────────────────
if ($method === 'POST' && $action === 'save') {
    $id          = trim($_POST['id'] ?? '');
    $courseCode  = strtoupper(trim($_POST['course_code'] ?? ''));
    $dayOfWeek   = (int)($_POST['day_of_week'] ?? 1);
    $startTime   = trim($_POST['start_time'] ?? '');
    $endTime     = trim($_POST['end_time'] ?? '');
    $venue       = trim($_POST['venue'] ?? '');
    $deviceId    = trim($_POST['device_id'] ?? 'WEB_DASHBOARD');
    $autoStart   = isset($_POST['auto_start']) ? (int)$_POST['auto_start'] : 1;
    $emailThresh = (int)($_POST['email_threshold'] ?? 80);
    $emailOnEnd  = isset($_POST['email_on_end']) ? (int)$_POST['email_on_end'] : 1;

    if (!$courseCode || !preg_match('/^[A-Z0-9_-]{1,50}$/', $courseCode)) {
        http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Valid course code required.']); exit;
    }
    if ($dayOfWeek < 1 || $dayOfWeek > 6) {
        http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Day must be 1-6.']); exit;
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Times must be HH:MM format.']); exit;
    }
    if ($emailThresh < 0 || $emailThresh > 100) {
        http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Threshold must be 0-100.']); exit;
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE course_schedules
                SET course_code = ?, day_of_week = ?, start_time = ?, end_time = ?, venue = ?,
                    device_id = ?, auto_start = ?, email_threshold = ?, email_on_end = ?
                WHERE id = ?
            ");
            $stmt->execute([$courseCode, $dayOfWeek, $startTime, $endTime, $venue, $deviceId, $autoStart, $emailThresh, $emailOnEnd, $id]);
        } else {
            $conflict = $pdo->prepare("
                SELECT id FROM course_schedules
                WHERE day_of_week = ? AND device_id = ?
                  AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))
                LIMIT 1
            ");
            $conflict->execute([$dayOfWeek, $deviceId, $endTime, $startTime, $endTime, $startTime]);
            if ($conflict->fetch()) {
                echo json_encode(['status' => 'conflict', 'message' => 'Time slot conflicts with another schedule on this device.']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO course_schedules (course_code, day_of_week, start_time, end_time, venue, device_id, auto_start, email_threshold, email_on_end)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$courseCode, $dayOfWeek, $startTime, $endTime, $venue, $deviceId, $autoStart, $emailThresh, $emailOnEnd]);
        }
        echo json_encode(['status' => 'success', 'message' => 'Schedule saved.']);
    } catch (PDOException $e) {
        error_log('[Schedule API] save: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── POST: Delete schedule ──────────────────────────────────────────
if ($method === 'POST' && $action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing schedule ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM course_schedules WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Schedule deleted.']);
    } catch (PDOException $e) {
        error_log('[Schedule API] delete: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── GET: Check if there's a currently active lecture based on schedule ──
if ($method === 'GET' && $action === 'now') {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, c.course_name
            FROM course_schedules s
            LEFT JOIN courses c ON s.course_code = c.course_code
            WHERE s.day_of_week = DAYOFWEEK(CURDATE()) - 1
              AND CURTIME() BETWEEN s.start_time AND s.end_time
              AND s.auto_start = 1
            ORDER BY s.start_time ASC
        ");
        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($schedules)) {
            // Return all currently active schedules
            echo json_encode(['status' => 'success', 'active' => true, 'schedules' => $schedules]);
        } else {
            // Find next upcoming
            $next = $pdo->prepare("
                SELECT s.*, c.course_name
                FROM course_schedules s
                LEFT JOIN courses c ON s.course_code = c.course_code
                WHERE s.day_of_week = DAYOFWEEK(CURDATE()) - 1
                  AND s.start_time > CURTIME()
                  AND s.auto_start = 1
                ORDER BY s.start_time ASC
                LIMIT 1
            ");
            $next->execute();
            $nextSchedule = $next->fetch();
            echo json_encode([
                'status' => 'success',
                'active' => false,
                'next' => $nextSchedule
            ]);
        }
    } catch (PDOException $e) {
        error_log('[Schedule API] now: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── GET: Get schedules that should auto-start soon (within next N minutes) ──
if ($method === 'GET' && $action === 'auto_start_check') {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, c.course_name
            FROM course_schedules s
            LEFT JOIN courses c ON s.course_code = c.course_code
            WHERE s.day_of_week = DAYOFWEEK(CURDATE()) - 1
              AND s.auto_start = 1
              AND s.start_time BETWEEN CURTIME() AND DATE_ADD(CURTIME(), INTERVAL 5 MINUTE)
            ORDER BY s.start_time ASC
        ");
        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'schedules' => $schedules]);
    } catch (PDOException $e) {
        error_log('[Schedule API] auto_start_check: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── GET: Get schedules that just ended (for auto-email absent report) ──
if ($method === 'GET' && $action === 'ended_schedules') {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, c.course_name
            FROM course_schedules s
            LEFT JOIN courses c ON s.course_code = c.course_code
            WHERE s.day_of_week = DAYOFWEEK(CURDATE()) - 1
              AND s.email_on_end = 1
              AND s.end_time BETWEEN DATE_SUB(CURTIME(), INTERVAL 5 MINUTE) AND CURTIME()
            ORDER BY s.end_time DESC
        ");
        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'schedules' => $schedules]);
    } catch (PDOException $e) {
        error_log('[Schedule API] ended_schedules: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── POST: Update course-level settings (applies to all slots of a course) ──
if ($method === 'POST' && $action === 'update_course_settings') {
    $courseCode = trim($_POST['course_code'] ?? '');
    
    if (!$courseCode) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Course code is required.']);
        exit;
    }

    try {
        $updates = [];
        $params  = [];

        if (isset($_POST['auto_start'])) {
            $updates[] = 'auto_start = ?';
            $params[] = (int)$_POST['auto_start'];
        }
        if (isset($_POST['email_threshold'])) {
            $updates[] = 'email_threshold = ?';
            $params[] = (int)$_POST['email_threshold'];
        }
        if (isset($_POST['email_on_end'])) {
            $updates[] = 'email_on_end = ?';
            $params[] = (int)$_POST['email_on_end'];
        }

        if (empty($updates)) {
            echo json_encode(['status' => 'error', 'message' => 'No settings to update.']);
            exit;
        }

        $query = "UPDATE course_schedules SET " . implode(', ', $updates) . " WHERE course_code = ?";
        $params[] = $courseCode;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        echo json_encode(['status' => 'success', 'message' => 'Course settings updated.']);
    } catch (PDOException $e) {
        error_log('[Schedule API] update_course_settings: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
