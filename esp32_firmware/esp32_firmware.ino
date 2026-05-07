// ============================================================
// SENTINEL SWARM — ESP32 Attendance Firmware
// FM10A Fingerprint | DS3231 RTC | LittleFS Offline Queue
// LiquidCrystal_I2C LCD | Dashboard OTA | Auto-Sync
// ============================================================

#include <WiFi.h>
#include <HTTPClient.h>
#include <WebServer.h>
#include <Preferences.h>
#include <Wire.h>
#include <RTClib.h>
#include <Adafruit_Fingerprint.h>
#include <LittleFS.h>
#include <SPI.h>
#include <MFRC522.h>
#include <LiquidCrystal_I2C.h>
#include <BLEDevice.h>
#include <BLEUtils.h>
#include <BLEScan.h>
#include <BLEAdvertisedDevice.h>

Preferences preferences;

// ============================================================
// ⚙️  SYSTEM CONFIGURATION  — Edit these before flashing
// ============================================================
const char* WIFI_SSID = "Card ekak daganin bn";
const char* WIFI_PASS = "meshan1234";

// Your PC's IPv4 Address (run 'ipconfig' in CMD to find it)
const char* SERVER_IP = "10.149.146.212";   // <-- CHANGE THIS!
// ============================================================

// --- Derived URLs ---
String serverURL;
String heartbeatURL;
String enrollApiURL;
String studentApiURL;

const char* DEVICE_NAME = "UoP_Scanner_1";

// --- Offline queue file on LittleFS ---
const char* OFFLINE_LOG_FILE = "/offline_queue.csv";

// --- Global State ---
String currentCourse    = "Unassigned Session";
String currentMode      = "ATTENDANCE_MODE";
bool   isOnline         = false;
bool   rtcOK            = false;
unsigned long bootMillis = 0;

// --- Timing ---
unsigned long lastHeartbeatTime  = 0;
unsigned long lastSyncAttempt    = 0;
unsigned long lastWifiRetry      = 0;
const unsigned long HEARTBEAT_INTERVAL = 10000;
const unsigned long SYNC_INTERVAL      = 30000;
const unsigned long WIFI_RETRY_MS      = 15000;

// --- Enrollment ---
int pendingEnrollSlot = -1;

// --- Runtime Device Settings (loaded from Preferences on boot) ---
bool enableFingerprint = true;
bool enableRfid        = true;
bool enableFace        = false;
bool requireMfa        = false;
int  enrollCount       = 3;

void loadDeviceConfig() {
  preferences.begin("config", true); // read-only
  enableFingerprint = preferences.getString("ENABLE_FP", "1") == "1";
  enableRfid        = preferences.getString("ENABLE_RFID", "1") == "1";
  enableFace        = preferences.getString("ENABLE_FACE", "0") == "1";
  requireMfa        = preferences.getString("MFA", "0") == "1";
  enrollCount       = preferences.getInt("ENROLL_COUNT", 3);
  preferences.end();
  espPrintln("[CONFIG] Loaded: FP=" + String(enableFingerprint ? "ON" : "OFF") +
             " RFID=" + String(enableRfid ? "ON" : "OFF") +
             " Face=" + String(enableFace ? "ON" : "OFF") +
             " MFA=" + String(requireMfa ? "ON" : "OFF") +
             " EnrollCount=" + String(enrollCount));
}

// --- Hardware ---
WebServer webServer(80);
RTC_DS3231 rtc;
HardwareSerial mySerial(2);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);

// --- RFID Hardware ---
#define SS_PIN 5
#define RST_PIN 22
MFRC522 mfrc522(SS_PIN, RST_PIN);

// --- LCD Hardware (I2C at address 0x27, 20x4) ---
LiquidCrystal_I2C lcd(0x27, 20, 4);
bool lcdOK = false;

// --- LCD State (4 lines mirrored to web) ---
String lcdLine[4] = {"", "", "", ""};

void lcdWrite(int row, String text) {
  if (text.length() < 20) { while(text.length() < 20) text += ' '; }
  else text = text.substring(0, 20);
  lcdLine[row] = text;
  if (lcdOK) {
    lcd.setCursor(0, row);
    lcd.print(text);
  }
}

void lcdClear() {
  for (int i = 0; i < 4; i++) lcdLine[i] = "                    ";
  if (lcdOK) lcd.clear();
}

// --- BLE Hardware ---
BLEScan* pBLEScan;
bool bleScannerEnabled = false;

// --- Virtual Keypad State ---
String pendingKeypadKey = "";

// --- Web Serial Logger ---
String webLogBuffer = "";
void espPrint(String msg) {
  Serial.print(msg);
  webLogBuffer += msg;
  if (webLogBuffer.length() > 4000) webLogBuffer = webLogBuffer.substring(webLogBuffer.length() - 2000);
}
void espPrintln(String msg) {
  Serial.println(msg);
  webLogBuffer += msg + "\n";
  if (webLogBuffer.length() > 4000) webLogBuffer = webLogBuffer.substring(webLogBuffer.length() - 2000);
}
void espPrintln() {
  Serial.println();
  webLogBuffer += "\n";
}
void handleGetLogs() {
  webServer.sendHeader("Access-Control-Allow-Origin", "*");
  webServer.send(200, "text/plain", webLogBuffer);
  webLogBuffer = "";
}

// --- URL Encoding ---
String urlEncode(const String& src) {
  String out;
  out.reserve(src.length() * 2);
  for (unsigned int i = 0; i < src.length(); i++) {
    char c = src.charAt(i);
    if (c == ' ') out += "%20";
    else if (c == ':') out += "%3A";
    else if (c == '&') out += "%26";
    else if (c == '+') out += "%2B";
    else if (c == '=') out += "%3D";
    else out += c;
  }
  return out;
}

