# Production Schema-Only Migration Guide

This guide is for deploying the September 2026 database structure without importing a full dump and without running the data-changing migrations.

## Why This Is Needed

The current production structure in `dts_db_production.sql` is missing newer schema objects used by the current application code. However, the repository migrations also contain data-changing steps that update action slip history, create assignment rows, delete copied events, and recalculate statuses.

For a no-data-touch production deployment, run only the schema-only SQL package:

`docs/production-schema-only-migration-202609.sql`

Do not run `database/migrate.php` for this deployment.

## What The Schema-Only Script Adds

- `department_action_slips.title`
- `department_action_slips.parent_slip_id`
- `department_action_slips.current_unit_id`
- `departments.office_level`
- `departments.can_release`
- `document_assignments.parent_assignment_id`
- `document_assignments.assignment_type` support for `UNIT`
- `user_access_change_history`
- `department_action_slip_assignments`
- Related indexes

## What It Does Not Do

- It does not fill `department_action_slips.title`.
- It does not classify existing departments as department/division/unit.
- It does not link old action slips to parent slips.
- It does not generate assignment rows from old action slip events.
- It does not copy, delete, or consolidate action slip event history.
- It does not recalculate assignment or completion statuses.

## Safe Migration Procedure

1. Push and tag the reviewed application code.
2. Schedule a maintenance window.
3. Back up the production database.
4. Restore that backup to staging.
5. Run `docs/production-schema-only-migration-202609.sql` on staging.
6. Confirm row counts for existing tables did not change.
7. Smoke test staging: login, dashboard, documents, action slips, user access changes, uploads, routing, and viewing old records.
8. Back up production again immediately before deployment.
9. Run the schema-only SQL on production.
10. Deploy the application files from the reviewed tag or branch.
11. Smoke test production.
12. Keep the data-changing migrations disabled until their effects are separately approved.

Do not rely on SQL transaction rollback for this operation. MariaDB/MySQL DDL statements usually cause implicit commits, so the real rollback plan is restoring the verified production backup.

## Pre-Run Checks

Before running the schema-only SQL, confirm the target production database does not already have these objects:

- `department_action_slips.title`
- `department_action_slips.parent_slip_id`
- `department_action_slips.current_unit_id`
- `departments.office_level`
- `departments.can_release`
- `document_assignments.parent_assignment_id`
- `user_access_change_history`
- `department_action_slip_assignments`

If any already exist, stop and have the DBA adjust the script for the actual production state.

The script does not write rows to `schema_migrations`. That is intentional: marking the normal application migrations as applied would hide the fact that their data-changing portions were skipped. Track this schema-only deployment in the release notes or deployment ticket instead.

## Validation Queries

After running the script, verify the new structures exist:

```sql
SHOW COLUMNS FROM department_action_slips LIKE 'title';
SHOW COLUMNS FROM department_action_slips LIKE 'parent_slip_id';
SHOW COLUMNS FROM department_action_slips LIKE 'current_unit_id';
SHOW COLUMNS FROM departments LIKE 'office_level';
SHOW COLUMNS FROM departments LIKE 'can_release';
SHOW COLUMNS FROM document_assignments LIKE 'parent_assignment_id';
SHOW TABLES LIKE 'user_access_change_history';
SHOW TABLES LIKE 'department_action_slip_assignments';
```

Also compare row counts before and after for existing tables. They should not change:

```sql
SELECT 'departments' AS table_name, COUNT(*) AS rows_count FROM departments
UNION ALL SELECT 'department_action_slips', COUNT(*) FROM department_action_slips
UNION ALL SELECT 'department_action_slip_events', COUNT(*) FROM department_action_slip_events
UNION ALL SELECT 'document_assignments', COUNT(*) FROM document_assignments
UNION ALL SELECT 'documents', COUNT(*) FROM documents
UNION ALL SELECT 'users', COUNT(*) FROM users;
```

## Important Limitation

This schema-only migration protects existing data from being rewritten, but it also means old records are not normalized into the new assignment/unit workflow. If old action slip records must fully participate in the new workflow, that should be handled later as a separate staged data migration with before/after review and approval.
