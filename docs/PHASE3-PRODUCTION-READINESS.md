# Phase 3 Production Readiness

## Database pre-flight

Before touching a development, staging, or production database, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in the PHP runtime environment. Take a logical backup with `backend/backup.sh`; it uses `mysqldump --single-transaction` and does not hardcode credentials.

Inspect the live schema, foreign keys, indexes, and existing data. The migration runner creates `schema_migrations`, applies SQL files in lexical order, records a SHA-256 checksum, skips already-applied files, and aborts on a checksum mismatch. Preview first:

```bash
cd backend
php migrate.php --dry-run
```

After the backup and review, apply migrations once:

```bash
cd backend
php migrate.php
```

Migrations 004–006 must be reviewed against the exact MySQL/MariaDB version and existing schema before production use. Migration 006 requires an operator to populate `teachers.user_id` for existing teacher accounts and `students.class_id` for existing student records; rows without these relationships are intentionally not exposed through teacher-assignment queries.

## Runtime configuration

The API and PHP admin share `backend/src/Database/Database.php`, which uses PDO with `utf8mb4`, exception mode, and native prepared statements. Configure the web server so `/admin` is served from the project’s `admin/` directory and `/api/v1` is routed to `backend/public/index.php`. Do not expose `.env` files, migration files, backups, or source directories directly.

## Tenant-isolation acceptance test

Create two active schools, two school-admin users, and two teacher users. Populate each tenant with students, classes, subjects, attendance, exams, marks, fees, admissions, notices, timetable entries, and notifications. Obtain a token for each tenant and test the same resource IDs from the other tenant. Every API query must return an empty result or a tenant/reference authorization error; it must never return the other school’s row. Repeat using URL IDs, query parameters, POST bodies, search values, and filters. The server must ignore any client-provided `school_id` as an authority.

The current sandbox has no configured live MySQL instance, so this acceptance test has not been falsely marked as passed. Execute it in staging before release and retain the request/response evidence.

## Suspension and rollback

When a school is suspended, API authentication rejects its active tokens with `SCHOOL_INACTIVE`, while data remains intact. To reactivate, change the school status to `active` through a Super Admin workflow or a reviewed SQL change. If a migration fails, stop deployment, retain the backup and error output, restore only with an approved rollback procedure, and do not rerun a modified migration under the same filename; the migration runner checksum guard is designed to prevent that ambiguity.

## Native applications

The Kotlin app remains native and keeps its local offline queue and WorkManager sync. The .NET application remains native. Android requests use the teacher-scoped API paths and must be configured with the deployed HTTPS base URL. Production builds should use HTTPS, secure token storage, certificate/network security appropriate to the organization, and release logging that excludes credentials, tokens, and personal data.
