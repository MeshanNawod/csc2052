</div> <!-- End of Global Container -->

<!-- AI Assistant -->
<div id="ai-assistant-container">
    <button id="ai-assistant-toggle" class="btn btn-primary rounded-circle shadow-lg ai-assistant-toggle" onclick="toggleAiAssistant()" title="AI Assistant" aria-controls="ai-assistant-panel" aria-expanded="false">
        <i class="bi bi-robot"></i>
    </button>
    <div id="ai-assistant-panel" class="d-none position-fixed ai-assistant-panel" role="dialog" aria-label="Sentinel AI Assistant">
        <div class="bg-primary text-white p-3 d-flex align-items-center">
            <i class="bi bi-robot fs-4 me-2"></i>
            <div class="flex-grow-1">
                <strong>Sentinel AI Assistant</strong>
                <div class="small opacity-75">Ask me anything about the system</div>
            </div>
            <button class="btn btn-sm btn-outline-light py-0 px-2" aria-label="Close AI Assistant" onclick="toggleAiAssistant()"><i class="bi bi-x"></i></button>
        </div>
        <div id="ai-chat-messages" class="flex-grow-1 p-3 overflow-auto" style="max-height:340px;min-height:200px;background:#f8f9fa;">
            <div class="mb-2">
                <div class="d-inline-block bg-white rounded-pill px-3 py-2 small shadow-sm">
                    <i class="bi bi-robot text-primary me-1"></i>Hi! I know the <strong>entire codebase</strong> — web pages, ESP32 firmware (1239 lines), all 13 APIs, database, hardware wiring, email, face recognition, and more.<br><br>Ask me about: errors, setup, features, code, debugging.
                </div>
            </div>
        </div>
        <div class="p-2 border-top bg-white">
            <div class="input-group input-group-sm">
                <input type="text" id="ai-chat-input" class="form-control" placeholder="Type your question..." onkeydown="if(event.key==='Enter')sendAiMessage()">
                <button class="btn btn-primary" aria-label="Send message" onclick="sendAiMessage()"><i class="bi bi-send"></i></button>
            </div>
            <div class="d-flex gap-1 mt-1 flex-wrap">
                <button class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="sendAiQuickQ('How do I start a lecture?')">Start lecture</button>
                <button class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="sendAiQuickQ('How to enroll a student?')">Enroll</button>
                <button class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="sendAiQuickQ('Email not sending')">Email fix</button>
                <button class="btn btn-xs btn-outline-danger py-0 px-2" onclick="sendAiQuickQ('ESP32 offline fix')">ESP offline</button>
                <button class="btn btn-xs btn-outline-warning py-0 px-2" onclick="sendAiQuickQ('Fingerprint not working')">FP issue</button>
                <button class="btn btn-xs btn-outline-info py-0 px-2" onclick="sendAiQuickQ('Database connection error')">DB error</button>
            </div>
        </div>
    </div>
</div>

<script>
// ─── Helper: escapeHtml (must load before main.js defer) ──────────────
window.escapeHtml = function(unsafe) {
    if (!unsafe) return '-';
    return unsafe.toString()
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
};

window.toggleAiAssistant = function() {
    const panel = document.getElementById('ai-assistant-panel');
    if (!panel) return;
    if (panel.classList.contains('d-none')) {
        panel.classList.remove('d-none');
        panel.style.display = 'flex';
        document.getElementById('ai-assistant-toggle')?.setAttribute('aria-expanded', 'true');
        const inp = document.getElementById('ai-chat-input');
        if (inp) inp.focus();
    } else {
        panel.classList.add('d-none');
        panel.style.display = 'none';
        document.getElementById('ai-assistant-toggle')?.setAttribute('aria-expanded', 'false');
    }
};

window.sendAiQuickQ = function(q) {
    const inp = document.getElementById('ai-chat-input');
    if (inp) { inp.value = q; }
    window.sendAiMessage();
};

window.sendAiMessage = function() {
    const input = document.getElementById('ai-chat-input');
    if (!input) return;
    const msg = input.value.trim();
    if (!msg) return;
    
    const messages = document.getElementById('ai-chat-messages');
    if (!messages) return;
    
    messages.innerHTML += '<div class="mb-2 text-end"><div class="d-inline-block bg-primary text-white rounded-pill px-3 py-2 small">' + window.escapeHtml(msg) + '</div></div>';
    input.value = '';
    
    setTimeout(function() {
        const response = window.getAiResponse(msg);
        messages.innerHTML += '<div class="mb-2"><div class="d-inline-block bg-white rounded-pill px-3 py-2 small shadow-sm"><i class="bi bi-robot text-primary me-1"></i>' + response + '</div></div>';
        messages.scrollTop = messages.scrollHeight;
    }, 300);
    
    messages.scrollTop = messages.scrollHeight;
};

