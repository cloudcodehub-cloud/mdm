# Roles and permissions

## Initial roles

Roles live on `users.role` as a PHP backed enum (`App\Enums\Role`):

- **ADMIN** — full administrative and HR/operations access
- **SUPERVISOR** — assigned DSPs/clients, visits, attendance, exceptions, progress, handovers, operational issues
- **DSP** — own schedule, assigned client selection, clock-in/out, care-plan tasks, notes/measurements, mandatory skip reasons, visit summary/handover

This is the simplest Laravel approach for three application roles. Authorization is enforced with Policies (`EmployeePolicy`, `ClientPolicy`, `ClientDspAssignmentPolicy`) and `User` helpers (`isAdmin()`, `isSupervisor()`, `isDsp()`). Never rely on UI hiding alone.

## Phase 1A policy scope

- Admin: view/create/update employees, clients, and assignments. Cannot hard-delete employees or clients.
- Supervisor: view assigned DSP reports (`employees.supervisor_id`) and assigned clients (`clients.supervisor_id`).
- DSP: view own employee profile and currently assigned clients (active `client_dsp_assignments`).

Module-specific permissions for visits, EVV, payroll, and messaging will be added with those features.

## Account provisioning note

Production accounts should be admin-provisioned. Unrestricted public self-registration is not the intended long-term model. During development, new registrations default to the **DSP** role and do not create an employee profile automatically.

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
