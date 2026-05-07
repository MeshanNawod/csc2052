<?php
/**
 * API: Student Management — Sentinel Swarm AMS
 * 
 * Handles all student and admin enrollment, biometric linking,
 * manual attendance, CSV bulk upload, and student deletion.
 * 
 * Authentication:
 *  - Web admin sessions (auth.php) for all dashboard-originated calls.
 *  - Hardware API key (X-Hardware-Key header) for hardware-originated calls.
 */
require_once '../includes/config.php';

// ─── Authentication ───────────────────────────────────────────────
$hw_key = $_SERVER['HTTP_X_HARDWARE_KEY'] ?? ($_POST['hardware_key'] ?? ($_GET['hardware_key'] ?? ''));
$is_hardware_call = !empty($hw_key) && hash_equals(HARDWARE_API_KEY, $hw_key);

if (!$is_hardware_call) {
    require '../includes/auth.php';
    requireCsrfIfMutating();
}

require '../includes/db.php';

// ─── Utility: Safe JSON response ─────────────────────────────────
function respond(string $status, string $message, int $http_code = 200): void {
    http_response_code($http_code);
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// ─── GET: Next Available Fingerprint Slot ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_next_slot') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $stmt    = $pdo->query("SELECT fingerprint_id FROM students WHERE fingerprint_id IS NOT NULL
                                UNION
                                SELECT fingerprint_id FROM admins WHERE fingerprint_id IS NOT NULL");
        $used_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $next_id  = 1;
        for ($i = 1; $i <= 127; $i++) {
            if (!in_array($i, $used_ids, true)) { $next_id = $i; break; }
        }
        echo json_encode(['next_slot' => $next_id]);
    } catch (PDOException $e) {
        error_log('[Student API] get_next_slot: ' . $e->getMessage());
        respond('error', 'Failed to determine next slot.', 500);
    }
    exit;
}

// ─── GET: All Courses ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_all_courses') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        // Teachers only see their assigned courses
        if (function_exists('isTeacher') && isTeacher()) {
            $teacherId = $_SESSION['teacher_id'] ?? 0;
            $stmt = $pdo->prepare(
                "SELECT c.course_code, c.course_name FROM courses c
                 JOIN teacher_courses tc ON c.course_code = tc.course_code
                 WHERE tc.teacher_id = ?
                 ORDER BY c.course_code ASC"
            );
            $stmt->execute([$teacherId]);
        } else {
            $stmt = $pdo->query("SELECT course_code, course_name FROM courses ORDER BY course_code ASC");
        }
        echo json_encode(['status' => 'success', 'courses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        error_log('[Student API] get_all_courses: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── GET: All Students (with optional course filter & search) ──────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_all_students') {
    header('Content-Type: application/json; charset=utf-8');
    $courseCode = strtoupper(trim($_GET['course_code'] ?? ''));
    $search = trim($_GET['search'] ?? '');

    try {
        $where = '1=1';
        $params = [];

        if (!empty($search)) {
            $where .= " AND (s.student_no LIKE ? OR s.student_name LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        if (!empty($courseCode)) {
            // Return students NOT yet enrolled in this course
            $where .= " AND s.student_no NOT IN (SELECT student_no FROM student_courses WHERE course_code = ?)";
            $params[] = $courseCode;
        }

        $stmt = $pdo->prepare("SELECT student_no, student_name FROM students WHERE $where ORDER BY student_no ASC LIMIT 100");
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'students' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        error_log('[Student API] get_all_students: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── GET: Email Config ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_email_config') {
    header('Content-Type: application/json; charset=utf-8');
    $configFile = __DIR__ . '/../includes/email_config.php';
    if (file_exists($configFile)) {
        require_once $configFile;
        echo json_encode([
            'status' => 'success',
            'email' => defined('USER_EMAIL') ? USER_EMAIL : '',
            'smtp_host' => defined('USER_SMTP_HOST') ? USER_SMTP_HOST : 'smtp.gmail.com',
            'has_password' => true
        ]);
    } else {
        echo json_encode(['status' => 'success', 'email' => '', 'smtp_host' => 'smtp.gmail.com', 'has_password' => false]);
    }
    exit;
}

// ─── GET: Absent Students ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_absent_students') {
    header('Content-Type: application/json; charset=utf-8');
    $code = strtoupper(trim($_GET['course_code'] ?? ''));
    $date = trim($_GET['date'] ?? date('Y-m-d'));

    if (empty($code)) {
        echo json_encode(['status' => 'error', 'message' => 'Course code is required.']);
        exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid date format.']);
        exit;
    }

    // Teachers can only check their own courses
    if (function_exists('isTeacher') && isTeacher()) {
        $teacherId = $_SESSION['teacher_id'] ?? 0;
        $stmtCheck = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
        $stmtCheck->execute([$teacherId, $code]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied. Not assigned to this course.']);
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT s.student_no, s.student_name, sc.course_code
             FROM student_courses sc
             JOIN students s ON sc.student_no = s.student_no
             WHERE sc.course_code = ?
               AND s.student_no NOT IN (
                   SELECT student_no FROM attendance_logs
                   WHERE course_code = ? AND DATE(timestamp) = ?
               )
             ORDER BY s.student_no ASC"
        );
        $stmt->execute([$code, $code, $date]);
        $absent = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE course_code = ? AND DATE(timestamp) = ?");
        $stmt2->execute([$code, $date]);
        $presentCount = (int)$stmt2->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'course_code' => $code,
            'date' => $date,
            'absent_count' => count($absent),
            'present_count' => $presentCount,
            'absent_students' => $absent,
        ]);
    } catch (PDOException $e) {
        error_log('[Student API] get_absent_students: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── GET: Email Logs ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_email_logs') {
    header('Content-Type: application/json; charset=utf-8');
    $limit = (int)($_GET['limit'] ?? 50);
    $course = trim($_GET['course_code'] ?? '');
    $type = trim($_GET['message_type'] ?? '');

    try {
        $query = "SELECT * FROM email_sent_logs WHERE 1=1";
        $params = [];
        if ($course) { $query .= " AND course_code = ?"; $params[] = $course; }
        if ($type) { $query .= " AND message_type = ?"; $params[] = $type; }
        $query .= " ORDER BY sent_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        error_log('[Student API] get_email_logs: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$rawAction = $_REQUEST['action'] ?? '';
$action = preg_replace('/:\d+$/','',$rawAction);

// ─── Helpers ──────────────────────────────────────────────────────
function sanitizeStudentNo(string $val): string {
    return substr(trim($val), 0, 50);
}
function sanitizeName(string $val): string {
    return substr(trim($val), 0, 100);
}
function validateFingerId(int $id): bool {
    return $id >= 1 && $id <= 127;
}
function sanitizeRfid(string $val): string {
    return substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($val)), 0, 50);
}
function sanitizeFaceId(string $val): string {
    return substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($val)), 0, 100);
}

// ─── link_student ─────────────────────────────────────────────────
if ($action === 'link_student') {
    $stu_no   = sanitizeStudentNo($_POST['student_no'] ?? '');
    $f_id     = (int)($_POST['finger_id'] ?? 0);
    $stu_name = sanitizeName($_POST['student_name'] ?? '');

    if (empty($stu_no) || !validateFingerId($f_id)) {
        respond('error', 'Invalid student number or fingerprint slot ID (must be 1–127).', 422);
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO students (student_no, fingerprint_id, student_name)
             VALUES (?, ?, NULLIF(?, ''))
             ON DUPLICATE KEY UPDATE fingerprint_id = ?, student_name = COALESCE(NULLIF(?, ''), student_name)"
        );
        $stmt->execute([$stu_no, $f_id, $stu_name, $f_id, $stu_name]);
        respond('success', "Student {$stu_no} linked to slot {$f_id}.");
    } catch (PDOException $e) {
        error_log('[Student API] link_student: ' . $e->getMessage());
        respond('error', 'Database error while linking student.', 500);
    }
}

