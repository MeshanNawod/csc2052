## 2026-06-09 - Hardening Hardware API with HMAC-SHA256
**Learning:** Hardware API endpoints were relying on static tokens or keys, and plaintext mode was potentially unauthenticated. Centralizing signature verification into a helper makes it easier to enforce security.
**Action:** Implemented verifyHardwareSignature() in includes/helpers.php and updated attendance.php and heartbeat.php to enforce either HMAC signature or legacy authenticated token/key.
