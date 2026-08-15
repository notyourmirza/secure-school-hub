# ERP Module Baseline

## Scope

The requested ERP modules are Teachers, Employees, Classes, Sections, Subjects, Attendance, Timetable, Fees, Expenses, Exams, Marks, Results, Payroll, Library, Transport, Notices, and Reports. Existing offline synchronization remains the foundation and must not be replaced.

## Baseline audit

| Module | Current backend | Current WPF | Current Android | Current Web Admin | Baseline status |
|---|---|---|---|---|---|
| Teachers | Partial schema only | No CRUD | No workflow | Placeholder route | Incomplete |
| Employees | Missing | No CRUD | No workflow | Placeholder route | Incomplete |
| Classes | Partial schema only | No CRUD | No workflow | Placeholder route | Incomplete |
| Sections | Partial schema only | No CRUD | No workflow | Placeholder route | Incomplete |
| Subjects | Partial schema only | No CRUD | No workflow | Placeholder route | Incomplete |
| Attendance | Partial schema only | Navigation label only | No workflow | Placeholder route | Incomplete |
| Timetable | Missing | No CRUD | No workflow | Placeholder route | Incomplete |
| Fees | Missing | Navigation label only | No workflow | Placeholder route | Incomplete |
| Expenses | Missing | No CRUD | No workflow | Placeholder route | Incomplete |
| Exams | Missing | No CRUD | No workflow | Placeholder route | Incomplete |
| Marks | Missing | No CRUD | No workflow | Placeholder route | Incomplete |
| Results | Missing | No CRUD | No workflow | Placeholder route | Incomplete |
| Payroll | Missing | No CRUD | No workflow | Placeholder route | Incomplete |
| Library | Missing | No CRUD | No workflow | Placeholder route | Incomplete |
| Transport | Missing | No CRUD | No workflow | Placeholder route | Incomplete |
| Notices | Partial schema only | No CRUD | No workflow | Placeholder route | Incomplete |
| Reports | Missing | No CRUD | No workflow | Placeholder route | Incomplete |

## Completion gate

A module can only be marked complete when its tenant-safe MySQL migration, PHP API routes, permission checks, validation, transactional/error behavior, WPF or Android client workflow, Web Admin CRUD where applicable, offline queue integration where mutations are required, duplicate/retry behavior, and automated or executable end-to-end verification are present. A navigation item, schema-only entity, or placeholder page does not count as implementation.