// ─── link_rfid ────────────────────────────────────────────────────
if ($action === 'link_rfid') {
    $stu_no   = sanitizeStudentNo($_POST['student_no'] ?? '');
    $rfid     = sanitizeRfid($_POST['rfid_uid'] ?? '');
    $stu_name = sanitizeName($_POST['student_name'] ?? '');

    if (empty($stu_no) || empty($rfid)) {
        respond('error', 'Student number and RFID UID are required.', 422);
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO students (student_no, rfid_uid, student_name)
             VALUES (?, ?, NULLIF(?, ''))
             ON DUPLICATE KEY UPDATE rfid_uid = ?, student_name = COALESCE(NULLIF(?, ''), student_name)"
        );
        $stmt->execute([$stu_no, $rfid, $stu_name, $rfid, $stu_name]);
        respond('success', "RFID {$rfid} linked to {$stu_no}.");
    } catch (PDOException $e) {
        error_log('[Student API] link_rfid: ' . $e->getMessage());
        respond('error', 'Database error while linking RFID.', 500);
    }
}

// ─── link_face ────────────────────────────────────────────────────
if ($action === 'link_face') {
    $stu_no   = sanitizeStudentNo($_POST['student_no'] ?? '');
    $face_id  = sanitizeFaceId($_POST['face_id'] ?? '');
    $stu_name = sanitizeName($_POST['student_name'] ?? '');

    if (empty($stu_no) || empty($face_id)) {
        respond('error', 'Student number and Face ID are required.', 422);
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO students (student_no, face_id, student_name)
             VALUES (?, ?, NULLIF(?, ''))
             ON DUPLICATE KEY UPDATE face_id = ?, student_name = COALESCE(NULLIF(?, ''), student_name)"
        );
        $stmt->execute([$stu_no, $face_id, $stu_name, $face_id, $stu_name]);
        respond('success', "Face ID {$face_id} linked to {$stu_no}.");
    } catch (PDOException $e) {
        error_log('[Student API] link_face: ' . $e->getMessage());
        respond('error', 'Database error while linking face ID.', 500);
    }
}

// ─── link_admin ───────────────────────────────────────────────────
if ($action === 'link_admin') {
    $admin_name = sanitizeName($_POST['admin_name'] ?? '');
    $f_id       = (int)($_POST['finger_id'] ?? 0);

    if (empty($admin_name) || !validateFingerId($f_id)) {
        respond('error', 'Admin name and valid slot ID (1–127) are required.', 422);
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO admins (admin_name, fingerprint_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE fingerprint_id = ?"
        );
        $stmt->execute([$admin_name, $f_id, $f_id]);
        respond('success', "Admin '{$admin_name}' linked to fingerprint slot {$f_id}.");
    } catch (PDOException $e) {
        error_log('[Student API] link_admin: ' . $e->getMessage());
        respond('error', 'Database error while linking admin fingerprint.', 500);
    }
}

// ─── link_admin_rfid ─────────────────────────────────────────────
if ($action === 'link_admin_rfid') {
    $admin_name = sanitizeName($_POST['admin_name'] ?? '');
    $rfid       = sanitizeRfid($_POST['rfid_uid'] ?? '');

    if (empty($admin_name) || empty($rfid)) {
        respond('error', 'Admin name and RFID UID are required.', 422);
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO admins (admin_name, rfid_uid) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE rfid_uid = ?"
        );
        $stmt->execute([$admin_name, $rfid, $rfid]);
        respond('success', "RFID {$rfid} linked to admin '{$admin_name}'.");
    } catch (PDOException $e) {
        error_log('[Student API] link_admin_rfid: ' . $e->getMessage());
        respond('error', 'Database error while linking admin RFID.', 500);
    }
}

// ─── link_admin_face ─────────────────────────────────────────────
if ($action === 'link_admin_face') {
    $admin_name = sanitizeName($_POST['admin_name'] ?? '');
    $face_id    = sanitizeFaceId($_POST['face_id'] ?? '');

    if (empty($admin_name) || empty($face_id)) {
        respond('error', 'Admin name and Face ID are required.', 422);
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO admins (admin_name, face_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE face_id = ?"
        );
        $stmt->execute([$admin_name, $face_id, $face_id]);
        respond('success', "Face ID {$face_id} linked to admin '{$admin_name}'.");
    } catch (PDOException $e) {
        error_log('[Student API] link_admin_face: ' . $e->getMessage());
        respond('error', 'Database error while linking admin face ID.', 500);
    }
}

// ─── link_admin_web_face ─────────────────────────────────────────
if ($action === 'link_admin_web_face') {
    $admin_name = sanitizeName($_POST['admin_name'] ?? '');
    $descriptor = $_POST['descriptor'] ?? '';

    if (empty($admin_name) || empty($descriptor)) {
        respond('error', 'Admin name and face descriptor are required.', 422);
    }

    $new_descriptor = json_decode($descriptor, true);
    if (!is_array($new_descriptor) || count($new_descriptor) === 0) {
        respond('error', 'Invalid face descriptor format.', 422);
    }

    $incoming_descriptors = [];
    if (is_array($new_descriptor[0])) {
        foreach ($new_descriptor as $desc) {
            if (is_array($desc) && count($desc) === 128) {
                $incoming_descriptors[] = $desc;
            }
        }
    } elseif (count($new_descriptor) === 128) {
        $incoming_descriptors[] = $new_descriptor;
    }

    if (empty($incoming_descriptors)) {
        respond('error', 'No valid 128-d descriptors found.', 422);
    }

    try {
        $stmtCheck = $pdo->prepare("SELECT face_descriptor FROM admins WHERE admin_name = ?");
        $stmtCheck->execute([$admin_name]);
        $res = $stmtCheck->fetch();

        $final_descriptors = [];

        if ($res && !empty($res['face_descriptor'])) {
            $existing = json_decode($res['face_descriptor'], true);
            if (is_array($existing) && count($existing) > 0) {
                if (is_array($existing[0])) {
                    $final_descriptors = $existing;
                } elseif (count($existing) === 128) {
                    $final_descriptors[] = $existing;
                }
            }
        }
        
        foreach ($incoming_descriptors as $inc_desc) {
            $final_descriptors[] = $inc_desc;
        }
        
        $final_json = json_encode($final_descriptors);

        if ($res) {
            $stmt = $pdo->prepare("UPDATE admins SET face_descriptor = ? WHERE admin_name = ?");
            $stmt->execute([$final_json, $admin_name]);
            respond('success', "Admin web face model added to '{$admin_name}' (Total angles: " . count($final_descriptors) . ").");
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO admins (admin_name, face_descriptor) VALUES (?, ?)");
            $stmtInsert->execute([$admin_name, $final_json]);
            respond('success', "New admin created and web face model linked to '{$admin_name}'.");
        }
    } catch (PDOException $e) {
        error_log('[Student API] link_admin_web_face: ' . $e->getMessage());
        respond('error', 'Database error while saving admin web face descriptor.', 500);
    }
}

