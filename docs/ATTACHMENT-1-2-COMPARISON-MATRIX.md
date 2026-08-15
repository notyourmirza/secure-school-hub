# Attachment 1 vs Attachment 2 vs Canonical Project

## Audit conclusion

Both supplied attachments are **requirements and audit prompts**, not source packages. Neither contains verifiable PHP controllers, SQL migrations, Web Admin files, WPF project files, Android source, compiled artifacts, or test output that can be safely merged. Their objectively stronger contribution is the acceptance standard, not an alternative implementation. The canonical project remains the sole implementation source.

The two attachments overlap heavily. Attachment 1 is broader and defines a final production-readiness scope, including additional modules such as Academic Sessions, Teacher Attendance, Notifications, Events, Certificates, Settings, Super Admin, Subscriptions, Subscription Limits, Device Management, and Audit Logs. Attachment 2 is more focused on the audit/merge procedure, comparison labels, security review, tenant tests, offline test matrix, and the required merge-report structure. Both reinforce preservation of the current sync architecture and the rule that no module may be marked complete merely because a route, table, button, or screen exists.

## Status definitions

| Label | Meaning |
|---|---|
| 🟢 COMPLETE | Implementation and required evidence pass every applicable acceptance layer. |
| 🟡 PARTIAL | Some connected implementation exists, but one or more required layers or tests remain. |
| 🔴 MISSING | No verified implementation exists for the requested capability. |
| ⚠️ NOT VERIFIED | Source or design exists, but required native/build/runtime/environment evidence is unavailable. |
| ❌ BROKEN | The available implementation fails a verified check. |

## Architecture and cross-cutting comparison

| Area | Attachment 1 | Attachment 2 | Canonical project | Stronger implementation | Merge decision |
|---|---|---|---|---|---|
| PHP REST API and MySQL | Requires one PHP API and central MySQL | Requires source audit, transactions, consistent errors, and no direct client DB access | 🟡 Partial but executable; PHP lint and authenticated smoke routes pass | Canonical project, because attachments contain no code | KEEP CURRENT; apply requirements as tests |
| Multi-tenancy | Requires server-side `school_id` isolation and two-school tests | Explicitly requires read/update/delete/report/settings isolation tests | 🟡 Partial; tenant derivation and scoped queries exist, full two-school fixture is pending | Canonical security model | IMPROVE CURRENT with additive isolation tests |
| Authentication and RBAC | Requires role matrix and 401/403 denial tests | Adds security comparison for expiry, logout, CORS, rate limits, and sensitive errors | 🟡 Partial; auth, refresh/logout, permissions, and rate limiting exist, complete role tests remain | Canonical implementation plus Attachment 2 test scope | KEEP CURRENT; expand tests |
| Offline synchronization | Requires pull, cache, retry, conflicts, reconnect, and duplicate replay | Explicitly says current sync is canonical and forbids a second engine | 🟡 Partial; `operation_uuid`, `client_uuid`, queue, transactional push, cursor pull, cache/conflict foundations exist; full UX/tests pending | Canonical implementation | PRESERVE CURRENT EXACTLY; improve incrementally |
| Web Admin | Requires real forms, validation, states, API integration, and permission-aware actions | Adds comparison of search, filters, pagination, responsive behavior, and no mock data | 🟡 Partial; real API-backed permission-aware UI builds successfully, full module-specific verification remains | Canonical project | KEEP CURRENT; improve module coverage |
| WPF | Requires native module workflows and MVVM; explicitly permits source-only status if Windows unavailable | Requires module-specific forms, ViewModels, queue/reconnect tests, and no generic JSON editor | ⚠️ Source Implemented — Native Build Unverified; module-specific definitions and queue fallback exist | Canonical source; no attachment code exists | KEEP CURRENT; native verification remains blocked |
| Android | Requires complete teacher app, local cache, pull, cursor, conflict handling, and WorkManager | Requires APK/runtime tests when SDK exists and explicit Not Verified otherwise | ⚠️ Source Implemented — Native Build Unverified; teacher workflows and sync foundations exist | Canonical source; no attachment code exists | KEEP CURRENT; native verification remains blocked |
| Testing and production readiness | Defines extensive E2E and production gate | Defines audit labels and final merge report | 🟡 Partial; PHP/Web/API/sync checks exist, full matrix remains | Attachment 2’s audit format plus Attachment 1’s broader scope | MERGE BOTH INTO DOCUMENTATION |

## Module comparison

