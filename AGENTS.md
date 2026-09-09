# AGENTS.md — MDM - Magic Data Management

## Product

**Product name:** MDM - Magic Data Management

MDM is a **healthcare workforce management / HRM / EVV-lite** application.

It is **not** generic Master Data Management. Do **not** introduce product catalogs, customer-master matching, duplicate-merging stewards, or other master-data concepts unless explicitly instructed.

## Domain (permanent)

Primary operational entities include:

- Admins, Supervisors, Employees, DSPs (Direct Support Professionals)
- Clients receiving services
- Employee credentials, documents, training, compliance
- Client authorizations, care plans, care-plan task templates
- Scheduled shifts, visits, DSP clock-in / clock-out, attendance
- EVV-style visit records, visit tasks, skip reasons, exceptions
- Handover notes, payroll-hour exports
- Internal messages, announcements, reports, audit/activity history

Initial roles:

- **ADMIN** — full administrative and HR/operations access
- **SUPERVISOR** — manages assigned DSPs/clients, visits, attendance, exceptions, handovers
- **DSP** — own schedule, clock-in/out, care-plan tasks, notes, skip reasons, handover, clock-out

This is a **standalone** application. It is **not** a Sandata integration.

## Stack

- **Backend:** Laravel (PHP). Conventional Laravel architecture only.
- **Frontend:** React + TypeScript + Inertia.
- **Do not** introduce a second Node/Express/Nest (or similar) backend.

## Engineering rules

- Inspect existing code before modifying it.
- Preserve working functionality.
- Use **migrations** for schema changes.
- Use **Eloquent** relationships.
- Use **Form Requests** for meaningful validation.
- Use **Policies/Gates** for authorization; enforce permissions **server-side**.
- Never substitute fake UI data for functionality that should reasonably be database-backed.
- After substantial changes, run appropriate tests and frontend build checks.
- Prefer `npm.cmd` on Windows when PowerShell blocks `npm.ps1`.

## Local development notes

- Local URL: `http://mdm-magic-data-management.test`
- SQLite is acceptable for local development until a production database is explicitly chosen.
- Production frontend builds must not depend on downloading fonts from external CDNs (for example `fonts.bunny.net`) at build time.

## Documentation

Project specifications live under `docs/`. Keep them aligned with this healthcare MDM domain.