// ─── add_name ─────────────────────────────────────────────────────
if ($action === 'add_name') {
    $stu_no = sanitizeStudentNo($_POST['student_no'] ?? '');
    $name   = sanitizeName($_POST['student_name'] ?? '');

    if (empty($stu_no) || empty($name)) {
        respond('error', 'Student number and name are both required.', 422);
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO students (student_no, student_name) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE student_name = ?"
        );
        $stmt->execute([$stu_no, $name, $name]);
        respond('success', "Name '{$name}' saved for {$stu_no}.");
    } catch (PDOException $e) {
        error_log('[Student API] add_name: ' . $e->getMessage());
        respond('error', 'Database error while saving name.', 500);
    }
}

// ─── update_student ───────────────────────────────────────────────
if ($action === 'update_student') {
    $stu_no   = sanitizeStudentNo($_POST['student_no'] ?? '');
    $name     = sanitizeName($_POST['student_name'] ?? '');
    $f_id     = isset($_POST['fingerprint_id']) ? (int)$_POST['fingerprint_id'] : null;
    $rfid     = isset($_POST['rfid_uid']) ? sanitizeRfid($_POST['rfid_uid']) : null;
    $face_id  = isset($_POST['face_id']) ? sanitizeFaceId($_POST['face_id']) : null;

    if (empty($stu_no)) {
        respond('error', 'Student number is required.', 422);
    }

    try {
        // Check if student exists
        $checkStmt = $pdo->prepare("SELECT student_no FROM students WHERE student_no = ?");
        $checkStmt->execute([$stu_no]);
        if ($checkStmt->rowCount() === 0) {
            respond('error', "Student {$stu_no} not found.", 404);
        }

        // Validate fingerprint_id range if provided
        if ($f_id !== null && ($f_id < 1 || $f_id > 127)) {
            respond('error', 'Fingerprint ID must be between 1 and 127.', 422);
        }

        // Build dynamic update query
        $updates = [];
        $params  = [];

        if ($name !== '')       { $updates[] = "student_name = ?"; $params[] = $name; }
        if ($f_id !== null)     { $updates[] = "fingerprint_id = ?"; $params[] = $f_id; }
        if ($rfid !== null)     { $updates[] = "rfid_uid = ?"; $params[] = $rfid; }
        if ($face_id !== null)  { $updates[] = "face_id = ?"; $params[] = $face_id; }

        if (empty($updates)) {
            respond('error', 'No fields to update.', 400);
        }

        $query = "UPDATE students SET " . implode(', ', $updates) . " WHERE student_no = ?";
        $params[] = $stu_no;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            respond('success', "Student {$stu_no} updated successfully.");
        } else {
            respond('success', "No changes needed for {$stu_no}.");
        }
    } catch (PDOException $e) {
        error_log('[Student API] update_student: ' . $e->getMessage());
        respond('error', 'Database error while updating student: ' . $e->getMessage(), 500);
    }
}

// ─── upload_csv ───────────────────────────────────────────────────
if ($action === 'upload_csv') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $upload_err = $_FILES['csv_file']['error'] ?? 'no file';
        respond('error', "File upload error (code: {$upload_err}). Please try again.", 400);
    }

    // Validate file type
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['csv_file']['tmp_name']);
    finfo_close($finfo);
    $allowed_mimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
    if (!in_array($mimeType, $allowed_mimes, true)) {
        respond('error', "Invalid file type ({$mimeType}). Please upload a .csv file.", 422);
    }

    // Size limit: 2MB
    if ($_FILES['csv_file']['size'] > 2 * 1024 * 1024) {
        respond('error', 'File too large. Maximum allowed size is 2MB.', 413);
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');
    if ($handle === false) {
        respond('error', 'Could not open uploaded file.', 500);
    }

    $count  = 0;
    $errors = 0;
    $line   = 0;

    try {
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $line++;
            if (count($data) < 2) continue;
            $stu_no = sanitizeStudentNo($data[0]);
            $name   = sanitizeName($data[1]);
            // Skip header row
            if ($line === 1 && (strtolower($stu_no) === 'student_no' || strtolower($stu_no) === 'studentno')) continue;
            if (empty($stu_no) || empty($name)) continue;

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO students (student_no, student_name) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE student_name = ?"
                );
                $stmt->execute([$stu_no, $name, $name]);
                $count++;
            } catch (PDOException $e) {
                $errors++;
                error_log("[Student API] upload_csv row {$line}: " . $e->getMessage());
            }
        }
    } finally {
        fclose($handle);
    }

    if ($errors > 0) {
        respond('success', "Processed {$count} students. {$errors} row(s) had errors and were skipped.");
    }
    respond('success', "Successfully imported {$count} student profile(s).");
}