| Module or capability | Attachment 1 requirement | Attachment 2 requirement | Canonical project status | Merge decision |
|---|---|---|---|---|
| Students | Full CRUD, profile, assignment, validation, duplicate prevention, sync | Same, with explicit invalid-FK and tenant tests | 🟡 Partial; API/Web/WPF/sync foundation exists; complete E2E/native evidence pending | IMPROVE CURRENT |
| Teachers | Identity, subject/class assignment, attendance, timetable, permissions | Same, with actual workflow requirement | 🟡 Partial; API/Web/WPF source exists; full business workflow tests pending | IMPROVE CURRENT |
| Employees / HR | Employee records, salary, joining/status, attendance, payroll relationship | Same plus role/department validation | 🟡 Partial; API/Web/WPF source exists; HR/payroll rules pending | IMPROVE CURRENT |
| Academic Sessions | Session relationships and validation | Included in module audit | 🔴 Missing or not verified as a complete feature | ADDITIVE IMPLEMENTATION REQUIRED |
| Classes / Sections / Subjects | Relational academic structure, FK and duplicate validation | Same | 🟡 Partial; routes/schema/UI surface exist; relationship tests pending | IMPROVE CURRENT |
| Attendance / Teacher Attendance | Student and teacher statuses, offline replay, pull/cache/conflict | Duplicate prevention and full offline matrix | 🟡 Partial; duplicate-safe attendance and Android flow verified; teacher attendance/full conflict UX pending | IMPROVE CURRENT |
| Timetable | Time ranges and overlap prevention | Conflict validation required server-side | 🟡 Partial; route/UI and client date-time validation exist; server conflict tests pending | IMPROVE CURRENT |
| Fees, invoices, payments, discounts, fines | Accounting formula, balance, receipts, duplicate/overpayment prevention | Same domain rules and transaction requirement | 🟡 Partial; fee structures/payments resources and validation exist; full invoice accounting remains | IMPROVE CURRENT |
| Expenses | Categories, amounts, approval/status, reports, transactions | Same | 🟡 Partial; CRUD surface exists; accounting/approval tests pending | IMPROVE CURRENT |
| Exams / Exam Subjects / Marks / Results | Persisted marks, grades, results from marks, invalid-FK/max checks | Same, with marks <= maximum and persisted-result rule | 🟡 Partial; routes/Web/Android reads exist; complete grading/result transaction tests pending | IMPROVE CURRENT |
| Payroll | Periods, components, gross/net, payslips, closing | Gross minus deductions plus allowances and closed-period protection | 🟡 Partial; payroll runs/items resources and validation exist; full processing/closing/payslips pending | IMPROVE CURRENT |
| Library | Books, copies, issue/return, due dates, fines, borrower, availability | Cannot issue unavailable copy | 🟡 Partial; books/loans surface and date validation exist; inventory/lending transaction tests pending | IMPROVE CURRENT |
| Transport | Vehicles, drivers, routes, stops, assignments, capacity | Invalid route/capacity prevention | 🟡 Partial; routes/assignments surface and validation exists; full capacity/relationship tests pending | IMPROVE CURRENT |
| Notices / Notifications | Publish, audience, read state, Android/Web retrieval | Same plus notification security review | 🟡 Partial; notices API/Web/Android reads exist; delivery/read-state workflow pending | IMPROVE CURRENT |
| Events | Complete event CRUD and workflow | Included in audit list | 🔴 Missing or not verified | ADDITIVE IMPLEMENTATION REQUIRED |
| Reports | Real tenant/permission/date-filtered queries and export | No fake data; report query and permission tests | 🟡 Partial; persisted report-run surface exists, complete real query/export evidence pending | IMPROVE CURRENT |
| Certificates | Dynamic student data, unique certificate number, print/export | Included in audit list | 🔴 Missing or not verified | ADDITIVE IMPLEMENTATION REQUIRED |
| Settings | Tenant settings with validation and isolation | Included in security/production audit | 🟡 Partial; configuration foundation exists; complete tenant settings workflow pending | IMPROVE CURRENT |
| Super Admin | Schools, users, plans, trials, usage, devices, logs, isolation | Separate role and authorization tests | 🟡 Partial foundation; complete workflows not verified | IMPROVE CURRENT |
| Subscriptions / Limits | Lifecycle states and server-side usage limits | Included in audit list | 🟡 Partial foundation; enforcement and tests pending | IMPROVE CURRENT |
| Device Management | Registration, status, activation/deactivation, unauthorized-device denial | Included in audit list and sync security | 🟡 Partial foundation; full endpoint/workflow tests pending | IMPROVE CURRENT |
| Audit Logs | Required in final production scope | Included in audit/security review | 🟡 Partial foundation; complete query/permission/export tests pending | IMPROVE CURRENT |

## Merge decisions

No source code, migrations, binaries, or configuration from either attachment was merged because none was supplied. The following was merged conceptually into the canonical project documentation: Attachment 1’s expanded module scope and production gate; Attachment 2’s independent-audit method, status labels, security checklist, two-school authorization tests, offline matrix, and final report requirements.

No competing authentication, RBAC, tenant, database, API, WPF, Android, or sync architecture was introduced. Existing canonical implementations were preserved. The current project’s actual implementation and verification evidence remain authoritative over statements in the attachments.

## Outstanding blockers

The largest blockers are not attachment conflicts. They are missing evidence and incomplete domain workflows: full module-specific API/business-rule tests, two-school isolation fixtures, role-by-role authorization tests, complete offline update/delete/conflict resolution, reporting/export behavior, missing modules such as Academic Sessions, Events, and Certificates, and native WPF/Android build/runtime verification. These remain explicitly incomplete or not verified in the authoritative acceptance matrix.
