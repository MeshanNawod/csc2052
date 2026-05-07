<?php
/**
 * Login Page — Sentinel Swarm AMS v3
 * Multi-role: admin, teacher, student
 * Students default password = student_no, forced change on first login.
 */
require_once 'includes/config.php';

// Use project-level session directory (avoids C:\xampp\tmp permission issues)
$sessionPath = __DIR__ . '/sessions';
if (is_dir($sessionPath)) {
    session_save_path($sessionPath);
}

// ─── ALWAYS start with a clean session on login page ─────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => SESSION_COOKIE_SECURE,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// If already logged in, redirect to correct dashboard (only on GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin')     { header('Location: index.php'); exit; }
    if ($role === 'teacher')   { header('Location: teacher_dashboard.php'); exit; }
    if ($role === 'student')   { header('Location: student_dashboard.php'); exit; }
    // No role known — destroy and show login form
    $_SESSION = [];
    session_destroy();
    session_start();
}

// ─── CSRF Token Generation ───────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ─── Rate Limiting ────────────────────────────────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$lockout_key = 'login_lockout_' . md5($ip);
$attempts_key = 'login_attempts_' . md5($ip);

$is_locked_out = false;
$lockout_remaining = 0;
if (isset($_SESSION[$lockout_key]) && $_SESSION[$lockout_key] > time()) {
    $is_locked_out = true;
    $lockout_remaining = $_SESSION[$lockout_key] - time();
} else {
    unset($_SESSION[$lockout_key], $_SESSION[$attempts_key]);
}

$error = '';
$reason = $_GET['reason'] ?? '';
if ($reason === 'timeout') {
    $error = 'Your session has expired. Please log in again.';
} elseif ($reason === 'hijack') {
    $error = 'Session security violation. Please log in again.';
}