// ─── delete_student ───────────────────────────────────────────────
if ($action === 'delete_student') {
    $stu_no = sanitizeStudentNo($_POST['student_no'] ?? '');
    if (empty($stu_no)) {
        respond('error', 'Student number is required.', 422);
    }
    try {
        $pdo->beginTransaction();
        
        // Delete related attendance logs first (avoid FK constraint errors)
        $pdo->prepare("DELETE FROM attendance_logs WHERE student_no = ?")->execute([$stu_no]);
        
        // Delete pending multi-factor entries
        try {
            $pdo->prepare("DELETE FROM pending_multi_factor WHERE student_no = ?")->execute([$stu_no]);
        } catch (PDOException $e) { /* Table might not exist */ }
        
        // Delete the student
        $stmt = $pdo->prepare("DELETE FROM students WHERE student_no = ?");
        $stmt->execute([$stu_no]);
        $deleted = $stmt->rowCount();
        
        $pdo->commit();
        
        if ($deleted > 0) {
            respond('success', "Student {$stu_no} and related records deleted.");
        } else {
            $pdo->rollBack();
            respond('error', "Student {$stu_no} not found.", 404);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[Student API] delete_student: ' . $e->getMessage());
        respond('error', 'Database error: ' . $e->getMessage(), 500);
    }
}

// ─── bulk_delete_students ─────────────────────────────────────────
if ($action === 'bulk_delete_students') {
    try {
        $pdo->beginTransaction();
        
        // Delete all attendance logs first
        $pdo->exec("DELETE FROM attendance_logs");
        
        // Delete pending multi-factor entries
        try {
            $pdo->exec("DELETE FROM pending_multi_factor");
        } catch (PDOException $e) { /* Table might not exist */ }
        
        // Delete all students
        $count = $pdo->exec("DELETE FROM students");
        
        $pdo->commit();
        respond('success', "All {$count} student records and related data wiped.");
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[Student API] bulk_delete: ' . $e->getMessage());
        respond('error', 'Database error during bulk delete: ' . $e->getMessage(), 500);
    }
}

// ─── link_web_face ──────────────────────────────────────────────────
if ($action === 'link_web_face') {
    $stu_no = sanitizeStudentNo($_POST['student_no'] ?? '');
    $descriptor = $_POST['descriptor'] ?? ''; // This is a JSON string of the float array

    if (empty($stu_no) || empty($descriptor)) {
        respond('error', 'Student number and face descriptor are required.', 422);
    }

    // Basic validation: Check if it's a valid JSON array
    $new_descriptor = json_decode($descriptor, true);
    if (!is_array($new_descriptor) || count($new_descriptor) === 0) {
        respond('error', 'Invalid face descriptor format.', 422);
    }

    $incoming_descriptors = [];
    if (is_array($new_descriptor[0])) {
        // Array of descriptors
        foreach ($new_descriptor as $desc) {
            if (is_array($desc) && count($desc) === 128) {
                $incoming_descriptors[] = $desc;
            }
        }
    } elseif (count($new_descriptor) === 128) {
        // Single descriptor
        $incoming_descriptors[] = $new_descriptor;
    }

    if (empty($incoming_descriptors)) {
        respond('error', 'No valid 128-d descriptors found.', 422);
    }

    try {
        // Fetch existing
        $stmtCheck = $pdo->prepare("SELECT face_descriptor FROM students WHERE student_no = ?");
        $stmtCheck->execute([$stu_no]);
        $res = $stmtCheck->fetch();

        $final_descriptors = [];

        if ($res && !empty($res['face_descriptor'])) {
            $existing = json_decode($res['face_descriptor'], true);
            if (is_array($existing) && count($existing) > 0) {
                if (is_array($existing[0])) {
                    $final_descriptors = $existing;
                } elseif (count($existing) === 128) {
                    $final_descriptors[] = $existing;
                }
            }
        }
        
        foreach ($incoming_descriptors as $inc_desc) {
            $final_descriptors[] = $inc_desc;
        }
        
        $final_json = json_encode($final_descriptors);

        if ($res) {
            $stmt = $pdo->prepare("UPDATE students SET face_descriptor = ? WHERE student_no = ?");
            $stmt->execute([$final_json, $stu_no]);
            respond('success', "Web face model added to {$stu_no} (Total angles: " . count($final_descriptors) . ").");
        } else {
            // Student does not exist yet. Since fingerprint_id is now NULLable, we can safely create them!
            $stu_name = sanitizeName($_POST['student_name'] ?? 'Unknown');
            $stmtInsert = $pdo->prepare("INSERT INTO students (student_no, student_name, face_descriptor, fingerprint_id) VALUES (?, ?, ?, NULL)");
            $stmtInsert->execute([$stu_no, $stu_name, $final_json]);
            respond('success', "New student created and Web face model linked to {$stu_no}.");
        }
    } catch (PDOException $e) {
        error_log('[Student API] link_web_face: ' . $e->getMessage());
        respond('error', 'Database error while saving web face descriptor.', 500);
    }
}

// ─── get_all_descriptors ───────────────────────────────────────────
if ($action === 'get_all_descriptors') {
    try {
        $stmt = $pdo->query(
            "SELECT student_no, student_name, face_descriptor 
             FROM students 
             WHERE face_descriptor IS NOT NULL AND face_descriptor != ''"
        );
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return standard raw JSON for face api to parse
        http_response_code(200);
        echo json_encode($data);
        exit;
    } catch (PDOException $e) {
        error_log('[Student API] get_all_descriptors: ' . $e->getMessage());
        respond('error', 'Database error fetching descriptors.', 500);
    }
}

// ─── manual_attendance ────────────────────────────────────────────
if ($action === 'manual_attendance') {
    $stu_no      = sanitizeStudentNo($_POST['student_no'] ?? '');
    $course_code = substr(preg_replace('/[^a-zA-Z0-9_\- ]/', '', trim($_POST['course_code'] ?? 'MANUAL_ENTRY')), 0, 30);
    $timestamp   = trim($_POST['timestamp'] ?? '');

    $modality    = trim($_POST['modality'] ?? 'manual');

    if (empty($stu_no)) {
        respond('error', 'Student number is required.', 422);
    }

    if (empty($course_code)) $course_code = 'MANUAL_ENTRY';

    // Validate and parse timestamp
    if (!empty($timestamp) && strtotime($timestamp) !== false) {
        $timestamp = date('Y-m-d H:i:s', strtotime($timestamp));
    } else {
        $timestamp = date('Y-m-d H:i:s');
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO attendance_logs (student_no, device_name, course_code, timestamp, modality)
             VALUES (?, 'WEB_DASHBOARD', ?, ?, ?)"
        );
        $stmt->execute([$stu_no, $course_code, $timestamp, $modality]);
        respond('success', "Manual attendance recorded for {$stu_no}.");
    } catch (PDOException $e) {
        error_log('[Student API] manual_attendance: ' . $e->getMessage());
        respond('error', 'Database error while recording attendance.', 500);
    }
}

// ─── search_student ───────────────────────────────────────────────
if ($action === 'search_student') {
    header('Content-Type: application/json; charset=utf-8');
    $qr     = sanitizeStudentNo($_POST['query'] ?? '');
    $course = substr(preg_replace('/[^a-zA-Z0-9_\- ]/', '', trim($_POST['course_code'] ?? '')), 0, 30);

    if (empty($qr)) {
        echo json_encode(['student_name' => null]);
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT student_name, student_no
             FROM students
             WHERE student_no LIKE ? OR student_name LIKE ?
             LIMIT 1"
        );
        $stmt->execute(['%' . $qr . '%', '%' . $qr . '%']);
        $res = $stmt->fetch();

        if ($res) {
            $out = ['student_name' => $res['student_name']];

            if (!empty($course)) {
                $totalStmt = $pdo->prepare(
                    "SELECT COUNT(DISTINCT DATE(timestamp)) FROM attendance_logs WHERE course_code = ?"
                );
                $totalStmt->execute([$course]);
                $total = (int)$totalStmt->fetchColumn();

                $attnStmt = $pdo->prepare(
                    "SELECT COUNT(id) FROM attendance_logs WHERE course_code = ? AND student_no = ?"
                );
                $attnStmt->execute([$course, $res['student_no']]);
                $attended = (int)$attnStmt->fetchColumn();

                $out['stats'] = [
                    'total'      => $total,
                    'attended'   => $attended,
                    'percentage' => $total > 0 ? round(($attended / $total) * 100) : 0,
                ];
            }
            echo json_encode($out);
        } else {
            echo json_encode(['student_name' => null]);
        }
    } catch (PDOException $e) {
        error_log('[Student API] search_student: ' . $e->getMessage());
        echo json_encode(['student_name' => null, 'error' => true]);
    }
    exit;
}

// ─── Unknown Action ────────────────────────────────────────────────

// ─── add_course ────────────────────────────────────────────────────
if ($action === 'add_course') {
    $code = strtoupper(trim($_POST['course_code'] ?? ''));
    $name = trim($_POST['course_name'] ?? '');

    if (empty($code)) {
        respond('error', 'Course code is required.', 422);
    }
    if (!preg_match('/^[A-Z]{2,5}\d{4}$/', $code)) {
        respond('error', 'Invalid course code format. Use e.g. PHY1911.', 422);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name) VALUES (?, ?)");
        $stmt->execute([$code, $name]);
        respond('success', "Course {$code} added successfully.");
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            respond('error', "Course {$code} already exists.", 409);
        }
        error_log('[Student API] add_course: ' . $e->getMessage());
        respond('error', 'Database error while adding course.', 500);
    }
}

