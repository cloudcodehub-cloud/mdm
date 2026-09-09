# Database

## Current (Phase 0)

- Driver: **SQLite** (`database/database.sqlite`)
- Auth-related tables from the Laravel starter are migrated (users, cache, jobs, passkeys, two-factor columns)

## Direction

- Use Laravel migrations for all schema changes.
- Use Eloquent relationships between domain models.
- Do not invent a complex schema in Phase 0.
- Future domain tables will cover employees/DSPs, clients, credentials, documents, training, compliance, authorizations, care plans, shifts, visits, attendance, EVV-style records, tasks, exceptions, handovers, payroll exports, messages, announcements, and audit history.

## Production database

MySQL/PostgreSQL migration is deferred until explicitly requested. Keep SQLite for local development for now.