// ─── Login Form Processing ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked_out) {
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $submitted_token)) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            // Try database authentication first
            $authenticated = false;
            $user = null;

            try {
                require_once 'includes/db.php';

                // Check users table
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $verify = password_verify($password, $user['password_hash']);
                    if ($verify) {
                        $authenticated = true;
                    } elseif ($user['role'] === ROLE_STUDENT && $password === $username) {
                        $authenticated = true;
                        $upd = $pdo->prepare("UPDATE users SET must_change_password = 1 WHERE id = ?");
                        $upd->execute([$user['id']]);
                        $user['must_change_password'] = 1;
                    }
                } else {
                    // Try student_no from students table (auto-create user)
                    $stmtS = $pdo->prepare("SELECT student_no, student_name FROM students WHERE student_no = ?");
                    $stmtS->execute([$username]);
                    $student = $stmtS->fetch(PDO::FETCH_ASSOC);

                    if ($student && $password === $username) {
                        $hash = password_hash($username, PASSWORD_BCRYPT);
                        $ins = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, must_change_password) VALUES (?, ?, 'student', ?, 1)");
                        $ins->execute([$username, $hash, $student['student_name'] ?? $username]);
                        $userId = $pdo->lastInsertId();

                        $link = $pdo->prepare("UPDATE students SET user_id = ? WHERE student_no = ?");
                        $link->execute([$userId, $username]);

                        $authenticated = true;
                        $user = [
                            'id' => $userId,
                            'username' => $username,
                            'role' => 'student',
                            'full_name' => $student['student_name'] ?? $username,
                            'must_change_password' => 1,
                        ];
                    }
                }
            } catch (PDOException $e) {
                error_log('[Login] DB auth failed: ' . $e->getMessage());
            }

            if ($authenticated && $user) {
                // DESTROY old session completely — prevents role carryover
                session_unset();
                session_destroy();

                session_set_cookie_params([
                    'lifetime' => SESSION_LIFETIME,
                    'path'     => '/',
                    'secure'   => SESSION_COOKIE_SECURE,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                session_start();

                // Regenerate ID for security
                session_regenerate_id(true);

                // Set NEW session with clean role data
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'] ?? '';
                $_SESSION['must_change_password'] = (bool)($user['must_change_password'] ?? 0);
                $_SESSION['last_activity'] = time();
                $_SESSION['csrf_token'] = $csrf_token;
                $_SESSION['role_locked'] = $user['role'];

                if ($user['role'] === 'student') {
                    $_SESSION['student_no'] = $user['username'];
                } elseif ($user['role'] === 'teacher') {
                    try {
                        require_once 'includes/db.php';
                        $tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
                        $tStmt->execute([$user['id']]);
                        $tRow = $tStmt->fetch(PDO::FETCH_ASSOC);
                        if ($tRow) $_SESSION['teacher_id'] = $tRow['id'];
                    } catch (PDOException $e) {}
                }

                try {
                    require_once 'includes/db.php';
                    $upd = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $upd->execute([$user['id']]);
                } catch (PDOException $e) {}

                unset($_SESSION[$lockout_key], $_SESSION[$attempts_key]);

                $redirect = $_GET['redirect'] ?? '';
                if ($redirect) {
                    $redirect = filter_var($redirect, FILTER_SANITIZE_URL);
                    if (!preg_match('/^[a-zA-Z0-9\/\._\-?&=%]+$/', $redirect)) $redirect = '';
                }

                if (!$redirect) {
                    switch ($user['role']) {
                        case 'admin':   $redirect = 'index.php'; break;
                        case 'teacher': $redirect = 'teacher_dashboard.php'; break;
                        case 'student': $redirect = 'student_dashboard.php'; break;
                        default:        $redirect = 'index.php';
                    }
                }

                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                header('Location: ' . $redirect);
                exit;
            } else {
                $_SESSION[$attempts_key] = ($_SESSION[$attempts_key] ?? 0) + 1;
                if ($_SESSION[$attempts_key] >= MAX_LOGIN_ATTEMPTS) {
                    $_SESSION[$lockout_key] = time() + LOGIN_LOCKOUT_SECONDS;
                    $is_locked_out = true;
                    $lockout_remaining = LOGIN_LOCKOUT_SECONDS;
                    $error = 'Too many failed attempts. Locked for ' . LOGIN_LOCKOUT_SECONDS . 's.';
                } else {
                    $remaining = MAX_LOGIN_ATTEMPTS - $_SESSION[$attempts_key];
                    $error = 'Invalid username or password. ' . $remaining . ' attempt(s) remaining.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Sentinel Swarm AMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        body { background: linear-gradient(135deg, #0a0e1a 0%, #0f172a 50%, #1a1f3a 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
        .security-bg { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
        .tech-grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(99,102,241,0.06) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,0.06) 1px, transparent 1px); background-size: 50px 50px; animation: gridMove 20s linear infinite; }
        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        .circuit-line { position: absolute; background: rgba(99,102,241,0.15); }
        .circuit-line-h { height: 1px; animation: circuitPulseH 8s ease-in-out infinite; }
        .circuit-line-v { width: 1px; animation: circuitPulseV 8s ease-in-out infinite; }
        .circuit-line:nth-child(1) { top: 20%; left: 5%; width: 15%; }
        .circuit-line:nth-child(2) { top: 35%; right: 10%; width: 12%; }
        .circuit-line:nth-child(3) { bottom: 25%; left: 15%; width: 18%; }
        .circuit-line:nth-child(4) { top: 60%; right: 20%; width: 10%; }
        .circuit-line:nth-child(5) { top: 15%; left: 40%; width: 8%; }
        .circuit-line:nth-child(6) { top: 10%; left: 5%; height: 12%; }
        .circuit-line:nth-child(7) { top: 50%; right: 5%; height: 15%; }
        .circuit-line:nth-child(8) { bottom: 10%; left: 25%; height: 10%; }
        .circuit-line:nth-child(9) { top: 20%; right: 15%; height: 8%; }
        @keyframes circuitPulseH {
            0%, 100% { opacity: 0.1; box-shadow: none; }
            50% { opacity: 0.6; box-shadow: 0 0 8px rgba(99,102,241,0.4); }
        }
        @keyframes circuitPulseV {
            0%, 100% { opacity: 0.1; box-shadow: none; }
            50% { opacity: 0.6; box-shadow: 0 0 8px rgba(99,102,241,0.4); }
        }
        .circuit-node { position: absolute; width: 6px; height: 6px; border-radius: 50%; background: rgba(99,102,241,0.3); border: 1px solid rgba(99,102,241,0.5); animation: nodeGlow 4s ease-in-out infinite; }
        .circuit-node:nth-child(10) { top: 20%; left: 20%; }
        .circuit-node:nth-child(11) { top: 35%; right: 22%; animation-delay: 1s; }
        .circuit-node:nth-child(12) { bottom: 25%; left: 33%; animation-delay: 2s; }
        .circuit-node:nth-child(13) { top: 60%; right: 30%; animation-delay: 0.5s; }
        .circuit-node:nth-child(14) { top: 15%; left: 48%; animation-delay: 1.5s; }
        .circuit-node:nth-child(15) { bottom: 40%; left: 10%; animation-delay: 3s; width: 8px; height: 8px; }
        .circuit-node:nth-child(16) { top: 45%; right: 8%; animation-delay: 2.5s; }
        @keyframes nodeGlow {
            0%, 100% { transform: scale(1); box-shadow: 0 0 4px rgba(99,102,241,0.3); opacity: 0.4; }
            50% { transform: scale(1.5); box-shadow: 0 0 12px rgba(99,102,241,0.7); opacity: 1; }
        }
        .shield-icon { position: absolute; font-size: 3rem; color: rgba(16,185,129,0.08); animation: shieldFloat 12s ease-in-out infinite; }
        .shield-icon:nth-child(17) { top: 15%; left: 8%; animation-duration: 15s; font-size: 4rem; }
        .shield-icon:nth-child(18) { bottom: 20%; right: 12%; animation-duration: 18s; animation-delay: 3s; }
        .shield-icon:nth-child(19) { top: 50%; left: 75%; animation-duration: 14s; animation-delay: 6s; font-size: 2.5rem; }
        .shield-icon:nth-child(20) { top: 70%; left: 5%; animation-duration: 16s; animation-delay: 4s; font-size: 2rem; }
        .shield-icon:nth-child(21) { top: 10%; right: 30%; animation-duration: 13s; animation-delay: 2s; font-size: 2.5rem; }
        @keyframes shieldFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.06; }
            50% { transform: translateY(-20px) rotate(5deg); opacity: 0.15; }
        }
        .binary-stream { position: absolute; font-family: 'Courier New', monospace; font-size: 12px; color: rgba(99,102,241,0.1); writing-mode: vertical-rl; animation: binaryFall linear infinite; white-space: nowrap; }
        .binary-stream:nth-child(22) { left: 5%; animation-duration: 20s; }
        .binary-stream:nth-child(23) { left: 15%; animation-duration: 25s; animation-delay: 3s; }
        .binary-stream:nth-child(24) { left: 30%; animation-duration: 22s; animation-delay: 6s; }
        .binary-stream:nth-child(25) { left: 50%; animation-duration: 28s; animation-delay: 2s; }
        .binary-stream:nth-child(26) { left: 65%; animation-duration: 24s; animation-delay: 8s; }
        .binary-stream:nth-child(27) { left: 80%; animation-duration: 21s; animation-delay: 4s; }
        .binary-stream:nth-child(28) { left: 92%; animation-duration: 26s; animation-delay: 1s; }
        @keyframes binaryFall {
            0% { transform: translateY(-100%); opacity: 0; }
            10% { opacity: 0.12; }
            90% { opacity: 0.12; }
            100% { transform: translateY(100vh); opacity: 0; }
        }
        .scan-beam { position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, rgba(99,102,241,0.3), transparent); animation: scanBeam 8s ease-in-out infinite; }
        @keyframes scanBeam {
            0% { top: 0; opacity: 0; }
            10% { opacity: 1; }
            50% { top: 100%; opacity: 1; }
            90% { opacity: 1; }
            100% { top: 0; opacity: 0; }
        }
        .login-wrapper { position: relative; z-index: 1; }
        .login-card { max-width: 400px; width: 100%; background: rgba(255,255,255,0.05); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; }
        .login-icon { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #818cf8); display: flex; align-items: center; justify-content: center; margin: 0 auto; box-shadow: 0 8px 24px rgba(99,102,241,0.3); position: relative; overflow: hidden; }
        .login-icon i { font-size: 2rem; color: #fff; position: relative; z-index: 1; }
        .scan-line { position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: rgba(255,255,255,0.6); box-shadow: 0 0 10px rgba(255,255,255,0.8); border-radius: 2px; animation: scanDown 2.5s ease-in-out infinite; }
        @keyframes scanDown {
            0% { top: 0; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }
        .fingerprint-pulse { animation: fpPulse 2.5s ease-in-out infinite; }
        @keyframes fpPulse {
            0%, 100% { opacity: 0.7; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.05); }
        }
        .scan-rings { position: absolute; width: 100%; height: 100%; border-radius: 50%; border: 2px solid rgba(255,255,255,0.2); animation: ringExpand 2.5s ease-out infinite; }
        @keyframes ringExpand {
            0% { transform: scale(1); opacity: 0.6; }
            100% { transform: scale(1.8); opacity: 0; }
        }
        .scan-rings:nth-child(2) { animation-delay: 0.8s; }
        .scan-rings:nth-child(3) { animation-delay: 1.6s; }
        .login-card .form-control { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 12px; padding: 0.65rem 1rem; font-size: 0.95rem; }
        .login-card .form-control:focus { border-color: #6366f1; box-shadow: 0 0 0 0.2rem rgba(99,102,241,0.25); background: rgba(255,255,255,0.12); }
        .login-card .form-control::placeholder { color: rgba(255,255,255,0.4); }
        .login-card label { color: rgba(255,255,255,0.7); font-size: 0.85rem; }
        .login-card .input-group-text { background: transparent; border: 1px solid rgba(255,255,255,0.15); border-right: none; color: rgba(255,255,255,0.5); border-radius: 12px 0 0 12px; }
        .login-card .input-group .form-control { border-left: none; border-radius: 0 12px 12px 0; }
        .btn-login { background: linear-gradient(135deg, #6366f1, #818cf8); border: none; border-radius: 12px; padding: 0.75rem; font-size: 1rem; }
        .btn-login:hover { background: linear-gradient(135deg, #4f46e5, #6366f1); }
        .btn-login:disabled { opacity: 0.5; }
        .login-brand { color: #fff; }
        .login-divider { height: 1px; background: rgba(255,255,255,0.1); }
        #mouse-canvas { position: absolute; inset: 0; z-index: 0; pointer-events: none; }
        .interactive-layer { position: absolute; inset: 0; pointer-events: none; }
        .circuit-node { position: absolute; width: 6px; height: 6px; border-radius: 50%; background: rgba(99,102,241,0.3); border: 1px solid rgba(99,102,241,0.5); animation: nodeGlow 4s ease-in-out infinite; transition: all 0.3s ease-out; }
        .circuit-node.target { cursor: pointer; pointer-events: auto; border-color: rgba(239,68,68,0.8); background: rgba(239,68,68,0.4); box-shadow: 0 0 8px rgba(239,68,68,0.5); }
        .circuit-line { position: absolute; background: rgba(99,102,241,0.15); transition: all 0.3s ease-out; }
        .shield-icon.target { cursor: pointer; pointer-events: auto; color: rgba(239,68,68,0.15) !important; }
        .binary-stream.target { cursor: pointer; pointer-events: auto; }
        #score-badge { position: absolute; top: 20px; right: 20px; z-index: 2; background: rgba(99,102,241,0.2); backdrop-filter: blur(8px); border: 1px solid rgba(99,102,241,0.3); border-radius: 12px; padding: 8px 16px; color: #fff; font-family: 'Courier New', monospace; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        #score-badge .score-val { font-weight: bold; color: #818cf8; font-size: 18px; }
        .hit-flash { position: fixed; inset: 0; background: rgba(239,68,68,0.08); pointer-events: none; z-index: 1; animation: flashBang 0.15s ease-out forwards; }
        @keyframes flashBang { 0% { opacity: 1; } 100% { opacity: 0; } }
        #boom-count { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 2; color: rgba(255,255,255,0.3); font-size: 12px; }
    </style>
</head>
<body>
<canvas id="mouse-canvas"></canvas>
<div id="score-badge"><i class="bi bi-crosshair"></i> SCORE: <span class="score-val" id="score-val">0</span></div>
<div id="boom-count"><i class="bi bi-info-circle me-1"></i>Click anywhere for sparks • Find hidden targets for BOOM!</div>
<div class="security-bg interactive-layer" id="security-bg">
    <div class="tech-grid"></div>
    <div class="circuit-line circuit-line-h"></div>
    <div class="circuit-line circuit-line-h"></div>
    <div class="circuit-line circuit-line-h"></div>
    <div class="circuit-line circuit-line-h"></div>
    <div class="circuit-line circuit-line-h"></div>
    <div class="circuit-line circuit-line-v"></div>
    <div class="circuit-line circuit-line-v"></div>
    <div class="circuit-line circuit-line-v"></div>
    <div class="circuit-line circuit-line-v"></div>
    <div class="circuit-node"></div>
    <div class="circuit-node target"></div>
    <div class="circuit-node"></div>
    <div class="circuit-node target"></div>
    <div class="circuit-node"></div>
    <div class="circuit-node target"></div>
    <div class="circuit-node"></div>
    <i class="bi bi-shield-check shield-icon target"></i>
    <i class="bi bi-shield-lock shield-icon"></i>
    <i class="bi bi-shield-shaded shield-icon"></i>
    <i class="bi bi-fingerprint shield-icon target"></i>
    <i class="bi bi-lock shield-icon"></i>
    <div class="binary-stream">01101001 01100100 01100101</div>
    <div class="binary-stream target">10110010 01110011 10001010</div>
    <div class="binary-stream">01001011 10110001 00101011</div>
    <div class="binary-stream target">11001100 01010100 01001011</div>
    <div class="binary-stream">00100101 10111100 10001000</div>
    <div class="binary-stream target">01001101 10101011 00100101</div>
    <div class="binary-stream">10011010 01011000 00111100</div>
    <div class="scan-beam"></div>
</div>
<div class="login-wrapper">
<div class="login-card p-4 shadow-lg">
    <div class="text-center mb-4">
        <div class="login-icon mb-3">
            <div class="scan-rings"></div>
            <div class="scan-rings"></div>
            <div class="scan-rings"></div>
            <div class="scan-line"></div>
            <i class="bi bi-fingerprint fingerprint-pulse"></i>
        </div>
        <h4 class="login-brand fw-bold mb-1">Sentinel Swarm</h4>
        <p class="text-white-50 small mb-0">Attendance Management System</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show small py-2 rounded-3" style="background:rgba(220,53,69,0.15);border:1px solid rgba(220,53,69,0.3);color:#fca5a5;">
        <i class="bi bi-exclamation-triangle me-1"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" style="filter:invert(1);"></button>
    </div>
    <?php endif; ?>

    <?php if ($is_locked_out): ?>
    <div class="alert alert-warning small py-2 text-center rounded-3" style="background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);color:#fcd34d;">
        <i class="bi bi-hourglass-split me-1"></i>Locked out. Please wait <strong id="lockout-timer"><?php echo $lockout_remaining; ?></strong>s
    </div>
    <script>
    (function(){var s=<?php echo $lockout_remaining; ?>;var e=document.getElementById('lockout-timer');
    var i=setInterval(function(){s--;if(e)e.textContent=s;if(s<=0){clearInterval(i);location.reload();}},1000);})();
    </script>
    <?php endif; ?>

    <form method="POST" id="login-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="mb-3">
            <label class="form-label fw-semibold">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control" placeholder="Enter your username" required autocomplete="username">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-login w-100 fw-bold" <?php echo $is_locked_out ? 'disabled' : ''; ?>>
            <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
        </button>
    </form>

    <div class="login-divider my-3"></div>
    <div class="text-center">
        <p class="text-white-50 small mb-0"><i class="bi bi-shield-lock me-1"></i>Secure authentication via database — contact your administrator for access</p>
        <p class="text-white-50 small mb-0 mt-1">Student: your student number is both username &amp; password</p>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const canvas=document.getElementById('mouse-canvas');
    const ctx=canvas.getContext('2d');
    let W=0,H=0;
    let particles=[];
    let scorePopups=[];
    let score=0;
    let shockwaves=[];
    let mx=-100,my=-100;

    function resize(){
        W=canvas.width=window.innerWidth;
        H=canvas.height=window.innerHeight;
    }
    resize();
    window.addEventListener('resize',resize);

    document.addEventListener('mousemove',function(e){
        mx=e.clientX;
        my=e.clientY;
    });

    function spawnSparks(x,y,count,isBig){
        for(var i=0;i<count;i++){
            var angle=Math.random()*Math.PI*2;
            var speed=isBig?(2+Math.random()*8):(1+Math.random()*3);
            var size=isBig?(2+Math.random()*4):(1+Math.random()*2);
            var colors=isBig?['#ef4444','#f97316','#eab308','#fff','#6366f1']:['#6366f1','#818cf8','#a78bfa','#fff'];
            particles.push({
                x:x,y:y,
                vx:Math.cos(angle)*speed,
                vy:Math.sin(angle)*speed,
                size:size,
                life:1,
                decay:0.01+Math.random()*0.02,
                color:colors[Math.floor(Math.random()*colors.length)],
                gravity:isBig?0.1:0.05,
                trail:[]
            });
        }
    }

    function addShockwave(x,y,maxR,color){
        shockwaves.push({x:x,y:y,r:0,maxR:maxR||150,opacity:0.8,color:color||'rgba(239,68,68,'});
    }

    function addScorePopup(x,y,pts,text){
        score+=pts;
        document.getElementById('score-val').textContent=score;
        scorePopups.push({
            x:x,y:y,text:(pts>10?'BOOM! ':'')+(pts>0?'+'+pts:text),
            life:1,vy:-2,color:pts>10?'#ef4444':pts>0?'#22c55e':'#818cf8',size:pts>10?24:16
        });
    }

    function bigBoom(x,y){
        spawnSparks(x,y,60,true);
        addShockwave(x,y,200,'rgba(239,68,68,');
        addShockwave(x,y,150,'rgba(249,115,22,');
        addShockwave(x,y,100,'rgba(255,255,255,');
        var flash=document.createElement('div');
        flash.className='hit-flash';
        document.body.appendChild(flash);
        setTimeout(function(){flash.remove();},200);
    }

    function canvasClick(x,y,isTarget,element){
        if(isTarget){
            bigBoom(x,y);
            addScorePopup(x,y,50,'');
            element.style.opacity='0.2';
            element.style.pointerEvents='none';
            element.style.transform='scale(0)';
            setTimeout(function(){
                element.style.transform='scale(1)';
                element.style.opacity='';
                element.style.pointerEvents='auto';
            },3000);
        } else {
            spawnSparks(x,y,15,false);
            addShockwave(x,y,80,'rgba(99,102,241,');
            addScorePopup(x,y,1,'');
        }
    }

    document.addEventListener('click',function(e){
        if(e.target.closest('.login-card')||e.target.closest('#score-badge'))return;
        canvasClick(e.clientX,e.clientY,false,null);
    });

    document.querySelectorAll('.target').forEach(function(el){
        el.addEventListener('click',function(e){
            e.stopPropagation();
            var rect=el.getBoundingClientRect();
            var cx=rect.left+rect.width/2;
            var cy=rect.top+rect.height/2;
            canvasClick(cx,cy,true,el);
        });
    });

    function animate(){
        ctx.clearRect(0,0,W,H);

        var g=ctx.createRadialGradient(mx,my,0,mx,my,100);
        g.addColorStop(0,'rgba(99,102,241,0.12)');
        g.addColorStop(0.5,'rgba(99,102,241,0.04)');
        g.addColorStop(1,'rgba(99,102,241,0)');
        ctx.fillStyle=g;
        ctx.beginPath();
        ctx.arc(mx,my,100,0,Math.PI*2);
        ctx.fill();

        for(var i=particles.length-1;i>=0;i--){
            var p=particles[i];
            p.trail.push({x:p.x,y:p.y,life:p.life});
            if(p.trail.length>8)p.trail.shift();
            p.x+=p.vx;
            p.y+=p.vy;
            p.vy+=p.gravity;
            p.vx*=0.98;
            p.life-=p.decay;

            for(var t=0;t<p.trail.length;t++){
                var tr=p.trail[t];
                ctx.beginPath();
                ctx.arc(tr.x,tr.y,p.size*0.5*(tr.life/p.life),0,Math.PI*2);
                ctx.fillStyle=p.color;
                ctx.globalAlpha=tr.life*0.3;
                ctx.fill();
                ctx.globalAlpha=1;
            }

            ctx.beginPath();
            ctx.arc(p.x,p.y,p.size*p.life,0,Math.PI*2);
            ctx.fillStyle=p.color;
            ctx.globalAlpha=p.life;
            ctx.fill();
            ctx.globalAlpha=1;

            if(p.life<=0)particles.splice(i,1);
        }

        for(var i=shockwaves.length-1;i>=0;i--){
            var sw=shockwaves[i];
            sw.r+=6;
            sw.opacity*=0.92;
            ctx.beginPath();
            ctx.arc(sw.x,sw.y,sw.r,0,Math.PI*2);
            ctx.strokeStyle=sw.color+sw.opacity+')';
            ctx.lineWidth=3;
            ctx.stroke();
            if(sw.opacity<0.02)shockwaves.splice(i,1);
        }

        for(var i=scorePopups.length-1;i>=0;i--){
            var sp=scorePopups[i];
            sp.y+=sp.vy;
            sp.life-=0.015;
            ctx.font='bold '+sp.size+'px Courier New';
            ctx.fillStyle=sp.color;
            ctx.globalAlpha=sp.life;
            ctx.textAlign='center';
            ctx.fillText(sp.text,sp.x,sp.y);
            ctx.globalAlpha=1;
            ctx.textAlign='start';
            if(sp.life<=0)scorePopups.splice(i,1);
        }

        requestAnimationFrame(animate);
    }
    animate();
})();
</script>
</body>
</html>