// ============================================================
// UTILITY: Get timestamp string from RTC or millis fallback
// ============================================================
String getTimestamp() {
  if (rtcOK) {
    DateTime now = rtc.now();
    if (now.year() >= 2020 && now.year() <= 2099 &&
        now.month() >= 1  && now.month() <= 12  &&
        now.day()   >= 1  && now.day()   <= 31) {
      char buf[20];
      snprintf(buf, sizeof(buf), "%04d-%02d-%02d %02d:%02d:%02d",
               now.year(), now.month(), now.day(),
               now.hour(), now.minute(), now.second());
      return String(buf);
    }
    espPrintln("[RTC] WARNING: RTC returned invalid date. Using millis fallback.");
  }
  unsigned long sec = millis() / 1000;
  unsigned long hh = sec / 3600; sec %= 3600;
  unsigned long mm = sec / 60;   sec %= 60;
  char buf[25];
  snprintf(buf, sizeof(buf), "BOOT+%02lu:%02lu:%02lu", hh, mm, sec);
  return String(buf);
}

// ============================================================
// UTILITY: Try to connect WiFi (non-blocking timeout)
// ============================================================
bool tryConnectWifi() {
  if (WiFi.status() == WL_CONNECTED) return true;

  espPrint("[WIFI] Connecting to: "); espPrintln(WIFI_SSID);
  WiFi.disconnect(true);
  delay(200);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 8000) {
    delay(300); espPrint(".");
  }
  espPrintln();

  if (WiFi.status() == WL_CONNECTED) {
    espPrintln("[WIFI] Connected! IP: " + WiFi.localIP().toString());
    return true;
  }
  espPrintln("[WIFI] Could not connect. Running OFFLINE.");
  return false;
}

// ============================================================
// OFFLINE QUEUE: Append a record to LittleFS CSV
// ============================================================
void saveToOfflineQueue(String fingerId, String modality, String ts) {
  File f = LittleFS.open(OFFLINE_LOG_FILE, FILE_APPEND);
  if (!f) {
    espPrintln("[LittleFS] ERROR: Could not open offline queue for writing!");
    return;
  }
  f.printf("%s,%s,%s\n", fingerId.c_str(), modality.c_str(), ts.c_str());
  f.close();
  espPrintln("[OFFLINE] Scan queued locally: " + fingerId + " @ " + ts);
}

// ============================================================
// OFFLINE QUEUE: Count lines in queue
// ============================================================
int offlineQueueCount() {
  if (!LittleFS.exists(OFFLINE_LOG_FILE)) return 0;
  File f = LittleFS.open(OFFLINE_LOG_FILE, FILE_READ);
  if (!f) return 0;
  int count = 0;
  while (f.available()) {
    String line = f.readStringUntil('\n');
    if (line.length() > 3) count++;
  }
  f.close();
  return count;
}

// ============================================================
// OFFLINE SYNC: Push queued records to server, clear on success
// ============================================================
void syncOfflineQueue() {
  if (!isOnline) return;
  if (!LittleFS.exists(OFFLINE_LOG_FILE)) return;

  File f = LittleFS.open(OFFLINE_LOG_FILE, FILE_READ);
  if (!f) return;

  espPrintln("\n[SYNC] Syncing offline queue to server...");
  int synced = 0, failed = 0;

  std::vector<String> lines;
  while (f.available()) {
    String line = f.readStringUntil('\n');
    line.trim();
    if (line.length() > 3) lines.push_back(line);
  }
  f.close();

  if (lines.empty()) {
    LittleFS.remove(OFFLINE_LOG_FILE);
    return;
  }

  std::vector<String> failedLines;

  for (auto& line : lines) {
    int c1 = line.indexOf(',');
    int c2 = line.indexOf(',', c1 + 1);
    if (c1 < 0 || c2 < 0) continue;

    String fId       = line.substring(0, c1);
    String modality  = line.substring(c1 + 1, c2);
    String ts        = line.substring(c2 + 1);

    WiFiClient client;
    HTTPClient http;
    http.begin(client, serverURL);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    String tsEncoded = ts; tsEncoded.replace(" ", "%20");
    String postData = "finger_id=" + fId +
                      "&device_name=" + String(DEVICE_NAME) +
                      "&course_code=" + currentCourse +
                      "&timestamp=" + tsEncoded +
                      "&modality=" + modality +
                      "&offline_sync=1";
    int code = http.POST(postData);
    if (code > 0) {
      espPrintln("[SYNC] OK: " + fId + " -> HTTP " + String(code));
      synced++;
    } else {
      espPrintln("[SYNC] FAIL: " + fId + " -> " + http.errorToString(code));
      failedLines.push_back(line);
      failed++;
    }
    http.end();
    delay(100);
  }

  if (failedLines.empty()) {
    LittleFS.remove(OFFLINE_LOG_FILE);
    espPrintln("[SYNC] All " + String(synced) + " records synced. Queue cleared.");
  } else {
    File fw = LittleFS.open(OFFLINE_LOG_FILE, FILE_WRITE);
    if (fw) {
      for (auto& fl : failedLines) { fw.println(fl); }
      fw.close();
    }
    espPrintln("[SYNC] Synced: " + String(synced) + " | Still pending: " + String(failed));
  }
}

// ============================================================
// HEARTBEAT: Ping PHP backend (registers device IP + name)
// ============================================================
void sendHeartbeat() {
  if (WiFi.status() != WL_CONNECTED) return;

  WiFiClient client;
  HTTPClient http;
  http.setTimeout(5000);
  String hbUrl = heartbeatURL + "?device=esp32&name=" + String(DEVICE_NAME);
  http.begin(client, hbUrl);
  int code = http.GET();
  if (code > 0) {
    Serial.print(".");
    isOnline = true;
  } else {
    espPrint("\n[HTTP] Heartbeat FAIL: " + http.errorToString(code));
    isOnline = false;
  }
  http.end();
}

