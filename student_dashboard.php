<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

if (!isStudent()) {
    if (isAdmin()) { header('Location: index.php'); exit; }
    if (isTeacher()) { header('Location: teacher_dashboard.php'); exit; }
    header('Location: login.php'); exit;
}

$studentNo = $_SESSION['student_no'] ?? $_SESSION['username'] ?? '';
$studentName = $_SESSION['full_name'] ?? 'Student';
$mustChange = $_SESSION['must_change_password'] ?? false;

$profile = null;
$profilePhoto = '';
$fingerId = '';
$rfidUid = '';
$faceId = '';
$initials = '';

try {
    $stmt = $pdo->prepare("SELECT student_no, student_name, fingerprint_id, rfid_uid, face_id, profile_photo FROM students WHERE student_no = ?");
    $stmt->execute([$studentNo]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($profile) {
        $studentName = $profile['student_name'] ?? $studentName;
        $_SESSION['full_name'] = $studentName;
        $fingerId = $profile['fingerprint_id'] ?? '';
        $rfidUid = $profile['rfid_uid'] ?? '';
        $faceId = $profile['face_id'] ?? '';
        $initials = getInitials($studentName);
        if (!empty($profile['profile_photo']) && file_exists(__DIR__ . '/' . $profile['profile_photo'])) {
            $profilePhoto = '/' . $profile['profile_photo'];
        }
    } else {
        $initials = getInitials($studentName);
    }
} catch (PDOException $e) {
    $initials = getInitials($studentName);
}

$csrf = $_SESSION['csrf_token'] ?? '';
$avatarSrc = $profilePhoto
    ? htmlspecialchars($profilePhoto)
    : 'https://ui-avatars.com/api/?name=' . urlencode($studentName) . '&size=80&background=059669&color=ffffff&bold=true';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="<?php echo h($_SESSION['csrf_token']); ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal — Sentinel Swarm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        body { background: #f8fafc; }
        .student-hero { background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%); border-radius: 20px; color: #fff; position: relative; overflow: hidden; }
        .student-hero::before { content: ''; position: absolute; top: -50%; right: -20%; width: 400px; height: 400px; background: rgba(255,255,255,0.06); border-radius: 50%; }
        .student-hero::after { content: ''; position: absolute; bottom: -30%; left: -10%; width: 300px; height: 300px; background: rgba(255,255,255,0.04); border-radius: 50%; }
        .avatar-ring { width: 80px; height: 80px; border-radius: 50% !important; padding: 3px; background: rgba(255,255,255,0.3); position: relative; cursor: pointer; overflow: hidden; }
        .avatar-ring img { width: 100% !important; height: 100% !important; border-radius: 50% !important; object-fit: cover; border: 2px solid #fff; }
        .avatar-ring .initials-avatar { width: 100%; height: 100%; border-radius: 50% !important; background: rgba(255,255,255,0.9); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 800; color: #059669; border: 2px solid #fff; }
        .avatar-ring .cam-overlay { position: absolute; bottom: 0; right: 0; width: 26px; height: 26px; background: #fff; border-radius: 50% !important; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
        .avatar-ring .cam-overlay i { font-size: 12px; color: #059669; }
        .stat-card { border-radius: 16px !important; overflow: hidden; border: 1px solid #e2e8f0; background: #fff; transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .stat-select { border-radius: 50px !important; }
        .ls-wide { letter-spacing: 0.04em; }
        .course-card { border-radius: 14px !important; overflow: hidden; border: 1px solid #e2e8f0; background: #fff; transition: box-shadow 0.2s, transform 0.2s; }
        .course-card:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.06); transform: translateY(-1px); }
        .course-card .progress { border-radius: 50px !important; overflow: hidden; }
        .course-card .progress-bar { border-radius: 50px !important; }
        .nav-tabs .nav-link { border: none; color: #64748b; font-weight: 600; padding: 0.75rem 1.25rem; }
        .nav-tabs .nav-link.active { color: #059669; border-bottom: 3px solid #059669; background: transparent; }
        .hist-table th { position: sticky; top: 0; z-index: 1; background: #f8fafc; }
        .badge-pill { border-radius: 50px !important; }
        #ai-assistant-container { display: none !important; }
    </style>
</head>
<body>
<?php require 'includes/header_student.php'; ?>

<?php if ($mustChange): ?>
<div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.6)" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h6 class="modal-title"><i class="bi bi-shield-lock me-2"></i>First-Time Password</h6>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Set a new password to secure your account.</p>
                <div id="pw-msg" class="small mb-2"></div>
                <input type="hidden" id="pw-cur" value="<?php echo htmlspecialchars($studentNo); ?>">
                <div class="mb-2"><label class="form-label small fw-bold">New Password</label><input type="password" id="pw-new" class="form-control form-control-sm" minlength="6"></div>
                <div class="mb-2"><label class="form-label small fw-bold">Confirm</label><input type="password" id="pw-conf" class="form-control form-control-sm" minlength="6"></div>
                <button class="btn btn-warning btn-sm w-100 fw-bold" onclick="changePassword()"><i class="bi bi-key me-1"></i>Set Password</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container pb-5 mt-3">

<div class="student-hero p-4 mb-4">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-auto">
            <div class="avatar-ring" onclick="document.getElementById('photo-upload').click()" title="Upload photo">
                <?php if ($profilePhoto): ?>
                <img id="profile-img" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Photo">
                <?php else: ?>
                <div id="profile-img" class="initials-avatar"><?php echo htmlspecialchars($initials ?: '?'); ?></div>
                <?php endif; ?>
                <div class="cam-overlay"><i class="bi bi-camera-fill"></i></div>
            </div>
            <input type="file" id="photo-upload" class="d-none" accept="image/*" onchange="uploadPhoto(this)">
        </div>
        <div class="col">
            <h3 class=\"fw-bold mb-1\"><?php echo htmlspecialchars($studentName); ?></h3>
            <span id=\"tier-badge\" class=\"badge bg-secondary ms-2\">Tier: —</span>
            <p class="mb-0 opacity-75"><i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($studentNo); ?>
            <?php if ($fingerId): ?> &bull; <i class="bi bi-fingerprint me-1"></i>FP #<?php echo htmlspecialchars($fingerId); ?><?php endif; ?>
            <?php if ($rfidUid): ?> &bull; <i class="bi bi-credit-card me-1"></i>RFID: <?php echo htmlspecialchars($rfidUid); ?><?php endif; ?>
            <?php if ($faceId): ?> &bull; <i class="bi bi-person-bounding-box me-1"></i>Face Enrolled<?php endif; ?></p>
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
                    <div class="text-muted small text-uppercase fw-semibold ls-wide">Enrolled Courses</div>
                    <div class="display-6 fw-bold mb-0" id="stat-courses">—</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 p-3"><i class="bi bi-calendar-check text-success fs-4"></i></div>
                <div>
                    <div class="text-muted small text-uppercase fw-semibold ls-wide">Sessions Attended</div>
                    <div class="display-6 fw-bold mb-0" id="stat-total">—</div>
                </div>
            </div>
            <select id="stat-course" class="form-select form-select-sm mt-2 stat-select border-secondary" onchange="refreshStats()">
                <option value="">All Courses</option>
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle p-3" id="rate-icon"><i class="bi bi-percent fs-4" id="rate-icon-i"></i></div>
                <div>
                    <div class="text-muted small text-uppercase fw-semibold ls-wide">Attendance Rate</div>
                    <div class="display-6 fw-bold mb-0" id="stat-rate">—</div>
                </div>
            </div>
            <div class="small text-muted mt-2"><span id="rate-detail">Select a course above</span></div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="stuTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-courses"><i class="bi bi-book me-1"></i>My Courses</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-history"><i class="bi bi-clock-history me-1"></i>Attendance History</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-courses">
        <div id="courses-container">
            <div class="text-center text-muted py-5"><i class="bi bi-arrow-clockwise spin fs-4"></i><br>Loading courses...</div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-history">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <div class="row g-2">
                    <div class="col-md-4"><input type="text" id="h-search" class="form-control form-control-sm" placeholder="Search course..." oninput="loadHistory()"></div>
                    <div class="col-md-3"><select id="h-course" class="form-select form-select-sm" onchange="loadHistory()"><option value="">All Courses</option></select></div>
                    <div class="col-md-2"><input type="date" id="h-from" class="form-control form-control-sm" onchange="loadHistory()"></div>
                    <div class="col-md-2"><input type="date" id="h-to" class="form-control form-control-sm" onchange="loadHistory()"></div>
                    <div class="col-md-1"><select id="h-limit" class="form-select form-select-sm" onchange="loadHistory()"><option value="30">30</option><option value="50" selected>50</option><option value="100">100</option></select></div>
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0" style="max-height:450px;overflow-y:auto;">
                <div id="hist-container">
                    <div class="text-center text-muted py-5 small">Switch to this tab to load history.</div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Profile</h6>
                <button type="button" class="btn-close btn-close-white" aria-label="Close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="avatar-ring mx-auto" onclick="document.getElementById('edit-photo-upload').click()">
                        <?php if ($profilePhoto): ?>
                        <img id="edit-photo-preview" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Preview">
                        <?php else: ?>
                        <div id="edit-photo-preview" class="initials-avatar"><?php echo htmlspecialchars($initials ?: '?'); ?></div>
                        <?php endif; ?>
                        <div class="cam-overlay"><i class="bi bi-camera-fill"></i></div>
            <div class="modal-footer">
                <button class="btn btn-warning btn-sm w-100 fw-bold" onclick="changePassword()"><i class="bi bi-key me-1"></i>Set Password</button>
                <button class="btn btn-link text-decoration-underline text-reset ps-0" onclick="window.location.href='/csc2052/logout.php'">Cancel and Logout</button>
            </div>
                    <input type="file" id="edit-photo-upload" class="d-none" accept="image/*" onchange="previewEditPhoto(this)">
                    <div class="small text-muted mt-1">Click to change photo</div>
                </div>
                <div class="mb-2"><label class="form-label small fw-bold">Full Name</label><input type="text" id="edit-name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($studentName); ?>"></div>
                <hr>
                <div class="mb-2"><label class="form-label small fw-bold">Current Password</label><input type="password" id="edit-cur" class="form-control form-control-sm" placeholder="Required to change password"></div>
                <div class="mb-2"><label class="form-label small fw-bold">New Password</label><input type="password" id="edit-new" class="form-control form-control-sm" placeholder="Leave blank to keep"></div>
                <div id="edit-msg" class="small"></div>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success btn-sm fw-semibold" onclick="saveProfile()"><i class="bi bi-check-lg me-1"></i>Save</button>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
<script>
let courseData = [];

function setAvatar(src) {
    const avatar = document.getElementById('profile-img');
    const editAvatar = document.getElementById('edit-photo-preview');
    if (avatar.tagName === 'IMG') {
        avatar.src = src;
    } else {
        const img = document.createElement('img');
        img.src = src; img.alt = 'Photo'; img.style.width = '100%'; img.style.height = '100%'; img.style.objectFit = 'cover';
        img.style.borderRadius = '50%'; img.style.border = '2px solid #fff';
        avatar.replaceWith(img); img.id = 'profile-img';
    }
    if (editAvatar) {
        if (editAvatar.tagName === 'IMG') { editAvatar.src = src; }
        else { const img2 = document.createElement('img'); img2.src = src; img2.style.width='100%'; img2.style.height='100%'; img2.style.borderRadius='50%'; img2.style.border='2px solid #fff'; editAvatar.replaceWith(img2); img2.id='edit-photo-preview'; }
    }
}

function refreshStats() {
    const selected = document.getElementById('stat-course').value;
    let totalAtt = 0, totalSess = 0, n = 0;
    courseData.forEach(c => {
        if (!selected || selected === c.course_code) {
            totalAtt += c.attended || 0;
            totalSess += c.total_sessions || 0;
            n++;
        }
    });
    document.getElementById('stat-total').textContent = totalAtt;
    const overall = totalSess > 0 ? Math.round(totalAtt / totalSess * 1000) / 10 : 0;
    const rateEl = document.getElementById('stat-rate');
    rateEl.textContent = overall + '%';
    rateEl.className = 'display-6 fw-bold mb-0 ' + (overall >= 80 ? 'text-success' : (overall >= 50 ? 'text-warning' : 'text-danger'));
    const ri = document.getElementById('rate-icon');
    ri.className = 'rounded-circle p-3 ' + (overall >= 80 ? 'bg-success bg-opacity-10' : (overall >= 50 ? 'bg-warning bg-opacity-10' : 'bg-danger bg-opacity-10'));
    document.getElementById('rate-icon-i').className = 'bi fs-4 ' + (overall >= 80 ? 'text-success' : (overall >= 50 ? 'text-warning' : 'text-danger'));
    document.getElementById('rate-detail').textContent = selected ? selected + ' — ' + totalAtt + '/' + totalSess + ' sessions' : 'All courses — ' + totalAtt + '/' + totalSess + ' sessions';
}

function loadCourses() {
    fetch('/csc2052/api/student.php?action=get_my_courses')
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('courses-container');
            if (!data.courses || data.courses.length === 0) {
                // Check if there are attendance logs but no enrollments
                fetch('/csc2052/api/student.php?action=get_my_attendance&limit=5')
                    .then(r => r.json())
                    .then(attData => {
                        if (attData.logs && attData.logs.length > 0) {
                            el.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">' +
                                '<i class="bi bi-exclamation-circle fs-1 d-block mb-2"></i>' +
                                '<p class="mb-0">You have attendance records but are not enrolled in any courses.</p>' +
                                '<p class="small">Contact your teacher or admin to enroll you in courses.</p>' +
                                '<hr><p class="small text-muted">Recent activity:</p>' +
                                '<p class="small">' + attData.logs.length + ' recent attendance record(s) found.</p></div></div>';
                        } else {
                            el.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-x fs-1 d-block mb-2"></i><p class="mb-0">Not enrolled in any courses yet.</p></div></div>';
                        }
                    })
                    .catch(() => {
                        el.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-x fs-1 d-block mb-2"></i><p class="mb-0">Not enrolled in any courses yet.</p></div></div>';
                    });
                document.getElementById('stat-courses').textContent = '0';
                document.getElementById('stat-total').textContent = '0';
                document.getElementById('stat-rate').textContent = '0%';
                return;
            }
            courseData = data.courses;
            if (data.tier) {
                const tierEl = document.getElementById('tier-badge');
                if (tierEl) tierEl.textContent = 'Tier: ' + data.tier;
            }
            let total = 0, n = 0;
            data.courses.forEach(c => { total += c.attended || 0; if (c.total_sessions > 0) n++; });
            let h = '<div class="row g-3">';
            data.courses.forEach(c => {
                const pct = c.percentage || 0;
                const bar = pct >= 80 ? 'bg-success' : (pct >= 50 ? 'bg-warning' : 'bg-danger');
                const pctColor = pct >= 80 ? 'text-success' : (pct >= 50 ? 'text-warning' : 'text-danger');
                h += '<div class="col-md-6 col-lg-4"><div class="course-card p-3 h-100">' +
                    '<div class="d-flex justify-content-between align-items-start mb-2">' +
                        '<div><h6 class="fw-bold text-primary mb-0">' + esc(c.course_code) + '</h6><div class="small text-muted">' + esc(c.course_name || '') + '</div></div>' +
                        '<span class="badge bg-light ' + pctColor + ' fw-bold badge-pill">' + pct + '%</span>' +
                    '</div>' +
                    '<div class="small text-muted mb-2"><i class="bi bi-calendar3 me-1"></i>Attended: <strong class="text-dark">' + c.attended + '</strong> of ' + c.total_sessions + ' sessions</div>' +
                    '<div class="progress" style="height:10px;background:#e2e8f0;">' +
                        '<div class="progress-bar ' + bar + '" style="width:' + pct + '%;"></div>' +
                    '</div>' +
                '</div></div>';
            });
            h += '</div>';
            el.innerHTML = h;
            document.getElementById('stat-courses').textContent = data.courses.length;
            document.getElementById('stat-total').textContent = total;
            const overall = n > 0 ? Math.round(data.courses.reduce((s, c) => s + (c.percentage || 0), 0) / n * 10) / 10 : 0;
            const rateEl = document.getElementById('stat-rate');
            rateEl.textContent = overall + '%';
            rateEl.className = 'display-6 fw-bold mb-0 ' + (overall >= 80 ? 'text-success' : (overall >= 50 ? 'text-warning' : 'text-danger'));
            document.getElementById('rate-detail').textContent = 'Average across ' + data.courses.length + ' courses';
            populateCourseSelect(data.courses);
            refreshStats();
        })
        .catch(() => { document.getElementById('courses-container').innerHTML = '<div class="text-center text-danger py-5"><i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i><p class="mb-0 small">Failed to load courses.</p></div>'; });
}

function populateCourseSelect(courses) {
    const sel = document.getElementById('stat-course');
    const histSel = document.getElementById('h-course');
    sel.innerHTML = '<option value="">All Courses</option>';
    histSel.innerHTML = '<option value="">All Courses</option>';
    courses.forEach(c => {
        sel.innerHTML += '<option value="' + esc(c.course_code) + '">' + esc(c.course_code) + '</option>';
        histSel.innerHTML += '<option value="' + esc(c.course_code) + '">' + esc(c.course_code) + '</option>';
    });
}

function loadHistory() {
    const el = document.getElementById('hist-container');
    el.innerHTML = '<div class="text-center py-4"><i class="bi bi-arrow-clockwise spin fs-4"></i></div>';
    let url = '/csc2052/api/student.php?action=get_my_attendance&limit=' + document.getElementById('h-limit').value;
    if (document.getElementById('h-course').value) url += '&course_code=' + encodeURIComponent(document.getElementById('h-course').value);
    if (document.getElementById('h-from').value) url += '&date_from=' + encodeURIComponent(document.getElementById('h-from').value);
    if (document.getElementById('h-to').value) url += '&date_to=' + encodeURIComponent(document.getElementById('h-to').value);
    if (document.getElementById('h-search').value) url += '&search=' + encodeURIComponent(document.getElementById('h-search').value);
    fetch(url).then(r => r.json()).then(data => {
    if (!data.logs || data.logs.length === 0) {
        el.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i><p class="mb-0 small">No attendance records found.</p></div>';
        return;
    }
        let h = '<table class="table table-hover table-sm mb-0 align-middle hist-table"><thead class="table-light"><tr><th class="ps-3">Course</th><th>Date</th><th>Time</th><th>Method</th></tr></thead><tbody>';
        data.logs.forEach(log => {
            const ts = new Date(log.timestamp.replace(' ', 'T'));
            const d = ts.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
            const t = ts.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            const mb = (log.modality === 'fingerprint') ? 'bg-success' : (log.modality === 'rfid') ? 'bg-info' : (log.modality === 'face') ? 'bg-warning text-dark' : 'bg-secondary';
            h += '<tr><td class="ps-3"><span class="badge bg-dark">' + esc(log.course_code) + '</span> <span class="small text-muted">' + esc(log.course_name || '') + '</span></td><td class="small">' + d + '</td><td class="small">' + t + '</td><td><span class="badge ' + mb + ' badge-pill">' + esc(log.modality || 'manual') + '</span></td></tr>';
        });
        h += '</tbody></table>';
        el.innerHTML = h;
    }).catch(() => { el.innerHTML = '<div class="text-center text-danger py-3 small"><i class="bi bi-exclamation-triangle me-1"></i>Failed to load.</div>'; });
}

function getCsrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
}

function uploadPhoto(input) {
    if (!input.files.length) return;
    const fd = new FormData(); fd.append('action', 'upload_my_photo'); fd.append('photo', input.files[0]);
    fetch('/csc2052/api/student.php', { method: 'POST', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd }).then(r => r.json()).then(data => {
        if (data.status === 'success') setAvatar(data.photo_url + '?t=' + Date.now());
        else alert('Upload failed: ' + (data.message || ''));
    }).catch(() => alert('Upload failed.'));
}

function previewEditPhoto(input) {
    if (input.files.length) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('edit-photo-preview');
            if (preview.tagName === 'IMG') preview.src = e.target.result;
            else {
                const img = document.createElement('img'); img.src = e.target.result; img.style.width = '100%'; img.style.height = '100%'; img.style.borderRadius = '50%'; img.style.border = '2px solid #fff';
                preview.replaceWith(img); img.id = 'edit-photo-preview';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function saveProfile() {
    const msg = document.getElementById('edit-msg');
    msg.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-clockwise spin me-1"></i>Saving...</span>';
    const fd = new FormData(); fd.append('action', 'update_my_profile');
    fd.append('student_name', document.getElementById('edit-name').value);
    const cur = document.getElementById('edit-cur').value;
    const nw = document.getElementById('edit-new').value;
    if (cur) fd.append('current_password', cur);
    if (nw) fd.append('new_password', nw);
    fetch('/csc2052/api/student.php', { method: 'POST', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd }).then(r => r.json()).then(data => {
        if (data.status === 'success') { msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Saved! Reloading...</span>'; setTimeout(() => location.reload(), 800); }
        else msg.innerHTML = '<span class="text-danger">' + esc(data.message || 'Failed') + '</span>';
    }).catch(() => { msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function changePassword() {
    const msg = document.getElementById('pw-msg');
    const nw = document.getElementById('pw-new').value;
    const conf = document.getElementById('pw-conf').value;
    if (nw.length < 6) { msg.innerHTML = '<span class="text-danger">Minimum 6 characters.</span>'; return; }
    if (nw !== conf) { msg.innerHTML = '<span class="text-danger">Passwords do not match.</span>'; return; }
    msg.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-clockwise spin me-1"></i>Updating...</span>';
    const fd = new FormData(); fd.append('action', 'update_my_profile');
    fd.append('student_name', '<?php echo addslashes($studentName); ?>');
    // For first-time setup, don't send current_password (it contains student_no as placeholder)
    // Only send current_password if user is changing an existing password
    const cur = document.getElementById('pw-cur').value;
    if (cur && cur !== '<?php echo $studentNo; ?>') {
        fd.append('current_password', cur);
    }
    fd.append('new_password', nw);
    fetch('/csc2052/api/student.php', { method: 'POST', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd }).then(r => r.json()).then(data => {
        if (data.status === 'success') { msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Updated! Reloading...</span>'; setTimeout(() => location.reload(), 800); }
        else msg.innerHTML = '<span class="text-danger">' + esc(data.message || 'Failed') + '</span>';
    });
}

function esc(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', () => {
    loadCourses();
    document.querySelector('[data-bs-target="#tab-history"]').addEventListener('shown.bs.tab', loadHistory);
});
</script>
