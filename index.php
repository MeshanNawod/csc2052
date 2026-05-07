<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Role guard: non-admins go to their own dashboards
if (!isAdmin()) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    if (isTeacher()) { header('Location: teacher_dashboard.php'); exit; }
    if (isStudent()) { header('Location: student_dashboard.php'); exit; }
    header('Location: login.php'); exit;
}

$page = basename($_SERVER['PHP_SELF']);

$is_esp = false;
$is_rpi = false;
$status_file_esp = __DIR__ . '/sys_status_esp32.txt';
$status_file_rpi = __DIR__ . '/sys_status_rpi.txt';

if (file_exists($status_file_esp)) {
    $last_ping = (int)file_get_contents($status_file_esp);
    if ((time() - $last_ping) <= 30) { $is_esp = true; }
}
if (file_exists($status_file_rpi)) {
    $last_ping = (int)file_get_contents($status_file_rpi);
    if ((time() - $last_ping) <= 30) { $is_rpi = true; }
}

$total_students = 0;
$today_attendance = 0;
$courses = [];
$discovered_devices = [];
try {
    $total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?: 0;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE DATE(timestamp) = ?");
    $stmt->execute([$today]);
    $today_attendance = $stmt->fetchColumn() ?: 0;
    $courses = $pdo->query("SELECT course_code, course_name FROM courses ORDER BY course_code ASC")->fetchAll(PDO::FETCH_ASSOC);
    $registry_file = __DIR__ . '/devices_registry.json';
    if (file_exists($registry_file)) {
        $registry = json_decode(file_get_contents($registry_file), true) ?: [];
        $now = time();
        foreach ($registry as $ip => $info) {
            if (empty($info['blocked'])) {
                $online = ($now - (int)$info['last_seen']) <= 30;
                $discovered_devices[] = [
                    'ip'     => $ip,
                    'name'   => $info['name'] ?? 'Unknown Node',
                    'type'   => $info['type'] ?? 'esp32',
                    'online' => $online,
                ];
            }
        }
    }
} catch (PDOException $e) {
    error_log('[Dashboard] DB error: ' . $e->getMessage());
}
?>
<?php require_once 'includes/header_admin.php'; ?>

