# Compliance

## Purpose

Track workforce compliance related to credentials, documents, training, and operational requirements.

## Phase 1B-1 foundation

Credential and training records now exist on employees:

- `employee_credentials` — type, issuer, issue/expiry, status
- `employee_trainings` — title, provider, hours, completion/expiry, status

Validity helpers treat a credential as currently valid when status is `active` and `expires_on` is empty or today-or-later. Completed training is currently valid under the same expiry rule.

Full compliance dashboards, document completeness, and aggregated employee/client compliance status are still later work.

## Planned later

- Document completeness
- Compliance status visibility for admins and supervisors
- Automated expiry alerts
