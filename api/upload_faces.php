<?php
/**
 * API: Upload Face Dataset — UOP AMS
 * Accepts face image uploads for a specific student.
 * Requires an active admin session.
 */
require '../includes/auth.php';
require '../includes/db.php';

requireCsrfIfMutating();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// ─── Input Validation ─────────────────────────────────────────────
$student_no   = trim($_POST['student_no']   ?? '');
$student_name = trim($_POST['student_name'] ?? 'Unknown');

if (empty($student_no)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Student number is required.']);
    exit;
}

// Sanitize for safe folder names (no path traversal)
$safe_no   = preg_replace('/[^a-zA-Z0-9_]/', '_', $student_no);
$safe_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $student_name);

// Ensure the face_datasets directory exists (one level above api/)
$datasets_dir = dirname(__DIR__) . '/face_datasets';
if (!is_dir($datasets_dir)) {
    if (!mkdir($datasets_dir, 0750, true)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Could not create datasets directory.']);
        exit;
    }
}

$folder_name = $datasets_dir . '/' . $safe_no . '_' . $safe_name;
if (!is_dir($folder_name)) {
    if (!mkdir($folder_name, 0750, true)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Could not create student image folder.']);
        exit;
    }
}

// ─── File Uploads ─────────────────────────────────────────────────
$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
$max_files          = 20;
$max_file_size      = 5 * 1024 * 1024; // 5 MB per file

$count  = 0;
$errors = [];

if (!isset($_FILES['face_images'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No images were received. Use field name "face_images[]".']);
    exit;
}

$files     = $_FILES['face_images'];
$fileCount = count($files['name']);

if ($fileCount > $max_files) {
    http_response_code(413);
    echo json_encode(['status' => 'error', 'message' => "Too many files. Maximum is {$max_files} images per upload."]);
    exit;
}

for ($i = 0; $i < $fileCount; $i++) {
    // Skip files with upload errors
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $errors[] = "File #{$i}: upload error code " . $files['error'][$i];
        continue;
    }

    // Check individual file size
    if ($files['size'][$i] > $max_file_size) {
        $errors[] = "File #{$i}: exceeds 5MB limit.";
        continue;
    }

    // Validate MIME type using finfo (not trusting extension alone)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
    finfo_close($finfo);

    if (strpos($mimeType, 'image/') !== 0) {
        $errors[] = "File #{$i}: not a valid image (detected: {$mimeType}).";
        continue;
    }

    // Validate file extension (whitelist)
    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions, true)) {
        $errors[] = "File #{$i}: extension '.{$ext}' is not allowed. Use jpg, jpeg, png, or webp.";
        continue;
    }

    // Generate a unique filename to prevent collisions
    $unique_name = 'face_' . uniqid('', true) . '.' . $ext;
    $destination = $folder_name . '/' . $unique_name;

    if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
        $count++;
    } else {
        $errors[] = "File #{$i}: failed to save to disk.";
    }
}

$response = [
    'status'  => 'success',
    'count'   => $count,
    'folder'  => basename($folder_name),
    'message' => "{$count} image(s) uploaded successfully.",
];

if (!empty($errors)) {
    $response['warnings'] = $errors;
    $response['message'] .= ' ' . count($errors) . ' file(s) were skipped (see warnings).';
}

echo json_encode($response);
?>
