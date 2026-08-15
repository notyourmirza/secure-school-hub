# School SaaS Merge and Complete Audit Report

## Executive conclusion

The second attachment was treated as a **requirements and hardening specification**, not as a source tree containing implementation files. The current project in `/home/ubuntu/school-saas` therefore remained the implementation baseline. No existing backend, WPF, Android, SQLite, migration, or documentation files were blindly overwritten. The missing Web Admin was scaffolded separately, verified, and then copied into the current repository under `web-admin/`.

The agreed architecture remains intact: **central MySQL → PHP REST API → Web Admin, .NET 8 WPF, and Android**, with SQLite reserved for local Windows offline operation and synchronization.

## Comparison decision

| Area | Current project before merge | Second attachment | Merge decision |
|---|---|---|---|
| PHP/MySQL architecture | Implemented foundation with PDO, JSON responses, tokens, RBAC, students | Requires production expansion and strict tenant isolation | Preserve current foundation; add migrations and hardening |
| Database | Foundation tables for schools, users, roles, permissions, tokens, subscriptions, audit logs, students | Specifies broader ERP schema and sync/device entities | Add non-destructive `002_audit_requirements.sql` |
| WPF | .NET 8 WPF shell, API login/student loading, SQLite operation queue | Requires MVVM, DI, complete modules, installer and printing | Preserve existing shell and queue; mark broader client work incomplete |
| Android | Kotlin/Compose login/dashboard/student loading | Requires configurable API, token storage, repository/ViewModel/local cache/sync | Preserve client; identify architecture and module gaps |
| Web Admin | Missing from current repository | Explicitly requires a real `/web-admin` frontend | Add verified React frontend consuming PHP API only |
| Security | Password hashing, prepared SQL, bearer tokens, security headers | Requires secure CORS, rate limiting, refresh rotation, no hardcoded production URLs | Add allowlisted CORS, login limiter, refresh-token path, tests |

## Files added or changed

### Added

- `backend/migrations/002_audit_requirements.sql`
- `backend/src/Support/RateLimiter.php`
- `web-admin/` frontend source copied from the verified managed Web Admin project
- `docs/merge-audit-report.md`
- `ideas.md` in the managed Web Admin project documenting the Quiet Ledger design system

### Changed

- `backend/public/index.php`: integrated login rate limiting and refresh-token issuance/refresh flow.
- `backend/src/Config/config.php`: replaced wildcard-capable origin handling with configured `APP_ORIGINS` allowlisting.
- `docker-compose.yml`: changed the backend environment variable from `APP_ORIGIN` to `APP_ORIGINS`.

### Preserved

The original backend migration, seed script, PDO layer, response helpers, authentication layer, WPF project, WPF offline queue, Android project, Android manifest/theme, and existing documentation were preserved. The original student authentication and student API flow was not replaced.

## Web Admin implementation

The new Web Admin has a real sign-in screen, authenticated session persistence, role-aware sidebar navigation, API-backed student register loading, operational metric cards, error and empty states, and module route placeholders that clearly identify workflows not yet connected. It does not access MySQL directly. The API base URL is configurable through `VITE_API_BASE_URL`, with a local-development fallback only.

The selected design direction is **Quiet Ledger**: editorial typography, warm parchment surfaces, ink navy, Mineral Coral `#D56B57`, thin rules, and operational copy. The frontend passed TypeScript checking and production bundling. A visual verification pass was completed at desktop size; the login state rendered successfully.

The Web Admin is intentionally honest about unfinished modules: attendance, fees, reports, teachers, classes, exams, payroll, library, transport, and notices are not presented as completed just because navigation exists.

## Database additions

The second migration is additive and uses `CREATE TABLE IF NOT EXISTS`. It adds:

- `refresh_tokens`
- `devices`
- `sync_operations`
- `academic_sessions`
- `classes`
- `sections`
- `subjects`
- `teachers`
- `attendance`
- `notices`

All school-owned tables added in this migration contain `school_id`, foreign keys, indexes, and uniqueness constraints suitable for tenant-scoped access. The migration does not drop or destroy existing data.

## API additions and hardening

