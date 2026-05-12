<?php
/**
 * Teacher Management — Sentinel Swarm AMS v3
 * Admin-only page to create/manage teachers and assign them to courses.
 */
require_once 'includes/auth.php';
require_once 'includes/db.php';

requireRole('admin');

// Handle form submissions
$msg = ''; $msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $msg = 'Security error.'; $msgType = 'danger';
    } else {
        // Add teacher
        if (isset($_POST['add_teacher'])) {
            $name = trim($_POST['teacher_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $department = trim($_POST['department'] ?? '');

            if (empty($name) || empty($email) || empty($password)) {
                $msg = 'Name, email, and password are required.'; $msgType = 'danger';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $msg = 'Invalid email format.'; $msgType = 'danger';
            } else {
                try {
                    $pdo->beginTransaction();
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, email, full_name) VALUES (?, ?, 'teacher', ?, ?)");
                    $stmt->execute([$email, $hash, $email, $name]);
                    $userId = $pdo->lastInsertId();
                    $stmt = $pdo->prepare("INSERT INTO teachers (user_id, teacher_name, department) VALUES (?, ?, ?)");
                    $stmt->execute([$userId, $name, $department]);
                    $pdo->commit();
                    $msg = "Teacher '{$name}' added successfully."; $msgType = 'success';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $msg = 'Error: ' . ($e->getCode() == 23000 ? 'Email already exists.' : $e->getMessage());
                    $msgType = 'danger';
                }
            }
        }
        // Assign teacher to course
        elseif (isset($_POST['assign_teacher'])) {
            $teacherId = (int)($_POST['teacher_id'] ?? 0);
            $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));
            if ($teacherId > 0 && !empty($courseCode)) {
                try {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO teacher_courses (teacher_id, course_code) VALUES (?, ?)");
                    $stmt->execute([$teacherId, $courseCode]);
                    $msg = "Teacher assigned to {$courseCode}."; $msgType = 'success';
                } catch (PDOException $e) {
                    $msg = 'Error assigning teacher.'; $msgType = 'danger';
                }
            }
        }
        // Remove teacher from course
        elseif (isset($_POST['remove_teacher'])) {
            $tcId = (int)($_POST['tc_id'] ?? 0);
            if ($tcId > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM teacher_courses WHERE id = ?");
                    $stmt->execute([$tcId]);
                    $msg = 'Teacher removed from course.'; $msgType = 'success';
                } catch (PDOException $e) {
                    $msg = 'Error removing teacher.'; $msgType = 'danger';
                }
            }
        }
        // Delete teacher
        elseif (isset($_POST['delete_teacher'])) {
            $teacherId = (int)($_POST['teacher_id'] ?? 0);
            if ($teacherId > 0) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
                    $stmt->execute([$teacherId]);
                    $t = $stmt->fetch(PDO::FETCH_ASSOC);
                    $pdo->prepare("DELETE FROM teacher_courses WHERE teacher_id = ?")->execute([$teacherId]);
                    $pdo->prepare("DELETE FROM teachers WHERE id = ?")->execute([$teacherId]);
                    if ($t) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$t['user_id']]);
                    $pdo->commit();
                    $msg = 'Teacher deleted.'; $msgType = 'success';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $msg = 'Error deleting teacher.'; $msgType = 'danger';
                }
            }
        }
        // Change teacher password
        elseif (isset($_POST['change_teacher_pw'])) {
            $teacherId = (int)($_POST['teacher_id'] ?? 0);
            $newPw = trim($_POST['new_password'] ?? '');
            if ($teacherId > 0 && !empty($newPw)) {
                try {
                    $hash = password_hash($newPw, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = (SELECT user_id FROM teachers WHERE id = ?)");
                    $stmt->execute([$hash, $teacherId]);
                    $msg = 'Password updated.'; $msgType = 'success';
                } catch (PDOException $e) {
                    $msg = 'Error updating password.'; $msgType = 'danger';
                }
            }
        }
    }
}

