# Roles and permissions

## Initial roles

Roles live on `users.role` as a PHP backed enum (`App\Enums\Role`):

- **ADMIN** — full administrative and HR/operations access
- **SUPERVISOR** — assigned DSPs/clients, visits, attendance, exceptions, progress, handovers, operational issues
- **DSP** — own schedule, assigned client selection, clock-in/out, care-plan tasks, notes/measurements, mandatory skip reasons, visit summary/handover

This is the simplest Laravel approach for three application roles. Authorization is enforced with Policies (`EmployeePolicy`, `ClientPolicy`, `ClientDspAssignmentPolicy`, `EmployeeCredentialPolicy`, `EmployeeTrainingPolicy`, `ClientAuthorizationPolicy`, `ShiftTemplatePolicy`, `CarePlanPolicy`, `CarePlanTaskTemplatePolicy`, `ScheduledVisitPolicy`, `SkipReasonPolicy`) and `User` helpers (`isAdmin()`, `isSupervisor()`, `isDsp()`). Never rely on UI hiding alone.

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
- Supervisor: view care plans/tasks for assigned clients; view scheduled visits for assigned clients or assigned DSP reports; view skip reasons. Cannot create or update them.
- DSP: view care plans/tasks for currently assigned clients, own scheduled visits, and skip reasons.

Module-specific permissions for clock-in, EVV visit records, payroll, and messaging will be added with those features.

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
