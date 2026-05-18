<?php
require_once 'includes/header_admin.php';

$courses = [];
$device_course_map = [];
$discovered_devices = [];

try {
    $courses = $pdo->query("SELECT course_code, course_name FROM courses ORDER BY course_code ASC")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->query("SELECT device_id, course_code FROM device_courses ORDER BY device_id ASC");
    while ($row = $stmt->fetch()) $device_course_map[$row['device_id']][] = $row['course_code'];
    $registry_file = __DIR__ . '/devices_registry.json';
    if (file_exists($registry_file)) {
        $registry = json_decode(file_get_contents($registry_file), true) ?: [];
        $now = time();
        foreach ($registry as $ip => $info) {
            if (empty($info['blocked'])) {
                $online = ($now - (int)$info['last_seen']) <= 30;
                $discovered_devices[] = ['ip' => $ip, 'name' => $info['name'] ?? 'Unknown Node', 'type' => $info['type'] ?? 'esp32', 'online' => $online];
            }
        }
    }
} catch (PDOException $e) { error_log('[Device] DB error: ' . $e->getMessage()); }
?>

<div class="row mb-4 mt-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h5 fw-bold text-dark mb-1"><i class="bi bi-motherboard me-2"></i>Hardware Nodes & Devices</h2>
                <p class="text-muted small mb-0">Discover, configure, and monitor all hardware nodes. Select a target device in each section to send OTA commands.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="refreshDeviceList()"><i class="bi bi-arrow-clockwise me-1"></i>Scan Network</button>
                <button class="btn btn-sm btn-primary" onclick="toggleManageDevices()"><i class="bi bi-gear me-1"></i>Manage Nodes</button>
                <a href="instructions.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-question-circle me-1"></i>Help</a>
            </div>
        </div>
        <div id="device-list-table" class="small text-muted mt-2"><em>Click "Scan Network" to discover nodes...</em></div>
        <div id="device-manage-panel" class="d-none border rounded bg-white p-3 shadow-sm mt-2">
            <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-plus-circle me-1"></i>Register New Node</h6>
            <div class="row g-2">
                <div class="col-md-3"><input type="text" id="new-device-ip" class="form-control form-control-sm" placeholder="IP Address"></div>
                <div class="col-md-3"><input type="text" id="new-device-name" class="form-control form-control-sm" placeholder="Node Name (e.g. ESP32-Lab1)"></div>
                <div class="col-md-2">
                    <select id="new-device-type" class="form-select form-select-sm">
                        <option value="esp32">ESP32</option><option value="rpi">Raspberry Pi</option><option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" onclick="registerNewDevice()"><i class="bi bi-plus-lg me-1"></i>Add</button></div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ ESP32 Control Center ═══ -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <strong class="text-dark"><i class="bi bi-cpu me-2 text-primary"></i>ESP32 Control Center</strong>
                <span id="esp32-status-badge" class="badge bg-secondary">Offline</span>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <!-- Left: Target & Telemetry -->
                    <div class="col-lg-3">
                        <div class="mb-3 p-2 bg-light border rounded">
                            <label class="form-label small text-muted fw-bold mb-1"><i class="bi bi-bullseye text-primary me-1"></i>Target ESP32 Node</label>
                            <div class="d-flex gap-2">
                                <div class="position-relative flex-grow-1">
                                    <input type="text" class="form-control form-control-sm border-primary searchable-select-input" data-target="action-target-device" data-list="esp-target-list" placeholder="Type or click to select..." oninput="filterSearchableSelect(this)" onclick="showDropdown(this.dataset.list)">
                                    <select id="action-target-device" class="form-select form-select-sm border-primary d-none" onchange="onEspTargetChange()">
                                        <option value="">Select ESP32 Node...</option>
                                        <?php foreach($discovered_devices as $dev): if($dev['type']==='esp32'): ?>
                                        <option value="<?php echo htmlspecialchars($dev['ip']);?>" data-name="<?php echo htmlspecialchars($dev['name']);?>"><?php echo htmlspecialchars($dev['name']);?> (<?php echo $dev['ip'];?>)<?php echo $dev['online']?' ✅':' ⚫';?></option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                    <div id="esp-target-list" class="searchable-dropdown d-none"></div>
                                </div>
                                <button aria-label="Refresh Device List" class="btn btn-sm btn-outline-primary" onclick="refreshDeviceList()"><i class="bi bi-arrow-clockwise"></i></button>
                            </div>
                        </div>

                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-speedometer2 me-1"></i>Live Telemetry</h6>
                        <div id="esp-telemetry" class="border rounded p-2 bg-light small mb-2">
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1"><span class="text-muted">WiFi RSSI</span><span id="esp-rssi" class="fw-bold">—</span></div>
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1"><span class="text-muted">Free Heap</span><span id="esp-heap" class="fw-bold">—</span></div>
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1"><span class="text-muted">Uptime</span><span id="esp-uptime" class="fw-bold">—</span></div>
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1"><span class="text-muted">Queue Size</span><span id="esp-queue" class="fw-bold">—</span></div>
                            <div class="d-flex justify-content-between"><span class="text-muted">Records Today</span><span id="esp-today" class="fw-bold">—</span></div>
                        </div>
                        <button class="btn btn-sm btn-outline-info w-100 mb-2" onclick="fetchTelemetry()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh Telemetry</button>
                        <div class="small text-muted"><strong>IP:</strong> <span id="esp-current-ip" class="font-monospace">Not selected</span></div>
                    </div>

                    <!-- Mid-Left: WiFi / SD Config -->
                    <div class="col-lg-3">
                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-wifi me-1"></i>Wi-Fi Configuration</h6>
                        <button id="btn-scan-wifi" class="btn btn-sm btn-primary w-100 mb-2" onclick="scanNetworksEsp()"><i class="bi bi-search me-1"></i>Scan Air</button>
                        <select id="esp-ssid-select" class="form-select form-select-sm mb-2"><option value="">Scan results...</option></select>
                        <input type="password" id="esp-wifi-pass" class="form-control form-control-sm mb-2" placeholder="Password">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="esp-enterprise-toggle" onchange="document.getElementById('esp-identity-row').classList.toggle('d-none', !this.checked)">
                            <label class="form-check-label small fw-bold text-muted" for="esp-enterprise-toggle"><i class="bi bi-building me-1"></i>Enterprise WiFi (WPA2-EAP)</label>
                        </div>
                        <div id="esp-identity-row" class="d-none mb-2">
                            <input type="text" id="esp-wifi-identity" class="form-control form-control-sm" placeholder="Enterprise Identity / Username">
                        </div>
                        <button id="btn-save-wifi" class="btn btn-sm btn-warning w-100 fw-bold mb-2" onclick="updateWifiEsp()"><i class="bi bi-save me-1"></i>Save & Reboot</button>

                        <h6 class="fw-bold text-muted small mb-2 mt-3"><i class="bi bi-sd-card me-1"></i>SD Card Setup File</h6>
                        <p class="small text-muted mb-2">Generate a config.txt to place on SD card root.</p>
                        <div class="mb-2">
                            <label class="form-label small fw-bold mb-0">Device Name</label>
                            <input type="text" id="sd-device-name" class="form-control form-control-sm" placeholder="ESP32-Lab1">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold mb-0">WiFi SSID</label>
                            <input type="text" id="sd-wifi-ssid" class="form-control form-control-sm" placeholder="Card ekak daganin bn">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold mb-0">WiFi Password</label>
                            <input type="text" id="sd-wifi-pass" class="form-control form-control-sm" placeholder="meshan1234">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold mb-0">Server URL</label>
                            <input type="text" id="sd-server-url" class="form-control form-control-sm" placeholder="http://10.0.0.5/csc2052">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold mb-0">Encryption Method</label>
                            <select id="sd-enc-method" class="form-select form-select-sm">
                                <option value="plaintext">Plaintext — No encryption (testing only)</option>
                                <option value="hmac">HMAC — Tamper-proof authentication (Recommended)</option>
                                <option value="aes">AES-128 — Full encrypted payload (Maximum security)</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold mb-0">Secret Token <span class="badge bg-info">Required for HMAC/AES</span></label>
                            <input type="text" id="sd-enc-token" class="form-control form-control-sm font-monospace" placeholder="ss_secret_key_2052">
                            <small class="text-muted">Enter a shared secret key. Same value must be set in config.php HEARTBEAT_SECRET.</small>
                        </div>
                        <div class="d-grid gap-2 mb-3">
                            <button class="btn btn-sm btn-outline-dark w-100" onclick="downloadSdConfigFromForm()"><i class="bi bi-download me-1"></i>Download SD Config File</button>
                            <button class="btn btn-sm btn-outline-primary w-100" onclick="downloadFirmwareSource()"><i class="bi bi-code-slash me-1"></i>Download Firmware Source (.ino)</button>
                            <button class="btn btn-sm btn-outline-success w-100" onclick="downloadSetupGuide()"><i class="bi bi-file-earmark-text me-1"></i>Download Setup Guide (.txt)</button>
                        </div>

                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-bluetooth me-1"></i>Bluetooth</h6>
                        <button class="btn btn-sm btn-info text-white w-100 mb-2" onclick="scanBluetoothEsp()"><i class="bi bi-search me-1"></i>Scan BLE</button>
                        <select id="esp-bt-list" class="form-select form-select-sm"><option value="">Scan results...</option></select>
                    </div>

                    <!-- Mid-Right: LittleFS / SD Control -->
                    <div class="col-lg-3">
                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-sd-card me-1"></i>LittleFS / SD Card Control</h6>
                        <div class="mb-3 p-2 bg-light border rounded">
                            <div class="small text-muted mb-1">LittleFS stores the offline attendance queue when the node has no internet.</div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-12"><button class="btn btn-sm btn-outline-primary w-100" onclick="sendEspOta('LISTFS')"><i class="bi bi-folder2-open me-1"></i>List Files on Flash</button></div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-success w-100" onclick="sendEspOta('DUMP_OFFLINE')"><i class="bi bi-file-earmark-text me-1"></i>Read Offline Queue</button></div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-info w-100" onclick="sendEspOta('SYNC_OFFLINE')"><i class="bi bi-cloud-upload me-1"></i>Force Sync Queue</button></div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-warning w-100" onclick="sendEspOta('CLEARLOGS')"><i class="bi bi-trash me-1"></i>Wipe Offline Queue</button></div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-danger w-100" onclick="sendEspOta('FORMAT_FS')"><i class="bi bi-exclamation-triangle me-1"></i>Format LittleFS (Danger)</button></div>
                        </div>

                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-fingerprint me-1"></i>Fingerprint Templates</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-12"><button class="btn btn-sm btn-outline-dark w-100" onclick="sendEspOta('UPLOAD_FP_TEMPLATES')"><i class="bi bi-upload me-1"></i>Upload Templates to ESP</button></div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-secondary w-100" onclick="sendEspOta('DOWNLOAD_FP_TEMPLATES')"><i class="bi bi-download me-1"></i>Download Templates from ESP</button></div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-danger w-100" onclick="sendEspOta('WIPE_FP_TEMPLATES')"><i class="bi bi-eraser me-1"></i>Wipe All FP Templates</button></div>
                        </div>
                    </div>

                    <!-- Far Right: Device Settings & Security -->
                    <div class="col-lg-3">
                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-gear me-1"></i>Device Settings</h6>
                        <div class="mb-3">
                            <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" id="set_enable_fingerprint" checked onchange="updateDeviceSettingsBadges()"><label class="form-check-label small" for="set_enable_fingerprint">Fingerprint Reader</label></div>
                            <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" id="set_enable_rfid" checked onchange="updateDeviceSettingsBadges()"><label class="form-check-label small" for="set_enable_rfid">RFID Scanner</label></div>
                            <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" id="set_enable_face" onchange="updateDeviceSettingsBadges()"><label class="form-check-label small" for="set_enable_face">Face Recognition</label></div>
                            <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="set_require_multi_factor" onchange="updateDeviceSettingsBadges()"><label class="form-check-label small text-danger fw-bold" for="set_require_multi_factor">Require Multi-Factor</label></div>
                            <label class="form-label small text-muted mb-1">Enroll Count: <span id="esp-enroll-count-val">3</span></label>
                            <input type="range" class="form-range" min="1" max="5" id="set_enroll_fingers" oninput="document.getElementById('esp-enroll-count-val').innerText=this.value">
                            <div class="d-grid gap-2 mt-2">
                                <button class="btn btn-sm btn-success w-100 fw-bold" onclick="saveDeviceSettingsEsp()"><i class="bi bi-save me-1"></i>Save Settings</button>
                                <button class="btn btn-sm btn-outline-primary w-100" onclick="loadDeviceSettingsEsp()"><i class="bi bi-arrow-clockwise me-1"></i>Get Current from Device</button>
                            </div>
                        </div>
                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-shield-lock me-1"></i>Security / Encryption</h6>
                        <div id="esp-enc-status" class="alert alert-light py-2 mb-2 small border text-muted">
                            <i class="bi bi-info-circle me-1"></i>Select a target device to view settings
                        </div>
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1">Method</label>
                            <select id="esp-enc-method" class="form-select form-select-sm">
                                <option value="plaintext">Plaintext (No Encryption)</option>
                                <option value="hmac">HMAC (Tamper-Proof)</option>
                                <option value="aes">AES-128 (Encrypted)</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1">Secret Token</label>
                            <input type="password" id="esp-enc-token" class="form-control form-control-sm" placeholder="Shared secret key">
                        </div>
                        <div class="d-grid gap-2">
                            <button id="btn-apply-security" class="btn btn-sm btn-outline-dark w-100" onclick="saveEspSecurity()"><i class="bi bi-shield-check me-1"></i>Apply Security</button>
                            <button id="btn-get-security" class="btn btn-sm btn-outline-info w-100" onclick="getEspSecurity()"><i class="bi bi-arrow-clockwise me-1"></i>Get Current from Device</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Online Serial Monitor ═══ -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0" style="background:#1a1a1a;">
            <div class="card-header border-bottom border-secondary py-2 px-3 d-flex justify-content-between align-items-center" style="background:#2d2d2d;">
                <div class="d-flex align-items-center gap-3">
                    <strong class="text-light small"><i class="bi bi-terminal me-1"></i>Serial Monitor</strong>
                    <select id="serial-esp-select" class="form-select form-select-sm" style="width:180px;background:#3c3c3c;color:#ccc;border-color:#555;font-size:0.75rem;" onchange="setSerialEsp(this.value); syncSerialWithMainTarget(this.value)">
                        <option value="">Select ESP32 Node...</option>
                        <?php foreach($discovered_devices as $dev): if($dev['type']==='esp32'): ?>
                        <option value="<?php echo htmlspecialchars($dev['ip']);?>"><?php echo htmlspecialchars($dev['name']);?> (<?php echo $dev['ip'];?>)</option>
                        <?php endif; endforeach; ?>
                    </select>
                    <select id="serial-baud-select" class="form-select form-select-sm" style="width:100px;background:#3c3c3c;color:#ccc;border-color:#555;font-size:0.75rem;">
                        <option value="9600">9600 baud</option>
                        <option value="19200">19200 baud</option>
                        <option value="38400">38400 baud</option>
                        <option value="57600">57600 baud</option>
                        <option value="115200" selected>115200 baud</option>
                        <option value="230400">230400 baud</option>
                        <option value="250000">250000 baud</option>
                        <option value="921600">921600 baud</option>
                    </select>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="form-check form-check-reverse form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="serial-auto-scroll" checked>
                        <label class="form-check-label text-secondary" for="serial-auto-scroll" style="font-size:0.75rem;">Auto Scroll</label>
                    </div>
                    <div class="form-check form-check-reverse form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="serial-show-timestamps" checked>
                        <label class="form-check-label text-secondary" for="serial-show-timestamps" style="font-size:0.75rem;">Timestamps</label>
                    </div>
                    <select id="serial-line-ending" class="form-select form-select-sm" style="width:120px;background:#3c3c3c;color:#ccc;border-color:#555;font-size:0.75rem;">
                        <option value="">No line ending</option>
                        <option value="\n" selected>Newline</option>
                        <option value="\r\n">Both NL & CR</option>
                        <option value="\r">Carriage return</option>
                    </select>
                    <button id="btn-sse-connect" class="btn btn-sm btn-success" onclick="toggleSseStream()" title="Live Stream">
                        <i class="bi bi-play-fill me-1"></i>Connect
                    </button>
                    <span id="sse-status-dot" class="badge bg-secondary" style="font-size:0.65rem;">Idle</span>
                    <button aria-label="Clear Serial Monitor" class="btn btn-sm btn-link text-secondary p-0" onclick="clearSerialMonitor()" title="Clear"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="serial-monitor" class="p-2" style="height:400px;overflow-y:auto;background:#1a1a1a;font-family:'Cascadia Code','Fira Code','Source Code Pro',monospace;font-size:13px;color:#d4d4d4;white-space:pre-wrap;word-break:break-all;">
                    <div class="text-secondary">Serial output will appear here...<br></div>
                </div>
            </div>
            <div class="card-footer border-top border-secondary d-flex align-items-center gap-2 py-2 px-3" style="background:#2d2d2d;">
                <input type="text" id="serial-cmd-input" class="form-control form-control-sm border-secondary" placeholder="Send command..." style="background:#3c3c3c;color:#d4d4d4;border-color:#555;font-family:monospace;" onkeydown="if(event.key==='Enter')sendSerialCmd()">
                <button class="btn btn-sm btn-primary" onclick="sendSerialCmd()"><i class="bi bi-send me-1"></i>Send</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Digital Twin Interface ═══ -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <strong class="text-dark"><i class="bi bi-display me-2 text-info"></i>Digital Twin Interface</strong>
                <span title="RX/TX Indicator" class="ota-blue-light" id="tx-indicator"></span>
            </div>
            <div class="card-body d-flex justify-content-center">
                <div class="col-lg-8">
                    <div class="mb-3 p-2 bg-light border rounded">
                        <label class="form-label small text-muted fw-bold mb-1"><i class="bi bi-bullseye text-info me-1"></i>Monitor Device</label>
                        <div class="position-relative">
                            <input type="text" class="form-control form-control-sm border-info searchable-select-input" data-target="twin-device-select" data-list="twin-device-list" placeholder="Type or click to select..." oninput="filterSearchableSelect(this)" onclick="showDropdown(this.dataset.list)">
                            <select id="twin-device-select" class="form-select form-select-sm border-info d-none" onchange="switchTwinDevice()">
                                <option value="">Select a device...</option>
                                <?php foreach($discovered_devices as $dev): ?>
                                <option value="<?php echo htmlspecialchars($dev['ip']);?>" data-name="<?php echo htmlspecialchars($dev['name']);?>"><?php echo htmlspecialchars($dev['name']);?> (<?php echo $dev['ip'];?>)<?php echo $dev['online']?' ✅':' ⚫';?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="twin-device-list" class="searchable-dropdown d-none"></div>
                        </div>
                    </div>

                    <h6 class="text-muted fw-bold mb-2 text-center">Live LCD Mirror</h6>
                    <div class="virtual-lcd mx-auto" id="virtual-lcd">
                        <div id="lcd0">Select device to mirror...</div>
                        <div id="lcd1">                    </div>
                        <div id="lcd2">                    </div>
                        <div id="lcd3">                    </div>
                    </div>
                    <div class="d-flex gap-2 mt-2 justify-content-center">
                        <button class="btn btn-sm btn-outline-primary" onclick="sendEspOta('GETLCD')"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="startLcdPolling()"><i class="bi bi-play me-1"></i>Auto Poll</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="stopLcdPolling()"><i class="bi bi-stop me-1"></i>Stop</button>
                    </div>

                    <h6 class="text-muted fw-bold mb-2 mt-4 text-center">Virtual Keypad</h6>
                    <div class="d-flex justify-content-center">
                        <div class="row g-2 text-center mb-3" style="width:280px;">
                            <?php
                            $btns = ['1','2','3','A','4','5','6','B','7','8','9','C','*','0','#','D'];
                            foreach($btns as $b) {
                                $class = ($b == 'A') ? 'bg-danger text-white border-danger' : (($b == 'B' || $b == 'C' || $b == 'D') ? 'bg-secondary text-white border-secondary' : '');
                                echo "<div class='col-3'><button class='keypad-btn $class' onclick=\"sendEspOta('KEYPAD $b')\">$b</button></div>";
                            }
                            ?>
                        </div>
                    </div>
                    <div class="alert alert-light py-2 border text-muted small mb-0">
                        <div class="d-flex flex-wrap gap-2 justify-content-center align-items-center">
                            <span class="d-flex align-items-center gap-1"><span class="badge bg-danger px-2 py-1">A</span> <span class="small">Menu</span></span>
                            <span class="d-flex align-items-center gap-1"><span class="badge bg-secondary px-2 py-1">B</span> <span class="small">Up</span></span>
                            <span class="d-flex align-items-center gap-1"><span class="badge bg-secondary px-2 py-1">C</span> <span class="small">Down</span></span>
                            <span class="d-flex align-items-center gap-1"><span class="badge bg-secondary px-2 py-1">D</span> <span class="small">OK</span></span>
                            <span class="d-flex align-items-center gap-1"><span class="badge bg-dark px-2 py-1">*</span> <span class="small">Delete</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ ESP-NOW Mesh Control ═══ -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <strong class="text-dark"><i class="bi bi-broadcast me-2" style="color:#6f42c1;"></i>ESP-NOW Mesh Control</strong>
                <span class="badge bg-success"><i class="bi bi-wifi me-1"></i>Mesh Ready</span>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="alert alert-light py-2 mb-3 small border">
                            <i class="bi bi-info-circle me-1 text-primary"></i>
                            <strong>How it works:</strong> ESP-NOW is a connectionless protocol. Offline nodes queue attendance data and broadcast via ESP-NOW. Nearby "gateway" nodes receive and forward to the server.
                        </div>
                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-diagram-3 me-1"></i>Mesh Network Status</h6>
                        <div id="espnow-mesh-status" class="border rounded p-2 bg-light mb-3 small text-muted">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span><i class="bi bi-circle-fill text-success me-1" style="font-size:0.5rem;"></i>Gateway Nodes (Online)</span>
                                <span id="espnow-gateway-count" class="badge bg-success">—</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span><i class="bi bi-circle-fill text-warning me-1" style="font-size:0.5rem;"></i>Relay Nodes (Offline Wi-Fi)</span>
                                <span id="espnow-relay-count" class="badge bg-warning text-dark">—</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-circle-fill text-secondary me-1" style="font-size:0.5rem;"></i>Queued Offline Records</span>
                                <span id="espnow-queue-count" class="badge bg-secondary">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-fingerprint me-1"></i>Peer MAC Management</h6>
                        <div class="alert alert-light py-2 mb-2 small border">
                            <i class="bi bi-book me-1"></i>
                            Add or remove peer MAC addresses so this ESP32 can relay data from specific nodes. <strong>Broadcast Mode</strong> sends to <code>FF:FF:FF:FF:FF:FF</code> (all nearby ESP-NOW devices).
                        </div>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-white"><i class="bi bi-hdd-network text-muted"></i></span>
                            <input type="text" id="espnow-mac-input" class="form-control font-monospace" placeholder="AA:BB:CC:DD:EE:FF">
                            <button aria-label="Add ESP-NOW Peer" class="btn btn-outline-primary" onclick="sendEspOta('ESPNOW_ADD_PEER:'+document.getElementById('espnow-mac-input').value)"><i class="bi bi-plus-lg"></i></button>
                            <button aria-label="Remove ESP-NOW Peer" class="btn btn-outline-danger" onclick="sendEspOta('ESPNOW_DEL_PEER:'+document.getElementById('espnow-mac-input').value)"><i class="bi bi-dash-lg"></i></button>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="espnow-broadcast-mode" checked onchange="sendEspOta('ESPNOW_BROADCAST:'+(this.checked?'1':'0'))">
                            <label class="form-check-label small text-muted fw-bold" for="espnow-broadcast-mode"><i class="bi bi-megaphone me-1"></i>Broadcast Mode</label>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-terminal me-1"></i>Send Mesh Command</h6>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><button class="btn btn-sm btn-outline-primary w-100" onclick="sendEspOta('ESPNOW_FLUSH_QUEUE')"><i class="bi bi-cloud-upload me-1"></i>Flush Queue</button></div>
                            <div class="col-6"><button class="btn btn-sm btn-outline-success w-100" onclick="sendEspOta('ESPNOW_PING_MESH')"><i class="bi bi-broadcast me-1"></i>Ping All</button></div>
                            <div class="col-6"><button class="btn btn-sm btn-outline-warning w-100" onclick="sendEspOta('ESPNOW_STATUS')"><i class="bi bi-info-circle me-1"></i>Mesh Status</button></div>
                            <div class="col-6"><button class="btn btn-sm btn-outline-danger w-100" onclick="sendEspOta('ESPNOW_RESET_PEERS')"><i class="bi bi-x-circle me-1"></i>Reset Peers</button></div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-info w-100" onclick="sendEspOta('ESPNOW_BROADCAST:1')"><i class="bi bi-megaphone me-1"></i>Broadcast ON</button></div>
                        </div>
                        <h6 class="fw-bold text-muted small mb-1 mt-2"><i class="bi bi-pencil me-1"></i>Custom Command</h6>
                        <div class="input-group input-group-sm">
                            <input type="text" id="mesh-cmd-input" class="form-control font-monospace" placeholder="Custom command..." onkeydown="if(event.key==='Enter')sendMeshCmd()">
                            <button aria-label="Send Mesh Command" class="btn btn-sm btn-outline-primary" onclick="sendMeshCmd()"><i class="bi bi-send"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Raspberry Pi 3B Control Center ═══ -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <strong class="text-dark"><i class="bi bi-raspberry-pi me-2 text-danger"></i>Raspberry Pi 3B Control Center</strong>
                <div class="d-flex align-items-center gap-2">
                    <span id="rpi-status-dot" class="badge bg-secondary"><i class="bi bi-circle me-1"></i>Offline</span>
                    <button aria-label="Refresh Pi Status" class="btn btn-xs btn-outline-secondary" onclick="refreshRpiStatus()"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-camera-reels me-1"></i>Live Camera Feed</h6>
                        <div class="border rounded bg-dark text-center shadow-sm mb-2" style="position:relative;overflow:hidden;aspect-ratio:4/3;">
                            <img id="rpi-cam-stream" src="" alt="Pi Camera" class="w-100" style="display:none;object-fit:cover;">
                            <div id="rpi-cam-placeholder" class="d-flex align-items-center justify-content-center text-muted" style="height:100%;">
                                <div><i class="bi bi-camera-reels" style="font-size:2.5rem;"></i><p class="small mt-2 mb-0">Pi Camera Offline</p></div>
                            </div>
                            <canvas id="rpi-attendance-overlay" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;"></canvas>
                        </div>
                        <div id="rpi-attendance-status" class="small fw-bold text-center mb-2"></div>
                        <div class="d-flex justify-content-center gap-3 mb-2">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="rpi-attendance-auto">
                                <label class="form-check-label small fw-bold text-muted" for="rpi-attendance-auto"><i class="bi bi-lightning-charge me-1"></i>Auto Attend</label>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="rpi-attendance-mute" checked>
                                <label class="form-check-label small fw-bold text-muted" for="rpi-attendance-mute"><i class="bi bi-volume-mute me-1"></i>Mute</label>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button id="btn-start-rpi-attendance" class="btn btn-sm btn-dark w-100 fw-bold" onclick="startRpiAttendance()"><i class="bi bi-play-circle me-1"></i>Start Scanner</button>
                            <button id="btn-stop-rpi-attendance" class="btn btn-sm btn-danger w-100 fw-bold d-none" onclick="stopRpiAttendance()"><i class="bi bi-stop-circle me-1"></i>Stop</button>
                        </div>

                        <h6 class="fw-bold text-muted small mb-2 mt-3"><i class="bi bi-people me-1"></i>Face Enrollment</h6>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-white"><i class="bi bi-person-badge text-muted"></i></span>
                            <input type="text" id="rpi-enroll-id" class="form-control" placeholder="Student No">
                            <button class="btn btn-sm btn-danger" onclick="sendRpiCommand('ENROLL:'+document.getElementById('rpi-enroll-id').value)"><i class="bi bi-camera me-1"></i>Enroll</button>
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-person-x text-danger"></i></span>
                            <input type="text" id="rpi-delete-id" class="form-control" placeholder="Student No to delete">
                            <button aria-label="Delete Pi Face ID" class="btn btn-sm btn-outline-danger" onclick="sendRpiCommand('DELETE_FACE:'+document.getElementById('rpi-delete-id').value)"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-hdd-network me-1"></i>Connection & Configuration</h6>
                        <div class="mb-3">
                            <label class="form-label small text-muted fw-bold">Pi IP Address</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-hdd-network text-danger"></i></span>
                                <div class="position-relative flex-grow-1">
                                    <input type="text" id="rpi-ip-search" class="form-control font-monospace searchable-select-input" data-target="rpi-ip-select" data-list="rpi-ip-dropdown" placeholder="Search or enter IP..." oninput="filterSearchableSelect(this)">
                                    <select id="rpi-ip-select" class="form-select d-none">
                                        <option value="">Select or type IP...</option>
                                        <?php foreach($discovered_devices as $dev): ?>
                                        <option value="<?php echo htmlspecialchars($dev['ip']);?>" data-name="<?php echo htmlspecialchars($dev['name']);?>"><?php echo htmlspecialchars($dev['name']);?> (<?php echo $dev['ip'];?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="rpi-ip-dropdown" class="searchable-dropdown d-none"></div>
                                </div>
                                <button class="btn btn-sm btn-danger" onclick="saveRpiConfig()"><i class="bi bi-save me-1"></i>Save</button>
                            </div>
                        </div>
                        <small id="rpi-ip-display" class="text-muted d-block mb-3">Not configured</small>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><button class="btn btn-sm btn-outline-success w-100" onclick="refreshRpiStatus()"><i class="bi bi-wifi me-1"></i>Test Ping</button></div>
                            <div class="col-6"><button class="btn btn-sm btn-outline-primary w-100" onclick="triggerRpiOta()"><i class="bi bi-cloud-arrow-down me-1"></i>Pull Queue</button></div>
                            <div class="col-6"><button class="btn btn-sm btn-outline-warning w-100" onclick="sendRpiCommand('RESTART_SERVICE')"><i class="bi bi-arrow-repeat me-1"></i>Restart Service</button></div>
                            <div class="col-6"><button class="btn btn-sm btn-outline-danger w-100" onclick="sendRpiCommand('REBOOT')"><i class="bi bi-power me-1"></i>Reboot Pi</button></div>
                        </div>
                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-sliders me-1"></i>Recognition Settings</h6>
                        <div class="mb-2">
                            <label class="form-label small text-muted fw-bold mb-1">Match Threshold: <span id="rpi-threshold-val">55%</span></label>
                            <input type="range" class="form-range" min="40" max="90" value="55" id="rpi-threshold" oninput="document.getElementById('rpi-threshold-val').textContent=this.value+'%'">
                        </div>
                        <button class="btn btn-sm btn-outline-dark w-100" onclick="sendRpiCommand('SETCONFIG:THRESHOLD:'+document.getElementById('rpi-threshold').value)"><i class="bi bi-check2 me-1"></i>Apply Threshold</button>

                        <h6 class="fw-bold text-muted small mb-2 mt-3"><i class="bi bi-wifi me-1"></i>Pi Wi-Fi Configuration</h6>
                        <div class="mb-2">
                            <input type="text" id="rpi-ssid-input" class="form-control form-control-sm mb-1" placeholder="SSID">
                            <input type="password" id="rpi-pass-input" class="form-control form-control-sm mb-1" placeholder="Password">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" id="rpi-enterprise-toggle" onchange="document.getElementById('rpi-identity-row').classList.toggle('d-none', !this.checked)">
                                <label class="form-check-label small fw-bold text-muted" for="rpi-enterprise-toggle"><i class="bi bi-building me-1"></i>Enterprise WiFi (WPA2-EAP)</label>
                            </div>
                            <div id="rpi-identity-row" class="d-none mb-1">
                                <input type="text" id="rpi-identity-input" class="form-control form-control-sm" placeholder="Enterprise Identity / Username">
                            </div>
                        </div>
                        <button id="btn-update-rpi-wifi" class="btn btn-sm btn-outline-warning w-100" onclick="updateRpiWifi()"><i class="bi bi-save me-1"></i>Update Pi WiFi</button>
                    </div>
                    <div class="col-lg-4">
                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-bar-chart me-1"></i>System Info</h6>
                        <div id="rpi-sysinfo" class="border rounded p-2 bg-light small mb-3">
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1"><span class="text-muted">CPU Temp</span><span id="rpi-cpu-temp" class="fw-bold">—</span></div>
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1"><span class="text-muted">CPU Usage</span><span id="rpi-cpu-usage" class="fw-bold">—</span></div>
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1"><span class="text-muted">RAM Free</span><span id="rpi-ram-free" class="fw-bold">—</span></div>
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1"><span class="text-muted">Uptime</span><span id="rpi-uptime" class="fw-bold">—</span></div>
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1"><span class="text-muted">Enrolled Faces</span><span id="rpi-face-count" class="fw-bold">—</span></div>
                            <div class="d-flex justify-content-between"><span class="text-muted">Service Status</span><span id="rpi-service-status" class="badge bg-secondary">—</span></div>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary w-100 mb-3" onclick="fetchRpiSysInfo()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh System Info</button>

                        <h6 class="fw-bold text-muted small mb-2"><i class="bi bi-terminal me-1"></i>Pi Serial Monitor</h6>
                        <div id="rpi-serial-output" class="terminal mb-2" style="height: 150px; overflow-y: auto;">
                            <div class="text-muted">> Pi Serial Output</div>
                        </div>
                        <div class="input-group input-group-sm">
                            <input type="text" id="rpi-cmd-input" class="form-control font-monospace" placeholder="Enter command...">
                            <button aria-label="Send Pi Command" class="btn btn-sm btn-dark" onclick="sendRpiCmdFromInput()"><i class="bi bi-send"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ All Devices Overview ═══ -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <strong class="text-dark"><i class="bi bi-hdd-rack me-2 text-info"></i>Devices Overview</strong>
                <div class="d-flex gap-2 align-items-center">
                    <span id="dev-overall-count" class="badge bg-secondary">0 devices</span>
                    <button class="btn btn-xs btn-outline-secondary" onclick="refreshAllDeviceStatus()"><i class="bi bi-arrow-clockwise"></i> Refresh All</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 small align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Device</th>
                                <th>IP Address</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Mode</th>
                                <th>Course</th>
                                <th>RSSI</th>
                                <th>Encryption</th>
                                <th>Queue</th>
                                <th>Last Seen</th>
                                <th class="pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="device-overview-body">
                            <tr><td colspan="11" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-1"></span>Loading devices...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Multi-Device Course Assignment ═══ -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <strong class="text-dark"><i class="bi bi-diagram-3 me-2 text-success"></i>Multi-Device Course Assignment</strong>
                <small class="text-muted">Assign courses to specific hardware devices</small>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-cpu me-1"></i>Device</label>
                        <select id="assign-device-select" class="form-select form-select-sm">
                            <option value="">Select Device...</option>
                            <?php foreach ($discovered_devices as $dev): ?>
                            <option value="<?php echo htmlspecialchars($dev['name']); ?>"><?php echo htmlspecialchars($dev['name']); ?> (<?php echo htmlspecialchars($dev['ip']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-tag me-1"></i>Course</label>
                        <select id="assign-course-select" class="form-select form-select-sm">
                            <option value="">Select Course...</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-sm btn-success w-100 fw-semibold" onclick="assignCourseToDevice()"><i class="bi bi-plus-lg me-1"></i>Assign</button>
                    </div>
                </div>
                <div id="device-assignments-list" class="border rounded bg-white p-2" style="max-height: 200px; overflow-y: auto;">
                    <?php if (count($device_course_map) > 0): ?>
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr>
                                <th class="small fw-bold text-secondary">Device</th>
                                <th class="small fw-bold text-secondary">Courses</th>
                                <th class="text-end small fw-bold text-secondary">Actions</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($device_course_map as $dev => $crs): ?>
                                <tr>
                                    <td class="fw-bold small"><?php echo htmlspecialchars($dev); ?></td>
                                    <td class="small"><?php foreach ($crs as $c): ?><span class="badge bg-info text-dark me-1 mb-1"><?php echo htmlspecialchars($c); ?></span><?php endforeach; ?></td>
                                    <td class="text-end"><?php foreach ($crs as $c): ?><button aria-label="Remove Device Course" class="btn btn-xs btn-outline-danger py-0 px-1 mb-1" onclick="removeDeviceCourse('<?php echo htmlspecialchars($dev); ?>','<?php echo htmlspecialchars($c); ?>')"><i class="bi bi-x"></i></button><?php endforeach; ?></td>
                                </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted text-center small mb-0 py-2"><em>No assignments yet.</em></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentEspIp = '';
let lcdPollInterval = null;

// ─── Searchable Dropdowns ──────────────────────────────────────
function showDropdown(listId) {
    const list = document.getElementById(listId);
    if (!list) return;
    const input = document.querySelector(`[data-list="${listId}"]`);
    const sel = document.getElementById(input?.dataset.target);
    if (!sel) return;
    
    list.innerHTML = '';
    const opts = sel.querySelectorAll('option');
    opts.forEach(opt => {
        if (!opt.value) return;
        const item = document.createElement('div');
        item.className = 'searchable-dropdown-item';
        item.textContent = opt.textContent;
        item.onmousedown = (e) => {
            e.preventDefault();
            sel.value = opt.value;
            input.value = opt.textContent;
            input.dataset.selected = opt.value;
            list.classList.add('d-none');
            sel.dispatchEvent(new Event('change'));
            input.focus();
        };
        list.appendChild(item);
    });
    list.classList.remove('d-none');
}

function filterSearchableSelect(input) {
    const query = input.value.toLowerCase();
    const targetId = input.dataset.target;
    const listId = input.dataset.list;
    const sel = document.getElementById(targetId);
    const list = document.getElementById(listId);
    list.innerHTML = '';
    const opts = sel.querySelectorAll('option');
    let matchCount = 0;
    opts.forEach(opt => {
        if (!opt.value) return;
        const text = (opt.textContent || '').toLowerCase();
        const name = (opt.dataset.name || '').toLowerCase();
        const val = (opt.value || '').toLowerCase();
        if (query === '' || text.includes(query) || name.includes(query) || val.includes(query)) {
            const item = document.createElement('div');
            item.className = 'searchable-dropdown-item';
            item.textContent = opt.textContent;
            item.onmousedown = (e) => {
                e.preventDefault();
                sel.value = opt.value;
                input.value = opt.textContent;
                input.dataset.selected = opt.value;
                list.classList.add('d-none');
                sel.dispatchEvent(new Event('change'));
                input.focus();
            };
            list.appendChild(item);
            matchCount++;
        }
    });
    if (matchCount > 0) list.classList.remove('d-none'); else list.classList.add('d-none');
}

document.querySelectorAll('.searchable-select-input').forEach(input => {
    input.addEventListener('blur', () => {
        setTimeout(() => {
            const list = document.getElementById(input.dataset.list);
            if (list && !list.matches(':hover')) list.classList.add('d-none');
        }, 200);
    });
});

function getEspTarget() { return document.getElementById('action-target-device')?.value || ''; }

let serialEspIp = '';
function setSerialEsp(ip) { serialEspIp = ip; if (ip) appendSerial('Serial Monitor now targeting: ' + ip, 'text-warning fw-bold'); }
function syncSerialWithMainTarget(ip) {
    const mainSel = document.getElementById('action-target-device');
    if (mainSel && mainSel.value !== ip) { mainSel.value = ip; onEspTargetChange(); }
}

function fetchTelemetry() {
    const ip = getEspTarget();
    if (!ip) { document.getElementById('esp-rssi').textContent = '—'; return; }
    fetch(`http://${ip}/cmd?command=GETSTATUS`, { signal: AbortSignal.timeout(8000) })
        .then(r => r.text())
        .then(raw => parseTelemetry(raw))
        .catch(e => console.warn('[Telemetry] Fetch error:', e));
}

function sendEspOta(cmd, targetIp) {
    const ip = targetIp || serialEspIp || getEspTarget();
    if (!ip) { alert('Please select a target ESP32 node first.'); return; }
    appendSerial('[TX] ' + cmd, 'text-info');
    flashTxIndicator();
    fetch(`http://${ip}/cmd?command=${encodeURIComponent(cmd)}`, { signal: AbortSignal.timeout(8000) })
        .then(r => r.text())
        .then(data => {
            const raw = data.trim();
            if (!raw) { appendSerial('[RX] (empty response)', 'text-muted'); return; }
            const lines = raw.split(/\r?\n/);
            lines.forEach(line => {
                const trimmed = line.trim();
                if (!trimmed) return;
                let cls = 'text-light';
                if (/^(STATUS|MODE|COURSE|DEVICE)/i.test(trimmed)) cls = 'text-success fw-bold';
                else if (/^>>/i.test(trimmed)) cls = 'text-warning';
                else if (/^--------------------------------/i.test(trimmed)) cls = 'text-muted';
                else if (/error|fail|offline|ERR/i.test(trimmed)) cls = 'text-danger';
                else if (/online|success|ok|ready|LIVE/i.test(trimmed)) cls = 'text-success';
                else if (/^\[/i.test(trimmed)) cls = 'text-info';
                appendSerial(trimmed, cls);
            });
            if (cmd === 'GETLCD') updateDigitalTwin(raw);
            if (cmd === 'SCANWIFI') parseWifiScan(raw);
            if (cmd === 'SCANBT') parseBtScan(raw);
            if (cmd === 'GETSTATUS' || cmd === 'STATUS') parseTelemetry(raw);
            if (cmd === 'GETSECURITY') parseSecurity(raw);
        })
        .catch(e => appendSerial(`[ERR] ${e.message}`, 'text-danger'));
}

function parseTelemetry(raw) {
    const el = (id) => document.getElementById(id);
    const d = {};
    console.log('[Telemetry] Raw:', raw.substring(0, 200));

    const rawTrimmed = raw.trim();
    if (rawTrimmed.startsWith('{')) {
        try {
            const j = JSON.parse(rawTrimmed);
            Object.keys(j).forEach(k => d[k.toUpperCase().replace(/\s+/g, '_')] = String(j[k]).trim());
        } catch(e) {}
    }

    const lines = raw.split(/\r?\n/);
    for (let i = 0; i < lines.length; i++) {
        let line = lines[i].replace(/[^\x20-\x7E]/g, '').trim();
        if (!line || line.startsWith('---') || line.startsWith('===')) continue;
        let m = line.match(/^\[([^\]]+)\]\s*->\s*(.+)$/);
        if (!m) {
            const clean = line.replace(/^>>\s*/, '');
            const sepIdx = clean.search(/[:=]/);
            if (sepIdx > 0) {
                const key = clean.substring(0, sepIdx).trim().replace(/\s+/g, '_').toUpperCase().replace(/[^A-Z0-9_]/g, '');
                const val = clean.substring(sepIdx + 1).trim();
                if (key && val) d[key] = val;
            }
        } else {
            const key = m[1].replace(/\s+/g, '_').toUpperCase().replace(/[^A-Z0-9_]/g, '');
            d[key] = m[2].trim();
        }
    }

    if (Object.keys(d).length === 0) {
        console.warn('[Telemetry] No keys parsed. Raw:', raw);
        appendSerial('[WARN] Telemetry response not recognized. Check console for raw data.', 'text-warning');
    } else {
        console.log('[Telemetry] Parsed keys:', Object.keys(d));
    }

    const status = d.STATUS || '';
    const rssi = d.RSSI || d.WIFI_RSSI || d.SIGNAL || d.WIFI || '';
    const heap = d.HEAP || d.HEAP_FREE || d.FREE_HEAP || d.FREEHEAP || d.HEAP_BYTES || '';
    const uptime = d.UPTIME || d.UP_TIME || d.RUNTIME || d.UPTIME_S || '';
    const queue = d.QUEUE || d.QUEUE_SIZE || d.QUEUE_LENGTH || d.QUEUE_COUNT || d.PENDING || '0';
    const today = d.TODAY || d.RECORDS_TODAY || d.TODAY_COUNT || d.RECORDS || d.COUNT || d.ATTENDANCE_TODAY || '';
    const mode = d.MODE || d.OPER_MODE || d.OP_MODE || d.OPERATION_MODE || d.CURRENT_MODE || '';
    const course = d.COURSE || d.COURSE_CODE || d.CLASS || '';
    const fsUsed = d.FS_USED || d.STORAGE_USED || d.STORAGE || d.FS_USAGE || '';
    const fsTotal = d.FS_TOTAL || d.STORAGE_TOTAL || d.TOTAL_STORAGE || '';

    if (el('esp-rssi')) el('esp-rssi').textContent = rssi ? (rssi.includes('dBm') ? rssi : rssi + ' dBm') : (status ? status : '—');
    if (el('esp-heap')) el('esp-heap').textContent = heap ? formatBytes(heap) + ' free' : '—';
    if (el('esp-uptime')) el('esp-uptime').textContent = uptime ? formatUptime(uptime) : '—';
    if (el('esp-queue')) el('esp-queue').textContent = queue + ' records';
    if (el('esp-today')) el('esp-today').textContent = today || '—';

    if (el('dt-rssi')) {
        if (rssi) {
            const rssiNum = parseInt(rssi);
            const badgeColor = !isNaN(rssiNum) && rssiNum > -50 ? 'bg-success' : (!isNaN(rssiNum) && rssiNum > -70 ? 'bg-warning text-dark' : 'bg-danger');
            el('dt-rssi').innerHTML = '<span class="badge ' + badgeColor + '">' + rssi + ' dBm</span>';
        } else if (status) {
            el('dt-rssi').innerHTML = '<span class="badge bg-success">' + status + '</span>';
        } else el('dt-rssi').innerHTML = '<span class="badge bg-secondary">—</span>';
    }
    if (el('dt-heap')) el('dt-heap').textContent = heap ? formatBytes(heap) : '—';
    if (el('dt-uptime')) el('dt-uptime').textContent = uptime ? formatUptime(uptime) : '—';
    if (el('dt-queue')) {
        const qNum = parseInt(queue) || 0;
        el('dt-queue').innerHTML = '<span class="badge ' + (qNum > 0 ? 'bg-warning text-dark' : 'bg-success') + '">' + qNum + '</span>';
    }
    if (el('dt-today')) el('dt-today').textContent = today || '—';
    if (el('esp-storage')) el('esp-storage').textContent = (fsUsed && fsTotal) ? formatBytes(fsUsed) + ' / ' + formatBytes(fsTotal) : '—';
    if (el('dt-storage')) el('dt-storage').textContent = (fsUsed && fsTotal) ? formatBytes(fsUsed) + ' / ' + formatBytes(fsTotal) : '—';
    if (el('dt-course')) el('dt-course').innerHTML = course ? '<span class="badge bg-info">' + escapeHtml(course) + '</span>' : '<span class="badge bg-secondary">—</span>';
    if (el('dt-peers')) {
        const peers = d.PEERS || d.PEER_COUNT || d.PEER_NUM || '—';
        el('dt-peers').textContent = peers;
    }
    if (el('dt-mode')) {
        const modeBadges = { ATTENDANCE_MODE: 'bg-success', ENROLL_MODE: 'bg-warning text-dark', IDLE: 'bg-dark', OFFLINE: 'bg-danger' };
        const key = mode.toUpperCase().replace(/\s/g, '_');
        const mBadge = modeBadges[key] || (mode ? 'bg-secondary' : 'bg-dark');
        el('dt-mode').innerHTML = mode ? '<span class="badge ' + mBadge + '">' + escapeHtml(mode) + '</span>' : '<span class="badge bg-dark">—</span>';
    }
    const ip = getEspTarget();
    if (el('esp-current-ip') && ip) el('esp-current-ip').textContent = ip;
    if (el('dt-ip')) el('dt-ip').textContent = ip || '—';
    if (el('esp-battery')) el('esp-battery').textContent = d.BATTERY || d.BATTERY_LEVEL || d.BAT || 'N/A';
    const badge = document.getElementById('esp32-status-badge');
    if (badge) {
        const isOnline = status.toUpperCase().includes('ONLINE') || status.toUpperCase().includes('OK') || status.toUpperCase().includes('READY');
        badge.textContent = isOnline ? 'Online' : 'Offline';
        badge.className = 'badge ' + (isOnline ? 'bg-success' : 'bg-danger');
    }
}

function formatBytes(val) {
    const n = parseInt(val);
    if (isNaN(n)) return val;
    if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB';
    if (n >= 1024) return (n / 1024).toFixed(1) + ' KB';
    return n + ' B';
}

function formatUptime(val) {
    const n = parseInt(val);
    if (isNaN(n) || n < 10) return val;
    const s = n % 60, m = Math.floor(n / 60) % 60, h = Math.floor(n / 3600) % 24, d = Math.floor(n / 86400);
    let out = '';
    if (d) out += d + 'd ';
    if (h || d) out += h + 'h ';
    out += m + 'm ' + s + 's';
    return out;
}

function parseSecurity(raw) {
    const parts = raw.split('|');
    if (parts.length >= 1) {
        const method = parts[0].toLowerCase();
        const token = parts[1] || '';
        const methodSel = document.getElementById('esp-enc-method');
        const tokenInput = document.getElementById('esp-enc-token');
        if (methodSel) methodSel.value = method;
        if (tokenInput) { tokenInput.value = token; tokenInput.type = 'text'; setTimeout(() => tokenInput.type = 'password', 3000); }
        const dtEnc = document.getElementById('dt-enc');
        const encBadges = { plaintext: '<span class="badge bg-secondary">Plaintext</span>', hmac: '<span class="badge bg-success">HMAC</span>', aes: '<span class="badge bg-primary">AES-128</span>' };
        if (dtEnc) dtEnc.innerHTML = encBadges[method] || `<span class="badge bg-secondary">${method}</span>`;
    }
}

// ─── Serial Monitor ──────────────────────────────────────────────
let serialPollTimer = null;
function toggleSseStream() {
    if (serialPollTimer) {
        clearInterval(serialPollTimer);
        serialPollTimer = null;
        document.getElementById('btn-sse-connect').innerHTML = '<i class="bi bi-play-fill me-1"></i>Connect';
        document.getElementById('btn-sse-connect').className = 'btn btn-sm btn-success';
        document.getElementById('sse-status-dot').className = 'badge bg-secondary';
        document.getElementById('sse-status-dot').textContent = 'Idle';
    } else {
        const ip = serialEspIp || getEspTarget();
        if (!ip) { appendSerial('[ERR] Select an ESP32 node first.', 'text-danger fw-bold'); return; }
        const btn = document.getElementById('btn-sse-connect');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Connecting...';

        let pollCount = 0;
        function pollEsp() {
            fetch(`http://${ip}/logs`, { signal: AbortSignal.timeout(3000) })
                .then(r => r.text())
                .then(raw => {
                    if (raw && raw.trim()) {
                        const lines = raw.split(/\r?\n/);
                        lines.forEach(line => {
                            const trimmed = line.trim();
                            if (!trimmed) return;
                            let cls = 'text-light';
                            if (/^(STATUS|MODE|COURSE|DEVICE)/i.test(trimmed)) cls = 'text-success fw-bold';
                            else if (/^>>/i.test(trimmed)) cls = 'text-warning';
                            else if (/^---/i.test(trimmed)) cls = 'text-muted';
                            else if (/error|fail|offline|ERR/i.test(trimmed)) cls = 'text-danger';
                            else if (/online|success|ok|ready|LIVE/i.test(trimmed)) cls = 'text-success';
                            else if (/^\[/i.test(trimmed)) cls = 'text-info';
                            appendSerial(trimmed, cls);
                        });
                    }
                    if (pollCount === 0) {
                        btn.innerHTML = '<i class="bi bi-stop-fill me-1"></i>Disconnect';
                        btn.className = 'btn btn-sm btn-danger';
                        document.getElementById('sse-status-dot').className = 'badge bg-success';
                        document.getElementById('sse-status-dot').textContent = 'Streaming';
                        appendSerial('[SERIAL] Connected to ' + ip + ' — reading live output.', 'text-success fw-bold');
                    }
                    pollCount++;
                    if (raw) parseTelemetry(raw);
                })
                .catch(e => {
                    if (pollCount > 0) {
                        appendSerial('[SERIAL] Poll error: ' + e.message, 'text-danger');
                        document.getElementById('sse-status-dot').className = 'badge bg-warning text-dark';
                        document.getElementById('sse-status-dot').textContent = 'Error';
                    }
                });
        }

        pollEsp();
        serialPollTimer = setInterval(pollEsp, 2000);
    }
}

function appendSerial(text, cls = '') {
    const mon = document.getElementById('serial-monitor');
    const line = document.createElement('div');
    const showTs = document.getElementById('serial-show-timestamps')?.checked;
    if (showTs) {
        const ts = document.createElement('span');
        ts.className = 'text-secondary';
        ts.textContent = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' ';
        line.appendChild(ts);
    }
    const msg = document.createElement('span');
    msg.className = cls;
    msg.textContent = text + '\n';
    line.appendChild(msg);
    mon.appendChild(line);
    if (document.getElementById('serial-auto-scroll')?.checked) mon.scrollTop = mon.scrollHeight;
}
function clearSerialMonitor() { document.getElementById('serial-monitor').innerHTML = ''; }

window.sendSerialCmd = function() {
    const input = document.getElementById('serial-cmd-input');
    const cmd = input.value.trim();
    if (!cmd) return;
    appendSerial('> ' + cmd, 'text-success fw-bold');
    const target = serialEspIp || getEspTarget();
    if (!target) { appendSerial('[ERR] No ESP32 selected. Choose a target above.', 'text-danger'); input.value = ''; return; }
    fetch(`http://${target}/cmd?command=${encodeURIComponent(cmd)}`, { signal: AbortSignal.timeout(5000) })
        .then(r => r.text())
        .then(resp => {
            if (resp.trim()) {
                resp.split(/\r?\n/).forEach(line => {
                    const trimmed = line.trim();
                    if (trimmed) {
                        let cls = 'text-light';
                        if (/^(STATUS|MODE|COURSE|DEVICE)/i.test(trimmed)) cls = 'text-success fw-bold';
                        else if (/^>>/i.test(trimmed)) cls = 'text-warning';
                        else if (/^---/i.test(trimmed)) cls = 'text-muted';
                        else if (/error|fail|offline|ERR/i.test(trimmed)) cls = 'text-danger';
                        else if (/ack/i.test(trimmed)) cls = 'text-success';
                        appendSerial(trimmed, cls);
                    }
                });
            }
        })
        .catch(e => appendSerial('[ERR] ' + e.message, 'text-danger'));
    input.value = '';
};

// ─── Telemetry (use sendEspOta('GETSTATUS') instead) ──────────────

// ─── WiFi / BT ───────────────────────────────────────────────────
function scanNetworksEsp() {
    const btn = document.getElementById('btn-scan-wifi');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Scanning...';
    sendEspOta('SCANWIFI');
    setTimeout(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-search me-1"></i>Scan Air'; }, 8000);
}
function parseWifiScan(data) {
    const btn = document.getElementById('btn-scan-wifi');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Scan Complete';
    setTimeout(() => { btn.innerHTML = '<i class="bi bi-search me-1"></i>Scan Air'; }, 3000);
    const sel = document.getElementById('esp-ssid-select');
    sel.innerHTML = '<option value="">Select a network...</option>';
    try {
        const networks = JSON.parse(data);
        networks.forEach(ssid => {
            const opt = document.createElement('option');
            opt.value = ssid; opt.textContent = ssid;
            sel.appendChild(opt);
        });
    } catch {
        data.split('\n').forEach(line => { if (line.trim()) { const opt = document.createElement('option'); opt.textContent = line.trim(); opt.value = line.trim(); sel.appendChild(opt); }});
    }
}
function updateWifiEsp() {
    const ssid = document.getElementById('esp-ssid-select').value;
    const pass = document.getElementById('esp-wifi-pass').value;
    const identity = document.getElementById('esp-identity-row')?.classList.contains('d-none') ? '' : (document.getElementById('esp-wifi-identity')?.value || '');
    if (!ssid) { alert('SSID is required.'); return; }
    const btn = document.getElementById('btn-save-wifi');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving & Rebooting...';
    sendEspOta(`SETWIFI ${ssid}|${pass}|${identity}`);
    setTimeout(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Sent — Device Rebooting'; }, 1000);
    setTimeout(() => { btn.innerHTML = '<i class="bi bi-save me-1"></i>Save & Reboot'; }, 6000);
}
function scanBluetoothEsp() {
    const btn = document.querySelector('[onclick*="scanBluetoothEsp"]');
    if (btn) { const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Scanning BLE...'; setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 10000); }
    sendEspOta('SCANBT');
}
function parseBtScan(data) {
    const sel = document.getElementById('esp-bt-list');
    sel.innerHTML = '<option value="">Scan results...</option>';
    try {
        const devices = JSON.parse(data);
        devices.forEach(d => { const opt = document.createElement('option'); opt.textContent = `${d.name} (${d.mac})`; opt.value = d.mac; sel.appendChild(opt); });
    } catch {
        data.split('\n').forEach(line => { if (line.trim()) { const opt = document.createElement('option'); opt.textContent = line.trim(); opt.value = line.trim(); sel.appendChild(opt); }});
    }
}

function loadDeviceSettingsEsp() {
    const ip = getEspTarget();
    if (!ip) return;
    fetch(`http://${ip}/cmd?command=${encodeURIComponent('GETCONFIG')}`, { signal: AbortSignal.timeout(5000) })
        .then(r => r.text())
        .then(resp => {
            resp.split(',').forEach(pair => {
                const [key, val] = pair.split('=').map(s => s.trim());
                if (!key || val === undefined) return;
                if (key === 'ENABLE_FP') { document.getElementById('set_enable_fingerprint').checked = (val === '1'); }
                else if (key === 'ENABLE_RFID') { document.getElementById('set_enable_rfid').checked = (val === '1'); }
                else if (key === 'ENABLE_FACE') { document.getElementById('set_enable_face').checked = (val === '1'); }
                else if (key === 'MFA') { document.getElementById('set_require_multi_factor').checked = (val === '1'); }
                else if (key === 'ENROLL_COUNT') {
                    const slider = document.getElementById('set_enroll_fingers');
                    if (slider) { slider.value = parseInt(val); document.getElementById('esp-enroll-count-val').innerText = val; }
                }
            });
            updateDeviceSettingsBadges();
        })
        .catch(e => console.warn('[DeviceSettings] Could not fetch config:', e.message));
}

function saveDeviceSettingsEsp() {
    const ip = serialEspIp || getEspTarget();
    if (!ip) { appendSerial('[ERR] No target device selected.', 'text-danger'); return; }
    const btn = document.querySelector('[onclick*="saveDeviceSettingsEsp"]');
    if (btn) { const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...'; setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 5000); }
    
    const cfg = 'ENABLE_FP=' + (document.getElementById('set_enable_fingerprint').checked ? 1 : 0) +
                ',ENABLE_RFID=' + (document.getElementById('set_enable_rfid').checked ? 1 : 0) +
                ',ENABLE_FACE=' + (document.getElementById('set_enable_face').checked ? 1 : 0) +
                ',MFA=' + (document.getElementById('set_require_multi_factor').checked ? 1 : 0) +
                ',ENROLL_COUNT=' + document.getElementById('set_enroll_fingers').value;
    
    appendSerial('[TX] SETCONFIG ' + cfg, 'text-info');
    fetch(`http://${ip}/cmd?command=${encodeURIComponent('SETCONFIG ' + cfg)}`, { signal: AbortSignal.timeout(5000) })
        .then(r => r.text())
        .then(resp => { appendSerial('[RX] ' + resp, 'text-success'); })
        .catch(e => appendSerial('[ERR] ' + e.message, 'text-danger'));
}

function updateDeviceSettingsBadges() {
    const fp = document.getElementById('set_enable_fingerprint').checked;
    const rfid = document.getElementById('set_enable_rfid').checked;
    const face = document.getElementById('set_enable_face').checked;
    const mfa = document.getElementById('set_require_multi_factor').checked;
    const label = document.querySelector('[for="set_enable_fingerprint"]');
    if (label) {
        label.innerHTML = 'Fingerprint Reader ' + (fp ? '<span class="badge bg-success ms-1">ON</span>' : '<span class="badge bg-danger ms-1">OFF</span>');
    }
    const rlabel = document.querySelector('[for="set_enable_rfid"]');
    if (rlabel) {
        rlabel.innerHTML = 'RFID Scanner ' + (rfid ? '<span class="badge bg-success ms-1">ON</span>' : '<span class="badge bg-danger ms-1">OFF</span>');
    }
    const flabel = document.querySelector('[for="set_enable_face"]');
    if (flabel) {
        flabel.innerHTML = 'Face Recognition ' + (face ? '<span class="badge bg-success ms-1">ON</span>' : '<span class="badge bg-danger ms-1">OFF</span>');
    }
    const mlabel = document.querySelector('[for="set_require_multi_factor"]');
    if (mlabel) {
        mlabel.innerHTML = 'Require Multi-Factor ' + (mfa ? '<span class="badge bg-warning text-dark ms-1">ON</span>' : '<span class="badge bg-secondary ms-1">OFF</span>');
    }
}

// ─── Digital Twin ────────────────────────────────────────────────
function filterTwinDropdown(inputEl) {
    const q = inputEl.value.toLowerCase().trim();
    const dd = document.getElementById('twin-device-list');
    const sel = document.getElementById('twin-device-select');
    if (!dd || !sel) return;
    dd.innerHTML = '';
    let visible = 0;
    for (let i = 1; i < sel.options.length; i++) {
        const opt = sel.options[i];
        const txt = (opt.textContent || '').toLowerCase();
        const val = (opt.value || '').toLowerCase();
        if (q === '' || txt.includes(q) || val.includes(q)) {
            const item = document.createElement('div');
            item.className = 'searchable-dropdown-item';
            item.textContent = opt.textContent;
            item.setAttribute('data-value', opt.value);
            item.setAttribute('data-name', opt.dataset.name || '');
            item.onclick = function() {
                sel.value = opt.value;
                inputEl.value = opt.textContent;
                switchTwinDevice();
                dd.classList.add('d-none');
            };
            dd.appendChild(item);
            visible++;
        }
    }
    dd.classList.toggle('d-none', visible === 0);
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('#twin-device-select, .searchable-select-input, #twin-device-list')) {
        document.getElementById('twin-device-list')?.classList.add('d-none');
    }
});
function switchTwinDevice() { currentEspIp = document.getElementById('twin-device-select')?.value || ''; for (let i = 0; i < 4; i++) document.getElementById(`lcd${i}`).textContent = 'Waiting for data... '; }
function updateDigitalTwin(data) {
    const parts = data.split('|');
    for (let i = 0; i < 4; i++) { const el = document.getElementById(`lcd${i}`); if (el) el.textContent = parts[i] || ''; }
}
function startLcdPolling() {
    if (lcdPollInterval) return;
    lcdPollInterval = setInterval(() => {
        if (!currentEspIp) return;
        fetch(`http://${currentEspIp}/cmd?command=GETLCD`, { signal: AbortSignal.timeout(5000) })
            .then(r => r.text()).then(data => { updateDigitalTwin(data); })
            .catch(e => appendSerial(`[LCD ERR] ${e.message}`, 'text-danger'));
    }, 2000);
    appendSerial('LCD auto-poll started (2s).', 'text-info');
}
function stopLcdPolling() { if (lcdPollInterval) { clearInterval(lcdPollInterval); lcdPollInterval = null; appendSerial('LCD auto-poll stopped.', 'text-warning'); } }

// ─── Sync with main.js functions ─────────────────────────────────
window.refreshDeviceList = async function() {
    const btn = document.querySelector('[onclick*="refreshDeviceList"]');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
    try {
        const res = await fetch('api/devices.php?action=list');
        const data = await res.json();
        const devices = data.devices || [];
        const table = document.getElementById('device-list-table');
        if (table) {
            if (devices.length === 0) { table.innerHTML = '<em class="text-muted">No hardware nodes discovered.</em>'; }
            else {
                let h = '<table class="table table-sm table-bordered mb-0" style="font-size:0.85rem"><thead class="table-dark"><tr><th>Device</th><th>IP</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
                devices.forEach(dev => {
                    const icon = dev.type === 'rpi' ? '<i class="bi bi-server text-danger me-1"></i>' : '<i class="bi bi-cpu text-primary me-1"></i>';
                    const badge = dev.online ? '<span class="badge bg-success">Online</span>' : '<span class="badge bg-secondary">Offline</span>';
                    const blockBtn = dev.blocked ? `<button class="btn btn-xs btn-outline-success py-0 px-1" onclick="deviceAction('unblock','${dev.ip}')">Unblock</button>` : `<button class="btn btn-xs btn-outline-danger py-0 px-1" onclick="deviceAction('block','${dev.ip}')">Block</button>`;
                    h += `<tr><td>${icon}<strong>${dev.name}</strong></td><td><code>${dev.ip}</code></td><td>${badge}</td><td class="d-flex gap-1">${blockBtn} <button class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="renameDevice('${dev.ip}','${dev.name.replace(/'/g, "\\'")}')"><i class="bi bi-pencil"></i></button> <button class="btn btn-xs btn-outline-dark py-0 px-1" onclick="deviceAction('forget','${dev.ip}')"><i class="bi bi-trash"></i></button></td></tr>`;
                });
                h += '</tbody></table>';
                table.innerHTML = h;
            }
        }
        // Update ESP32 target selector
        const espSel = document.getElementById('action-target-device');
        if (espSel) {
            const cur = espSel.value;
            espSel.innerHTML = '<option value="">Select ESP32 Node...</option>';
            devices.filter(d => d.type === 'esp32' && !d.blocked).forEach(dev => {
                const o = document.createElement('option');
                o.value = dev.ip; o.dataset.name = dev.name;
                o.textContent = `${dev.name} (${dev.ip})${dev.online ? ' ✅' : ' ⚫'}`;
                espSel.appendChild(o);
            });
            if (cur) espSel.value = cur;
        }
        // Update Digital Twin selector
        const twinSel = document.getElementById('twin-device-select');
        if (twinSel) {
            const cur = twinSel.value;
            twinSel.innerHTML = '<option value="">Select a device...</option>';
            devices.filter(d => !d.blocked).forEach(dev => {
                const o = document.createElement('option');
                o.value = dev.ip; o.dataset.name = dev.name;
                o.textContent = `${dev.name} (${dev.ip})${dev.online ? ' ✅' : ' ⚫'}`;
                twinSel.appendChild(o);
            });
            if (cur) twinSel.value = cur;
        }
        // Update Serial Monitor ESP selector
        const serialSel = document.getElementById('serial-esp-select');
        if (serialSel) {
            const cur = serialSel.value;
            const firstOpt = serialSel.querySelector('option');
            serialSel.innerHTML = '';
            serialSel.appendChild(firstOpt);
            devices.filter(d => d.type === 'esp32' && !d.blocked).forEach(dev => {
                const o = document.createElement('option');
                o.value = dev.ip;
                o.textContent = `${dev.name} (${dev.ip})${dev.online ? ' ✅' : ' ⚫'}`;
                serialSel.appendChild(o);
            });
            if (cur) serialSel.value = cur;
        }
        // Repopulate visible dropdown if open
        const openList = document.querySelector('.searchable-dropdown:not(.d-none)');
        if (openList) {
            const input = document.querySelector(`[data-list="${openList.id}"]`);
            if (input) filterSearchableSelect(input);
        }
    } catch(e) { console.warn('[Devices] Fetch failed:', e); }
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>'; }
};

window.toggleManageDevices = function() {
    const panel = document.getElementById('device-manage-panel');
    if (panel) panel.classList.toggle('d-none');
};

window.deviceAction = async function(action, ip) {
    try {
        const fd = new FormData(); fd.append('ip', ip);
        const res = await fetch(`api/devices.php?action=${action}`, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok') { showToast('OK', 'success'); window.refreshDeviceList(); }
        else alert('Error: ' + (data.message || 'Unknown'));
    } catch(e) { alert('Network error.'); }
};

window.renameDevice = async function(ip, currentName) {
    const newName = prompt('Enter new name for ' + currentName + ':', currentName);
    if (!newName || newName === currentName) return;
    try {
        const fd = new FormData(); fd.append('ip', ip); fd.append('name', newName);
        const res = await fetch('api/devices.php?action=rename', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok') { showToast('Device renamed!', 'success'); window.refreshDeviceList(); }
        else alert('Error: ' + (data.message || 'Unknown'));
    } catch(e) { alert('Network error.'); }
};

window.registerNewDevice = async function() {
    const ip = document.getElementById('new-device-ip')?.value?.trim();
    const name = document.getElementById('new-device-name')?.value?.trim();
    const type = document.getElementById('new-device-type')?.value || 'esp32';
    if (!ip || !name) { alert('IP and Name required.'); return; }
    try {
        const fd = new FormData(); fd.append('ip', ip); fd.append('name', name); fd.append('type', type);
        const res = await fetch('api/devices.php?action=register', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok') { showToast('Device registered!', 'success'); document.getElementById('new-device-ip').value = ''; document.getElementById('new-device-name').value = ''; window.refreshDeviceList(); }
        else alert('Error: ' + (data.message || 'Unknown'));
    } catch(e) { alert('Server error.'); }
};

window.assignCourseToDevice = async function() {
    const device = document.getElementById('assign-device-select')?.value;
    const code = document.getElementById('assign-course-select')?.value;
    if (!device || !code) { alert('Select device and course.'); return; }
    try {
        const fd = new FormData(); fd.append('action', 'assign_course_to_device'); fd.append('device_id', device); fd.append('course_code', code);
        const res = await fetch('api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') { showToast(data.message, 'success'); setTimeout(() => location.reload(), 800); }
        else showToast(data.message, 'danger');
    } catch(e) { showToast('Network error.', 'danger'); }
};

window.removeDeviceCourse = async function(device, code) {
    if (!confirm('Remove ' + code + ' from ' + device + '?')) return;
    try {
        const fd = new FormData(); fd.append('action', 'remove_course_from_device'); fd.append('device_id', device); fd.append('course_code', code);
        const res = await fetch('api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') { showToast(data.message, 'success'); setTimeout(() => location.reload(), 800); }
        else showToast(data.message, 'danger');
    } catch(e) { showToast('Network error.', 'danger'); }
};

window.refreshRpiStatus = async function() {
    const ip = getRpiIp();
    const dot = document.getElementById('rpi-status-dot');
    const ipDisplay = document.getElementById('rpi-ip-display');
    if (!ip) { alert('Enter or select Pi IP first.'); return; }
    const orig = dot.innerHTML;
    dot.innerHTML = '<span class="spinner-border spinner-border-sm spinner-border-sm me-1"></span>Checking...';
    try {
        const res = await fetch(`http://${ip}:5000/api/status`, { signal: AbortSignal.timeout(3000) });
        if (res.ok) { dot.className = 'badge bg-success'; dot.innerHTML = '<i class="bi bi-check-circle me-1"></i>Online'; ipDisplay.textContent = ip; localStorage.setItem('rpi_node_ip', ip); return; }
    } catch(e) {}
    dot.className = 'badge bg-danger'; dot.innerHTML = '<i class="bi bi-circle me-1"></i>Offline'; ipDisplay.textContent = 'Not connected';
};

window.saveRpiConfig = function() {
    const sel = document.getElementById('rpi-ip-select');
    const search = document.getElementById('rpi-ip-search');
    const ip = (sel && sel.value) ? sel.value : (search ? search.value.trim() : '');
    if (!ip) { alert('Enter or select Pi IP.'); return; }
    localStorage.setItem('rpi_node_ip', ip);
    showToast('Pi config saved.', 'success');
    window.refreshRpiStatus();
};

window.triggerRpiOta = async function() {
    const ip = getRpiIp();
    if (!ip) { alert('Set Pi IP first.'); return; }
    try {
        const res = await fetch('api/ota.php?action=poll&device=rpi');
        const data = await res.json();
        if (data.status === 'success' && data.commands) {
            await fetch(`http://${ip}:5000/api/ota`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data.commands) });
            showToast('Commands sent to Pi.', 'success');
        } else showToast('No pending commands.', 'info');
    } catch(e) { showToast('Could not reach Pi.', 'danger'); }
};

window.sendRpiCommand = async function(cmd) {
    const ip = getRpiIp();
    if (!ip) { alert('Set Pi IP first.'); return; }
    const out = document.getElementById('rpi-serial-output');
    if (out) { const l = document.createElement('div'); l.textContent = `[${new Date().toLocaleTimeString()}] TX: ${cmd}`; out.appendChild(l); }
    try {
        const res = await fetch(`http://${ip}:5000/api/command`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ command: cmd }), signal: AbortSignal.timeout(10000) });
        const data = await res.json();
        if (out) { const l = document.createElement('div'); l.textContent = `[${new Date().toLocaleTimeString()}] RX: ${data.message || data.output || JSON.stringify(data)}`; out.appendChild(l); out.scrollTop = out.scrollHeight; }
    } catch(e) { if (out) { const l = document.createElement('div'); l.className = 'text-danger'; l.textContent = `[ERR] ${e.message}`; out.appendChild(l); out.scrollTop = out.scrollHeight; } }
};

window.fetchRpiSysInfo = async function() {
    const ip = getRpiIp();
    if (!ip) { alert('Set Pi IP first.'); return; }
    const btn = document.querySelector('[onclick*="fetchRpiSysInfo"]');
    if (btn) { const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Fetching...'; }
    try {
        const res = await fetch(`http://${ip}:5000/api/sysinfo`, { signal: AbortSignal.timeout(5000) });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const d = await res.json();
        document.getElementById('rpi-cpu-temp').textContent = d.cpu_temp ?? '—';
        document.getElementById('rpi-cpu-usage').textContent = d.cpu_usage ?? '—';
        document.getElementById('rpi-ram-free').textContent = d.ram_free ?? '—';
        document.getElementById('rpi-uptime').textContent = d.uptime ?? '—';
        document.getElementById('rpi-face-count').textContent = d.face_count ?? '—';
        const svc = document.getElementById('rpi-service-status');
        svc.textContent = d.service ?? '—'; svc.className = 'badge ' + (d.service === 'running' ? 'bg-success' : 'bg-danger');
    } catch(e) { alert('Cannot reach Pi: ' + e.message); }
    finally { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Refresh System Info'; } }
};

window.startRpiAttendance = async function() {
    const ip = getRpiIp();
    if (!ip) { alert('Set Pi IP first.'); return; }
    document.getElementById('btn-start-rpi-attendance').classList.add('d-none');
    document.getElementById('btn-stop-rpi-attendance').classList.remove('d-none');
    const img = document.getElementById('rpi-cam-stream');
    img.src = `http://${ip}:5000/stream`; img.style.display = 'block';
    document.getElementById('rpi-cam-placeholder').style.display = 'none';
    if (window._rpiPoll) clearInterval(window._rpiPoll);
    window._rpiPoll = setInterval(async () => {
        try {
            const res = await fetch(`http://${ip}:5000/api/face-status`, { signal: AbortSignal.timeout(3000) });
            if (!res.ok) return;
            const data = await res.json();
            if (data.face_detected) {
                const auto = document.getElementById('rpi-attendance-auto')?.checked;
                if (auto && data.student_no && !window._rpiLastMarked) {
                    window._rpiLastMarked = data.student_no;
                    setTimeout(() => { window._rpiLastMarked = null; }, 5000);
                    const fd = new FormData(); fd.append('action', 'manual_attendance'); fd.append('student_no', data.student_no); fd.append('modality', 'rpi_face');
                    const course = window.activeWebCourse || '';
                    if (course) fd.append('course_code', course);
                    await fetch('api/student.php', { method: 'POST', body: fd });
                }
                const mute = document.getElementById('rpi-attendance-mute')?.checked;
                const globalMute = document.getElementById('global-voice-mute')?.checked;
                if (!mute && !globalMute && data.student_name && 'speechSynthesis' in window) {
                    window.speechSynthesis.speak(new SpeechSynthesisUtterance(auto ? data.student_name + ' marked present' : data.student_name + ' detected'));
                }
                document.getElementById('rpi-attendance-status').innerHTML = `<span class="text-success fw-bold">${data.student_name || data.student_no} ${auto ? '<span class="badge bg-success">Marked</span>' : ''}</span>`;
            } else {
                document.getElementById('rpi-attendance-status').innerHTML = '<span class="text-muted"><i class="bi bi-camera-video me-1"></i>Scanning...</span>';
            }
        } catch(e) {}
    }, 1500);
};

window.stopRpiAttendance = function() {
    if (window._rpiPoll) clearInterval(window._rpiPoll);
    window._rpiPoll = null; window._rpiLastMarked = null;
    const img = document.getElementById('rpi-cam-stream');
    img.src = ''; img.style.display = 'none';
    document.getElementById('rpi-cam-placeholder').style.display = 'flex';
    document.getElementById('rpi-attendance-status').innerHTML = '';
    document.getElementById('btn-start-rpi-attendance').classList.remove('d-none');
    document.getElementById('btn-stop-rpi-attendance').classList.add('d-none');
};

window.sendRpiCmdFromInput = function() { const cmd = document.getElementById('rpi-cmd-input')?.value?.trim(); if (cmd) { window.sendRpiCommand(cmd); document.getElementById('rpi-cmd-input').value = ''; } };

function sendMeshCmd() {
    const cmd = document.getElementById('mesh-cmd-input')?.value?.trim();
    if (!cmd) return;
    sendEspOta(cmd);
    document.getElementById('mesh-cmd-input').value = '';
}

function getRpiIp() {
    const sel = document.getElementById('rpi-ip-select');
    if (sel && sel.value) return sel.value;
    const search = document.getElementById('rpi-ip-search');
    if (search && search.value.trim()) return search.value.trim();
    return localStorage.getItem('rpi_node_ip') || '';
}

window.updateRpiWifi = function() {
    const ssid = document.getElementById('rpi-ssid-input')?.value?.trim();
    const pass = document.getElementById('rpi-pass-input')?.value?.trim();
    const enterprise = document.getElementById('rpi-enterprise-toggle')?.checked;
    const identity = document.getElementById('rpi-identity-input')?.value?.trim();
    if (!ssid) { alert('Enter SSID.'); return; }
    let cmd = 'SETWIFI:' + ssid + '|' + pass;
    if (enterprise && identity) cmd += '|' + identity;
    const btn = document.getElementById('btn-update-rpi-wifi');
    if (btn) { const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...'; setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 5000); }
    window.sendRpiCommand(cmd);
};

window.onEspTargetChange = function() {
    const sel = document.getElementById('action-target-device');
    const ip = sel?.value || '';
    const name = sel?.selectedOptions[0]?.dataset?.name || '';
    const statusEl = document.getElementById('esp-enc-status');
    const ipEl = document.getElementById('esp-current-ip');
    const serialSel = document.getElementById('serial-esp-select');
    if (!ip) {
        if (statusEl) { statusEl.className = 'alert alert-light py-2 mb-2 small border text-muted'; statusEl.innerHTML = '<i class="bi bi-info-circle me-1"></i>Select a target device to view its encryption settings'; }
        if (ipEl) ipEl.textContent = 'Not selected';
        return;
    }
    if (statusEl) { statusEl.className = 'alert alert-info py-2 mb-2 small border'; statusEl.innerHTML = '<i class="bi bi-hdd-rack me-1"></i>Connected to <strong>' + name + '</strong> (' + ip + ')'; }
    if (ipEl) ipEl.textContent = ip;
    fetchTelemetry();
    getEspSecurity();
    loadDeviceSettingsEsp();
};

window.getEspSecurity = function() {
    const ip = getEspTarget();
    if (!ip) { alert('Please select a target ESP32 node first.'); return; }
    const btn = document.getElementById('btn-get-security');
    if (btn) { const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Fetching...'; setTimeout(() => { if (btn.disabled) { btn.disabled = false; btn.innerHTML = orig; } }, 6000); }
    appendSerial('[TX] -> ' + ip + '/cmd : GETSECURITY', 'text-info');
    fetch('http://' + ip + '/cmd?command=GETSECURITY', { signal: AbortSignal.timeout(5000) })
        .then(r => r.text())
        .then(data => {
            appendSerial('[RX] <- ' + data.trim(), 'text-success');
            parseSecurity(data.trim());
            const parts = data.trim().split('|');
            const method = parts[0]?.toLowerCase() || '';
            const statusEl = document.getElementById('esp-enc-status');
            const badges = { plaintext: '<span class="badge bg-secondary">Plaintext</span>', hmac: '<span class="badge bg-success">HMAC</span>', aes: '<span class="badge bg-primary">AES-128</span>' };
            if (statusEl) statusEl.innerHTML = '<i class="bi bi-shield-check me-1"></i>Device encryption: ' + (badges[method] || method);
        })
        .catch(e => {
            appendSerial('[ERR] ' + e.message, 'text-danger');
            const statusEl = document.getElementById('esp-enc-status');
            if (statusEl) { statusEl.className = 'alert alert-danger py-2 mb-2 small border'; statusEl.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Failed to reach device'; }
        })
        .finally(() => { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Get Current from Device'; } });
};

window.saveEspSecurity = function() {
    const ip = getEspTarget();
    if (!ip) { alert('Please select a target ESP32 node first.'); return; }
    const method = document.getElementById('esp-enc-method')?.value || 'plaintext';
    const token = document.getElementById('esp-enc-token')?.value?.trim();
    if (!token && method !== 'plaintext') { alert('Enter a token for ' + method + ' mode.'); return; }
    const cmd = 'SETSECURITY:' + method + '|' + token;
    const btn = document.getElementById('btn-apply-security');
    if (btn) { const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying...'; }
    sendEspOta(cmd);
    const statusEl = document.getElementById('esp-enc-status');
    const badges = { plaintext: '<span class="badge bg-secondary">Plaintext</span>', hmac: '<span class="badge bg-success">HMAC</span>', aes: '<span class="badge bg-primary">AES-128</span>' };
    if (statusEl) { statusEl.className = 'alert alert-success py-2 mb-2 small border'; statusEl.innerHTML = '<i class="bi bi-check-circle me-1"></i>Applied ' + (badges[method] || method) + ' to device'; }
    setTimeout(() => { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-shield-check me-1"></i>Apply Security'; } }, 5000);
};

window._deviceStatusCache = {};

async function refreshAllDeviceStatus() {
    const tbody = document.getElementById('device-overview-body');
    const countBadge = document.getElementById('dev-overall-count');
    tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-1"></span>Probing all devices...</td></tr>';
    try {
        const res = await fetch('api/devices.php?action=list');
        const data = await res.json();
        if (!data || !data.devices) { tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted">No devices found</td></tr>'; return; }
        const devices = data.devices;
        const lectures = window.activeLectures || {};
        const countBadgeEl = document.getElementById('dev-overall-count');
        if (countBadgeEl) countBadgeEl.textContent = devices.length + ' devices';
        let html = '';
        for (const dev of devices) {
            const ip = dev.ip;
            const name = dev.name || ip;
            const type = dev.type || 'esp32';
            const online = dev.online;
            const typeIcon = type === 'rpi' ? '<i class="bi bi-raspberry-pi text-danger me-1"></i>' : '<i class="bi bi-cpu text-primary me-1"></i>';
            const statusBadge = online ? '<span class="badge bg-success"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i>Online</span>' : '<span class="badge bg-danger"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i>Offline</span>';
            const lec = lectures[ip];
            const courseBadge = lec ? '<span class="badge bg-success">' + escapeHtml(lec.course) + '</span>' : '<span class="text-muted">—</span>';
            const lastSeen = dev.last_seen ? timeAgo(dev.last_seen) : '—';
            const cached = window._deviceStatusCache[ip] || {};
            const rssi = cached.rssi ? cached.rssi + ' dBm' : '—';
            const mode = cached.mode ? '<span class="badge bg-dark">' + escapeHtml(cached.mode) + '</span>' : '—';
            const enc = cached.enc ? cached.enc : '—';
            const queue = cached.queue !== undefined ? '<span class="badge ' + (cached.queue > 0 ? 'bg-warning text-dark' : 'bg-success') + '">' + cached.queue + '</span>' : '—';
            const actions = [];
            if (online) {
                actions.push('<button class="btn btn-xs btn-outline-info py-0 px-1 me-1" onclick="probeDevice(\'' + ip + '\')" title="Probe"><i class="bi bi-search"></i></button>');
                if (type === 'esp32') actions.push('<button class="btn btn-xs btn-outline-primary py-0 px-1 me-1" onclick="document.getElementById(\'action-target-device\').value=\'' + ip + '\';onEspTargetChange()" title="Select"><i class="bi bi-bullseye"></i></button>');
            }
            if (type === 'rpi') {
                actions.push('<button class="btn btn-xs btn-outline-danger py-0 px-1" onclick="document.getElementById(\'rpi-ip-search\').value=\'' + ip + '\';saveRpiConfig()" title="Set as Pi"><i class="bi bi-raspberry-pi"></i></button>');
            }
            html += '<tr><td class="ps-3 fw-bold">' + typeIcon + escapeHtml(name) + '</td>';
            html += '<td class="font-monospace small">' + ip + '</td>';
            html += '<td><span class="badge ' + (type === 'rpi' ? 'bg-danger' : 'bg-primary') + '">' + type.toUpperCase() + '</span></td>';
            html += '<td>' + statusBadge + '</td>';
            html += '<td id="dt-mode-' + ip.replace(/\./g, '_') + '">' + mode + '</td>';
            html += '<td>' + courseBadge + '</td>';
            html += '<td id="dt-rssi-' + ip.replace(/\./g, '_') + '" class="font-monospace small">' + rssi + '</td>';
            html += '<td id="dt-enc-' + ip.replace(/\./g, '_') + '">' + enc + '</td>';
            html += '<td id="dt-queue-' + ip.replace(/\./g, '_') + '">' + queue + '</td>';
            html += '<td class="small">' + lastSeen + '</td>';
            html += '<td class="pe-3">' + actions.join('') + '</td></tr>';
        }
        tbody.innerHTML = html;
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-danger">Failed to load devices</td></tr>';
    }
}

window.probeDevice = async function(ip) {
    const el = (id) => document.getElementById(id);
    const key = ip.replace(/\./g, '_');
    el('dt-rssi-' + key).innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    el('dt-queue-' + key).innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    el('dt-mode-' + key).innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        const res = await fetch('http://' + ip + '/cmd?command=GETSTATUS', { signal: AbortSignal.timeout(5000) });
        const raw = await res.text();
        const d = {};
        if (raw.startsWith('{')) { try { Object.assign(d, JSON.parse(raw)); } catch(e) {} }
        else { raw.split('\n').forEach(l => { const m = l.match(/^(\w+)\s*[:=]\s*(.+)$/i); if (m) d[m[1].toUpperCase()] = m[2].trim(); }); }
        const cache = {};
        cache.rssi = d.RSSI || d.rssi || null;
        cache.queue = parseInt(d.QUEUE || d.queue || 0) || 0;
        cache.mode = d.MODE || d.mode || null;
        cache.enc = '—';
        window._deviceStatusCache[ip] = cache;
        const rssiVal = cache.rssi;
        const rssiBadge = rssiVal ? (parseInt(rssiVal) > -50 ? 'bg-success' : (parseInt(rssiVal) > -70 ? 'bg-warning text-dark' : 'bg-danger')) : 'bg-secondary';
        el('dt-rssi-' + key).innerHTML = rssiVal ? '<span class="badge ' + rssiBadge + '">' + rssiVal + ' dBm</span>' : '—';
        el('dt-queue-' + key).innerHTML = '<span class="badge ' + (cache.queue > 0 ? 'bg-warning text-dark' : 'bg-success') + '">' + cache.queue + '</span>';
        el('dt-mode-' + key).innerHTML = cache.mode ? '<span class="badge bg-dark">' + escapeHtml(cache.mode) + '</span>' : '—';
    } catch (e) {
        el('dt-rssi-' + key).innerHTML = '<span class="badge bg-danger">Timeout</span>';
        el('dt-queue-' + key).innerHTML = '<span class="badge bg-danger">Off</span>';
        el('dt-mode-' + key).innerHTML = '<span class="badge bg-danger">—</span>';
    }
};

function timeAgo(ts) {
    const diff = Math.floor(Date.now() / 1000) - parseInt(ts);
    if (diff < 30) return 'Just now';
    if (diff < 60) return diff + 's ago';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

function escapeHtml(str) { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; }

window.setButtonLoading = function(btnId, loading, originalHtml) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    if (loading) { btn.disabled = true; btn.dataset.original = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...'; }
    else { btn.disabled = false; if (originalHtml) btn.innerHTML = originalHtml; }
};

if (document.getElementById('device-overview-body')) {
    refreshAllDeviceStatus();
    setInterval(refreshAllDeviceStatus, 30000);
}

let telemetryAutoPoll = null;
window.startTelemetryAutoPoll = function() {
    if (telemetryAutoPoll) clearInterval(telemetryAutoPoll);
    telemetryAutoPoll = setInterval(() => {
        if (getEspTarget()) fetchTelemetry();
    }, 10000);
};
window.startTelemetryAutoPoll();
updateDeviceSettingsBadges();
</script>
