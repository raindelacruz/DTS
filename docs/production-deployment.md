# Production deployment requirements

## Web server boundary

Set the virtual host `DocumentRoot` to the repository's `public` directory. Never expose the repository root. The root and public `.htaccess` files provide defense in depth, but they do not replace a correct virtual-host boundary.

Do not deploy `dts_db.sql`, development logs, or local backup files inside the web root.

## Required environment

Set these values in the service or Apache virtual-host environment:

- `APP_ENV=production`
- `APP_URL=https://canonical.example.gov.ph`
- `APP_KEY` to a randomly generated secret of at least 32 bytes; keep it stable because it encrypts MFA secrets
- `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME`
- `TRUSTED_PROXIES` to a comma-separated allowlist of reverse-proxy IP addresses, or leave empty when there is no proxy
- `REQUIRE_MALWARE_SCAN=1`
- `MALWARE_SCAN_COMMAND` to the approved scanner command, for example `clamdscan --no-summary --fdpass`
- `PASSWORD_RESET_FROM` to the approved sender address
- `UPLOAD_STORAGE_QUOTA_MB` to the attachment storage allocation

Configure PHP email delivery so `mail()` sends through the organization's approved relay. Password-reset responses are intentionally generic and tokens expire after 30 minutes.

Install and enable PHP `fileinfo`, `openssl`, `PDO MySQL`, and `zip`. The ZIP extension is required to validate DOCX and XLSX containers.

## Release procedure

1. Back up and restore-test the database.
2. Run `composer install --no-dev --classmap-authoritative`.
3. Run `npm ci --omit=dev` when rebuilding PDF.js assets.
4. Run `php database/migrate.php` with the production environment loaded.
5. For a new installation only, create the first administrator with `php database/bootstrap_admin.php` and the documented options. The command refuses to run when an administrator already exists.
6. Confirm HTTPS, secure session cookies, HSTS, CSP, login throttling, administrator MFA, password-reset delivery, malware scanning, attachment access, and backup monitoring.

## Security operating procedures

- Verify identity through an approved offline channel before an administrator resets another administrator's MFA.
- Review `security_audit_log` from a read-only reporting account. Application code does not update or delete audit records, and database triggers reject either operation.
- Rotate `APP_KEY` only through a planned MFA re-enrollment migration.
- Keep the malware engine and signatures current and monitor scan failures.
- Monitor attachment volume against `UPLOAD_STORAGE_QUOTA_MB` and free-disk alerts.