window.getAiResponse = function(q) {
    const lower = q.toLowerCase();
    const kb = {
        
        // ─── ERRORS & TROUBLESHOOTING ────────────────────────────────
        'error|fix|broken|not working|fail|stuck|bug': {
            'dropdown|search|textbox': 'Search dropdowns use <code>filterDropdown()</code> + <code>showDropdown()</code> from main.js. Items are populated by <code>refreshAllCourseDropdowns()</code> on page load. If broken: 1) Check browser console (F12) 2) Verify <code>/csc2052/api/student.php?action=get_all_courses</code> returns JSON 3) Check CSS class <code>.searchable-dropdown</code> exists in style.css:735',
            'email|mail|send|smtp': 'Email uses native PHP SMTP via <code>fsockopen()</code> in student.php. Setup: 1) Lectures page → enter Gmail + App Password 2) Select SMTP host (Gmail/Outlook/Yahoo) 3) Click Save → writes to includes/email_config.php 4) Test by sending absent report. Common errors: wrong app password (use Google Account → App Passwords), firewall blocking port 587, or EMAIL_ENABLED=false in config.php',
            'log|attendance|live|loading': 'Live Logs stuck on "Loading..." means fetchLogs() failed. Check: 1) F12 console for errors 2) Network tab → logs.php response 3) If HTML returned → session expired, relogin 4) Verify attendance_logs table exists in DB 5) Check includes/auth.php is not blocking the API. The logs API at api/logs.php requires active session',
            'database|db|connection|pdo|mysql': 'DB connection in includes/db.php using PDO. Fix: 1) Check XAMPP MySQL is running 2) Verify db.php credentials (host/db/user/pass) 3) Check database exists in phpMyAdmin 4) Look for PDOException in PHP error log 5) Test connection via PHP CLI or phpMyAdmin',
            'device|esp|offline|connect|heartbeat': 'ESP32 connects via WiFi to server. Heartbeat every 10s to api/heartbeat.php. If offline: 1) Check navbar pills (green=online) 2) Verify ESP WiFi credentials in esp32_firmware.ino line 28-29 3) Check SERVER_IP matches your PC IP 4) ESP queues scans in LittleFS offline_queue.csv 5) Syncs automatically when reconnected 6) Check serial monitor for errors',
            'face|recognition|model|load|detect': 'Face recognition uses face-api.js from CDN. Models load from /models/ folder. If not working: 1) Check network tab for face-api.js loading 2) Verify models folder exists with .json/.bin files 3) Browser console for "model load" errors 4) Webcam enrollment needs HTTPS or localhost 5) Photos must be named student_no.jpg for bulk upload',
            'fingerprint|fm10a|sensor|enroll|scan': 'FM10A sensor on ESP32 UART (Serial2: TX=17, RX=16). Issues: 1) Wiring: VCC=5V, GND, TX→GPIO16, RX→GPIO17 2) Enrollment: 3 finger presses required 3) Max 127 templates in sensor flash 4) If "Could not process image" → clean sensor 5) Link to student via api/student.php?action=link_student 6) ESP stores slot ID, DB links slot→student_no',
            'rfid|mfrc522|card|tag|read': 'RFID uses MFRC522 on SPI: SS=GPIO5, RST=GPIO22, MOSI=GPIO23, MISO=GPIO19, SCK=GPIO18. Fix: 1) Check wiring 2) Card UID sent to api/attendance.php 3) Auto-fills student form on students.php via api/rfid_scanned.php 4) BLE scanner mode can read cards via phone 5) Enable/disable in device settings',
            'wifi|network|connect|scan|wpa': 'ESP32 WiFi: STA mode, connects to configured SSID. In Hardware Nodes → Wi-Fi: 1) Scan Air discovers networks 2) Supports WPA2-PSK and WPA2-EAP (enterprise) 3) Credentials saved to ESP Preferences (NVRAM) 4) Persists across reboots 5) 8s timeout, retries every 15s 6) For university networks use WPA2-EAP with identity',
            'lcd|display|screen|i2c|20x4': 'LCD: LiquidCrystal_I2C at 0x27, 20x4 chars. Lines mirrored to web via api/sse_monitor.php. If blank: 1) Check I2C address (scan with I2C scanner sketch) 2) SDA=GPIO21, SCL=GPIO22 3) Adjust contrast potentiometer on back 4) lcdOK flag set false if init fails 5) Web shows LCD mirror in Hardware Nodes page',
            'rtc|clock|time|ds3231|date': 'DS3231 RTC on I2C for accurate timestamps. If wrong time: 1) Check battery (CR2032) 2) RTC validated in getTimestamp() — falls back to BOOT+HH:MM:SS 3) NTP sync not implemented, set via serial or dashboard 4) Invalid dates logged as warnings 5) Required for correct attendance timestamps',
            'auth|login|session|expire|redirect|401': 'Auth: includes/auth.php checks $_SESSION[\'admin_logged_in\']. Sessions last 1 hour (SESSION_LIFETIME). If redirected to login: 1) Session expired 2) Relogin at /csc2052/login.php 3) Default: admin/admin123 4) Rate limited: 5 attempts, 15s lockout 5) CSRF tokens generated per session',
            'serial|monitor|usb|upload|flash': 'Serial monitor: Hardware Nodes → Serial Monitor polls via api/ota.php?action=poll&rpi. ESP32: 1) Use Arduino IDE or PlatformIO 2) ESP32 Dev Module, 115200 baud 3) Web serial via /api/ota.php 4) Firmware in esp32_firmware/ 5) OTA commands: enroll, reboot, start_course, sync',
            'ota|update|remote|push|command': 'OTA: Dashboard sends commands to ESP32 via HTTP POST. api/ota.php handles: enroll_fingerprint, reboot, sync_queue, start_course, end_course, wifi_config. ESP32 web server on port 80 receives commands. Commands queued and executed in loop(). Response logged to web serial buffer.'
        },
        
        // ─── WEB PAGES ───────────────────────────────────────────────
        'dashboard|index|home|main page': 'index.php: Dashboard with stats (total students, today attendance, today count chart), Live Attendance Logs (grouped by course→date→time), Lecture & Quick Actions (start/stop courses, device targeting), Recent Activity. Polls logs every 5s via fetchLogs()',
        'students|student base|enroll|add student': 'students.php: Student management. Features: add/edit/delete students, enroll fingerprint (via ESP32), link RFID, link face photo, webcam enrollment (face-api.js), bulk CSV upload, course enrollment, Course Manager (per-course auto-start/threshold/email settings), Weekly Course Schedule, RFID auto-fill polling',
        'lecture|lectures|export|csv|absent|analytics': 'lecture.php: Absent Students Report (lookup by course+date, email absentees), Course Attendance Analytics (percentage bars, filter by attendance level, email alerts), Email Sender Configuration (SMTP credentials, save to email_config.php), Export Ledger (CSV with column selection, date/course/device filters), Email Sent Logs (history with type filter), Custom Email Modal with placeholders',
        'hardware|device|nodes|esp|settings': 'device.php: Hardware Nodes management. Features: device discovery, rename, block/unblock, serial monitor, Wi-Fi config (scan/connect/WPA2-EAP), Quick Modes (enroll, reboot, sync, LCD test), Device Settings (enable/disable FP/RFID/Face, MFA, enroll count), Twin Device pairing, RPi IP config, OTA firmware push',
        'instructions|help|docs|documentation': 'instructions.php: Full documentation covering: system overview, hardware setup, enrollment, attendance, email reports, course management, schedules, CSV export, troubleshooting. Updated with all new feature docs.',
        'login|auth|password|admin': 'login.php: Admin authentication. Default: admin/admin123. Hash stored in config.php (ADMIN_PASSWORD_HASH). Rate limited: MAX_LOGIN_ATTEMPTS=5, LOCKOUT=15s. Session lifetime: 1 hour. CSRF token generated. Redirects to requested page after login.',
        
        // ─── ESP32 FIRMWARE ─────────────────────────────────────────
        'esp32|firmware|arduino|code|flash|sketch': 'ESP32 Firmware (esp32_firmware.ino, 1239 lines): Libraries: WiFi, HTTPClient, WebServer(80), Preferences, Wire, RTC_DS3231, Adafruit_Fingerprint, LittleFS, MFRC522, LiquidCrystal_I2C, BLE. Key functions: tryConnectWifi(), sendHeartbeat() every 10s, markAttendance() POST to server, saveToOfflineQueue() to LittleFS CSV, syncOfflineQueue() on reconnect, runEnrollment() 3-press wizard, sendFingerprint() search sensor, readRFID() get UID, handleWebServer() routes: /api/attendance, /api/enroll, /logs, /lcd, /serial, /reboot, /wifi, /settings',
        'offline|queue|sync|littlefs|csv': 'ESP32 Offline System: When server unreachable, attendance saved to LittleFS /offline_queue.csv as "finger_id,modality,timestamp". syncOfflineQueue() POSTs each line to server on reconnect. Successful records removed, failed ones kept. Queue count shown on LCD. Triggered automatically every 30s when online.',
        'enroll|enrollment|fingerprint|wizard|slot': 'ESP32 Enrollment: Triggered via serial ("enroll") or web OTA. runEnrollment() gets next slot from api/student.php?action=get_next_finger_id, then 3-press wizard: press→capture template1, remove, press→capture template2, createModel(), storeModel(slotId). Then links to student via api/student.php?action=link_student. enrollCount configurable (default 3).',
        'heartbeat|online|status|ping|register': 'ESP32 Heartbeat: sendHeartbeat() GET to api/heartbeat.php?device=esp32&name=UoP_Scanner_1 every 10s. If successful → isOnline=true, device registered in devices_registry.json. If fail → isOnline=false, serial dots printed. Server detects offline devices by last_seen timestamp (30s threshold).',
        'attendance|mark|scan|record|post|log': 'ESP32 Attendance: markAttendance() POST to api/attendance.php with finger_id, device_name, course_code, timestamp, modality. Server validates, checks duplicates (5min window), logs to attendance_logs. If offline → saveToOfflineQueue(). Admin fingerprint (slot 0) ends current lecture and triggers sync.',
        'ble|bluetooth|scanner|phone|card': 'ESP32 BLE Scanner: Uses BLEDevice, BLEScan to detect nearby BLE devices. Can scan for RFID cards via phone BLE apps. pBLEScan configured in setup(). bleScannerEnabled toggled via settings. Discovered devices logged to serial and web.',
        'preferences|nvram|config|settings|save': 'ESP32 Preferences (NVRAM): Uses Preferences library to persist settings. Keys: ENABLE_FP, ENABLE_RFID, ENABLE_FACE, MFA, ENROLL_COUNT, WIFI_SSID, WIFI_PASS, WIFI_EAP_IDENTITY, WIFI_EAP_PASS, SERVER_IP. Loaded on boot via loadDeviceConfig(). Saved via web settings POST.',
        
        // ─── API ENDPOINTS ──────────────────────────────────────────
        'api|endpoint|route|url': 'API Endpoints: /api/attendance.php (POST: record attendance), /api/student.php (GET: get_all_courses, get_email_config; POST: add/edit/delete/link, send_absent_report_email, send_custom_email, save_email_config, get_email_logs), /api/schedule.php (GET: auto_start_check; POST: CRUD, update_course_settings), /api/logs.php (GET: fetch_logs, get_courses, today_count), /api/devices.php (GET: list; POST: register, rename, block), /api/heartbeat.php (GET: register device), /api/settings.php (GET/POST: device settings), /api/ota.php (POST: commands, GET: poll serial), /api/rfid_scanned.php (GET: consume RFID), /api/face_scanned.php (GET: consume face), /api/analytics.php (GET: course stats), /api/upload_faces.php (POST: bulk face photos), /api/sse_monitor.php (GET: SSE for LCD/serial)',
        'attendance api|record|post|log entry': 'api/attendance.php: POST with finger_id, device_name, course_code, timestamp, modality. Validates student exists, checks duplicate (5min window), inserts to attendance_logs. Returns status: success, duplicate, or admin (triggers lecture end). Supports offline_sync flag.',
        'student api|add|edit|delete|link': 'api/student.php: Main student CRUD. Actions: add_student, update_student, delete_student, link_student (finger_id→student_no), link_rfid, link_face, unlink_face, get_next_finger_id, get_student_courses, send_absent_report_email, send_custom_email, save_email_config, get_email_logs. All POST except GET for course list.',
        'schedule api|timetable|auto_start|course settings': 'api/schedule.php: Actions: add_schedule, update_schedule, delete_schedule, get_schedules, auto_start_check (polling), update_course_settings (auto_start, email_threshold, email_on_end, device_id per course). Auto-start checks every 30s on dashboard.',
        'logs api|fetch|filter|today_count': 'api/logs.php: Actions: fetch_logs (filter by student_no, course_code, date_from, date_to, device, returns 500 most recent), get_courses (distinct course codes from logs), today_count (count for dashboard chart). All GET. Requires auth session.',
        'devices api|discovery|registry|block|rename': 'api/devices.php: Actions: list (reads devices_registry.json, checks online status by last_seen < 30s), register (ESP32 heartbeat creates/updates entry), rename (update device name), block (toggle blocked flag). Registry stores IP, name, type, last_seen, blocked.',
        'heartbeat api|register|online|status': 'api/heartbeat.php: GET with device=esp32|rpi, name, ip. Creates/updates devices_registry.json entry with last_seen=now(). Returns "OK". Called by ESP32 every 10s. Server uses this for online/offline detection.',
        'settings api|device config|fp|rfid|face|mfa': 'api/settings.php: GET: device settings by device_id. POST: save settings (ENABLE_FP, ENABLE_RFID, ENABLE_FACE, MFA, ENROLL_COUNT, LCD_LINES). Sends commands to ESP32 via HTTP POST to device IP. Settings stored on ESP NVRAM.',
        'ota api|remote|command|push|serial|reboot': 'api/ota.php: POST: send commands to ESP32 (enroll_fingerprint, reboot, sync, start_course, end_course, wifi_config, lcd_test). GET: poll serial logs, RPi status. Commands sent to device IP:80. Serial logs stored in buffer and served as plain text.',
        'rfid api|consume|auto_fill|poll': 'api/rfid_scanned.php: GET: consume last RFID scan. Returns {student_no, student_name, status}. Called by students.php every 1.5s to auto-fill enrollment form. RFID UID matched to student_courses → students.',
        'face api|scanned|consume|recognition': 'api/face_scanned.php: GET: consume last face recognition result. Returns {student_no, student_name, status, confidence}. Called by face_recognition.js for real-time face matching. Flask RPi service can also post results here.',
        'analytics api|stats|percentage|chart': 'api/analytics.php: GET: course statistics. Returns total students, attendance counts, percentages. Used for dashboard chart and analytics page calculations.',
        'upload api|face|photo|bulk|image': 'api/upload_faces.php: POST: bulk face photo upload. Photos named student_no.jpg. Extracts face using face-api.js, saves to faces/ directory. Returns success/fail per student.',
        'sse api|monitor|lcd|serial|stream': 'api/sse_monitor.php: GET: Server-Sent Events for real-time LCD mirror and serial monitor output. Streams updates when ESP32 LCD lines change or serial logs arrive.',
        
        // ─── DATABASE ───────────────────────────────────────────────
        'database|db|table|schema|sql|mysql|pdo': 'Database: MySQL via PDO. Tables: students (student_no PK, student_name, finger_id, rfid_uid, face_path, created_at), courses (course_code PK, course_name), student_courses (student_no, course_code FK), attendance_logs (id PK, student_no, device_name, timestamp, course_code, modality, is_offline_sync), admins (id, admin_name, admin_username, admin_password), courses_schedules (id, course_code, day, start_time, end_time, device_id, auto_start, email_threshold, email_on_end), email_sent_logs (id, sender_email, recipient_email, subject, message_type, course_code, student_no, student_name, body, sent_at, status)',
        'students table|student_no|finger_id|rfid': 'students table: student_no (PK, VARCHAR 50), student_name (VARCHAR 100), finger_id (INT, sensor slot), rfid_uid (VARCHAR 20), face_path (VARCHAR 255), created_at (TIMESTAMP). Finger ID links to FM10A slot. RFID UID from MFRC522. Face path to photo in faces/ directory.',
        'attendance_logs|logs|records|history': 'attendance_logs table: id (AUTO_INCREMENT PK), student_no (FK), device_name (VARCHAR 50), timestamp (DATETIME), course_code (VARCHAR 20), modality (VARCHAR 20: fingerprint, rfid, face, 2fa, manual), is_offline_sync (TINYINT 0/1). Indexed by timestamp DESC for fast queries.',
        'courses table|course|code|name': 'courses table: course_code (PK, VARCHAR 20), course_name (VARCHAR 100). Linked to student_courses (many-to-many) and attendance_logs. Used in all dropdowns and reports.',
        'email_logs|sent|history|tracking': 'email_sent_logs table: id (AUTO_INCREMENT), sender_email, recipient_email, subject, message_type (absent_report/attendance_alert/custom), course_code, student_no, student_name, body (HTML), sent_at (TIMESTAMP), status. Tracks all system emails for audit.',
        
        // ─── EMAIL SYSTEM ──────────────────────────────────────────
        'email|mail|smtp|send|report|absent|notification': 'Email System: 1) Config: Lectures page → Email Sender Config → enter Gmail + App Password → Save (writes includes/email_config.php) 2) Uses native SMTP via fsockopen() TLS on port 587 — no PHPMailer needed 3) Absent Report: full HTML with summary boxes + table 4) Custom Email: placeholders {student_name}, {student_no}, {percentage}, {course} 5) Auto-email: on lecture end if email_on_end=1, or when attendance below threshold 6) All emails logged to email_sent_logs 7) Config: EMAIL_ENABLED, EMAIL_FROM in config.php',
        'auto email|threshold|alert|automatic|trigger': 'Auto Email Triggers: 1) End-of-course: when lecture ends, if schedule has email_on_end=1 → sends attendance summary 2) Threshold alert: checkLowAttendanceEmail() polls every 60s, if student attendance < email_threshold → sends alert 3) Deduplication: low_attendance_sent localStorage prevents duplicate sends 4) Configured per-course in Course Manager',
        'custom email|template|placeholder|bulk|compose': 'Custom Email: Open via "Custom" button in absent/analytics reports. Modal with: sender dropdown, recipient list (checked students), subject, message body. Placeholders: {student_name}, {student_no}, {percentage}, {course}. Each student gets personalized email. Sent via sendCustomEmails() in main.js.',
        
        // ─── FEATURES ──────────────────────────────────────────────
        'start|lecture|course|begin|active': 'Start Lecture: Dashboard → Lecture & Quick Actions → select target device (Web Dashboard or ESP32) → search course → Start. Sends START_COURSE command to device. Device shows course on LCD. Attendance tracked until Stop or timer expires. Timer: preset (15/30/45/60/90min) or custom minutes.',
        'enroll|student|fingerprint|rfid|face|add': 'Enroll Student: Students Base → enter student_no + name → choose method: 1) Enroll Finger: sends command to ESP32, 3-press wizard, slot auto-assigned 2) Link RFID: scan card, UID stored 3) Link Face: webcam capture (7 angles) or upload photo 4) CSV bulk upload 5) Link existing finger_id manually. All methods link student_no to biometric in DB.',
        'course manager|settings|per-course|threshold|auto': 'Course Manager: Students Base → expandable course cards showing all time slots. Per-course settings: auto_start (auto-begin at schedule time), email_threshold (attendance % for alerts), email_on_end (send summary when lecture ends), device_id (target specific ESP32). Applied to all slots of that course.',
        'schedule|timetable|weekly|auto-start|slot': 'Schedule System: Students Base → Weekly Course Schedule → add day+start_time+end_time+course. Auto-start: dashboard polls api/schedule.php?action=auto_start_check every 30s, starts course if current time matches schedule. Course Manager configures auto-start behavior per course.',
        'csv|export|import|bulk|download|upload': 'CSV Operations: Export: Lectures page → Export Ledger (filter by date/course/device, select columns), Absent Report → Download CSV, Analytics → Download CSV. Import: Students Base → Bulk CSV Upload (student_no, student_name, course_code). All exports use proper escaping and headers.',
        'face recognition|webcam|model|detect|identify': 'Face Recognition: Uses face-api.js (CDN). Models in /models/ (ssd, face_landmark, face_recognition). Webcam Enrollment: capture 7 angles, extracts descriptors, saves to faces/student_no.jpg. Real-time: face_recognition.js polls camera, matches against loaded descriptors. Bulk upload: photos named student_no.jpg → processed by upload_faces.php.',
        'multi-factor|2fa|mfa|security|fraud': 'Multi-Factor (2FA): Requires 2+ sensor matches for attendance (e.g., fingerprint AND RFID). Enabled in Hardware Nodes → Device Settings → Require Multi-Factor. Prevents fraud (buddy punching). ESP32 tracks last sensor, requires different sensor within time window. Modality stored as "2fa" in attendance_logs.',
        'timer|preset|custom|auto-end|duration': 'Lecture Timer: Dashboard → Lecture & Quick Actions → Timer dropdown: No Timer, 15min, 30min, 45min, 60min, 90min, or Custom (enter minutes). Auto-ends lecture, sends email if configured. Timer displayed in active lectures. State persisted in localStorage as active_lectures.',
        
        // ─── HARDWARE ──────────────────────────────────────────────
        'hardware|wiring|pin|gpio|connect|schematic': 'Hardware: ESP32 Dev Board. FM10A: TX→GPIO16, RX→GPIO17, VCC=5V, GND. MFRC522: SS→5, RST→22, MOSI→23, MISO→19, SCK→18. LCD I2C: SDA→21, SCL→22, 0x27 address. DS3231 RTC: SDA→21, SCL→22, 3.3V. All I2C devices share bus. Power: 5V USB or external.',
        'pin|gpio|wiring|connection|schematic': 'ESP32 GPIO Map: GPIO16=FM10A RX, GPIO17=FM10A TX, GPIO5=RFID SS, GPIO22=RFID RST + I2C SCL, GPIO23=RFID MOSI, GPIO19=RFID MISO, GPIO18=RFID SCK, GPIO21=I2C SDA. Serial2 (mySerial) for FM10A. SPI for MFRC522. Wire for I2C (LCD+RTC).',
        'lcd|display|20x4|i2c|0x27|mirror': 'LCD: 20x4 I2C at 0x27. Lines mirrored to web via SSE (api/sse_monitor.php). Shows: course, mode, attendance count, queue count. Updated by lcdWrite(row, text). 4 lines stored in lcdLine[] array. Web mirror in Hardware Nodes page updates via Server-Sent Events.',
        'rtc|clock|ds3231|time|battery|cr2032': 'RTC: DS3231 precision RTC on I2C. CR2032 battery backup. getTimestamp() validates year 2020-2099, falls back to BOOT+HH:MM:SS if invalid. Required for accurate attendance timestamps. No NTP sync — set via serial or dashboard.',
        
        // ─── HOW TO USE ────────────────────────────────────────────
        'help|what can|guide|tutorial|how to use|start': 'I know the ENTIRE system codebase — web pages, ESP32 firmware, all APIs, database, hardware, email, face recognition, and more. Ask me anything: errors, setup, features, code explanation, troubleshooting. Try: "ESP offline", "email not sending", "how to enroll", "fingerprint not working", "database error", "start lecture", "CSV export", "schedule setup", "2FA", "face recognition", "WiFi config".',
        'thank|thanks|appreciate|good job': "You're welcome! I'm here to help with any part of the Sentinel Swarm AMS system. Just ask!",
        'who are you|what are you|about you': "I'm the Sentinel AI Assistant — built into this AMS dashboard. I have detailed knowledge of every file: web pages (index.php, students.php, lecture.php, device.php), ESP32 firmware (1239 lines), all 13 API endpoints, database schema, hardware wiring, email system, face recognition, and more. Ask me anything about the system!"
    };
    
    // Match against knowledge base
    for (const [keys, responses] of Object.entries(kb)) {
        const keyPatterns = keys.split('|');
        if (keyPatterns.some(p => lower.includes(p))) {
            if (typeof responses === 'string') return responses;
            // Object with sub-patterns
            for (const [subKeys, response] of Object.entries(responses)) {
                const subPatterns = subKeys.split('|');
                if (subPatterns.some(p => lower.includes(p))) return response;
            }
            // No sub-pattern matched, return first response
            return Object.values(responses)[0];
        }
    }
    
    return "I'm trained on the entire Sentinel Swarm AMS codebase. Try asking about: <strong>errors</strong> (what's broken), <strong>hardware</strong> (ESP32, FM10A, RFID, LCD), <strong>email</strong> (setup, SMTP, auto-send), <strong>enrollment</strong>, <strong>schedules</strong>, <strong>face recognition</strong>, <strong>API endpoints</strong>, <strong>database</strong>, or <strong>any specific feature</strong>. I can explain code, debug issues, and guide you through any process.";
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Face API library for Web-based Face Recognition -->
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script defer src="<?php echo htmlspecialchars(asset_url('js/face_recognition.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script defer src="<?php echo htmlspecialchars(asset_url('js/main.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