// ============================================================
// ATTENDANCE: POST one record to server, save offline on fail
// ============================================================
void markAttendance(String fingerId, String modality, String ts) {
  if (WiFi.status() != WL_CONNECTED || !isOnline) {
    espPrintln("[OFFLINE] No server. Saving locally...");
    saveToOfflineQueue(fingerId, modality, ts);
    return;
  }

  WiFiClient client;
  HTTPClient http;
  http.setTimeout(8000);
  http.begin(client, serverURL);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  String tsEncoded = ts; tsEncoded.replace(" ", "%20");
  String postData = "finger_id=" + fingerId +
                    "&device_name=" + String(DEVICE_NAME) +
                    "&course_code=" + currentCourse +
                    "&timestamp=" + tsEncoded +
                    "&modality=" + modality;

  int code = http.POST(postData);
  if (code > 0) {
    String payload = http.getString();
    espPrintln("[SERVER] HTTP " + String(code) + " | " + payload);
    isOnline = true;
    
    if (payload.indexOf("\"status\":\"admin\"") >= 0 || payload.indexOf("\"status\": \"admin\"") >= 0) {
      espPrintln("\n[ADMIN] Admin Verified! Ending Active Lecture.");
      currentCourse = "Unassigned Session";
      syncOfflineQueue();
      printMenu();
    }
  } else {
    espPrintln("[SERVER] Failed (" + http.errorToString(code) + "). Saving offline...");
    saveToOfflineQueue(fingerId, modality, ts);
    isOnline = false;
  }
  http.end();
}

// ============================================================
// ENROLLMENT: Interactive wizard (Serial or OTA triggered)
// ============================================================
void runEnrollment(int overrideSlot) {
  int    slotId    = overrideSlot;
  String studentNo = "";

  if (slotId == -1) {
    espPrintln("\n--- ENROLLMENT WIZARD ---");
    espPrintln("Enter Student No (e.g. S/20/123) then press ENTER:");
    while (Serial.available()) Serial.read();

    unsigned long waitStart = millis();
    while (studentNo == "") {
      webServer.handleClient();
      if (Serial.available() > 0) {
        studentNo = Serial.readStringUntil('\n');
        studentNo.trim();
        if (studentNo.length() < 2) studentNo = "";
      }
      if (millis() - waitStart > 60000) {
        espPrintln("[ENROLL] Timeout waiting for input. Aborting.");
        return;
      }
    }
    espPrintln("[CONFIG] Enrolling: " + studentNo);

    if (isOnline && WiFi.status() == WL_CONNECTED) {
      WiFiClient client;
      HTTPClient http;
      http.setTimeout(6000);
      http.begin(client, enrollApiURL);
      int code = http.GET();
      if (code == 200) {
        String resp = http.getString();
        resp.trim();
        slotId = resp.toInt();
        espPrintln("[SERVER] Assigned Slot #" + String(slotId));
      } else {
        espPrintln("[SERVER] Could not get slot (HTTP " + String(code) + "). Defaulting to 1.");
        slotId = 1;
      }
      http.end();
    } else {
      espPrintln("[OFFLINE] No server. Defaulting to slot 1.");
      slotId = 1;
    }
  } else {
    espPrintln("\n--- WEB-TRIGGERED ENROLLMENT ---");
    espPrintln("[CONFIG] Slot: " + String(slotId));
  }

  espPrintln("Enrolling Slot #" + String(slotId));
  espPrintln("=> Place finger on scanner...");

  unsigned long timeout = millis() + 15000;
  while (finger.getImage() != FINGERPRINT_OK) {
    webServer.handleClient();
    delay(20);
    if (millis() > timeout) {
      espPrintln("[ENROLL] Timeout waiting for finger. Aborting.");
      return;
    }
  }
  espPrintln("[OK] First image captured.");
  if (finger.image2Tz(1) != FINGERPRINT_OK) {
    espPrintln("[ERROR] Could not process first image."); return;
  }

  espPrintln("=> Remove finger.");
  delay(1500);
  while (finger.getImage() != FINGERPRINT_NOFINGER) { webServer.handleClient(); delay(20); }

  espPrintln("=> Place SAME finger again...");
  timeout = millis() + 15000;
  while (finger.getImage() != FINGERPRINT_OK) {
    webServer.handleClient();
    delay(20);
    if (millis() > timeout) {
      espPrintln("[ENROLL] Timeout on second scan. Aborting.");
      return;
    }
  }
  espPrintln("[OK] Second image captured.");
  if (finger.image2Tz(2) != FINGERPRINT_OK) {
    espPrintln("[ERROR] Could not process second image."); return;
  }

  if (finger.createModel() != FINGERPRINT_OK) {
    espPrintln("[ERROR] Fingerprints did not match!"); return;
  }

  if (finger.storeModel(slotId) == FINGERPRINT_OK) {
    espPrintln("[SUCCESS] Fingerprint saved to Slot #" + String(slotId));

    if (studentNo != "" && isOnline && WiFi.status() == WL_CONNECTED) {
      WiFiClient client;
      HTTPClient http;
      http.setTimeout(6000);
      http.begin(client, studentApiURL);
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");
      String post = "action=link_student&student_no=" + studentNo +
                    "&finger_id=" + String(slotId) + "&hardware_override=1";
      int code = http.POST(post);
      if (code == 200) {
        String res = http.getString(); res.trim();
        espPrintln("[DB] Link result: " + res);
      } else {
        espPrintln("[DB] Server error HTTP " + String(code) + ". Link MANUALLY on dashboard.");
      }
      http.end();
    } else if (studentNo != "") {
      espPrintln("[OFFLINE] No server. Link student manually on dashboard when online.");
    }
  } else {
    espPrintln("[ERROR] FM10A failed to save model to flash.");
  }
}

