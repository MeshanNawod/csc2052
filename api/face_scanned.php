<?php
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$buffer_file = __DIR__ . '/../sys_latest_face.txt';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'consume') {
    require '../includes/auth.php';

    if (!file_exists($buffer_file)) {
        echo json_encode(['status' => 'empty']);
        exit;
    }

    $face_id = trim(file_get_contents($buffer_file));
    if (!empty($face_id)) {
        // Atomically clear the buffer after reading
        file_put_contents($buffer_file, '', LOCK_EX);
        echo json_encode(['status' => 'success', 'face_id' => $face_id]);
    } else {
        echo json_encode(['status' => 'empty']);
    }
    exit;
}
?>
