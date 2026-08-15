# School SaaS

Multi-tenant school management platform. Three deliverables, three folders.

```
android/   Kotlin mobile client (offline sync, WorkManager)
windows/   .NET 8 WPF desktop client (offline queue)
website/   PHP 8 + MySQL API, admin panel, migrations, tools
```

## website/

```
website/
  config.php        environment, sessions, CSP + security headers, CORS
  database.php      PDO factory (utf8mb4, real prepares) + LIKE escaping
  auth.php          authn/authz, tenant isolation, rate limit, throttle, audit
  api/              JSON API (index.php, ModuleRouter, TeacherRouter, Response)
  admin/            server-rendered admin panel
  migrations/       numbered .sql migrations (checksum-locked)
  tools/migrate.php migration runner
```

### Setup

```bash
cp website/config.example.php website/config.local.php   # or export env vars
php website/tools/migrate.php --dry-run
php website/tools/migrate.php
php -S 127.0.0.1:8080 -t website
```

Required environment: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`, `APP_ENV`, `APP_KEY`, `CORS_ALLOWED_ORIGINS`.

## Clients

- **Android** — set `API_BASE_URL` in `android/app/build.gradle.kts`. Cleartext HTTP is blocked except emulator loopback.
- **Windows** — set `SCHOOL_SAAS_API_BASE_URL`; the app rejects non-HTTPS URLs outside loopback.

## Security

Findings and controls: [`docs/SECURITY-AUDIT.md`](docs/SECURITY-AUDIT.md).
Reporting: [`.github/SECURITY.md`](.github/SECURITY.md).
CI lints every PHP file, applies migrations twice against MySQL, and fails on
dangerous functions, interpolated SQL, unescaped output, committed secrets and
cleartext client endpoints.
