-- Sentinel Swarm AMS v3 — Master Database Schema
-- Focus: Security, Stability, and System Architecture

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users Table (Unified Authentication)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
    `email` VARCHAR(100),
    `full_name` VARCHAR(100),
    `must_change_password` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Courses Table
CREATE TABLE IF NOT EXISTS `courses` (
    `course_code` VARCHAR(50) NOT NULL PRIMARY KEY,
    `course_name` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Students Table
CREATE TABLE IF NOT EXISTS `students` (
    `student_no` VARCHAR(50) NOT NULL PRIMARY KEY,
    `student_name` VARCHAR(100),
    `user_id` INT NULL,
    `fingerprint_id` INT NULL,
    `rfid_uid` VARCHAR(50) NULL,
    `face_id` VARCHAR(50) NULL,
    `face_descriptor` TEXT NULL,
    `must_change_password` TINYINT(1) DEFAULT 0,
    `profile_photo` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_fingerprint` (`fingerprint_id`),
    INDEX `idx_rfid` (`rfid_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Teachers Table
CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `teacher_name` VARCHAR(100) NOT NULL,
    `department` VARCHAR(100),
    `phone` VARCHAR(20),
    `profile_photo` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Teacher-Course Junction
CREATE TABLE IF NOT EXISTS `teacher_courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `course_code` VARCHAR(50) NOT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_teacher_course` (`teacher_id`, `course_code`),
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_code`) REFERENCES `courses`(`course_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Course Schedules Table
CREATE TABLE IF NOT EXISTS `course_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(50) NOT NULL,
    `day_of_week` TINYINT NOT NULL COMMENT '1=Mon, ..., 6=Sat, 7=Sun',
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `venue` VARCHAR(100) DEFAULT '',
    `device_id` VARCHAR(50) DEFAULT 'WEB_DASHBOARD',
    `auto_start` TINYINT(1) DEFAULT 1,
    `email_threshold` TINYINT DEFAULT 80,
    `email_on_end` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_code`) REFERENCES `courses`(`course_code`) ON DELETE CASCADE,
    INDEX `idx_day_time` (`day_of_week`, `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Attendance Logs Table
CREATE TABLE IF NOT EXISTS `attendance_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_no` VARCHAR(50) NOT NULL,
    `course_code` VARCHAR(50),
    `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `device_name` VARCHAR(100),
    `modality` VARCHAR(50),
    `is_offline_sync` TINYINT(1) DEFAULT 0,
    `ip_address` VARCHAR(45),
    INDEX `idx_student_no` (`student_no`),
    INDEX `idx_course_date` (`course_code`, `timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Device Settings Table
CREATE TABLE IF NOT EXISTS `device_settings` (
    `device_id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `fp_power` TINYINT(1) DEFAULT 1,
    `display_power` TINYINT(1) DEFAULT 1,
    `backlight_power` TINYINT(1) DEFAULT 1,
    `bluetooth_on` TINYINT(1) DEFAULT 0,
    `enable_fingerprint` TINYINT(1) DEFAULT 1,
    `enable_rfid` TINYINT(1) DEFAULT 1,
    `enable_face` TINYINT(1) DEFAULT 1,
    `require_multi_factor` TINYINT(1) DEFAULT 0,
    `enroll_fingers` TINYINT DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Admins Table (Hardware Access)
CREATE TABLE IF NOT EXISTS `admins` (
    `admin_name` VARCHAR(100) NOT NULL PRIMARY KEY,
    `fingerprint_id` INT NULL,
    `rfid_uid` VARCHAR(50) NULL,
    `face_id` VARCHAR(50) NULL,
    `face_descriptor` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Email Sent Logs Table
CREATE TABLE IF NOT EXISTS `email_sent_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sender_email` VARCHAR(100),
    `recipient_email` VARCHAR(100),
    `subject` VARCHAR(255),
    `message_type` VARCHAR(50),
    `course_code` VARCHAR(50),
    `student_no` VARCHAR(50),
    `student_name` VARCHAR(100),
    `body` TEXT,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Active Courses (Live Sessions)
CREATE TABLE IF NOT EXISTS `active_courses` (
    `course_code` VARCHAR(50) NOT NULL PRIMARY KEY,
    `teacher_id` INT,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `device_name` VARCHAR(50),
    `timer_minutes` INT,
    FOREIGN KEY (`course_code`) REFERENCES `courses`(`course_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Admin
INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `full_name`, `must_change_password`)
VALUES ('admin', '$2y$10$twpDafck36dbydSHQ1bP8.1wFdj/GyONHglNcccYWcFeVWn2C1vN.', 'admin', 'System Administrator', 0);

-- Seed Default Device Settings
INSERT IGNORE INTO `device_settings` (`device_id`) VALUES ('DEFAULT');

SET FOREIGN_KEY_CHECKS = 1;