// ============================================================
// SCAN: Attendance detection loop (called from loop())
// ============================================================
void scanForAttendance() {
  String ts = getTimestamp();

#ifdef USE_RFID
  // --- 1. Check RFID (only if enabled) ---
  if (enableRfid && mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {
    String rfidUid = "";
    for (byte i = 0; i < mfrc522.uid.size; i++) {
      rfidUid += String(mfrc522.uid.uidByte[i] < 0x10 ? "0" : "");
      rfidUid += String(mfrc522.uid.uidByte[i], HEX);
    }
    rfidUid.toUpperCase();
    
    espPrintln("\n" + ts + " [SCAN] RFID Card Detected! UID: " + rfidUid);
    espPrintln(isOnline ? "[STATUS] ONLINE — Posting to server." : "[STATUS] OFFLINE — Saving to queue.");
    markAttendance(rfidUid, "rfid", ts);
    
    mfrc522.PICC_HaltA();
    delay(2000);
    printMenu();
    return;
  }
#endif

#ifdef USE_FINGERPRINT
  // --- 2. Check Fingerprint (only if enabled) ---
  if (!enableFingerprint) return;
  
  if (finger.getImage() != FINGERPRINT_OK) return;

  espPrintln("\n" + ts + " [SCAN] Finger detected! Analyzing...");

  if (finger.image2Tz() != FINGERPRINT_OK) {
    espPrintln("[ERROR] Poor image quality. Try again.");
    delay(1500);
    return;
  }

  if (finger.fingerSearch() == FINGERPRINT_OK) {
    espPrint("[MATCH] Slot #");  espPrint(String(finger.fingerID));
    espPrint(" | Confidence: "); espPrintln(String(finger.confidence));
    espPrintln(isOnline ? "[STATUS] ONLINE — Posting to server." : "[STATUS] OFFLINE — Saving to queue.");
    markAttendance(String(finger.fingerID), "fingerprint", ts);
    delay(2000);
    printMenu();
  } else {
    espPrintln("[REJECT] Unknown fingerprint. Access Denied.");
    delay(2000);
  }
#endif
}

// ============================================================
// UI: Print the serial console menu + update LCD
// ============================================================
void printMenu() {
  int qCount = offlineQueueCount();
  espPrintln("\n-------------------------------------------");
  espPrint("MODE: ");   espPrintln(currentMode);
  espPrint("STATUS: "); espPrintln(isOnline ? "ONLINE" : "OFFLINE");
  espPrint("COURSE: "); espPrintln(currentCourse);
  espPrint("SENSORS: ");
  String sensors = "";
  if (enableFingerprint) sensors += "FP ";
  if (enableRfid) sensors += "RFID ";
  if (enableFace) sensors += "FACE ";
  if (sensors == "") sensors = "NONE";
  espPrintln(sensors + (requireMfa ? " | MFA" : ""));
  if (qCount > 0) {
    espPrintln("QUEUE:  " + String(qCount) + " record(s) pending sync");
  }
  espPrintln(">> Type 'E' to Enroll a new student locally.");
  espPrintln(">> Type 'A' to switch to Attendance Mode.");
  if (currentMode == "ATTENDANCE_MODE") {
    espPrintln(">> Waiting for finger scan...");
  }
  espPrintln("-------------------------------------------");

  String modeShort = (currentMode == "ATTENDANCE_MODE") ? "ATTENDANCE" : "ENROLL";
  String statusShort = isOnline ? "ONLINE" : "OFFLINE";
  String queueStr = (qCount > 0) ? "Queue:" + String(qCount) : "Queue: Clear";
  String courseShort = currentCourse.substring(0, 20);
  lcdClear();
  lcdWrite(0, "Sentinel Swarm AMS");
  lcdWrite(1, courseShort);
  lcdWrite(2, modeShort + " | " + statusShort);
  lcdWrite(3, queueStr);
}

// ============================================================
// OTA: Handle commands from the Web Dashboard
// ============================================================
void handleDashboardCommand() {
  webServer.sendHeader("Access-Control-Allow-Origin", "*");
  webServer.sendHeader("Access-Control-Allow-Methods", "GET, OPTIONS");
  webServer.sendHeader("Access-Control-Allow-Headers", "*");

  if (!webServer.hasArg("command")) {
    webServer.send(400, "application/json", "{\"error\":\"Missing command\"}");
    return;
  }

  String cmd = webServer.arg("command");
  espPrintln("\n[OTA] Received: " + cmd);

  // --- PING ---
  if (cmd == "PING") {
    String resp = "{\"status\":\"PONG\",\"mode\":\"" + currentMode +
                  "\",\"course\":\"" + currentCourse +
                  "\",\"online\":" + (isOnline ? "true" : "false") +
                  ",\"queue\":" + String(offlineQueueCount()) + "}";
    webServer.send(200, "application/json", resp);
    return;
  }

  // --- ATTENDANCE_MODE ---
  if (cmd == "ATTENDANCE_MODE") {
    currentMode = "ATTENDANCE_MODE";
    pendingEnrollSlot = -1;
    espPrintln("[OTA] Switched to ATTENDANCE_MODE");
    webServer.send(200, "text/plain", "ACK: Mode set to ATTENDANCE_MODE");
    printMenu();
    return;
  }

  // --- ENROLL_MODE ---
  if (cmd == "ENROLL_MODE") {
    currentMode = "ENROLL_MODE";
    espPrintln("[OTA] Switched to ENROLL_MODE (awaiting ENROLL_SLOT command)");
    webServer.send(200, "text/plain", "ACK: Mode set to ENROLL_MODE");
    printMenu();
    return;
  }

  // --- START_COURSE ---
  if (cmd.startsWith("START_COURSE ")) {
    currentCourse = cmd.substring(13);
    espPrintln("[UPDATE] Course: " + currentCourse);
    webServer.send(200, "text/plain", "ACK: Course set to " + currentCourse);
    printMenu();
    return;
  }

  // --- ENROLL <slot> ---
  if (cmd.startsWith("ENROLL ")) {
    String slotStr = cmd.substring(7); slotStr.trim();
    pendingEnrollSlot = slotStr.toInt();
    if (pendingEnrollSlot < 1 || pendingEnrollSlot > 127) {
      webServer.send(400, "text/plain", "ERR: Invalid slot (1-127)");
      pendingEnrollSlot = -1;
      return;
    }
    currentMode = "ENROLL_MODE";
    espPrintln("[UPDATE] Web enrollment queued for slot " + String(pendingEnrollSlot));
    webServer.send(200, "text/plain", "ACK: Enrollment queued for slot " + slotStr);
    return;
  }

  // --- SETWIFI <ssid>|<pass>[|<identity>][|<method>|<token>] ---
  if (cmd.startsWith("SETWIFI ")) {
    String params = cmd.substring(8);
    int p1 = params.indexOf('|');
    String newSsid = (p1 >= 0) ? params.substring(0, p1) : params;
    String rest    = (p1 >= 0) ? params.substring(p1 + 1) : "";
    int p2 = rest.indexOf('|');
    String newPass = (p2 >= 0) ? rest.substring(0, p2) : rest;
    String rest2 = (p2 >= 0) ? rest.substring(p2 + 1) : "";
    int p3 = rest2.indexOf('|');
    String identity = (p3 >= 0) ? rest2.substring(0, p3) : "";
    String encMethod = "";
    String token = "";
    if (p3 >= 0) {
      String after = rest2.substring(p3 + 1);
      int p4 = after.indexOf('|');
      if (p4 >= 0) {
        encMethod = after.substring(0, p4);
        token = after.substring(p4 + 1);
      } else {
        encMethod = after;
      }
    }
    
    espPrintln("[OTA] WiFi update -> SSID: " + newSsid + (identity != "" ? " (Enterprise: " + identity + ")" : ""));
    
    preferences.begin("wifi", false);
    preferences.putString("ssid", newSsid);
    preferences.putString("pass", newPass);
    if (identity != "") preferences.putString("identity", identity);
    else preferences.putString("identity", "");
    preferences.end();
    
    if (encMethod != "") {
      preferences.begin("security", false);
      preferences.putString("method", encMethod);
      if (token != "") preferences.putString("token", token);
      preferences.end();
      espPrintln("[OTA] Security -> Method: " + encMethod);
    }
    
    webServer.send(200, "text/plain", "ACK: WiFi saved. Rebooting...");
    delay(1000);
    ESP.restart();
    return;
  }

  // --- REBOOT ---
  if (cmd == "REBOOT") {
    webServer.send(200, "text/plain", "ACK: Rebooting now...");
    delay(500);
    ESP.restart();
    return;
  }

  // --- CLEARLOGS ---
  if (cmd == "CLEARLOGS") {
    if (LittleFS.exists(OFFLINE_LOG_FILE)) {
      LittleFS.remove(OFFLINE_LOG_FILE);
      espPrintln("[OTA] Offline queue cleared.");
      webServer.send(200, "text/plain", "ACK: Offline queue cleared.");
    } else {
      webServer.send(200, "text/plain", "ACK: Queue already empty.");
    }
    return;
  }

  // --- LISTFS ---
  if (cmd == "LISTFS") {
    String resp = "LittleFS Files:\n";
    File root = LittleFS.open("/");
    File file = root.openNextFile();
    int count = 0;
    while (file) {
      resp += "  " + String(file.name()) + " (" + String(file.size()) + " bytes)\n";
      file = root.openNextFile();
      count++;
    }
    if (count == 0) resp += "  (empty)\n";
    resp += "Total: " + String(count) + " file(s)";
    webServer.send(200, "text/plain", resp);
    return;
  }

  // --- DUMP_OFFLINE ---
  if (cmd == "DUMP_OFFLINE") {
    if (!LittleFS.exists(OFFLINE_LOG_FILE)) {
      webServer.send(200, "text/plain", "ACK: Offline queue is empty.");
      return;
    }
    File f = LittleFS.open(OFFLINE_LOG_FILE, FILE_READ);
    String resp = "Offline Queue (" + String(offlineQueueCount()) + " records):\n";
    while (f.available()) {
      resp += f.readStringUntil('\n') + "\n";
    }
    f.close();
    webServer.send(200, "text/plain", resp);
    return;
  }

  // --- SYNC_OFFLINE ---
  if (cmd == "SYNC_OFFLINE") {
    espPrintln("[OTA] Force sync triggered.");
    syncOfflineQueue();
    webServer.send(200, "text/plain", "ACK: Sync attempted. Check logs for results.");
    return;
  }

  // --- FORMAT_FS ---
  if (cmd == "FORMAT_FS") {
    espPrintln("[OTA] Formatting LittleFS...");
    LittleFS.format();
    webServer.send(200, "text/plain", "ACK: LittleFS formatted. Rebooting...");
    delay(500);
    ESP.restart();
    return;
  }

  // --- SYNC_TIME ---
  if (cmd == "SYNC_TIME") {
    if (rtcOK) {
      rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
      espPrintln("[OTA] RTC time synced from compile time.");
      webServer.send(200, "text/plain", "ACK: RTC synced to " + String(F(__DATE__)) + " " + String(F(__TIME__)));
    } else {
      webServer.send(200, "text/plain", "ERR: RTC not available.");
    }
    return;
  }

  // --- WIPE_FP_TEMPLATES ---
  if (cmd == "WIPE_FP_TEMPLATES") {
    espPrintln("[OTA] Wiping all fingerprint templates...");
    if (finger.emptyDatabase()) {
      espPrintln("[OTA] FP database wiped.");
      webServer.send(200, "text/plain", "ACK: All fingerprint templates wiped.");
    } else {
      webServer.send(200, "text/plain", "ERR: Failed to wipe FP database.");
    }
    return;
  }

  // --- DOWNLOAD_FP_TEMPLATES ---
  if (cmd == "DOWNLOAD_FP_TEMPLATES") {
    uint16_t count = finger.templateCount;
    espPrintln("[OTA] Template count: " + String(count));
    webServer.send(200, "text/plain", "ACK: " + String(count) + " templates stored on sensor.");
    return;
  }

  // --- UPLOAD_FP_TEMPLATES ---
  if (cmd == "UPLOAD_FP_TEMPLATES") {
    uint16_t count = finger.templateCount;
    espPrintln("[OTA] Ready to receive templates. Current: " + String(count));
    webServer.send(200, "text/plain", "ACK: ESP ready. " + String(count) + " templates currently stored.");
    return;
  }

  // --- SCANWIFI ---
  if (cmd == "SCANWIFI") {
    espPrintln("[OTA] Scanning Wi-Fi...");
    int n = WiFi.scanNetworks();
    String resp = "[";
    for (int i = 0; i < n; ++i) {
      if (i > 0) resp += ",";
      resp += "\"" + WiFi.SSID(i) + "\"";
    }
    resp += "]";
    webServer.send(200, "application/json", resp);
    return;
  }

  // --- SCANBT ---
  if (cmd == "SCANBT") {
    espPrintln("[OTA] Scanning BLE...");
    BLEScanResults* foundDevices = pBLEScan->start(5, false);
    String resp = "[";
    for (int i = 0; i < foundDevices->getCount(); i++) {
      if (i > 0) resp += ",";
      BLEAdvertisedDevice d = foundDevices->getDevice(i);
      String name = d.haveName() ? String(d.getName().c_str()) : "Unknown Device";
      resp += "{\"name\":\"" + name + "\", \"mac\":\"" + String(d.getAddress().toString().c_str()) + "\"}";
    }
    resp += "]";
    pBLEScan->clearResults();
    webServer.send(200, "application/json", resp);
    return;
  }

  // --- GETLCD ---
  if (cmd == "GETLCD") {
    String resp = lcdLine[0] + "|" + lcdLine[1] + "|" + lcdLine[2] + "|" + lcdLine[3];
    webServer.send(200, "text/plain", resp);
    return;
  }

  // --- KEYPAD <key> ---
  if (cmd.startsWith("KEYPAD ")) {
    pendingKeypadKey = cmd.substring(7);
    pendingKeypadKey.trim();
    espPrintln("[KEYPAD] Virtual key pressed: " + pendingKeypadKey);
    webServer.send(200, "text/plain", "ACK: KEYPAD " + pendingKeypadKey);
    return;
  }

  // --- LCD <row> <text> ---
  if (cmd.startsWith("LCD ")) {
    String args = cmd.substring(4);
    int spacePos = args.indexOf(' ');
    if (spacePos > 0) {
      int row = args.substring(0, spacePos).toInt();
      String text = args.substring(spacePos + 1);
      if (row >= 0 && row <= 3) {
        lcdWrite(row, text);
        webServer.send(200, "text/plain", "ACK: LCD row " + String(row) + " updated.");
      } else {
        webServer.send(400, "text/plain", "ERR: Row must be 0-3.");
      }
    } else {
      webServer.send(400, "text/plain", "ERR: Format: LCD <row 0-3> <text>");
    }
    return;
  }

  // --- GETSTATUS ---
  if (cmd == "GETSTATUS") {
    int rssi = WiFi.RSSI();
    uint32_t uptimeSec = millis() / 1000;
    int queue = offlineQueueCount();
    uint32_t fsTotal = LittleFS.totalBytes();
    uint32_t fsUsed  = LittleFS.usedBytes();
    
    String resp = "";
    resp += "STATUS: " + String(isOnline ? "ONLINE" : "OFFLINE") + "\n";
    resp += "MODE: " + currentMode + "\n";
    resp += "COURSE: " + currentCourse + "\n";
    resp += "SENSORS: " + (enableFingerprint ? "FP " : "") + (enableRfid ? "RFID " : "") + (enableFace ? "FACE " : "") + (requireMfa ? "| MFA" : "") + "\n";
    resp += "RSSI: " + String(rssi) + "\n";
    resp += "UPTIME: " + String(uptimeSec) + "\n";
    resp += "QUEUE: " + String(queue) + "\n";
    resp += "FS_USED: " + String(fsUsed) + "\n";
    resp += "FS_TOTAL: " + String(fsTotal) + "\n";
    resp += "HEAP: " + String(ESP.getFreeHeap()) + "\n";
    resp += "DEVICE: " + String(DEVICE_NAME) + "\n";
    resp += "IP: " + WiFi.localIP().toString() + "\n";
    webServer.send(200, "text/plain", resp);
    return;
  }

  // --- STATUS (JSON legacy) ---
  if (cmd == "STATUS") {
    int rssi = WiFi.RSSI();
    int battPin = 34;
    int rawAdc = analogRead(battPin);
    int battPct = (int)map(rawAdc, 0, 4095, 0, 100);
    if (battPct < 0) battPct = 0;
    if (battPct > 100) battPct = 100;
    
    String resp = "{\"STATUS\":\"" + String(isOnline ? "ONLINE" : "OFFLINE") +
                  "\",\"MODE\":\"" + currentMode +
                  "\",\"COURSE\":\"" + currentCourse +
                  "\",\"QUEUE\":" + String(offlineQueueCount()) +
                  ",\"DEVICE\":\"" + String(DEVICE_NAME) +
                  "\",\"RSSI\":" + String(rssi) +
                  ",\"UPTIME\":" + String(millis() / 1000) +
                  ",\"HEAP\":" + String(ESP.getFreeHeap()) +
                  ",\"FS_USED\":" + String(LittleFS.usedBytes()) +
                  ",\"FS_TOTAL\":" + String(LittleFS.totalBytes()) +
                  ",\"BATTERY\":\"" + String(battPct) + "%\"}";
    webServer.send(200, "application/json", resp);
    return;
  }

  // --- SETCOURSES ---
  if (cmd.startsWith("SETCOURSES ")) {
    String courses = cmd.substring(11);
    courses.trim();
    preferences.begin("courses", false);
    preferences.putString("list", courses);
    preferences.end();
    espPrintln("[OTA] Courses saved: " + courses);
    webServer.send(200, "text/plain", "ACK: Courses saved: " + courses);
    return;
  }

  // --- GETCONFIG: Return current device settings ---
  if (cmd == "GETCONFIG") {
    String resp = "ENABLE_FP=" + String(enableFingerprint ? "1" : "0") +
                  ",ENABLE_RFID=" + String(enableRfid ? "1" : "0") +
                  ",ENABLE_FACE=" + String(enableFace ? "1" : "0") +
                  ",MFA=" + String(requireMfa ? "1" : "0") +
                  ",ENROLL_COUNT=" + String(enrollCount) +
                  ",COURSE=" + currentCourse +
                  ",MODE=" + currentMode +
                  ",ONLINE=" + String(isOnline ? "1" : "0");
    webServer.send(200, "text/plain", resp);
    return;
  }

  // --- SETCONFIG: Update device settings (comma-separated key=val) ---
  if (cmd.startsWith("SETCONFIG ")) {
    String cfg = cmd.substring(10);
    cfg.trim();
    espPrintln("[OTA] Config received: " + cfg);
    
    preferences.begin("config", false);
    
    // Parse comma-separated key=value pairs
    int start = 0;
    while (start < (int)cfg.length()) {
      int comma = cfg.indexOf(',', start);
      if (comma < 0) comma = cfg.length();
      String pair = cfg.substring(start, comma);
      int eq = pair.indexOf('=');
      if (eq > 0) {
        String key = pair.substring(0, eq); key.trim();
        String val = pair.substring(eq + 1); val.trim();
        preferences.putString(key.c_str(), val);
        
        // Update runtime variables
        if (key == "ENABLE_FP") { enableFingerprint = (val == "1"); espPrintln("  FP -> " + String(enableFingerprint ? "ON" : "OFF")); }
        if (key == "ENABLE_RFID") { enableRfid = (val == "1"); espPrintln("  RFID -> " + String(enableRfid ? "ON" : "OFF")); }
        if (key == "ENABLE_FACE") { enableFace = (val == "1"); espPrintln("  Face -> " + String(enableFace ? "ON" : "OFF")); }
        if (key == "MFA") { requireMfa = (val == "1"); espPrintln("  MFA -> " + String(requireMfa ? "ON" : "OFF")); }
        if (key == "ENROLL_COUNT") { enrollCount = val.toInt(); espPrintln("  Enroll Count -> " + String(enrollCount)); }
      }
      start = comma + 1;
    }
    preferences.end();
    webServer.send(200, "text/plain", "ACK: Config applied.");
    return;
  }

  // --- GETSECURITY ---
  if (cmd == "GETSECURITY") {
    webServer.send(200, "text/plain", "plaintext|");
    return;
  }

  // --- SETSECURITY ---
  if (cmd.startsWith("SETSECURITY ")) {
    String params = cmd.substring(12);
    params.trim();
    int p = params.indexOf('|');
    String method = (p >= 0) ? params.substring(0, p) : params;
    String token  = (p >= 0) ? params.substring(p + 1) : "";
    espPrintln("[OTA] Security update -> Method: " + method);
    preferences.begin("security", false);
    preferences.putString("method", method);
    preferences.putString("token", token);
    preferences.end();
    webServer.send(200, "text/plain", "ACK: Security set to " + method);
    return;
  }

  // --- Unknown command ---
  webServer.send(200, "text/plain", "ACK: Unknown command ignored: " + cmd);
}

void handleOptions() {
  webServer.sendHeader("Access-Control-Allow-Origin", "*");
  webServer.sendHeader("Access-Control-Allow-Methods", "GET, OPTIONS");
  webServer.sendHeader("Access-Control-Allow-Headers", "*");
  webServer.send(204);
}

// ============================================================
// SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(800);
  bootMillis = millis();

  espPrintln("\n\n===========================================");
  espPrintln("  SENTINEL SWARM — BOOTING...             ");
  espPrintln("===========================================");

  // Power management
  pinMode(4, OUTPUT);
  digitalWrite(4, HIGH);
  pinMode(34, INPUT);

  // --- 1. LittleFS ---
  if (!LittleFS.begin(true)) {
    espPrintln("[LittleFS] ERROR: Could not mount. Offline queue unavailable.");
  } else {
    int q = offlineQueueCount();
    espPrintln("[LittleFS] OK. Offline queue: " + String(q) + " record(s) pending.");
  }

  // --- 2. RTC ---
  Wire.begin(21, 22);
  if (!rtc.begin()) {
    espPrintln("[RTC] ERROR: Could not find DS3231. Using millis() fallback.");
    rtcOK = false;
  } else {
    rtcOK = true;
    espPrintln("[RTC] OK.");
    if (rtc.lostPower()) {
      espPrintln("[RTC] Lost power — setting time from compile date.");
      rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
    }
    DateTime now = rtc.now();
    if (now.year() < 2020 || now.year() > 2099) {
      espPrintln("[RTC] WARNING: Date looks wrong (" + String(now.year()) + "). Adjusting from compile time.");
      rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
    }
  }

  // --- 3. Fingerprint Sensor (FM10A on Serial2: RX=16, TX=17) ---
  espPrintln("[SENSOR] Initializing FM10A...");
  mySerial.begin(57600, SERIAL_8N1, 16, 17);
  finger.begin(57600);
  delay(500);

  if (finger.verifyPassword()) {
    espPrintln("[SENSOR] Found at 57600 baud.");
  } else {
    espPrintln("[SENSOR] Not at 57600. Trying 9600...");
    mySerial.end();
    mySerial.begin(9600, SERIAL_8N1, 16, 17);
    finger.begin(9600);
    delay(500);
    if (finger.verifyPassword()) {
      espPrintln("[SENSOR] Found at 9600 baud.");
    } else {
      espPrintln("[SENSOR] FATAL: FM10A not responding at any baud rate!");
      espPrintln("         -> Check wiring: TX->RX2(16), RX->TX2(17), GND->GND, VCC->5V");
    }
  }

  // --- 3.5. RFID Sensor ---
  espPrintln("[SENSOR] Initializing MFRC522...");
  SPI.begin();
  mfrc522.PCD_Init();
  delay(10);
  mfrc522.PCD_DumpVersionToSerial();
  espPrintln("[SENSOR] RFID Ready.");

  // --- 3.7. LCD ---
  espPrintln("[LCD] Initializing I2C LCD (0x27, 20x4)...");
  lcd.init();
  lcd.backlight();
  lcdOK = true;
  lcdClear();
  lcdWrite(0, "Sentinel Swarm AMS");
  lcdWrite(1, "Booting...");
  lcdWrite(2, "Please wait...");
  lcdWrite(3, "");
  espPrintln("[LCD] OK.");

  // --- 3.8. BLE Setup ---
  espPrintln("[BLE] Initializing BLE Scanner...");
  BLEDevice::init(DEVICE_NAME);
  pBLEScan = BLEDevice::getScan();
  pBLEScan->setActiveScan(true);
  pBLEScan->setInterval(100);
  pBLEScan->setWindow(99);
  espPrintln("[BLE] Scanner Ready.");

  // --- 3.9. Load Device Config from Preferences ---
  loadDeviceConfig();

  // --- 4. Build URLs ---
  serverURL    = "http://" + String(SERVER_IP) + "/csc2052/attendance.php";
  heartbeatURL = "http://" + String(SERVER_IP) + "/csc2052/heartbeat.php";
  enrollApiURL = "http://" + String(SERVER_IP) + "/csc2052/api/student.php?action=get_next_slot&hardware_override=1";
  studentApiURL= "http://" + String(SERVER_IP) + "/csc2052/api/student.php";

  // --- 5. WiFi ---
  isOnline = tryConnectWifi();

  // --- 6. Web Server ---
  webServer.on("/cmd", HTTP_GET,     handleDashboardCommand);
  webServer.on("/cmd", HTTP_OPTIONS, handleOptions);
  webServer.on("/logs", HTTP_GET,    handleGetLogs);
  webServer.begin();
  espPrintln("[WEB] OTA server listening on port 80.");

  printMenu();
}

