## 2025-05-14 - HMAC Hardware Authentication
Implemented 'verifyHardwareSignature()' in 'includes/helpers.php' using HMAC-SHA256 to authenticate hardware node requests. Integrated this check into 'attendance.php' and 'heartbeat.php' API endpoints, ensuring all hardware communication is signed while maintaining backward compatibility for legacy tokens.
