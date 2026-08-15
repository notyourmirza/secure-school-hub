# Fourth Attachment Comparison Matrix

**Audit basis:** The fourth attachment is a detailed audit and merge specification. It does not include an independently extractable source tree, database dump, compiled client, or verifiable API implementation. Therefore, its implementation status is recorded as **Missing / Not verifiable**, while its requirements are used as the merge and testing checklist. The current project was inspected from its actual files and local API/Web Admin verification records.

| Feature or area | Current project status | Fourth attachment status | Merge decision | Evidence / action |
|---|---|---|---|---|
| PHP REST API | 🟡 Partial | 🟢 Requirement specified; no source implementation supplied | Keep current and improve | Current PHP API, migrations, auth, module router, and sync routes retained. |
| Central MySQL | 🟢 Partial | 🟢 Requirement specified; no schema supplied | Keep current and improve | Additive migrations 001–003 retained; local schema exercised. |
| Multi-tenancy / `school_id` | 🟡 Partial | 🟢 Requirement specified; no implementation supplied | Improve current | Tenant derivation and tenant-safe queries retained; second-school isolation fixture remains required. |
| Authentication | 🟡 Partial | 🟢 Requirement specified; no implementation supplied | Keep current and improve | Password hashing, bearer tokens, refresh/logout paths retained; expiry tests remain required. |
| RBAC | 🟡 Partial | 🟢 Requirement specified; no implementation supplied | Keep current and improve | Explicit permissions returned at login and enforced in API/Web Admin; teacher-vs-admin matrix still needs full tests. |
| Refresh tokens | 🟡 Partial | 🟢 Requirement specified; no implementation supplied | Keep current and improve | Existing rotation/revocation implementation retained; full expiry/reuse tests remain. |
| Offline sync | 🟡 Partial | 🟢 Requirement specified; no implementation supplied | Preserve current exactly | `client_uuid`, `operation_uuid`, durable queues, WorkManager, WPF SyncService, replay protection, and transactional push are preserved. Pull sync and complete conflict UX remain incomplete. |
| Students | 🟡 Partial | 🟢 Requirement specified; no source supplied | Keep current and improve | API, Web Admin CRUD, WPF generic CRUD, and sync foundation exist; domain-specific forms/tests remain. |
| Teachers | 🟡 Partial | 🟢 Requirement specified; no source supplied | Keep current and improve | API and Web Admin route exist; native admin forms and teacher identity workflows remain. |
| Employees | 🟡 Partial | 🟢 Requirement specified; no source supplied | Keep current and improve | API/Web Admin surface exists; payroll/HR business rules remain. |
| Classes / Sections / Subjects | 🟡 Partial | 🟢 Requirement specified; no source supplied | Keep current and improve | Routes and schema exist; relationship validation and complete UI tests remain. |
| Academic sessions | 🔴 Missing | 🟢 Requirement specified | Additive implementation required | No verified complete client/API workflow in current project. |
| Attendance / Teacher attendance | 🟡 Partial | 🟢 Requirement specified | Keep current and improve | Attendance push is duplicate-safe and tested; full teacher attendance and offline update/delete remain. |
| Timetable | 🟡 Partial | 🟢 Requirement specified | Keep current and improve | Read/API/Web Admin coverage exists; scheduling validation and native workflows remain. |
| Fees / invoices / payments / discounts / fines | 🟡 Partial | 🟢 Requirement specified | Improve current | Broad resources exist; accounting transactions, balances, receipts, and full CRUD tests remain. |
| Expenses | 🟡 Partial | 🟢 Requirement specified | Improve current | Broad resource exists; approval/accounting rules remain. |
| Exams / Marks / Results | 🟡 Partial | 🟢 Requirement specified | Improve current | Routes, persisted results table, Web Admin routes, and Android reads exist; grading rules and complete workflows remain. |
| Payroll | 🟡 Partial | 🟢 Requirement specified | Improve current | Payroll resource exists; salary calculation, payslips, and period closing remain. |
| Library | 🟡 Partial | 🟢 Requirement specified | Improve current | Library resource exists; lending/return/availability rules remain. |
| Transport | 🟡 Partial | 🟢 Requirement specified | Improve current | Transport resource exists; assignments, routes, and billing rules remain. |
| Notices / notifications | 🟡 Partial | 🟢 Requirement specified | Improve current | Notices resource and Android/Web reads exist; delivery/read-state infrastructure remains. |
| Events | 🔴 Missing | 🟢 Requirement specified | Additive implementation required | No verified complete event workflow. |
| Reports | 🟡 Partial | 🟢 Requirement specified | Improve current | Reports route exists; actual report queries, export, and permission tests remain. |
| Certificates | 🔴 Missing | 🟢 Requirement specified | Additive implementation required | No verified complete certificate workflow. |
| Settings | 🟡 Partial | 🟢 Requirement specified | Improve current | Configuration exists; complete tenant settings UI and validation remain. |
| Super Admin / subscriptions / limits | 🟡 Partial | 🟢 Requirement specified | Improve current | Foundation tables and concepts exist; server-side enforcement and full Super Admin workflows remain. |
| Web Admin | 🟡 Partial | 🟢 Requirement specified | Keep current and improve | Real API-backed CRUD routing and permission-aware actions build successfully; module-specific UX remains. |
| WPF admin client | 🟡 Partial | 🟢 Requirement specified | Preserve current and improve | .NET 8 WPF, SQLite queue, SyncService, and generic CRUD actions retained; native build is not verified. |
| Android teacher app | 🟡 Partial | 🟢 Requirement specified | Preserve current and improve | Kotlin, local operation store, WorkManager, students/attendance/timetable/notices/marks/results reads exist; APK build and complete cache remain unverified. |
| Security | 🟡 Partial | 🟢 Requirement specified | Improve current | CORS allowlist, rate limiter, RBAC, tenant checks, and prepared statements retained; full security test suite remains. |
| Configuration | 🟡 Partial | 🟢 Requirement specified | Improve current | Android API URL is configurable; production secrets/domain and client deployment configuration remain. |
| Testing / production readiness | 🟡 Partial | 🟢 Requirement specified | Do not claim complete | PHP/Web/API smoke tests pass; native builds, full CRUD matrix, tenant fixture, and complete offline tests remain. |

## Decision summary

The fourth attachment contributes a **requirements and audit framework**, not a stronger competing implementation. No third-party source code was blindly copied or allowed to replace current working files. The current offline-sync foundation remains canonical. Any future implementation from a source package must be adapted to these contracts and must pass the completion gate before being marked complete.
