<?php
/**
 * SSE (Server-Sent Events) endpoint for real-time Serial Monitor.
 * Connect with: /api/sse_monitor.php?ip=10.185.246.68
 * Streams ESP32 responses to the browser in real-time.
 */
require_once __DIR__ . '/../includes/auth.php';

set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

while (ob_get_level()) ob_end_clean();

$ip = filter_input(INPUT_GET, 'ip', FILTER_VALIDATE_IP);
if (!$ip) {
    echo "data: {\"error\":\"Valid IP required\"}\n\n";
    flush();
    exit;
}

$timeout = 300;
$start = time();
$pollInterval = 2;

echo "data: {\"type\":\"connected\",\"ip\":\"$ip\"}\n\n";
flush();

function fetchEsp($ip, $command) {
    $url = "http://$ip/cmd?command=" . urlencode($command);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/plain',
        'User-Agent: Mozilla/5.0',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response !== false && $httpCode == 200) return $response;
    return json_encode(['type' => 'error', 'message' => "HTTP $httpCode: $error"]);
}

while (connection_status() === CONNECTION_NORMAL && (time() - $start) < $timeout) {
    if (connection_aborted()) break;

    $response = fetchEsp($ip, 'GETSTATUS');

    if ($response !== false && trim($response) !== '') {
        // Check if it's an error JSON from fetchEsp
        $decoded = json_decode($response, true);
        if (isset($decoded['type']) && $decoded['type'] === 'error') {
            echo "data: $response\n\n";
            flush();
            continue;
        }
        $lines = preg_split('/\r?\n/', trim($response));
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $payload = json_encode(['type' => 'data', 'line' => $trimmed], JSON_UNESCAPED_SLASHES);
                echo "data: $payload\n\n";
                flush();
            }
        }
    } else {
        echo "data: {\"type\":\"error\",\"message\":\"Cannot reach $ip\"}\n\n";
        flush();
    }

    for ($i = 0; $i < $pollInterval * 4; $i++) {
        usleep(250000);
        if (connection_aborted()) break 2;
    }
}

echo "data: {\"type\":\"disconnected\"}\n\n";
flush();
