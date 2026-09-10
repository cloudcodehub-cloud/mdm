# Database

## Current (Phase 1A + Phase 1B-1)

- Driver: **SQLite** (`database/database.sqlite`)
- Auth-related tables from the Laravel starter (users, cache, jobs, passkeys, two-factor columns)
- Domain tables: `employees`, `clients`, `client_dsp_assignments`, `employee_credentials`, `employee_trainings`, `client_authorizations`, `shift_templates`
- `users.role` stores `ADMIN`, `SUPERVISOR`, or `DSP`

## Conventions

- Use Laravel migrations for all schema changes.
- Use Eloquent relationships between domain models.
- Do not hard-delete employees or clients; retain records with status and soft deletes.
- DSP ↔ client coverage uses `client_dsp_assignments` rows (never comma-separated IDs).
- Overnight shifts are modeled by start/end times on `shift_templates`. When `ends_at` is earlier than or equal to `starts_at`, the shift ends on the next calendar day (for example 11 p.m.–7 a.m.).

## Core tables

### users

Login accounts. `role` is a string enum (`ADMIN`, `SUPERVISOR`, `DSP`). Admins do not need an employee profile. Supervisor and DSP users typically link to one `employees` row.

### employees

Workforce/HR profiles. Unique `employee_number`. Optional unique `user_id`. Supervisor reporting is `employees.supervisor_id` → `employees.id`. Lifecycle: `employment_status` (`active`, `inactive`, `terminated`) plus `deleted_at` if a row is archived.

### clients

People receiving services. Not login users. Unique `client_number`. Assigned supervisor is `clients.supervisor_id` → `employees.id`. Status: `active`, `inactive`, `discharged`.

### client_dsp_assignments

Many-to-many history between DSP employees and clients. One DSP may have many clients; one client may have many DSPs. `status` (`active` / `inactive`) with `started_on` / `ended_on` supports current coverage and past assignments.

### employee_credentials

Workforce credentials (CPR, first aid, driver’s license, background check, medication administration, TB screening). Linked to `employees`. Status: `pending`, `active`, `expired`, `revoked`. Optional `issued_on` / `expires_on`. Records are retained; do not hard-delete for history.

### employee_trainings

Training completions and in-progress courses for an employee. Status: `in_progress`, `completed`, `expired`. Optional `completed_on`, `expires_on`, and `hours`.

### client_authorizations

Payer authorizations for a client. Unique `authorization_number`. Includes payer, service type, `starts_on` / `ends_on`, `authorized_units`, unit (`hour`, `visit`, `day`), and status (`pending`, `active`, `expired`, `exhausted`, `cancelled`).

### shift_templates

Reusable shift definitions used later for scheduling. Seeded templates: **7–3** (`07:00`–`15:00`), **3–11** (`15:00`–`23:00`), and **11–7** (`23:00`–`07:00`, overnight). Duration is derived from start/end times, not stored.

## Demo seed

`DemoSeeder` (called from `DatabaseSeeder`) loads fictional demo data, including credentials, training, authorizations, and the three shift templates.

Shared demo password: **`password`**

## Production database

MySQL/PostgreSQL migration is deferred until explicitly requested. Keep SQLite for local development for now.
