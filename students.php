<?php
require 'includes/header_admin.php';

$enrolled_students = [];
$admins = [];
$courses = [];
$next_id = 1;
try {
    $enrolled_students = $pdo->query("SELECT student_no, student_name, fingerprint_id, rfid_uid, face_id, face_descriptor FROM students ORDER BY student_no ASC")->fetchAll(PDO::FETCH_ASSOC);
    $used_ids = array_column($enrolled_students, 'fingerprint_id');
    for ($i = 1; $i <= 127; $i++) {
        if (!in_array($i, $used_ids)) {
            $next_id = $i; break;
        }
    }
    $admins = $pdo->query("SELECT admin_name, fingerprint_id, rfid_uid, face_id, face_descriptor FROM admins ORDER BY admin_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $courses = $pdo->query("SELECT course_code, course_name FROM courses ORDER BY course_code ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[Students] DB error: ' . $e->getMessage());
}
?>

<div class="row mt-2">
    <div class="col-lg-10 mx-auto">
        
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-journal-bookmark-fill me-2"></i>Course Management</strong>
            </div>
            <div class="card-body p-0">
                <ul class="nav nav-tabs bg-light px-3 pt-2 border-bottom" id="courseTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" id="courses-tab" data-bs-toggle="tab" data-bs-target="#tab-courses" type="button" role="tab"><i class="bi bi-book me-1"></i>Courses</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="manager-tab" data-bs-toggle="tab" data-bs-target="#tab-manager" type="button" role="tab" onclick="loadCourseManager()"><i class="bi bi-folder2-open me-1"></i>Course Manager</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#tab-schedule" type="button" role="tab" onclick="loadSchedules()"><i class="bi bi-calendar-week me-1"></i>Weekly Schedule</button>
                    </li>
                </ul>
                <div class="tab-content p-3" id="courseTabsContent">

                    <div class="tab-pane fade show active" id="tab-courses" role="tabpanel">
                        <div class="row">
                            <div class="col-md-5 border-end pe-md-4">
                                <h6 class="text-muted fw-bold mb-2"><i class="bi bi-plus-circle me-1"></i>Add New Course</h6>
                                <div class="input-group mb-2">
                                    <span class="input-group-text bg-white"><i class="bi bi-tag text-primary"></i></span>
                                    <input type="text" id="new-course-code" class="form-control" placeholder="Course Code (e.g. PHY1911)">
                                    <input type="text" id="new-course-name" class="form-control" placeholder="Course Name (Optional)">
                                    <button class="btn btn-primary fw-semibold" onclick="addCourse()"><i class="bi bi-plus-lg me-1"></i>Add</button>
                                </div>
                                <div id="course-add-msg" class="small mb-2 min-h-xs"></div>

                                <h6 class="text-muted fw-bold mb-2 mt-3"><i class="bi bi-list-ul me-1"></i>Registered Courses</h6>
                                <div class="scroll-y-sm border rounded bg-white p-2 shadow-sm">
                                    <ul id="course-list" class="list-group list-group-flush small">
                                        <?php if (count($courses) > 0): ?>
                                            <?php foreach ($courses as $c): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                                                <span><strong><?php echo htmlspecialchars($c['course_code']); ?></strong><?php if (!empty($c['course_name'])): ?> - <span class="text-muted"><?php echo htmlspecialchars($c['course_name']); ?></span><?php endif; ?></span>
                                                <button class="btn btn-xs btn-outline-danger py-0 px-1" onclick="deleteCourse('<?php echo htmlspecialchars($c['course_code']); ?>')"><i class="bi bi-x-lg"></i></button>
                                            </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li class="list-group-item text-muted text-center py-2"><em>No courses registered</em></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <div class="col-md-7 ps-md-4">
                                <h6 class="text-muted fw-bold mb-2"><i class="bi bi-person-plus me-1"></i>Enroll Student in Course</h6>
                                <div class="row g-2 mb-2">
                                    <div class="col-5">
                                        <input type="text" id="enroll-stu-course-no" class="form-control form-control-sm" placeholder="Student No (e.g. S/22/314)">
                                    </div>
                                    <div class="col-5">
                                        <select id="enroll-stu-course-select" class="form-select form-select-sm">
                                            <option value="">Select Course...</option>
                                        </select>
                                    </div>
                                    <div class="col-2">
                                        <button class="btn btn-success btn-sm w-100 fw-semibold" onclick="enrollStudentCourse()"><i class="bi bi-check-lg"></i></button>
                                    </div>
                                </div>
                                <div id="course-enroll-msg" class="small mb-2 min-h-xs"></div>

                                <h6 class="text-muted fw-bold mb-2 mt-3"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Bulk Enroll via CSV</h6>
                                <div class="card border-primary border-opacity-25 shadow-sm">
                                    <div class="card-body p-2">
                                        <p class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i>Name the CSV file after the course code, e.g. <code>MAT3063.csv</code> or <code>PHY1911.csv</code>. Each row: <strong>Student No</strong>, <strong>Name</strong> (name column optional).</p>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white"><i class="bi bi-upload text-primary"></i></span>
                                            <input type="file" id="course-bulk-csv" class="form-control" accept=".csv">
                                            <button class="btn btn-primary fw-semibold" onclick="bulkEnrollCourseCSV()"><i class="bi bi-person-plus me-1"></i>Enroll All</button>
                                        </div>
                                        <div id="course-bulk-log" class="small mt-2 min-h-xs"></div>
                                    </div>
                                </div>

                                <h6 class="text-muted fw-bold mb-2 mt-3"><i class="bi bi-people me-1"></i>Course Enrollment Lookup</h6>
                                <div class="input-group mb-2">
                                    <input type="text" id="lookup-stu-course" class="form-control form-control-sm" placeholder="Student No to lookup">
                                    <button class="btn btn-outline-secondary btn-sm" onclick="lookupStudentCourses()"><i class="bi bi-search me-1"></i>Lookup</button>
                                </div>
                                <div id="lookup-courses-result" class="border rounded bg-white p-2 shadow-sm small min-h-sm"></div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-manager" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-muted fw-bold mb-0"><i class="bi bi-folder2-open me-1"></i>Course Manager</h6>
                            <button class="btn btn-sm btn-outline-primary fw-semibold" onclick="loadCourseManager()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                        </div>
                        <div id="course-manager-container">
                            <div class="text-center py-4 text-muted"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading courses...</div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-schedule" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-muted fw-bold mb-0"><i class="bi bi-calendar-week me-1"></i>Weekly Course Schedule</h6>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-success fw-semibold" onclick="loadSchedules()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                                <button class="btn btn-sm btn-success fw-semibold" data-bs-toggle="modal" data-bs-target="#scheduleModal"><i class="bi bi-plus-lg me-1"></i>Add Time Slot</button>
                            </div>
                        </div>
                        <div id="timetable-container" class="table-responsive">
                            <table class="table table-bordered mb-0 text-center small" style="table-layout: fixed;">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width:80px;">Time</th>
                                        <th>Monday</th>
                                        <th>Tuesday</th>
                                        <th>Wednesday</th>
                                        <th>Thursday</th>
                                        <th>Friday</th>
                                        <th>Saturday</th>
                                    </tr>
                                </thead>
                                <tbody id="timetable-body">
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <strong class="text-dark"><i class="bi bi-people-fill me-2"></i>Student Database & Templates</strong>
            </div>
            <div class="card-body bg-light">
                
                <div class="row">
                    <div class="col-md-6 border-end pe-md-4">
                        <h6 class="text-muted fw-bold mb-2">Hardware Enrollment Link</h6>
                        <div class="input-group mb-2 shadow-sm rounded">
                            <span class="input-group-text bg-white"><i class="bi bi-person-badge text-primary"></i></span>
                            <input type="text" id="enroll-student-no" class="form-control border-end-0" placeholder="Student No">
                            <input type="text" id="enroll-student-name" class="form-control w-50" placeholder="Student Name (Optional)">
                            <button class="btn btn-outline-primary fw-semibold" onclick="updateStudentProfile()"><i class="bi bi-save me-1"></i>Update</button>
                        </div>
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-white"><i class="bi bi-fingerprint text-primary"></i></span>
                            <input type="number" id="enroll-id" class="form-control bg-light text-muted" value="<?php echo $next_id; ?>" readonly title="Auto-assigned ID">
                            <button class="btn btn-primary fw-semibold" onclick="triggerEnroll()">
                                <i class="bi bi-plus-circle me-1"></i>Enroll Finger
                            </button>
                        </div>
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-white"><i class="bi bi-credit-card text-info"></i></span>
                            <input type="text" id="enroll-rfid" class="form-control" placeholder="RFID Tag UID">
                            <button class="btn btn-outline-info fw-semibold px-2" onclick="autoFindRfid('enroll-rfid')" title="Auto Find Latest Scan"><i class="bi bi-search"></i></button>
                            <button class="btn btn-info text-white fw-semibold" onclick="triggerEnrollRfid()">
                                <i class="bi bi-link me-1"></i>Link RFID
                            </button>
                        </div>
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-white"><i class="bi bi-person-bounding-box text-warning"></i></span>
                            <input type="text" id="enroll-face" class="form-control" placeholder="Face Profile ID">
                            <button class="btn btn-outline-warning fw-semibold px-2" onclick="autoFindFace('enroll-face')" title="Auto Find Latest Scan"><i class="bi bi-search"></i></button>
                            <button class="btn btn-warning text-white fw-semibold" onclick="triggerEnrollFace()">
                                <i class="bi bi-link me-1"></i>Link Face
                            </button>
                        </div>
                        <div class="input-group mb-2 mt-3">
                            <span class="input-group-text bg-white border-danger-subtle"><i class="bi bi-images text-danger"></i></span>
                            <input type="file" id="face-bulk-input" class="form-control border-danger-subtle" multiple accept="image/*"
                                title="Name files as student numbers: S-20-123.jpg">
                            <button class="btn btn-danger text-white fw-semibold" onclick="bulkEnrollFromImages(document.getElementById('face-bulk-input'))">
                                <i class="bi bi-upload me-1"></i>Bulk Enroll
                            </button>
                        </div>
                        <div class="text-muted small mb-1 ps-1">
                            <i class="bi bi-info-circle text-primary me-1"></i>
                            Name each image as the student number, e.g. <code>S-20-123.jpg</code> or <code>S_20_123.png</code>. Hyphens/underscores are auto-converted to slashes.
                        </div>
                        <div class="progress mb-1" style="height:6px" id="bulk-upload-progress">
                            <div id="bulk-upload-bar" class="progress-bar bg-danger" style="width:0%"></div>
                        </div>
                        <div id="bulk-upload-log" class="d-none rounded mb-3 bulk-log" style="display:none!important"></div>
                        <script>
                            const _origBulk = window.bulkEnrollFromImages;
                            window.bulkEnrollFromImages = async function(fi) {
                                const log = document.getElementById('bulk-upload-log');
                                if (log) { log.style.display = ''; log.classList.remove('d-none'); }
                                await _origBulk(fi);
                            };
                        </script>

                        <h6 class="text-muted fw-bold mb-2 border-top pt-3"><i class="bi bi-camera-video me-1"></i>Web Face Enrollment</h6>
                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white border rounded shadow-sm">
                                <i class="bi bi-images text-primary"></i>
                                <label class="form-label small fw-bold mb-0 text-muted flex-shrink-0">Capture Count</label>
                                <input type="range" class="form-range flex-grow-1" min="3" max="15" value="7" id="enroll-capture-count"
                                    oninput="document.getElementById('enroll-count-val').textContent=this.value">
                                <span class="badge bg-primary enroll-count-badge" id="enroll-count-val">7</span>
                            </div>
                            <button id="btn-start-enroll" class="btn btn-outline-primary fw-semibold w-100"
                                onclick="startWebFaceEnrollment(document.getElementById('enroll-student-no').value, document.getElementById('enroll-student-name').value, parseInt(document.getElementById('enroll-capture-count').value))">
                                <i class="bi bi-person-bounding-box me-1"></i>Start Webcam Enrollment
                            </button>
                            <div class="mt-2 mb-2 text-center">
                                <span class="badge bg-secondary mb-1">OR UPLOAD PHOTO</span>
                                <input type="file" id="web-enroll-image" accept="image/*" class="form-control form-control-sm" onchange="enrollUploadedImage(this)">
                            </div>
                            <div id="web-enroll-container" class="d-none border rounded p-2 mt-2 bg-white text-center shadow-sm">
                                <select id="enroll-camera-select" class="form-select form-select-sm mb-2 d-none" onchange="switchEnrollCamera()"></select>
                                <video id="web-enroll-video" autoplay muted playsinline class="w-100 rounded bg-dark mb-2" style="height: auto; display: block;"></video>
                                <div id="web-enroll-status" class="small fw-bold mb-2"></div>
                                <div class="d-flex gap-2">
                                    <button id="btn-capture-enroll" class="btn btn-success flex-grow-1 fw-bold d-none"><i class="bi bi-camera me-1"></i>Capture Face</button>
                                    <button id="btn-cancel-enroll" class="btn btn-secondary flex-grow-1 fw-bold d-none">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 ps-md-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-muted fw-bold mb-0">Profile Names</h6>
                            <button class="btn btn-sm btn-link text-decoration-none py-0 px-0" data-bs-toggle="collapse" data-bs-target="#csvHelp">
                                <i class="bi bi-info-circle me-1"></i>Instructions
                            </button>
                        </div>
                        <div class="collapse mb-3" id="csvHelp">
                            <div class="card card-body bg-white border border-info small p-2 text-muted shadow-sm rounded-3">
                                <strong class="text-info-emphasis mb-1"><i class="bi bi-file-csv me-1"></i>How to bulk import:</strong>
                                Upload an Excel-generated <code>.csv</code> file with exactly two columns: <strong>Student No</strong> and <strong>Name</strong>. No headers needed.
                            </div>
                        </div>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-white"><i class="bi bi-person-plus text-primary"></i></span>
                            <input type="text" id="add-name-stuno" class="form-control" placeholder="Stu No">
                            <input type="text" id="add-name-full" class="form-control w-50" placeholder="Full Name">
                            <button class="btn btn-outline-primary fw-semibold" onclick="addStudentName()"><i class="bi bi-save me-1"></i>Add</button>
                        </div>
                        
                        <div class="input-group input-group-sm mb-4">
                            <span class="input-group-text bg-white"><i class="bi bi-file-earmark-spreadsheet text-success"></i></span>
                            <input type="file" id="csv-upload-file" class="form-control" accept=".csv">
                            <button class="btn btn-outline-success fw-semibold" onclick="uploadStudentCSV()"><i class="bi bi-upload me-1"></i>Import CSV</button>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-3 mb-2 mt-2">
                    <h6 class="text-muted fw-bold mb-0">Mapped Templates</h6>
                    <div class="d-flex gap-2 align-items-center">
                        <select id="template-attendance-course" class="form-select form-select-sm w-auto" onchange="loadTemplateAttendance()">
                            <option value="">Overall Attendance</option>
                        </select>
                        <input type="text" id="template-search" class="form-control form-control-sm border-secondary w-25" placeholder="Search student..." oninput="filterTemplates()">
                    </div>
                </div>
                <div class="scroll-y-lg border rounded bg-white mb-3 shadow-sm w-100">
                    <table class="table table-hover table-borderless align-middle mb-0" id="templates-table">
                        <thead class="table-light sticky-top border-bottom">
                            <tr>
                                <th class="ps-3 text-secondary small fw-bold">Student No</th>
                                <th class="text-secondary small fw-bold">Slot ID</th>
                                <th class="text-secondary small fw-bold">RFID UID</th>
                                <th class="text-secondary small fw-bold">HW Face ID</th>
                                <th class="text-secondary small fw-bold">Web Face</th>
                                <th class="text-secondary small fw-bold">Attendance %</th>
                                <th class="text-end pe-3 text-secondary small fw-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $attendance_pcts = [];
                            $attendance_counts = [];
                            $total_days = 0;
                            try {
                                $stmt = $pdo->query(
                                    "SELECT student_no, COUNT(DISTINCT DATE(timestamp)) as days_present
                                     FROM attendance_logs
                                     GROUP BY student_no"
                                );
                                while ($row = $stmt->fetch()) {
                                    $attendance_pcts[$row['student_no']] = (int)$row['days_present'];
                                }
                                $totalDaysStmt = $pdo->query("SELECT COUNT(DISTINCT DATE(timestamp)) FROM attendance_logs");
                                $total_days = (int)$totalDaysStmt->fetchColumn();
                            } catch (PDOException $e) {
                                $total_days = 0;
                            }
                            $course_attendance = [];
                            try {
                                $stmt = $pdo->query(
                                    "SELECT student_no, course_code, COUNT(DISTINCT DATE(timestamp)) as days_present
                                     FROM attendance_logs
                                     WHERE course_code != '' AND course_code != 'MANUAL_ENTRY'
                                     GROUP BY student_no, course_code"
                                );
                                while ($row = $stmt->fetch()) {
                                    if (!isset($course_attendance[$row['course_code']])) {
                                        $course_attendance[$row['course_code']] = ['students' => [], 'total_days' => 0];
                                    }
                                    $course_attendance[$row['course_code']]['students'][$row['student_no']] = (int)$row['days_present'];
                                }
                                foreach ($course_attendance as $cc => $data) {
                                    $daysStmt = $pdo->prepare("SELECT COUNT(DISTINCT DATE(timestamp)) FROM attendance_logs WHERE course_code = ?");
                                    $daysStmt->execute([$cc]);
                                    $course_attendance[$cc]['total_days'] = (int)$daysStmt->fetchColumn();
                                }
                            } catch (PDOException $e) {}
                            ?>
                            <?php if(count($enrolled_students) > 0): ?>
                                <?php foreach($enrolled_students as $st): ?>
                                <?php
                                    $overall_days = $attendance_pcts[$st['student_no']] ?? 0;
                                    $overall_pct = $total_days > 0 ? round(($overall_days / $total_days) * 100) : 0;
                                    $badgeClass = $overall_pct >= 80 ? 'bg-success' : ($overall_pct >= 50 ? 'bg-warning text-dark' : ($overall_days > 0 ? 'bg-danger' : 'bg-secondary'));
                                    
                                    $course_data = [];
                                    foreach ($course_attendance as $cc => $data) {
                                        $stu_days = $data['students'][$st['student_no']] ?? 0;
                                        $course_total = $data['total_days'];
                                        $course_pct = $course_total > 0 ? round(($stu_days / $course_total) * 100) : 0;
                                        $course_data[$cc] = ['days' => $stu_days, 'total' => $course_total, 'pct' => $course_pct];
                                    }

                                    $wf = 0;
                                    if (!empty($st['face_descriptor'])) {
                                        $fd = json_decode($st['face_descriptor'], true);
                                        if (is_array($fd) && count($fd) > 0) {
                                            $wf = is_array($fd[0]) ? count($fd) : 1;
                                        }
                                    }
                                ?>
                                <tr class="template-row border-bottom" style="transition: all 0.2s;">
                                    <td class="ps-3 fw-bold text-dark template-student-id" style="font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($st['student_no']); ?>
                                        <?php if(!empty($st['student_name'])): ?>
                                            <div class="text-muted fw-normal template-student-name" style="font-size: 0.8rem;"><?php echo htmlspecialchars($st['student_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($st['fingerprint_id']): ?>
                                        <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary-subtle px-2 py-1">
                                            <i class="bi bi-fingerprint me-1"></i><?php echo htmlspecialchars($st['fingerprint_id']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($st['rfid_uid']): ?>
                                        <span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info-subtle px-2 py-1">
                                            <i class="bi bi-credit-card me-1"></i><?php echo htmlspecialchars($st['rfid_uid']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($st['face_id']): ?>
                                        <span class="badge rounded-pill bg-warning bg-opacity-10 text-warning border border-warning-subtle px-2 py-1">
                                            <i class="bi bi-person-bounding-box me-1"></i><?php echo htmlspecialchars($st['face_id']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($wf > 0): ?>
                                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-camera me-1"></i><?php echo $wf; ?> angle(s)
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($overall_days > 0): ?>
                                        <span class="badge rounded-pill <?php echo $badgeClass; ?> bg-opacity-10 border border-<?php echo $overall_pct >= 80 ? 'success' : ($overall_pct >= 50 ? 'warning' : 'danger'); ?>-subtle px-2 py-1 attendance-badge" data-student="<?php echo htmlspecialchars($st['student_no']); ?>" data-overall="<?php echo $overall_pct; ?>"
<?php foreach ($course_data as $cc => $cd): ?>data-course-<?php echo htmlspecialchars($cc); ?>="<?php echo $cd['pct']; ?>|<?php echo $cd['days']; ?>/<?php echo $cd['total']; ?>"<?php endforeach; ?>>
                                            <?php echo $overall_pct; ?>%
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted small attendance-badge" data-student="<?php echo htmlspecialchars($st['student_no']); ?>" data-overall="0"
<?php foreach ($course_data as $cc => $cd): ?>data-course-<?php echo htmlspecialchars($cc); ?>="<?php echo $cd['pct']; ?>|<?php echo $cd['days']; ?>/<?php echo $cd['total']; ?>"<?php endforeach; ?>>0%</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group shadow-sm">
                                            <button onclick="editStudentMap('<?php echo htmlspecialchars($st['student_no'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($st['student_name'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($st['fingerprint_id'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($st['rfid_uid'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($st['face_id'], ENT_QUOTES); ?>')" class="btn btn-sm btn-light border text-info" title="Edit Student Details"><i class="bi bi-pencil-square"></i></button>
                                            <button onclick="pushTemplateToDevice('<?php echo htmlspecialchars($st['student_no'], ENT_QUOTES); ?>',<?php echo ($st['fingerprint_id'] ? (int)$st['fingerprint_id'] : 0); ?>)" class="btn btn-sm btn-light border text-primary" title="Push Finger to ESP" <?php echo empty($st['fingerprint_id']) ? 'disabled' : ''; ?>><i class="bi bi-cloud-arrow-up"></i></button>
                                            <a href="download_template.php?student_no=<?php echo urlencode($st['student_no']); ?>" class="btn btn-sm btn-light border text-success" title="Download Fingerprint" <?php echo empty($st['fingerprint_id']) ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>><i class="bi bi-download"></i></a>
                                            <button onclick="deleteStudentMap('<?php echo htmlspecialchars($st['student_no'], ENT_QUOTES); ?>')" class="btn btn-sm btn-light border text-danger" title="Delete Match"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4 small"><em>No fingerprints enrolled yet</em></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex gap-2">
                    <a href="download_template.php?bulk=true" class="btn btn-sm w-100 fw-semibold btn-success"><i class="bi bi-box-arrow-down me-1"></i>Backup All Records</a>
                    <button onclick="bulkDeleteTemplates()" class="btn btn-sm w-100 fw-semibold btn-danger"><i class="bi bi-exclamation-triangle me-1"></i>Wipe Database</button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-danger text-white py-3">
                <strong><i class="bi bi-shield-lock-fill me-2"></i>Admin Database</strong>
            </div>
            <div class="card-body bg-light">
                <div class="row">
                    <div class="col-md-6 border-end pe-md-4">
                        <h6 class="text-danger fw-bold mb-2"><i class="bi bi-person-badge me-1"></i>Admin Hardware Enrollment</h6>
                        <p class="small text-muted mb-2">Enroll an admin to perform special hardware actions.</p>
                        <div class="input-group mb-2 shadow-sm rounded">
                            <span class="input-group-text bg-danger text-white"><i class="bi bi-person-badge"></i></span>
                            <input type="text" id="enroll-admin-name" class="form-control" placeholder="Admin Name (e.g. Dr. Smith)">
                        </div>
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-white border-danger-subtle"><i class="bi bi-fingerprint text-danger"></i></span>
                            <input type="number" id="enroll-admin-id" class="form-control bg-light text-muted border-danger-subtle" placeholder="Slot ID (1-127)">
                            <button class="btn btn-outline-danger fw-semibold" onclick="triggerAdminEnroll()">
                                <i class="bi bi-plus-circle me-1"></i>Enroll Finger
                            </button>
                        </div>
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-white border-danger-subtle"><i class="bi bi-credit-card text-danger"></i></span>
                            <input type="text" id="enroll-admin-rfid" class="form-control border-danger-subtle" placeholder="RFID Tag UID">
                            <button class="btn btn-outline-danger fw-semibold px-2" onclick="autoFindRfid('enroll-admin-rfid')" title="Auto Find Latest Scan"><i class="bi bi-search"></i></button>
                            <button class="btn btn-danger text-white fw-semibold" onclick="triggerAdminEnrollRfid()">
                                <i class="bi bi-link me-1"></i>Link RFID
                            </button>
                        </div>
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-white border-danger-subtle"><i class="bi bi-person-bounding-box text-danger"></i></span>
                            <input type="text" id="enroll-admin-face" class="form-control border-danger-subtle" placeholder="Raspberry Pi Face ID (e.g. face_101)">
                            <button class="btn btn-outline-danger fw-semibold px-2" onclick="autoFindFace('enroll-admin-face')" title="Auto Find Latest Scan"><i class="bi bi-search"></i></button>
                            <button class="btn btn-danger text-white fw-semibold" onclick="triggerAdminEnrollFace()">
                                <i class="bi bi-link me-1"></i>Link Face
                            </button>
                        </div>

                        <h6 class="text-danger fw-bold mb-2 border-top pt-3"><i class="bi bi-camera-video me-1"></i>Admin Web Face Enrollment</h6>
                        <p class="small text-muted mb-2">Enroll admin face via webcam for RPi-based recognition.</p>
                        <div class="input-group mb-2 shadow-sm rounded">
                            <span class="input-group-text bg-danger text-white"><i class="bi bi-person-badge"></i></span>
                            <input type="text" id="admin-webface-name" class="form-control" placeholder="Admin Name">
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white border rounded shadow-sm">
                            <i class="bi bi-images text-danger"></i>
                            <label class="form-label small fw-bold mb-0 text-muted flex-shrink-0">Capture Count</label>
                            <input type="range" class="form-range flex-grow-1" min="3" max="15" value="7" id="admin-enroll-capture-count"
                                oninput="document.getElementById('admin-enroll-count-val').textContent=this.value">
                            <span class="badge bg-danger enroll-count-badge" id="admin-enroll-count-val">7</span>
                        </div>
                        <button id="btn-start-admin-enroll" class="btn btn-outline-danger fw-semibold w-100"
                            onclick="startAdminWebFaceEnrollment(document.getElementById('admin-webface-name').value, parseInt(document.getElementById('admin-enroll-capture-count').value))">
                            <i class="bi bi-person-bounding-box me-1"></i>Start Admin Webcam Enrollment
                        </button>
                        <div class="mt-2 mb-2 text-center">
                            <span class="badge bg-secondary mb-1">OR UPLOAD PHOTO</span>
                            <input type="file" id="admin-web-enroll-image" accept="image/*" class="form-control form-control-sm" onchange="adminEnrollUploadedImage(this)">
                        </div>
                        <div id="admin-web-enroll-container" class="d-none border rounded p-2 mt-2 bg-white text-center shadow-sm">
                            <select id="admin-enroll-camera-select" class="form-select form-select-sm mb-2 d-none" onchange="switchAdminEnrollCamera()"></select>
                            <video id="admin-web-enroll-video" autoplay muted playsinline class="w-100 rounded bg-dark mb-2" style="height: auto; display: block;"></video>
                            <div id="admin-web-enroll-status" class="small fw-bold mb-2"></div>
                            <div class="d-flex gap-2">
                                <button id="btn-capture-admin-enroll" class="btn btn-success flex-grow-1 fw-bold d-none"><i class="bi bi-camera me-1"></i>Capture Face</button>
                                <button id="btn-cancel-admin-enroll" class="btn btn-secondary flex-grow-1 fw-bold d-none">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 ps-md-4">
                        <h6 class="text-muted fw-bold mb-2"><i class="bi bi-list-check me-1"></i>Registered Admins</h6>
                        <div class="scroll-y-lg border rounded bg-white shadow-sm">
                            <table class="table table-hover table-sm mb-0 table-xs">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th class="ps-3 text-secondary small fw-bold">Admin Name</th>
                                        <th class="text-secondary small fw-bold">Finger</th>
                                        <th class="text-secondary small fw-bold">RFID</th>
                                        <th class="text-secondary small fw-bold">Face ID</th>
                                        <th class="text-secondary small fw-bold">Web Face</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($admins) > 0): ?>
                                        <?php foreach ($admins as $a): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold"><?php echo htmlspecialchars($a['admin_name']); ?></td>
                                            <td><?php if ($a['fingerprint_id']): ?><span class="badge bg-primary"><i class="bi bi-fingerprint me-1"></i><?php echo htmlspecialchars($a['fingerprint_id']); ?></span><?php else: ?><span class="text-muted small">-</span><?php endif; ?></td>
                                            <td><?php if ($a['rfid_uid']): ?><span class="badge bg-info"><i class="bi bi-credit-card me-1"></i><?php echo htmlspecialchars($a['rfid_uid']); ?></span><?php else: ?><span class="text-muted small">-</span><?php endif; ?></td>
                                            <td><?php if ($a['face_id']): ?><span class="badge bg-warning text-dark"><i class="bi bi-person-bounding-box me-1"></i><?php echo htmlspecialchars($a['face_id']); ?></span><?php else: ?><span class="text-muted small">-</span><?php endif; ?></td>
                                            <td>
                                                <?php
                                                    $awf = 0;
                                                    if (!empty($a['face_descriptor'])) {
                                                        $ad = json_decode($a['face_descriptor'], true);
                                                        if (is_array($ad) && count($ad) > 0) {
                                                            $awf = is_array($ad[0]) ? count($ad) : 1;
                                                        }
                                                    }
                                                ?>
                                                <?php if ($awf > 0): ?><span class="badge bg-success"><i class="bi bi-camera me-1"></i><?php echo $awf; ?></span><?php else: ?><span class="text-muted small">-</span><?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3 small"><em>No admins registered</em></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Add/Edit Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Add Weekly Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="sched-edit-id" value="">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Course Code</label>
                    <select id="sched-course" class="form-select">
                        <option value="">Select Course...</option>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Day</label>
                        <select id="sched-day" class="form-select">
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label fw-semibold">Start</label>
                        <input type="time" id="sched-start" class="form-control" value="08:00">
                    </div>
                    <div class="col-3">
                        <label class="form-label fw-semibold">End</label>
                        <input type="time" id="sched-end" class="form-control" value="10:00">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Venue (Optional)</label>
                    <input type="text" id="sched-venue" class="form-control" placeholder="e.g. Lab 204">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Target Device</label>
                    <select id="sched-device" class="form-select">
                        <option value="WEB_DASHBOARD">Web Dashboard</option>
                    </select>
                    <div class="form-text">Device that will auto-start this schedule.</div>
                </div>
                <div id="sched-msg" class="small mb-2 min-h-xs"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success fw-semibold" onclick="saveSchedule()"><i class="bi bi-check-lg me-1"></i>Save Schedule</button>
            </div>
        </div>
    </div>
</div>

<script>
// Populate schedule course dropdown
function populateSchedCourseDropdown() {
    fetch('/csc2052/api/student.php?action=get_all_courses')
        .then(r => r.json())
        .then(data => {
            if (!data.courses) return;
            const sel = document.getElementById('sched-course');
            if (!sel) return;
            sel.innerHTML = '<option value="">Select Course...</option>';
            data.courses.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.course_code;
                opt.textContent = c.course_code + (c.course_name ? ' - ' + c.course_name : '');
                sel.appendChild(opt);
            });
        })
        .catch(() => {});
}

function populateSchedDeviceDropdown() {
    fetch('/csc2052/api/devices.php?action=list')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('sched-device');
            if (!sel) return;
            sel.innerHTML = '<option value="WEB_DASHBOARD">Web Dashboard</option>';
            if (data.devices && Array.isArray(data.devices)) {
                data.devices.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.ip_address || d.id || '';
                    opt.textContent = (d.device_name || d.ip_address || 'Unknown') + ' (' + (d.ip_address || 'no IP') + ')';
                    if (opt.value) sel.appendChild(opt);
                });
            }
        })
        .catch(() => {});
}