<style>
body { background: #f8fafc; }
.stat-card { border-radius: 16px !important; overflow: hidden !important; transition: transform 0.2s, box-shadow 0.2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important; }
.ls-wide { letter-spacing: 0.04em; }
.badge-pill { border-radius: 50px !important; }
.log-row { border-bottom: 1px solid #f1f5f9; padding: 0.6rem 0; }
.log-row:last-child { border-bottom: none; }
.card { border-radius: 14px !important; overflow: hidden !important; }
.device-btn { border-radius: 50px !important; }
#ai-assistant-container { display: none !important; }
</style>

<div class="row mb-4 mt-3">
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="stat-card bg-gradient-primary text-white h-100 shadow p-3">
            <div class="card-body p-3 stat-content d-flex flex-column justify-content-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-25 p-3"><i class="bi bi-people-fill fs-4"></i></div>
                    <div>
                        <h6 class="text-uppercase fw-semibold mb-1 opacity-75 tracking-wide ls-wide">Total Enrolled Students</h6>
                        <h2 class="display-4 fw-bolder mb-0"><?php echo $total_students; ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="stat-card bg-white h-100 shadow border-0 p-3">
            <div class="card-body p-3 stat-content d-flex flex-column align-items-center justify-content-center text-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3"><i class="bi bi-clock text-info fs-4"></i></div>
                    <div>
                        <h6 class="text-uppercase fw-semibold mb-1 text-secondary tracking-wide ls-wide"><i class="bi bi-clock me-1 text-primary"></i>System Clock</h6>
                        <h2 class="display-5 fw-bolder mb-0 mt-1" id="live-clock">--:--:--</h2>
                        <small class="text-muted mt-1 fw-semibold text-uppercase" id="live-date">Loading...</small>
                    </div>
                </div>
            </div>
            <script>
            (function(){
                function tick(){
                    var n=new Date();
                    var c=document.getElementById('live-clock');
                    var d=document.getElementById('live-date');
                    if(c) c.textContent=n.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
                    if(d) d.textContent=n.toLocaleDateString('en-GB',{weekday:'long',year:'numeric',month:'short',day:'numeric'});
                }
                tick();
                setInterval(tick,1000);
            })();
            </script>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card bg-gradient-success text-white h-100 shadow p-3">
            <div class="card-body p-3 stat-content d-flex flex-column justify-content-center">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-uppercase fw-semibold mb-0 opacity-75 tracking-wide ls-wide">Today's Attendance</h6>
                    <select id="stat-device-select" class="form-select form-select-sm w-auto text-white fw-bold shadow-sm select-transparent" style="max-width:140px;background:rgba(255,255,255,0.2);border:none;color:#fff;border-radius:50px;" onchange="fetchTodayAttendance()">
                        <option value="" class="text-dark">All Devices</option>
                        <option value="WEB_DASHBOARD" class="text-dark">Web Dashboard</option>
                        <?php foreach ($discovered_devices as $dev): ?>
                        <option value="<?php echo htmlspecialchars($dev['name']); ?>" class="text-dark"><?php echo htmlspecialchars($dev['name']); ?><?php echo $dev['online'] ? '' : ' (Offline)'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <h2 class="display-4 fw-bolder mb-0" id="today-attendance-count"><?php echo $today_attendance; ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <strong class="small"><i class="bi bi-clock-history me-1"></i>Live Attendance Logs</strong>
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="global-voice-mute">
                        <label class="form-check-label small text-muted fw-bold" for="global-voice-mute"><i class="bi bi-volume-mute me-1"></i>Mute</label>
                    </div>
                    <button class="btn btn-sm btn-outline-primary rounded-pill py-0 fw-semibold" onclick="exportLogsCsv()"><i class="bi bi-download me-1"></i>Export CSV</button>
                </div>
            </div>
            <div class="card-body py-2 bg-light border-bottom">
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="text" id="filter-student" class="form-control form-control-sm border-secondary" placeholder="Search student..." oninput="fetchLogs()">
                    </div>
                    <div class="col-md-2">
                        <input type="text" id="filter-course" class="form-control form-control-sm border-secondary" placeholder="Course" oninput="fetchLogs()">
                    </div>
                    <div class="col-md-3">
                        <select id="filter-device" class="form-select form-select-sm border-secondary" onchange="fetchLogs()">
                            <option value="">All Devices</option>
                            <option value="WEB_DASHBOARD">Web Dashboard</option>
                            <?php foreach ($discovered_devices as $dev): ?>
                            <option value="<?php echo htmlspecialchars($dev['name']); ?>"><?php echo htmlspecialchars($dev['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="filter-date-from" class="form-control form-control-sm border-secondary" onchange="fetchLogs()">
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="filter-date-to" class="form-control form-control-sm border-secondary" onchange="fetchLogs()">
                    </div>
                </div>
            </div>
            <div class="card-body p-0 bg-white" style="max-height: 500px; overflow-y: auto;">
                <div id="logs-accordion-container">
                    <div class="text-center text-muted py-4"><em>Loading live logs...</em></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">

        <div class="card shadow-sm border-0 mb-3 bg-white">
            <div class="card-header bg-primary text-white py-2">
                <strong class="small"><i class="bi bi-broadcast me-1"></i>Lecture &amp; Quick Actions</strong>
            </div>
            <div class="card-body py-2">
                <div class="mb-2 p-2 bg-light rounded shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small text-muted fw-bold mb-0"><i class="bi bi-bullseye text-primary me-1"></i>Target Device</label>
                        <div class="d-flex gap-1">
                            <button class="btn btn-xs btn-outline-secondary py-0 px-1 small rounded-pill" onclick="refreshDeviceList(); refreshDeviceDropdown();" title="Scan"><i class="bi bi-arrow-clockwise"></i></button>
                            <button class="btn btn-xs btn-outline-primary py-0 px-1 small rounded-pill" onclick="toggleManageDevices()" title="Manage"><i class="bi bi-gear"></i></button>
                        </div>
                    </div>
                    <div class="searchable-select-wrapper position-relative">
                        <input type="text" id="device-monitor-search" class="form-control form-control-sm border-primary fw-semibold" placeholder="Search devices..." autocomplete="off" oninput="filterDropdown(this, 'device-monitor-dropdown', 'action-target-device')" onfocus="showDropdown('device-monitor-dropdown')">
                        <input type="hidden" id="action-target-device" value="WEB_DASHBOARD">
                        <div id="device-monitor-dropdown" class="searchable-dropdown d-none"></div>
                    </div>
                    <div id="device-manage-panel" class="d-none mt-2">
                        <div id="device-list-table" class="small text-muted"><em>Loading...</em></div>
                    </div>
                </div>

                <h6 class="text-muted fw-bold mb-1 small">Start Active Lecture</h6>
                <div class="input-group input-group-sm mb-2">
                    <div class="searchable-select-wrapper flex-grow-1 position-relative">
                        <input type="text" id="start-course-search" class="form-control border-primary" placeholder="Search courses..." autocomplete="off" oninput="filterDropdown(this, 'start-course-dropdown', 'start-course-input')" onfocus="showDropdown('start-course-dropdown')" onkeydown="filterDropdown(this, 'start-course-dropdown', 'start-course-input')">
                        <input type="hidden" id="start-course-input" value="">
                        <div id="start-course-dropdown" class="searchable-dropdown d-none"></div>
                    </div>
                    <select id="lecture-timer-preset" class="form-select form-select-sm border-secondary" style="width:90px;" onchange="applyTimerPreset(this.value)">
                        <option value="0">No Timer</option>
                        <option value="30">30 min</option>
                        <option value="60">1 hour</option>
                        <option value="90">1h 30m</option>
                        <option value="120">2 hours</option>
                        <option value="custom">Custom</option>
                    </select>
                    <input type="number" id="lecture-timer-custom" class="form-control form-control-sm border-secondary" style="width:65px;display:none;" placeholder="Min" min="1" max="480">
                    <button class="btn btn-primary fw-semibold rounded-pill" id="btn-start-course" onclick="startCourseToDevice()"><i class="bi bi-play-fill"></i></button>
                    <button class="btn btn-outline-danger fw-semibold px-2 rounded-pill" id="btn-end-course" onclick="endCourseToDevice()" disabled><i class="bi bi-stop-fill"></i></button>
                </div>
                <div id="lecture-timer-display" class="d-none mb-2">
                    <div class="d-flex align-items-center justify-content-between small">
                        <span class="text-muted"><i class="bi bi-clock me-1"></i>Auto-end in:</span>
                        <span id="lecture-timer-countdown" class="fw-bold text-danger"></span>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill" onclick="cancelLectureTimer()">Cancel</button>
                    </div>
                </div>

                <h6 class="text-muted fw-bold mb-1 small">Sync Courses to SD</h6>
                <div class="input-group input-group-sm mb-2">
                    <div class="searchable-select-wrapper flex-grow-1 position-relative">
                        <input type="text" id="sync-courses-input" class="form-control border-secondary" placeholder="Search & add courses..." autocomplete="off" oninput="filterDropdown(this, 'sync-course-dropdown', null)" onfocus="showDropdown('sync-course-dropdown')" onkeydown="filterDropdown(this, 'sync-course-dropdown', null)">
                        <div id="sync-course-dropdown" class="searchable-dropdown d-none"></div>
                    </div>
                    <button class="btn btn-secondary fw-semibold rounded-pill" onclick="syncCoursesToScanner()"><i class="bi bi-sd-card"></i></button>
                </div>

                <h6 class="text-muted fw-bold mb-1 small">Hardware Mode</h6>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-success w-100 fw-semibold rounded-pill" onclick="sendOtaCommand('ATTENDANCE_MODE')"><i class="bi bi-person-check me-1"></i>Attendance</button>
                    <button class="btn btn-sm btn-outline-warning w-100 fw-semibold rounded-pill" onclick="sendOtaCommand('ENROLL_MODE')"><i class="bi bi-fingerprint me-1"></i>Enroll</button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3 bg-white">
            <div class="card-header bg-info text-white py-2 d-flex justify-content-between align-items-center">
                <strong class="small"><i class="bi bi-collection-play me-1"></i>Active Lectures</strong>
                <span id="active-lecture-count" class="badge bg-white text-info badge-pill">0</span>
            </div>
            <div class="card-body py-2" style="max-height:180px;overflow-y:auto;">
                <div id="active-lectures-container">
                    <div class="text-muted small text-center py-2"><i class="bi bi-inbox me-1"></i>No active lectures</div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3 bg-white">
            <div class="card-header bg-success text-white py-2">
                <strong class="small"><i class="bi bi-person-check me-1"></i>Mark Attendance Manually</strong>
            </div>
            <div class="card-body py-2">
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1 fw-bold">Course (Optional)</label>
                        <div class="searchable-select-wrapper position-relative">
                            <input type="text" id="manual-course-search" class="form-control form-control-sm" placeholder="Search..." autocomplete="off" oninput="filterDropdown(this, 'manual-course-dropdown', 'manual-course-code')" onfocus="showDropdown('manual-course-dropdown')">
                            <input type="hidden" id="manual-course-code" value="">
                            <div id="manual-course-dropdown" class="searchable-dropdown d-none"></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1 fw-bold">Time (Optional)</label>
                        <input type="datetime-local" id="manual-timestamp" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="input-group mb-1 shadow-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-person-check text-success"></i></span>
                    <input type="text" id="manual-student-no" class="form-control" placeholder="e.g. S/20/123" oninput="searchStudentName(this.value)">
                    <button class="btn btn-success fw-semibold rounded-end-pill" onclick="markManualAttendance()">Present</button>
                </div>
                <div id="manual-student-name-display" class="form-text text-muted small px-1 min-h-xs"></div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3 bg-white">
            <div class="card-header bg-warning text-dark py-2">
                <strong class="small"><i class="bi bi-camera-video me-1"></i>Webcam Attendance</strong>
            </div>
            <div class="card-body py-2">
                <button id="btn-start-attendance" class="btn btn-warning w-100 fw-bold shadow-sm btn-sm rounded-pill" onclick="startWebFaceAttendance()">
                    <i class="bi bi-play-circle me-1"></i>Start Camera Scanner
                </button>
                <div id="web-attendance-container" class="d-none border rounded p-2 mt-2 bg-light text-center shadow-sm">
                    <select id="attendance-camera-select" class="form-select form-select-sm mb-2 d-none" onchange="switchAttendanceCamera()"></select>
                    <div class="position-relative w-100 rounded bg-dark mb-2" style="line-height:0;overflow:hidden;">
                        <video id="web-attendance-video" autoplay muted playsinline class="w-100" style="height:auto;display:block;"></video>
                        <canvas id="web-attendance-overlay" style="position:absolute;top:0;left:0;pointer-events:none;width:100%;height:100%;"></canvas>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div id="web-attendance-status" class="small fw-bold"></div>
                        <span id="web-head-count-badge" class="badge bg-secondary d-none badge-pill">Faces: 0</span>
                    </div>
                    <div class="mb-2 p-1 border rounded bg-white">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="webFaceMode" id="modeAttendance" value="attendance" checked>
                            <label class="form-check-label fw-bold text-success small" for="modeAttendance"><i class="bi bi-person-check-fill me-1"></i>Mark</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="webFaceMode" id="modeIdentify" value="identify">
                            <label class="form-check-label fw-bold text-primary small" for="modeIdentify"><i class="bi bi-search me-1"></i>Identify</label>
                        </div>
                    </div>
                    <div class="d-flex justify-content-center align-items-center gap-3 mb-2">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="web-attendance-auto">
                            <label class="form-check-label small text-muted fw-bold" for="web-attendance-auto"><i class="bi bi-lightning-charge me-1"></i>Auto</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="web-attendance-mute">
                            <label class="form-check-label small text-muted fw-bold" for="web-attendance-mute"><i class="bi bi-volume-mute me-1"></i>Mute</label>
                        </div>
                    </div>
                    <button id="btn-stop-attendance" class="btn btn-danger btn-sm w-100 fw-bold d-none rounded-pill"><i class="bi bi-stop-circle me-1"></i>Stop Scanner</button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3 bg-white">
            <div class="card-header bg-dark text-white py-2">
                <strong class="small"><i class="bi bi-camera-reels me-1"></i>Raspberry Pi Camera</strong>
            </div>
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span id="rpi-status-dot" class="badge bg-secondary badge-pill"><i class="bi bi-circle me-1"></i>Offline</span>
                        <small id="rpi-ip-display" class="text-muted">Not connected</small>
                    </div>
                    <button class="btn btn-sm btn-outline-light rounded-pill" onclick="refreshRpiStatus()" title="Check Pi"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
                <div id="rpi-face-container" class="border rounded p-2 bg-light text-center shadow-sm">
                    <div class="position-relative w-100 rounded bg-dark mb-2" style="line-height:0;overflow:hidden;aspect-ratio:4/3;">
                        <img id="rpi-cam-stream" src="" alt="Pi Camera Feed" class="w-100 rounded" style="display:none;object-fit:cover;">
                        <div id="rpi-cam-placeholder" class="d-flex align-items-center justify-content-center text-muted" style="height:160px;">
                            <div><i class="bi bi-camera-reels" style="font-size:2rem;"></i><p class="small mt-1 mb-0">Pi Camera Offline</p></div>
                        </div>
                        <canvas id="rpi-attendance-overlay" style="position:absolute;top:0;left:0;pointer-events:none;width:100%;height:100%;"></canvas>
                    </div>
                    <div id="rpi-attendance-status" class="small fw-bold mb-2"></div>
                    <div class="d-flex justify-content-center align-items-center gap-3 mb-2">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="rpi-attendance-auto">
                            <label class="form-check-label small text-muted fw-bold" for="rpi-attendance-auto"><i class="bi bi-lightning-charge me-1"></i>Auto</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="rpi-attendance-mute" checked>
                            <label class="form-check-label small text-muted fw-bold" for="rpi-attendance-mute"><i class="bi bi-volume-mute me-1"></i>Mute</label>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="btn-start-rpi-attendance" class="btn btn-dark btn-sm w-100 fw-bold shadow-sm rounded-pill" onclick="startRpiAttendance()"><i class="bi bi-play-circle me-1"></i>Start Pi</button>
                        <button id="btn-stop-rpi-attendance" class="btn btn-danger btn-sm w-100 fw-bold d-none rounded-pill" onclick="stopRpiAttendance()"><i class="bi bi-stop-circle me-1"></i>Stop</button>
                    </div>
                </div>
                <div class="mt-2">
                    <h6 class="text-muted fw-bold mb-1 small"><i class="bi bi-terminal me-1"></i>Pi Configuration</h6>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text bg-white"><i class="bi bi-hdd-network"></i></span>
                        <input type="text" id="rpi-node-ip" class="form-control" placeholder="Pi IP (e.g. 192.168.1.100)">
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm w-100 fw-semibold rounded-pill" onclick="saveRpiConfig()"><i class="bi bi-save me-1"></i>Save</button>
                        <button class="btn btn-outline-dark btn-sm w-100 fw-semibold rounded-pill" onclick="triggerRpiOta()"><i class="bi bi-cloud-arrow-down me-1"></i>Pull Queue</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require 'includes/footer.php'; ?>
