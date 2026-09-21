# Reporting

## Purpose

Operational and management reports for workforce, visits, attendance, compliance, and exceptions.

## Phase 3D-2 reports

Reports are database-backed views over existing attendance, visit, task, exception, and compliance records. Calculations reuse AttendanceStatusService and ComplianceService. Times use the organization operational timezone from Settings. Original EVV clock timestamps are never rewritten.

| Report | Source |
|--------|--------|
| Employee Attendance | Scheduled visits with attendance status and effective clocks |
| Client Visits | Visit execution records |
| Late / Missed Visits | Attendance late/missed status (no grace period) |
| Task Completion | Visit tasks |
| Credential / Training Expiration | Compliance classifier (expired / expiring soon) |
| Compliance | Credential and training status |
| Exceptions | Visit exceptions |
| Payroll Hours | Completed visits aggregated by employee (hours only) |

Filters (where relevant): date range, employee/DSP, client, supervisor (admin), status.

CSV export is available for these reports using the same filters.

Branded PDF output uses the shared MDM document framework (`barryvdh/laravel-dompdf` on the server). The first printable documents are the Client Visit & Task Handout, Completed Visit Report, and Employee Hours & Attendance Report. PDF routes reuse the same authorization and reporting scope as the matching screens.

## Permissions

- **ADMIN** — organization-wide reports and CSV export, including payroll hours CSV
- **SUPERVISOR** — permitted caseload only; may view payroll hours for that caseload; cannot export payroll CSV
- **DSP** — no Reports module
