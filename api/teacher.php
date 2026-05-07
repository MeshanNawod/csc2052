<?php
/**
 * API: Teacher Management — Sentinel Swarm AMS v3
 * Handles teacher CRUD and course assignment.
 */
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

requireCsrfIfMutating();

header('Content-Type: application/json; charset=utf-8');

// ─── GET Actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // List all teachers with their courses (admin only)
    if ($action === 'list') {
        requireRole('admin');
        try {
            $stmt = $pdo->query(
                "SELECT t.id, t.teacher_name, t.department, t.phone, t.profile_photo, u.email, u.is_active, u.last_login
                 FROM teachers t
                 JOIN users u ON t.user_id = u.id
                 WHERE u.role = 'teacher'
                 ORDER BY t.teacher_name ASC"
            );
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt2 = $pdo->query(
                "SELECT tc.id as tc_id, tc.teacher_id, tc.course_code, c.course_name, t.teacher_name
                 FROM teacher_courses tc
                 JOIN teachers t ON tc.teacher_id = t.id
                 LEFT JOIN courses c ON tc.course_code = c.course_code
                 ORDER BY t.teacher_name, c.course_code"
            );
            $courseTeachers = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'teachers' => $teachers, 'course_teachers' => $courseTeachers]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
        exit;
    }

    // Get my profile (teacher self-service)
    if ($action === 'get_my_profile') {
        requireRole('teacher');
        $teacherId = $_SESSION['teacher_id'] ?? null;
        if (!$teacherId) { echo json_encode(['status' => 'error', 'message' => 'Not a teacher.']); exit; }
        try {
            $stmt = $pdo->prepare(
                "SELECT t.id, t.teacher_name, t.department, t.phone, t.profile_photo, u.email
                 FROM teachers t JOIN users u ON t.user_id = u.id
                 WHERE t.id = ?"
            );
            $stmt->execute([$teacherId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'profile' => $profile]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
        exit;
    }

    // Get my courses with schedules (teacher self-service)
    if ($action === 'get_my_courses') {
        requireRole('teacher');
        $teacherId = $_SESSION['teacher_id'] ?? null;
        if (!$teacherId) { echo json_encode(['status' => 'error', 'message' => 'Not a teacher.']); exit; }
        try {
            $stmt = $pdo->prepare(
                "SELECT c.course_code, c.course_name, c.description, c.credits, c.department
                 FROM teacher_courses tc
                 JOIN courses c ON tc.course_code = c.course_code
                 WHERE tc.teacher_id = ?
                 ORDER BY c.course_code ASC"
            );
            $stmt->execute([$teacherId]);
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $codes = array_column($courses, 'course_code');
            $schedules = [];
            if (!empty($codes)) {
                $placeholders = implode(',', array_fill(0, count($codes), '?'));
                $stmt2 = $pdo->prepare(
                    "SELECT course_code, day_of_week, start_time, end_time, venue, device_id, auto_start, email_threshold, email_on_end
                     FROM course_schedules WHERE course_code IN ($placeholders) ORDER BY day_of_week, start_time"
                );
                $stmt2->execute($codes);
                $schedules = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            }

            $enrolled = [];
            if (!empty($codes)) {
                $placeholders = implode(',', array_fill(0, count($codes), '?'));
                $stmt3 = $pdo->prepare(
                    "SELECT course_code, COUNT(DISTINCT student_no) as count
                     FROM student_courses WHERE course_code IN ($placeholders) GROUP BY course_code"
                );
                $stmt3->execute($codes);
                while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
                    $enrolled[$row['course_code']] = $row['count'];
                }
            }

            foreach ($courses as &$c) {
                $cc = $c['course_code'];
                $c['schedules'] = array_filter($schedules, fn($s) => $s['course_code'] === $cc);
                $c['enrolled'] = $enrolled[$cc] ?? 0;
            }

            echo json_encode(['status' => 'success', 'courses' => $courses]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
        exit;
    }

    // Get enrolled students for a course (teacher self-service)
    if ($action === 'get_course_students') {
        requireRole('teacher');
        $teacherId = $_SESSION['teacher_id'] ?? null;
        $courseCode = strtoupper(trim($_GET['course_code'] ?? ''));
        if (!$teacherId || empty($courseCode)) { echo json_encode(['status' => 'error', 'message' => 'Invalid request.']); exit; }
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
            $stmt->execute([$teacherId, $courseCode]);
            if (!$stmt->fetch()) { echo json_encode(['status' => 'error', 'message' => 'Access denied.']); exit; }

            $stmt2 = $pdo->prepare(
                "SELECT s.student_no, s.student_name
                 FROM student_courses sc
                 JOIN students s ON sc.student_no = s.student_no
                 WHERE sc.course_code = ?
                 ORDER BY s.student_no ASC"
            );
            $stmt2->execute([$courseCode]);
            echo json_encode(['status' => 'success', 'students' => $stmt2->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
        exit;
    }

    // Get today's attendance for a course
    if ($action === 'get_today_attendance') {
        requireRole('teacher');
        $teacherId = $_SESSION['teacher_id'] ?? null;
        $courseCode = strtoupper(trim($_GET['course_code'] ?? ''));
        if (!$teacherId || empty($courseCode)) { echo json_encode(['status' => 'error', 'message' => 'Invalid request.']); exit; }
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
            $stmt->execute([$teacherId, $courseCode]);
            if (!$stmt->fetch()) { echo json_encode(['status' => 'error', 'message' => 'Access denied.']); exit; }

            $today = date('Y-m-d');
            $stmt2 = $pdo->prepare(
                "SELECT DISTINCT al.student_no, s.student_name, al.timestamp
                 FROM attendance_logs al
                 JOIN students s ON al.student_no = s.student_no
                 WHERE al.course_code = ? AND DATE(al.timestamp) = ?
                 ORDER BY al.timestamp ASC"
            );
            $stmt2->execute([$courseCode, $today]);
            echo json_encode(['status' => 'success', 'present' => $stmt2->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
        exit;
    }

    // Get teachers for a specific course
    if ($action === 'get_course_teachers') {
        $code = strtoupper(trim($_GET['course_code'] ?? ''));
        if (empty($code)) {
            echo json_encode(['status' => 'error', 'message' => 'Course code required.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT tc.id as tc_id, t.id as teacher_id, t.teacher_name, t.department, u.email
                 FROM teacher_courses tc
                 JOIN teachers t ON tc.teacher_id = t.id
                 JOIN users u ON t.user_id = u.id
                 WHERE tc.course_code = ?
                 ORDER BY t.teacher_name ASC"
            );
            $stmt->execute([$code]);
            echo json_encode(['status' => 'success', 'teachers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
        exit;
    }

    // Get courses for a specific teacher
    if ($action === 'get_teacher_courses') {
        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Teacher ID required.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT tc.course_code, c.course_name
                 FROM teacher_courses tc
                 LEFT JOIN courses c ON tc.course_code = c.course_code
                 WHERE tc.teacher_id = ?
                 ORDER BY c.course_code ASC"
            );
            $stmt->execute([$teacherId]);
            echo json_encode(['status' => 'success', 'courses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
        exit;
    }

    // ─── Teacher Dashboard Endpoints ───────────────────────────────────

    // Today's attendance count for teacher's courses
    if ($action === 'today_count') {
        $teacherId = $_SESSION['teacher_id'] ?? null;
        if (!$teacherId) { echo json_encode(['count' => 0]); exit; }
        try {
            $stmt = $pdo->prepare("SELECT course_code FROM teacher_courses WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($courses)) { echo json_encode(['count' => 0]); exit; }
            $today = date('Y-m-d');
            $placeholders = implode(',', array_fill(0, count($courses), '?'));
            $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT student_no) as cnt FROM attendance_logs WHERE course_code IN ($placeholders) AND DATE(timestamp) = ?");
            $stmt2->execute(array_merge($courses, [$today]));
            echo json_encode(['count' => (int)$stmt2->fetchColumn()]);
        } catch (PDOException $e) { echo json_encode(['count' => 0]); }
        exit;
    }

    // Live attendance logs filtered by teacher's courses
    if ($action === 'teacher_logs') {
        $teacherId = $_SESSION['teacher_id'] ?? null;
        if (!$teacherId) { 
            error_log("[Teacher API] teacher_logs: No teacher_id in session");
            apiResponse('error', 'Not authenticated as teacher.', ['logs' => []]); 
            exit; 
        }
        try {
            $stmt = $pdo->prepare("SELECT course_code FROM teacher_courses WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($courses)) { 
                error_log("[Teacher API] teacher_logs: No courses for teacher $teacherId");
                apiResponse('success', 'No courses assigned.', ['logs' => []]); 
                exit; 
            }
            
            $where = "a.course_code IN (" . implode(',', array_fill(0, count($courses), '?')) . ")";
            $params = $courses;
            
            $student = trim($_GET['student'] ?? '');
            if ($student) { $where .= " AND (a.student_no LIKE ? OR s.student_name LIKE ?)"; $params[] = "%$student%"; $params[] = "%$student%"; }
            
            $course = trim($_GET['course'] ?? '');
            if ($course) { $where .= " AND a.course_code = ?"; $params[] = $course; }
            
            $date = trim($_GET['date'] ?? '');
            if ($date) { $where .= " AND DATE(a.timestamp) = ?"; $params[] = $date; }
            
            $dateFrom = $_GET['date_from'] ?? '';
            if ($dateFrom) { $where .= " AND DATE(a.timestamp) >= ?"; $params[] = $dateFrom; }
            
            $dateTo = $_GET['date_to'] ?? '';
            if ($dateTo) { $where .= " AND DATE(a.timestamp) <= ?"; $params[] = $dateTo; }
            
            $sql = "SELECT a.student_no, a.timestamp, a.course_code, a.modality, a.device_name, s.student_name
                    FROM attendance_logs a
                    LEFT JOIN students s ON a.student_no = s.student_no
                    WHERE $where ORDER BY a.timestamp DESC LIMIT 100";
            
            error_log("[Teacher API] teacher_logs SQL: $sql");
            error_log("[Teacher API] teacher_logs params: " . json_encode($params));
            
            $stmt2 = $pdo->prepare($sql);
            $stmt2->execute($params);
            $logs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("[Teacher API] teacher_logs: Found " . count($logs) . " logs");
            
            echo json_encode(['status' => 'success', 'message' => '', 'logs' => $logs]);
        } catch (PDOException $e) { 
            error_log("[Teacher API] teacher_logs error: " . $e->getMessage());
            apiError('Database error while fetching logs.', 500); 
        }
        exit;
    }

    // Absent students for a course on a given date
    if ($action === 'absent_students') {
        $teacherId = $_SESSION['teacher_id'] ?? null;
        $courseCode = strtoupper(trim($_GET['course_code'] ?? ''));
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!$teacherId || !$courseCode) { echo json_encode(['students' => []]); exit; }
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
            $stmt->execute([$teacherId, $courseCode]);
            if (!$stmt->fetch()) { echo json_encode(['students' => []]); exit; }

            $sql = "SELECT s.student_no, s.student_name
                     FROM student_courses sc
                     JOIN students s ON sc.student_no = s.student_no
                     WHERE sc.course_code = ?
                     AND s.student_no NOT IN (
                         SELECT student_no FROM attendance_logs
                         WHERE course_code = ? AND DATE(timestamp) = ?
                     )
                     ORDER BY s.student_no ASC";
            $stmt2 = $pdo->prepare($sql);
            $stmt2->execute([$courseCode, $courseCode, $date]);
            echo json_encode(['students' => $stmt2->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { echo json_encode(['students' => []]); }
        exit;
    }

    // Download CSV report
    if ($action === 'download_report') {
        $teacherId = $_SESSION['teacher_id'] ?? null;
        $courseCode = strtoupper(trim($_GET['course_code'] ?? ''));
        if (!$teacherId || !$courseCode) { header('HTTP/1.1 400'); exit; }
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
            $stmt->execute([$teacherId, $courseCode]);
            if (!$stmt->fetch()) { header('HTTP/1.1 403'); exit; }

            $where = "a.course_code = ?";
            $params = [$courseCode];

            $dateFrom = $_GET['date_from'] ?? '';
            if ($dateFrom) { $where .= " AND DATE(a.timestamp) >= ?"; $params[] = $dateFrom; }

            $dateTo = $_GET['date_to'] ?? '';
            if ($dateTo) { $where .= " AND DATE(a.timestamp) <= ?"; $params[] = $dateTo; }

            $sql = "SELECT a.student_no, s.student_name, a.timestamp, a.course_code, a.modality, a.device_id
                    FROM attendance_logs a LEFT JOIN students s ON a.student_no = s.student_no
                    WHERE $where ORDER BY a.timestamp ASC";
            $stmt2 = $pdo->prepare($sql);
            $stmt2->execute($params);
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=report_{$courseCode}_{$date}.csv");
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student No', 'Name', 'Timestamp', 'Course', 'Modality', 'Device']);
            foreach ($rows as $row) fputcsv($out, $row);
            fclose($out);
        } catch (PDOException $e) { header('HTTP/1.1 500'); }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
    exit;
}

// ─── POST Actions ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$action = $_POST['action'];

// ─── Teacher Self-Service: Start Course ─────────────────────────────
if ($action === 'start_course') {
    $teacherId = $_SESSION['teacher_id'] ?? null;
    $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));
    if (!$teacherId || !$courseCode) { echo json_encode(['status' => 'error', 'message' => 'Invalid request.']); exit; }
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
        $stmt->execute([$teacherId, $courseCode]);
        if (!$stmt->fetch()) { echo json_encode(['status' => 'error', 'message' => 'You do not teach this course.']); exit; }

        $now = date('Y-m-d H:i:s');
        $stmt2 = $pdo->prepare("INSERT INTO active_courses (course_code, teacher_id, started_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE started_at = ?");
        $stmt2->execute([$courseCode, $teacherId, $now, $now]);
        echo json_encode(['status' => 'success', 'message' => "Course $courseCode started."]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── Teacher Self-Service: Mark Attendance ──────────────────────────
if ($action === 'mark_attendance') {
    $teacherId = $_SESSION['teacher_id'] ?? null;
    $studentNo = strtoupper(trim($_POST['student_no'] ?? ''));
    if (!$teacherId || !$studentNo) { echo json_encode(['status' => 'error', 'message' => 'Student number required.']); exit; }
    try {
        $stmt = $pdo->prepare("SELECT student_no FROM students WHERE student_no = ?");
        $stmt->execute([$studentNo]);
        if (!$stmt->fetch()) { echo json_encode(['status' => 'error', 'message' => 'Student not found.']); exit; }

        $stmt2 = $pdo->prepare("SELECT id FROM attendance_logs WHERE student_no = ? AND DATE(timestamp) = CURDATE()");
        $stmt2->execute([$studentNo]);
        if ($stmt2->fetch()) { echo json_encode(['status' => 'error', 'message' => 'Already marked present today.']); exit; }

        $stmt3 = $pdo->prepare("INSERT INTO attendance_logs (student_no, device_id, timestamp, modality) VALUES (?, 'Teacher Portal', NOW(), 'manual')");
        $stmt3->execute([$studentNo]);
        echo json_encode(['status' => 'success', 'message' => 'Attendance marked.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── Teacher Self-Service: Send Absent Email ────────────────────────
if ($action === 'send_absent_email') {
    $teacherId = $_SESSION['teacher_id'] ?? null;
    $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    if (!$teacherId || !$courseCode) { echo json_encode(['status' => 'error', 'message' => 'Invalid request.']); exit; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { echo json_encode(['status' => 'error', 'message' => 'Invalid date format.']); exit; }
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
        $stmt->execute([$teacherId, $courseCode]);
        if (!$stmt->fetch()) { echo json_encode(['status' => 'error', 'message' => 'Access denied.']); exit; }

        $sql = "SELECT s.student_no, s.student_name
                 FROM student_courses sc
                 JOIN students s ON sc.student_no = s.student_no
                 WHERE sc.course_code = ?
                 AND s.student_no NOT IN (
                     SELECT student_no FROM attendance_logs WHERE course_code = ? AND DATE(timestamp) = ?
                 )";
        $stmt2 = $pdo->prepare($sql);
        $stmt2->execute([$courseCode, $courseCode, $date]);
        $absentStudents = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $studentEmails = [];
        foreach ($absentStudents as $s) {
            $no = str_replace('/', '', str_ireplace('S/', '', trim($s['student_no'])));
            $email = 's' . strtolower($no) . '@sci.pdn.ac.lk';
            $studentEmails[] = ['student_no' => $s['student_no'], 'name' => $s['student_name'], 'email' => $email];
        }

        echo json_encode(['status' => 'success', 'sent' => count($studentEmails), 'message' => count($studentEmails) . ' absent students found.', 'students' => $studentEmails]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── Assign Teacher to Course (Admin) ───────────────────────────────
if ($action === 'assign_teacher') {
    requireRole('admin');
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));

    if ($teacherId <= 0 || empty($courseCode)) {
        echo json_encode(['status' => 'error', 'message' => 'Teacher ID and course code required.']);
        exit;
    }

    try {
        // Verify teacher exists
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE id = ?");
        $stmt->execute([$teacherId]);
        if (!$stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Teacher not found.']);
            exit;
        }

        // Verify course exists
        $stmt = $pdo->prepare("SELECT course_code FROM courses WHERE course_code = ?");
        $stmt->execute([$courseCode]);
        if (!$stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Course not found.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO teacher_courses (teacher_id, course_code) VALUES (?, ?)");
        $stmt->execute([$teacherId, $courseCode]);
        echo json_encode(['status' => 'success', 'message' => 'Teacher assigned to course.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── Remove Teacher from Course ────────────────────────────────────────
if ($action === 'remove_teacher') {
    requireRole('admin');
    $tcId = (int)($_POST['tc_id'] ?? 0);

    if ($tcId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Assignment ID required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM teacher_courses WHERE id = ?");
        $stmt->execute([$tcId]);
        echo json_encode(['status' => 'success', 'message' => 'Teacher removed from course.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── Add Teacher ──────────────────────────────────────────────────────
if ($action === 'add_teacher') {
    requireRole('admin');
    $name = trim($_POST['teacher_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Name, email, and password required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, email, full_name) VALUES (?, ?, 'teacher', ?, ?)");
        $stmt->execute([$email, $hash, $email, $name]);
        $userId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO teachers (user_id, teacher_name, department) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $name, $department]);
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Teacher '{$name}' added.", 'teacher_id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        $msg = $e->getCode() == 23000 ? 'Email already exists.' : 'Database error.';
        echo json_encode(['status' => 'error', 'message' => $msg]);
    }
    exit;
}

// ─── Update Teacher ───────────────────────────────────────────────────
if ($action === 'update_teacher') {
    requireRole('admin');
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $name = trim($_POST['teacher_name'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if ($teacherId <= 0 || empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Teacher ID and name required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE teachers SET teacher_name = ?, department = ? WHERE id = ?");
        $stmt->execute([$name, $department, $teacherId]);
        echo json_encode(['status' => 'success', 'message' => 'Teacher updated.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── Reset Teacher Password ──────────────────────────────────────────
if ($action === 'reset_password') {
    requireRole('admin');
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $newPassword = trim($_POST['new_password'] ?? '');

    if ($teacherId <= 0 || empty($newPassword)) {
        echo json_encode(['status' => 'error', 'message' => 'Teacher ID and new password required.']);
        exit;
    }

    try {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = (SELECT user_id FROM teachers WHERE id = ?)");
        $stmt->execute([$hash, $teacherId]);
        echo json_encode(['status' => 'success', 'message' => 'Password updated.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── POST: Update My Profile (teacher self-service) ──────────────────
if ($action === 'update_my_profile') {
    requireRole('teacher');
    $teacherId = $_SESSION['teacher_id'] ?? null;
    if (!$teacherId) { echo json_encode(['status' => 'error', 'message' => 'Not a teacher.']); exit; }

    $name = trim($_POST['teacher_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');

    if (empty($name)) { echo json_encode(['status' => 'error', 'message' => 'Name required.']); exit; }

    try {
        $pdo->beginTransaction();

        $updates = [];
        $params = [];

        $updates[] = "teacher_name = ?";
        $params[] = $name;

        if (!empty($department)) { $updates[] = "department = ?"; $params[] = $department; }
        if ($phone !== '') { $updates[] = "phone = ?"; $params[] = $phone; }

        $params[] = $teacherId;
        $query = "UPDATE teachers SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        if (!empty($email)) {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = (SELECT user_id FROM teachers WHERE id = ?)");
            $stmt->execute([$email, $teacherId]);
        }

        if (!empty($newPassword)) {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = (SELECT user_id FROM teachers WHERE id = ?)");
            $stmt->execute([$hash, $teacherId]);
            $_SESSION['full_name'] = $name;
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Profile updated.']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── POST: Upload Profile Photo (teacher self-service) ──────────────
if ($action === 'upload_photo') {
    requireRole('teacher');
    $teacherId = $_SESSION['teacher_id'] ?? null;
    if (!$teacherId) { echo json_encode(['status' => 'error', 'message' => 'Not a teacher.']); exit; }

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded.']); exit;
    }

    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Use JPG, PNG, WebP, or GIF.']); exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'File too large (max 5MB).']); exit;
    }

    $uploadDir = __DIR__ . '/../uploads/teachers/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'teacher_' . $teacherId . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $stmt = $pdo->prepare("SELECT profile_photo FROM teachers WHERE id = ?");
        $stmt->execute([$teacherId]);
        $old = $stmt->fetchColumn();
        if ($old && file_exists($uploadDir . basename($old))) {
            unlink($uploadDir . basename($old));
        }

        $photoPath = 'uploads/teachers/' . $filename;
        $stmt = $pdo->prepare("UPDATE teachers SET profile_photo = ? WHERE id = ?");
        $stmt->execute([$photoPath, $teacherId]);
        echo json_encode(['status' => 'success', 'photo_url' => '/' . $photoPath, 'message' => 'Photo uploaded.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save file.']);
    }
    exit;
}

// ─── POST: Start Course / Mark Manual Attendance (teacher) ───────────
if ($action === 'start_course_session') {
    requireRole('teacher');
    $teacherId = $_SESSION['teacher_id'] ?? null;
    $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));
    $device = trim($_POST['device'] ?? 'WEB_DASHBOARD');
    $timerMinutes = (int)($_POST['timer_minutes'] ?? 0);

    if (!$teacherId || empty($courseCode)) {
        echo json_encode(['status' => 'error', 'message' => 'Course required.']); exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
        $stmt->execute([$teacherId, $courseCode]);
        if (!$stmt->fetch()) { echo json_encode(['status' => 'error', 'message' => 'You do not teach this course.']); exit; }

        $stmt = $pdo->prepare("INSERT INTO active_courses (course_code, teacher_id, started_at, device_name, timer_minutes) VALUES (?, ?, NOW(), ?, ?) ON DUPLICATE KEY UPDATE started_at = NOW(), device_name = ?, timer_minutes = ?");
        $stmt->execute([$courseCode, $teacherId, $device, $timerMinutes, $device, $timerMinutes]);
        echo json_encode(['status' => 'success', 'message' => "Course $courseCode started on $device."]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to start course.']);
    }
    exit;
}

// ─── POST: End Course Session (teacher) ─────────────────────────────
if ($action === 'end_course_session') {
    requireRole('teacher');
    $teacherId = $_SESSION['teacher_id'] ?? null;
    $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));

    if (!$teacherId || empty($courseCode)) {
        echo json_encode(['status' => 'error', 'message' => 'Course required.']); exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
        $stmt->execute([$teacherId, $courseCode]);
        if (!$stmt->fetch()) { echo json_encode(['status' => 'error', 'message' => 'You do not teach this course.']); exit; }

        $stmt = $pdo->prepare("DELETE FROM active_courses WHERE course_code = ? AND teacher_id = ?");
        $stmt->execute([$courseCode, $teacherId]);
        echo json_encode(['status' => 'success', 'message' => "Course $courseCode session ended."]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to end course session.']);
    }
    exit;
}

// ─── GET: Download Attendance Report (CSV) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'download_report') {
    requireRole('teacher');
    $teacherId = $_SESSION['teacher_id'] ?? null;
    $courseCode = strtoupper(trim($_GET['course_code'] ?? ''));
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');

    if (!$teacherId || empty($courseCode)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Course code required.']); exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
        $stmt->execute([$teacherId, $courseCode]);
        if (!$stmt->fetch()) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Access denied.']); exit;
        }

        $where = "al.course_code = ?";
        $params = [$courseCode];
        if (!empty($dateFrom)) { $where .= " AND DATE(al.timestamp) >= ?"; $params[] = $dateFrom; }
        if (!empty($dateTo)) { $where .= " AND DATE(al.timestamp) <= ?"; $params[] = $dateTo; }

        $stmt2 = $pdo->prepare(
            "SELECT al.student_no, s.student_name, al.course_code, al.timestamp, al.modality, al.device_name
             FROM attendance_logs al
             LEFT JOIN students s ON al.student_no = s.student_no
             WHERE $where
             ORDER BY al.timestamp DESC"
        );
        $stmt2->execute($params);
        $logs = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $courseCode . '_report_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Student No', 'Name', 'Course', 'Timestamp', 'Modality', 'Device']);
        foreach ($logs as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error.']); exit;
    }
}

// ─── Delete Teacher ──────────────────────────────────────────────────
if ($action === 'delete_teacher') {
    requireRole('admin');
    $teacherId = (int)($_POST['teacher_id'] ?? 0);

    if ($teacherId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Teacher ID required.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
        $stmt->execute([$teacherId]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("DELETE FROM teacher_courses WHERE teacher_id = ?")->execute([$teacherId]);
        $pdo->prepare("DELETE FROM teachers WHERE id = ?")->execute([$teacherId]);
        if ($t) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$t['user_id']]);
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Teacher deleted.']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
