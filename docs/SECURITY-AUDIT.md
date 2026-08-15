# Security Audit — Restructure Phase

Scope: `website/` (PHP 8 + MySQL API and admin panel), `android/` (Kotlin),
`windows/` (.NET WPF). Method: manual source review of every PHP entry point,
every SQL statement, both client network layers, plus automated grep gates now
enforced in CI.

## 1. Findings and fixes

| # | Severity | Class | Finding | Fix |
| --- | --- | --- | --- | --- |
| 1 | High | Broken access control | Admin CRUD (`admin/module.php`) only checked "is logged in", so any authenticated staff account could read/write every module including fees and payroll | Per-module permission gates: `require_permission('<module>.view|create|update|delete')` |
| 2 | High | IDOR | Admin attendance and student writes accepted `student_id` / `class_id` without confirming the record belonged to the caller's school | Ownership pre-check query scoped by `school_id` before every insert/update; API `ModuleRouter` resolves tenant via `Auth::tenantId()` |
| 3 | High | Brute force | Rate limiting used an in-process array, which resets on every PHP request, so it limited nothing; no account lockout | MySQL-backed `RateLimiter` (fixed window per bucket+identifier) and `LoginThrottle` (per email and per IP) in `website/auth.php`, tables in migration `007` |
| 4 | High | Session fixation / hijack | Session cookie flags and id rotation were inconsistent between admin pages | Central `bootstrap.php`: `HttpOnly`, `Secure` (when HTTPS), `SameSite=Lax`, strict mode, `session_regenerate_id(true)` on login, idle + absolute timeout |
| 5 | Medium | SQL injection (latent) | Search used raw `%$q%` in `LIKE`; user-supplied `%` and `_` altered query semantics, and emulated prepares were enabled | `Database::likeParam()` escapes `\ % _` with an explicit `ESCAPE` clause; PDO now runs with `ATTR_EMULATE_PREPARES => false` and `ERRMODE_EXCEPTION` |
| 6 | Medium | XSS | Reflected filter values and module labels rendered without escaping in a few admin views | All output passes through `e()` (`htmlspecialchars`, `ENT_QUOTES\|ENT_SUBSTITUTE`, UTF-8); a strict CSP without `unsafe-inline` for scripts is sent on every response |
| 7 | Medium | CSRF | Tokens existed but were not verified on every state-changing handler, and were not per-session random | `verify_csrf()` on every POST, 32-byte random token compared with `hash_equals`, rotated on login |
| 8 | Medium | Information disclosure | Database exceptions and stack traces surfaced in API and admin responses | Generic user-facing messages; details go to `error_log` only. `display_errors` forced off outside local dev |
| 9 | Medium | Unvalidated input | No length or type bounds on free-text fields; malformed dates reached MySQL | `clamp_text()` limits, `valid_date()` for date inputs, allow-lists for every enum/status/sort field, JSON bodies capped at `MAX_JSON_BODY_BYTES` |
| 10 | Medium | Transport | Desktop client defaulted to `http://localhost:8080`; Android allowed cleartext to any host | Desktop refuses non-HTTPS URLs unless loopback; Android ships `network_security_config.xml` with `cleartextTrafficPermitted="false"` and `usesCleartextTraffic="false"` |
| 11 | Medium | Missing headers | No CSP, HSTS, frame or MIME protections | `send_security_headers()` sends CSP, `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, and HSTS over HTTPS |
| 12 | Low | CORS | Wildcard origin on the API | Origin allow-list from `CORS_ALLOWED_ORIGINS`, credentials only for allow-listed origins, explicit method/header lists |
| 13 | Low | Auditability | Writes were not consistently recorded | `audit()` / `Auth::audit()` on create, update, delete, login and logout, indexed by `(school_id, created_at)` |

### File upload

There is no user-facing upload endpoint in this codebase, so no upload
vulnerability exists today. The rules a future upload endpoint must follow are
recorded in the PR checklist: validate real MIME type from content (not the
client-supplied header), allow-list extensions, cap size, generate a random
stored filename, store outside the web root, and never serve with a
user-controlled `Content-Type`.

## 2. Controls now enforced centrally

- `website/config.php` — environment loading, secure session parameters, CSP and security headers, CORS allow-list, input size constants.
- `website/database.php` — single PDO factory (`utf8mb4`, exceptions, no emulated prepares) and `likeParam()`.
- `website/auth.php` — bearer-token and session authentication, `tenantId()`, `requirePermission()`, `RateLimiter`, `LoginThrottle`, `audit()`.
- `website/admin/includes/bootstrap.php` — every admin page starts sessions and headers the same way.

## 3. Database verification

Seven migrations apply cleanly in order against MySQL 8 and are checksum-locked
by `website/tools/migrate.php`; re-running is a no-op. Migration `007` adds
`rate_limits`, `login_attempts` and the audit/token indexes. CI applies the full
chain twice against a live MySQL service and asserts the core tables exist, so
schema drift fails the build.

## 4. Residual risks

- Passwords use `password_hash()` defaults; consider Argon2id with tuned cost.
- No MFA for admin accounts.
- Rate limiting is per application node; a multi-node deployment shares state
  only because it lives in MySQL — move to Redis if write volume grows.
- Gradle wrapper is not committed, so the Android CI job is a no-op until it is.
