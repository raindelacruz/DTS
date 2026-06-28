# DTS Operational Readiness

This document turns the P2 launch items into repeatable operating procedures. It should be reviewed before every production release and after every incident.

## Automated checks

Run these locally before release:

```powershell
composer install --no-interaction --prefer-dist
composer validate --strict
Get-ChildItem app,helpers,public,database,tests,scripts -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
composer test
php database/migrate.php
composer test:db
composer audit --locked --no-interaction
npm audit --omit=dev
php scripts/reconcile.php
php scripts/check_upload_storage.php --incoming-mb=25
```

The GitHub Actions workflow in `.github/workflows/ci.yml` runs PHP linting, migrations against MySQL, unit tests, DB integration tests, Composer audit, and NPM audit.

## Staging parity

Staging must match production for:

- PHP major/minor version and required extensions: `pdo_mysql`, `fileinfo`, `openssl`, `zip`.
- MySQL/MariaDB version, SQL mode, character set, and collation.
- Web server type, document root, rewrite configuration, upload/post limits, and timeout limits.
- `APP_URL`, `APP_SUBDIR`, trusted proxy settings, HTTPS termination, and cookie behavior.
- Malware scanner command and maximum upload size.
- Path casing and filesystem permissions for `storage/logs` and `storage/uploads`.

Do not approve a release if staging uses a different public path, proxy behavior, upload limit, or PHP extension set from production.

## Health checks and monitoring

Use `public/health.php` as the load balancer or uptime-monitor health endpoint.

The endpoint reports:

- database connectivity;
- migration table presence;
- log directory writability;
- upload directory writability;
- free disk capacity;
- malware-scan configuration.

Recommended alert thresholds:

- health endpoint returns HTTP 503 for 2 consecutive checks;
- disk free space below 20%;
- upload storage quota above 80%;
- database CPU, memory, or connection count sustained above 80%;
- no successful backup in 24 hours;
- reconciliation report returns new orphaned or duplicate records;
- error logs grow unusually fast or contain repeated fatal errors.

Log rotation should keep application logs for at least 90 days online unless records-management policy requires longer retention. Centralized logging should ingest Apache/Nginx, PHP, MySQL, and `storage/logs/app.log`.

## Backup, retention, and restore testing

Minimum backup standard:

- full database backup at least daily;
- transaction-log/binlog or equivalent point-in-time recovery if the business RPO is less than 24 hours;
- encrypted off-site copy;
- least-privileged backup account;
- restore test at least monthly;
- documented RPO and RTO approved by the business owner.

Example backup:

```powershell
$env:DB_HOST="localhost"
$env:DB_USER="backup_user"
$env:DB_PASS="..."
$env:DB_NAME="dts_db"
.\scripts\backup_database.ps1 -OutputDirectory D:\dts-backups
```

Restore tests must be performed into a non-production database first:

```powershell
$env:DB_NAME="dts_restore_test"
.\scripts\restore_database.ps1 -BackupSqlPath D:\dts-backups\dts_db-YYYYMMDD-HHMMSS.sql
php database/migrate.php
php scripts/reconcile.php
```

## Release checklist

Before release:

- CI is green on the release branch.
- Staging migration has been run from the exact release artifact.
- Staging smoke test covers login, MFA for admins, password reset, document create/release/receive/return, action-slip create/receive/forward/complete, attachment upload/download, and verification link.
- `php scripts/reconcile.php` has no unexpected duplicate, orphaned, overdue, or stuck records.
- A fresh production backup has completed and restore instructions are available.
- Rollback owner, decision window, and database rollback strategy are named.
- Incident contacts and emergency administrator process are confirmed.

During release:

- Put the system in a maintenance window if database migrations are not fully online-safe.
- Run `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`.
- Run `php database/migrate.php`.
- Clear PHP/opcode caches if enabled.
- Verify `public/health.php`.
- Run one production smoke test with a non-sensitive test record.

After release:

- Monitor health checks, logs, DB metrics, disk, and upload quota for at least one business cycle.
- Run `php scripts/reconcile.php` and compare against pre-release output.
- Record release result, migration output, and any follow-up actions.

## Rollback procedure

Rollback is a business decision as much as a technical step. Use it when production data integrity, authentication, document routing, or upload/download behavior is impaired.

1. Stop new writes if the defect can corrupt workflow state.
2. Preserve logs and the failed release artifact.
3. Decide whether code-only rollback is safe. If migrations changed data destructively, restore to a tested backup or perform a forward corrective migration.
4. Redeploy the last known-good code artifact.
5. Run `public/health.php`, smoke tests, and reconciliation.
6. Document timeline, impact, and corrective actions.

## Incident runbook

Severity 1 examples: outage, data loss, unauthorized access, malware upload bypass, broken authentication, or widespread workflow corruption.

Immediate response:

- Assign incident commander, technical lead, communications owner, and scribe.
- Capture start time, current release, affected users, and visible symptoms.
- Preserve logs and database state before destructive remediation.
- If security-related, rotate affected credentials and revoke sessions where appropriate.
- If workflow-related, run reconciliation and export affected records.

Recovery:

- Prefer forward fixes for small, well-understood data issues.
- Prefer restore plus replay only when corruption scope is broad and backup freshness satisfies RPO.
- Confirm recovery with health checks, smoke tests, and user-owner signoff.

Post-incident:

- Publish root cause, user impact, detection gap, prevention work, and owner/date for each action item.

## Ownership model

Define named owners before launch:

- user provisioning and deactivation;
- department transfers;
- role changes and administrator MFA reset;
- orphaned assignments;
- document correction and re-release;
- action-slip correction and closure;
- emergency administrator access;
- backup/restore testing;
- security audit review;
- records retention and deletion approval.

No production administrator account should be shared. Emergency access should be time-boxed, audited, and reviewed after use.

## Records and attachment retention

The system stores potentially sensitive documents and completion attachments. Before launch, approve a written records policy covering:

- retention period per document/action-slip type;
- legal hold procedure;
- archival storage location and encryption;
- who may approve deletion;
- how deletion is audited;
- how backups age out deleted records;
- privacy handling for personal data in attachments and logs.

Until that policy exists, do not run automated deletion of attachments or workflow records.

## Acceptance testing matrix

Run this on staging with production-like volume:

- browser coverage: current Chrome, Edge, Firefox, and one mobile viewport;
- accessibility: keyboard-only navigation, visible focus, form labels, color contrast, screen-reader landmarks;
- responsive layout: dashboard, list pages, document/action-slip forms, attachment previews;
- authorization: every role attempts allowed and forbidden document/action-slip operations;
- workflow: draft, release, receive, thru, to, cc, delegate, return, re-upload, complete, confirm, close;
- uploads: allowed types, blocked types, oversized files, scanner failure, quota exhaustion;
- concurrency: simultaneous action-slip creation, simultaneous receive/forward, repeated double-submit, QR-token generation;
- volume: realistic record counts for users, departments, documents, logs, assignments, notifications, and action slips.

Record evidence, defects, owner, and signoff for every launch-blocking scenario.
