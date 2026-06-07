# Sentinel Journal

## 2025-05-14 - System Reconstruction and Security Hardening
Reconstructed the Sentinel Swarm AMS v3 repository from a broken state. Key accomplishments include:
- Restored missing directories (rpi_flask, uploads, logs) and API endpoints.
- Implemented HMAC-SHA256 signature verification for hardware nodes in includes/helpers.php.
- Normalized all application paths to be root-relative, removing hardcoded /csc2052/ prefixes.
- Secured all hardware-facing API endpoints (attendance, heartbeat, qr, voice, logs) with signature verification.
- Established a robust .gitignore and config.example.php to prevent secret leakage.
- Restored a standard PDO database connection logic in includes/db.php.
- Enhanced UI accessibility and added visual security indicators to the dashboard.
