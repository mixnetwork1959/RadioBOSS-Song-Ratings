# Security policy

Please do not publish security vulnerabilities in a public issue.

Include the edition and version, WordPress/PHP versions where applicable, reproduction steps, and the expected impact in a private report to the repository owner.

## Security design

- Public REST input is sanitized and validated.
- Voting is rate-limited.
- Visitor identifiers are salted and hashed before storage.
- WordPress administrator capability checks protect settings and reports.
- Setup forms use WordPress nonces.
- Remote metadata requests use wp_safe_remote_get.
- Deactivation and uninstall do not automatically delete ratings.

## Standalone security design

- Database queries use PDO prepared statements.
- The setup wizard generates a random hashing secret and locks itself after installation.
- Database credentials are stored in a protected PHP configuration file and are never sent to the browser.
- The administrator password is stored with PHP's password hashing API.
- Public votes and administrator login attempts are rate-limited without storing raw IP addresses in MySQL.
- Apache access rules protect configuration and runtime storage. Nginx installations must deny `/config/` and `/storage/` explicitly.
