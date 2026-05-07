            $sql = "SELECT a.student_no, a.timestamp, a.course_code, a.modality, a.device_name, s.student_name
                    FROM attendance_logs a
                    LEFT JOIN students s ON a.student_no = s.student_no
                    WHERE $where ORDER BY a.timestamp DESC LIMIT 100";
