# ERP Completion Matrix

This matrix follows the project completion gate: a module is complete only when UI, PHP API, MySQL persistence, permissions, offline/sync where applicable, validation/error handling, and tests all work together.

| Module | PHP/MySQL route | Web Admin CRUD | WPF CRUD | Android teacher workflow | Offline/sync | End-to-end status |
|---|---|---:|---:|---:|---:|---|
| Teachers | Present | Present | Source Implemented — Native Build Unverified | Not applicable | Queue path present; native/runtime unverified | Incomplete |
| Employees | Present | Present | Source Implemented — Native Build Unverified | Not applicable | Queue path present; native/runtime unverified | Incomplete |
| Classes | Present | Present | Source Implemented — Native Build Unverified | Not applicable | Queue path present; native/runtime unverified | Incomplete |
| Sections | Present | Present | Source Implemented — Native Build Unverified | Not applicable | Queue path present; native/runtime unverified | Incomplete |
| Subjects | Present | Present | Source Implemented — Native Build Unverified | Not applicable | Queue path present; native/runtime unverified | Incomplete |
| Attendance | Present, including idempotent offline create | Present | Source Implemented — Native Build Unverified | Teacher attendance flow present | Duplicate-safe create/pull transport verified; full conflicts/native runtime incomplete | Incomplete |
| Timetable | Present with overlap validation | Present | Source Implemented — Native Build Unverified | Read workflow present | Pull/cache transport present; conflict/native runtime incomplete | Incomplete |
| Fees | Present | Present | Source Implemented — Native Build Unverified | Not applicable | Queue path present; native/runtime unverified | Incomplete |
| Expenses | Present | Present | Source Implemented — Native Build Unverified | Not applicable | Queue path present; native/runtime unverified | Incomplete |
| Exams | Present | Present | Source Implemented — Native Build Unverified | Not applicable | Queue path present; native/runtime unverified | Incomplete |
| Marks | Present | Present | Source Implemented — Native Build Unverified | Not verified | Queue path present; native/runtime unverified | Incomplete |
| Results | Present | Present | Source Implemented — Native Build Unverified | Not verified | Not applicable; native/runtime unverified | Incomplete |
| Payroll | Present for runs/items with net validation | Present for runs/items | Source Implemented — Native Build Unverified | Not applicable | Queue path present; native/runtime unverified | Incomplete |
| Library | Present for books/loans with date/availability rules | Present for books/loans | Source Implemented — Native Build Unverified | Not verified | Queue path present; native conflict/runtime unverified | Incomplete |
| Transport | Present for routes/assignments | Present for routes/assignments | Source Implemented — Native Build Unverified | Not verified | Queue path present; native/runtime unverified | Incomplete |
| Notices | Present | Present | Source Implemented — Native Build Unverified | Read workflow present | Pull/cache transport present; native/runtime incomplete | Incomplete |
| Reports | Present as persisted report runs | Present | Source Implemented — Native Build Unverified | Not applicable | Not applicable; native/runtime unverified | Incomplete |

## Verified checks

- PHP source passes syntax validation.
- All requested top-level API list routes return authenticated success envelopes against local MySQL.
- Web Admin TypeScript check and production build pass.
- Login returns explicit permissions and Web Admin filters navigation and CRUD actions accordingly.
- Duplicate-safe student and attendance offline create replay was verified against MySQL; one operation UUID produced one canonical row.
- WPF create/update/delete command paths enqueue network failures into the existing SQLite sync queue.
- WPF module form payload names and enum values were audited against the PHP/MySQL schema and corrected for fee structures, payments, exam subjects, library loans, and transport assignments.
- PHP server-side validation now rejects timetable overlaps, invalid library loan dates, and inconsistent payroll net calculations.
- Android exposes recorded sync conflicts for local review and acknowledgement without claiming server-side conflict resolution.

## Reasons no module is marked complete

The WPF source now defines module-specific field groups, typed controls, client validation, backend-aligned resources, and offline queue fallback for the ERP surface. Because Windows/.NET 8 is unavailable, every WPF row is explicitly `Source Implemented — Native Build Unverified`; no WPF module is fully verified or production-ready. The Android implementation has teacher attendance, students, timetable, notices, marks, results, push, pull cursor, local cache, and conflict-record foundations, but Android compilation and complete teacher workflow/runtime testing remain unavailable. PHP domain rules and Web Admin typed forms are implemented, while individual module transaction, permission, error, and end-to-end tests remain required. Therefore no module is marked complete under the strict gate.

## Latest verification run

The authenticated smoke test returned HTTP 200 success envelopes for the requested top-level routes, including the added resources `fee-structures`, `payments`, `exam-subjects`, `payroll-items`, `library-loans`, and `transport-assignments`. An unauthenticated request returned HTTP 401 with `AUTH_REQUIRED`.

The Web Admin typecheck and production build pass. The PHP source passes syntax validation, including the newly merged timetable-overlap, library-date, and payroll-net business rules. Authenticated sync status/pull returned cursor `2`, two canonical tenant-scoped changes, and `has_more=false`; duplicate-safe attendance replay remained one MySQL row. Native WPF and Android compilation remain unverified because the available sandbox does not contain the .NET SDK or Gradle/Android SDK. The WPF UI now has module-specific field definitions, schema-compatible payloads, validation, API mutations, and existing SQLite queue fallback, but all WPF rows remain source-only. The Android UI has teacher-facing students, attendance, timetable, notices, marks, results, cursor pull, cache, conflict records, and a local conflict-review/acknowledgement surface; complete server conflict resolution and native runtime/build acceptance remain pending.

No module has been marked complete under the requested strict gate.
