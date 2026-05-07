-- Create course_schedules table if it doesn't exist
CREATE TABLE IF NOT EXISTS course_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(50) NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '1=Monday, 2=Tuesday, ..., 6=Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(100) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slot (day_of_week, start_time, end_time),
    INDEX idx_day (day_of_week),
    INDEX idx_course (course_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
