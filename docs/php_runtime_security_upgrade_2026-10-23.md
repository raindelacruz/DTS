# PHP runtime security upgrade — October 23, 2026

Status: completed and verified at approximately 07:20 UTC+08:00.

Apache's live PHP runtime and the CLI at `C:\xampp\php\php.exe` were upgraded from PHP 8.2.12 to PHP 8.2.35, x64, thread-safe, VS16. The PHP 8.2 branch was retained to minimize application compatibility changes.

## Verified package

- Official package: https://downloads.php.net/~windows/releases/archives/php-8.2.35-Win32-vs16-x64.zip
- SHA-256: `cfa6000be835931961e40740bd3b51111700061d6655ddbfe5a2125807947457`
- Advisory: https://www.php.net/archive/2026.php#2026-10-21-5
- Covers CVE-2026-109618 and CVE-2026-109619.

The downloaded archive matched PHP's published checksum before extraction. No exploit requests were used for validation.

## Server changes

- Installed the official patched runtime at the existing `C:\xampp\php` path.
- Preserved the existing php.ini, PEAR support files, browscap.ini, upload limits, temporary directory and session directory.
- Added an explicit `LoadFile "C:/xampp/php/libssh2.dll"` before the PHP module in `C:\xampp\apache\conf\extra\httpd-xampp.conf`. This ensures PHP's newer cURL extension uses the required dependency instead of Apache's older bundled libssh2.
- Apache's bundled DLLs were not replaced. Apache and MariaDB versions were not upgraded.
- Fully stopped and started the Apache2.4 service. Post-switch web verification identified PHP 8.2.35 with SAPI `apache2handler`; all previously loaded PHP extensions remain available.
- No application business logic, database schema or business records were changed for this upgrade.

## Validation

- Patched-runtime application suite: 42 passed, 0 failed, 5 skipped. Skipped checks require isolated security databases; they were not run against production.
- Actual Apache module checks passed in loopback staging and after installation.
- Live login page returned HTTP 200 and rendered its CSRF field. No live account login or MFA enrollment was performed; authentication/MFA helper tests passed in the suite.
- Actual Apache read-only database check passed (`SELECT 1`, read-only transaction).
- PDF generation using the installed application dependency passed.
- Actual Apache multipart upload received exactly the submitted PDF bytes, verified by SHA-256. PHP managed its temporary upload; no application attachment/document was created.
- Existing isolated HTTP upload/save/history-link/download test passed using the installed PHP executable.
- Effective web upload limits remain 25M per file and 26M per POST. Session and upload temporary directories remain `C:\xampp\tmp`.
- Temporary loopback-only verification endpoint was removed after checks.

## Rollback and deployment record

- The original runtime is preserved at `C:\xampp\php.pre-security-20261023`.
- Original php.ini and Apache PHP configuration, checksum-verified archive, and before/after web verification records are under `storage/maintenance/php-security-20261023/`, excluded from version control.
- Deployment script: `scripts/upgrade_php_runtime_20261023.ps1`. It refuses to overwrite an existing backup, so it is not a routine restart command.
- The first installation attempt rolled back automatically after the service reported a missing legacy browscap.ini file. Availability was verified after rollback. Staging was corrected to contain the required support file and to use staged paths for all PHP support settings. The second installation and verification succeeded.
- The incomplete first candidate remains at `C:\xampp\php.failed-security-20261023` for deployment diagnostics; it is not the active runtime.

Reverting to the old runtime would reintroduce the vulnerabilities and should be an emergency availability action only. A manual rollback would require stopping Apache, retaining the patched directory, restoring the original directory and Apache configuration, then restarting and verifying the service.

## Scope limitations

This runtime patch is not a forensic compromise assessment. Historical data was not altered or declared uncompromised. Existing origin-server TLS certificate hostname warnings were observed before and after the upgrade; certificate configuration was unchanged. PHP 8.2 reaches upstream end of support on December 31, 2026, so a separately tested supported-branch upgrade remains future work.
