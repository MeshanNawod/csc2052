-- ============================================================
-- Multi-Role System Migration — Sentinel Swarm AMS v3
-- Adds: users, teachers, teacher_courses tables
-- Modifies: students (add must_change_password)
-- ============================================================

-- 1. Users table (unified login for admin, teacher, student)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
    email VARCHAR(100),
    full_name VARCHAR(100),
    must_change_password TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Teachers table (extends users with teacher-specific info)
CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    teacher_name VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY idx_user (user_id),
    INDEX idx_name (teacher_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Teacher-Course junction (many-to-many: one course has many teachers, one teacher teaches many courses)
CREATE TABLE IF NOT EXISTS teacher_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    course_code VARCHAR(50) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_teacher_course (teacher_id, course_code),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (course_code) REFERENCES courses(course_code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Add must_change_password column to students (for student login)
ALTER TABLE students ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER student_name;
ALTER TABLE students ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) DEFAULT 0 AFTER user_id;
ALTER TABLE students ADD INDEX IF NOT EXISTS idx_user_id (user_id);
ALTER TABLE students ADD FOREIGN KEY IF NOT EXISTS fk_student_user (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- 5. Seed default admin user (migrates from config.php hardcoded creds)
-- Default: admin / admin123
INSERT IGNORE INTO users (username, password_hash, role, full_name, must_change_password)
VALUES (
    'admin',
    '$2y$10$twpDafck36dbydSHQ1bP8.1wFdj/GyONHglNcccYWcFeVWn2C1vN.',
    'admin',
    'System Administrator',
    0
);

-- ============================================================
-- USAGE INSTRUCTIONS:
-- 1. Run this SQL in phpMyAdmin or MySQL CLI:
--    C:\xampp\mysql\bin\mysql.exe -u root csc2052 < sql/multi_role_migration.sql
-- 2. Create teacher accounts via admin panel (students.php → Course Manager)
-- 3. Students auto-created in users table when they first log in
--    (or admin can pre-create them)
-- ============================================================