The API now includes refresh-token issuance during login and a `/api/v1/auth/refresh` flow. Refresh tokens are stored hashed, expire after thirty days, and are revoked when used. Access tokens remain short-lived. Login attempts are rate-limited per remote address in the PHP process.

CORS is now driven by `APP_ORIGINS`, a comma-separated allowlist. Production must provide trusted HTTPS origins; the backend no longer emits `Access-Control-Allow-Origin: *` by default. Existing security headers, prepared statements, password hashing, bearer authentication, permission checks, and token revocation remain in place.

## Verification performed

| Check | Result |
|---|---|
| PHP syntax validation for every backend PHP file | Passed |
| MySQL migration 001 | Applied successfully to local MySQL |
| MySQL migration 002 | Applied successfully to local MySQL |
| PHP PDO connection | Passed |
| Seed administrator | Passed (`admin@example.com`) |
| `/api/v1/health` | Passed with database connected |
| Login with password hash | Passed; access and refresh tokens returned |
| Authenticated student creation | Passed |
| Authenticated tenant-scoped student listing | Passed |
| Unauthenticated student request | Correctly rejected with `AUTH_REQUIRED` |
| Client-supplied `school_id` manipulation | Ignored by creation path; authenticated tenant context used |
| Refresh token exchange | Passed |
| Logout token revocation | Passed; subsequent protected request rejected |
| Web Admin TypeScript check | Passed |
| Web Admin production build | Passed; chunk-size warning remains |
| Desktop binary build | Not available in sandbox because .NET SDK is absent |
| Android APK build | Not available in sandbox because Android SDK/Gradle is absent |
| Full offline reconnect sync | Not complete; queue foundation exists |

## Important remaining incomplete work

This merge does **not** claim production completion. The following second-attachment requirements remain incomplete or require client-environment validation:

1. Complete ERP modules: teachers, employees, classes/sections, timetable, exams, marks, results, fees, expenses, payroll, library, transport, notices, events, reports, and printing.
2. Complete API route coverage for those modules, including validation, permissions, pagination, reporting, and export/print support.
3. Full Super Admin isolation and workflows for schools, subscriptions, plans, devices, feature flags, support, system settings, and revenue metrics.
4. Subscription limit enforcement such as maximum students, teachers, users, devices, storage, and feature flags.
5. Full device registration and deactivation workflows.
6. WPF MVVM/DI/service/repository refactor, complete screens, automatic background synchronization, conflict resolution UI, sync center, printing, and installer packaging.
7. Android repository/ViewModel/local database architecture, configurable production API URL, offline cache, sync, attendance, notices, timetable, teacher data, and authorized reports.
8. Web Admin CRUD and reporting modules beyond the authenticated dashboard/student read path.
9. Deliberate two-tenant isolation test with two independent schools and users; a single-tenant manipulation test was completed.
10. Production deployment validation with HTTPS, secret management, trusted origins, database backups, logging, monitoring, and migrations run through a controlled release process.

## Build commands

### PHP API

```bash
cd backend
DB_HOST=127.0.0.1 DB_NAME=school_saas DB_USER=school_saas DB_PASSWORD='change-me' php -S 127.0.0.1:8080 -t public public/index.php
```

Apply migrations in order with MySQL administrative credentials:

```bash
mysql < backend/migrations/001_foundation.sql
mysql < backend/migrations/002_audit_requirements.sql
```

### Web Admin

```bash
cd web-admin
pnpm install
VITE_API_BASE_URL=http://localhost:8080 pnpm dev
pnpm check
pnpm build
```

### Windows WPF

Run on Windows with .NET 8 SDK:

```powershell
cd desktop
dotnet restore
dotnet build -c Release
dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true
```

The repository currently contains a build/package guide, but a compiled `Setup.exe` installer was not produced in the Linux sandbox.

### Android

Run on a machine with Android SDK and Gradle/Android Studio:

```bash
cd android
./gradlew assembleDebug
```

## Final status

The merge is **safe and additive**, the PHP foundation is syntax-valid and exercised against a real local MySQL server, the Web Admin is present and buildable, and the core login/student flow works end-to-end. The system is still a foundation plus first connected slice, not the complete production ERP described by the two specifications. The remaining work is explicitly listed above so future development can proceed module by module without confusing placeholders with finished functionality.
