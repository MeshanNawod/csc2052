<?php
/**
 * Device Password API — Verifies hardware-side admin passwords
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$password = $_POST['password'] ?? '';

if (!$password) {
    echo json_encode(['status' => 'error', 'message' => 'Password required']);
    exit;
}

try {
    // Check against admin user's password hash or a dedicated device password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        echo json_encode(['status' => 'ok', 'verified' => true]);
    } else {
        echo json_encode(['status' => 'error', 'verified' => false, 'message' => 'Invalid password']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
