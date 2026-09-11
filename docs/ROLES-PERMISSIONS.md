# Roles and permissions

## Initial roles

Roles live on `users.role` as a PHP backed enum (`App\Enums\Role`):

- **ADMIN** — full administrative and HR/operations access
- **SUPERVISOR** — assigned DSPs/clients, visits, attendance, exceptions, progress, handovers, operational issues
- **DSP** — own schedule, assigned client selection, clock-in/out, care-plan tasks, notes/measurements, mandatory skip reasons, visit summary/handover

This is the simplest Laravel approach for three application roles. Authorization is enforced with Policies (`EmployeePolicy`, `ClientPolicy`, `ClientDspAssignmentPolicy`, `EmployeeCredentialPolicy`, `EmployeeTrainingPolicy`, `ClientAuthorizationPolicy`, `ShiftTemplatePolicy`, `CarePlanPolicy`, `CarePlanTaskTemplatePolicy`, `ScheduledVisitPolicy`, `SkipReasonPolicy`, `VisitPolicy`, `VisitExceptionPolicy`, `AttendanceCorrectionPolicy`) and `User` helpers (`isAdmin()`, `isSupervisor()`, `isDsp()`). Never rely on UI hiding alone.

## Phase 1A policy scope

- Admin: view/create/update employees, clients, and assignments. Cannot hard-delete employees or clients.
- Supervisor: view assigned DSP reports (`employees.supervisor_id`) and assigned clients (`clients.supervisor_id`).
- DSP: view own employee profile and currently assigned clients (active `client_dsp_assignments`).

## Phase 1B-1 policy scope

- Admin: create/update credentials, training, authorizations, and shift templates. Cannot hard-delete these records.
- Supervisor: view credentials/training for assigned DSP reports; view authorizations for assigned clients; view shift templates. Cannot create or update them.
- DSP: view own credentials/training, authorizations for currently assigned clients, and shift templates.

## Phase 1B-2 policy scope

- Admin: create/update care plans, task templates, scheduled visits, and skip reasons. Cannot hard-delete these records.
- Supervisor: view care plans/tasks for assigned clients; view scheduled visits for assigned clients or assigned DSP reports; view skip reasons. Cannot create or update care plans, task templates, or skip reasons.
- DSP: view care plans/tasks for currently assigned clients, own scheduled visits, and skip reasons.

## Phase 3B-1 policy scope

- Admin: create/update scheduled visits for any client and DSP.
- Supervisor: create/update scheduled visits only when the client is on their caseload (`clients.supervisor_id`) and the DSP is their report or is actively assigned to that client. They may view visits for assigned clients, assigned DSP reports, or visits listing them as supervisor of record.
- DSP: view own scheduled visits only. Cannot create or update schedules.

## Phase 3B-2A clock-in policy scope

- DSP: clock in only on their own scheduled visit, and only while their employee record is an active DSP. One active visit at a time.
- Admin / Supervisor: may view in-scope active visit records (admin: all; supervisor: same scheduled-visit caseload rules). Cannot clock in.

## Phase 3B-2B clock-out and task policy scope

- DSP: complete/skip own in-progress visit tasks, save visit notes/handover, and clock out own in-progress visit. Cannot act on another DSP's visit. Duplicate clock-out is blocked.
- Admin / Supervisor: may view in-scope visit records. Cannot complete tasks or clock out.

## Phase 3C supervisor operations and exception review

- Admin: view the operations board; view and review/resolve any visit exception; view the supervisor directory and each supervisor's caseload board. Cannot alter DSP clock events.
- Supervisor: view the operations board, visits, and exceptions only for their caseload (assigned DSPs, assigned clients, or supervisor of record). May mark in-scope exceptions reviewed or resolved. Cannot open another supervisor's caseload. Cannot clock in, complete tasks, or clock out.
- DSP: cannot open operations, exception review, or the supervisor directory.

## Phase 3D-1 attendance and compliance

- Admin: view all attendance; approve/reject correction requests; apply corrections directly with a mandatory reason. Original visit clock timestamps are immutable. View all workforce credential/training compliance.
- Supervisor: view attendance and submit correction requests only for permitted caseload. Cannot approve corrections or change clock events. View compliance for assigned DSP reports.
- DSP: view own attendance only. Cannot submit or review corrections. No Compliance module access; own credentials/training remain on the employee profile.

Module-specific permissions for payroll and messaging will be added with those features.

## Account provisioning note

Accounts are admin-provisioned. Public self-registration is disabled.

ADMIN users may sign in without an employee profile. SUPERVISOR and DSP users linked to an employee may sign in only while that employee’s `employment_status` is **active**. Inactive and terminated employees keep their User and Employee records; login is blocked server-side.

## Demo logins

Password for all demo accounts: **`password`**

| Role | Email |
|------|--------|
| ADMIN | `admin@mdm.test` |
| SUPERVISOR | `jordan.hale@mdm.test` |
| SUPERVISOR | `priya.nair@mdm.test` |
| DSP | `maya.chen@mdm.test` |
| DSP | `luis.ortega@mdm.test` |
| DSP | `nina.brooks@mdm.test` |
| DSP | `owen.patel@mdm.test` |
| DSP (terminated employee) | `sara.kim@mdm.test` |
