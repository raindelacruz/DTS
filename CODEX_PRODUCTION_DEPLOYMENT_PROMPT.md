# DTS Production Deployment Prompt For Codex

Use this prompt on the production Windows server.

```text
You are Codex running on the production Windows server through PowerShell. Read this entire instruction before doing anything.

Goal:
Deploy the DTS release from the user's Downloads folder to the production DTS server safely.

Important rules:
- Do not modify application code.
- Do not overwrite, delete, empty, or replace the production storage folder.
- Do not run php database/migrate.php.
- Use only docs/production-schema-only-migration-202609.sql for the database change.
- Stop immediately if any command fails.
- Stop immediately if any schema pre-check shows the target object already exists.
- Before making production changes, show the detected paths, backup locations, database name, and planned commands, then ask for confirmation.
- Use PowerShell commands only.

Expected release folder:
- The release folder should be in the default Downloads directory.
- The expected folder name is DTS-main (18).
- If that exact folder is not found, search the current user's Downloads folder for DTS-main* directories and ask me which one to use.

Likely production folder:
- C:\xampp\htdocs\DTS
- If this path does not exist, search C:\xampp\htdocs for DTS-like folders and ask me which one is production.

Deployment sequence:
1. Locate the downloaded release folder, preferably:
   C:\Users\<current-user>\Downloads\DTS-main (18)
2. Locate the current production DTS folder, preferably:
   C:\xampp\htdocs\DTS
3. Show both resolved absolute paths and ask for confirmation before continuing.
4. Confirm production environment requirements are configured:
   APP_ENV=production
   APP_URL
   APP_KEY
   DB_HOST
   DB_USER
   DB_PASS
   DB_NAME
   REQUIRE_MALWARE_SCAN=1
   MALWARE_SCAN_COMMAND
5. Back up the production database.
6. Back up the production storage folder.
7. Capture before row counts for:
   departments
   department_action_slips
   department_action_slip_events
   document_assignments
   documents
   users
8. Run the schema pre-check queries from:
   docs/production-schema-only-migration-guide-202609.md
9. If any target column/table already exists, stop and report what exists.
10. If the pre-check is clean, import:
    docs/production-schema-only-migration-202609.sql
11. Run the validation queries from the guide.
12. Capture after row counts for the same tables and confirm they match the before counts.
13. Deploy the release files into the production DTS folder, excluding:
    storage
    .git
    node_modules
    any production-specific environment or server config files
14. Preserve production uploads, logs, backups, and server-specific settings.
15. If Composer is available and vendor dependencies are missing or need refresh, run:
    composer install --no-dev --optimize-autoloader
16. Run:
    php tests/run.php
17. Run a PHP syntax check across:
    app
    public
    helpers
    database
    scripts
18. Report:
    - release folder used
    - production folder used
    - database backup path
    - storage backup path
    - schema migration result
    - row count comparison
    - files deployed
    - test results
    - any remaining manual smoke tests needed

Manual smoke tests to remind me to perform:
- Login
- Dashboard load
- Documents list/view/create/edit if allowed
- Action slips list/view/create/routing if allowed
- File upload and file viewing
- Old record viewing
- User access changes
- Logout
```
