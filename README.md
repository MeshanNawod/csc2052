# Sentinel Swarm AMS v3

A full-stack, multi-modal Attendance Management System that fuses IoT hardware (ESP32 + Raspberry Pi) with a PHP/React web dashboard.

## Overview

Sentinel Swarm replaces manual roll-call with autonomous hardware nodes that detect students and record attendance in real time using face recognition, RFID, QR codes, and fingerprints. All devices report back to a central PHP server that manages courses, schedules, and generates reports.

## Features

- **Multi-Modal Capture:** Fingerprint (FM10A), RFID (MFRC522), Face Recognition (RPI/Webcam), and QR Codes.
- **Secure Integration:** HMAC-SHA256 request signing between hardware and backend.
- **Mesh Networking:** ESP-NOW support for relaying data from offline nodes to gateways.
- **Analytics Dashboard:** Real-time monitoring, attendance stats, and automated email alerts.
- **Remote Management:** OTA firmware updates and remote device configuration.

## Directory Structure

- `api/`: PHP REST API endpoints.
- `css/`: Application styles.
- `js/`: Dashboard and face recognition logic.
- `includes/`: Core logic, database connection, and helpers.
- `esp32_firmware/`: Arduino sketches for hardware nodes.
- `rpi_flask/`: Flask-based face recognition service for Raspberry Pi.
- `sql/`: Database schema and migrations.
- `uploads/`: Biometric and profile image storage.

## Setup

1. **Database:** Import `sql/schema.sql` into your MySQL/MariaDB server.
2. **Configuration:** Copy `includes/config.example.php` to `includes/config.php` and update your database credentials.
3. **Web Server:** Point your web server document root to the project directory.
4. **Hardware:** Flash the ESP32 using the source in `esp32_firmware/` and configure the Pi service in `rpi_flask/`.

## License

This project is developed for academic purposes at University of Peradeniya.
