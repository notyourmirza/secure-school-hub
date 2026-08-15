# ERP Module Acceptance Tests

This file converts the fourth attachment into a mandatory acceptance checklist. A module may be marked **🟢 COMPLETE** only when every applicable column has a passing test. A route, table, button, or generic CRUD page alone is insufficient.

| Module | UI | PHP API | MySQL | RBAC | Validation | Domain transaction/business rule | WPF native workflow | Android workflow | Offline/sync | Error paths | E2E status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Dashboard | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | N/A | ☐ | 🔴 |
| Students | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ where teacher uses it | ☐ | ☐ | 🔴 |
| Teachers | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | ☐ if offline admin mutation | ☐ | 🔴 |
| Employees | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | ☐ if offline admin mutation | ☐ | 🔴 |
| Classes | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ read where assigned | ☐ | ☐ | 🔴 |
| Sections | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ read where assigned | ☐ | ☐ | 🔴 |
| Subjects | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ read where assigned | ☐ | ☐ | 🔴 |
| Academic Sessions | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | ☐ | ☐ | 🔴 |
| Attendance | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ duplicate-safe create/retry/pull/conflict | ☐ | 🔴 |
| Teacher Attendance | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ duplicate-safe create/retry/pull/conflict | ☐ | 🔴 |
| Timetable | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ read | ☐ pull/cache/conflict where edited | ☐ | 🔴 |
| Fees / invoices / payments | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | ☐ | ☐ | 🔴 |
| Discounts / fines | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | ☐ | ☐ | 🔴 |
| Expenses | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | ☐ | ☐ | 🔴 |
| Exams | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ read | ☐ | ☐ | 🔴 |
| Marks | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ create/update | ☐ duplicate-safe where edited | ☐ | 🔴 |
| Results | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ read | ☐ | ☐ | 🔴 |
| Payroll | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | ☐ | ☐ | 🔴 |
| Library | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | ☐ loan/return where offline | ☐ | 🔴 |
| Transport | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ read where assigned | ☐ | ☐ | 🔴 |
| Notices / notifications | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ read/acknowledge | ☐ pull/cache | ☐ | 🔴 |
| Events | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ read | ☐ | ☐ | 🔴 |
| Reports | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ read where authorized | N/A | ☐ | 🔴 |
| Certificates | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | ☐ | ☐ | 🔴 |
| Settings | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | ☐ | ☐ | 🔴 |
| Super Admin / subscriptions / limits | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | N/A | N/A | ☐ | 🔴 |

## Mandatory common tests

Every module test suite must include valid create/read/update/delete where the operation is supported, malformed input, unauthorized role, wrong-tenant identifier, not-found identifier, database constraint violation, and a consistent JSON error envelope. Offline-capable modules must also include offline create/update/delete as applicable, reconnect, automatic retry, manual retry, failed-operation retention, duplicate replay, canonical ID reconciliation, and conflict handling.

Native build results must be recorded as either **PASS** or **⚠️ NOT VERIFIED — BUILD ENVIRONMENT REQUIRED**. No native workflow is considered complete from source inspection alone.

## Latest implementation evidence

The WPF editor now uses module-specific native field definitions, typed controls, required-field validation, numeric bounds, date/time checks, select options, grid-row editing, API mutations, and the existing SQLite queue fallback. The generic JSON payload editor is no longer the primary WPF workflow.

The Android sync foundation now includes cursor-based pull through `/api/v1/sync/pull`, cursor status through `/api/v1/sync/status`, durable cached entity records, conflict records, login-time reconciliation, and WorkManager reconciliation. The original operation UUID/idempotent push queue remains unchanged as the write foundation.

These changes do not by themselves mark modules complete. Native Android and WPF compilation, module-specific business workflows, full conflict resolution, and per-module UI-to-MySQL acceptance tests remain required by the completion gate.

### Backend checks

- PHP syntax validation: passed after adding pull/status endpoints and domain-aware routing.
- Existing duplicate-safe push tests: passed for students and attendance.
- Android native build: pending because Android SDK/Gradle is unavailable in the current sandbox.
- WPF native build: pending because the .NET SDK is unavailable in the current sandbox.

### Sync pull verification

- Authenticated `GET /api/v1/sync/status`: passed; local cursor returned `2`.
- Authenticated `GET /api/v1/sync/pull?cursor=0&limit=10`: passed; returned two tenant-scoped canonical changes, advanced cursor to `2`, and reported `has_more=false`.
- Pull data included canonical operation UUID, client UUID, entity, canonical ID, payload, replay result, and processed timestamp.
- This verifies the transport and cursor contract; it does not yet prove full entity-level conflict resolution or complete teacher cache UX, so related module acceptance rows remain incomplete.

### WPF source-level module workflow update

The WPF client now exposes module-specific native form definitions and navigation for Students, Teachers, Employees, Classes, Sections, Subjects, Attendance, Timetable, Fees, Fee Structures, Payments, Expenses, Exams, Exam Subjects, Marks, Results, Payroll Runs, Payroll Items, Library Books, Library Loans, Transport Routes, Transport Assignments, Notices, and Reports. Each form uses typed field metadata, required-field checks, numeric bounds, select options, date/time ordering checks, JSON filter validation, API mutations, and the preserved SQLite idempotent queue fallback on network failure. The existing operation UUID, client UUID, retry, and sync service contracts were not replaced.

These changes are **Source Implemented — Native Build Unverified**. No WPF module is fully verified or production-ready because this environment has no Windows/.NET 8 SDK and no native runtime test. The acceptance gate remains incomplete for every WPF-dependent module until compilation and runtime evidence are available.
