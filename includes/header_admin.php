<?php
/**
 * Admin Header — Sentinel Swarm AMS v3
 * Role-isolated navigation for admin pages only.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// ─── ROLE LOCK: Prevent admin session from being hijacked ──────────
if (isset($_SESSION['role_locked']) && $_SESSION['role_locked'] === 'admin' && !isAdmin()) {
    session_unset(); session_destroy();
    header('Location: /csc2052/login.php?reason=hijack'); exit;
}
if (isAdmin() && !isset($_SESSION['role_locked'])) {
    $_SESSION['role_locked'] = 'admin';
}

$is_esp = false; $is_rpi = false;
$status_file_esp = __DIR__ . '/../sys_status_esp32.txt';
$status_file_rpi = __DIR__ . '/../sys_status_rpi.txt';
if (file_exists($status_file_esp)) { $last = (int)file_get_contents($status_file_esp); if ((time()-$last)<=30) $is_esp = true; }
if (file_exists($status_file_rpi)) { $last = (int)file_get_contents($status_file_rpi); if ((time()-$last)<=30) $is_rpi = true; }

$page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UoP AMS | Sentinel Swarm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>

<div class="floating-navbar-wrapper">
    <nav class="navbar navbar-expand-lg premium-navbar">
        <div class="container-fluid px-3">
            <a class="navbar-brand mb-0 h1 d-flex align-items-center" href="index.php">
                <i class="bi bi-fingerprint me-2 text-gradient-premium nav-logo-icon"></i>
                <span class="fw-bolder text-dark fs-4 nav-brand-text">Sentinel Swarm</span>
            </a>
            <button class="navbar-toggler border-0 shadow-none px-2" type="button" data-bs-toggle="collapse" data-bs-target="#navBar">
                <i class="bi bi-list fs-2 text-dark opacity-75"></i>
            </button>
            <div class="collapse navbar-collapse" id="navBar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='index.php'?'active':''; ?>" href="index.php"><i class="bi bi-display me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='students.php'?'active':''; ?>" href="students.php"><i class="bi bi-people me-1"></i>Students Base</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='teachers.php'?'active':''; ?>" href="teachers.php"><i class="bi bi-person-workspace me-1"></i>Teachers</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='lecture.php'?'active':''; ?>" href="lecture.php"><i class="bi bi-journal-album me-1"></i>Lectures & Export</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='device.php'?'active':''; ?>" href="device.php"><i class="bi bi-motherboard me-1"></i>Hardware Nodes</a></li>
                    <li class="nav-item ms-lg-2"><a class="nav-link nav-link-premium <?php echo $page=='instructions.php'?'active':''; ?>" href="instructions.php"><i class="bi bi-info-circle me-1"></i>Help</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3 mt-3 mt-lg-0">
                    <div class="input-group node-ip-group">
                        <span class="input-group-text bg-transparent border-0 text-secondary pe-1"><i class="bi bi-hdd-network"></i></span>
                        <input type="text" id="esp-ip" class="node-ip-input" placeholder="Node IP">
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="hw-pill <?php echo $is_esp?'hw-online':'hw-offline'; ?>">
                            <span class="hw-dot <?php echo $is_esp?'hw-dot-on':''; ?>"></span>
                            <?php echo $is_esp?'ESP32 ONLINE':'ESP32 OFFLINE'; ?>
                        </div>
                        <div class="hw-pill <?php echo $is_rpi?'hw-online':'hw-offline'; ?>">
                            <span class="hw-dot <?php echo $is_rpi?'hw-dot-on':''; ?>"></span>
                            <?php echo $is_rpi?'RPi ONLINE':'RPi OFFLINE'; ?>
                        </div>
                    </div>
                    <a href="logout.php" class="btn btn-sm btn-danger btn-exit-nav px-3 py-2 fw-bold text-uppercase shadow-sm">Exit</a>
                </div>
            </div>
        </div>
    </nav>
</div>

<div class="container pb-5 mt-3">
<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']??'', ENT_QUOTES, 'UTF-8'); ?>">
