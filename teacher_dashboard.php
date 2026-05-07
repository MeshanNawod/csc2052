<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

if (!isTeacher()) {
    if (isAdmin()) { header('Location: index.php'); exit; }
    if (isStudent()) { header('Location: student_dashboard.php'); exit; }
    header('Location: login.php'); exit;
}

$teacherId = $_SESSION['teacher_id'] ?? null;
$teacherName = $_SESSION['full_name'] ?? 'Teacher';
$teacherEmail = '';
$department = '';
$profilePhoto = '';
$phone = '';
$initials = '';

$myCourses = [];
$discoveredDevices = [];

try {
    if ($teacherId) {
        $p = $pdo->prepare("SELECT t.teacher_name, t.department, t.phone, t.profile_photo, u.email FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
        $p->execute([$teacherId]);
        $tInfo = $p->fetch(PDO::FETCH_ASSOC);
        if ($tInfo) {
            $teacherName = $tInfo['teacher_name'];
            $department = $tInfo['department'] ?? '';
            $teacherEmail = $tInfo['email'] ?? '';
            $phone = $tInfo['phone'] ?? '';
            $initials = getInitials($teacherName);
            if (!empty($tInfo['profile_photo']) && file_exists(__DIR__ . '/' . $tInfo['profile_photo'])) {
                $profilePhoto = '/' . $tInfo['profile_photo'];
            }
        }

        $q = $pdo->prepare("SELECT c.course_code, c.course_name FROM courses c JOIN teacher_courses tc ON c.course_code = tc.course_code WHERE tc.teacher_id = ? ORDER BY c.course_code");
        $q->execute([$teacherId]);
        $myCourses = $q->fetchAll(PDO::FETCH_ASSOC);
    }

    $registry_file = __DIR__ . '/devices_registry.json';
    if (file_exists($registry_file)) {
        $registry = json_decode(file_get_contents($registry_file), true) ?: [];
        $now = time();
        foreach ($registry as $ip => $info) {
            if (empty($info['blocked'])) {
                $online = ($now - (int)$info['last_seen']) <= 30;
                $discoveredDevices[] = ['ip' => $ip, 'name' => $info['name'] ?? 'Unknown Node', 'online' => $online];
            }
        }
    }
} catch (PDOException $e) {
    $myCourses = [];
    $discoveredDevices = [];
    $initials = getInitials($teacherName);
}

$csrf = $_SESSION['csrf_token'] ?? '';
$courseData = json_encode($myCourses);
$deviceOptions = '<option value="WEB_DASHBOARD">Web Dashboard</option>';
foreach ($discoveredDevices as $d) {
    $deviceOptions .= '<option value="'.htmlspecialchars($d['name']).'">'.htmlspecialchars($d['name']).($d['online']?'':' (Offline)').'</option>';
}
$devicesJson = json_encode(array_merge([['name'=>'WEB_DASHBOARD','online'=>true]], $discoveredDevices));
$avatarHtml = $profilePhoto
    ? '<img id="profile-img" src="'.htmlspecialchars($profilePhoto).'" alt="Photo">'
    : '<div id="profile-img" class="initials-avatar">'.htmlspecialchars($initials ?: '?').'</div>';
$editAvatarHtml = $profilePhoto
    ? '<img id="edit-photo-preview" src="'.htmlspecialchars($profilePhoto).'" alt="Preview">'
    : '<div id="edit-photo-preview" class="initials-avatar">'.htmlspecialchars($initials ?: '?').'</div>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Portal — Sentinel Swarm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        body { background: #f8fafc; }
        .teacher-hero { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%); border-radius: 20px !important; color: #fff; position: relative; overflow: hidden; }
        .teacher-hero::before { content: ''; position: absolute; top: -50%; right: -20%; width: 400px; height: 400px; background: rgba(255,255,255,0.06); border-radius: 50% !important; }
        .teacher-hero::after { content: ''; position: absolute; bottom: -30%; left: -10%; width: 300px; height: 300px; background: rgba(255,255,255,0.04); border-radius: 50% !important; }
        .avatar-ring { width: 80px; height: 80px; border-radius: 50% !important; overflow: hidden !important; padding: 3px; background: rgba(255,255,255,0.3); position: relative; cursor: pointer; }
        .avatar-ring img { width: 100%; height: 100%; border-radius: 50% !important; object-fit: cover; border: 2px solid #fff; }
        .avatar-ring .initials-avatar { width: 100%; height: 100%; border-radius: 50% !important; background: rgba(255,255,255,0.9); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 800; color: #1e40af; border: 2px solid #fff; }
        .avatar-ring .cam-overlay { position: absolute; bottom: 0; right: 0; width: 26px; height: 26px; background: #fff; border-radius: 50% !important; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
        .avatar-ring .cam-overlay i { font-size: 12px; color: #1e40af; }
        .stat-card { border-radius: 16px !important; overflow: hidden !important; border: 1px solid #e2e8f0; background: #fff; transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .stat-select { border-radius: 50px !important; }
        .ls-wide { letter-spacing: 0.04em; }
        .start-hero { border-radius: 16px !important; overflow: hidden !important; border: 2px solid #e2e8f0; background: #fff; }
        .start-hero:focus-within { border-color: #3b82f6; box-shadow: 0 4px 16px rgba(59,130,246,0.1); }
        .log-row { border-bottom: 1px solid #f1f5f9; padding: 0.6rem 0; }
        .log-row:last-child { border-bottom: none; }
        .course-item { border-radius: 14px !important; overflow: hidden !important; border: 1px solid #e2e8f0; background: #fff; }
        .course-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .course-item .progress { border-radius: 50px !important; overflow: hidden; }
        .course-item .progress-bar { border-radius: 50px !important; }
        .nav-tabs .nav-link { border: none; color: #64748b; font-weight: 600; padding: 0.75rem 1.25rem; }
        .nav-tabs .nav-link.active { color: #1e40af; border-bottom: 3px solid #1e40af; background: transparent; }
        .badge-pill { border-radius: 50px !important; }
        #ai-assistant-container { display: none !important; }
    </style>
</head>
<body>
<?php require 'includes/header_teacher.php'; ?>

<div class="container pb-5 mt-3">

<div class="teacher-hero p-4 mb-4">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-auto">
            <div class="avatar-ring" onclick="document.getElementById('photo-upload').click()" title="Upload photo">
                <?php echo $avatarHtml; ?>
                <div class="cam-overlay"><i class="bi bi-camera-fill"></i></div>
            </div>
            <input type="file" id="photo-upload" class="d-none" accept="image/*" onchange="uploadPhoto(this)">
        </div>
        <div class="col">
            <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($teacherName); ?></h3>
            <p class="mb-0 opacity-75"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($teacherEmail); ?>
            <?php if ($department): ?> &bull; <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($department); ?><?php endif; ?>
            <?php if ($phone): ?> &bull; <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($phone); ?><?php endif; ?></p>
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-light rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil me-1"></i>Edit Profile</button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3"><i class="bi bi-journal-bookmark text-primary fs-4"></i></div>
                <div>
                    <div class="text-muted small text-uppercase fw-semibold ls-wide">My Courses</div>
                    <div class="display-6 fw-bold mb-0"><?php echo count($myCourses); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 p-3"><i class="bi bi-people text-success fs-4"></i></div>
                <div>
                    <div class="text-muted small text-uppercase fw-semibold ls-wide">Total Students</div>
                    <div class="display-6 fw-bold mb-0" id="stat-students">—</div>
                </div>
            </div>
            <select id="filter-student-course" class="form-select form-select-sm mt-2 stat-select border-secondary" onchange="refreshStats()">
                <option value="">All Courses</option>
                <?php foreach ($myCourses as $c): ?>
                <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info bg-opacity-10 p-3"><i class="bi bi-calendar-check text-info fs-4"></i></div>
                <div>
                    <div class="text-muted small text-uppercase fw-semibold ls-wide">Today's Scans</div>
                    <div class="display-6 fw-bold mb-0" id="stat-today">—</div>
                </div>
            </div>
            <select id="filter-today-course" class="form-select form-select-sm mt-2 stat-select border-secondary" onchange="refreshStats()">
                <option value="">All Courses</option>
                <?php foreach ($myCourses as $c): ?>
                <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<div class="text-center mb-4">
    <div class="start-hero p-4 d-inline-block" style="min-width: 500px; max-width: 700px; width: 100%;">
        <h5 class="fw-bold text-primary mb-3"><i class="bi bi-broadcast me-2"></i>Start Lecture Session</h5>
        <div class="row g-2 align-items-center justify-content-center">
            <div class="col-sm-4">
                <label class="form-label small fw-semibold text-muted mb-1">Course</label>
                <select id="start-course" class="form-select form-select">
                    <option value="">Select course...</option>
                    <?php foreach ($myCourses as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?> — <?php echo htmlspecialchars($c['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold text-muted mb-1">Device</label>
                <select id="start-device" class="form-select form-select">
                    <?php echo $deviceOptions; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small fw-semibold text-muted mb-1">Timer</label>
                <select id="start-timer" class="form-select form-select" onchange="toggleCustomTimer()">
                    <option value="0">No Timer</option>
                    <option value="15">15 min</option>
                    <option value="30" selected>30 min</option>
                    <option value="45">45 min</option>
                    <option value="60">60 min</option>
                    <option value="90">90 min</option>
                    <option value="custom">Custom...</option>
                </select>
                <input type="number" id="start-timer-custom" class="form-control form-control-sm mt-1" placeholder="Minutes" min="1" max="480" style="display:none;">
            </div>
            <div class="col-sm-3 d-flex align-items-end gap-1">
                <button class="btn btn-success fw-bold w-100 py-2" id="btn-start" onclick="startLecture()"><i class="bi bi-play-fill me-1"></i>Start</button>
                <button class="btn btn-danger fw-bold py-2 px-3" id="btn-end" onclick="endLecture()" style="display:none;"><i class="bi bi-stop-fill"></i></button>
            </div>
        </div>
        <div class="form-check form-switch mt-2 justify-content-center d-flex">
            <input class="form-check-input" type="checkbox" id="auto-start-check">
            <label class="form-check-label small text-muted" for="auto-start-check">Auto-start on schedule</label>
        </div>
        <div id="start-msg" class="mt-3 small"></div>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="teacherTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-logs"><i class="bi bi-clock-history me-1"></i>Attendance Logs</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-absent"><i class="bi bi-person-x me-1 text-danger"></i>Absent Students</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-courses"><i class="bi bi-book me-1"></i>My Courses</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-enroll"><i class="bi bi-person-plus me-1"></i>Enroll Students</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attendance"><i class="bi bi-bar-chart me-1"></i>Attendance %</button></li>
</ul>

<div class="row g-3 mb-4" id="upcoming-section">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <strong class="small"><i class="bi bi-calendar-event me-1"></i>Today's Scheduled Courses</strong>
                <span class="badge bg-primary" id="upcoming-count">0</span>
            </div>
            <div class="card-body p-0" style="max-height:220px;overflow-y:auto;">
                <div id="upcoming-container" class="px-3 py-2">
                    <div class="text-center text-muted py-3 small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-logs">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong class="small"><i class="bi bi-funnel me-1"></i>Filters</strong>
                    <button class="btn btn-sm btn-outline-primary" onclick="fetchLogs()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row g-2">
                    <div class="col-md-4"><input type="text" id="log-search" class="form-control form-control-sm" placeholder="Search student..." oninput="fetchLogs()"></div>
                    <div class="col-md-3"><select id="log-course" class="form-select form-select-sm" onchange="fetchLogs()"><option value="">All Courses</option><?php foreach ($myCourses as $c): ?><option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><input type="date" id="log-date" class="form-control form-control-sm" onchange="fetchLogs()"></div>
                </div>
            </div>
            <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
                <div id="log-container" class="px-3">
                    <div class="text-center text-muted py-5"><i class="bi bi-arrow-clockwise spin fs-4"></i><br>Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-absent">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-5">
                        <select id="absent-course" class="form-select form-select-sm">
                            <option value="">Select course...</option>
                            <?php foreach ($myCourses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?> — <?php echo htmlspecialchars($c['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" id="absent-date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-danger w-100" onclick="checkAbsent()"><i class="bi bi-search me-1"></i>Check</button>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-outline-dark w-100" onclick="emailAbsent()" id="btn-email-absent" disabled><i class="bi bi-envelope me-1"></i>Email</button>
                    </div>
                </div>
                <div id="absent-container">
                    <div class="text-center text-muted py-4 small">Select a course and date to check absences.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-courses">
        <div id="courses-container">
            <div class="text-center text-muted py-5"><i class="bi bi-arrow-clockwise spin fs-4"></i><br>Loading...</div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-attendance">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-2">
                <strong class="small"><i class="bi bi-bar-chart me-1"></i>Student Attendance by Course</strong>
            </div>
            <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
                <div id="attendance-container">
                    <div class="text-center text-muted py-5"><i class="bi bi-arrow-clockwise spin fs-4"></i><br>Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-enroll">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-2">
                <strong class="small"><i class="bi bi-person-plus me-1"></i>Enroll Students in Your Courses</strong>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <select id="enroll-course" class="form-select form-select-sm">
                            <option value="">Select course...</option>
                            <?php foreach ($myCourses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?> — <?php echo htmlspecialchars($c['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" id="enroll-search" class="form-control form-control-sm" placeholder="Search by student no or name..." oninput="searchEnrollStudents()">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-sm btn-success w-100" onclick="searchEnrollStudents()"><i class="bi bi-search me-1"></i>Search</button>
                    </div>
                </div>
                <div id="enroll-search-results" class="mb-3" style="max-height:300px;overflow-y:auto;">
                    <div class="text-center text-muted py-3 small">Select a course and search for students to enroll.</div>
                </div>
                <hr>
                <h6 class="small fw-bold"><i class="bi bi-people me-1"></i>Currently Enrolled</h6>
                <div id="enrolled-list" style="max-height:250px;overflow-y:auto;">
                    <div class="text-center text-muted py-3 small">Select a course to view enrolled students.</div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Profile</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="avatar-ring mx-auto" onclick="document.getElementById('edit-photo-upload').click()">
                        <?php echo $editAvatarHtml; ?>
                        <div class="cam-overlay"><i class="bi bi-camera-fill"></i></div>
                    </div>
                    <input type="file" id="edit-photo-upload" class="d-none" accept="image/*" onchange="previewEditPhoto(this)">
                    <div class="small text-muted mt-1">Click avatar to change photo</div>
                </div>
                <div class="mb-2"><label for="edit-name" class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label><input type="text" id="edit-name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($teacherName); ?>" required></div>
                <div class="mb-2"><label for="edit-email" class="form-label small fw-bold">Email (Login ID) <span class="text-danger">*</span></label><input type="email" id="edit-email" class="form-control form-control-sm" value="<?php echo htmlspecialchars($teacherEmail); ?>" required></div>
                <div class="mb-2"><label for="edit-phone" class="form-label small fw-bold">Phone</label><input type="text" id="edit-phone" class="form-control form-control-sm" value="<?php echo htmlspecialchars($phone); ?>"></div>
                <hr>
                <div class="mb-2"><label for="edit-cur-pw" class="form-label small fw-bold">Current Password</label><input type="password" id="edit-cur-pw" class="form-control form-control-sm" placeholder="Required to change password"></div>
                <div class="mb-2"><label for="edit-new-pw" class="form-label small fw-bold">New Password</label><input type="password" id="edit-new-pw" class="form-control form-control-sm" placeholder="Leave blank to keep"></div>
                <div id="edit-msg" class="small"></div>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary btn-sm fw-semibold" onclick="saveProfile()"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
<script>
const MY_COURSES = <?php echo $courseData; ?>;
const DEVICES = <?php echo $devicesJson; ?>;

function setAvatar(src) {
    ['profile-img','edit-photo-preview'].forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        if (el.tagName === 'IMG') { el.src = src; }
        else {
            var img = document.createElement('img');
            img.src = src; img.alt = ''; img.style.width = '100%'; img.style.height = '100%';
            img.style.borderRadius = '50%'; img.style.border = '2px solid #fff'; img.style.objectFit = 'cover';
            el.replaceWith(img); img.id = id;
        }
    });
}

function refreshStats(){
    var sc = document.getElementById('filter-student-course').value;
    var tc = document.getElementById('filter-today-course').value;

    if (sc) {
        fetch('/csc2052/api/teacher.php?action=get_course_students&course_code='+encodeURIComponent(sc))
            .then(function(r){return r.json();})
            .then(function(d){
                document.getElementById('stat-students').textContent = (d.students ? d.students.length : 0);
            })
            .catch(function(){ document.getElementById('stat-students').textContent = '—'; });
    } else {
        var total = 0;
        MY_COURSES.forEach(function(c){ total += (c.enrolled || 0); });
        document.getElementById('stat-students').textContent = total;
    }

    if (tc) {
        fetch('/csc2052/api/teacher.php?action=get_today_attendance&course_code='+encodeURIComponent(tc))
            .then(function(r){return r.json();})
            .then(function(d){
                document.getElementById('stat-today').textContent = (d.present ? d.present.length : 0);
            })
            .catch(function(){ document.getElementById('stat-today').textContent = '—'; });
    } else {
        fetch('/csc2052/api/teacher.php?action=today_count')
            .then(function(r){return r.json();})
            .then(function(d){
                document.getElementById('stat-today').textContent = (d.count || 0);
            })
            .catch(function(){ document.getElementById('stat-today').textContent = '—'; });
    }
}

function toggleCustomTimer(){
    var sel = document.getElementById('start-timer');
    var custom = document.getElementById('start-timer-custom');
    custom.style.display = sel.value === 'custom' ? '' : 'none';
}

function fetchLogs(){
    var search = document.getElementById('log-search').value;
    var course = document.getElementById('log-course').value;
    var date = document.getElementById('log-date').value;
    var url = '/csc2052/api/teacher.php?action=teacher_logs';
    if (course) url += '&course=' + encodeURIComponent(course);
    // Only add date filter if explicitly set
    if (date) url += '&date=' + encodeURIComponent(date);
    if (search) url += '&search=' + encodeURIComponent(search);
    console.log('Fetching logs from:', url);
    fetch(url)
        .then(function(r){ 
            console.log('Response status:', r.status);
            return r.json(); 
        })
        .then(function(data) {
            console.log('Response data:', data);
            var el = document.getElementById('log-container');
            if (!data.logs || data.logs.length === 0){
                el.innerHTML = '<div class="text-center text-muted py-5">';
                if (date || course || search) {
                    el.innerHTML += '<i class="bi bi-inbox fs-1 d-block mb-2"></i>' +
                                    '<p class="mb-0 small">No records for selected filters.</p>' +
                                    '<p class="small text-muted mt-2">Try adjusting the date, course, or search filters.</p>';
                } else {
                    el.innerHTML += '<p class="mb-0 small">No attendance records found.</p>';
                }
                el.innerHTML += '</div>';
                return;
            }
            // Use table style matching student dashboard
            var h = '<table class="table table-hover table-sm mb-0 align-middle"><thead class="table-light"><tr>' +
                        '<th>Student</th><th>Course</th><th>Time</th><th>Method</th></tr></thead><tbody>';
            data.logs.forEach(function(log) {
                var t = new Date(log.timestamp.replace(' ','T'));
                var d = t.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                var time = t.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                var badge = (log.modality==='fingerprint')?'bg-success':(log.modality==='rfid')?'bg-info':(log.modality==='face')?'bg-warning text-dark':'bg-secondary';
                h += '<tr><td><strong class="small">' + esc(log.student_no) + '</strong> <span class="text-muted small">' + esc(log.student_name||'') + '</span></td>' +
                        '<td><span class="badge bg-light">' + esc(log.course_code||'') + '</span> <span class="text-muted small">' + esc(log.device_name || log.device_id || 'Web') + '</span></td>' +
                        '<td class="small">' + d + '</td>' +
                        '<td><span class="badge ' + badge + ' badge-pill">' + esc(log.modality||'manual') + '</span></td>' +
                    '</tr>';
            });
            h += '</tbody></table>';
            el.innerHTML = h;
        })
        .catch(function(err){ 
            console.error('Fetch error:', err);
            document.getElementById('log-container').innerHTML = '<div class="text-center text-danger py-4 small"><i class="bi bi-exclamation-triangle me-1"></i>Failed to load logs: ' + err + '</div>'; 
        });
}

function checkAbsent(){
    var course = document.getElementById('absent-course').value;
    var date = document.getElementById('absent-date').value;
    if (!course) return;
    fetch('/csc2052/api/teacher.php?action=absent_students&course_code='+encodeURIComponent(course)+'&date='+encodeURIComponent(date))
        .then(function(r){return r.json();})
        .then(function(data){
            var el = document.getElementById('absent-container');
            var btn = document.getElementById('btn-email-absent');
            if (!data.students || data.students.length===0){
                el.innerHTML = '<div class="text-center text-success py-3"><i class="bi bi-check-circle me-1"></i><strong>All students present!</strong></div>';
                btn.disabled = true;
            } else {
                var h = '<table class="table table-sm mb-0 small"><thead class="table-light"><tr><th>Student No</th><th>Name</th><th>Status</th></tr></thead><tbody>';
                data.students.forEach(function(s){ h += '<tr><td>'+esc(s.student_no)+'</td><td>'+esc(s.student_name||'')+'</td><td><span class="badge bg-danger badge-pill">Absent</span></td></tr>'; });
                h += '</tbody></table>';
                el.innerHTML = h;
                btn.disabled = false;
            }
        });
}

function emailAbsent(){
    var course = document.getElementById('absent-course').value;
    var date = document.getElementById('absent-date').value;
    if (!course) return;
    if (!confirm('Send email to all absent students for '+course+'?')) return;
    var fd = new FormData(); fd.append('action','send_absent_email'); fd.append('course_code',course); fd.append('date',date);
    fetch('/csc2052/api/teacher.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(data){
        if (data.status==='success'){ alert('Sent to '+data.sent+' students.'); checkAbsent(); }
        else alert('Failed: '+(data.message||''));
    });
}

function startLecture(){
    var course = document.getElementById('start-course').value;
    var device = document.getElementById('start-device').value;
    var timerSel = document.getElementById('start-timer').value;
    var timer = timerSel === 'custom' ? document.getElementById('start-timer-custom').value : timerSel;
    var autoStart = document.getElementById('auto-start-check').checked;
    if (!course){ alert('Select a course.'); return; }
    if(timerSel === 'custom' && (!timer || timer < 1)){ alert('Enter a valid custom time (min 1).'); return; }
    var msg = document.getElementById('start-msg');
    msg.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-clockwise spin me-1"></i>Starting...</span>';

    var fd = new FormData();
    fd.append('action','start_course_session');
    fd.append('course_code',course);
    fd.append('device',device);
    if(timer) fd.append('timer_minutes',timer);
    if(autoStart) fd.append('auto_start','1');

    fetch('/csc2052/api/teacher.php',{method:'POST', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd })
        .then(function(r){return r.json();})
        .then(function(data){
            if(data.status==='success'){
                var timerLabel = timerSel === 'custom' ? timer+' min' : (timer == 0 ? 'no timer' : timer+' min');
                msg.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Started '+esc(course)+' on '+esc(device)+' ('+timerLabel+')</span>';
                document.getElementById('btn-start').style.display = 'none';
                document.getElementById('btn-end').style.display = '';
                if(autoStart) msg.innerHTML += ' <span class="text-info">(Auto-start enabled)</span>';
            } else msg.innerHTML = '<span class="text-danger">'+esc(data.message||'Failed')+'</span>';
        })
        .catch(function(){ msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function endLecture(){
    var course = document.getElementById('start-course').value;
    if (!course){ alert('Select the course to end.'); return; }
    if (!confirm('End lecture for '+course+'?')) return;
    var msg = document.getElementById('start-msg');
    msg.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-clockwise spin me-1"></i>Ending...</span>';
    var fd = new FormData(); fd.append('action','end_course_session'); fd.append('course_code',course);
    fetch('/csc2052/api/teacher.php',{method:'POST', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd })
        .then(function(r){return r.json();})
        .then(function(data){
            if(data.status==='success'){
                msg.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Ended '+esc(course)+'</span>';
                document.getElementById('btn-start').style.display = '';
                document.getElementById('btn-end').style.display = 'none';
            } else msg.innerHTML = '<span class="text-danger">'+esc(data.message||'Failed')+'</span>';
        })
        .catch(function(){ msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function endLecture(){
    var course = document.getElementById('start-course').value;
    if (!course){ alert('Select the course to end.'); return; }
    if (!confirm('End lecture for '+course+'?')) return;
    var msg = document.getElementById('start-msg');
    msg.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-clockwise spin me-1"></i>Ending...</span>';

    var fd = new FormData(); fd.append('action','end_course_session'); fd.append('course_code',course);
    fetch('/csc2052/api/teacher.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(data){
            if(data.status==='success'){
                msg.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-stop-circle me-1"></i>Ended '+esc(course)+'</span>';
                document.getElementById('btn-start').style.display = '';
                document.getElementById('btn-end').style.display = 'none';
            } else msg.innerHTML = '<span class="text-danger">'+esc(data.message||'Failed')+'</span>';
        })
        .catch(function(){ msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function loadCourses(){
    var el = document.getElementById('courses-container');
    var dayNames = ['','Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    var formatTime = function(t){ if(!t) return ''; t = String(t); if(t.length>=5) return t.substring(0,5); return t; };
    fetch('/csc2052/api/teacher.php?action=get_my_courses')
        .then(function(r){return r.json();})
        .then(function(data){
            if(!data.courses||data.courses.length===0){
                el.innerHTML='<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-x fs-1 d-block mb-2"></i><p class="mb-0">No courses assigned yet.</p></div></div>';
                return;
            }
            var h='<div class="row g-3">';
            data.courses.forEach(function(c){
                var schedInfo = '';
                if(c.schedules && c.schedules.length > 0){
                    c.schedules.forEach(function(s){
                        var day = dayNames[parseInt(s.day_of_week)||0] || s.day_of_week;
                        var st = formatTime(s.start_time);
                        var et = formatTime(s.end_time);
                        var venue = s.venue ? ' @ '+esc(s.venue) : '';
                        schedInfo += '<span class="badge bg-light text-dark badge-pill me-1 mb-1">'+esc(day)+' '+st+'–'+et+venue+'</span>';
                    });
                }
                h+='<div class="col-md-6 col-lg-4"><div class="course-item p-3 h-100">'+
                    '<h6 class="fw-bold text-primary mb-0">'+esc(c.course_code)+'</h6>'+
                    '<div class="small text-muted">'+esc(c.course_name||'')+'</div>'+
                    (c.enrolled !== undefined && c.enrolled !== null ? '<div class="small mt-1 text-muted"><i class="bi bi-people me-1"></i>'+c.enrolled+' students</div>' : '')+
                    (schedInfo ? '<div class="mt-2">'+schedInfo+'</div>' : '')+
                '</div></div>';
            });
            h+='</div>';
            el.innerHTML=h;
        })
        .catch(function(){ el.innerHTML='<div class="text-center text-danger py-4 small"><i class="bi bi-exclamation-triangle me-1"></i>Failed to load.</div>'; });
}

function loadUpcoming(){
    var container = document.getElementById('upcoming-container');
    var dayIdx = new Date().getDay();
    var dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    var todayName = dayNames[dayIdx];
    var dayNumMap = {'Sun':0,'Mon':1,'Tue':2,'Wed':3,'Thu':4,'Fri':5,'Sat':6};
    var targetDay = dayNumMap[todayName];

    fetch('/csc2052/api/teacher.php?action=get_my_courses')
        .then(function(r){return r.json();})
        .then(function(data){
            if(!data.courses||data.courses.length===0){
                container.innerHTML = '<div class="text-center text-muted py-2 small">No courses assigned.</div>';
                document.getElementById('upcoming-count').textContent = '0';
                return;
            }
            var upcoming = [];
            var now = new Date();
            var nowMin = now.getHours()*60+now.getMinutes();
            data.courses.forEach(function(c){
                if(c.schedules){
                    c.schedules.forEach(function(s){
                        if(parseInt(s.day_of_week) === targetDay){
                            var st = String(s.start_time||'');
                            var parts = st.split(':');
                            var startMin = parseInt(parts[0])*60 + parseInt(parts[1]||0);
                            var isPast = startMin < nowMin;
                            upcoming.push({
                                code: c.course_code,
                                name: c.course_name||'',
                                day: s.day_of_week,
                                start: st.substring(0,5),
                                end: (s.end_time||'').substring(0,5),
                                venue: s.venue||'',
                                isPast: isPast
                            });
                        }
                    });
                }
            });
            upcoming.sort(function(a,b){ return a.start.localeCompare(b.start); });

            if(upcoming.length === 0){
                container.innerHTML = '<div class="text-center text-muted py-2 small">No classes scheduled for today.</div>';
                document.getElementById('upcoming-count').textContent = '0';
                return;
            }

            document.getElementById('upcoming-count').textContent = upcoming.length;
            var h = '';
            upcoming.forEach(function(u){
                var opacity = u.isPast ? 'opacity-50' : '';
                var badge = u.isPast ? 'bg-secondary' : 'bg-success';
                var label = u.isPast ? 'Done' : 'Upcoming';
                h += '<div class="d-flex align-items-center gap-2 py-1 '+opacity+'">'+
                    '<span class="badge '+badge+' badge-pill">'+label+'</span>'+
                    '<span class="fw-bold small">'+esc(u.code)+'</span>'+
                    '<span class="text-muted small">'+esc(u.name)+'</span>'+
                    '<span class="text-muted small ms-auto">'+esc(u.start)+'–'+esc(u.end)+(u.venue?' @ '+esc(u.venue):'')+'</span>'+
                '</div>';
            });
            container.innerHTML = h;
        })
        .catch(function(){ container.innerHTML = '<div class="text-center text-danger py-2 small">Failed to load.</div>'; });
}

function searchEnrollStudents(){
    var course = document.getElementById('enroll-course').value;
    var query = document.getElementById('enroll-search').value.trim();
    if(!course){ alert('Select a course first.'); return; }
    var container = document.getElementById('enroll-search-results');
    container.innerHTML = '<div class="text-center py-2"><i class="bi bi-arrow-clockwise spin"></i></div>';

    var url = '/csc2052/api/student.php?action=get_all_students&course_code='+encodeURIComponent(course);
    if(query) url += '&search='+encodeURIComponent(query);

    fetch(url)
        .then(function(r){return r.json();})
        .then(function(data){
            var students = data.students || [];
            if(students.length === 0){
                container.innerHTML = '<div class="text-center text-muted py-2 small">No students found.</div>';
                return;
            }
            var h = '<table class="table table-sm mb-0 small"><thead class="table-light"><tr><th>Student No</th><th>Name</th><th>Action</th></tr></thead><tbody>';
            students.forEach(function(s){
                h += '<tr><td>'+esc(s.student_no)+'</td><td>'+esc(s.student_name||'')+'</td>'+
                    '<td><button class="btn btn-sm btn-outline-success py-0 px-2" onclick="enrollStudent(\''+esc(s.student_no)+'\',\''+esc(course)+'\')">Enroll</button></td></tr>';
            });
            h += '</tbody></table>';
            container.innerHTML = h;
        })
        .catch(function(){ container.innerHTML = '<div class="text-center text-danger py-2 small">Failed to search.</div>'; });
    loadEnrolledList(course);
}

function enrollStudent(studentNo, course){
    if(!confirm('Enroll '+studentNo+' in '+course+'?')) return;
    var fd = new FormData(); fd.append('action','enroll_student_course'); fd.append('student_no',studentNo); fd.append('course_code',course);
    fetch('/csc2052/api/student.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(data){
        if(data.status==='success'){ alert('Enrolled successfully.'); searchEnrollStudents(); }
        else alert('Failed: '+(data.message||''));
    });
}

function loadEnrolledList(course){
    if(!course) course = document.getElementById('enroll-course').value;
    if(!course){ document.getElementById('enrolled-list').innerHTML = '<div class="text-center text-muted py-2 small">Select a course.</div>'; return; }
    var container = document.getElementById('enrolled-list');
    container.innerHTML = '<div class="text-center py-2"><i class="bi bi-arrow-clockwise spin"></i></div>';

    fetch('/csc2052/api/teacher.php?action=get_course_students&course_code='+encodeURIComponent(course))
        .then(function(r){return r.json();})
        .then(function(data){
            var students = data.students || [];
            if(students.length === 0){
                container.innerHTML = '<div class="text-center text-muted py-2 small">No students enrolled yet.</div>';
                return;
            }
            var h = '<table class="table table-sm mb-0 small"><thead class="table-light"><tr><th>Student No</th><th>Name</th><th>Action</th></tr></thead><tbody>';
            students.forEach(function(s){
                h += '<tr><td>'+esc(s.student_no)+'</td><td>'+esc(s.student_name||'')+'</td>'+
                    '<td><button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="unEnrollStudent(\''+esc(s.student_no)+'\',\''+esc(course)+'\')">Remove</button></td></tr>';
            });
            h += '</tbody></table>';
            container.innerHTML = h;
        })
        .catch(function(){ container.innerHTML = '<div class="text-center text-danger py-2 small">Failed to load.</div>'; });
}

function unEnrollStudent(studentNo, course){
    if(!confirm('Remove '+studentNo+' from '+course+'?')) return;
    var fd = new FormData(); fd.append('action','unenroll_student_course'); fd.append('student_no',studentNo); fd.append('course_code',course);
    fetch('/csc2052/api/student.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(data){
        if(data.status==='success'){ loadEnrolledList(course); }
        else alert('Failed: '+(data.message||''));
    });
}

function loadAttendancePct(){
    var el = document.getElementById('attendance-container');
    if(MY_COURSES.length === 0){
        el.innerHTML = '<div class="text-center text-muted py-5 small">No courses assigned.</div>';
        return;
    }
    var html = '<table class="table table-sm mb-0 align-middle"><thead class="table-light sticky-top"><tr>'+
        '<th class="ps-3">Student</th>';
    MY_COURSES.forEach(function(c){ html += '<th>'+esc(c.course_code)+'</th>'; });
    html += '<th>Overall</th></tr></thead><tbody id="att-tbody"><tr><td colspan="'+(MY_COURSES.length+2)+'" class="text-center py-3 text-muted small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading...</td></tr></tbody></table>';
    el.innerHTML = html;

    var promises = MY_COURSES.map(function(c){
        return fetch('/csc2052/api/teacher.php?action=get_course_students&course_code='+encodeURIComponent(c.course_code))
            .then(function(r){return r.json();})
            .then(function(d){ return {code: c.course_code, students: d.students || []}; });
    });

    Promise.all(promises).then(function(results){
        var studentMap = {};
        results.forEach(function(r){
            r.students.forEach(function(s){
                if(!studentMap[s.student_no]) studentMap[s.student_no] = {name: s.student_name, courses:{}};
                studentMap[s.student_no].courses[r.code] = true;
            });
        });

        var allCodes = MY_COURSES.map(function(c){return c.course_code;});
        var tbody = document.getElementById('att-tbody');
        var rows = '';
        Object.keys(studentMap).forEach(function(sno){
            var info = studentMap[sno];
            var attended = 0, total = 0;
            var cells = '';
            allCodes.forEach(function(code){
                var enrolled = info.courses[code];
                if(enrolled){
                    cells += '<td><span class="badge bg-success badge-pill">Enrolled</span></td>';
                    attended++; total++;
                } else {
                    cells += '<td><span class="badge bg-light text-muted">—</span></td>';
                }
            });
            var pct = total > 0 ? Math.round(attended/total*100) : 0;
            var pctColor = pct >= 80 ? 'text-success' : (pct >= 50 ? 'text-warning' : 'text-danger');
            rows += '<tr><td class="ps-3"><strong class="small">'+esc(sno)+'</strong><br><span class="text-muted small">'+esc(info.name||'')+'</span></td>'+cells+
                '<td><span class="fw-bold '+pctColor+'">'+pct+'%</span></td></tr>';
        });

        if(!rows){
            tbody.innerHTML = '<tr><td colspan="'+(allCodes.length+2)+'" class="text-center text-muted py-4 small">No students found.</td></tr>';
        } else {
            tbody.innerHTML = rows;
        }
    }).catch(function(){ el.innerHTML = '<div class="text-center text-danger py-3 small">Failed to load.</div>'; });
}

function uploadPhoto(input){
    if(!input.files.length) return;
    var fd = new FormData(); fd.append('action','upload_photo'); fd.append('photo',input.files[0]);
    fetch('/csc2052/api/teacher.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(data){
        if(data.status==='success') setAvatar(data.photo_url+'?t='+Date.now());
        else alert('Upload failed: '+(data.message||''));
    }).catch(function(){ alert('Upload failed.'); });
}

function previewEditPhoto(input){
    if(input.files.length){
        var reader = new FileReader();
        reader.onload = function(e){
            var preview = document.getElementById('edit-photo-preview');
            if(preview.tagName === 'IMG') preview.src = e.target.result;
            else {
                var img = document.createElement('img');
                img.src = e.target.result; img.style.width='100%'; img.style.height='100%';
                img.style.borderRadius='50%'; img.style.border='2px solid #fff';
                preview.replaceWith(img); img.id = 'edit-photo-preview';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function saveProfile(){
    var msg = document.getElementById('edit-msg');
    msg.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-clockwise spin me-1"></i>Saving...</span>';
    var fd = new FormData(); fd.append('action','update_my_profile');
    fd.append('teacher_name',document.getElementById('edit-name').value);
    fd.append('email',document.getElementById('edit-email').value);
    fd.append('phone',document.getElementById('edit-phone').value);
    var curPw = document.getElementById('edit-cur-pw').value;
    var newPw = document.getElementById('edit-new-pw').value;
    if(curPw) fd.append('current_password',curPw);
    if(newPw) fd.append('new_password',newPw);
    fetch('/csc2052/api/teacher.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(data){
        if(data.status==='success'){
            msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Saved! Reloading...</span>';
            setTimeout(function(){location.reload();},800);
        } else msg.innerHTML = '<span class="text-danger">'+esc(data.message||'Failed')+'</span>';
    }).catch(function(){ msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function esc(t){ var d=document.createElement('div'); d.textContent=t||''; return d.innerHTML; }

refreshStats();
fetchLogs();
loadCourses();
loadAttendancePct();
loadUpcoming();

document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('[data-bs-target="#tab-attendance"]').addEventListener('shown.bs.tab', loadAttendancePct);
    document.querySelector('[data-bs-target="#tab-courses"]').addEventListener('shown.bs.tab', loadCourses);
    document.querySelector('[data-bs-target="#tab-logs"]').addEventListener('shown.bs.tab', fetchLogs);
    document.getElementById('enroll-course').addEventListener('change', function(){ loadEnrolledList(this.value); });
});
</script>
