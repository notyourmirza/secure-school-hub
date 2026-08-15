# School SaaS Merge Audit Baseline

## Source comparison

The first attachment was a requirements specification. The current project was generated from that specification and contains 20 source/configuration files across `backend`, `desktop`, and `android`. The second attachment is also a requirements and development instruction document; it does not contain an alternate source tree, database dump, API implementation, WPF project, Android project, or Web Admin files. Therefore, there is no second implementation to choose as newer or to copy file-for-file. Its actionable differences are treated as an audit checklist and target architecture requirements.

## Existing implementation found

| Area | Existing state | Audit result |
|---|---|---|
| PHP API | Plain PHP bootstrap with health, login, logout, and student list/create routes | Real foundation, but incomplete module coverage and no refresh tokens, rate limiting, pagination, or sync routes |
| MySQL | Foundation migration for schools, plans, subscriptions, roles, permissions, users, access tokens, students, and audit logs | Valid starting point, but far short of the requested ERP schema |
| RBAC | Permission tables and server-side permission lookup | Real enforcement exists for current student routes; role-permission seed is development-only |
| Tenant isolation | Student queries constrain by authenticated `school_id` | Present in current student routes; broader endpoint coverage absent |
| WPF | Single-window shell, API login, student fetch, role-aware navigation text | Partial working shell; navigation buttons are placeholders for unimplemented modules |
| SQLite | Durable `sync_queue` table and enqueue/pending/status methods | Foundation only; no API push/pull engine or conflict handling |
| Android | Compose login and student-load flow | Partial working shell; token storage, repository/ViewModel separation, cache, and sync absent |
| Web Admin | No web-admin directory or frontend | Missing entirely |
| Super Admin | No separate panel or routes | Missing entirely |
| Installer | Build instructions only | No installer project |

## High-risk findings

1. Client API URLs are hardcoded (`localhost` in WPF and `10.0.2.2` in Android), violating the second attachment’s configuration requirement.
2. Development database passwords and a demo administrator password are present in local configuration/seed files. They must be isolated to development and replaced with environment/secret configuration for production.
3. CORS defaults to `*`, which is unsafe for production.
4. Login has no rate limiting or brute-force protection.
5. There is no refresh-token rotation flow.
6. The PHP API connects to MySQL before serving the health route and has no migration runner or end-to-end test harness.
7. WPF and Android currently implement only the student slice; most requested modules do not exist.
8. No Web Admin exists despite being a required platform.
9. The Android app uses raw `HttpURLConnection` in the activity and does not yet persist tokens securely.
10. The second attachment cannot be merged as code because it contains no code; its requirements must be implemented as new work without deleting the current foundation.