// ─── delete_course ─────────────────────────────────────────────────
if ($action === 'delete_course') {
    $code = strtoupper(trim($_POST['course_code'] ?? ''));
    if (empty($code)) {
        respond('error', 'Course code is required.', 422);
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM courses WHERE course_code = ?");
        $stmt->execute([$code]);
        $deleted = $stmt->rowCount();
        $pdo->commit();
        if ($deleted > 0) {
            respond('success', "Course {$code} and all associated enrollments deleted.");
        } else {
            respond('error', "Course {$code} not found.", 404);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[Student API] delete_course: ' . $e->getMessage());
        respond('error', 'Database error while deleting course.', 500);
    }
}

// ─── enroll_student_course ─────────────────────────────────────────
if ($action === 'enroll_student_course') {
    $stuNo = sanitizeStudentNo($_POST['student_no'] ?? '');
    $code  = strtoupper(trim($_POST['course_code'] ?? ''));

    if (empty($stuNo) || empty($code)) {
        respond('error', 'Student number and course code are required.', 422);
    }

    try {
        $stmtCheck = $pdo->prepare("SELECT student_no FROM students WHERE student_no = ?");
        $stmtCheck->execute([$stuNo]);
        if (!$stmtCheck->fetch()) {
            respond('error', "Student {$stuNo} not found.", 404);
        }

        $stmtCheck2 = $pdo->prepare("SELECT course_code FROM courses WHERE course_code = ?");
        $stmtCheck2->execute([$code]);
        if (!$stmtCheck2->fetch()) {
            respond('error', "Course {$code} not found.", 404);
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO student_courses (student_no, course_code) VALUES (?, ?)");
        $stmt->execute([$stuNo, $code]);
        if ($stmt->rowCount() > 0) {
            respond('success', "{$stuNo} enrolled in {$code}.");
        } else {
            respond('error', "{$stuNo} is already enrolled in {$code}.", 409);
        }
    } catch (PDOException $e) {
        error_log('[Student API] enroll_student_course: ' . $e->getMessage());
        respond('error', 'Database error while enrolling student.', 500);
    }
}

// ─── bulk_enroll_course_csv ────────────────────────────────────────
if ($action === 'bulk_enroll_course_csv') {
    if (!isset($_FILES['csv_file'])) {
        respond('error', 'No CSV file received.', 422);
    }

    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond('error', 'File upload error.', 500);
    }

    // Extract course code from filename (e.g. MAT3063.csv -> MAT3063)
    $filename = basename($file['name'], '.csv');
    $code = strtoupper(trim(preg_replace('/[^a-zA-Z0-9]/', '', $filename)));

    if (!preg_match('/^[A-Z]{2,5}\d{4}$/', $code)) {
        respond('error', 'Invalid course code in filename. Use e.g. MAT3063.csv', 422);
    }

    $content = file_get_contents($file['tmp_name']);
    $lines = array_filter(array_map('trim', explode("\n", $content)));

    if (empty($lines)) {
        respond('error', 'CSV file is empty.', 422);
    }

    $pdo->beginTransaction();
    try {
        // Verify course exists
        $stmtCheck = $pdo->prepare("SELECT course_code FROM courses WHERE course_code = ?");
        $stmtCheck->execute([$code]);
        if (!$stmtCheck->fetch()) {
            // Auto-create the course
            $stmtCreate = $pdo->prepare("INSERT INTO courses (course_code) VALUES (?)");
            $stmtCreate->execute([$code]);
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO student_courses (student_no, course_code) VALUES (?, ?)");
        $enrolled = 0;
        $skipped = 0;
        $errors = [];

        foreach ($lines as $line) {
            $cols = array_map('trim', explode(',', $line));
            $stuNo = trim($cols[0], '"\' ');

            if (empty($stuNo)) continue;

            // Normalize student number
            $stuNo = str_replace(['_', '-'], '/', $stuNo);

            try {
                $stmt->execute([$stuNo, $code]);
                if ($stmt->rowCount() > 0) {
                    $enrolled++;
                } else {
                    $skipped++;
                }
            } catch (PDOException $e) {
                $errors[] = $stuNo;
            }
        }

        $pdo->commit();
        respond('success', "Enrolled {$enrolled} students in {$code}. " . ($skipped > 0 ? "{$skipped} already enrolled. " : '') . (count($errors) > 0 ? count($errors) . " errors." : ''));
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[Student API] bulk_enroll_course_csv: ' . $e->getMessage());
        respond('error', 'Database error while bulk enrolling.', 500);
    }
}

// ─── unenroll_student_course ───────────────────────────────────────
if ($action === 'unenroll_student_course') {
    $stuNo = sanitizeStudentNo($_POST['student_no'] ?? '');
    $code  = strtoupper(trim($_POST['course_code'] ?? ''));

    if (empty($stuNo) || empty($code)) {
        respond('error', 'Student number and course code are required.', 422);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM student_courses WHERE student_no = ? AND course_code = ?");
        $stmt->execute([$stuNo, $code]);
        if ($stmt->rowCount() > 0) {
            respond('success', "{$stuNo} removed from {$code}.");
        } else {
            respond('error', "Enrollment not found.", 404);
        }
    } catch (PDOException $e) {
        error_log('[Student API] unenroll_student_course: ' . $e->getMessage());
        respond('error', 'Database error while unenrolling.', 500);
    }
}

// ─── get_student_courses ───────────────────────────────────────────
if ($action === 'get_student_courses') {
    header('Content-Type: application/json; charset=utf-8');
    $stuNo = sanitizeStudentNo($_GET['student_no'] ?? '');

    if (empty($stuNo)) {
        echo json_encode(['status' => 'error', 'message' => 'Student number is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT sc.course_code, c.course_name,
                    COUNT(DISTINCT DATE(al.timestamp)) AS total_sessions,
                    COUNT(al.id) AS attended
             FROM student_courses sc
             JOIN courses c ON sc.course_code = c.course_code
             LEFT JOIN attendance_logs al ON al.student_no = sc.student_no AND al.course_code = sc.course_code
             WHERE sc.student_no = ?
             GROUP BY sc.course_code, c.course_name
             ORDER BY sc.course_code ASC"
        );
        $stmt->execute([$stuNo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $courses = [];
        foreach ($rows as $r) {
            $total = (int)$r['total_sessions'];
            $attended = (int)$r['attended'];
            $courses[] = [
                'course_code' => $r['course_code'],
                'course_name' => $r['course_name'],
                'total' => $total,
                'attended' => $attended,
                'attendance_pct' => $total > 0 ? round(($attended / $total) * 100) : 0,
            ];
        }

        echo json_encode(['status' => 'success', 'courses' => $courses]);
    } catch (PDOException $e) {
        error_log('[Student API] get_student_courses: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── save_email_config ─────────────────────────────────────────────
if ($action === 'save_email_config') {
    header('Content-Type: application/json; charset=utf-8');

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $smtpHost = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
    $recipient = trim($_POST['recipient'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Valid email is required.']);
        exit;
    }
    if (empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'App password is required.']);
        exit;
    }

    $configFile = __DIR__ . '/../includes/email_config.php';
    $encrypted = base64_encode(json_encode([
        'email' => $email,
        'password' => $password,
        'smtp_host' => $smtpHost,
        'recipient' => $recipient,
        'smtp_port' => 587,
        'smtp_secure' => 'tls'
    ]));

    $phpContent = "<?php\n// Auto-generated email config — DO NOT EDIT MANUALLY\ndefine('USER_EMAIL_CONFIG', '" . addslashes($encrypted) . "');\ndefine('USER_EMAIL', '" . addslashes($email) . "');\ndefine('USER_SMTP_HOST', '" . addslashes($smtpHost) . "');\n";

    if (file_put_contents($configFile, $phpContent)) {
        echo json_encode(['status' => 'success', 'message' => 'Email config saved.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save config file.']);
    }
    exit;
}

// ─── send_absent_report_email ──────────────────────────────────────
if ($action === 'send_absent_report_email') {
    header('Content-Type: application/json; charset=utf-8');

    if (!defined('EMAIL_ENABLED') || !EMAIL_ENABLED) {
        echo json_encode(['status' => 'error', 'message' => 'Email notifications are not enabled.']);
        exit;
    }

    $code = strtoupper(trim($_POST['course_code'] ?? ''));
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $recipient = trim($_POST['recipient'] ?? '');
    $sendToStudents = (bool)($_POST['send_to_students'] ?? false);
    $senderEmail = trim($_POST['sender'] ?? trim($_POST['sender_email'] ?? (defined('USER_EMAIL') ? USER_EMAIL : (defined('EMAIL_FROM') ? EMAIL_FROM : ''))));
    $senderPassword = trim($_POST['sender_password'] ?? '');
    $smtpHost = trim($_POST['smtp_host'] ?? (defined('USER_SMTP_HOST') ? USER_SMTP_HOST : 'smtp.gmail.com'));

    if (empty($code)) {
        echo json_encode(['status' => 'error', 'message' => 'Course code is required.']);
        exit;
    }
    if (!empty($recipient) && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid recipient email address.']);
        exit;
    }
    if (empty($senderEmail) || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        $senderEmail = defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@localhost';
    }

    try {
        // Fetch absent students
        $stmt = $pdo->prepare(
            "SELECT s.student_no, s.student_name, sc.course_code
             FROM student_courses sc
             JOIN students s ON sc.student_no = s.student_no
             WHERE sc.course_code = ?
               AND s.student_no NOT IN (
                   SELECT student_no FROM attendance_logs
                   WHERE course_code = ? AND DATE(timestamp) = ?
               )
             ORDER BY s.student_no ASC"
        );
        $stmt->execute([$code, $code, $date]);
        $absent = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch course name
        $stmtCourse = $pdo->prepare("SELECT course_name FROM courses WHERE course_code = ?");
        $stmtCourse->execute([$code]);
        $courseRow = $stmtCourse->fetch();
        $courseName = $courseRow['course_name'] ?? $code;

        // Fetch present count
        $stmtPresent = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE course_code = ? AND DATE(timestamp) = ?");
        $stmtPresent->execute([$code, $date]);
        $presentCount = (int)$stmtPresent->fetchColumn();
        $absentCount = count($absent);
        $totalCount = $presentCount + $absentCount;

        $dateFormatted = date('F j, Y', strtotime($date));
        $adminSubject = "[Sentinel AMS] Absent Students Report - {$code} ({$dateFormatted})";

        $studentSent = 0;
        $adminSent = false;

        // Send individual emails to absent students
        if ($sendToStudents && $absentCount > 0) {
            foreach ($absent as $s) {
                $studentEmail = studentNoToEmail($s['student_no']);
                $stuSubject = "[Sentinel AMS] Absence Notification - {$code} ({$dateFormatted})";
                $stuHtml = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
                    body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
                    .container { max-width: 500px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; }
                    .header { background: #dc3545; color: #fff; padding: 16px 24px; }
                    .content { padding: 24px; }
                    .footer { padding: 16px 24px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #eee; }
                </style></head><body>
                <div class='container'>
                    <div class='header'><h2>Absence Notification</h2></div>
                    <div class='content'>
                        <p>Dear " . htmlspecialchars($s['student_name'] ?? 'Student') . ",</p>
                        <p>You were marked <strong>absent</strong> for the following session:</p>
                        <table style='width:100%;border-collapse:collapse;background:#f8f9fa;border-radius:6px;'>
                            <tr><td style='padding:8px;border-bottom:1px solid #eee;font-weight:bold;width:40%;'>Course</td><td style='padding:8px;border-bottom:1px solid #eee;'>{$code} ({$courseName})</td></tr>
                            <tr><td style='padding:8px;border-bottom:1px solid #eee;font-weight:bold;'>Date</td><td style='padding:8px;border-bottom:1px solid #eee;'>{$dateFormatted}</td></tr>
                            <tr><td style='padding:8px;font-weight:bold;'>Student No</td><td style='padding:8px;'>{$s['student_no']}</td></tr>
                        </table>
                        <p style='margin-top:16px;color:#666;'>If you believe this is an error, please contact your lecturer.</p>
                    </div>
                    <div class='footer'>Sentinel Swarm AMS — " . date('Y-m-d H:i:s') . "</div>
                </div></body></html>";

                $sent = false;
                if (!empty($senderPassword)) {
                    $sent = sendSmtpEmail($smtpHost, 587, $senderEmail, $senderPassword, $studentEmail, $stuSubject, $stuHtml);
                } else {
                    $stuHeaders = "From: " . EMAIL_FROM_NAME . " <" . $senderEmail . ">\r\n";
                    $stuHeaders .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
                    $sent = mail($studentEmail, $stuSubject, $stuHtml, $stuHeaders);
                }
                if ($sent) {
                    $studentSent++;
                    try {
                        $logStmt = $pdo->prepare("INSERT INTO email_sent_logs (sender_email, recipient_email, subject, message_type, course_code, student_no, student_name, body) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $logStmt->execute([$senderEmail, $studentEmail, $stuSubject, 'absent_notification', $code, $s['student_no'], $s['student_name'], $stuHtml]);
                    } catch (PDOException $e) { error_log('[Student API] email log: ' . $e->getMessage()); }
                }
            }
        }

        // Send admin report (if recipient provided)
        if (!empty($recipient)) {
            $headers = "From: " . EMAIL_FROM_NAME . " <" . $senderEmail . ">\r\n";
            $headers .= "Reply-To: " . $senderEmail . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
                body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; }
                .header { background: #ffc107; color: #333; padding: 16px 24px; }
                .header h2 { margin: 0; font-size: 18px; }
                .summary { display: flex; gap: 12px; padding: 16px 24px; background: #f8f9fa; }
                .summary .box { flex: 1; text-align: center; padding: 12px; border-radius: 6px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
                .box .num { font-size: 28px; font-weight: bold; }
                .box .label { font-size: 12px; color: #666; text-transform: uppercase; }
                .box.absent .num { color: #dc3545; }
                .box.present .num { color: #198754; }
                .box.total .num { color: #0d6efd; }
                table { width: 100%; border-collapse: collapse; margin: 0; }
                th { background: #343a40; color: #fff; padding: 10px 16px; text-align: left; font-size: 13px; }
                td { padding: 10px 16px; border-bottom: 1px solid #eee; font-size: 14px; }
                tr:nth-child(even) td { background: #f8f9fa; }
                .footer { padding: 16px 24px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #eee; }
            </style></head><body>
            <div class='container'>
                <div class='header'><h2>Absent Students Report</h2></div>
                <div style='padding: 16px 24px;'><p style='margin:0; color:#666;'>
                    <strong>Course:</strong> {$code} ({$courseName})<br>
                    <strong>Date:</strong> {$dateFormatted}
                </p></div>
                <div class='summary'>
                    <div class='box absent'><div class='num'>{$absentCount}</div><div class='label'>Absent</div></div>
                    <div class='box present'><div class='num'>{$presentCount}</div><div class='label'>Present</div></div>
                    <div class='box total'><div class='num'>{$totalCount}</div><div class='label'>Total</div></div>
                </div>";

            if ($absentCount > 0) {
                $html .= "<table><thead><tr><th>Student No</th><th>Name</th></tr></thead><tbody>";
                foreach ($absent as $s) {
                    $name = htmlspecialchars($s['student_name'] ?? 'Unknown');
                    $stuNo = htmlspecialchars($s['student_no']);
                    $stuEmail = studentNoToEmail($s['student_no']);
                    $html .= "<tr><td>{$stuNo}</td><td>{$name} <span class='small text-muted'>({$stuEmail})</span></td></tr>";
                }
                $html .= "</tbody></table>";
            } else {
                $html .= "<div style='padding: 24px; text-align: center; color: #198754; font-weight: bold; font-size: 16px;'>
                    ✓ All enrolled students were present!
                </div>";
            }

            $html .= "<div class='footer'>Generated by Sentinel Swarm AMS on " . date('Y-m-d H:i:s') . "</div></div></body></html>";

            if (!empty($senderPassword)) {
                $adminSent = sendSmtpEmail($smtpHost, 587, $senderEmail, $senderPassword, $recipient, $adminSubject, $html);
            } else {
                $adminSent = mail($recipient, $adminSubject, $html, $headers);
            }

            if ($adminSent) {
                try {
                    $logStmt = $pdo->prepare("INSERT INTO email_sent_logs (sender_email, recipient_email, subject, message_type, course_code, body) VALUES (?, ?, ?, ?, ?, ?)");
                    $logStmt->execute([$senderEmail, $recipient, $adminSubject, 'absent_report', $code, $html]);
                } catch (PDOException $e) { error_log('[Student API] email log: ' . $e->getMessage()); }
            }
        }

        $msg = '';
        if ($sendToStudents) $msg .= "{$studentSent}/{$absentCount} student emails sent. ";
        if ($adminSent) $msg .= "Admin report sent to {$recipient}.";

        echo json_encode([
            'status' => 'success',
            'message' => trim($msg) ?: 'Email processed.',
            'student_sent' => $studentSent,
            'admin_sent' => $adminSent,
            'absent_count' => $absentCount,
        ]);
    } catch (PDOException $e) {
        error_log('[Student API] send_absent_report_email: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── Helper: Convert student number to university email ───────────
// S/22/314 -> s22314@sci.pdn.ac.lk
function studentNoToEmail($studentNo) {
    $no = trim($studentNo);
    // Remove S/ prefix and all slashes
    $clean = str_replace('/', '', str_ireplace('S/', '', $no));
    $clean = str_replace('/', '', $clean);
    return 's' . strtolower($clean) . '@sci.pdn.ac.lk';
}

// ─── SMTP Email Helper (no external libraries) ─────────────────────
function sendSmtpEmail($host, $port, $user, $pass, $to, $subject, $body) {
    try {
        $socket = fsockopen($host, $port, $errno, $errstr, 15);
        if (!$socket) return false;
        
        $read = function($socket) {
            $response = '';
            while ($line = fgets($socket, 512)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $response;
        };
        
        $read($socket); // Banner
        fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n"); $read($socket);
        fputs($socket, "STARTTLS\r\n"); $read($socket);
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
        fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n"); $read($socket);
        fputs($socket, "AUTH LOGIN\r\n"); $read($socket);
        fputs($socket, base64_encode($user) . "\r\n"); $read($socket);
        fputs($socket, base64_encode($pass) . "\r\n"); $resp = $read($socket);
        if (substr($resp, 0, 3) !== '235') { fclose($socket); return false; }
        
        $headers = "From: Sentinel AMS <{$user}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        fputs($socket, "MAIL FROM:<{$user}>\r\n"); $read($socket);
        fputs($socket, "RCPT TO:<{$to}>\r\n"); $read($socket);
        fputs($socket, "DATA\r\n"); $read($socket);
        fputs($socket, $headers . "\r\n" . $body . "\r\n.\r\n"); $read($socket);
        fputs($socket, "QUIT\r\n"); $read($socket);
        fclose($socket);
        return true;
    } catch (Exception $e) {
        error_log('[SMTP Error] ' . $e->getMessage());
        return false;
    }
}

// ─── send_custom_email ─────────────────────────────────────────────
if ($action === 'send_custom_email') {
    header('Content-Type: application/json; charset=utf-8');

    if (!defined('EMAIL_ENABLED') || !EMAIL_ENABLED) {
        echo json_encode(['status' => 'error', 'message' => 'Email notifications are not enabled.']);
        exit;
    }

    $sender     = trim($_POST['sender'] ?? (defined('USER_EMAIL') ? USER_EMAIL : EMAIL_FROM));
    $senderPassword = trim($_POST['sender_password'] ?? '');
    $smtpHost   = trim($_POST['smtp_host'] ?? (defined('USER_SMTP_HOST') ? USER_SMTP_HOST : 'smtp.gmail.com'));
    $recipient  = trim($_POST['recipient'] ?? '');
    $subject    = trim($_POST['subject'] ?? 'Sentinel AMS Notification');
    $body       = trim($_POST['body'] ?? '');
    $msgType    = trim($_POST['message_type'] ?? 'custom');
    $courseCode = trim($_POST['course_code'] ?? '');
    $studentNo  = trim($_POST['student_no'] ?? '');
    $studentName = trim($_POST['student_name'] ?? '');

    if (empty($recipient) || empty($body)) {
        echo json_encode(['status' => 'error', 'message' => 'Recipient and message body are required.']);
        exit;
    }
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid recipient email address.']);
        exit;
    }

    $headers = "From: Sentinel AMS <" . $sender . ">\r\n";
    $headers .= "Reply-To: " . $sender . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $htmlBody = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
        body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #0d6efd; color: #fff; padding: 16px 24px; }
        .content { padding: 24px; white-space: pre-wrap; }
        .footer { padding: 16px 24px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #eee; }
    </style></head><body>
    <div class='container'>
        <div class='header'><h2>" . htmlspecialchars($subject) . "</h2></div>
        <div class='content'>" . nl2br(htmlspecialchars($body)) . "</div>
        <div class='footer'>Sent by Sentinel Swarm AMS on " . date('Y-m-d H:i:s') . "</div>
    </div></body></html>";

    // Send email via SMTP if credentials provided, otherwise use mail()
    $emailSent = false;
    if (!empty($senderPassword)) {
        $emailSent = sendSmtpEmail($smtpHost, 587, $sender, $senderPassword, $recipient, $subject, $htmlBody);
    } else {
        $emailSent = mail($recipient, $subject, $htmlBody, $headers);
    }
    
    if ($emailSent) {
        try {
            $logStmt = $pdo->prepare("INSERT INTO email_sent_logs (sender_email, recipient_email, subject, message_type, course_code, student_no, student_name, body) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $logStmt->execute([$sender, $recipient, $subject, $msgType, $courseCode, $studentNo, $studentName, $htmlBody]);
        } catch (PDOException $e) { error_log('[Student API] email log: ' . $e->getMessage()); }
        
        echo json_encode(['status' => 'success', 'message' => "Email sent to {$recipient}."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send email.']);
    }
    exit;
}

// ─── Unknown Action fallback ────────────────────────────────────────

// ─── assign_course_to_device ───────────────────────────────────────
if ($action === 'assign_course_to_device') {
    $device = trim($_POST['device_id'] ?? '');
    $code = strtoupper(trim($_POST['course_code'] ?? ''));

    if (empty($device) || empty($code)) {
        respond('error', 'Device ID and course code are required.', 422);
    }

    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO device_courses (device_id, course_code) VALUES (?, ?)");
        $stmt->execute([$device, $code]);
        if ($stmt->rowCount() > 0) {
            respond('success', "Course {$code} assigned to {$device}.");
        } else {
            respond('error', "Course {$code} is already assigned to {$device}.", 409);
        }
    } catch (PDOException $e) {
        error_log('[Student API] assign_course_to_device: ' . $e->getMessage());
        respond('error', 'Database error while assigning course.', 500);
    }
}

// ─── remove_course_from_device ─────────────────────────────────────
if ($action === 'remove_course_from_device') {
    $device = trim($_POST['device_id'] ?? '');
    $code = strtoupper(trim($_POST['course_code'] ?? ''));

    if (empty($device) || empty($code)) {
        respond('error', 'Device ID and course code are required.', 422);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM device_courses WHERE device_id = ? AND course_code = ?");
        $stmt->execute([$device, $code]);
        if ($stmt->rowCount() > 0) {
            respond('success', "Course {$code} removed from {$device}.");
        } else {
            respond('error', "Assignment not found.", 404);
        }
    } catch (PDOException $e) {
        error_log('[Student API] remove_course_from_device: ' . $e->getMessage());
        respond('error', 'Database error while removing assignment.', 500);
    }
}

// ─── get_device_courses ────────────────────────────────────────────
if ($action === 'get_device_courses') {
    header('Content-Type: application/json; charset=utf-8');
    $device = trim($_GET['device_id'] ?? '');

    if (empty($device)) {
        echo json_encode(['status' => 'error', 'message' => 'Device ID is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT course_code FROM device_courses WHERE device_id = ? ORDER BY course_code ASC");
        $stmt->execute([$device]);
        $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['status' => 'success', 'courses' => $courses]);
    } catch (PDOException $e) {
        error_log('[Student API] get_device_courses: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── Student Self-Service: Get My Profile ────────────────────────
if ($action === 'get_my_profile') {
    requireRole('student');
    $studentNo = $_SESSION['student_no'] ?? $_SESSION['username'] ?? '';
    if (empty($studentNo)) {
        echo json_encode(['status' => 'error', 'message' => 'No student number.']); exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT student_no, student_name, fingerprint_id, rfid_uid, face_id, profile_photo FROM students WHERE student_no = ?");
        $stmt->execute([$studentNo]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'profile' => $profile]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// ─── Student Self-Service: Update Profile ────────────────────────
if ($action === 'update_my_profile') {
    requireRole('student');
    $studentNo = $_SESSION['student_no'] ?? $_SESSION['username'] ?? '';
    if (empty($studentNo)) {
        echo json_encode(['status' => 'error', 'message' => 'No student number.']); exit;
    }
    $name = trim($_POST['student_name'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $currentPassword = trim($_POST['current_password'] ?? '');

    if (empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Name required.']); exit;
    }

    try {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $lookup = $pdo->prepare("SELECT user_id FROM students WHERE student_no = ?");
            $lookup->execute([$studentNo]);
            $userId = $lookup->fetchColumn();
        }

        if (!empty($newPassword) && $userId) {
            if (empty($currentPassword)) {
                echo json_encode(['status' => 'error', 'message' => 'Current password required to change.']); exit;
            }
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$u || !password_verify($currentPassword, $u['password_hash'])) {
                echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']); exit;
            }
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);
        }

        $stmt = $pdo->prepare("UPDATE students SET student_name = ? WHERE student_no = ?");
        $stmt->execute([$name, $studentNo]);
        $_SESSION['full_name'] = $name;

        echo json_encode(['status' => 'success', 'message' => 'Profile updated.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ─── Student Self-Service: Upload Photo ──────────────────────────
if ($action === 'upload_my_photo') {
    requireRole('student');
    $studentNo = $_SESSION['student_no'] ?? $_SESSION['username'] ?? '';
    if (empty($studentNo)) {
        echo json_encode(['status' => 'error', 'message' => 'No student number.']); exit;
    }
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded.']); exit;
    }
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type.']); exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'File too large (max 5MB).']); exit;
    }
    $uploadDir = __DIR__ . '/../uploads/students/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = str_replace('/', '_', $studentNo) . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $old = $pdo->prepare("SELECT profile_photo FROM students WHERE student_no = ?");
        $old->execute([$studentNo]);
        $oldPath = $old->fetchColumn();
        if ($oldPath && file_exists($uploadDir . basename($oldPath))) unlink($uploadDir . basename($oldPath));
        $photoPath = 'uploads/students/' . $filename;
        $pdo->prepare("UPDATE students SET profile_photo = ? WHERE student_no = ?")->execute([$photoPath, $studentNo]);
        echo json_encode(['status' => 'success', 'photo_url' => '/' . $photoPath, 'message' => 'Photo uploaded.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save file.']);
    }
    exit;
}

// ─── Student Self-Service: Get Attendance History ────────────────
if ($action === 'get_my_attendance') {
    requireRole('student');
    $studentNo = $_SESSION['student_no'] ?? $_SESSION['username'] ?? '';
    if (empty($studentNo)) {
        echo json_encode(['status' => 'error', 'message' => 'No student number in session.']); exit;
    }
    $courseFilter = strtoupper(trim($_GET['course_code'] ?? ''));
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    $search = trim($_GET['search'] ?? '');
    $limit = (int)($_GET['limit'] ?? 30);

    $where = "al.student_no = ?";
    $params = [$studentNo];

    if (!empty($courseFilter)) { $where .= " AND al.course_code = ?"; $params[] = $courseFilter; }
    if (!empty($dateFrom)) { $where .= " AND DATE(al.timestamp) >= ?"; $params[] = $dateFrom; }
    if (!empty($dateTo)) { $where .= " AND DATE(al.timestamp) <= ?"; $params[] = $dateTo; }
    if (!empty($search)) { $where .= " AND (al.course_code LIKE ? OR c.course_name LIKE ?)"; $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%'; }

    $stmt = $pdo->prepare(
        "SELECT al.student_no, al.course_code, c.course_name, al.timestamp, al.modality
         FROM attendance_logs al
         LEFT JOIN courses c ON al.course_code = c.course_code
         WHERE $where ORDER BY al.timestamp DESC LIMIT ?"
    );
    $params[] = $limit;
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'logs' => $logs]);
    exit;
}

// ─── Student Self-Service: Get My Courses ────────────────────────
if ($action === 'get_my_courses') {
    requireRole('student');
    $studentNo = $_SESSION['student_no'] ?? $_SESSION['username'] ?? '';
    if (empty($studentNo)) {
        echo json_encode(['status' => 'error', 'message' => 'No student number.']); exit;
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT sc.course_code, c.course_name
             FROM student_courses sc
             LEFT JOIN courses c ON sc.course_code = c.course_code
             WHERE sc.student_no = ? ORDER BY c.course_code ASC"
        );
        $stmt->execute([$studentNo]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $codes = array_column($courses, 'course_code');
        $attendance = [];
        $totals = [];
        if (!empty($codes)) {
            $ph = implode(',', array_fill(0, count($codes), '?'));

            $s = $pdo->prepare("SELECT course_code, COUNT(DISTINCT DATE(timestamp)) as cnt FROM attendance_logs WHERE student_no = ? AND course_code IN ($ph) GROUP BY course_code");
            $s->execute(array_merge([$studentNo], $codes));
            while ($r = $s->fetch(PDO::FETCH_ASSOC)) $attendance[$r['course_code']] = $r['cnt'];

            $s2 = $pdo->prepare("SELECT course_code, COUNT(DISTINCT DATE(timestamp)) as cnt FROM attendance_logs WHERE course_code IN ($ph) GROUP BY course_code");
            $s2->execute($codes);
            while ($r = $s2->fetch(PDO::FETCH_ASSOC)) $totals[$r['course_code']] = $r['cnt'];
        }

        foreach ($courses as &$c) {
            $cc = $c['course_code'];
            $c['attended'] = $attendance[$cc] ?? 0;
            $c['total_sessions'] = $totals[$cc] ?? 0;
            $c['percentage'] = $c['total_sessions'] > 0 ? round(($c['attended'] / $c['total_sessions']) * 100, 1) : 0;
        }

        // Compute overall attendance percentage across all courses
        $sumAtt = 0; $sumTot = 0;
        foreach ($courses as $cc) {
            $sumAtt += (int)($cc['attended'] ?? 0);
            $sumTot += (int)($cc['total_sessions'] ?? 0);
        }
        $overallPct = $sumTot > 0 ? round(($sumAtt / $sumTot) * 100, 1) : 0;
        $tier = attendanceTier((float)$overallPct);

        echo json_encode(['status' => 'success', 'courses' => $courses, 'tier' => $tier, 'overall' => $overallPct]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ─── Unknown Action fallback ────────────────────────────────────────
respond('error', "Unknown action: {$action}", 400);
?>
