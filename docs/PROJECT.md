# Project definition — MDM - Magic Data Management

## What this product is

**MDM - Magic Data Management** is a healthcare workforce and service-delivery management platform designed around employees/DSPs, supervisors, clients, HR/compliance, scheduling, attendance and EVV-lite visit workflows.

It is a **standalone** application. It is **not** a Sandata integration.

MDM is **not** generic Master Data Management. Do not treat this product as a product/customer master-data system.

## Core Phase 1 application areas

- Dashboard
- Employees
- Clients
- Supervisors
- DSP Operations
- Attendance / EVV
- Compliance
- Reports
- Messages / Announcements

## Initial roles

| Role | Purpose |
|------|---------|
| ADMIN | Full administrative and HR/operations access |
| SUPERVISOR | Manages and monitors assigned DSPs, assigned clients, scheduled visits, attendance, exceptions, visit/task progress, handovers and operational issues |
| DSP | Views their own schedule, selects the assigned client, clocks in, performs care-plan tasks, records notes/measurements where applicable, handles task skips with mandatory reasons, completes visit summary/handover and clocks out |

## Account provisioning (production intent)

Public self-registration is **not** the intended final production account-provisioning model.

In production, accounts should ultimately be **provisioned and controlled by administrators** rather than allowing unrestricted public registration.

The starter kit’s registration routes may remain available during early development; they should not be treated as the long-term production model. Do not rewrite authentication solely for this note in Phase 0.

## Local setup (Phase 0)

- App name: `MDM - Magic Data Management`
- Local URL: `http://mdm-magic-data-management.test`
- Database for local development: SQLite
