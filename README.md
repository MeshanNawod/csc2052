# Sentinel Swarm V3: Multi-Modal Biometric Attendance Management System

An integrated, multi-modal biometric attendance and access control system developed for the **CSC2052** project. The system combines edge hardware nodes (ESP32 microcontrollers and Raspberry Pi face recognition modules) with a centralized PHP web dashboard and MySQL database backend to automate real-time attendance tracking.

---

## 🚀 Key Features

* **Multi-Modal Verification:** Supports both fingerprint verification (via microcontroller-integrated biometric sensors) and face recognition.
* **Edge Processing & Microcontroller Integration:** Utilizes ESP32 expansion boards to handle local sensor polling, card/fingerprint reading, and real-time network communication.
* **Centralized Web Dashboard:** A robust PHP-backed management interface for viewing live attendance logs, managing user profiles, and generating reports.
* **Secure Database Schema:** Normalized MySQL architecture designed to securely store user credentials, device states, and attendance logs.

---

## 🛠️ Tech Stack & Hardware Components

### **Software & Backend**
* **Backend:** PHP, MySQL
* **Microcontroller Firmware:** C / C++ (Arduino IDE / ESP-IDF)
* **Computer Vision / Edge Node:** Python (OpenCV / face recognition libraries on Raspberry Pi)

### **Hardware Components**
* ESP32 30-pin Development Board / Expansion Adapter
* FM10A Fingerprint Sensor / RC522 RFID Reader
* Raspberry Pi (for camera-based facial recognition nodes)
* DS3231 Real-Time Clock (RTC) & I2C LCD Display
* XL6009 Step-Down DC-DC Converters & MOSFET Power Switching Modules

---

## 📂 Repository Structure

```text
├── firmware/            # ESP32 and sensor integration code (C/C++)
├── raspberry-pi/        # Python scripts for face recognition and camera nodes
├── web-dashboard/       # PHP source files, API endpoints, and frontend assets
├── database/            # MySQL schema definitions and migration scripts
└── docs/                # Circuit diagrams, pinout configurations, and project reports

⚙️ Getting Started
1. Database Setup
 * Import the SQL schema located in the database/ directory into your MySQL server:
   mysql -u root -p csc2052_attendance < database/schema.sql

 * Configure your database credentials inside the PHP configuration file (web-dashboard/config.php).
2. Running the Web Dashboard
 * Host the web-dashboard/ directory using a local server stack (e.g., Apache/Nginx with PHP).
 * Access the dashboard via your browser to monitor real-time logs and manage enrolled users.
3. Flashing Firmware (ESP32)
 * Open the firmware/ project in the Arduino IDE or PlatformIO.
 * Update your Wi-Fi credentials and the backend API endpoint URL in the configuration header.
 * Select your board (ESP32 Dev Module) and flash the code.
👥 Author
 * Meshan Nawod (S/22/314)
   Faculty of Science, University of Peradeniya
📄 License
This project is developed for academic purposes under the Department of Computer Science, University of Peradeniya.