// Fetch all teachers with their courses
$teachers = [];
try {
    $stmt = $pdo->query(
        "SELECT t.id, t.teacher_name, t.department, u.email, u.is_active, u.last_login,
                GROUP_CONCAT(c.course_code ORDER BY c.course_code SEPARATOR ', ') as courses
         FROM teachers t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN teacher_courses tc ON t.id = tc.teacher_id
         LEFT JOIN courses c ON tc.course_code = c.course_code
         GROUP BY t.id, t.teacher_name, t.department, u.email, u.is_active, u.last_login
         ORDER BY t.teacher_name ASC"
    );
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch all courses for assignment dropdown
$allCourses = [];
try {
    $stmt = $pdo->query("SELECT course_code, course_name FROM courses ORDER BY course_code ASC");
    $allCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$csrf_token = $_SESSION['csrf_token'] ?? '';
?>
<?php require_once 'includes/header_admin.php'; ?>
<style>.teacher-card { border-left: 4px solid #0d6efd; }</style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-person-workspace me-2"></i>Teacher Management</h4>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                <i class="bi bi-person-plus me-1"></i>Add Teacher
            </button>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Teachers List -->
    <?php if (empty($teachers)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-people display-4 d-block mb-3"></i>
                <p class="mb-0">No teachers registered yet. Click "Add Teacher" to create the first one.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($teachers as $t): ?>
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 teacher-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold mb-0 text-primary"><?php echo htmlspecialchars($t['teacher_name']); ?></h6>
                                    <div class="small text-muted"><?php echo htmlspecialchars($t['email']); ?></div>
                                </div>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#pwModal<?php echo $t['id']; ?>" title="Reset Password" aria-label="Reset Password">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this teacher?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" name="delete_teacher" class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete Teacher">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php if ($t['department']): ?>
                                <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($t['department']); ?></span>
                            <?php endif; ?>
                            <?php if ($t['last_login']): ?>
                                <div class="small text-muted mb-2"><i class="bi bi-clock me-1"></i>Last login: <?php echo date('M j, Y h:i A', strtotime($t['last_login'])); ?></div>
                            <?php endif; ?>

                            <!-- Assigned Courses -->
                            <div class="mt-2">
                                <strong class="small text-muted">Courses:</strong>
                                <?php if ($t['courses']): ?>
                                    <?php foreach (explode(', ', $t['courses']) as $cc): ?>
                                        <span class="badge bg-primary me-1 mb-1"><?php echo htmlspecialchars($cc); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="small text-muted fst-italic">None assigned</span>
                                <?php endif; ?>
                            </div>

                            <!-- Assign Course Form -->
                            <form method="POST" class="mt-2 d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                                <select name="course_code" class="form-select form-select-sm" required>
                                    <option value="">Assign course...</option>
                                    <?php foreach ($allCourses as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?> - <?php echo htmlspecialchars($c['course_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="assign_teacher" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i></button>
                            </form>

                            <!-- Remove from course -->
                            <?php if ($t['courses']): ?>
                                <form method="POST" class="mt-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <select name="tc_id" class="form-select form-select-sm">
                                        <option value="">Remove from course...</option>
                                        <?php
                                        // Fetch tc IDs for this teacher
                                        try {
                                            $stmt2 = $pdo->prepare("SELECT tc.id, tc.course_code FROM teacher_courses tc WHERE tc.teacher_id = ? ORDER BY tc.course_code");
                                            $stmt2->execute([$t['id']]);
                                            while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                                                echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['course_code']) . '</option>';
                                            }
                                        } catch (PDOException $e) {}
                                        ?>
                                    </select>
                                    <button type="submit" name="remove_teacher" class="btn btn-sm btn-outline-danger btn-sm mt-1">Remove</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Password Reset Modal -->
                <div class="modal fade" id="pwModal<?php echo $t['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-sm modal-dialog-centered">
                        <form method="POST" class="modal-content">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                            <div class="modal-header py-2">
                                <h6 class="modal-title">Reset Password</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="password" name="new_password" class="form-control" placeholder="New password" minlength="6" required>
                            </div>
                            <div class="modal-footer py-2">
                                <button type="submit" name="change_teacher_pw" class="btn btn-sm btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Full Name</label>
                    <input type="text" name="teacher_name" class="form-control" placeholder="Dr. John Smith" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Email (Login Username)</label>
                    <input type="email" name="email" class="form-control" placeholder="john@university.edu" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Password</label>
                    <input type="password" name="password" class="form-control" minlength="6" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Department (Optional)</label>
                    <input type="text" name="department" class="form-control" placeholder="Computer Science">
                </div>
                <div class="alert alert-info small py-2">
                    <i class="bi bi-info-circle me-1"></i> Teacher will log in using their email and this password.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_teacher" class="btn btn-primary">Add Teacher</button>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
