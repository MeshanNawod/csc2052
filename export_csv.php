<?php
/**
 * CSV Export — UOP AMS
 * Downloads attendance records as a CSV. Requires an active admin session.
 * All filter parameters are sanitized and validated before use in queries.
 */
require_once 'includes/auth.php'; // Session auth required
require_once 'includes/db.php';

// ─── Input Sanitization ───────────────────────────────────────────
$student_no  = trim($_GET['student_no']  ?? '');
$start_time  = trim($_GET['start_time']  ?? '');
$end_time    = trim($_GET['end_time']    ?? '');
$device      = trim($_GET['device']      ?? '');
$course_code = trim($_GET['course_code'] ?? '');

// Validate date inputs
if (!empty($start_time) && strtotime($start_time) === false) {
    $start_time = '';
}
if (!empty($end_time) && strtotime($end_time) === false) {
    $end_time = '';
}

// Pad time portion if only a date was given
if (!empty($start_time) && strpos($start_time, ':') === false) {
    $start_time .= ' 00:00:00';
}
if (!empty($end_time) && strpos($end_time, ':') === false) {
    $end_time .= ' 23:59:59';
}

// Sanitize filename-safe values
$safe_course = preg_replace('/[^a-zA-Z0-9_]/', '', $course_code);
$safe_device = preg_replace('/[^a-zA-Z0-9_]/', '', $device);

// ─── Build Filename ───────────────────────────────────────────────
$filename = 'attendance_export_' . date('Y-m-d_H-i');
if (!empty($safe_course)) $filename .= '_' . $safe_course;
if (!empty($safe_device))  $filename .= '_' . $safe_device;
$filename .= '.csv';

// ─── Column Selection ─────────────────────────────────────────────
$availableColumns = [
    'id'          => ['label' => 'Log ID',          'sql' => 'l.id'],
    'student_no'  => ['label' => 'Student No',      'sql' => 'l.student_no'],
    'student_name'=> ['label' => 'Student Name',    'sql' => "COALESCE(s.student_name, '') AS student_name"],
    'course_code' => ['label' => 'Course Code',     'sql' => 'l.course_code'],
    'device'      => ['label' => 'Device',          'sql' => 'l.device_name'],
    'timestamp'   => ['label' => 'Timestamp',       'sql' => 'l.timestamp'],
    'modality'    => ['label' => 'Modality',        'sql' => 'l.modality'],
    'is_offline'  => ['label' => 'Offline Sync',    'sql' => "CASE WHEN l.is_offline_sync=1 THEN 'Yes' ELSE 'No' END AS is_offline"],
    'ip_address'  => ['label' => 'Device IP',       'sql' => "COALESCE(d.ip, '') AS ip_address"],
];

$selectedCols = trim($_GET['columns'] ?? '');
if ($selectedCols === '') {
    // Default: all columns
    $colKeys = array_keys($availableColumns);
} else {
    $colKeys = array_map('trim', explode(',', $selectedCols));
    $colKeys = array_intersect($colKeys, array_keys($availableColumns));
    if (empty($colKeys)) $colKeys = array_keys($availableColumns);
}

// ─── Send Headers ─────────────────────────────────────────────────
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');
// BOM for Excel UTF-8 compatibility
fwrite($output, "\xEF\xBB\xBF");

// Column headers (only selected)
$headers = [];
foreach ($colKeys as $key) {
    $headers[] = $availableColumns[$key]['label'];
}
fputcsv($output, $headers);

// ─── Query Builder ────────────────────────────────────────────────
try {
    $sqlCols = [];
    foreach ($colKeys as $key) {
        $sqlCols[] = $availableColumns[$key]['sql'];
    }
    
    $query  = "SELECT " . implode(', ', $sqlCols) .
              " FROM attendance_logs l" .
              " LEFT JOIN students s ON l.student_no = s.student_no" .
              " LEFT JOIN devices_registry d ON d.name = l.device_name" .
              " WHERE 1=1";
    $params = [];

    if (!empty($student_no)) {
        $query   .= " AND l.student_no LIKE ?";
        $params[] = '%' . $student_no . '%';
    }
    if (!empty($start_time)) {
        $query   .= " AND l.timestamp >= ?";
        $params[] = $start_time;
    }
    if (!empty($end_time)) {
        $query   .= " AND l.timestamp <= ?";
        $params[] = $end_time;
    }
    if (!empty($device)) {
        $query   .= " AND l.device_name LIKE ?";
        $params[] = '%' . $device . '%';
    }
    if (!empty($course_code)) {
        $query   .= " AND l.course_code LIKE ?";
        $params[] = '%' . $course_code . '%';
    }

    $query .= " ORDER BY l.timestamp DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        fputcsv($output, $row);
    }

} catch (PDOException $e) {
    error_log('[UOP AMS Export CSV] ' . $e->getMessage());
    fputcsv($output, ['ERROR: Failed to fetch records. Please contact the administrator.']);
}

fclose($output);
exit;
?>