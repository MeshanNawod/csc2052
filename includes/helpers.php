<?php
/**
 * Shared Helper Functions — Sentinel Swarm AMS
 */

/**
 * Extract 2-letter initials from a full name, stripping title prefixes.
 * e.g. "Prof. John Doe" → "JD"
 * Also handles dotted initials like "M.D.M.N.Appuhami" → "MA"
 */
if (!function_exists('getInitials')) {
    function getInitials(string $name): string {
        $name = preg_replace('/^(Prof|Dr|Mr|Mrs|Ms|Sir|Madam)\.?\s*/i', '', trim($name));
        // Handle dotted initials: "M.D.M.N.Appuhami" → keep only letters and spaces
        $name = preg_replace('/[^\p{L}\s]/u', ' ', $name);
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) return '';
        // If more than 2 parts, likely dotted name: take first letter of first part + first letter of last part
        $first = strtoupper(substr($parts[0], 0, 1));
        if (count($parts) > 1) {
            // For dotted names like "M D M N Appuhami", use last part for second initial
            $lastPart = $parts[count($parts)-1];
            $second = strtoupper(substr($lastPart, 0, 1));
        } else {
            $second = '';
        }
        return $first . $second;
    }
}

/**
 * Map attendance percentage to a tier name.
 * Diamond > 94, Emerald 90-93, Platinum 85-89, Gold 80-84, Silver 70-79, Bronze 60-69, Copper < 60
 */
if (!function_exists('attendanceTier')) {
    function attendanceTier($percent) {
        $percent = (float)$percent;
        if ($percent >= 94) return 'Diamond';
        if ($percent >= 90) return 'Emerald';
        if ($percent >= 85) return 'Platinum';
        if ($percent >= 80) return 'Gold';
        if ($percent >= 70) return 'Silver';
        if ($percent >= 60) return 'Bronze';
        return 'Copper';
    }
}

/**
 * Escape output for HTML context (alias for convenience).
 */
if (!function_exists('h')) {
    function h($value, string $encoding = 'UTF-8'): string {
        if ($value === null || $value === '') return '';
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, $encoding);
    }
}

/**
 * Validate a device IP address string.
 */
if (!function_exists('isValidDeviceIp')) {
    function isValidDeviceIp(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            || filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}

/**
 * Sanitize and validate a course code (alphanumeric, hyphens, underscores only).
 */
if (!function_exists('sanitizeCourseCode')) {
    function sanitizeCourseCode(string $code): string|false {
        $code = trim($code);
        if (preg_match('/^[A-Za-z0-9_-]{1,50}$/', $code)) {
            return $code;
        }
        return false;
    }
}

/**
 * Validate a date string (YYYY-MM-DD format).
 */
if (!function_exists('isValidDate')) {
    function isValidDate(string $date): bool {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false;
    }
}

/**
 * Log a structured audit message to the application log.
 */
if (!function_exists('auditLog')) {
    function auditLog(string $level, string $action, array $context = []): void {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0750, true); }

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => $level,
            'action'    => $action,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user'      => $_SESSION['username'] ?? 'anonymous',
            'request_id' => substr(bin2hex(random_bytes(4)), 0, 8),
        ] + $context;

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
        $file = $logDir . '/audit-' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Standardized API JSON response.
 */
if (!function_exists('apiResponse')) {
    function apiResponse(string $status, string $message, mixed $data = null, int $httpCode = 200): never {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/**
 * Standardized API error response.
 */
if (!function_exists('apiError')) {
    function apiError(string $message, int $httpCode = 400, array $errors = []): never {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=UTF-8');
        $resp = [
            'status'  => 'error',
            'message' => $message,
        ];
        if (!empty($errors)) $resp['errors'] = $errors;
        echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
