# Attachment 1 and Attachment 2 Merge Audit

## Executive conclusion

Attachment 1 and Attachment 2 were independently inspected against the actual canonical School SaaS repository. Both are detailed implementation, audit, security, and acceptance prompts; neither is a source package. They contain no PHP source, SQL migration files, Web Admin code, WPF project, Android source, binaries, or executable test artifacts. Therefore there was no alternate implementation to copy, overwrite, or blindly merge.

The canonical project remains the only implementation source. The attachments were merged as **requirements and verification standards**. Attachment 1 contributes the broader production-readiness module scope and exact business rules. Attachment 2 contributes the disciplined comparison method, status labels, security checklist, two-school isolation tests, offline test matrix, and final-report structure.

## Architecture preserved

The merge preserves one PHP 8.3 REST API, one central MySQL database, one authentication/RBAC model, one tenant boundary derived from authenticated context, one Web Admin, one .NET 8 WPF client, one Kotlin Android client, and one offline-sync architecture based on `operation_uuid`, `client_uuid`, transactional push, replay protection, durable queues, cursor pull, WPF `SyncService`, Android `WorkManager`, and local cache/conflict records.

No production migration was dropped, no production table was reset, no direct client-to-MySQL connection was added, and no second synchronization engine was introduced.

## Three-way comparison summary

| Area | Attachment 1 | Attachment 2 | Canonical project | Decision |
|---|---|---|---|---|
| Backend/API/database | Requirements for PHP/MySQL, transactions, validation, and tenant safety | Requires source audit and consistent API errors | 🟡 Partial but executable; PHP lint and API smoke evidence pass | KEEP CURRENT; use both checklists |
| RBAC/security | Broad role and two-school requirements | Detailed security and authorization audit | 🟡 Partial; controls exist, complete matrix tests pending | IMPROVE CURRENT |
| Offline/sync | Full pull/cache/retry/conflict/reconnect gate | Explicitly declares current sync canonical | 🟡 Partial; duplicate-safe push and cursor/pull foundations exist | PRESERVE CURRENT; improve additively |
| Web Admin | Real forms, states, validation, API actions | Search/filter/pagination/responsive/security requirements | 🟡 Partial; typecheck/build pass, full module E2E pending | KEEP CURRENT; improve incrementally |
| WPF | Native module workflows, MVVM, offline behavior | No generic JSON editors, native/build gate | ⚠️ Source Implemented — Native Build Unverified | KEEP CURRENT; corrected API contract mismatches |
| Android | Teacher workflows, cache, pull, conflicts, WorkManager | APK/runtime and offline matrix | ⚠️ Source Implemented — Native Build Unverified | KEEP CURRENT; added conflict acknowledgement surface |
| ERP scope | Adds Academic Sessions, Teacher Attendance, Notifications, Events, Certificates, Settings, Super Admin, Subscriptions, Devices, Audit Logs | Audits the same wider scope | 🟡/🔴 Mixed partial and missing coverage | Preserve canonical scope and track gaps |

The full module-level matrix is in `ATTACHMENT-1-2-COMPARISON-MATRIX.md`.

## Safe changes merged into the canonical project

The WPF form metadata was corrected to match the actual PHP/MySQL contracts. Fee structures now submit `active` and support `one_time`; payments submit `reference_no`; exam subjects submit `passing_marks`; library loans use the database enum values `issued`, `returned`, and `lost`; and transport assignments no longer submit the unsupported `is_active` field. These changes prevent avoidable client/API constraint failures while preserving the existing queue and mutation flow.

The PHP module router now enforces additional server-side business rules. Timetable creates and complete updates reject overlapping schedules for the same class or teacher, while preserving partial PATCH compatibility. Library loans reject due dates before issue dates. Payroll items reject negative deductions/net values and require `net_amount = gross_amount - deductions` when all three values are supplied. All additions use the existing authenticated, tenant-scoped router and consistent error envelope.

The Android app now exposes recorded sync conflicts through a visible Conflicts action and allows local acknowledgement of reviewed conflict records. The UI explicitly states that acknowledgement is local and that server-side resolution still requires a supported API action; it does not falsely claim complete conflict resolution or create a second sync system.

## Code preserved and code rejected

The canonical PHP authentication, RBAC, tenant derivation, migrations, Web Admin, WPF queue/SyncService, Android operation store, WorkManager, cursor pull, and duplicate-safe replay code were preserved. No attachment code was rejected because no attachment code was supplied. Requirements that cannot yet be evidenced by implementation or tests remain marked partial, missing, or not verified rather than being represented as completed.

## Verification performed after merge

| Verification | Result |
|---|---|
| PHP syntax lint | PASS for all backend PHP files |
| WPF API-contract scan | PASS; stale `is_active`, `reference`, `pass_marks`, and `overdue` form keys removed |
| Android conflict integration scan | PASS at source level |
| Web Admin TypeScript check | PASS |
| Web Admin production build | PASS with non-blocking chunk-size and runtime asset-resolution warnings |
| Native WPF build/runtime | NOT VERIFIED — Windows/.NET 8 environment unavailable |
| Native Android build/runtime | NOT VERIFIED — Gradle/Android SDK environment unavailable |
| Full module E2E matrix | INCOMPLETE; required fixtures and native execution remain unavailable |

## Remaining work

The attachments identify requirements that remain outside the evidence boundary: full two-school isolation fixtures, role-by-role 401/403 tests, complete create/update/delete tests for every module, full offline update/delete/retry/conflict-resolution flows, report query/export verification, Academic Sessions, Events, Certificates, complete Notifications/Settings/Super Admin/Subscription/Device/Audit workflows, and native WPF/Android build/runtime acceptance. These are not attachment merge conflicts; they are remaining implementation and verification work in the canonical project.

## Final decision

The merge is **intelligent and additive**. The current project remains canonical, no competing implementation was introduced, objective contract mismatches found during audit were corrected, and the attachments’ stronger acceptance rules are now reflected in the comparison matrix and audit documentation. The software is not declared fully complete or production-ready under the requested strict gate.
