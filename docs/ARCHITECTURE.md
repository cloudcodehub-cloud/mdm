# Architecture

## Stack

- **Laravel** — backend, routing, auth, validation, authorization, persistence
- **Inertia + React + TypeScript** — frontend screens
- **SQLite** — local development database (production database TBD)

There is **one** application backend (Laravel). Do not add a separate Node/Express/Nest API service.

## High-level shape

Browser → Laravel (web routes) → Inertia page props → React pages

Business rules, permissions, and data writes happen on the server.

## Domain modules (planned)

Specifications for each area live in sibling docs. Implementation comes after Phase 0 approval.

- Employees / HR
- Clients
- Supervisors
- DSP operations
- Attendance / EVV-lite visits
- Compliance
- Payroll-hour exports
- Messaging / announcements — 1-to-1 internal chat, in-app notifications (polling), and role-scoped announcements. No WebSockets yet.
- Reporting
- Audit / activity history

## Conventions

- Migrations for schema
- Eloquent models and relationships
- Form Requests for validation
- Policies/Gates for authorization
- Server-side permission enforcement
