<?php
require 'includes/header_admin.php';

$courses = [];
$emailConfigEmail = defined('EMAIL_FROM') ? EMAIL_FROM : '';
$emailConfigSmtp = 'smtp.gmail.com';
$configFile = __DIR__ . '/includes/email_config.php';
if (file_exists($configFile)) {
    require_once $configFile;
    if (defined('USER_EMAIL')) $emailConfigEmail = USER_EMAIL;
    if (defined('USER_SMTP_HOST')) $emailConfigSmtp = USER_SMTP_HOST;
}
try {
    $courses = $pdo->query("SELECT course_code, course_name FROM courses ORDER BY course_code ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[Lecture] DB error: ' . $e->getMessage());
}
?>

<div class="row mt-2">
    <div class="col-lg-10 mx-auto">
        
        <!-- Email Sender Config -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-envelope-paper me-2"></i>Email Sender Configuration</strong>
                <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#emailConfigBody" aria-label="Toggle Email Configuration"><i class="bi bi-chevron-down"></i></button>
            </div>
            <div class="collapse show" id="emailConfigBody">
                <div class="card-body py-2 bg-light">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Your Email (Sender)</label>
                            <input type="email" id="user-sender-email" class="form-control form-control-sm" placeholder="you@gmail.com" value="<?php echo htmlspecialchars($emailConfigEmail); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">App Password</label>
                            <div class="input-group input-group-sm">
                                <input type="password" id="user-sender-password" class="form-control form-control-sm" placeholder="16-char app password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()" aria-label="Toggle Password Visibility"><i class="bi bi-eye" id="pwd-eye-icon"></i></button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">SMTP Host</label>
                            <select id="user-smtp-host" class="form-select form-select-sm">
                                <option value="smtp.gmail.com" <?php echo $emailConfigSmtp === 'smtp.gmail.com' ? 'selected' : ''; ?>>Gmail</option>
                                <option value="smtp-mail.outlook.com" <?php echo $emailConfigSmtp === 'smtp-mail.outlook.com' ? 'selected' : ''; ?>>Outlook</option>
                                <option value="smtp.yahoo.com" <?php echo $emailConfigSmtp === 'smtp.yahoo.com' ? 'selected' : ''; ?>>Yahoo</option>
                                <option value="custom" <?php echo !in_array($emailConfigSmtp, ['smtp.gmail.com', 'smtp-mail.outlook.com', 'smtp.yahoo.com']) ? 'selected' : ''; ?>>Custom...</option>
                            </select>
                        </div>
                        <div class="col-md-2 <?php echo in_array($emailConfigSmtp, ['smtp.gmail.com', 'smtp-mail.outlook.com', 'smtp.yahoo.com']) ? 'd-none' : ''; ?>" id="custom-smtp-col">
                            <label class="form-label small fw-bold text-muted mb-1">Custom SMTP</label>
                            <input type="text" id="custom-smtp-host" class="form-control form-control-sm" placeholder="smtp.example.com" value="<?php echo htmlspecialchars($emailConfigSmtp); ?>">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-success w-100 fw-semibold" onclick="saveEmailConfig()"><i class="bi bi-save me-1"></i>Save</button>
                        </div>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Default Recipient</label>
                            <input type="email" id="default-recipient" class="form-control form-control-sm" value="<?php echo defined('EMAIL_ADMIN_RECIPIENT') ? EMAIL_ADMIN_RECIPIENT : ''; ?>" placeholder="recipient@example.com">
                        </div>
                        <div class="col-md-2">
                            <span id="email-status-badge" class="badge fs-6 <?php echo defined('EMAIL_ENABLED') && EMAIL_ENABLED ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo defined('EMAIL_ENABLED') && EMAIL_ENABLED ? 'Email Active' : 'Email Disabled'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Absent Students Report -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-warning text-dark py-3">
                <strong><i class="bi bi-calendar-x me-2"></i>Absent Students Report</strong>
            </div>
            <div class="card-body bg-light">
                <p class="text-muted small mb-3">Find out who did not attend a specific course on a given day.</p>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <div class="searchable-select-wrapper position-relative">
                            <input type="text" id="absent-course-search" class="form-control" placeholder="Search courses..." autocomplete="off" oninput="filterDropdown(this, 'absent-course-dropdown', 'absent-course-input')" onfocus="showDropdown('absent-course-dropdown')">
                            <input type="hidden" id="absent-course-input" value="">
                            <div id="absent-course-dropdown" class="searchable-dropdown d-none"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <input type="date" id="absent-date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-warning w-100 fw-semibold" onclick="getAbsentStudents()"><i class="bi bi-search me-1"></i>Lookup</button>
                    </div>
                </div>
                
                <div id="absent-results" class="d-none">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <div class="alert alert-danger py-2 fw-bold text-center mb-0">
                                Absent: <span id="absent-count">0</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-success py-2 fw-bold text-center mb-0">
                                Present: <span id="present-count">0</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-info py-2 fw-bold text-center mb-0">
                                Course: <span id="absent-course-label">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        <div class="flex-grow-1" style="min-width:180px;">
                            <input type="email" id="absent-email-recipient" class="form-control form-control-sm" placeholder="Recipient email...">
                        </div>
                        <button class="btn btn-outline-dark btn-sm fw-semibold" id="btn-email-absent-all" onclick="sendAbsentEmailReport()" disabled>
                            <i class="bi bi-envelope me-1"></i>Send All
                        </button>
                        <button class="btn btn-outline-primary btn-sm fw-semibold" id="btn-email-absent-selected" onclick="sendSelectedAbsentEmails()" disabled>
                            <i class="bi bi-envelope-check me-1"></i>Selected (<span id="absent-selected-count">0</span>)
                        </button>
                        <button class="btn btn-outline-info btn-sm fw-semibold" id="btn-custom-absent-email" onclick="openCustomEmailModal('absent')" disabled>
                            <i class="bi bi-pencil-square me-1"></i>Custom
                        </button>
                        <button class="btn btn-outline-secondary btn-sm fw-semibold" id="btn-download-absent" onclick="downloadAbsentReport()" disabled>
                            <i class="bi bi-download me-1"></i>CSV
                        </button>
                    </div>
                    <div class="table-responsive bg-white border rounded shadow-sm scroll-y-md">
                        <table class="table table-hover table-sm text-center mb-0 align-middle">
                            <thead class="table-danger sticky-top">
                                <tr>
                                    <th style="width:40px;"><input class="form-check-input" type="checkbox" id="absent-select-all" onchange="toggleSelectAll('absent', this.checked)"></th>
                                    <th class="ps-3">Student No</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th style="width:80px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="absent-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Attendance Analytics -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-primary text-white py-3">
                <strong><i class="bi bi-graph-up-arrow me-2"></i>Course Attendance Analytics</strong>
            </div>
            <div class="card-body bg-light border">
                <p class="text-muted small mb-3">Calculate the exact attendance percentage for every student enrolled in a specific course.</p>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-primary text-primary"><i class="bi bi-search"></i></span>
                            <div class="searchable-select-wrapper position-relative flex-grow-1">
                                <input type="text" id="analytics-course-search" class="form-control border-primary" placeholder="Search courses..." autocomplete="off" oninput="filterDropdown(this, 'analytics-course-dropdown', 'analytics-course-input')" onfocus="showDropdown('analytics-course-dropdown')">
                                <input type="hidden" id="analytics-course-input" value="">
                                <div id="analytics-course-dropdown" class="searchable-dropdown d-none"></div>
                            </div>
                            <button class="btn btn-primary fw-semibold" onclick="calculateCourseAnalytics()">Calculate</button>
                        </div>
                    </div>
                </div>
                
                <div id="analytics-results" class="d-none">
                    <div class="alert alert-info py-2 fw-bold shadow-sm d-flex justify-content-between">
                        <span>Total Sessions Held:</span>
                        <span id="analytics-total-sessions" class="badge bg-primary fs-6">0</span>
                    </div>
                    <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                        <label class="small fw-bold text-muted mb-0">Filter:</label>
                        <select id="analytics-filter" class="form-select form-select-sm" style="width:160px;" onchange="filterAnalyticsByAttendance()">
                            <option value="all">All Students</option>
                            <option value="below_80">Below 80%</option>
                            <option value="below_50">Below 50%</option>
                            <option value="critical">Critical (&lt;50%)</option>
                            <option value="warning">Warning (50-79%)</option>
                            <option value="good">Good (80%+)</option>
                        </select>
                        <button class="btn btn-outline-primary btn-sm fw-semibold" id="btn-filter-analytics" onclick="filterAnalyticsByAttendance()">
                            <i class="bi bi-funnel me-1"></i>Apply
                        </button>
                        <div class="flex-grow-1"></div>
                        <button class="btn btn-outline-dark btn-sm fw-semibold" id="btn-email-analytics-all" onclick="sendAnalyticsBulkEmail()" disabled>
                            <i class="bi bi-envelope me-1"></i>Send All
                        </button>
                        <button class="btn btn-outline-primary btn-sm fw-semibold" id="btn-email-analytics-selected" onclick="sendSelectedAnalyticsEmails()" disabled>
                            <i class="bi bi-envelope-check me-1"></i>Selected (<span id="analytics-selected-count">0</span>)
                        </button>
                        <button class="btn btn-outline-info btn-sm fw-semibold" id="btn-custom-analytics-email" onclick="openCustomEmailModal('analytics')" disabled>
                            <i class="bi bi-pencil-square me-1"></i>Custom
                        </button>
                        <button class="btn btn-outline-secondary btn-sm fw-semibold" id="btn-download-analytics" onclick="downloadAnalyticsReport()" disabled>
                            <i class="bi bi-download me-1"></i>CSV
                        </button>
                    </div>
                    <div class="table-responsive bg-white border rounded shadow-sm scroll-y-lg">
                        <table class="table table-hover table-sm text-center mb-0 align-middle">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th style="width:40px;"><input class="form-check-input" type="checkbox" id="analytics-select-all" onchange="toggleSelectAll('analytics', this.checked)"></th>
                                    <th class="ps-3">Student No</th>
                                    <th>Name</th>
                                    <th>Attended</th>
                                    <th>Percentage</th>
                                    <th style="width:80px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="analytics-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Sent Logs -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-secondary text-white py-3 d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-clock-history me-2"></i>Email Sent Logs</strong>
                <div class="d-flex gap-2">
                    <select id="email-log-filter" class="form-select form-select-sm" style="width:140px;" onchange="loadEmailLogs()">
                        <option value="all">All Types</option>
                        <option value="absent_report">Absent Reports</option>
                        <option value="attendance_alert">Attendance Alerts</option>
                        <option value="custom">Custom</option>
                    </select>
                    <button class="btn btn-sm btn-light fw-semibold" onclick="loadEmailLogs()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="email-logs-container" class="table-responsive">
                    <div class="text-center py-3 text-muted"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading email logs...</div>
                </div>
            </div>
        </div>

        <!-- Export Lecture Ledger -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-success text-white py-3">
                <strong><i class="bi bi-file-earmark-arrow-down me-2"></i>Export Lecture Ledger (CSV)</strong>
            </div>
            <div class="card-body bg-light border">
                <p class="text-muted small mb-3">Filter attendance records and select which columns to include.</p>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted mb-1">Start Time</label>
                    <input type="datetime-local" id="export-start" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted mb-1">End Time</label>
                    <input type="datetime-local" id="export-end" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted mb-1">Course Code</label>
                    <div class="searchable-select-wrapper position-relative">
                        <input type="text" id="export-course-search" class="form-control form-control-sm" placeholder="Search courses..." autocomplete="off" oninput="filterDropdown(this, 'export-course-dropdown', 'export-course-input')" onfocus="showDropdown('export-course-dropdown')">
                        <input type="hidden" id="export-course-input" value="">
                        <div id="export-course-dropdown" class="searchable-dropdown d-none"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted mb-1">Device Node</label>
                    <input type="text" id="export-device" class="form-control form-control-sm" placeholder="e.g. Device name from Hardware Nodes">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted mb-1">CSV Columns</label>
                    <div class="row g-1">
                        <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="csv-col-student_no" checked><label class="form-check-label small" for="csv-col-student_no">Student No</label></div></div>
                        <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="csv-col-student_name" checked><label class="form-check-label small" for="csv-col-student_name">Student Name</label></div></div>
                        <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="csv-col-course_code" checked><label class="form-check-label small" for="csv-col-course_code">Course Code</label></div></div>
                        <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="csv-col-device" checked><label class="form-check-label small" for="csv-col-device">Device</label></div></div>
                        <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="csv-col-timestamp" checked><label class="form-check-label small" for="csv-col-timestamp">Timestamp</label></div></div>
                        <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="csv-col-modality" checked><label class="form-check-label small" for="csv-col-modality">Modality</label></div></div>
                        <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="csv-col-is_offline"><label class="form-check-label small" for="csv-col-is_offline">Offline Sync</label></div></div>
                        <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="csv-col-ip_address"><label class="form-check-label small" for="csv-col-ip_address">Device IP</label></div></div>
                        <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="csv-col-id"><label class="form-check-label small" for="csv-col-id">Log ID</label></div></div>
                    </div>
                </div>
                <button class="btn btn-success w-100 fw-semibold shadow-sm mb-2" onclick="exportFilteredCSV()">
                    <i class="bi bi-download me-2"></i>Download Relevant CSV
                </button>
                <a href="export_csv.php" class="btn btn-outline-success btn-sm w-100 fw-semibold">
                    Download Master Ledger (All)
                </a>
            </div>
        </div>
        
        <!-- Custom Email Modal -->
        <div class="modal fade" id="customEmailModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Compose Custom Email</h5>
                        <button type="button" class="btn-close btn-close-white" aria-label="Close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="custom-email-source" value="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Sender</label>
                            <select id="custom-email-sender" class="form-select">
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Recipients</label>
                            <div id="custom-email-recipients" class="small text-muted border rounded p-2 bg-light" style="max-height:100px;overflow-y:auto;"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Subject</label>
                            <input type="text" id="custom-email-subject" class="form-control" placeholder="Email subject...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Message</label>
                            <textarea id="custom-email-body" class="form-control" rows="6" placeholder="Type your message here..."></textarea>
                            <div class="form-text">Use <code>{student_name}</code>, <code>{student_no}</code>, <code>{percentage}</code>, <code>{course}</code> as placeholders.</div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Recipient Email</label>
                            <input type="email" id="custom-email-recipient" class="form-control" placeholder="recipient@example.com">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-info text-white fw-semibold" id="btn-send-custom-email" onclick="sendCustomEmails()">
                            <i class="bi bi-send me-1"></i>Send Emails
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require 'includes/footer.php'; ?>
