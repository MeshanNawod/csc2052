<?php
/**
 * Student Header — Sentinel Swarm AMS v3
 * Role-isolated navigation for student pages only.
 * NOTE: This outputs ONLY the navbar + container opening. The calling page must have its own <!DOCTYPE html>, <head>, <body>.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// ─── ROLE LOCK: Prevent student session from being hijacked ──────────
if (isset($_SESSION['role_locked']) && $_SESSION['role_locked'] === 'student' && !isStudent()) {
    session_unset(); session_destroy();
    header('Location: /csc2052/login.php?reason=hijack'); exit;
}
if (isStudent() && !isset($_SESSION['role_locked'])) {
    $_SESSION['role_locked'] = 'student';
}

$page = basename($_SERVER['PHP_SELF']);
?>

<div class="floating-navbar-wrapper">
    <nav class="navbar navbar-expand-lg premium-navbar">
        <div class="container-fluid px-3">
            <a class="navbar-brand mb-0 h1 d-flex align-items-center" href="student_dashboard.php">
                <i class="bi bi-mortarboard-fill me-2 text-gradient-premium"></i>
                <span class="fw-bolder text-dark fs-4">Student Portal</span>
            </a>
            <button class="navbar-toggler border-0 shadow-none px-2" type="button" data-bs-toggle="collapse" data-bs-target="#navBar">
                <i class="bi bi-list fs-2 text-dark opacity-75"></i>
            </button>
            <div class="collapse navbar-collapse" id="navBar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='student_dashboard.php'?'active':''; ?>" href="student_dashboard.php"><i class="bi bi-display me-1"></i>Dashboard</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small"><i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($_SESSION['username']??'Student'); ?></span>
                    <a href="logout.php" class="btn btn-sm btn-danger px-3 py-2 fw-bold">Exit</a>
                </div>
            </div>
        </div>
    </nav>
</div>

<div class="container pb-5 mt-3">
<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']??'', ENT_QUOTES, 'UTF-8'); ?>">
