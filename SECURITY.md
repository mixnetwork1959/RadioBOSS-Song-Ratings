# Security policy

Please do not publish security vulnerabilities in a public issue.

Include the plugin version, WordPress/PHP versions, reproduction steps, and the expected impact in a private report to the repository owner.

## Security design

- Public REST input is sanitized and validated.
- Voting is rate-limited.
- Visitor identifiers are salted and hashed before storage.
- WordPress administrator capability checks protect settings and reports.
- Setup forms use WordPress nonces.
- Remote metadata requests use wp_safe_remote_get.
- Deactivation and uninstall do not automatically delete ratings.

