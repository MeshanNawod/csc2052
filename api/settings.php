<?php
/**
 * API: Settings — UOP AMS
 * Read and write device hardware configuration settings.
 * Requires an active admin session.
 */
require '../includes/auth.php';
require '../includes/db.php';

requireCsrfIfMutating();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ─── GET: Read Settings ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $device_id = trim($_GET['device_id'] ?? 'DEFAULT');
    $device_id = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $device_id), 0, 50);

    try {
        $stmt = $pdo->prepare("SELECT * FROM device_settings WHERE device_id = ? LIMIT 1");
        $stmt->execute([$device_id]);
        $settings = $stmt->fetch();

        if ($settings) {
            echo json_encode($settings);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => "Device '{$device_id}' not found in settings."]);
        }
    } catch (PDOException $e) {
        error_log('[UOP AMS Settings API] GET: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve settings.']);
    }
    exit;
}

// ─── POST: Save Settings ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_id = trim($_POST['device_id'] ?? 'DEFAULT');
    $device_id = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $device_id), 0, 50);

    // Integer toggles — clamp to 0 or 1
    $bool = fn($v) => (int)(bool)(int)$v;

    $fp_power        = $bool($_POST['fp_power']        ?? 1);
    $display_power   = $bool($_POST['display_power']   ?? 1);
    $backlight_power = $bool($_POST['backlight_power'] ?? 1);
    $bluetooth_on    = $bool($_POST['bluetooth_on']    ?? 0);

    // Phase 3 modality toggles
    $enable_fingerprint    = $bool($_POST['enable_fingerprint']    ?? 1);
    $enable_rfid           = $bool($_POST['enable_rfid']           ?? 1);
    $enable_face           = $bool($_POST['enable_face']           ?? 1);
    $require_multi_factor  = $bool($_POST['require_multi_factor']  ?? 0);

    // Enroll fingers count — clamp to 1–10
    $enroll_fingers = max(1, min(10, (int)($_POST['enroll_fingers'] ?? 3)));

    try {
        $stmt = $pdo->prepare(
            "UPDATE device_settings
             SET fp_power = ?, display_power = ?, backlight_power = ?,
                 enroll_fingers = ?, bluetooth_on = ?,
                 enable_fingerprint = ?, enable_rfid = ?, enable_face = ?,
                 require_multi_factor = ?
             WHERE device_id = ?"
        );
        $stmt->execute([
            $fp_power, $display_power, $backlight_power,
            $enroll_fingers, $bluetooth_on,
            $enable_fingerprint, $enable_rfid, $enable_face,
            $require_multi_factor,
            $device_id,
        ]);

        if ($stmt->rowCount() === 0) {
            // Device row doesn't exist — inform the user
            echo json_encode(['status' => 'warning', 'message' => "No settings row found for device '{$device_id}'. No changes made."]);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Settings saved successfully.']);
        }
    } catch (PDOException $e) {
        error_log('[Sentinel Swarm Settings API] POST: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save settings.']);
    }
    exit;
}

// ─── Method Not Allowed ────────────────────────────────────────────
http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
?>