function loadSchedules() {
    const tbody = document.getElementById('timetable-body');
    if (!tbody) return;
    
    fetch('/csc2052/api/schedule.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (!data.schedules) return;
            
            const dayColors = {
                1: 'table-info', 2: 'table-warning', 3: 'table-success',
                4: 'table-danger', 5: 'table-primary', 6: 'table-secondary'
            };
            const days = {1: 'mon', 2: 'tue', 3: 'wed', 4: 'thu', 5: 'fri', 6: 'sat'};
            const timeSlots = {};
            
            // Group schedules by time slot
            data.schedules.forEach(s => {
                const key = s.start_time + '|' + s.end_time;
                if (!timeSlots[key]) timeSlots[key] = { start: s.start_time, end: s.end_time, days: {} };
                timeSlots[key].days[s.day_of_week] = s;
            });
            
            // Sort time slots
            const sortedSlots = Object.values(timeSlots).sort((a, b) => a.start.localeCompare(b.start));
            
            let html = '';
            sortedSlots.forEach(slot => {
                const startDisplay = slot.start.substring(0, 5);
                const endDisplay = slot.end.substring(0, 5);
                html += '<tr><td class="fw-bold bg-light align-middle">' + startDisplay + '<br><span class="text-muted small">' + endDisplay + '</span></td>';
                
                for (let d = 1; d <= 6; d++) {
                    const s = slot.days[d];
                    if (s) {
                        const courseName = (s.course_name || s.course_code);
                        const venue = s.venue ? '<br><small class="text-muted"><i class="bi bi-geo-alt"></i> ' + escapeHtml(s.venue) + '</small>' : '';
                        const deviceBadge = s.device_id && s.device_id !== 'WEB_DASHBOARD' 
                            ? '<br><span class="badge bg-info text-dark" style="font-size:10px;"><i class="bi bi-cpu"></i> ' + escapeHtml(s.device_id) + '</span>' 
                            : '<br><span class="badge bg-light text-muted" style="font-size:10px;">Web</span>';
                        const autoBadge = s.auto_start == 1 
                            ? '<span class="badge bg-success" style="font-size:10px;margin-left:2px;">Auto</span>' 
                            : '';
                        html += '<td class="' + dayColors[d] + ' align-middle" style="vertical-align:middle;">' +
                            '<div class="fw-bold small">' + escapeHtml(courseName) + '</div>' +
                            venue + deviceBadge + ' ' + autoBadge +
                            '<div class="mt-1">' +
                            '<button class="btn btn-xs btn-outline-danger py-0 px-1" onclick="deleteSchedule(' + s.id + ')" title="Delete"><i class="bi bi-x"></i></button>' +
                            '</div>' +
                            '</td>';
                    } else {
                        html += '<td class="text-muted small align-middle">—</td>';
                    }
                }
                html += '</tr>';
            });
            
            if (sortedSlots.length === 0) {
                html = '<tr><td colspan="7" class="text-muted text-center py-4"><em>No schedules yet. Click "Add Schedule" to create one.</em></td></tr>';
            }
            
            tbody.innerHTML = html;
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center py-3"><em>Failed to load schedules.</em></td></tr>';
        });
}

