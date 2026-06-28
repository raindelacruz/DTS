# Database migrations

Back up the database and test against a restored production copy before applying migrations.

Configure `APP_ENV`, `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME`, then run:

```powershell
php database/migrate.php
```

Applied migration names are recorded in `schema_migrations`. Application requests never create or alter tables.

For a fresh production database, import `dts_db.sql`, run all migrations, and then create the initial administrator with:

```powershell
php database/bootstrap_admin.php --id=SYSADMIN --first=System --last=Administrator --email=admin@example.gov.ph --department=1
```

The password is read from `BOOTSTRAP_ADMIN_PASSWORD` or prompted on standard input. The command is disabled after any administrator exists. Seed user accounts and password hashes are intentionally excluded from `dts_db.sql`.
