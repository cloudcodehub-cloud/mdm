# Database

## Current (Phase 1A)

- Driver: **SQLite** (`database/database.sqlite`)
- Auth-related tables from the Laravel starter (users, cache, jobs, passkeys, two-factor columns)
- Domain tables: `employees`, `clients`, `client_dsp_assignments`
- `users.role` stores `ADMIN`, `SUPERVISOR`, or `DSP`

## Conventions

- Use Laravel migrations for all schema changes.
- Use Eloquent relationships between domain models.
- Do not hard-delete employees or clients; retain records with status and soft deletes.
- DSP ↔ client coverage uses `client_dsp_assignments` rows (never comma-separated IDs).

## Core tables

### users

Login accounts. `role` is a string enum (`ADMIN`, `SUPERVISOR`, `DSP`). Admins do not need an employee profile. Supervisor and DSP users typically link to one `employees` row.

### employees

Workforce/HR profiles. Unique `employee_number`. Optional unique `user_id`. Supervisor reporting is `employees.supervisor_id` → `employees.id`. Lifecycle: `employment_status` (`active`, `inactive`, `terminated`) plus `deleted_at` if a row is archived.

### clients

People receiving services. Not login users. Unique `client_number`. Assigned supervisor is `clients.supervisor_id` → `employees.id`. Status: `active`, `inactive`, `discharged`.

### client_dsp_assignments

Many-to-many history between DSP employees and clients. One DSP may have many clients; one client may have many DSPs. `status` (`active` / `inactive`) with `started_on` / `ended_on` supports current coverage and past assignments.

## Demo seed

`DemoSeeder` (called from `DatabaseSeeder`) loads fictional demo data.

Shared demo password: **`password`**

## Production database

MySQL/PostgreSQL migration is deferred until explicitly requested. Keep SQLite for local development for now.
