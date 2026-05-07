-- Add device_id column to course_schedules table for auto-start device selection
ALTER TABLE course_schedules ADD COLUMN IF NOT EXISTS device_id VARCHAR(50) DEFAULT 'WEB_DASHBOARD' COMMENT 'Device to auto-start this schedule on';
ALTER TABLE course_schedules ADD COLUMN IF NOT EXISTS auto_start TINYINT(1) DEFAULT 1 COMMENT 'Whether to auto-start this schedule';
ALTER TABLE course_schedules ADD COLUMN IF NOT EXISTS email_threshold TINYINT DEFAULT 80 COMMENT 'Attendance percentage threshold for auto-email alerts';
ALTER TABLE course_schedules ADD COLUMN IF NOT EXISTS email_on_end TINYINT(1) DEFAULT 1 COMMENT 'Send absent report email when schedule ends';
