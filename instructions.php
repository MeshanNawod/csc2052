<?php
require 'includes/header_admin.php';
?>
<div class="row mt-2">
    <div class="col-lg-10 mx-auto">

        <!-- ═══ Getting Started ═══ -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-gradient-primary text-white py-3">
                <strong><i class="bi bi-rocket-takeoff me-2"></i>Getting Started</strong>
            </div>
            <div class="card-body bg-light">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-primary"><i class="bi bi-1-circle me-1"></i>Hardware Setup</h6>
                        <p class="small text-muted mb-0">Power on your ESP32 node. It will boot in <strong>AP Hotspot Mode</strong> (SSID: <code>SentinelSwarm</code>). Connect to it, or if it has saved WiFi credentials it will connect automatically. Enter the node's IP in the <strong>Node IP</strong> field in the navbar. The green/red pill shows if the ESP32 and Raspberry Pi are online (heartbeat within 30 seconds).</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-primary"><i class="bi bi-2-circle me-1"></i>Wi-Fi &amp; NVRAM</h6>
                        <p class="small text-muted mb-0">The ESP32 uses C++ <code>Preferences</code> (NVRAM flash) to store WiFi credentials persistently. To switch networks, go to <a href="device.php" class="fw-bold">Hardware Nodes &rarr; Wi-Fi</a> and use <strong>Scan Air</strong> to discover networks, select one, enter the password, and click <strong>Save &amp; Reboot</strong>. For university/corporate networks, enable <strong>Enterprise WiFi (WPA2-EAP)</strong> to provide your identity/username for PEAP/MSCHAPv2 authentication.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Dashboard ═══ -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-dark text-white py-3">
                <strong><i class="bi bi-display me-2"></i>Dashboard (index.php)</strong>
            </div>
            <div class="card-body bg-light">
                <h6 class="fw-bold text-dark"><i class="bi bi-speedometer2 me-1"></i>Stats Row</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Total Enrolled Students</strong> — Count of all students in the database with at least one template (fingerprint, RFID, or face).</li>
                    <li><strong>System Clock</strong> — Live 24-hour clock synced to your server's time. The ESP32 RTC can be synced to this via <strong>Hardware Nodes &rarr; Quick Modes &rarr; Sync Time</strong>.</li>
                    <li><strong>Today's Attendance</strong> — Count of attendance records logged today. Use the dropdown to filter by a specific device node.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-clock-history me-1"></i>Live Attendance Logs</h6>
                <ul class="small text-muted mb-3">
                    <li>Real-time stream of all attendance events from all devices. Updates every 3 seconds.</li>
                    <li><strong>Filters</strong> — Search by student number/name, course code, device node, and date range.</li>
                    <li><strong>Mute Voice</strong> — Globally silences browser speech synthesis announcements for new attendance entries.</li>
                    <li><strong>Export Filtered CSV</strong> — Downloads a CSV of the currently filtered log results.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-journal-check me-1"></i>Lecture &amp; Quick Actions</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Target Device</strong> — Select which ESP32 node receives commands. Click the gear icon to scan/register/rename devices.</li>
                    <li><strong>Start Active Lecture</strong> — Pick a course and click <strong>Start</strong> to begin the attendance session on the selected device. The device LCD will show the course code. Click the red <strong>Stop</strong> button to end the lecture.</li>
                    <li><strong>Sync Courses to SD</strong> — Pushes a comma-separated list of course codes to the ESP32's LittleFS for offline course validation.</li>
                    <li><strong>Hardware Mode</strong> — <strong>Attendance</strong> switches the ESP32 to read fingerprints/RFID for attendance. <strong>Enroll</strong> switches to fingerprint enrollment mode.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-person-check me-1"></i>Mark Attendance Manually</h6>
                <ul class="small text-muted mb-3">
                    <li>Enter a student number (e.g. <code>S/20/123</code>) and click <strong>Present</strong>. The student's name auto-fills as you type.</li>
                    <li><strong>Course Code</strong> (optional) — Attaches the attendance to a specific course session.</li>
                    <li><strong>Timestamp</strong> (optional) — Backdate the attendance record. Leave empty for current time.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-camera-video me-1"></i>Webcam Attendance</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Start Camera Scanner</strong> — Opens your browser's webcam for face recognition using face-api.js.</li>
                    <li><strong>Mark Mode</strong> — Auto-marks attendance when a known face is detected.</li>
                    <li><strong>Identify Mode</strong> — Displays the detected student's name without marking attendance.</li>
                    <li><strong>Auto Toggle</strong> — When enabled, automatically marks attendance without confirmation.</li>
                    <li><strong>Mute Toggle</strong> — Silences voice announcements for this scanner only.</li>
                    <li><strong>Camera Selector</strong> — Choose between front/rear cameras on devices with multiple webcams.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-camera-reels me-1"></i>Raspberry Pi Camera</h6>
                <ul class="small text-muted mb-3">
                    <li>Connects to the Flask-based face recognition service running on a Raspberry Pi 3B.</li>
                    <li><strong>Start Pi</strong> — Streams the Pi's camera feed and polls for face detections via <code>/api/face_scanned</code>.</li>
                    <li><strong>Auto Toggle</strong> — Automatically marks attendance when the Pi recognizes a face.</li>
                    <li><strong>Pi IP</strong> — Enter the Pi's LAN IP (e.g. <code>192.168.1.100</code>). Saved to localStorage.</li>
                    <li><strong>Pull Queue</strong> — Fetches any pending OTA commands from the server's <code>api/ota.php</code> queue and sends them to the Pi.</li>
                    <li><strong>Match Threshold</strong> — Set on the Pi side. Controls the cosine similarity cutoff for face matches (lower = more permissive).</li>
                </ul>
            </div>
        </div>

        <!-- ═══ Students Base ═══ -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-primary text-white py-3">
                <strong><i class="bi bi-journal-bookmark-fill me-2"></i>Students Base (students.php)</strong>
            </div>
            <div class="card-body bg-light">
                <h6 class="fw-bold text-dark"><i class="bi bi-tag me-1"></i>Course Management</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Add New Course</strong> — Enter a course code (e.g. <code>PHY1911</code>) and optional name. Click <strong>Add</strong>.</li>
                    <li><strong>Enroll Student in Course</strong> — Link a student number to a course. They will only be counted for attendance when that course's lecture is active.</li>
                    <li><strong>Bulk Enroll via CSV</strong> — Upload a CSV file named after the course code (e.g. <code>MAT3063.csv</code>). Each row: <code>Student No, Name</code>. Creates students and enrolls them in the course automatically.</li>
                    <li><strong>Course Enrollment Lookup</strong> — Enter a student number to see all courses they are enrolled in.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-folder2-open me-1"></i>Course Manager</h6>
                <ul class="small text-muted mb-3">
                    <li>Groups all schedule slots by course — one course can have many time slots.</li>
                    <li>Click a course row to expand and see all its slots in a table (Day, Start, End, Venue, Device).</li>
                    <li>Per-course settings (applies to all slots of that course):
                        <ul>
                            <li><strong>Auto-start</strong> — Automatically starts the lecture at the scheduled time on the selected device.</li>
                            <li><strong>Email Alert Threshold (%)</strong> — Auto-emails when student attendance drops below this percentage.</li>
                            <li><strong>Send absent report when schedule ends</strong> — Automatically emails the absent students list when the scheduled time slot ends.</li>
                        </ul>
                    </li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-calendar-week me-1"></i>Weekly Course Schedule</h6>
                <ul class="small text-muted mb-3">
                    <li>Timetable view showing all scheduled slots across Monday–Saturday.</li>
                    <li>Each cell shows the course name, venue, target device badge, and auto-start status.</li>
                    <li><strong>Add Time Slot</strong> — Creates a new schedule entry with course, day, start/end time, venue, and device.</li>
                    <li>Conflict detection prevents overlapping slots on the same device.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-person-badge me-1"></i>Hardware Enrollment Link</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Student No / Name</strong> — Enter the student's registration number. The name is optional but recommended.</li>
                    <li><strong>Enroll Finger</strong> — Sends <code>ENROLL &lt;slot&gt;</code> to the ESP32. The student must place their finger on the FM10A sensor <em>enroll_count</em> times (default 3). The slot ID is auto-assigned from the next available number (1–127).</li>
                    <li><strong>Link RFID</strong> — Paste the hex UID from an RFID tag (e.g. <code>04A3B2C1D4E5F0</code>). Click the <strong>search</strong> button to auto-fill the latest scanned RFID from the logs. Then click <strong>Link RFID</strong>.</li>
                    <li><strong>Link Face</strong> — Enter the face profile ID assigned by the Raspberry Pi (e.g. <code>face_101</code>). Click the <strong>search</strong> button to auto-fill the latest face scan from the logs. Then click <strong>Link Face</strong>.</li>
                    <li><strong>Bulk Enroll (Images)</strong> — Upload multiple photos named as student numbers (e.g. <code>S-20-123.jpg</code>). Hyphens/underscores are auto-converted to slashes (<code>S/20/123</code>). Each image is processed by face-api.js to generate a face descriptor stored in the database.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-camera-video me-1"></i>Web Face Enrollment</h6>
                <ul class="small text-muted mb-3">
                    <li>Uses your browser's webcam to capture face images and generate descriptors via face-api.js.</li>
                    <li><strong>Capture Count</strong> — Slider (3–15). Higher count = more angles = better recognition accuracy. Default is 7.</li>
                    <li><strong>Start Webcam Enrollment</strong> — Opens the camera. Click <strong>Capture Face</strong> for each angle (front, left, right, etc.).</li>
                    <li><strong>Upload Photo</strong> — Alternatively, upload a single photo to generate a descriptor without using the webcam.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-person-plus me-1"></i>Profile Names &amp; CSV Import</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Add Student</strong> — Quick form to create a student record (number + name) without any biometric data.</li>
                    <li><strong>Import CSV</strong> — Upload a two-column CSV (no headers): <code>Student No, Full Name</code>. Creates or updates student records in bulk.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-table me-1"></i>Mapped Templates Table</h6>
                <ul class="small text-muted mb-3">
                    <li>Shows every student with their enrolled biometric templates: fingerprint slot, RFID UID, hardware face ID, and web face descriptor count.</li>
                    <li><strong>Attendance %</strong> — Color-coded badge: green (&ge;80%), yellow (50–79%), red (&lt;50%), gray (0%). Filter by course using the dropdown.</li>
                    <li><strong>Edit</strong> (pencil) — Modify student details or template assignments.</li>
                    <li><strong>Push to ESP</strong> (cloud up) — Sends a fingerprint template download command to the ESP32 for the selected student's slot.</li>
                    <li><strong>Download</strong> (download) — Downloads a fingerprint template backup file for this student.</li>
                    <li><strong>Delete</strong> (trash) — Removes the student's database record. Does <em>not</em> delete from the ESP32 sensor — use the <strong>Wipe</strong> button on Hardware Nodes for that.</li>
                    <li><strong>Backup All Records</strong> — Downloads a CSV dump of the entire student database.</li>
                    <li><strong>Wipe Database</strong> — Deletes <em>all</em> student records. <strong>This cannot be undone!</strong> Back up first.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-shield-lock me-1"></i>Admin Database</h6>
                <ul class="small text-muted mb-3">
                    <li>Admins are enrolled separately from students and can perform special hardware actions (e.g. initiating bulk template wipe on the ESP32).</li>
                    <li>Admins can be enrolled with fingerprint, RFID, and face modalities just like students.</li>
                    <li><strong>Admin Web Face Enrollment</strong> — Same as student web face enrollment but stores descriptors in the <code>admins</code> table.</li>
                </ul>
            </div>
        </div>

        <!-- ═══ Lectures & Export ═══ -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-warning text-dark py-3">
                <strong><i class="bi bi-journal-album me-2"></i>Lectures &amp; Export (lecture.php)</strong>
            </div>
            <div class="card-body bg-light">
                <h6 class="fw-bold text-dark"><i class="bi bi-envelope-paper me-1"></i>Email Sender Configuration</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Sender Address</strong> — Choose who emails are sent from. Options include the system default and registered admins.</li>
                    <li><strong>Default Recipient</strong> — Set the default email address for reports and alerts. Auto-fills on the report pages.</li>
                    <li>The badge shows whether email is active (green) or disabled (gray).</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-calendar-x me-1"></i>Absent Students Report</h6>
                <ul class="small text-muted mb-3">
                    <li>Select a course and date, then click <strong>Lookup</strong>.</li>
                    <li>Shows all students enrolled in that course who did <em>not</em> have an attendance record on the selected date.</li>
                    <li><strong>Checkboxes</strong> — Select individual students or use the header checkbox to select all.</li>
                    <li><strong>Send All</strong> — Sends the full absent report to the recipient email with an HTML-formatted summary.</li>
                    <li><strong>Selected (N)</strong> — Sends individual absence notices only to checked students.</li>
                    <li><strong>Custom</strong> — Opens a compose window with placeholders: <code>{student_name}</code>, <code>{student_no}</code>, <code>{percentage}</code>, <code>{course}</code>.</li>
                    <li><strong>CSV</strong> — Downloads the absent students list as a CSV file.</li>
                    <li><strong>Individual email button</strong> (envelope icon) — Sends a single absence notice for that student.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-graph-up-arrow me-1"></i>Course Attendance Analytics</h6>
                <ul class="small text-muted mb-3">
                    <li>Select a course and click <strong>Calculate</strong>.</li>
                    <li>Shows each enrolled student's attendance percentage with color-coded badges and progress bars:
                        <ul>
                            <li><span class="badge bg-success">80%+</span> Good attendance</li>
                            <li><span class="badge bg-warning text-dark">50-79%</span> Warning zone</li>
                            <li><span class="badge bg-danger">Below 50%</span> Critical</li>
                        </ul>
                    </li>
                    <li><strong>Filter</strong> — Narrow the view: All, Below 80%, Below 50%, Critical, Warning, or Good.</li>
                    <li><strong>Send All</strong> — Sends attendance alerts to all visible students (respecting the active filter).</li>
                    <li><strong>Selected (N)</strong> — Sends alerts only to checked students.</li>
                    <li><strong>Custom</strong> — Compose personalized emails using placeholders.</li>
                    <li><strong>CSV</strong> — Downloads the analytics table as CSV.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-clock-history me-1"></i>Email Sent Logs</h6>
                <ul class="small text-muted mb-3">
                    <li>Shows a history of all emails sent from the system.</li>
                    <li>Columns: Date, Type (Absent Report, Attendance Alert, Custom), Course, Student, Recipient, Subject.</li>
                    <li><strong>Filter dropdown</strong> — Filter by type: All, Absent Reports, Attendance Alerts, or Custom.</li>
                    <li>Updated automatically after each email is sent.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-file-earmark-arrow-down me-1"></i>Export Lecture Ledger (CSV)</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Start / End Time</strong> — Filter records within a specific date-time range.</li>
                    <li><strong>Course Code</strong> — Filter by a specific course. Leave as "All Courses" for everything.</li>
                    <li><strong>Device Node</strong> — Filter by the device name that recorded the attendance (e.g. <code>ESP32-Lab1</code>).</li>
                    <li><strong>Download Relevant CSV</strong> — Exports only the filtered results.</li>
                    <li><strong>Download Master Ledger</strong> — Exports every attendance record in the database, no filters applied.</li>
                </ul>
            </div>
        </div>

        <!-- ═══ AI Assistant ═══ -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-info text-white py-3">
                <strong><i class="bi bi-robot me-2"></i>AI Assistant</strong>
            </div>
            <div class="card-body bg-light">
                <p class="small text-muted mb-2">The <strong>AI Assistant</strong> (robot icon, bottom-right corner) is available on every page. Click it to open a chat interface that can answer questions about:</p>
                <div class="row g-2">
                    <div class="col-md-6">
                        <ul class="small text-muted mb-0">
                            <li>How to start lectures and manage courses</li>
                            <li>Student enrollment (fingerprint, RFID, face)</li>
                            <li>Email reports and notifications</li>
                            <li>Device offline troubleshooting</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="small text-muted mb-0">
                            <li>Schedule management and auto-start</li>
                            <li>CSV export and bulk operations</li>
                            <li>WiFi configuration</li>
                            <li>Multi-factor authentication (2FA)</li>
                        </ul>
                    </div>
                </div>
                <p class="small text-muted mt-2 mb-0">Use the <strong>quick question buttons</strong> for common topics, or type any question in the chat input.</p>
            </div>
        </div>

        <!-- ═══ Hardware Nodes ═══ -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-success text-white py-3">
                <strong><i class="bi bi-motherboard me-2"></i>Hardware Nodes &amp; Devices (device.php)</strong>
            </div>
            <div class="card-body bg-light">
                <h6 class="fw-bold text-dark"><i class="bi bi-cpu me-1"></i>ESP32 Control Center</h6>
                <p class="small text-muted mb-2">All controls below operate on the device selected in the <strong>Target ESP32</strong> dropdown at the top of the page. This dropdown is populated from the <code>devices_registry.json</code> file.</p>

                <h6 class="fw-bold text-dark"><i class="bi bi-bar-chart-line me-1"></i>Node Telemetry</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Battery</strong> — Reads the ADC voltage on GPIO34. Shows approximate battery percentage when a LiPo battery is connected. Shows N/A if no battery hardware is wired.</li>
                    <li><strong>Storage (LittleFS)</strong> — Flash memory usage in KB. Stores offline attendance queue CSV and synced course files.</li>
                    <li><strong>WiFi RSSI</strong> — Signal strength in dBm. Closer to 0 is better (e.g. -40 dBm = excellent, -80 dBm = poor).</li>
                    <li><strong>Offline Queue Count</strong> — Number of attendance records queued locally because the device had no internet. These sync automatically when connectivity returns.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-wifi me-1"></i>Wi-Fi Configuration</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Scan Air</strong> — Sends <code>SCANWIFI</code> to the ESP32. Returns nearby SSIDs in a dropdown list.</li>
                    <li><strong>Password</strong> — For personal/home networks (WPA/WPA2-PSK).</li>
                    <li><strong>Enterprise WiFi (WPA2-EAP)</strong> — Toggle this for university/corporate networks. Enter your identity/username in the field that appears. Uses PEAP/MSCHAPv2.</li>
                    <li><strong>Save &amp; Reboot</strong> — Sends <code>SETWIFI SSID|PASSWORD|IDENTITY</code>. Credentials are saved to NVRAM <code>Preferences</code>. Device reboots and reconnects.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-bluetooth me-1"></i>Bluetooth Scanner</h6>
                <p class="small text-muted mb-3">Sends <code>SCANBT</code>. Scans for nearby BLE devices for 5 seconds. Returns names and MAC addresses.</p>

                <h6 class="fw-bold text-dark"><i class="bi bi-sd-card me-1"></i>LittleFS / Storage Control</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>List Files on Flash</strong> (<code>LISTFS</code>) — Lists all files on LittleFS with sizes.</li>
                    <li><strong>Read Offline Queue</strong> (<code>DUMP_OFFLINE</code>) — Displays the contents of <code>offline_queue.csv</code>.</li>
                    <li><strong>Force Sync Queue</strong> (<code>SYNC_OFFLINE</code>) — Immediately pushes all queued records to the server.</li>
                    <li><strong>Wipe Offline Queue</strong> (<code>CLEARLOGS</code>) — Deletes <code>offline_queue.csv</code>. Removes all unsynced records.</li>
                    <li><strong>Format LittleFS (Danger)</strong> (<code>FORMAT_FS</code>) — Completely erases the flash filesystem. <strong>All data will be lost!</strong></li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-fingerprint me-1"></i>Fingerprint Templates</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Upload Templates to ESP</strong> — Tells ESP32 to prepare for receiving template data from the server.</li>
                    <li><strong>Download Templates from ESP</strong> — Reports how many templates are stored on the FM10A sensor.</li>
                    <li><strong>Wipe All FP Templates</strong> — Deletes the entire fingerprint database on the FM10A sensor. <strong>Cannot be undone!</strong></li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-lightning-charge me-1"></i>Quick Modes &amp; OTA Commands</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Attendance</strong> (<code>ATTENDANCE_MODE</code>) — Normal operating mode. Reads fingerprints/RFID and sends attendance to the server.</li>
                    <li><strong>Enroll</strong> (<code>ENROLL_MODE</code>) — Awaits a specific <code>ENROLL &lt;slot&gt;</code> command from the Students page to capture a new fingerprint.</li>
                    <li><strong>Sync Time</strong> (<code>SYNC_TIME</code>) — Syncs the DS3231 RTC hardware clock to the firmware compile time.</li>
                    <li><strong>Reboot</strong> (<code>REBOOT</code>) — Restarts the ESP32 immediately.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-gear me-1"></i>Device Settings</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Fingerprint / RFID / Face</strong> — Enable or disable individual sensor modalities globally on the device.</li>
                    <li><strong>Require Multi-Factor</strong> — If enabled, requires 2+ sensor matches (e.g. fingerprint + RFID) to mark attendance. Enforces 2FA.</li>
                    <li><strong>Enroll Count</strong> — Number of times a finger must be placed during enrollment (1–5). Higher = more reliable template.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-terminal me-1"></i>Online Serial Monitor</h6>
                <p class="small text-muted mb-1">Real-time TX (transmit) and RX (receive) log of all commands sent to and received from the ESP32 via its built-in HTTP server on port 80.</p>
                <ul class="small text-muted mb-3">
                    <li><strong>Auto-Scroll</strong> — Toggle automatic scrolling to the latest entry.</li>
                    <li><strong>Clear</strong> — Wipes the log display.</li>
                    <li>Type custom commands in the input field or use the quick buttons below.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-display me-1"></i>Digital Twin Interface</h6>
                <p class="small text-muted mb-1">Mirrors the physical 20×4 LCD screen of the ESP32 in real-time.</p>
                <ul class="small text-muted mb-3">
                    <li><strong>Live LCD Mirror</strong> — Shows the 4 lines currently displayed on the ESP32's LCD.</li>
                    <li><strong>Auto Poll</strong> — Refreshes the LCD state every 2 seconds.</li>
                    <li><strong>Virtual Keypad</strong> — Sends key presses to the ESP32 as if you pressed the physical 4×4 matrix keypad.</li>
                    <li><strong>Custom LCD Write</strong> — Manually write text to any row (0–3). Max 20 characters per row.</li>
                    <li><strong>LCD Presets</strong> — Quick buttons: Default display, Enroll screen, or Offline notice.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-broadcast me-1"></i>ESP-NOW Mesh Control</h6>
                <p class="small text-muted mb-1">ESP-NOW is a connectionless WiFi protocol enabling ESP32-to-ESP32 communication without a shared network.</p>
                <ul class="small text-muted mb-3">
                    <li><strong>Gateway Nodes</strong> — ESP32s connected to WiFi. They receive queued data from relay nodes and forward it to the server.</li>
                    <li><strong>Relay Nodes</strong> — ESP32s without WiFi. They queue attendance records locally and broadcast via ESP-NOW hoping a gateway picks them up.</li>
                    <li><strong>Flush Queue</strong> (<code>ESPNOW_FLUSH_QUEUE</code>) — Tells the ESP32 to push all queued records through the nearest gateway.</li>
                    <li><strong>Ping All</strong> (<code>ESPNOW_PING_MESH</code>) — Broadcast ping. All reachable ESP-NOW peers respond.</li>
                    <li><strong>Mesh Status</strong> (<code>ESPNOW_STATUS</code>) — Reports gateway count, relay count, and total queued records.</li>
                    <li><strong>Reset Peers</strong> (<code>ESPNOW_RESET_PEERS</code>) — Clears all registered peer MAC addresses from the ESP32.</li>
                    <li><strong>Peer MAC Management</strong> — Add/remove specific MAC addresses. Use <strong>Broadcast Mode</strong> (<code>FF:FF:FF:FF:FF:FF</code>) to reach all nearby devices.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-raspberry-pi me-1"></i>Raspberry Pi 3B Control Center</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>Live Camera Feed</strong> — MJPEG stream from <code>http://PI_IP:5000/stream</code>.</li>
                    <li><strong>Test Ping</strong> — Checks connectivity to <code>http://PI_IP:5000/api/status</code>.</li>
                    <li><strong>Pull Queue</strong> — Fetches pending OTA commands from the server queue and sends them to the Pi.</li>
                    <li><strong>Match Threshold</strong> — Cosine similarity cutoff for face recognition (40–90%). Default 55%. Higher = stricter matching.</li>
                    <li><strong>Face Enrollment</strong> — Enter a student number and click <strong>Enroll</strong> to trigger face capture on the Pi.</li>
                    <li><strong>System Info</strong> — CPU temperature, RAM usage, uptime, enrolled face count, and Flask service status.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-diagram-3 me-1"></i>Multi-Device Course Assignment</h6>
                <p class="small text-muted mb-1">Maps specific courses to specific hardware devices. When a lecture starts on a device, it only accepts attendance for its assigned courses.</p>
                <ul class="small text-muted mb-3">
                    <li><strong>Assign</strong> — Links a course to a selected device in the database.</li>
                    <li><strong>X button</strong> — Removes a course assignment from a device.</li>
                </ul>
            </div>
        </div>

        <!-- ═══ Key Terms Glossary ═══ -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-info text-white py-3">
                <strong><i class="bi bi-book me-2"></i>Key Terms Glossary</strong>
            </div>
            <div class="card-body bg-light">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" placeholder="Search terms..." oninput="filterGlossary(this.value)">
                </div>
                <div id="glossary-list">
                    <div class="glossary-item" data-keywords="ota over the air http command esp32 pi">
                        <span class="badge bg-primary me-2">OTA</span>
                        <span class="small text-muted">Over-The-Air. Sending commands to ESP32 or Raspberry Pi via HTTP instead of USB serial. The ESP32 runs a lightweight web server on port 80 that accepts <code>/cmd?command=...</code> requests.</span>
                    </div>
                    <div class="glossary-item" data-keywords="nvrAM preferences flash storage wifi credentials persistent esp32">
                        <span class="badge bg-dark me-2">NVRAM / Preferences</span>
                        <span class="small text-muted">The ESP32's non-volatile flash storage area used to persist WiFi credentials, device settings, and mode flags across reboots. Unlike Arduino EEPROM, it uses key-value pairs.</span>
                    </div>
                    <div class="glossary-item" data-keywords="littlefs flash filesystem sd card offline queue csv storage">
                        <span class="badge bg-success me-2">LittleFS</span>
                        <span class="small text-muted">A lightweight filesystem for microcontroller flash memory. Used by the ESP32 to store the offline attendance queue CSV and synced course files. NOT a physical SD card.</span>
                    </div>
                    <div class="glossary-item" data-keywords="esp-now mesh gateway relay peer mac broadcast offline sync">
                        <span class="badge bg-warning text-dark me-2">ESP-NOW Mesh</span>
                        <span class="small text-muted">A connectionless WiFi protocol for direct ESP32-to-ESP32 communication. Gateway nodes (with WiFi) receive queued data from relay nodes (without WiFi) and forward it to the server.</span>
                    </div>
                    <div class="glossary-item" data-keywords="match threshold face recognition cosine similarity accuracy">
                        <span class="badge bg-danger me-2">Match Threshold</span>
                        <span class="small text-muted">The minimum cosine similarity score (40–90%) required for a face to be considered a match. Default is 55%. Higher values reduce false positives but may miss valid matches in poor lighting.</span>
                    </div>
                    <div class="glossary-item" data-keywords="pull queue ota pending command server rpi raspberry pi">
                        <span class="badge bg-secondary me-2">Pull Queue</span>
                        <span class="small text-muted">The Raspberry Pi polls <code>api/ota.php</code> for pending commands queued by the web dashboard. When the Pi "pulls" a command, it executes it locally and the server removes it from the queue.</span>
                    </div>
                    <div class="glossary-item" data-keywords="offline queue attendance record sync connectivity internet">
                        <span class="badge bg-info text-dark me-2">Offline Queue</span>
                        <span class="small text-muted">When the ESP32 loses internet, attendance records are stored in <code>offline_queue.csv</code> on LittleFS. When connectivity returns, <code>syncOfflineQueue()</code> sends all pending records to the server.</span>
                    </div>
                    <div class="glossary-item" data-keywords="multi-factor 2fa two-factor fingerprint rfid face security">
                        <span class="badge bg-dark me-2">Multi-Factor (2FA)</span>
                        <span class="small text-muted">When enabled, attendance requires 2+ sensor matches (e.g. fingerprint AND RFID). Prevents attendance fraud. Configurable in <strong>Hardware Nodes &rarr; Device Settings</strong>.</span>
                    </div>
                    <div class="glossary-item" data-keywords="rssi signal strength wifi dbm connection quality">
                        <span class="badge bg-primary me-2">RSSI</span>
                        <span class="small text-muted">Received Signal Strength Indicator, measured in dBm. Ranges from -30 dBm (perfect) to -90 dBm (unusable). The ESP32 reports this so you can optimize antenna placement.</span>
                    </div>
                    <div class="glossary-item" data-keywords="digital twin lcd mirror screen esp32 display virtual keypad">
                        <span class="badge bg-success me-2">Digital Twin</span>
                        <span class="small text-muted">A web-based mirror of the ESP32's physical LCD screen. Shows real-time content and allows virtual keypad input. Useful for debugging when the physical device is not accessible.</span>
                    </div>
                    <div class="glossary-item" data-keywords="face descriptor vector embedding face-api.js webcam recognition">
                        <span class="badge bg-danger me-2">Face Descriptor</span>
                        <span class="small text-muted">A 128-element numeric vector generated by face-api.js that uniquely represents a person's facial features. Stored as JSON in the database. Used for browser-based and Pi-based face recognition.</span>
                    </div>
                    <div class="glossary-item" data-keywords="fm10a fingerprint sensor slot template enroll esp32">
                        <span class="badge bg-warning text-dark me-2">FM10A Sensor</span>
                        <span class="small text-muted">The optical fingerprint sensor module connected to the ESP32. Stores up to 127 templates in its own memory. Templates are referenced by slot ID (1–127).</span>
                    </div>
                    <div class="glossary-item" data-keywords="ds3231 rtc clock time sync hardware real-time">
                        <span class="badge bg-info text-dark me-2">DS3231 RTC</span>
                        <span class="small text-muted">A high-precision real-time clock module with temperature compensation. Connected to the ESP32 via I2C. Maintains accurate time even when the ESP32 loses power. Synced via <strong>Quick Modes &rarr; Sync Time</strong>.</span>
                    </div>
                    <div class="glossary-item" data-keywords="wpa2 eap enterprise peap mschapv2 identity university corporate">
                        <span class="badge bg-secondary me-2">WPA2-Enterprise</span>
                        <span class="small text-muted">WiFi authentication using a username/identity instead of just a password. Uses PEAP with MSCHAPv2 inside. Common in universities and corporate networks. Enable the Enterprise toggle in Wi-Fi Config.</span>
                    </div>
                    <div class="glossary-item" data-keywords="heartbeat ping online status esp32 rpi detection">
                        <span class="badge bg-dark me-2">Heartbeat</span>
                        <span class="small text-muted">ESP32 and Raspberry Pi periodically write their timestamp to <code>sys_status_esp32.txt</code> or <code>sys_status_rpi.txt</code>. If the file was updated within 30 seconds, the device is considered ONLINE. Shown as green/red pills in the navbar.</span>
                    </div>
                    <div class="glossary-item" data-keywords="bulk enroll csv image face descriptor batch import">
                        <span class="badge bg-primary me-2">Bulk Enrollment</span>
                        <span class="small text-muted">Import many students at once via CSV (course enrollment) or image files (face descriptors). For images, name files as student numbers: <code>S-20-123.jpg</code>. Hyphens/underscores convert to slashes automatically.</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function filterGlossary(query) {
    const items = document.querySelectorAll('.glossary-item');
    const q = query.toLowerCase();
    items.forEach(item => {
        const keywords = (item.dataset.keywords || '').toLowerCase();
        const text = (item.textContent || '').toLowerCase();
        item.style.display = (!q || keywords.includes(q) || text.includes(q)) ? '' : 'none';
    });
}
</script>

<?php require 'includes/footer.php'; ?>