// ============================================================
// LOOP
// ============================================================
void loop() {
  webServer.handleClient();

  // --- WiFi watchdog ---
  if (WiFi.status() != WL_CONNECTED && millis() - lastWifiRetry >= WIFI_RETRY_MS) {
    espPrintln("\n[WIFI] Connection lost. Attempting reconnect...");
    isOnline = tryConnectWifi();
    lastWifiRetry = millis();
  }

  // --- Heartbeat ---
  if (millis() - lastHeartbeatTime >= HEARTBEAT_INTERVAL) {
    sendHeartbeat();
    lastHeartbeatTime = millis();
  }

  // --- Offline queue sync ---
  if (isOnline && millis() - lastSyncAttempt >= SYNC_INTERVAL) {
    syncOfflineQueue();
    lastSyncAttempt = millis();
  }

  // --- Serial Monitor commands ---
  if (Serial.available() > 0) {
    char cmd = Serial.read();
    if (cmd == '\n' || cmd == '\r' || cmd == ' ' || cmd == '.' || cmd == '\t') {
      // Silently discard
    } else if (cmd == 'E' || cmd == 'e') {
      currentMode = "ENROLL_MODE";
      espPrintln("\n[MODE] -> ENROLL_MODE");
      runEnrollment(-1);
      currentMode = "ATTENDANCE_MODE";
      printMenu();
    } else if (cmd == 'A' || cmd == 'a') {
      currentMode = "ATTENDANCE_MODE";
      espPrintln("\n[MODE] -> ATTENDANCE_MODE");
      printMenu();
    } else if (cmd == 'S' || cmd == 's') {
      espPrintln("\n[SYNC] Manual sync triggered...");
      syncOfflineQueue();
    } else {
      // Unknown key — ignore
    }
  }

  // --- Handle queued OTA enrollment ---
  if (currentMode == "ENROLL_MODE" && pendingEnrollSlot != -1) {
    runEnrollment(pendingEnrollSlot);
    pendingEnrollSlot = -1;
    currentMode = "ATTENDANCE_MODE";
    printMenu();
  }

  // --- Process virtual keypad key ---
  if (pendingKeypadKey.length() > 0) {
    String key = pendingKeypadKey;
    pendingKeypadKey = "";

    espPrintln("[KEYPAD] Processing key: " + key);
    lcdClear();

    if (key == "A") {
      printMenu();
    } else if (key == "B") {
      lcdWrite(0, "[B] Nav Up");
      lcdWrite(1, "Mode: " + currentMode);
    } else if (key == "C") {
      lcdWrite(0, "[C] Nav Down");
      lcdWrite(1, "Course: " + currentCourse);
    } else if (key == "D") {
      lcdWrite(0, "[D] Confirm");
      lcdWrite(1, currentCourse);
    } else if (key == "*") {
      lcdWrite(0, "[*] Cleared");
    } else if (key == "#") {
      espPrintln("[KEYPAD] # pressed — forcing offline sync.");
      syncOfflineQueue();
      lcdWrite(0, "[#] Force Sync");
      lcdWrite(1, isOnline ? "Syncing..." : "OFFLINE!");
    } else {
      lcdWrite(0, "Key: " + key);
      lcdWrite(1, currentCourse);
    }
  }

  // --- Attendance scanning ---
  if (currentMode == "ATTENDANCE_MODE") {
    scanForAttendance();
  }
}
