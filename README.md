# School SaaS

A connected, multi-tenant school management SaaS built on a PHP 8.3 REST API, MySQL/MariaDB, a server-rendered PHP admin panel, a .NET 8 WPF client, and a native Kotlin Android teacher client.

## Architecture

MySQL is the canonical source of truth. PHP owns authentication, authorization, tenant isolation, plan limits, audit logging, and REST APIs. The PHP Admin Web renders HTML directly and uses CSS plus small amounts of vanilla JavaScript where needed. Native applications remain native and communicate through the PHP API.

```text
PHP Admin Web ─┐
WPF Desktop ───┼── HTTPS/JSON ── PHP REST API ── PDO ── MySQL/MariaDB
Android Kotlin ┘

Offline native operations ── SQLite queue ── sync when connected
```

## Repository layout

| Directory | Responsibility |
|---|---|
| `admin/` | Server-rendered PHP admin, reusable layout, CSRF/session helpers, tenant-scoped modules, and Super Admin area. |
| `backend/` | PHP REST API, authentication, shared authorization, migrations, synchronization, and ERP modules. |
| `android/` | Native Kotlin teacher application; preserved in its existing technology. |
| `desktop/` | Native .NET WPF client; preserved in its existing technology and aligned with the no-sections model. |
| `docs/` | Architecture, audit, acceptance, and offline-sync documentation. |

The former `web-admin/` React/Vite SPA has been removed from the primary project. The admin surface is now `admin/` and is served by PHP.

## PHP admin

The entrypoint is `admin/login.php`. The shared code in `admin/includes/` provides secure sessions, CSRF validation, tenant context derived from the authenticated session, plan-limit checks, audit logging, flash messages, and reusable layout components. Core pages include students, teachers, classes, subjects, attendance, exams, fees, admissions, notices, reports, and settings. Super Admin lives under `admin/super-admin/`.

## Database upgrade

Apply migrations in order through `backend/migrations/005_phase2_teacher_workflows.sql`. Migrations 004 and 005 remove the legacy section relationship from timetable data, add the admissions workflow, notifications, attendance idempotency, configurable plan limits, and teacher marks workflow fields. They are designed to be applied after the existing migrations; inspect the live schema and take a backup before applying them in production.

The teacher API now includes tenant-scoped dashboard, profile, classes, students, timetable, attendance read/write, exams, marks read/write, notices, notifications, and notification-read routes under `/api/v1/teacher/*`. Teacher reads and writes are filtered through teacher assignments when the `teachers.user_id` and `students.class_id` relationships are populated by migration 006. The Kotlin app has been pointed at the teacher-scoped read routes while retaining its existing local queue and sync architecture.

Phase 3 adds `backend/migrate.php`, which tracks applied migration filenames and checksums in `schema_migrations`, plus `backend/backup.sh` for explicit `mysqldump --single-transaction` backups. The sandbox has no reachable MySQL service; the migration dry-run therefore failed with `Connection refused` and was not counted as a successful database validation. See `docs/PHASE3-PRODUCTION-READINESS.md` for operator steps.

## Security baseline

Passwords use `password_hash`/`password_verify`; SQL uses prepared PDO statements; access tokens are hashed and expire; school-owned reads and writes use the authenticated `school_id`; server-side permissions are enforced; audit logs capture admin actions; CSRF protection is required for admin forms; and raw database errors are not rendered to end users.

## Local prerequisites

PHP 8.3+ with PDO MySQL and OpenSSL extensions, MySQL 8+/MariaDB, .NET 8 SDK on Windows, and Android Studio/JDK 17+ for the Android build.

## Status

The PHP admin migration and foundational tenant/API upgrade are implemented in this source package. Native application sources remain preserved. Run the verification commands below before deployment.

```bash
find admin backend -name '*.php' -print0 | xargs -0 -n1 php -l
find admin -type f -name '*.php' | sort
```