function saveSchedule() {
    const id = document.getElementById('sched-edit-id').value;
    const course = document.getElementById('sched-course').value;
    const day = document.getElementById('sched-day').value;
    const start = document.getElementById('sched-start').value;
    const end = document.getElementById('sched-end').value;
    const venue = document.getElementById('sched-venue').value;
    const deviceId = document.getElementById('sched-device').value;
    const msg = document.getElementById('sched-msg');
    
    if (!course) { msg.innerHTML = '<span class="text-danger">Select a course.</span>'; return; }
    if (start >= end) { msg.innerHTML = '<span class="text-danger">End time must be after start time.</span>'; return; }
    
    const fd = new FormData();
    fd.append('action', 'save');
    if (id) fd.append('id', id);
    fd.append('course_code', course);
    fd.append('day_of_week', day);
    fd.append('start_time', start);
    fd.append('end_time', end);
    fd.append('venue', venue);
    fd.append('device_id', deviceId);
    
    fetch('/csc2052/api/schedule.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                msg.innerHTML = '<span class="text-success">Saved!</span>';
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
                    loadSchedules();
                    loadCourseManager();
                    document.getElementById('sched-edit-id').value = '';
                    document.getElementById('sched-venue').value = '';
                    document.getElementById('sched-device').value = 'WEB_DASHBOARD';
                    msg.innerHTML = '';
                }, 800);
            } else if (data.status === 'conflict') {
                msg.innerHTML = '<span class="text-danger">' + escapeHtml(data.message) + '</span>';
            } else {
                msg.innerHTML = '<span class="text-danger">' + escapeHtml(data.message) + '</span>';
            }
        })
        .catch(() => { msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function deleteSchedule(id) {
    if (!confirm('Delete this schedule?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    
    fetch('/csc2052/api/schedule.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') loadSchedules();
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// ─── COURSE ENROLLMENT ────────────────────────────────────────────────────
function populateEnrollCourseSelect() {
    fetch('/csc2052/api/student.php?action=get_all_courses')
        .then(r => r.json())
        .then(data => {
            if (!data.courses) return;
            const sel = document.getElementById('enroll-stu-course-select');
            if (!sel) return;
            sel.innerHTML = '<option value="">Select Course...</option>';
            data.courses.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.course_code;
                opt.textContent = c.course_code + (c.course_name ? ' - ' + c.course_name : '');
                sel.appendChild(opt);
            });
        })
        .catch(() => {});
}

function addCourse() {
    const code = document.getElementById('new-course-code').value.trim();
    const name = document.getElementById('new-course-name').value.trim();
    const msg = document.getElementById('course-add-msg');
    if (!code) { msg.innerHTML = '<span class="text-danger">Course code is required.</span>'; return; }
    const fd = new FormData();
    fd.append('action', 'add_course');
    fd.append('course_code', code);
    if (name) fd.append('course_name', name);
    fetch('/csc2052/api/student.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                msg.innerHTML = '<span class="text-success">Course added!</span>';
                document.getElementById('new-course-code').value = '';
                document.getElementById('new-course-name').value = '';
                populateEnrollCourseSelect();
                populateSchedCourseDropdown();
                loadCourseManager();
                setTimeout(() => { msg.innerHTML = ''; }, 2000);
            } else {
                msg.innerHTML = '<span class="text-danger">' + escapeHtml(data.message || 'Failed to add course') + '</span>';
            }
        })
        .catch(() => { msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function deleteCourse(code) {
    if (!confirm('Delete course ' + code + '?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_course');
    fd.append('course_code', code);
    fetch('/csc2052/api/student.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                populateEnrollCourseSelect();
                populateSchedCourseDropdown();
                loadCourseManager();
                location.reload();
            }
        });
}

function enrollStudentCourse() {
    const stuNo = document.getElementById('enroll-stu-course-no').value.trim();
    const course = document.getElementById('enroll-stu-course-select').value;
    const msg = document.getElementById('course-enroll-msg');
    if (!stuNo || !course) { msg.innerHTML = '<span class="text-danger">Student No and Course required.</span>'; return; }
    const fd = new FormData();
    fd.append('action', 'enroll_student_course');
    fd.append('student_no', stuNo);
    fd.append('course_code', course);
    fetch('/csc2052/api/student.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                msg.innerHTML = '<span class="text-success">Enrolled!</span>';
                document.getElementById('enroll-stu-course-no').value = '';
                setTimeout(() => { msg.innerHTML = ''; }, 2000);
            } else {
                msg.innerHTML = '<span class="text-danger">' + escapeHtml(data.message || 'Failed to enroll') + '</span>';
            }
        })
        .catch(() => { msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function bulkEnrollCourseCSV() {
    const fileInput = document.getElementById('course-bulk-csv');
    const log = document.getElementById('course-bulk-log');
    if (!fileInput.files.length) { log.innerHTML = '<span class="text-danger">Select a CSV file.</span>'; return; }
    const file = fileInput.files[0];
    const fd = new FormData();
    fd.append('action', 'bulk_enroll_course_csv');
    fd.append('csv_file', file);
    log.innerHTML = '<span class="text-primary">Processing...</span>';
    fetch('/csc2052/api/student.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                let html = '<span class="text-success">Done! ' + (data.enrolled || 0) + ' enrolled.</span>';
                if (data.errors && data.errors.length) {
                    html += '<ul class="mb-0 mt-1">';
                    data.errors.forEach(e => { html += '<li class="text-danger small">' + escapeHtml(e) + '</li>'; });
                    html += '</ul>';
                }
                log.innerHTML = html;
            } else {
                log.innerHTML = '<span class="text-danger">' + escapeHtml(data.message || 'Failed') + '</span>';
            }
        })
        .catch(() => { log.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function lookupStudentCourses() {
    const stuNo = document.getElementById('lookup-stu-course').value.trim();
    const result = document.getElementById('lookup-courses-result');
    if (!stuNo) { result.innerHTML = '<span class="text-muted">Enter a Student No.</span>'; return; }
    fetch('/csc2052/api/student.php?action=get_student_courses&student_no=' + encodeURIComponent(stuNo))
        .then(r => r.json())
        .then(data => {
            if (data.courses && data.courses.length > 0) {
                let html = '<strong>' + escapeHtml(data.student_name || stuNo) + '</strong><ul class="mb-0 mt-1">';
                data.courses.forEach(c => { html += '<li>' + escapeHtml(c.course_code) + (c.course_name ? ' - ' + escapeHtml(c.course_name) : '') + '</li>'; });
                html += '</ul>';
                result.innerHTML = html;
            } else {
                result.innerHTML = '<span class="text-muted">No courses found for this student.</span>';
            }
        })
        .catch(() => { result.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

// ─── COURSE MANAGER ────────────────────────────────────────────────────
let courseManagerData = {};
let allTeachers = [];

function loadCourseManager() {
    const container = document.getElementById('course-manager-container');
    if (!container) return;
    
    // Load schedules and teachers in parallel
    Promise.all([
        fetch('/csc2052/api/schedule.php?action=list').then(r => r.json()),
        fetch('/csc2052/api/teacher.php?action=list').then(r => r.json()).catch(() => ({status:'error', teachers:[]}))
    ]).then(([data, tData]) => {
        if (!data.schedules) return;
        
        // Store teachers list
        allTeachers = tData.teachers || [];
        
        // Group schedules by course
        const courses = {};
        data.schedules.forEach(s => {
            if (!courses[s.course_code]) {
                courses[s.course_code] = {
                    course_code: s.course_code,
                    course_name: s.course_name || s.course_code,
                    auto_start: s.auto_start == 1,
                    email_threshold: parseInt(s.email_threshold) || 80,
                    email_on_end: s.email_on_end == 1,
                    teachers: [],
                    slots: []
                };
            }
            courses[s.course_code].slots.push(s);
        });
        
        // Attach teachers to courses
        if (tData.course_teachers) {
            tData.course_teachers.forEach(ct => {
                if (courses[ct.course_code]) {
                    courses[ct.course_code].teachers.push(ct);
                }
            });
        }
        
        courseManagerData = courses;
        renderCourseManager();
    }).catch(() => {
        container.innerHTML = '<div class="text-center py-3 text-danger"><em>Failed to load course manager.</em></div>';
    });
}

function renderCourseManager() {
    const container = document.getElementById('course-manager-container');
    if (!container) return;
    
    const courses = Object.values(courseManagerData);
    if (courses.length === 0) {
        container.innerHTML = '<div class="text-center py-4 text-muted"><em>No scheduled courses. Add a schedule to get started.</em></div>';
        return;
    }
    
    // Sort by course code
    courses.sort((a, b) => a.course_code.localeCompare(b.course_code));
    
    let html = '';
    courses.forEach(course => {
        const slotCount = course.slots.length;
        const days = {1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat'};
        
        html += `<div class="border-bottom last:border-0">
            <div class="d-flex align-items-center p-3 bg-white" style="cursor:pointer;" onclick="toggleCourseSlots('${escapeHtml(course.course_code)}')">
                <i class="bi bi-chevron-right me-2 text-muted transition-icon" id="icon-${escapeHtml(course.course_code)}" style="transition:transform 0.2s;"></i>
                <div class="flex-grow-1">
                    <div class="fw-bold">${escapeHtml(course.course_code)}</div>
                    <div class="small text-muted">${escapeHtml(course.course_name)} &middot; ${slotCount} slot(s)`;
        
        // Show assigned teachers
        if (course.teachers && course.teachers.length > 0) {
            html += ` &middot; <i class="bi bi-person-workspace me-1"></i>`;
            course.teachers.forEach(t => {
                html += `<span class="badge bg-info me-1">${escapeHtml(t.teacher_name)}</span>`;
            });
        } else {
            html += ` &middot; <span class="text-warning small"><i class="bi bi-person-x me-1"></i>No teacher assigned</span>`;
        }
        
        html += `</div>
                </div>
                <div class="d-flex align-items-center gap-2 me-2">
                    <span class="badge ${course.auto_start ? 'bg-success' : 'bg-secondary'}">${course.auto_start ? 'Auto' : 'Manual'}</span>
                    <span class="badge bg-info">Threshold: ${course.email_threshold}%</span>
                    <span class="badge ${course.email_on_end ? 'bg-primary' : 'bg-light text-muted'}">${course.email_on_end ? 'Email On End' : 'No Email'}</span>
                </div>
            </div>
            
            <div id="slots-${escapeHtml(course.course_code)}" class="d-none bg-light p-3 border-top">
                <!-- Teacher Assignment -->
                <div class="mb-3 p-2 bg-white rounded border">
                    <label class="small fw-semibold mb-1"><i class="bi bi-person-workspace me-1"></i>Assigned Teachers</label>
                    <div class="d-flex gap-2 mb-2 flex-wrap">`;
        
        if (course.teachers && course.teachers.length > 0) {
            course.teachers.forEach(t => {
                html += `<span class="badge bg-info d-flex align-items-center gap-1">
                    ${escapeHtml(t.teacher_name)}
                    <button class="btn btn-sm btn-outline-light py-0 px-1 lh-1" onclick="removeTeacherFromCourse(${t.tc_id}, '${escapeHtml(course.course_code)}')" title="Remove">&times;</button>
                </span>`;
            });
        } else {
            html += `<span class="text-muted small fst-italic">No teachers assigned</span>`;
        }
        
        html += `</div>
                    <div class="input-group input-group-sm">
                        <select class="form-select" id="teacher-select-${escapeHtml(course.course_code)}">
                            <option value="">Select teacher to assign...</option>`;
        
        allTeachers.forEach(t => {
            const alreadyAssigned = course.teachers && course.teachers.some(ct => ct.teacher_id == t.id);
            if (!alreadyAssigned) {
                html += `<option value="${t.id}">${escapeHtml(t.teacher_name)} (${escapeHtml(t.email)})</option>`;
            }
        });
        
        html += `</select>
                        <button class="btn btn-sm btn-success" onclick="assignTeacherToCourse('${escapeHtml(course.course_code)}')"><i class="bi bi-plus-lg"></i>Assign</button>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto-start-${escapeHtml(course.course_code)}" 
                                ${course.auto_start ? 'checked' : ''} 
                                onchange="updateCourseSetting('${escapeHtml(course.course_code)}', 'auto_start', this.checked ? 1 : 0)">
                            <label class="form-check-label small fw-semibold" for="auto-start-${escapeHtml(course.course_code)}">
                                Auto-start at scheduled time
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="small fw-semibold mb-0">Email Alert Threshold (%)</label>
                        <input type="number" class="form-control form-control-sm mt-1" 
                            id="threshold-${escapeHtml(course.course_code)}" value="${course.email_threshold}" min="0" max="100"
                            onchange="updateCourseSetting('${escapeHtml(course.course_code)}', 'email_threshold', this.value)">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" id="email-on-end-${escapeHtml(course.course_code)}" 
                                ${course.email_on_end ? 'checked' : ''} 
                                onchange="updateCourseSetting('${escapeHtml(course.course_code)}', 'email_on_end', this.checked ? 1 : 0)">
                            <label class="form-check-label small fw-semibold" for="email-on-end-${escapeHtml(course.course_code)}">
                                Send absent report when schedule ends
                            </label>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered text-center mb-0 small">
                        <thead class="table-light">
                            <tr><th>Day</th><th>Start</th><th>End</th><th>Venue</th><th>Device</th><th>Actions</th></tr>
                        </thead>
                        <tbody>`;
        
        course.slots.forEach(slot => {
            html += `<tr>
                <td class="fw-semibold">${days[slot.day_of_week] || slot.day_of_week}</td>
                <td>${slot.start_time ? slot.start_time.substring(0,5) : ''}</td>
                <td>${slot.end_time ? slot.end_time.substring(0,5) : ''}</td>
                <td>${escapeHtml(slot.venue || '—')}</td>
                <td><span class="badge bg-light text-dark">${escapeHtml(slot.device_id || 'Web')}</span></td>
                <td>
                    <button class="btn btn-xs btn-outline-danger py-0 px-2" onclick="deleteSchedule(${slot.id})" title="Delete slot">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
        });
        
        html += `</tbody></table></div>
            </div>
        </div>`;
    });
    
    container.innerHTML = html;
}

function toggleCourseSlots(courseCode) {
    const slotsDiv = document.getElementById('slots-' + courseCode);
    const icon = document.getElementById('icon-' + courseCode);
    if (!slotsDiv) return;
    
    if (slotsDiv.classList.contains('d-none')) {
        slotsDiv.classList.remove('d-none');
        icon.style.transform = 'rotate(90deg)';
    } else {
        slotsDiv.classList.add('d-none');
        icon.style.transform = 'rotate(0deg)';
    }
}

function updateCourseSetting(courseCode, setting, value) {
    const course = courseManagerData[courseCode];
    if (!course) return;
    
    course[setting] = value;
    
    const fd = new FormData();
    fd.append('action', 'update_course_settings');
    fd.append('course_code', courseCode);
    fd.append(setting, value);
    
    fetch('/csc2052/api/schedule.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                loadSchedules();
            }
        })
        .catch(() => {});
}

function assignTeacherToCourse(courseCode) {
    const select = document.getElementById('teacher-select-' + courseCode);
    if (!select || !select.value) return;
    
    const teacherId = select.value;
    const fd = new FormData();
    fd.append('action', 'assign_teacher');
    fd.append('teacher_id', teacherId);
    fd.append('course_code', courseCode);
    
    fetch('/csc2052/api/teacher.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                loadCourseManager();
            } else {
                alert('Error: ' + (data.message || 'Failed to assign teacher'));
            }
        })
        .catch(() => alert('Network error'));
}

function removeTeacherFromCourse(tcId, courseCode) {
    if (!confirm('Remove teacher from this course?')) return;
    
    const fd = new FormData();
    fd.append('action', 'remove_teacher');
    fd.append('tc_id', tcId);
    fd.append('course_code', courseCode);
    
    fetch('/csc2052/api/teacher.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                loadCourseManager();
            } else {
                alert('Error: ' + (data.message || 'Failed to remove teacher'));
            }
        })
        .catch(() => alert('Network error'));
}

document.addEventListener('DOMContentLoaded', () => {
    populateEnrollCourseSelect();
    populateSchedCourseDropdown();
    populateSchedDeviceDropdown();
});
</script>

<?php require 'includes/footer.php'; ?>
